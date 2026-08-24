@php
    $notasItem = collect($notas ?? [])->filter(function ($n) {
        return is_array($n) && trim((string) ($n['contenido'] ?? '')) !== '';
    })->values();
@endphp
@if($notasItem->isNotEmpty())
    <div class="item-notas-imprimibles {{ $wrapperClass ?? '' }}">
        @foreach($notasItem as $n)
            <p class="item-nota-imprimible-line">{{ $n['contenido'] ?? '' }}</p>
        @endforeach
    </div>
@endif
