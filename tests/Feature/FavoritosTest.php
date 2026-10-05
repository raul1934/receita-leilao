<?php

namespace Tests\Feature;

use App\Models\Edital;
use App\Models\Lote;
use App\Services\Sle\EditalImporter;
use App\Services\Sle\EditalRef;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoritosTest extends TestCase
{
    use RefreshDatabase;

    private function edital(int $numero, string $abertura, int $situacao = 2): Edital
    {
        return Edital::create([
            'unidade' => 700100, 'numero' => $numero, 'exercicio' => 2026, 'situacao' => $situacao,
            'codigo' => "0700100/{$numero}/2026", 'cidade' => 'RIO DE JANEIRO', 'data_abertura_lances' => $abertura,
        ]);
    }

    public function test_estrela_marca_e_desmarca_o_lote(): void
    {
        $edital = $this->edital(12, '2026-10-08 10:00');
        $lote = $edital->lotes()->create(['numero' => 1]);
        $url = route('favoritos.alternar', [$edital, $lote]);

        $this->from(route('editais.show', $edital))->post($url)
            ->assertRedirect(route('editais.show', $edital))
            ->assertSessionHas('status', 'Lote 1 adicionado aos favoritos.');
        $this->assertNotNull($lote->refresh()->favoritado_em);

        $this->post($url)->assertSessionHas('status', 'Lote 1 removido dos favoritos.');
        $this->assertNull($lote->refresh()->favoritado_em);
    }

    public function test_alternar_por_javascript_responde_json(): void
    {
        $edital = $this->edital(12, '2026-10-08 10:00');
        $lote = $edital->lotes()->create(['numero' => 1]);

        $this->postJson(route('favoritos.alternar', [$edital, $lote]))
            ->assertOk()
            ->assertExactJson(['favorito' => true, 'total' => 1]);
    }

    public function test_lote_de_outro_edital_retorna_404(): void
    {
        $edital = $this->edital(12, '2026-10-08 10:00');
        $outro = $this->edital(13, '2026-10-09 10:00');
        $edital->lotes()->create(['numero' => 1]);

        $this->post("/editais/{$outro->id}/lotes/1/favorito")->assertNotFound();
    }

    public function test_pagina_lista_os_favoritos_dos_leiloes_mais_proximos_primeiro(): void
    {
        $this->travelTo('2026-10-05 12:00');
        $passado = $this->edital(5, '2026-08-13 10:00', situacao: 15);
        $distante = $this->edital(13, '2026-11-12 10:30');
        $proximo = $this->edital(12, '2026-10-08 10:00');

        $passado->lotes()->create(['numero' => 7, 'favoritado_em' => now(), 'resultado' => 'arrematado', 'valor_arremate' => 1500]);
        $distante->lotes()->create(['numero' => 3, 'favoritado_em' => now()]);
        $proximo->lotes()->create(['numero' => 9, 'favoritado_em' => now(), 'valor_minimo' => 306100]);
        $proximo->lotes()->create(['numero' => 10]);

        $this->get('/favoritos')
            ->assertOk()
            ->assertSeeInOrder(['Lote 9', '0700100/12/2026', '08/10/2026 10:00', 'R$ 306.100,00', 'Lote 3', 'Lote 7', 'R$ 1.500,00'])
            ->assertDontSee('Lote 10')
            ->assertSee('aria-pressed="true"', false);

        $this->get('/favoritos?lances=abertos')->assertSee('Lote 9')->assertSee('Lote 3')->assertDontSee('Lote 7');
        $this->get('/favoritos?lances=fechados')->assertSee('Lote 7')->assertDontSee('Lote 9');
    }

    public function test_pagina_vazia(): void
    {
        $this->get('/favoritos')->assertOk()->assertSee('Nenhum lote favorito ainda');
    }

    public function test_estrela_e_contador_nas_paginas(): void
    {
        $edital = $this->edital(12, '2026-10-08 10:00');
        $edital->lotes()->create(['numero' => 1, 'favoritado_em' => now()]);
        $lote = $edital->lotes()->create(['numero' => 2]);

        $this->get(route('editais.show', $edital))
            ->assertSee('Favoritos (<span data-total-favoritos>1</span>)', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('aria-pressed="false"', false);

        $this->get(route('lotes.show', [$edital, $lote]))
            ->assertSee('aria-label="Favoritar lote 2"', false)
            ->assertSee('aria-pressed="false"', false);
    }

    public function test_reimportar_nao_desmarca_o_favorito(): void
    {
        $this->fakeSle();
        $importer = app(EditalImporter::class);
        $ref = EditalRef::parse('700100/12/2026');
        $importer->importar($ref);
        Lote::query()->update(['favoritado_em' => now()]);

        $importer->importar($ref);

        $this->assertNotNull(Lote::sole()->favoritado_em);
    }
}
