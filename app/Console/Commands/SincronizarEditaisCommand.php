<?php

namespace App\Console\Commands;

use App\Enums\SituacaoEdital;
use App\Jobs\ImportarEdital;
use App\Models\Edital;
use App\Services\Sle\EditalImporter;
use App\Services\Sle\EditalRef;
use App\Services\Sle\SleClient;
use App\Services\Sle\SleException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('leilao:sincronizar
    {--situacao=* : Só editais nestas situações (ex: --situacao=2 --situacao=3)}
    {--todos : Reimporta também os editais finalizados que não mudaram desde a última importação}
    {--sem-detalhes : Importa só a lista de lotes, sem itens e imagens}
    {--fila : Envia cada edital para a fila em vez de importar agora}')]
#[Description('Importa os editais listados em "Editais disponíveis" no portal do SLE, dos leilões mais próximos para os mais antigos')]
class SincronizarEditaisCommand extends Command
{
    public function handle(SleClient $client, EditalImporter $importer): int
    {
        try {
            $editais = collect($client->editaisDisponiveis());
        } catch (SleException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($situacoes = array_map('intval', $this->option('situacao'))) {
            $editais = $editais->filter(fn (array $e) => in_array((int) ($e['codigoSituacao'] ?? 0), $situacoes, true));
        }

        $total = $editais->count();
        $ignorados = 0;

        if (! $this->option('todos')) {
            $atualizados = $this->finalizadosJaImportados();
            $editais = $editais->reject(fn (array $e) => $atualizados->contains($this->chave($e)));
            $ignorados = $total - $editais->count();
        }

        $editais = $this->proximosPrimeiro($editais);

        $this->info("{$total} edital(is) encontrado(s)".($ignorados ? ", {$ignorados} finalizado(s) e já importado(s) ignorado(s)" : '').'.');

        $comDetalhes = ! $this->option('sem-detalhes');
        $status = self::SUCCESS;

        foreach ($editais as $indice => $item) {
            $ref = EditalRef::parse($item['edle']);
            $cidade = $item['cidade'] ?? null;
            $prefixo = sprintf('[%d/%d] %s (%s, %s)', $indice + 1, $editais->count(), $ref, $cidade ?? '-',
                SituacaoEdital::descricao(isset($item['codigoSituacao']) ? (int) $item['codigoSituacao'] : null));

            if ($this->option('fila')) {
                ImportarEdital::dispatch($ref, $comDetalhes, $cidade);
                $this->line("{$prefixo}: enviado para a fila");

                continue;
            }

            try {
                $resultado = $importer->importar($ref, $comDetalhes, ['cidade' => $cidade]);
                $falhas = count($resultado->falhas);
                $this->line("{$prefixo}: {$resultado->totalLotes} lote(s)".($falhas ? ", <comment>{$falhas} falha(s)</comment>" : ''));
            } catch (SleException $e) {
                $this->line("{$prefixo}: <error>{$e->getMessage()}</error>");
                $status = self::FAILURE;
            }
        }

        return $status;
    }

    /**
     * Chaves "unidade/numero/ano/situacao" dos editais finalizados que já foram
     * importados por completo nessa mesma situação.
     *
     * @return Collection<int, string>
     */
    private function finalizadosJaImportados(): Collection
    {
        $finalizadas = array_map(
            fn (SituacaoEdital $s) => $s->value,
            array_filter(SituacaoEdital::cases(), fn (SituacaoEdital $s) => $s->finalizada()),
        );

        return Edital::query()
            ->whereIn('situacao', $finalizadas)
            ->whereNotNull('importado_em')
            ->whereDoesntHave('lotes', fn ($q) => $q->whereNull('detalhes_importados_em'))
            ->get(['unidade', 'numero', 'exercicio', 'situacao'])
            ->map(fn (Edital $e) => $e->ref()->path().'/'.$e->situacao);
    }

    private function chave(array $item): string
    {
        return EditalRef::parse($item['edle'])->path().'/'.(int) ($item['codigoSituacao'] ?? 0);
    }

    /**
     * Mesma ordem da listagem da interface: leilões com abertura dos lances
     * ainda por vir primeiro (o mais próximo antes), depois os que já
     * começaram (o mais recente antes).
     *
     * @param  Collection<int, array<string, mixed>>  $editais
     * @return Collection<int, array<string, mixed>>
     */
    private function proximosPrimeiro(Collection $editais): Collection
    {
        // A API usa "Y-m-d H:i", que pode ser comparado como texto.
        $agora = now()->format('Y-m-d H:i');

        [$proximos, $passados] = $editais->partition(fn (array $e) => ($e['dataAberturaLances'] ?? '') >= $agora);

        return $proximos->sortBy('dataAberturaLances')
            ->concat($passados->sortByDesc('dataAberturaLances'))
            ->values();
    }
}
