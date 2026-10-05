@extends('layouts.app')

@use('App\Support\Formato')

@section('title', 'Editais')

@section('content')
    <div class="card">
        <h1>Importar edital</h1>
        <p class="muted">Cole a URL do edital no portal do SLE ou o número no formato unidade/número/ano.</p>

        <form method="POST" action="{{ route('editais.store') }}" class="linha">
            @csrf
            <input type="text" name="edital" class="largo" value="{{ old('edital') }}" required
                   placeholder="https://www25.receita.fazenda.gov.br/sle-sociedade/portal/edital/700100/12/2026">
            <button type="submit">Importar</button>
        </form>

        @error('edital')
            <div class="alerta erro" style="margin: 12px 0 0">{{ $message }}</div>
        @enderror
    </div>

    <div class="cabecalho">
        <h2 style="margin: 0">Editais importados</h2>
        <form method="GET" action="{{ route('editais.index') }}" class="linha">
            <input type="search" name="q" value="{{ $busca }}" placeholder="Buscar por edital, unidade ou cidade">
            <button type="submit" class="secundario">Buscar</button>
        </form>
    </div>

    <div class="tabela">
        <table>
            <thead>
                <tr>
                    <th>Edital</th>
                    <th>Unidade</th>
                    <th>Situação</th>
                    <th>Propostas até</th>
                    <th>Abertura dos lances</th>
                    <th class="num">Lotes</th>
                    <th>Importado em</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($editais as $edital)
                    <tr>
                        <td><a href="{{ route('editais.show', $edital) }}">{{ $edital->codigo ?? $edital->ref() }}</a></td>
                        <td>
                            {{ $edital->unidade_nome ?? '-' }}
                            @if ($edital->cidade)
                                <div class="muted">{{ $edital->cidade }}</div>
                            @endif
                        </td>
                        <td><span class="badge">{{ $edital->situacao_descricao }}</span></td>
                        <td>{{ Formato::dataHora($edital->data_fim_propostas) }}</td>
                        <td>{{ Formato::dataHora($edital->data_abertura_lances) }}</td>
                        <td class="num">{{ $edital->lotes_count }}</td>
                        <td>{{ Formato::dataHora($edital->importado_em) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="vazio">
                            Nenhum edital importado ainda. Use o formulário acima ou rode
                            <code>php artisan leilao:importar 700100/12/2026</code>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $editais->links() }}
@endsection
