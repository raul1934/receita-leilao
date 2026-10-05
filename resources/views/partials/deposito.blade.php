{{-- Depósito principal do lote e indicação dos demais. Espera $depositosDoLote (Collection de nomes). --}}
{{ $depositosDoLote->first() ?? '-' }}
@if ($depositosDoLote->count() > 1)
    <span class="badge" title="{{ $depositosDoLote->implode(', ') }}">+{{ $depositosDoLote->count() - 1 }}</span>
@endif
