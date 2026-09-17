@php
    $id = is_array($contacto) ? ($contacto['id'] ?? 0) : ($contacto->id ?? 0);
    $nombre = is_array($contacto) ? trim($contacto['nombre'] ?? '') : trim($contacto->nombre ?? '');
    $email = is_array($contacto) ? trim($contacto['email'] ?? '') : trim($contacto->email ?? '');
    $telefono = is_array($contacto) ? trim($contacto['telefono'] ?? '') : trim($contacto->telefono ?? '');
    $mostrarCheck = ! empty($permiteSeleccionEnvio) || ! empty($requiereSeleccionEmail);
    $preseleccionado = ! empty($emailsPreseleccionados)
        && in_array(\App\Support\CotizacionContactosFacturacion::normalizarEmail($email), $emailsPreseleccionados, true);
@endphp
<li class="contacto-envio-item @if(! ($loop->last ?? true)) border-bottom @endif"
    data-contacto-id="{{ $id }}"
    data-nombre="{{ $nombre }}"
    data-email="{{ $email }}"
    data-telefono="{{ $telefono }}">
    <div class="contacto-envio-row">
        @if($mostrarCheck)
            <input type="checkbox"
                   class="form-check-input email-envio-factura-check mt-0"
                   name="emails_envio_factura[]"
                   value="{{ $email }}"
                   form="facturarForm"
                   @checked($preseleccionado)>
        @endif
        <div class="flex-grow-1 min-w-0">
            @if($nombre !== '')
                <span class="fw-semibold">{{ $nombre }}</span>
                <span class="text-muted">·</span>
            @endif
            <span>{{ $email }}</span>
            @if($telefono !== '')
                <small class="text-muted ms-1">{{ $telefono }}</small>
            @endif
        </div>
        <div class="contacto-envio-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary btn-editar-contacto-envio" title="Editar" aria-label="Editar">
                <x-heroicon-o-pencil style="width: 14px; height: 14px;" />
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-contacto-envio" title="Eliminar" aria-label="Eliminar">
                <x-heroicon-o-trash style="width: 14px; height: 14px;" />
            </button>
        </div>
    </div>
</li>
