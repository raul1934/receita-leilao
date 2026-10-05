<?php

namespace App\Enums;

/**
 * Resultado do lote segundo o "Extrato do Leilão" publicado pelo SLE.
 */
enum ResultadoLote: string
{
    case Arrematado = 'arrematado';
    case NaoArrematado = 'nao_arrematado';
    case Excluido = 'excluido';

    public function label(): string
    {
        return match ($this) {
            self::Arrematado => 'Arrematado',
            self::NaoArrematado => 'Não arrematado',
            self::Excluido => 'Excluído',
        };
    }
}
