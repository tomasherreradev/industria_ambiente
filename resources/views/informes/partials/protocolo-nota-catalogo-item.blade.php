@php
    $tituloNota = trim((string) ($nota->titulo ?? ''));
    $contenidoNota = trim((string) ($nota->contenido ?? ''));
    $etiqueta = $tituloNota !== '' ? $tituloNota : \Illuminate\Support\Str::limit($contenidoNota, 80);
@endphp
<li class="list-group-item" data-nota-id="{{ $nota->id }}">
    <div class="d-flex gap-2 align-items-start">
        <div class="flex-grow-1 min-w-0">
            <div class="fw-semibold protocolo-nota-item-texto">{{ $etiqueta }}</div>
            @if($contenidoNota !== '')
                <div class="small text-muted protocolo-nota-item-texto">{{ $contenidoNota }}</div>
            @endif
            <input type="hidden" name="notas_catalogo_ids[]" value="{{ $nota->id }}">
        </div>
        <div class="btn-group btn-group-sm flex-shrink-0">
            <button type="button" class="btn btn-outline-secondary" data-action="subir" title="Subir">↑</button>
            <button type="button" class="btn btn-outline-secondary" data-action="bajar" title="Bajar">↓</button>
            <button type="button" class="btn btn-outline-danger" data-action="quitar" title="Quitar">×</button>
        </div>
    </div>
</li>
