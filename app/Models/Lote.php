<?php

namespace App\Models;

use App\Enums\SituacaoLote;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'edital_id', 'numero', 'sequencial', 'tipo', 'situacao', 'valor_minimo', 'valor_avaliacao',
    'permite_pf', 'dados', 'detalhes_importados_em',
])]
class Lote extends Model
{
    protected function casts(): array
    {
        return [
            'valor_minimo' => 'decimal:2',
            'valor_avaliacao' => 'decimal:2',
            'permite_pf' => 'boolean',
            'dados' => 'array',
            'detalhes_importados_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Edital, $this>
     */
    public function edital(): BelongsTo
    {
        return $this->belongsTo(Edital::class);
    }

    /**
     * @return HasMany<LoteItem, $this>
     */
    public function itens(): HasMany
    {
        return $this->hasMany(LoteItem::class);
    }

    /**
     * @return HasMany<LoteImagem, $this>
     */
    public function imagens(): HasMany
    {
        return $this->hasMany(LoteImagem::class);
    }

    /**
     * @return HasOne<LoteImagem, $this>
     */
    public function primeiraImagem(): HasOne
    {
        return $this->hasOne(LoteImagem::class)->oldestOfMany();
    }

    protected function situacaoDescricao(): Attribute
    {
        return Attribute::get(fn () => SituacaoLote::descricao($this->situacao));
    }
}
