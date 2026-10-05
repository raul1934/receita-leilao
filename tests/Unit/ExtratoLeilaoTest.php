<?php

namespace Tests\Unit;

use App\Enums\ResultadoLote;
use App\Services\Sle\ExtratoLeilao;
use PHPUnit\Framework\TestCase;

class ExtratoLeilaoTest extends TestCase
{
    /**
     * Texto no formato do pdftotext -layout de um extrato real, com nomes e
     * documentos trocados por fictícios.
     */
    private function lotes(): array
    {
        return ExtratoLeilao::interpretar(file_get_contents(__DIR__.'/../Fixtures/sle/extrato_leilao.txt'));
    }

    public function test_encontra_todos_os_lotes_inclusive_apos_quebra_de_pagina(): void
    {
        $this->assertSame(range(1, 10), array_keys($this->lotes()));
    }

    public function test_le_o_valor_de_arrematacao(): void
    {
        $lotes = $this->lotes();

        $this->assertSame(ResultadoLote::Arrematado, $lotes[1]['resultado']);
        $this->assertSame('58000.00', $lotes[1]['valor']);
        $this->assertSame('1234600.00', $lotes[5]['valor']);
        $this->assertSame('900.50', $lotes[8]['valor']);
        $this->assertSame('16522.00', $lotes[10]['valor']);
    }

    public function test_nome_quebrado_em_varias_linhas_nao_atrapalha(): void
    {
        $lotes = $this->lotes();

        $this->assertSame('66666.00', $lotes[3]['valor']);
        $this->assertSame('38500.00', $lotes[4]['valor']);
        $this->assertSame('1234600.00', $lotes[5]['valor']);
    }

    public function test_lotes_nao_arrematados_e_excluidos(): void
    {
        $lotes = $this->lotes();

        $this->assertSame(['resultado' => ResultadoLote::NaoArrematado, 'valor' => null], $lotes[6]);
        $this->assertSame(['resultado' => ResultadoLote::Excluido, 'valor' => null], $lotes[7]);
    }

    public function test_soma_confere_com_o_total_geral_do_extrato(): void
    {
        $soma = array_sum(array_map(fn (array $lote) => (float) $lote['valor'], $this->lotes()));

        $this->assertSame('1497488.50', number_format($soma, 2, '.', ''));
    }

    public function test_texto_sem_lotes(): void
    {
        $this->assertSame([], ExtratoLeilao::interpretar("Relatório de Extrato do Leilão\nTotal Geral 0,00"));
    }
}
