<?php

use App\Support\ExecucaoDiaria;
use Illuminate\Support\Facades\Schedule;

// Rodado pelo container "scheduler" (php artisan schedule:work). O agendador
// só funciona com o computador ligado, então em vez de um horário fixo cada
// tarefa é conferida a cada 5 minutos e roda uma vez por dia a partir de
// SLE_SINCRONIZACAO_HORARIO; se o computador estava desligado no horário, ela
// roda assim que ele ligar. Uma execução manual completa também conta.

// As importações vão para a fila; o job é único por edital, então uma
// execução que ainda não terminou não é duplicada.
Schedule::command('leilao:sincronizar --fila')
    ->everyFiveMinutes()
    ->when(fn () => ExecucaoDiaria::pendente('leilao:sincronizar'))
    ->withoutOverlapping();

// A importação já lê o resultado dos editais encerrados; isto completa os que
// ficaram sem (falha ao ler o PDF, por exemplo) e atualiza os não finalizados.
Schedule::command('leilao:resultados')
    ->everyFiveMinutes()
    ->when(fn () => ExecucaoDiaria::pendente('leilao:resultados'))
    ->withoutOverlapping();
