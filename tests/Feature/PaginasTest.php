<?php

namespace Tests\Feature;

use App\Jobs\ImportarEdital;
use App\Models\Edital;
use App\Services\Sle\EditalImporter;
use App\Services\Sle\EditalRef;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaginasTest extends TestCase
{
    use RefreshDatabase;

    private function editalImportado(): Edital
    {
        $this->fakeSle();

        return app(EditalImporter::class)->importar(EditalRef::parse('700100/12/2026'))->edital;
    }

    public function test_lista_vazia(): void
    {
        $this->get('/')->assertOk()->assertSee('Nenhum edital importado ainda');
    }

    public function test_lista_os_editais_importados(): void
    {
        $this->editalImportado();

        $this->get('/')
            ->assertOk()
            ->assertSee('0700100/000012/2026')
            ->assertSee('Disponibilizado')
            ->assertSee('08/10/2026 10:00');
    }

    public function test_ordena_pela_abertura_dos_lances_com_os_proximos_primeiro(): void
    {
        $this->travelTo('2026-10-05 12:00');

        foreach ([
            'ED-PASSADO-RECENTE' => '2026-10-01 10:00',
            'ED-PASSADO-ANTIGO' => '2026-08-01 10:00',
            'ED-PROXIMO' => '2026-10-08 10:00',
            'ED-DISTANTE' => '2026-11-12 10:30',
        ] as $codigo => $abertura) {
            Edital::create([
                'unidade' => 700100, 'numero' => crc32($codigo) % 100000, 'exercicio' => 2026,
                'codigo' => $codigo, 'data_abertura_lances' => $abertura,
            ]);
        }

        $this->get('/')->assertSeeInOrder(['ED-PROXIMO', 'ED-DISTANTE', 'ED-PASSADO-RECENTE', 'ED-PASSADO-ANTIGO']);
        $this->get('/?ordem=abertura_asc')->assertSeeInOrder(['ED-PASSADO-ANTIGO', 'ED-PASSADO-RECENTE', 'ED-PROXIMO', 'ED-DISTANTE']);
        $this->get('/?ordem=abertura_desc')->assertSeeInOrder(['ED-DISTANTE', 'ED-PROXIMO', 'ED-PASSADO-RECENTE', 'ED-PASSADO-ANTIGO']);
    }

    public function test_detalhe_do_edital_lista_os_lotes(): void
    {
        $edital = $this->editalImportado();

        $this->get(route('editais.show', $edital))
            ->assertOk()
            ->assertSee('Lote 1')
            ->assertSee('EXPORTAÇÃO')
            ->assertSee('R$ 306.100,00')
            ->assertSee('R$ 1.307.215,00')
            ->assertSee('leilao.rj.srrf07@rfb.gov.br');
    }

    public function test_filtra_lotes_pela_descricao_dos_itens(): void
    {
        $edital = $this->editalImportado();

        $this->get(route('editais.show', [$edital, 'q' => 'mibro']))->assertSee('Lote 1');
        $this->get(route('editais.show', [$edital, 'q' => 'geladeira']))->assertSee('Nenhum lote encontrado');
        $this->get(route('editais.show', [$edital, 'tipo' => 'INFORMÁTICA']))->assertSee('Nenhum lote encontrado');
    }

    public function test_filtro_por_tipo_considera_lotes_com_varias_categorias(): void
    {
        $edital = Edital::create(['unidade' => 727600, 'numero' => 3, 'exercicio' => 2026]);
        $edital->lotes()->create(['numero' => 1, 'tipo' => 'ELETRÔNICO/ÁUDIO/VÍDEO, INFORMÁTICA']);
        $edital->lotes()->create(['numero' => 2, 'tipo' => 'VEÍCULO']);

        $this->get(route('editais.show', $edital))
            ->assertSee('<option value="INFORMÁTICA"', false)
            ->assertDontSee('<option value="ELETRÔNICO/ÁUDIO/VÍDEO, INFORMÁTICA"', false);

        $this->get(route('editais.show', [$edital, 'tipo' => 'INFORMÁTICA']))
            ->assertSee('Lote 1')
            ->assertDontSee('Lote 2');
    }

    public function test_detalhe_do_lote_mostra_itens_e_fotos(): void
    {
        $edital = $this->editalImportado();

        $this->get(route('lotes.show', [$edital, $edital->lotes()->first()]))
            ->assertOk()
            ->assertSee('SMARTWATCH (RELOG.INTLG.) MICROWEAR W69')
            ->assertDontSee('W69////')
            ->assertSee('1.500')
            ->assertSee('Porto Seco de Mesquita')
            ->assertSee('foto_pq_700100_2026_12_1_546371', false)
            // O JSON precisa vir escapado, senão as aspas fecham o atributo HTML.
            ->assertSee('data-galeria="[{&quot;url&quot;:&quot;https:', false)
            ->assertSee('data-indice="0"', false)
            ->assertSee('id="galeria"', false);
    }

    public function test_miniatura_na_lista_de_lotes_abre_a_galeria(): void
    {
        $edital = $this->editalImportado();

        $this->get(route('editais.show', $edital))
            ->assertOk()
            ->assertSee('class="foto"', false)
            // O JSON precisa vir escapado, senão as aspas fecham o atributo HTML.
            ->assertSee('data-galeria="[{&quot;url&quot;:&quot;https:', false)
            ->assertSee('data-titulo="Lote 1"', false);
    }

    public function test_detalhe_do_lote_mostra_o_historico_com_as_mudancas(): void
    {
        $edital = $this->editalImportado();
        $lote = $edital->lotes()->first();
        $lote->update(['situacao' => 12, 'valor_minimo' => 250000]);

        $this->get(route('lotes.show', [$edital, $lote]))
            ->assertOk()
            ->assertSee('Histórico de situação e preço')
            ->assertSeeInOrder(['Aberto para Proposta', 'R$ 250.000,00', 'Disponibilizado', 'R$ 306.100,00'])
            ->assertSee('class="num mudou"', false);
    }

    public function test_lote_de_outro_edital_retorna_404(): void
    {
        $this->editalImportado();
        $outro = Edital::create(['unidade' => 800100, 'numero' => 7, 'exercicio' => 2026]);

        $this->get("/editais/{$outro->id}/lotes/1")->assertNotFound();
    }

    public function test_formulario_envia_a_importacao_para_a_fila(): void
    {
        Queue::fake();

        $this->post('/editais', ['edital' => 'https://www25.receita.fazenda.gov.br/sle-sociedade/portal/edital/700100/12/2026'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Queue::assertPushed(ImportarEdital::class, fn (ImportarEdital $job) => $job->ref->path() === '700100/12/2026');
    }

    public function test_formulario_rejeita_entrada_invalida(): void
    {
        Queue::fake();

        $this->post('/editais', ['edital' => 'não é um edital'])->assertSessionHasErrors('edital');

        Queue::assertNothingPushed();
    }
}
