<?php

namespace App\Http\Controllers;

use App\Jobs\ImportarEdital;
use App\Models\Edital;
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

        $editais = Edital::query()
            ->withCount('lotes')
            ->when($busca, fn ($query) => $query->where(fn ($q) => $q
                ->where('codigo', 'like', "%{$busca}%")
                ->orWhere('unidade_nome', 'like', "%{$busca}%")
                ->orWhere('cidade', 'like', "%{$busca}%")))
            ->orderByDesc('data_abertura_lances')
            ->paginate(25)
            ->withQueryString();

        return view('editais.index', ['editais' => $editais, 'busca' => $busca]);
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
        $busca = $request->string('q')->trim()->value();
        $ordem = $request->string('ordem')->value();

        $lotes = $edital->lotes()
            ->with('primeiraImagem')
            ->withCount('itens')
            // Um lote pode ter várias categorias: "ELETRÔNICO/ÁUDIO/VÍDEO, INFORMÁTICA".
            ->when($tipo, fn ($q) => $q->where('tipo', 'like', "%{$tipo}%"))
            ->when($busca, fn ($q) => $q->whereHas('itens', fn ($itens) => $itens->where('descricao', 'like', "%{$busca}%")))
            ->when($ordem === 'menor_valor', fn ($q) => $q->orderBy('valor_minimo'))
            ->when($ordem === 'maior_valor', fn ($q) => $q->orderByDesc('valor_minimo'))
            ->orderBy('numero')
            ->paginate(50)
            ->withQueryString();

        $tipos = $edital->lotes()->whereNotNull('tipo')->distinct()->pluck('tipo')
            ->flatMap(fn (string $t) => array_map('trim', explode(',', $t)))
            ->filter()
            ->unique()
            ->sortBy(fn (string $t) => Str::ascii($t))
            ->values();

        return view('editais.show', compact('edital', 'lotes', 'tipos', 'tipo', 'busca', 'ordem'));
    }
}
