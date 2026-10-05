<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

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
     * Depósitos (recintos armazenadores) de cada lote, o com mais itens
     * primeiro. Quase sempre há um só por lote.
     *
     * @param  iterable<int>  $loteIds
     * @return Collection<int, Collection<int, string>> id do lote => depósitos
     */
    public static function depositosPorLote(iterable $loteIds): Collection
    {
        return static::query()
            ->whereIn('lote_id', collect($loteIds)->all())
            ->whereNotNull('recinto_armazenador')
            ->select('lote_id', 'recinto_armazenador')
            ->selectRaw('count(*) as itens')
            ->groupBy('lote_id', 'recinto_armazenador')
            ->get()
            ->groupBy('lote_id')
            ->map(fn ($grupo) => $grupo->sortByDesc('itens')->pluck('recinto_armazenador')->values());
    }

    /**
     * @return BelongsTo<Lote, $this>
     */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }
}
