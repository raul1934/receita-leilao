<?php

namespace App\Models;

use App\Enums\SituacaoLote;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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

    protected function casts(): array
    {
        return [
            'valor_minimo' => 'decimal:2',
            'valor_avaliacao' => 'decimal:2',
            'registrado_em' => 'datetime',
        ];
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
}
