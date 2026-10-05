<?php

namespace App\Console\Commands;

use App\Enums\SituacaoEdital;
use App\Jobs\ImportarEdital;
use App\Services\Sle\EditalImporter;
use App\Services\Sle\EditalRef;
use App\Services\Sle\SleClient;
use App\Services\Sle\SleException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('leilao:sincronizar
    {--situacao=* : Só editais nestas situações (ex: --situacao=2 --situacao=3)}
    {--sem-detalhes : Importa só a lista de lotes, sem itens e imagens}
    {--fila : Envia cada edital para a fila em vez de importar agora}')]
#[Description('Importa todos os editais listados em "Editais disponíveis" no portal do SLE')]
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

        $this->info("{$editais->count()} edital(is) encontrado(s).");

        $comDetalhes = ! $this->option('sem-detalhes');
        $status = self::SUCCESS;

        foreach ($editais->values() as $indice => $item) {
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
}
