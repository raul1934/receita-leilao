<?php

namespace App\Models;

use App\Enums\SituacaoEdital;
use App\Services\Sle\EditalRef;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Table('editais')]
#[Fillable([
    'unidade', 'numero', 'exercicio', 'codigo', 'situacao', 'tipo', 'orgao', 'unidade_nome', 'cidade',
    'permite_pf', 'data_inicio_propostas', 'data_fim_propostas', 'data_classificacao', 'data_abertura_lances',
    'forma_contato', 'dados_publicacao', 'dados', 'importado_em',
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

    public function ref(): EditalRef
    {
        return new EditalRef($this->unidade, $this->numero, $this->exercicio);
    }

    protected function situacaoDescricao(): Attribute
    {
        return Attribute::get(fn () => SituacaoEdital::descricao($this->situacao));
    }
}
