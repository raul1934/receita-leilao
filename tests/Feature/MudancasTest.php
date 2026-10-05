<?php

namespace Tests\Feature;

use App\Models\Edital;
use App\Models\Lote;
use App\Models\LoteHistorico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MudancasTest extends TestCase
{
    use RefreshDatabase;

    private Edital $edital;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 12:00');
        $this->edital = Edital::create([
            'unidade' => 700100, 'numero' => 12, 'exercicio' => 2026,
            'codigo' => '0700100/000012/2026', 'cidade' => 'RIO DE JANEIRO',
        ]);
    }

    private function lote(int $numero, int $situacao = 11, int $valor = 300000): Lote
    {
        return $this->edital->lotes()->create([
            'numero' => $numero, 'tipo' => 'INFORMÁTICA', 'situacao' => $situacao,
            'valor_minimo' => $valor, 'valor_avaliacao' => $valor * 2,
        ]);
    }

    public function test_lista_mudancas_de_situacao_e_de_preco(): void
    {
        $this->lote(1)->update(['situacao' => 12]);
        $this->lote(2)->update(['valor_minimo' => 250000]);
        $this->lote(3);

        $this->get('/mudancas')
            ->assertOk()
            ->assertSee('2 mudança(s) no período: 1 de situação, 1 de valor mínimo, 0 de valor de avaliação.')
            ->assertSeeInOrder(['Lote 2', 'R$ 300.000,00', 'R$ 250.000,00', '-16,7%', 'Lote 1', 'Disponibilizado', 'Aberto para Proposta'])
            ->assertSee('RIO DE JANEIRO')
            ->assertDontSee('Lote 3');
    }

    public function test_primeiro_registro_do_lote_nao_e_mudanca(): void
    {
        $this->lote(1);

        $this->assertSame(1, LoteHistorico::count());
        $this->get('/mudancas')->assertOk()->assertSee('Nenhuma mudança no período');
    }

    public function test_compara_com_o_registro_imediatamente_anterior(): void
    {
        $lote = $this->lote(1);
        $this->travel(1)->hours();
        $lote->update(['situacao' => 12]);
        $this->travel(1)->hours();
        $lote->update(['situacao' => 13]);

        $this->get('/mudancas')
            ->assertSee('2 mudança(s)')
            ->assertSeeInOrder(['Aberto para Proposta', 'Fechado para Proposta', 'Disponibilizado', 'Aberto para Proposta']);
    }

    public function test_filtra_pelo_tipo_de_mudanca(): void
    {
        $this->lote(1)->update(['situacao' => 12]);
        $this->lote(2)->update(['valor_minimo' => 250000]);

        $this->get('/mudancas?mudanca=situacao')->assertSee('Lote 1')->assertDontSee('Lote 2');
        $this->get('/mudancas?mudanca=valor_minimo')->assertSee('Lote 2')->assertDontSee('Lote 1');
        $this->get('/mudancas?mudanca=valor_avaliacao')->assertSee('Nenhuma mudança no período');
    }

    public function test_filtra_pelo_periodo(): void
    {
        $lote = $this->lote(1);
        $this->travel(-10)->days();
        // Primeiro registro e mudança há 10 dias.
        LoteHistorico::query()->update(['registrado_em' => now()->subHour()]);
        $lote->update(['situacao' => 12]);
        $this->travelBack();
        $this->travelTo('2026-10-05 12:00');

        $this->get('/mudancas')->assertSee('Nenhuma mudança no período');
        $this->get('/mudancas?periodo=30d')->assertSee('Lote 1')->assertSee('Aberto para Proposta');
        $this->get('/mudancas?periodo=tudo')->assertSee('Lote 1');
    }

    public function test_menu_leva_para_a_pagina(): void
    {
        $this->get('/')->assertSee(route('mudancas.index'), false)->assertSee('Mudanças recentes');
    }
}
