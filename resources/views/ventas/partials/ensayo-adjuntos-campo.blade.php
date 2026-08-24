{{-- Campo de adjuntos para modales de ensayo en ventas --}}
@php
    $inputId = $inputId ?? 'ensayo_adjuntos_input';
    $listaId = $listaId ?? 'ensayoAdjuntosLista';
@endphp
<div class="row mb-3 ensayo-adjuntos-campo">
    <div class="col-md-12">
        <label for="{{ $inputId }}" class="form-label fw-semibold">Archivos adjuntos</label>
        <input type="file"
               class="form-control ensayo-adjuntos-input"
               id="{{ $inputId }}"
               accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,application/pdf,image/*"
               multiple>
        <small class="text-muted">PDF o imágenes (JPG, PNG, GIF, WEBP). Máximo 10 MB por archivo.</small>
        <ul class="list-group list-group-flush mt-2 ensayo-adjuntos-lista" id="{{ $listaId }}"></ul>
    </div>
</div>
