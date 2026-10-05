@extends('layouts.app')

@use('App\Support\Formato')

@section('title', 'Mudanças recentes')

@section('content')
    <div class="cabecalho">
        <div>
            <h1>Mudanças recentes</h1>
            <div class="muted">
                {{ sprintf('%d mudança(s) no período: %d de situação, %d de valor mínimo, %d de valor de avaliação.',
                    $resumo->total, $resumo->situacao, $resumo->valor_minimo, $resumo->valor_avaliacao) }}
            </div>
        </div>
        <form method="GET" action="{{ route('mudancas.index') }}" class="linha">
            <select name="periodo" aria-label="Período">
                <option value="24h" @selected($periodo === '24h')>Últimas 24 horas</option>
                <option value="7d" @selected($periodo === '7d')>Últimos 7 dias</option>
                <option value="30d" @selected($periodo === '30d')>Últimos 30 dias</option>
                <option value="tudo" @selected($periodo === 'tudo')>Todo o período</option>
            </select>
            <select name="mudanca" aria-label="Tipo de mudança">
                <option value="">Qualquer mudança</option>
                <option value="situacao" @selected($campo === 'situacao')>Situação</option>
                <option value="valor_minimo" @selected($campo === 'valor_minimo')>Valor mínimo</option>
                <option value="valor_avaliacao" @selected($campo === 'valor_avaliacao')>Valor de avaliação</option>
            </select>
            <button type="submit" class="secundario">Filtrar</button>
        </form>
    </div>

    <div class="tabela">
        <table>
            <thead>
                <tr>
                    <th>Quando</th>
                    <th>Lote</th>
                    <th>Situação</th>
                    <th class="num">Valor mínimo</th>
                    <th class="num">Valor de avaliação</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($mudancas as $mudanca)
                    @php($lote = $mudanca->lote)
                    <tr>
                        <td style="white-space: nowrap">{{ Formato::dataHora($mudanca->registrado_em) }}</td>
                        <td>
                            <a href="{{ route('lotes.show', [$lote->edital, $lote]) }}">Lote {{ $lote->numero }}</a>
                            <div class="muted">
                                {{ $lote->edital->codigo ?? $lote->edital->ref() }}{{ $lote->edital->cidade ? ' · '.$lote->edital->cidade : '' }}
                            </div>
                            @if ($lote->tipo)
                                <div class="muted">{{ $lote->tipo }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($mudanca->mudou('situacao'))
                                <span class="badge antes">{{ $mudanca->situacao_anterior_descricao }}</span>
                                &rarr;
                                <span class="badge mudou">{{ $mudanca->situacao_descricao }}</span>
                            @else
                                <span class="badge">{{ $mudanca->situacao_descricao }}</span>
                            @endif
                        </td>
                        @foreach (['valor_minimo', 'valor_avaliacao'] as $valor)
                            <td class="num">
                                @if ($mudanca->mudou($valor))
                                    <s class="muted">{{ Formato::moeda($mudanca->{$valor.'_anterior'}) }}</s>
                                    <div class="mudou">{{ Formato::moeda($mudanca->{$valor}) }}</div>
                                    @if ($variacao = Formato::variacao($mudanca->{$valor.'_anterior'}, $mudanca->{$valor}))
                                        <div class="muted">{{ $variacao }}</div>
                                    @endif
                                @else
                                    <span class="muted">{{ Formato::moeda($mudanca->{$valor}) }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="vazio">
                            Nenhuma mudança no período. Uma mudança aparece aqui quando uma importação encontra
                            situação ou valores diferentes dos registrados (a sincronização roda todo dia às
                            {{ config('sle.sincronizacao_horario') }}).
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $mudancas->links() }}
@endsection
