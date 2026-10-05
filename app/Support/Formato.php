<?php

namespace App\Support;

use DateTimeInterface;

/**
 * Formatação no padrão brasileiro para as views.
 */
final class Formato
{
    public static function moeda(int|float|string|null $valor): string
    {
        return $valor === null ? '-' : 'R$ '.number_format((float) $valor, 2, ',', '.');
    }

    public static function quantidade(int|float|string|null $valor): string
    {
        if ($valor === null) {
            return '-';
        }

        return rtrim(rtrim(number_format((float) $valor, 4, ',', '.'), '0'), ',');
    }

    public static function dataHora(?DateTimeInterface $data): string
    {
        return $data?->format('d/m/Y H:i') ?? '-';
    }
}
