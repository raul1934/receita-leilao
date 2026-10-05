<?php

use Illuminate\Support\Facades\Schedule;

// Rodado pelo container "scheduler" (php artisan schedule:work). As importações
// vão para a fila; o job é único por edital, então uma execução que ainda não
// terminou não é duplicada.
Schedule::command('leilao:sincronizar --fila')
    ->dailyAt(config('sle.sincronizacao_horario'))
    ->withoutOverlapping();
