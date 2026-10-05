<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('lote_itens')]
#[Fillable(['lote_id', 'ordem', 'descricao', 'quantidade', 'unidade_medida', 'recinto_armazenador', 'nr_referencia'])]
class LoteItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Lote, $this>
     */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }
}
