<?php

namespace App\Enums;

/**
 * Situações do lote, conforme o portal do SLE.
 */
enum SituacaoLote: int
{
    case Montado = 1;
    case MontadoComValorMinimo = 2;
    case EmExecucaoNoOffline = 3;
    case ArrematadoNaoPago = 4;
    case ArrematadoPagoNaoRetirado = 5;
    case ArrematadoPagoRetirado = 6;
    case NaoArrematado = 7;
    case Arrematado = 8;
    case Excluido = 9;
    case Baixado = 10;
    case Disponibilizado = 11;
    case AbertoParaProposta = 12;
    case FechadoParaProposta = 13;
    case Suspenso = 14;
    case AbertoParaLance = 15;
    case Encerrado = 16;
    case DisponivelParaReaproveitamento = 17;
    case Reaproveitado = 18;
    case ComReaproveitamentoCancelado = 19;
    case Disponivel = 20;
    case ComLancesEmpatados = 21;

    public function label(): string
    {
        return match ($this) {
            self::Montado => 'Lote Montado',
            self::MontadoComValorMinimo => 'Lote Montado com Valor Mínimo',
            self::EmExecucaoNoOffline => 'Lote em Execução no Offline',
            self::ArrematadoNaoPago => 'Lote Arrematado Não Pago',
            self::ArrematadoPagoNaoRetirado => 'Lote Arrematado Pago Não Retirado',
            self::ArrematadoPagoRetirado => 'Lote Arrematado Pago Retirado',
            self::NaoArrematado => 'Lote Não Arrematado',
            self::Arrematado => 'Arrematado',
            self::Excluido => 'Lote Excluído',
            self::Baixado => 'Lote Baixado',
            self::Disponibilizado => 'Disponibilizado',
            self::AbertoParaProposta => 'Aberto para Proposta',
            self::FechadoParaProposta => 'Fechado para Proposta',
            self::Suspenso => 'Suspenso',
            self::AbertoParaLance => 'Aberto para Lance',
            self::Encerrado => 'Encerrado',
            // O portal exibe estas quatro situações como "Baixado".
            self::DisponivelParaReaproveitamento,
            self::Reaproveitado,
            self::ComReaproveitamentoCancelado,
            self::Disponivel => 'Baixado',
            self::ComLancesEmpatados => 'Lote com Lances Empatados',
        };
    }

    public static function descricao(?int $codigo): string
    {
        if ($codigo === null) {
            return '-';
        }

        return self::tryFrom($codigo)?->label() ?? "Situação {$codigo}";
    }
}
