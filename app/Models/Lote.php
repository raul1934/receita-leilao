<?php

namespace App\Models;

use App\Enums\ResultadoLote;
use App\Enums\SituacaoLote;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'edital_id', 'numero', 'sequencial', 'tipo', 'situacao', 'valor_minimo', 'valor_avaliacao',
    'resultado', 'valor_arremate', 'permite_pf', 'dados', 'detalhes_importados_em',
])]
class Lote extends Model
{
    /**
     * Campos cujas mudanças ficam registradas em lote_historicos.
     */
    public const CAMPOS_HISTORICO = ['situacao', 'valor_minimo', 'valor_avaliacao'];

    protected static function booted(): void
    {
        static::created(fn (Lote $lote) => $lote->registrarHistorico());

        static::updated(function (Lote $lote) {
            // doesntExist() cobre lotes gravados antes de o histórico existir.
            if ($lote->wasChanged(self::CAMPOS_HISTORICO) || $lote->historico()->doesntExist()) {
                $lote->registrarHistorico();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'valor_minimo' => 'decimal:2',
            'valor_avaliacao' => 'decimal:2',
            'resultado' => ResultadoLote::class,
            'valor_arremate' => 'decimal:2',
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
     * @return HasMany<LoteHistorico, $this>
     */
    public function historico(): HasMany
    {
        return $this->hasMany(LoteHistorico::class);
    }

    /**
     * Lista usada pela galeria de fotos das views (data-galeria).
     *
     * @return list<array{url: string, miniatura: string|null}>
     */
    public function fotosParaGaleria(): array
    {
        return $this->imagens
            ->map(fn (LoteImagem $imagem) => ['url' => $imagem->url, 'miniatura' => $imagem->url_miniatura])
            ->values()
            ->all();
    }

    private function registrarHistorico(): void
    {
        $this->historico()->create($this->only(self::CAMPOS_HISTORICO));
    }

    protected function situacaoDescricao(): Attribute
    {
        return Attribute::get(fn () => SituacaoLote::descricao($this->situacao));
    }
}
