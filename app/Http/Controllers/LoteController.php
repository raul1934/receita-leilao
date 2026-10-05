<?php

namespace App\Http\Controllers;

use App\Models\Edital;
use App\Models\Lote;
use Illuminate\View\View;

class LoteController extends Controller
{
    public function show(Edital $edital, Lote $lote): View
    {
        $lote->load([
            'itens' => fn ($q) => $q->orderBy('ordem'),
            'imagens' => fn ($q) => $q->orderBy('id'),
            'historico' => fn ($q) => $q->orderByDesc('registrado_em')->orderByDesc('id'),
        ]);

        return view('lotes.show', compact('edital', 'lote'));
    }
}
