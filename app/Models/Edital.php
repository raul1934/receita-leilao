<?php

namespace App\Models;

use App\Enums\ResultadoLote;
use App\Enums\SituacaoEdital;
use App\Services\Sle\EditalRef;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Table('editais')]
#[Fillable([
    'unidade', 'numero', 'exercicio', 'codigo', 'situacao', 'tipo', 'orgao', 'unidade_nome', 'cidade',
    'permite_pf', 'data_inicio_propostas', 'data_fim_propostas', 'data_classificacao', 'data_abertura_lances',
    'forma_contato', 'dados_publicacao', 'dados', 'importado_em', 'resultado_importado_em',
])]
class Edital extends Model
{
    protected function casts(): array
    {
        return [
            'permite_pf' => 'boolean',
            'data_inicio_propostas' => 'datetime',
            'data_fim_propostas' => 'datetime',
            'data_classificacao' => 'datetime',
            'data_abertura_lances' => 'datetime',
            'dados' => 'array',
            'importado_em' => 'datetime',
            'resultado_importado_em' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Lote, $this>
     */
    public function lotes(): HasMany
    {
        return $this->hasMany(Lote::class);
    }

    /**
     * @return HasManyThrough<LoteItem, Lote, $this>
     */
    public function itens(): HasManyThrough
    {
        return $this->hasManyThrough(LoteItem::class, Lote::class);
    }

    /**
     * @return HasManyThrough<LoteImagem, Lote, $this>
     */
    public function imagens(): HasManyThrough
    {
        return $this->hasManyThrough(LoteImagem::class, Lote::class);
    }

    /**
     * O portal publica o "Extrato do Leilão" (valores de arremate) quando a
     * sessão do edital termina; a permissão vem nos dados do edital.
     */
    public function temExtrato(): bool
    {
        return in_array('extrato-leilao', $this->dados['permissoes'] ?? [], true);
    }

    /**
     * Totais do resultado do leilão, sem os arremates suspeitos.
     *
     * @return array{lotes: int, de: int, total: float|int|string, suspeitos: int}
     */
    public function resumoArremate(): array
    {
        $validos = fn () => $this->lotes()->where('resultado', ResultadoLote::Arrematado)->where('arremate_suspeito', false);

        return [
            'lotes' => $validos()->count(),
            'de' => $this->lotes()->count(),
            'total' => $validos()->sum('valor_arremate'),
            'suspeitos' => $this->lotes()->where('arremate_suspeito', true)->count(),
        ];
    }

    public function ref(): EditalRef
    {
        return new EditalRef($this->unidade, $this->numero, $this->exercicio);
    }

    /**
     * Leilões com abertura dos lances ainda por vir primeiro (o mais próximo
     * no topo) e depois os que já começaram (o mais recente no topo).
     */
    #[Scope]
    protected function proximosPrimeiro(Builder $query): void
    {
        self::ordenarProximosPrimeiro($query);
    }

    /**
     * Também usado em consultas de lotes com join em editais, passando a
     * coluna qualificada ("editais.data_abertura_lances").
     */
    public static function ordenarProximosPrimeiro(Builder $query, string $coluna = 'data_abertura_lances'): Builder
    {
        $agora = now();

        return $query->orderByRaw("case when {$coluna} >= ? then 0 else 1 end", [$agora])
            ->orderByRaw("case when {$coluna} >= ? then {$coluna} end", [$agora])
            ->orderByDesc($coluna);
    }

    /**
     * "abertos": sessão de lances ainda não encerrada; "fechados": encerrada.
     * Qualquer outro valor não filtra.
     */
    #[Scope]
    protected function comLances(Builder $query, ?string $estado): void
    {
        if (in_array($estado, ['abertos', 'fechados'], true)) {
            $query->whereIn('situacao', SituacaoEdital::codigos(lancesEncerrados: $estado === 'fechados'));
        }
    }

    protected function situacaoDescricao(): Attribute
    {
        return Attribute::get(fn () => SituacaoEdital::descricao($this->situacao));
    }
}
