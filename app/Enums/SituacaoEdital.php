<?php

namespace App\Enums;

/**
 * Situações do edital, conforme o portal do SLE.
 */
enum SituacaoEdital: int
{
    case EmPreparacao = 1;
    case Disponibilizado = 2;
    case AbertoParaProposta = 3;
    case FechadoParaProposta = 4;
    case AbertaSessaoPublicaEmClassificacao = 5;
    case AbertaSessaoPublicaClassificacaoEncerrada = 6;
    case AbertaSessaoParaLance = 7;
    case FechadaSessaoParaLance = 8;
    case FechadaSessaoParaLanceLotesAdjudicados = 9;
    case EncerradaSessaoPublica = 10;
    case Encerrado = 11;
    case Homologado = 12;
    case SessaoPublicaSuspensa = 13;
    case Cancelado = 14;
    case EncerradaSessaoPublicaAtaPublicada = 15;
    case Excluido = 16;

    public function label(): string
    {
        return match ($this) {
            self::EmPreparacao => 'Em Preparação',
            self::Disponibilizado => 'Disponibilizado',
            self::AbertoParaProposta => 'Aberto para Proposta',
            self::FechadoParaProposta => 'Fechado para Proposta',
            self::AbertaSessaoPublicaEmClassificacao => 'Aberta Sessão Pública - Em Classificação',
            self::AbertaSessaoPublicaClassificacaoEncerrada => 'Aberta Sessão Pública - Classificação Encerrada',
            self::AbertaSessaoParaLance => 'Aberta Sessão para Lance',
            self::FechadaSessaoParaLance => 'Fechada Sessão para Lance',
            self::FechadaSessaoParaLanceLotesAdjudicados => 'Fechada Sessão para Lance - Lotes Adjudicados',
            self::EncerradaSessaoPublica => 'Encerrada Sessão Pública',
            self::Encerrado => 'Encerrado',
            self::Homologado => 'Homologado',
            self::SessaoPublicaSuspensa => 'Sessão Pública Suspensa',
            self::Cancelado => 'Cancelado',
            self::EncerradaSessaoPublicaAtaPublicada => 'Encerrada Sessão Pública - Ata Publicada',
            self::Excluido => 'Excluído',
        };
    }

    /**
     * Leilão já terminado: os lotes praticamente não mudam mais, então a
     * sincronização diária não precisa reimportar o edital.
     */
    public function finalizada(): bool
    {
        return in_array($this, [
            self::Encerrado,
            self::Homologado,
            self::Cancelado,
            self::EncerradaSessaoPublicaAtaPublicada,
            self::Excluido,
        ], true);
    }

    public static function descricao(?int $codigo): string
    {
        if ($codigo === null) {
            return '-';
        }

        return self::tryFrom($codigo)?->label() ?? "Situação {$codigo}";
    }
}
