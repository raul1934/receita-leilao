<?php

namespace App\Console\Commands;

use App\Jobs\ImportarEdital;
use App\Models\Lote;
use App\Services\Sle\EditalImporter;
use App\Services\Sle\EditalRef;
use App\Services\Sle\ResultadoImportacao;
use App\Services\Sle\SleException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Helper\ProgressBar;

#[Signature('leilao:importar
    {editais* : URL do edital no portal ou unidade/número/ano (ex: 700100/12/2026)}
    {--sem-detalhes : Importa só a lista de lotes, sem itens e imagens}
    {--fila : Envia para a fila em vez de importar agora}')]
#[Description('Lê um ou mais editais do SLE da Receita Federal e salva os lotes no banco')]
class ImportarEditalCommand extends Command
{
    public function handle(EditalImporter $importer): int
    {
        $status = self::SUCCESS;

        foreach ($this->argument('editais') as $entrada) {
            try {
                $ref = EditalRef::parse($entrada);
            } catch (InvalidArgumentException $e) {
                $this->error($e->getMessage());
                $status = self::FAILURE;

                continue;
            }

            if ($this->option('fila')) {
                ImportarEdital::dispatch($ref, ! $this->option('sem-detalhes'));
                $this->info("Edital {$ref} enviado para a fila.");

                continue;
            }

            $this->info("Importando edital {$ref}...");

            /** @var ProgressBar|null $barra */
            $barra = null;

            try {
                $resultado = $importer->importar(
                    $ref,
                    comDetalhes: ! $this->option('sem-detalhes'),
                    aoProcessarLote: function (Lote $lote, int $atual, int $total) use (&$barra) {
                        $barra ??= tap($this->output->createProgressBar($total))->start();
                        $barra->advance();
                    },
                );
            } catch (SleException $e) {
                $this->error($e->getMessage());
                $status = self::FAILURE;

                continue;
            } finally {
                if ($barra) {
                    $barra->finish();
                    $this->newLine();
                }
            }

            $this->resumo($resultado);
        }

        return $status;
    }

    private function resumo(ResultadoImportacao $resultado): void
    {
        $edital = $resultado->edital;

        $this->table(['Edital', 'Situação', 'Lotes', 'Lotes detalhados', 'Itens', 'Imagens'], [[
            $edital->codigo,
            $edital->situacao_descricao,
            $resultado->totalLotes,
            $resultado->lotesDetalhados,
            $edital->itens()->count(),
            $edital->imagens()->count(),
        ]]);

        foreach ($resultado->falhas as $numero => $mensagem) {
            $this->warn("Lote {$numero}: {$mensagem}");
        }
    }
}
