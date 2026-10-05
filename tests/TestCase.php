<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Respostas reais da API do SLE, salvas em tests/Fixtures/sle.
     */
    protected function fixture(string $nome): array
    {
        return json_decode(file_get_contents(__DIR__."/Fixtures/sle/{$nome}.json"), true);
    }

    /**
     * Simula a API do SLE. As respostas passadas têm prioridade sobre as padrão.
     *
     * @param  array<string, mixed>  $respostas
     */
    protected function fakeSle(array $respostas = []): void
    {
        Http::preventStrayRequests();

        Http::fake($respostas + [
            '*/api/edital/700100/12/2026' => Http::response($this->fixture('edital_700100_12_2026')),
            '*/api/lote/700100/12/2026/1' => Http::response($this->fixture('lote_700100_12_2026_1')),
            '*/api/editais-disponiveis' => Http::response($this->fixture('editais_disponiveis')),
        ]);
    }

    protected function respostaDeErroDoSle()
    {
        return Http::response([
            'code' => 'ER9999',
            'message' => 'Não foi possível realizar a operação devido a falha do sistema. Tente novamente mais tarde.',
        ], 500);
    }
}
