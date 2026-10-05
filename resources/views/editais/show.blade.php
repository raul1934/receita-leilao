@extends('layouts.app')

@use('App\Support\Formato')

@section('title', 'Edital '.$edital->ref())

@section('content')
    <p><a href="{{ route('editais.index') }}">&larr; Editais</a></p>

    <div class="cabecalho">
        <div>
            <h1>Edital {{ $edital->codigo ?? $edital->ref() }}</h1>
            <div class="muted">{{ $edital->unidade_nome }}{{ $edital->cidade ? ' · '.$edital->cidade : '' }}</div>
        </div>
        <div class="acoes">
            <a class="botao secundario" href="{{ $edital->ref()->urlPortal() }}" target="_blank" rel="noopener">Ver no portal</a>
            <form method="POST" action="{{ route('editais.store') }}">
                @csrf
                <input type="hidden" name="edital" value="{{ $edital->ref() }}">
                <button type="submit">Reimportar</button>
            </form>
        </div>
    </div>

    <div class="card">
        <dl class="grade">
            <div><dt>Situação</dt><dd><span class="badge">{{ $edital->situacao_descricao }}</span></dd></div>
            <div><dt>Órgão</dt><dd>{{ $edital->orgao ?? '-' }}</dd></div>
            <div><dt>Recebimento de propostas</dt><dd>{{ Formato::dataHora($edital->data_inicio_propostas) }} a {{ Formato::dataHora($edital->data_fim_propostas) }}</dd></div>
            <div><dt>Classificação</dt><dd>{{ Formato::dataHora($edital->data_classificacao) }}</dd></div>
            <div><dt>Abertura dos lances</dt><dd>{{ Formato::dataHora($edital->data_abertura_lances) }}</dd></div>
            <div><dt>Pessoa física pode participar</dt><dd>{{ $edital->permite_pf ? 'Sim' : 'Não' }}</dd></div>
            <div><dt>Importado em</dt><dd>{{ Formato::dataHora($edital->importado_em) }}</dd></div>
            @if ($arremate)
                <div>
                    <dt>Total arrematado</dt>
                    <dd>
                        {{ Formato::moeda($arremate['total']) }} <span class="muted">({{ $arremate['lotes'] }} de {{ $arremate['de'] }} lotes)</span>
                        @if ($arremate['suspeitos'])
                            <div class="muted">{{ $arremate['suspeitos'] }} lance(s) suspeito(s) fora do total</div>
                        @endif
                    </dd>
                </div>
            @endif
        </dl>
        @if ($edital->forma_contato)
            <p><span class="muted">Contato:</span> {{ $edital->forma_contato }}</p>
        @endif
        @if ($edital->dados_publicacao)
            <p style="margin-bottom: 0"><span class="muted">Publicação:</span> {{ $edital->dados_publicacao }}</p>
        @endif
    </div>

    <div class="cabecalho">
        <h2 style="margin: 0">Lotes ({{ $lotes->total() }})</h2>
        <form method="GET" action="{{ route('editais.show', $edital) }}" class="linha">
            <select name="tipo" aria-label="Tipo de lote">
                <option value="">Todos os tipos</option>
                @foreach ($tipos as $opcao)
                    <option value="{{ $opcao }}" @selected($opcao === $tipo)>{{ $opcao }}</option>
                @endforeach
            </select>
            @if ($depositos->isNotEmpty())
                <select name="deposito" aria-label="Depósito">
                    <option value="">Todos os depósitos</option>
                    @foreach ($depositos as $opcao)
                        <option value="{{ $opcao }}" @selected($opcao === $deposito)>{{ $opcao }}</option>
                    @endforeach
                </select>
            @endif
            <input type="search" name="q" value="{{ $busca }}" placeholder="Buscar nos itens">
            <select name="ordem" aria-label="Ordenação">
                <option value="">Nº do lote</option>
                <option value="menor_valor" @selected($ordem === 'menor_valor')>Menor valor mínimo</option>
                <option value="maior_valor" @selected($ordem === 'maior_valor')>Maior valor mínimo</option>
                @if ($arremate)
                    <option value="maior_arremate" @selected($ordem === 'maior_arremate')>Maior arremate</option>
                @endif
            </select>
            <button type="submit" class="secundario">Filtrar</button>
        </form>
    </div>

    <div class="tabela">
        <table>
            <thead>
                <tr>
                    <th></th>
                    <th>Lote</th>
                    <th>Tipo</th>
                    <th>Depósito</th>
                    <th>Situação</th>
                    <th class="num">Valor mínimo</th>
                    <th class="num">Valor de avaliação</th>
                    @if ($arremate)
                        <th class="num">Arremate</th>
                    @endif
                    <th class="num">Itens</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lotes as $lote)
                    <tr>
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
                        <td style="white-space: nowrap"><a href="{{ route('lotes.show', [$edital, $lote]) }}">Lote {{ $lote->numero }}</a></td>
                        <td>{{ $lote->tipo ?? '-' }}</td>
                        <td>
                            @php($depositosDoLote = $depositosPorLote->get($lote->id, collect()))
                            {{ $depositosDoLote->first() ?? '-' }}
                            @if ($depositosDoLote->count() > 1)
                                <span class="badge" title="{{ $depositosDoLote->implode(', ') }}">+{{ $depositosDoLote->count() - 1 }}</span>
                            @endif
                        </td>
                        <td><span class="badge">{{ $lote->situacao_descricao }}</span></td>
                        <td class="num">{{ Formato::moeda($lote->valor_minimo) }}</td>
                        <td class="num">{{ Formato::moeda($lote->valor_avaliacao) }}</td>
                        @if ($arremate)
                            <td class="num">
                                @if ($lote->arremate_suspeito)
                                    <span class="muted">{{ Formato::moeda($lote->valor_arremate) }}</span>
                                    <div><span class="badge alerta" title="Lance acima de {{ Formato::quantidade(config('sle.arremate_suspeito_multiplo')) }} vezes o valor mínimo/de avaliação; fora dos totais">suspeito</span></div>
                                @elseif ($lote->valor_arremate !== null)
                                    <strong>{{ Formato::moeda($lote->valor_arremate) }}</strong>
                                @else
                                    <span class="muted">{{ $lote->resultado?->label() ?? '-' }}</span>
                                @endif
                            </td>
                        @endif
                        <td class="num">{{ $lote->detalhes_importados_em ? $lote->itens_count : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="vazio">Nenhum lote encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $lotes->links() }}
@endsection
