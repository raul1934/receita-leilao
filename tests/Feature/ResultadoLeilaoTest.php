<?php

namespace Tests\Feature;

use App\Enums\ResultadoLote;
use App\Models\Edital;
use App\Models\Lote;
use App\Models\LoteHistorico;
use App\Services\Sle\EditalImporter;
use App\Services\Sle\EditalRef;
use App\Services\Sle\PdfParaTexto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\PdfSimples;
use Tests\TestCase;

class ResultadoLeilaoTest extends TestCase
{
    use RefreshDatabase;

    private function extrato(string ...$linhas): array
    {
        return ['data' => base64_encode(PdfSimples::gerar([
            'Relatório de Extrato do Leilão',
            'Lote   CNPJ/CPF             Arrematante                Valor Arrematação',
            ...$linhas,
        ]))];
    }

    /**
     * Edital 700100/12/2026 como se a sessão já tivesse terminado.
     */
    private function fakeSleEncerrado(mixed $extrato): void
    {
        $edital = $this->fixture('edital_700100_12_2026');
        $edital['situacao'] = 15;
        $edital['permissoes'] = ['ata-publicada', 'extrato-leilao', 'historico-leilao'];

        $this->fakeSle([
            '*/api/edital/700100/12/2026/extrato-leilao' => $extrato,
            '*/api/edital/700100/12/2026' => Http::response($edital),
        ]);
    }

    private function importar()
    {
        return app(EditalImporter::class)->importar(EditalRef::parse('700100/12/2026'));
    }

    public function test_pdf_para_texto_usa_o_pdftotext(): void
    {
        $texto = app(PdfParaTexto::class)->converter(PdfSimples::gerar(['7   Lote Não Arrematado']));

        $this->assertMatchesRegularExpression('~7\s+Lote Não Arrematado~u', $texto);
    }

    public function test_importacao_de_edital_encerrado_grava_o_valor_de_arremate(): void
    {
        $this->fakeSleEncerrado(Http::response($this->extrato(
            '1      11.111.111/0001-11   EMPRESA ALFA LTDA          400.000,00',
        )));

        $resultado = $this->importar();

        $this->assertNull($resultado->erroResultado);
        $lote = Lote::sole();
        $this->assertSame(ResultadoLote::Arrematado, $lote->resultado);
        $this->assertSame('400000.00', $lote->valor_arremate);
        $this->assertNotNull(Edital::sole()->resultado_importado_em);
        // O valor mínimo continua o do edital e o histórico não muda.
        $this->assertSame('306100.00', $lote->valor_minimo);
        $this->assertSame(1, LoteHistorico::count());
    }

    public function test_edital_em_andamento_nao_busca_o_extrato(): void
    {
        $this->fakeSle();

        $this->importar();

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'extrato-leilao'));
        $this->assertNull(Lote::sole()->resultado);
    }

    public function test_falha_no_extrato_nao_impede_a_importacao(): void
    {
        $this->fakeSleEncerrado(Http::response(['data' => base64_encode('não é um PDF')]));

        $resultado = $this->importar();

        $this->assertStringContainsString('não veio em PDF', $resultado->erroResultado);
        $this->assertSame(13, Edital::sole()->itens()->count());
        $this->assertNull(Edital::sole()->resultado_importado_em);
    }

    public function test_comando_resultados_le_so_os_editais_pendentes(): void
    {
        $this->fakeSleEncerrado(Http::response($this->extrato('1      ***.111.111-**       PESSOA UM          1.500,00')));
        $pendente = $this->editalEncerrado(700100, 12, resultadoImportado: false);
        $jaImportado = $this->editalEncerrado(800100, 7, resultadoImportado: true);
        $semExtrato = Edital::create(['unidade' => 727600, 'numero' => 3, 'exercicio' => 2026, 'situacao' => 2, 'dados' => ['permissoes' => []]]);

        $this->artisan('leilao:resultados')
            ->expectsOutputToContain('1 edital(is) com resultado a importar.')
            ->expectsOutputToContain('700100/12/2026: 1 de 1 lote(s) arrematado(s), total R$ 1.500,00')
            ->assertSuccessful();

        $this->assertSame('1500.00', $pendente->lotes()->sole()->valor_arremate);
        $this->assertNull($jaImportado->lotes()->sole()->valor_arremate);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '800100/7/2026')
            || str_contains($request->url(), (string) $semExtrato->ref()));
    }

    public function test_comando_resultados_informa_edital_nao_importado(): void
    {
        $this->artisan('leilao:resultados', ['editais' => ['900100/1/2026']])
            ->expectsOutputToContain('não foi importado')
            ->assertFailed();
    }

    public function test_paginas_mostram_o_arremate(): void
    {
        $this->fakeSleEncerrado(Http::response($this->extrato(
            '1      11.111.111/0001-11   EMPRESA ALFA LTDA          400.000,00',
        )));
        $edital = $this->importar()->edital;

        $this->get(route('editais.show', $edital))
            ->assertSee('Total arrematado')
            ->assertSee('R$ 400.000,00')
            ->assertSee('(1 de 1 lotes)')
            ->assertSee('Maior arremate');

        $this->get(route('lotes.show', [$edital, $edital->lotes()->first()]))
            ->assertSee('Arrematado por R$ 400.000,00')
            ->assertSee('+30,7% sobre o valor mínimo');
    }

    public function test_lance_suspeito_e_marcado_e_fica_fora_do_total(): void
    {
        // Lote com mínimo de R$ 306.100 e avaliação de R$ 1.307.215.
        $this->fakeSleEncerrado(Http::response($this->extrato(
            '1      11.111.111/0001-11   EMPRESA ALFA LTDA          170.100.000,00',
        )));
        $edital = $this->importar()->edital;
        $lote = $edital->lotes()->sole();

        $this->assertTrue($lote->arremate_suspeito);
        $this->assertSame('170100000.00', $lote->valor_arremate);

        $this->get(route('editais.show', $edital))
            ->assertSee('R$ 0,00')
            ->assertSee('(0 de 1 lotes)')
            ->assertSee('1 lance(s) suspeito(s) fora do total')
            ->assertSee('badge alerta', false);

        $this->get(route('lotes.show', [$edital, $lote]))
            ->assertSee('Arrematado por R$ 170.100.000,00')
            ->assertSee('Lance suspeito: 130 vezes o maior valor de referência')
            ->assertDontSee('sobre o valor mínimo');
    }

    public function test_edital_sem_resultado_nao_mostra_a_coluna(): void
    {
        $this->fakeSle();
        $edital = $this->importar()->edital;

        $this->get(route('editais.show', $edital))->assertDontSee('Total arrematado')->assertDontSee('Maior arremate');
    }

    private function editalEncerrado(int $unidade, int $numero, bool $resultadoImportado): Edital
    {
        $edital = Edital::create([
            'unidade' => $unidade, 'numero' => $numero, 'exercicio' => 2026, 'situacao' => 15,
            'dados' => ['permissoes' => ['extrato-leilao']],
            'resultado_importado_em' => $resultadoImportado ? now() : null,
        ]);
        $edital->lotes()->create(['numero' => 1, 'valor_minimo' => 1000]);

        return $edital;
    }
}
