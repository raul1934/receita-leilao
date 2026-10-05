<?php

namespace App\Models;

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
        $agora = now();

        $query->orderByRaw('case when data_abertura_lances >= ? then 0 else 1 end', [$agora])
            ->orderByRaw('case when data_abertura_lances >= ? then data_abertura_lances end', [$agora])
            ->orderByDesc('data_abertura_lances');
    }

    protected function situacaoDescricao(): Attribute
    {
        return Attribute::get(fn () => SituacaoEdital::descricao($this->situacao));
    }
}
