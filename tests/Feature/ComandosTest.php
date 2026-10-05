<?php

namespace Tests\Feature;

use App\Jobs\ImportarEdital;
use App\Models\Edital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ComandosTest extends TestCase
{
    use RefreshDatabase;

    public function test_importar_salva_o_edital_a_partir_da_url(): void
    {
        $this->fakeSle();

        $this->artisan('leilao:importar', [
            'editais' => ['https://www25.receita.fazenda.gov.br/sle-sociedade/portal/edital/700100/12/2026'],
        ])->assertSuccessful();

        $edital = Edital::sole();
        $this->assertSame('0700100/000012/2026', $edital->codigo);
        $this->assertSame(13, $edital->itens()->count());
    }

    public function test_importar_com_fila_despacha_o_job(): void
    {
        Queue::fake();

        $this->artisan('leilao:importar', ['editais' => ['700100/12/2026'], '--fila' => true])
            ->expectsOutputToContain('enviado para a fila')
            ->assertSuccessful();

        Queue::assertPushed(ImportarEdital::class, fn (ImportarEdital $job) => $job->ref->path() === '700100/12/2026' && $job->comDetalhes);
    }

    public function test_importar_rejeita_entrada_invalida(): void
    {
        $this->fakeSle();

        $this->artisan('leilao:importar', ['editais' => ['qualquer-coisa']])
            ->expectsOutputToContain('Edital inválido')
            ->assertFailed();

        $this->assertSame(0, Edital::count());
    }

    public function test_importar_informa_erro_do_sle(): void
    {
        $this->fakeSle(['*/api/edital/700100/12/2026' => $this->respostaDeErroDoSle()]);

        $this->artisan('leilao:importar', ['editais' => ['700100/12/2026']])
            ->expectsOutputToContain('Não foi possível realizar a operação')
            ->assertFailed();
    }

    public function test_sincronizar_filtra_por_situacao_e_despacha_com_a_cidade(): void
    {
        $this->fakeSle();
        Queue::fake();

        $this->artisan('leilao:sincronizar', ['--situacao' => ['2'], '--fila' => true])
            ->expectsOutputToContain('2 edital(is) encontrado(s)')
            ->assertSuccessful();

        Queue::assertPushed(ImportarEdital::class, 2);
        Queue::assertPushed(ImportarEdital::class, fn (ImportarEdital $job) => $job->ref->path() === '717700/3/2026' && $job->cidade === 'RIO DE JANEIRO');
        Queue::assertNotPushed(ImportarEdital::class, fn (ImportarEdital $job) => $job->ref->path() === '600100/3/2026');
    }

    public function test_sincronizar_importa_e_continua_apos_falha(): void
    {
        $this->fakeSle(['*/api/edital/717700/3/2026' => $this->respostaDeErroDoSle()]);

        $this->artisan('leilao:sincronizar', ['--situacao' => ['2']])
            ->expectsOutputToContain('700100/12/2026 (RIO DE JANEIRO, Disponibilizado): 1 lote(s)')
            ->assertFailed();

        $edital = Edital::sole();
        $this->assertSame('RIO DE JANEIRO', $edital->cidade);
        $this->assertSame(13, $edital->itens()->count());
    }
}
