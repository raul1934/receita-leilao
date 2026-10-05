<?php

use Illuminate\Support\Facades\Schedule;

// Rodado pelo container "scheduler" (php artisan schedule:work). As importações
// vão para a fila; o job é único por edital, então uma execução que ainda não
// terminou não é duplicada.
Schedule::command('leilao:sincronizar --fila')
    ->dailyAt(config('sle.sincronizacao_horario'))
    ->withoutOverlapping();

// A importação já lê o resultado dos editais encerrados; isto completa os que
// ficaram sem (falha ao ler o PDF, por exemplo) e atualiza os não finalizados.
Schedule::command('leilao:resultados')
    ->dailyAt(config('sle.sincronizacao_horario'))
    ->withoutOverlapping();
