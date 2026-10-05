<?php

namespace Tests\Feature;

use App\Models\Edital;
use App\Models\Lote;
use App\Models\LoteImagem;
use App\Models\LoteItem;
use App\Services\Sle\EditalImporter;
use App\Services\Sle\EditalRef;
use App\Services\Sle\SleClient;
use App\Services\Sle\SleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class EditalImporterTest extends TestCase
{
    use RefreshDatabase;

    private function importar(bool $comDetalhes = true)
    {
        return app(EditalImporter::class)->importar(EditalRef::parse('700100/12/2026'), $comDetalhes);
    }

    public function test_importa_edital_lotes_itens_e_imagens(): void
    {
        $this->fakeSle();

        $resultado = $this->importar();

        $this->assertSame(1, $resultado->totalLotes);
        $this->assertSame(1, $resultado->lotesDetalhados);
        $this->assertSame([], $resultado->falhas);
        Http::assertSentCount(2);

        $edital = Edital::sole();
        $this->assertSame('0700100/000012/2026', $edital->codigo);
        $this->assertSame(2, $edital->situacao);
        $this->assertSame('Disponibilizado', $edital->situacao_descricao);
        $this->assertSame('Receita Federal do Brasil', $edital->orgao);
        $this->assertStringContainsString('7ª REGIÃO FISCAL', $edital->unidade_nome);
        $this->assertFalse($edital->permite_pf);
        $this->assertSame('2026-10-06 09:00', $edital->data_inicio_propostas->format('Y-m-d H:i'));
        $this->assertSame('2026-10-08 10:00', $edital->data_abertura_lances->format('Y-m-d H:i'));
        $this->assertNotNull($edital->importado_em);
        $this->assertArrayNotHasKey('listaLotes', $edital->dados);

        $lote = Lote::sole();
        $this->assertSame(1, $lote->numero);
        $this->assertSame('EXPORTAÇÃO', $lote->tipo);
        $this->assertSame(11, $lote->situacao);
        $this->assertSame('306100.00', $lote->valor_minimo);
        $this->assertSame('1307215.00', $lote->valor_avaliacao);
        $this->assertNotNull($lote->detalhes_importados_em);

        $itens = $lote->itens()->orderBy('ordem')->get();
        $this->assertCount(13, $itens);
        $this->assertSame('SMARTWATCH (RELOG.INTLG.) MICROWEAR W69', $itens[0]->descricao);
        $this->assertSame('1500.0000', $itens[0]->quantidade);
        $this->assertSame('un', $itens[0]->unidade_medida);
        $this->assertSame('Porto Seco de Mesquita', $itens[0]->recinto_armazenador);
        $this->assertSame(13, $itens->last()->ordem);

        $imagem = LoteImagem::sole();
        $this->assertSame(546371, $imagem->imagem_id);
        $this->assertStringContainsString('foto_700100_2026_12_1_546371', $imagem->url);
        $this->assertStringContainsString('foto_pq_700100_2026_12_1_546371', $imagem->url_miniatura);
    }

    public function test_reimportar_atualiza_sem_duplicar(): void
    {
        $edital = $this->fixture('edital_700100_12_2026');
        $edital['situacao'] = 3;
        $edital['listaLotes'][0]['valorMinimo'] = 250000;
        $lote = $this->fixture('lote_700100_12_2026_1');
        unset($lote['valorMinimo']);

        $this->fakeSle([
            '*/api/edital/700100/12/2026' => Http::sequence()->push($this->fixture('edital_700100_12_2026'))->push($edital),
            '*/api/lote/700100/12/2026/1' => Http::sequence()->push($this->fixture('lote_700100_12_2026_1'))->push($lote),
        ]);

        $this->importar();
        $this->importar();

        $this->assertSame(1, Edital::count());
        $this->assertSame(1, Lote::count());
        $this->assertSame(13, LoteItem::count());
        $this->assertSame(1, LoteImagem::count());
        $this->assertSame(3, Edital::sole()->situacao);
        $this->assertSame('250000.00', Lote::sole()->valor_minimo);
    }

    public function test_sem_detalhes_importa_apenas_a_lista_de_lotes(): void
    {
        $this->fakeSle();

        $resultado = $this->importar(comDetalhes: false);

        $this->assertSame(1, $resultado->totalLotes);
        $this->assertSame(0, $resultado->lotesDetalhados);
        Http::assertSentCount(1);
        $this->assertSame(1, Lote::count());
        $this->assertNull(Lote::sole()->detalhes_importados_em);
        $this->assertSame(0, LoteItem::count());
        // Este edital já traz as imagens na listagem.
        $this->assertSame(1, LoteImagem::count());
    }

    public function test_remove_imagens_que_sairam_do_lote(): void
    {
        $edital = $this->fixture('edital_700100_12_2026');
        $edital['listaLotes'][0]['imagens'] = [];
        $lote = $this->fixture('lote_700100_12_2026_1');
        $lote['imagens'] = [];

        $this->fakeSle([
            '*/api/edital/700100/12/2026' => Http::sequence()->push($this->fixture('edital_700100_12_2026'))->push($edital),
            '*/api/lote/700100/12/2026/1' => Http::sequence()->push($this->fixture('lote_700100_12_2026_1'))->push($lote),
        ]);

        $this->importar();
        $this->assertSame(1, LoteImagem::count());
        $this->importar();

        $this->assertSame(0, LoteImagem::count());
    }

    public function test_falha_em_um_lote_nao_interrompe_a_importacao(): void
    {
        $this->fakeSle(['*/api/lote/700100/12/2026/1' => $this->respostaDeErroDoSle()]);

        $resultado = $this->importar();

        $this->assertSame(0, $resultado->lotesDetalhados);
        $this->assertArrayHasKey(1, $resultado->falhas);
        $this->assertStringContainsString('HTTP 500', $resultado->falhas[1]);
        $this->assertStringContainsString('Não foi possível realizar a operação', $resultado->falhas[1]);
        $this->assertSame(1, Edital::count());
        $this->assertSame(1, Lote::count());
        $this->assertSame(0, LoteItem::count());
    }

    public function test_falha_ao_ler_o_edital_lanca_excecao(): void
    {
        $this->fakeSle(['*/api/edital/700100/12/2026' => $this->respostaDeErroDoSle()]);

        $this->expectException(SleException::class);
        $this->expectExceptionMessage('Não foi possível realizar a operação');

        try {
            $this->importar();
        } finally {
            $this->assertSame(0, Edital::count());
        }
    }

    public function test_tenta_novamente_quando_o_servidor_falha(): void
    {
        Sleep::fake();
        config(['sle.retries' => 1]);
        $this->app->forgetInstance(SleClient::class);

        $this->fakeSle([
            '*/api/edital/700100/12/2026' => Http::sequence()
                ->pushResponse($this->respostaDeErroDoSle())
                ->push($this->fixture('edital_700100_12_2026')),
        ]);

        $resultado = $this->importar();

        $this->assertSame(1, $resultado->lotesDetalhados);
        Http::assertSentCount(3);
    }

    public function test_respeita_a_pausa_entre_requisicoes(): void
    {
        Sleep::fake();
        config(['sle.delay_ms' => 300]);
        $this->app->forgetInstance(SleClient::class);
        $this->fakeSle();

        $this->importar();

        // Duas requisições (edital + lote): uma pausa antes da segunda.
        Sleep::assertSleptTimes(1);
    }
}
