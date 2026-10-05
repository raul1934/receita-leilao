{{-- Célula de arremate do $lote. --}}
@if ($lote->arremate_suspeito)
    <span class="muted">{{ \App\Support\Formato::moeda($lote->valor_arremate) }}</span>
    <div><span class="badge alerta" title="Lance acima de {{ \App\Support\Formato::quantidade(config('sle.arremate_suspeito_multiplo')) }} vezes o valor mínimo/de avaliação; fora dos totais">suspeito</span></div>
@elseif ($lote->valor_arremate !== null)
    <strong>{{ \App\Support\Formato::moeda($lote->valor_arremate) }}</strong>
@else
    <span class="muted">{{ $lote->resultado?->label() ?? '-' }}</span>
@endif
