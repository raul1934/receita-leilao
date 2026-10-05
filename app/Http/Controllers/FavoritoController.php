<?php

namespace App\Http\Controllers;

use App\Models\Edital;
use App\Models\Lote;
use App\Models\LoteItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoritoController extends Controller
{
    public function index(Request $request): View
    {
        $lances = $request->string('lances')->value();

        $query = Lote::query()
            ->select('lotes.*')
            ->join('editais', 'editais.id', '=', 'lotes.edital_id')
            ->whereNotNull('lotes.favoritado_em')
            ->whereHas('edital', fn ($q) => $q->comLances($lances))
            ->with(['edital', 'imagens' => fn ($q) => $q->orderBy('id')]);

        $lotes = Edital::ordenarProximosPrimeiro($query, 'editais.data_abertura_lances')
            ->orderBy('lotes.numero')
            ->paginate(50)
            ->withQueryString();

        $depositosPorLote = LoteItem::depositosPorLote($lotes->pluck('id'));

        return view('favoritos.index', compact('lotes', 'lances', 'depositosPorLote'));
    }

    /**
     * Marca ou desmarca o lote. Com JavaScript a estrela muda sem recarregar
     * a página (resposta JSON); sem, volta para a página anterior.
     */
    public function alternar(Request $request, Edital $edital, Lote $lote): JsonResponse|RedirectResponse
    {
        $lote->favoritado_em = $lote->favoritado_em ? null : now();
        $lote->save();

        $favorito = $lote->favoritado_em !== null;

        if ($request->expectsJson()) {
            return response()->json(['favorito' => $favorito, 'total' => Lote::whereNotNull('favoritado_em')->count()]);
        }

        return back()->with('status', $favorito
            ? "Lote {$lote->numero} adicionado aos favoritos."
            : "Lote {$lote->numero} removido dos favoritos.");
    }
}
