<?php

namespace App\Services\Sle;

use App\Models\Edital;
use App\Models\Lote;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lê um edital e seus lotes no SLE e grava (ou atualiza) no banco.
 * Reimportar o mesmo edital é seguro: os registros são atualizados.
 */
class EditalImporter
{
    public function __construct(private readonly SleClient $client) {}

    /**
     * @param  bool  $comDetalhes  Busca também itens e imagens de cada lote (uma requisição por lote).
     * @param  array{cidade?: string|null}  $extras  Dados que só aparecem na listagem de editais do portal.
     * @param  (callable(Lote, int, int): void)|null  $aoProcessarLote  Recebe o lote, a posição atual e o total.
     */
    public function importar(
        EditalRef $ref,
        bool $comDetalhes = true,
        array $extras = [],
        ?callable $aoProcessarLote = null,
    ): ResultadoImportacao {
        $dados = $this->client->edital($ref);

        $edital = DB::transaction(fn () => $this->salvarEdital($ref, $dados, $extras));

        $numeros = collect($dados['listaLotes'] ?? [])->pluck('nrAtribuido')->map(fn ($n) => (int) $n);
        $falhas = [];
        $detalhados = 0;

        if ($comDetalhes) {
            $lotes = $edital->lotes()->whereIn('numero', $numeros)->orderBy('numero')->get();

            foreach ($lotes as $indice => $lote) {
                try {
                    $detalhe = $this->client->lote($ref, $lote->numero);
                    DB::transaction(fn () => $this->salvarDetalhesLote($lote, $detalhe));
                    $detalhados++;
                } catch (SleException $e) {
                    $falhas[$lote->numero] = $e->getMessage();
                    Log::warning("Falha ao importar o lote {$lote->numero} do edital {$ref}", ['erro' => $e->getMessage()]);
                }

                if ($aoProcessarLote) {
                    $aoProcessarLote($lote, $indice + 1, $lotes->count());
                }
            }
        }

        return new ResultadoImportacao($edital->refresh(), $numeros->count(), $detalhados, $falhas);
    }

    private function salvarEdital(EditalRef $ref, array $dados, array $extras): Edital
    {
        $atributos = [
            'codigo' => $dados['edital'] ?? null,
            'situacao' => $dados['situacao'] ?? null,
            'tipo' => $dados['tipo'] ?? null,
            'orgao' => $dados['orgao'] ?? null,
            // Neste endpoint o campo "cidade" traz o nome da unidade executora.
            'unidade_nome' => $dados['cidade'] ?? null,
            'permite_pf' => (bool) ($dados['permitePF'] ?? false),
            'data_inicio_propostas' => $this->data($dados['dataInicioPropostas'] ?? null),
            'data_fim_propostas' => $this->data($dados['dataFimPropostas'] ?? null),
            'data_classificacao' => $this->data($dados['dataClassificacao'] ?? null),
            'data_abertura_lances' => $this->data($dados['dataAberturaLances'] ?? null),
            'forma_contato' => $dados['formaContato'] ?? null,
            'dados_publicacao' => $dados['dadosPublicacao'] ?? null,
            'dados' => Arr::except($dados, ['listaLotes']),
            'importado_em' => now(),
        ];

        if (filled($extras['cidade'] ?? null)) {
            $atributos['cidade'] = $extras['cidade'];
        }

        $edital = Edital::updateOrCreate(
            ['unidade' => $ref->unidade, 'numero' => $ref->numero, 'exercicio' => $ref->exercicio],
            $atributos,
        );

        foreach ($dados['listaLotes'] ?? [] as $item) {
            $lote = $edital->lotes()->firstOrNew(['numero' => (int) $item['nrAtribuido']]);
            $lote->fill([
                'sequencial' => $item['loleNrSq'] ?? null,
                'tipo' => $item['tipo'] ?? null,
                'situacao' => $item['situacaoLote'] ?? null,
                'valor_minimo' => $item['valorMinimo'] ?? null,
                'valor_avaliacao' => $item['valorAvaliacao'] ?? null,
                'permite_pf' => (bool) ($item['permitePF'] ?? false),
            ]);
            $lote->dados = array_merge($lote->dados ?? [], Arr::except($item, ['imagens']));
            $lote->save();

            // Alguns editais já trazem as imagens na listagem; outros só no detalhe do lote.
            if (array_key_exists('imagens', $item)) {
                $this->salvarImagens($lote, $item['imagens'] ?? []);
            }
        }

        return $edital;
    }

    private function salvarDetalhesLote(Lote $lote, array $detalhe): void
    {
        $lote->fill(array_filter([
            'tipo' => $detalhe['tipo'] ?? null,
            'situacao' => $detalhe['situacaoLote'] ?? null,
            'valor_minimo' => $detalhe['valorMinimo'] ?? null,
        ], fn ($valor) => $valor !== null));
        $lote->dados = array_merge($lote->dados ?? [], Arr::except($detalhe, ['itensDetalhesLote', 'imagens']));
        $lote->detalhes_importados_em = now();
        $lote->save();

        // Os itens não têm identificador na API, então são sempre substituídos.
        $lote->itens()->delete();
        $lote->itens()->createMany(
            collect($detalhe['itensDetalhesLote'] ?? [])->values()->map(fn (array $item, int $indice) => [
                'ordem' => $indice + 1,
                'descricao' => $this->descricao($item['descricao'] ?? null),
                'quantidade' => $item['quantidade'] ?? null,
                'unidade_medida' => $item['unMedida'] ?? null,
                'recinto_armazenador' => $item['recintoArmazenador'] ?? null,
                'nr_referencia' => $item['nrReferencia'] ?? null,
            ])->all()
        );

        if (array_key_exists('imagens', $detalhe)) {
            $this->salvarImagens($lote, $detalhe['imagens'] ?? []);
        }
    }

    private function salvarImagens(Lote $lote, array $imagens): void
    {
        $ids = [];

        foreach ($imagens as $imagem) {
            if (empty($imagem['imllNrSq']) || empty($imagem['src'])) {
                continue;
            }

            $lote->imagens()->updateOrCreate(
                ['imagem_id' => $imagem['imllNrSq']],
                [
                    'url' => $imagem['src'],
                    'url_miniatura' => $imagem['min'] ?? null,
                    'largura' => $imagem['w'] ?? null,
                    'altura' => $imagem['h'] ?? null,
                ],
            );

            $ids[] = $imagem['imllNrSq'];
        }

        $lote->imagens()->whereNotIn('imagem_id', $ids)->delete();
    }

    private function data(?string $valor): ?Carbon
    {
        // A API usa "2026-10-06 09:00", no horário de Brasília (timezone da aplicação).
        return filled($valor) ? rescue(fn () => Carbon::parse($valor), null, false) : null;
    }

    private function descricao(?string $valor): ?string
    {
        // As descrições vêm com separadores vazios no final ("SMARTWATCH ... W69////").
        $limpo = rtrim(trim((string) $valor), '/ ');

        return $limpo === '' ? null : $limpo;
    }
}
