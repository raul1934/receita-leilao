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

    /**
     * Variação percentual entre dois valores, ex: "-16,7%" ou "+5,0%".
     */
    public static function variacao(int|float|string|null $antes, int|float|string|null $depois): ?string
    {
        if ($antes === null || $depois === null || (float) $antes == 0.0) {
            return null;
        }

        $percentual = ((float) $depois - (float) $antes) / (float) $antes * 100;

        return ($percentual > 0 ? '+' : '').number_format($percentual, 1, ',', '.').'%';
    }

    public static function dataHora(?DateTimeInterface $data): string
    {
        return $data?->format('d/m/Y H:i') ?? '-';
    }
}
