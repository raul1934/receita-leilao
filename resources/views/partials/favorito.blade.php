{{-- Estrela de favorito do $lote (do $edital). O script em partials/favorito-script alterna sem recarregar. --}}
<form method="POST" action="{{ route('favoritos.alternar', [$edital, $lote]) }}" class="favorito" data-favorito>
    @csrf
    <button type="submit" @class(['estrela', 'ativa' => $lote->favoritado_em])
            aria-pressed="{{ $lote->favoritado_em ? 'true' : 'false' }}"
            aria-label="Favoritar lote {{ $lote->numero }}"
            title="{{ $lote->favoritado_em ? 'Remover dos favoritos' : 'Adicionar aos favoritos' }}">{{ $lote->favoritado_em ? '★' : '☆' }}</button>
</form>
