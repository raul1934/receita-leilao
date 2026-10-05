<?php

namespace Tests\Feature;

use App\Jobs\ImportarEdital;
use App\Models\Edital;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
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

    public function test_sincronizar_processa_os_leiloes_mais_proximos_primeiro(): void
    {
        $this->travelTo('2026-10-05 12:00');
        $this->fakeSle();
        Queue::fake();

        $this->artisan('leilao:sincronizar', ['--fila' => true])->assertSuccessful();

        // Abertura dos lances: 08/10 e 12/11 ainda por vir; 13/08 já passou.
        $this->assertSame(
            ['700100/12/2026', '717700/3/2026', '600100/3/2026'],
            Queue::pushed(ImportarEdital::class)->map(fn (ImportarEdital $job) => $job->ref->path())->values()->all(),
        );
    }

    public function test_sincronizar_ignora_finalizados_que_ja_foram_importados(): void
    {
        $this->fakeSle();
        Queue::fake();
        $this->editalFinalizado(situacao: 12, detalhado: true);

        $this->artisan('leilao:sincronizar', ['--fila' => true])
            ->expectsOutputToContain('3 edital(is) encontrado(s), 1 finalizado(s) e já importado(s) ignorado(s)')
            ->assertSuccessful();

        Queue::assertPushed(ImportarEdital::class, 2);
        Queue::assertNotPushed(ImportarEdital::class, fn (ImportarEdital $job) => $job->ref->path() === '600100/3/2026');

        $this->artisan('leilao:sincronizar', ['--fila' => true, '--todos' => true])->assertSuccessful();

        Queue::assertPushed(ImportarEdital::class, fn (ImportarEdital $job) => $job->ref->path() === '600100/3/2026');
    }

    public function test_sincronizar_reimporta_finalizado_que_mudou_de_situacao(): void
    {
        $this->fakeSle();
        Queue::fake();
        // Na última importação o leilão ainda estava em andamento.
        $this->editalFinalizado(situacao: 8, detalhado: true);

        $this->artisan('leilao:sincronizar', ['--fila' => true])->assertSuccessful();

        Queue::assertPushed(ImportarEdital::class, fn (ImportarEdital $job) => $job->ref->path() === '600100/3/2026');
    }

    public function test_sincronizar_reimporta_finalizado_com_lotes_sem_detalhes(): void
    {
        $this->fakeSle();
        Queue::fake();
        $this->editalFinalizado(situacao: 12, detalhado: false);

        $this->artisan('leilao:sincronizar', ['--fila' => true])->assertSuccessful();

        Queue::assertPushed(ImportarEdital::class, fn (ImportarEdital $job) => $job->ref->path() === '600100/3/2026');
    }

    public function test_sincronizar_ignora_finalizado_cujos_lotes_sem_detalhes_estao_baixados(): void
    {
        $this->fakeSle();
        Queue::fake();
        $edital = $this->editalFinalizado(situacao: 12, detalhado: true);
        // O portal não mostra detalhes de lotes baixados (situações 17 a 20).
        $edital->lotes()->create(['numero' => 2, 'situacao' => 18]);

        $this->artisan('leilao:sincronizar', ['--fila' => true])->assertSuccessful();

        Queue::assertNotPushed(ImportarEdital::class, fn (ImportarEdital $job) => $job->ref->path() === '600100/3/2026');
    }

    public function test_sincronizacao_diaria_esta_agendada(): void
    {
        $eventos = collect(app(Schedule::class)->events())
            ->filter(fn (Event $evento) => str_contains($evento->command, 'leilao:sincronizar --fila'));

        $this->assertCount(1, $eventos);
        $this->assertSame('0 6 * * *', $eventos->first()->expression);
    }

    private function editalFinalizado(int $situacao, bool $detalhado): Edital
    {
        $edital = Edital::create([
            'unidade' => 600100, 'numero' => 3, 'exercicio' => 2026,
            'situacao' => $situacao, 'importado_em' => now(),
        ]);
        $edital->lotes()->create(['numero' => 1, 'detalhes_importados_em' => $detalhado ? now() : null]);

        return $edital;
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
