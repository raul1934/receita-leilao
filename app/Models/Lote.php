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
        // Sem "fn": um listener de saving que devolve false cancela o save.
        static::saving(function (Lote $lote) {
            $lote->arremate_suspeito = self::arremateSuspeito($lote->valor_arremate, $lote->valor_minimo, $lote->valor_avaliacao);
        });

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
            'arremate_suspeito' => 'boolean',
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
     * Quantas vezes o arremate supera o maior valor de referência do lote
     * (mínimo ou avaliação). O mínimo sozinho não serve: às vezes é simbólico,
     * como R$ 10 num lote avaliado em R$ 5.000.
     */
    public static function multiploArremate(int|float|string|null $arremate, int|float|string|null $minimo, int|float|string|null $avaliacao): ?float
    {
        $referencia = max((float) $minimo, (float) $avaliacao);

        return $arremate === null || $referencia <= 0 ? null : (float) $arremate / $referencia;
    }

    public static function arremateSuspeito(int|float|string|null $arremate, int|float|string|null $minimo, int|float|string|null $avaliacao): bool
    {
        return (self::multiploArremate($arremate, $minimo, $avaliacao) ?? 0) > config('sle.arremate_suspeito_multiplo');
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

    protected function multiploDoArremate(): Attribute
    {
        return Attribute::get(fn () => self::multiploArremate($this->valor_arremate, $this->valor_minimo, $this->valor_avaliacao));
    }

    protected function situacaoDescricao(): Attribute
    {
        return Attribute::get(fn () => SituacaoLote::descricao($this->situacao));
    }
}
