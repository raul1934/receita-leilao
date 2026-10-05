<?php

namespace App\Services\Sle;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Cliente da API JSON pública usada pelo portal do SLE (a mesma que a SPA
 * Angular consome no navegador).
 */
class SleClient
{
    private ?float $ultimaRequisicao = null;

    /**
     * @param  array{base_url: string, timeout: int, retries: int, delay_ms: int, user_agent: string}  $config
     */
    public function __construct(private readonly array $config) {}

    /**
     * Dados do edital com a lista resumida de lotes ("listaLotes").
     */
    public function edital(EditalRef $ref): array
    {
        return $this->get("edital/{$ref->path()}");
    }

    /**
     * Detalhes de um lote: itens ("itensDetalhesLote") e imagens.
     */
    public function lote(EditalRef $ref, int $lote): array
    {
        return $this->get("lote/{$ref->path()}/{$lote}");
    }

    /**
     * PDF "Extrato do Leilão" de um edital encerrado, com o valor de
     * arrematação de cada lote. A API devolve o PDF em base64 num JSON.
     */
    public function extratoLeilao(EditalRef $ref): string
    {
        $dados = $this->get("edital/{$ref->path()}/extrato-leilao");
        $pdf = base64_decode((string) ($dados['data'] ?? ''), true);

        if ($pdf === false || ! str_starts_with($pdf, '%PDF')) {
            throw new SleException("O extrato do edital {$ref} não veio em PDF.");
        }

        return $pdf;
    }

    /**
     * Editais listados na página "Editais disponíveis" do portal, de todas as
     * situações. Para algumas situações o portal limita a listagem aos mais
     * recentes.
     *
     * @return list<array<string, mixed>>
     */
    public function editaisDisponiveis(): array
    {
        $dados = $this->get('editais-disponiveis');

        return collect($dados['situacoes'] ?? [])
            ->flatMap(fn (array $grupo) => $grupo['lista'] ?? [])
            ->values()
            ->all();
    }

    private function get(string $uri): array
    {
        $this->aguardarIntervalo();

        try {
            // Com uma única tentativa, retry() devolve a resposta de erro em vez de lançar.
            $json = $this->http()->get($uri)->throw()->json();
        } catch (ConnectionException $e) {
            throw new SleException("Falha de conexão com o SLE em {$uri}: {$e->getMessage()}", previous: $e);
        } catch (RequestException $e) {
            throw SleException::resposta($uri, $e->response, $e);
        } finally {
            $this->ultimaRequisicao = microtime(true);
        }

        if (! is_array($json)) {
            throw new SleException("Resposta inesperada do SLE em {$uri}.");
        }

        return $json;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->config['base_url'], '/').'/api')
            ->acceptJson()
            ->withUserAgent($this->config['user_agent'])
            ->timeout($this->config['timeout'])
            ->retry(
                max(1, $this->config['retries'] + 1),
                fn (int $tentativa) => $tentativa * 1000,
                fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()),
            );
    }

    /**
     * Respeita uma pausa mínima entre requisições para não sobrecarregar o
     * site da Receita.
     */
    private function aguardarIntervalo(): void
    {
        $intervalo = $this->config['delay_ms'] / 1000;

        if ($this->ultimaRequisicao === null || $intervalo <= 0) {
            return;
        }

        $restante = $this->ultimaRequisicao + $intervalo - microtime(true);

        if ($restante > 0) {
            Sleep::usleep((int) ($restante * 1_000_000));
        }
    }
}
