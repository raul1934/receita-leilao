<?php

namespace App\Console\Commands;

use App\Enums\ResultadoLote;
use App\Enums\SituacaoEdital;
use App\Models\Edital;
use App\Services\Sle\EditalImporter;
use App\Services\Sle\EditalRef;
use App\Services\Sle\SleException;
use App\Support\Formato;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use InvalidArgumentException;

#[Signature('leilao:resultados
    {editais?* : Editais já importados (URL ou unidade/número/ano); sem isso, todos os pendentes}
    {--todos : Relê também os editais finalizados cujo resultado já foi importado}')]
#[Description('Lê o "Extrato do Leilão" (PDF) dos editais encerrados e grava o valor de arremate dos lotes')]
class ImportarResultadosCommand extends Command
{
    public function handle(EditalImporter $importer): int
    {
        try {
            $editais = $this->editais();
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$editais->count()} edital(is) com resultado a importar.");
        $status = self::SUCCESS;

        foreach ($editais->values() as $indice => $edital) {
            $prefixo = sprintf('[%d/%d] %s', $indice + 1, $editais->count(), $edital->ref());

            try {
                $importer->importarResultado($edital);
            } catch (SleException $e) {
                $this->line("{$prefixo}: <error>{$e->getMessage()}</error>");
                $status = self::FAILURE;

                continue;
            }

            $arrematados = $edital->lotes()->where('resultado', ResultadoLote::Arrematado)->count();
            $total = Formato::moeda($edital->lotes()->sum('valor_arremate'));
            $this->line("{$prefixo}: {$arrematados} de {$edital->lotes()->count()} lote(s) arrematado(s), total {$total}");
        }

        return $status;
    }

    /**
     * @return Collection<int, Edital>
     */
    private function editais(): Collection
    {
        if ($entradas = $this->argument('editais')) {
            return collect($entradas)->map(function (string $entrada) {
                $ref = EditalRef::parse($entrada);

                return Edital::where(['unidade' => $ref->unidade, 'numero' => $ref->numero, 'exercicio' => $ref->exercicio])->first()
                    ?? throw new InvalidArgumentException("Edital {$ref} não foi importado. Rode antes: php artisan leilao:importar {$ref}");
            });
        }

        // Pendentes: sem resultado ainda, ou com a sessão ainda podendo mudar
        // (recursos, adjudicação) até o edital ser finalizado.
        return Edital::query()
            ->orderBy('id')
            ->get()
            ->filter(fn (Edital $e) => $e->temExtrato() && (
                $this->option('todos')
                || $e->resultado_importado_em === null
                || ! SituacaoEdital::tryFrom((int) $e->situacao)?->finalizada()
            ));
    }
}
