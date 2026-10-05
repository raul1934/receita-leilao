<?php

namespace App\Models;

use App\Enums\SituacaoLote;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Estado de um lote a partir de um momento: um registro novo é criado
 * sempre que a situação ou os valores mudam numa importação.
 */
#[Fillable(['lote_id', 'situacao', 'valor_minimo', 'valor_avaliacao'])]
class LoteHistorico extends Model
{
    const CREATED_AT = 'registrado_em';

    const UPDATED_AT = null;

    /**
     * Condição SQL de "o campo mudou em relação ao registro anterior", para
     * usar sobre mudancas().
     */
    public const MUDOU = [
        'situacao' => 'coalesce(situacao, -1) <> coalesce(situacao_anterior, -1)',
        'valor_minimo' => 'coalesce(valor_minimo, -1) <> coalesce(valor_minimo_anterior, -1)',
        'valor_avaliacao' => 'coalesce(valor_avaliacao, -1) <> coalesce(valor_avaliacao_anterior, -1)',
    ];

    protected function casts(): array
    {
        return [
            'situacao' => 'integer',
            'situacao_anterior' => 'integer',
            'valor_minimo' => 'decimal:2',
            'valor_minimo_anterior' => 'decimal:2',
            'valor_avaliacao' => 'decimal:2',
            'valor_avaliacao_anterior' => 'decimal:2',
            'registrado_em' => 'datetime',
        ];
    }

    /**
     * Registros que são mudanças (todos menos o primeiro de cada lote), com os
     * valores do registro anterior em situacao_anterior, valor_minimo_anterior
     * e valor_avaliacao_anterior.
     *
     * @return Builder<static>
     */
    public static function mudancas(?CarbonInterface $desde = null): Builder
    {
        $janela = 'over (partition by lote_id order by registrado_em, id)';

        $comAnterior = static::query()
            ->select('lote_historicos.*')
            ->selectRaw("lag(id) {$janela} as anterior_id")
            ->selectRaw("lag(situacao) {$janela} as situacao_anterior")
            ->selectRaw("lag(valor_minimo) {$janela} as valor_minimo_anterior")
            ->selectRaw("lag(valor_avaliacao) {$janela} as valor_avaliacao_anterior")
            // O registro anterior pode ser mais antigo que o período, então a
            // janela olha o histórico inteiro dos lotes que mudaram no período.
            ->when($desde, fn ($q) => $q->whereIn('lote_id', static::query()->select('lote_id')->where('registrado_em', '>=', $desde)));

        return static::query()
            ->fromSub($comAnterior, 'lote_historicos')
            ->whereNotNull('anterior_id')
            ->when($desde, fn ($q) => $q->where('registrado_em', '>=', $desde));
    }

    /**
     * Só faz sentido em registros vindos de mudancas().
     */
    public function mudou(string $campo): bool
    {
        return $this->getAttribute($campo) !== $this->getAttribute("{$campo}_anterior");
    }

    /**
     * @return BelongsTo<Lote, $this>
     */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    protected function situacaoDescricao(): Attribute
    {
        return Attribute::get(fn () => SituacaoLote::descricao($this->situacao));
    }

    protected function situacaoAnteriorDescricao(): Attribute
    {
        return Attribute::get(fn () => SituacaoLote::descricao($this->situacao_anterior));
    }
}
