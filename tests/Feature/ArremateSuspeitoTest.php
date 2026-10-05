<?php

namespace Tests\Feature;

use App\Models\Edital;
use App\Models\Lote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArremateSuspeitoTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('casos')]
    public function test_regra_de_arremate_suspeito(?string $arremate, ?string $minimo, ?string $avaliacao, bool $suspeito): void
    {
        $this->assertSame($suspeito, Lote::arremateSuspeito($arremate, $minimo, $avaliacao));
    }

    /**
     * Casos tirados de extratos reais.
     */
    public static function casos(): array
    {
        return [
            'veículo de R$ 80 mil por R$ 170 milhões' => ['170100000', '80000', '150000', true],
            'celulares de R$ 1.666 por R$ 69.995' => ['69995', '125', '1666', true],
            'antiguidade a 15x a avaliação' => ['7506', '150', '490', false],
            'mínimo simbólico, abaixo da avaliação' => ['3000', '10', '5000', false],
            'ágio normal' => ['400000', '306100', '1307215', false],
            'sem arremate' => [null, '1000', '2000', false],
            'sem valor de referência' => ['5000', null, null, false],
        ];
    }

    public function test_multiplo_e_configuravel(): void
    {
        config(['sle.arremate_suspeito_multiplo' => 5]);

        $this->assertTrue(Lote::arremateSuspeito('7506', '150', '490'));
    }

    public function test_marcacao_e_recalculada_ao_salvar_o_lote(): void
    {
        $edital = Edital::create(['unidade' => 700100, 'numero' => 12, 'exercicio' => 2026]);
        $lote = $edital->lotes()->create(['numero' => 1, 'valor_minimo' => 1000, 'valor_arremate' => 50000]);

        $this->assertTrue($lote->refresh()->arremate_suspeito);
        $this->assertSame(50.0, $lote->multiplo_do_arremate);

        $lote->update(['valor_avaliacao' => 10000]);

        $this->assertFalse($lote->refresh()->arremate_suspeito);
    }

    public function test_comando_recalcula_apos_mudar_o_multiplo(): void
    {
        $edital = Edital::create(['unidade' => 700100, 'numero' => 12, 'exercicio' => 2026]);
        $lote = $edital->lotes()->create(['numero' => 1, 'valor_minimo' => 150, 'valor_avaliacao' => 490, 'valor_arremate' => 7506]);
        $this->assertFalse($lote->refresh()->arremate_suspeito);

        config(['sle.arremate_suspeito_multiplo' => 5]);

        $this->artisan('leilao:resultados', ['--recalcular' => true])
            ->expectsOutputToContain('1 lance(s) suspeito(s)')
            ->assertSuccessful();
        $this->assertTrue($lote->refresh()->arremate_suspeito);
    }

    public function test_totais_do_edital_ignoram_os_suspeitos(): void
    {
        $edital = Edital::create(['unidade' => 700100, 'numero' => 12, 'exercicio' => 2026]);
        $edital->lotes()->create(['numero' => 1, 'valor_minimo' => 1000, 'resultado' => 'arrematado', 'valor_arremate' => 1500]);
        $edital->lotes()->create(['numero' => 2, 'valor_minimo' => 80000, 'resultado' => 'arrematado', 'valor_arremate' => 170100000]);
        $edital->lotes()->create(['numero' => 3, 'valor_minimo' => 500, 'resultado' => 'nao_arrematado']);

        $this->assertSame(['lotes' => 1, 'de' => 3, 'total' => 1500.0, 'suspeitos' => 1], array_merge(
            $edital->resumoArremate(),
            ['total' => (float) $edital->resumoArremate()['total']],
        ));
    }
}
