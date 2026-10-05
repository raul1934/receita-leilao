<?php

namespace App\Http\Controllers;

use App\Models\LoteHistorico;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MudancaController extends Controller
{
    /**
     * Períodos aceitos no filtro => dias (null = tudo).
     */
    private const PERIODOS = ['24h' => 1, '7d' => 7, '30d' => 30, 'tudo' => null];

    public function index(Request $request): View
    {
        $periodo = array_key_exists($request->query('periodo'), self::PERIODOS) ? $request->query('periodo') : '7d';
        $campo = array_key_exists($request->query('mudanca'), LoteHistorico::MUDOU) ? $request->query('mudanca') : null;
        $dias = self::PERIODOS[$periodo];
        $desde = $dias ? now()->subDays($dias) : null;

        $resumo = LoteHistorico::mudancas($desde)->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when '.LoteHistorico::MUDOU['situacao'].' then 1 else 0 end) as situacao')
            ->selectRaw('sum(case when '.LoteHistorico::MUDOU['valor_minimo'].' then 1 else 0 end) as valor_minimo')
            ->selectRaw('sum(case when '.LoteHistorico::MUDOU['valor_avaliacao'].' then 1 else 0 end) as valor_avaliacao')
            ->first();

        $mudancas = LoteHistorico::mudancas($desde)
            ->when($campo, fn ($q) => $q->whereRaw(LoteHistorico::MUDOU[$campo]))
            ->with('lote.edital')
            ->orderByDesc('registrado_em')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('mudancas.index', compact('mudancas', 'resumo', 'periodo', 'campo'));
    }
}
