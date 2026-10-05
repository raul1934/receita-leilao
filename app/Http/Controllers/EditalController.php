<?php

namespace App\Http\Controllers;

use App\Jobs\ImportarEdital;
use App\Models\Edital;
use App\Models\LoteItem;
use App\Services\Sle\EditalRef;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class EditalController extends Controller
{
    public function index(Request $request): View
    {
        $busca = $request->string('q')->trim()->value();
        $ordem = $request->string('ordem')->value();
        $lances = $request->string('lances')->value();

        $editais = Edital::query()
            ->withCount('lotes')
            ->when($busca, fn ($query) => $query->where(fn ($q) => $q
                ->where('codigo', 'like', "%{$busca}%")
                ->orWhere('unidade_nome', 'like', "%{$busca}%")
                ->orWhere('cidade', 'like', "%{$busca}%")))
            ->comLances($lances)
            ->when(
                $ordem === 'abertura_asc' || $ordem === 'abertura_desc',
                fn ($q) => $q->orderBy('data_abertura_lances', $ordem === 'abertura_asc' ? 'asc' : 'desc'),
                fn ($q) => $q->proximosPrimeiro(),
            )
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('editais.index', ['editais' => $editais, 'busca' => $busca, 'ordem' => $ordem, 'lances' => $lances]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate(['edital' => ['required', 'string', 'max:500']]);

        try {
            $ref = EditalRef::parse($dados['edital']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['edital' => $e->getMessage()])->withInput();
        }

        ImportarEdital::dispatch($ref);

        return back()->with('status', "Importação do edital {$ref} enviada para a fila. Atualize a página em alguns instantes.");
    }

    public function show(Request $request, Edital $edital): View
    {
        $tipo = $request->string('tipo')->value();
        $deposito = $request->string('deposito')->value();
        $busca = $request->string('q')->trim()->value();
        $ordem = $request->string('ordem')->value();

        $lotes = $edital->lotes()
            ->with(['imagens' => fn ($q) => $q->orderBy('id')])
            ->withCount('itens')
            // Um lote pode ter várias categorias: "ELETRÔNICO/ÁUDIO/VÍDEO, INFORMÁTICA".
            ->when($tipo, fn ($q) => $q->where('tipo', 'like', "%{$tipo}%"))
            ->when($deposito, fn ($q) => $q->whereHas('itens', fn ($itens) => $itens->where('recinto_armazenador', $deposito)))
            ->when($busca, fn ($q) => $q->whereHas('itens', fn ($itens) => $itens->where('descricao', 'like', "%{$busca}%")))
            ->when($ordem === 'menor_valor', fn ($q) => $q->orderBy('valor_minimo'))
            ->when($ordem === 'maior_valor', fn ($q) => $q->orderByDesc('valor_minimo'))
            // Arremates suspeitos vão para o fim, senão dominam a ordenação.
            ->when($ordem === 'maior_arremate', fn ($q) => $q->orderBy('arremate_suspeito')->orderByDesc('valor_arremate'))
            ->orderBy('numero')
            ->paginate(50)
            ->withQueryString();

        $tipos = $edital->lotes()->whereNotNull('tipo')->distinct()->pluck('tipo')
            ->flatMap(fn (string $t) => array_map('trim', explode(',', $t)))
            ->filter()
            ->unique()
            ->sortBy(fn (string $t) => Str::ascii($t))
            ->values();

        $depositos = LoteItem::query()
            ->whereIn('lote_id', $edital->lotes()->select('id'))
            ->whereNotNull('recinto_armazenador')
            ->distinct()
            ->pluck('recinto_armazenador')
            ->sortBy(fn (string $d) => Str::ascii($d))
            ->values();

        $depositosPorLote = LoteItem::depositosPorLote($lotes->pluck('id'));

        $arremate = $edital->resultado_importado_em ? $edital->resumoArremate() : null;

        return view('editais.show', compact(
            'edital', 'lotes', 'tipos', 'tipo', 'depositos', 'deposito', 'depositosPorLote', 'busca', 'ordem', 'arremate',
        ));
    }
}
