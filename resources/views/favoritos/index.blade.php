@extends('layouts.app')

@use('App\Support\Formato')

@section('title', 'Favoritos')

@section('content')
    <div class="cabecalho">
        <div>
            <h1>Lotes favoritos</h1>
            <div class="muted">Marque um lote com ☆ na lista de lotes do edital ou na página do lote. Os leilões mais próximos aparecem primeiro.</div>
        </div>
        <form method="GET" action="{{ route('favoritos.index') }}" class="linha">
            <select name="lances" aria-label="Situação dos lances">
                <option value="">Lances abertos e fechados</option>
                <option value="abertos" @selected($lances === 'abertos')>Abertos para lances</option>
                <option value="fechados" @selected($lances === 'fechados')>Fechados para lances</option>
            </select>
            <button type="submit" class="secundario">Filtrar</button>
        </form>
    </div>

    <div class="tabela">
        <table>
            <thead>
                <tr>
                    <th><span class="sr-only">Favorito</span></th>
                    <th></th>
                    <th>Lote</th>
                    <th>Abertura dos lances</th>
                    <th>Tipo</th>
                    <th>Depósito</th>
                    <th>Situação</th>
                    <th class="num">Valor mínimo</th>
                    <th class="num">Valor de avaliação</th>
                    <th class="num">Arremate</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lotes as $lote)
                    @php($edital = $lote->edital)
                    <tr>
                        <td>@include('partials.favorito')</td>
                        <td>
                            @if ($capa = $lote->imagens->first())
                                <a class="foto" href="{{ route('lotes.show', [$edital, $lote]) }}"
                                   data-galeria="{{ json_encode($lote->fotosParaGaleria()) }}" data-titulo="Lote {{ $lote->numero }}">
                                    <img class="thumb" loading="lazy" referrerpolicy="no-referrer" alt="Fotos do lote {{ $lote->numero }}"
                                         src="{{ $capa->url_miniatura ?? $capa->url }}">
                                    @if ($lote->imagens->count() > 1)
                                        <span class="qtd">{{ $lote->imagens->count() }}</span>
                                    @endif
                                </a>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('lotes.show', [$edital, $lote]) }}" style="white-space: nowrap">Lote {{ $lote->numero }}</a>
                            <div class="muted">
                                <a href="{{ route('editais.show', $edital) }}">{{ $edital->codigo ?? $edital->ref() }}</a>{{ $edital->cidade ? ' · '.$edital->cidade : '' }}
                            </div>
                        </td>
                        <td style="white-space: nowrap">
                            {{ Formato::dataHora($edital->data_abertura_lances) }}
                            <div class="muted">{{ $edital->situacao_descricao }}</div>
                        </td>
                        <td>{{ $lote->tipo ?? '-' }}</td>
                        <td>@include('partials.deposito', ['depositosDoLote' => $depositosPorLote->get($lote->id, collect())])</td>
                        <td><span class="badge">{{ $lote->situacao_descricao }}</span></td>
                        <td class="num">{{ Formato::moeda($lote->valor_minimo) }}</td>
                        <td class="num">{{ Formato::moeda($lote->valor_avaliacao) }}</td>
                        <td class="num">@include('partials.arremate')</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="vazio">
                            @if ($lances)
                                Nenhum lote favorito com os lances {{ $lances === 'abertos' ? 'abertos' : 'fechados' }}.
                            @else
                                Nenhum lote favorito ainda. Use a estrela ☆ ao lado de um lote para adicioná-lo aqui.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $lotes->links() }}
@endsection
