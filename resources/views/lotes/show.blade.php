@extends('layouts.app')

@use('App\Support\Formato')

@section('title', 'Lote '.$lote->numero.' · Edital '.$edital->ref())

@section('content')
    <p><a href="{{ route('editais.show', $edital) }}">&larr; Edital {{ $edital->codigo ?? $edital->ref() }}</a></p>

    <div class="cabecalho">
        <div>
            <h1>Lote {{ $lote->numero }}</h1>
            <div class="muted">{{ $lote->tipo }}</div>
        </div>
        <div class="acoes">
            @include('partials.favorito')
            <a class="botao secundario" href="{{ $edital->ref()->urlPortal() }}/lote/{{ $lote->numero }}" target="_blank" rel="noopener">Ver no portal</a>
        </div>
    </div>

    <div class="card">
        <dl class="grade">
            <div><dt>Situação</dt><dd><span class="badge">{{ $lote->situacao_descricao }}</span></dd></div>
            <div><dt>Valor mínimo</dt><dd>{{ Formato::moeda($lote->valor_minimo) }}</dd></div>
            <div><dt>Valor de avaliação</dt><dd>{{ Formato::moeda($lote->valor_avaliacao) }}</dd></div>
            @if ($lote->resultado)
                <div>
                    <dt>Resultado do leilão</dt>
                    <dd>
                        @if ($lote->arremate_suspeito)
                            Arrematado por {{ Formato::moeda($lote->valor_arremate) }}
                            <div class="aviso">
                                Lance suspeito: {{ number_format($lote->multiplo_do_arremate, 0, ',', '.') }} vezes o maior valor de referência
                                (mínimo ou avaliação). Não entra nos totais.
                            </div>
                        @elseif ($lote->valor_arremate !== null)
                            <strong>Arrematado por {{ Formato::moeda($lote->valor_arremate) }}</strong>
                            @if ($agio = Formato::variacao($lote->valor_minimo, $lote->valor_arremate))
                                <div class="muted">{{ $agio }} sobre o valor mínimo</div>
                            @endif
                        @else
                            {{ $lote->resultado->label() }}
                        @endif
                    </dd>
                </div>
            @endif
            <div><dt>Pessoa física pode participar</dt><dd>{{ $edital->permite_pf ? 'Sim' : 'Não' }}</dd></div>
            <div><dt>Detalhes importados em</dt><dd>{{ Formato::dataHora($lote->detalhes_importados_em) }}</dd></div>
        </dl>
    </div>

    @if ($lote->imagens->isNotEmpty())
        <h2>Fotos ({{ $lote->imagens->count() }})</h2>
        <div class="galeria" data-galeria="{{ json_encode($lote->fotosParaGaleria()) }}" data-titulo="Lote {{ $lote->numero }}">
            @foreach ($lote->imagens as $indice => $imagem)
                <a href="{{ $imagem->url }}" target="_blank" rel="noopener" data-indice="{{ $indice }}">
                    <img loading="lazy" referrerpolicy="no-referrer" alt="Foto do lote {{ $lote->numero }}"
                         src="{{ $imagem->url_miniatura ?? $imagem->url }}">
                </a>
            @endforeach
        </div>
    @endif

    <h2>Itens ({{ $lote->itens->count() }})</h2>
    <div class="tabela">
        <table>
            <thead>
                <tr>
                    <th class="num">#</th>
                    <th>Descrição</th>
                    <th class="num">Quantidade</th>
                    <th>Unidade</th>
                    <th>Recinto armazenador</th>
                    <th>Referência</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lote->itens as $item)
                    <tr>
                        <td class="num">{{ $item->ordem }}</td>
                        <td>{{ $item->descricao ?? '-' }}</td>
                        <td class="num">{{ Formato::quantidade($item->quantidade) }}</td>
                        <td>{{ $item->unidade_medida ?? '-' }}</td>
                        <td>{{ $item->recinto_armazenador ?? '-' }}</td>
                        <td>{{ $item->nr_referencia ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="vazio">
                            @if ($lote->detalhes_importados_em)
                                Este lote não tem itens.
                            @else
                                Os itens deste lote ainda não foram importados. Use "Reimportar" na página do edital.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h2>Histórico de situação e preço</h2>
    <p class="muted">Um registro por mudança detectada nas importações. Em destaque, o que mudou em relação ao registro anterior.</p>
    <div class="tabela">
        <table>
            <thead>
                <tr>
                    <th>Desde</th>
                    <th>Situação</th>
                    <th class="num">Valor mínimo</th>
                    <th class="num">Valor de avaliação</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lote->historico as $indice => $registro)
                    @php($anterior = $lote->historico[$indice + 1] ?? null)
                    <tr>
                        <td>{{ Formato::dataHora($registro->registrado_em) }}</td>
                        <td><span @class(['badge', 'mudou' => $anterior && $anterior->situacao !== $registro->situacao])>{{ $registro->situacao_descricao }}</span></td>
                        <td @class(['num', 'mudou' => $anterior && $anterior->valor_minimo !== $registro->valor_minimo])>{{ Formato::moeda($registro->valor_minimo) }}</td>
                        <td @class(['num', 'mudou' => $anterior && $anterior->valor_avaliacao !== $registro->valor_avaliacao])>{{ Formato::moeda($registro->valor_avaliacao) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="vazio">Sem histórico registrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
