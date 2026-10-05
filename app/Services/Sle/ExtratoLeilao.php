<?php

namespace App\Services\Sle;

use App\Enums\ResultadoLote;

/**
 * Lê o texto do PDF "Extrato do Leilão" (pdftotext -layout), que tem uma
 * linha por lote:
 *
 *     1     21.488.829/0001-55   NOME DO ARREMATANTE            58.000,00
 *     338                        Lote Não Arrematado
 *
 * Nomes longos quebram em linhas sem número de lote, acima e abaixo da
 * linha do valor; essas linhas são ignoradas, assim como cabeçalhos,
 * rodapés e o "Total Geral".
 */
final class ExtratoLeilao
{
    private const ARREMATADO = '~^\s*(\d+)\s+[\d*][\d*./-]+\s.*?\s(\d{1,3}(?:\.\d{3})*,\d{2})\s*$~u';

    private const SEM_ARREMATE = '~^\s*(\d+)\s+Lote\s+(Não\s+Arrematado|Excluído)\s*$~iu';

    /**
     * @return array<int, array{resultado: ResultadoLote, valor: string|null}> número do lote => resultado
     */
    public static function interpretar(string $texto): array
    {
        $lotes = [];

        foreach (preg_split('~\R~u', $texto) as $linha) {
            if (preg_match(self::ARREMATADO, $linha, $m)) {
                $lotes[(int) $m[1]] = [
                    'resultado' => ResultadoLote::Arrematado,
                    'valor' => str_replace(['.', ','], ['', '.'], $m[2]),
                ];
            } elseif (preg_match(self::SEM_ARREMATE, $linha, $m)) {
                $lotes[(int) $m[1]] = [
                    'resultado' => str_starts_with(mb_strtolower($m[2]), 'exclu') ? ResultadoLote::Excluido : ResultadoLote::NaoArrematado,
                    'valor' => null,
                ];
            }
        }

        return $lotes;
    }
}
