<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Controla tarefas que devem rodar uma vez por dia a partir de um horário
 * (SLE_SINCRONIZACAO_HORARIO). O agendador só funciona com o computador
 * ligado: se ele estava desligado no horário, a tarefa continua "pendente" e
 * roda assim que o agendador voltar, em vez de pular o dia.
 */
final class ExecucaoDiaria
{
    public static function pendente(string $tarefa): bool
    {
        $horarioDeHoje = today()->setTimeFromTimeString(config('sle.sincronizacao_horario'));
        $ultima = Cache::get(self::chave($tarefa));

        return now()->gte($horarioDeHoje) && ($ultima === null || $ultima < $horarioDeHoje->getTimestamp());
    }

    public static function registrar(string $tarefa): void
    {
        Cache::forever(self::chave($tarefa), now()->getTimestamp());
    }

    private static function chave(string $tarefa): string
    {
        return "execucao_diaria:{$tarefa}";
    }
}
