@php
    $suffix = $suffix ?? 'default';
    $layout = $layout ?? 'panel';
    $filasRefs = $refsFacturacion['filas'] ?? [];
    $modalId = 'modalEditarRefs-' . $suffix;
    $swalAlGuardar = (bool) ($swalAlGuardar ?? false);
@endphp

@if ($layout === 'panel')
    <div class="ucrud-panel mb-4">
        <div class="fact-panel-head">
            <div>
                <h2 class="ucrud-panel-title">Referencias para facturación</h2>
                <small class="text-muted">Mismos datos que se imprimen en la factura</small>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                <x-heroicon-o-pencil-square style="width: 14px; height: 14px;" class="me-1" />
                Editar referencias
            </button>
        </div>
        <div class="fact-panel-body">
            @include('facturacion.partials.referencias-facturacion-resumen', ['refsFacturacion' => $refsFacturacion])
        </div>
    </div>
@else
    <div class="fact-coti-card__refs border-top px-3 py-2 bg-white">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div class="flex-grow-1 min-w-0">
                @if (empty($refsFacturacion['puede_facturar']) && ! empty($refsFacturacion['mensaje_bloqueo']))
                    <div class="alert alert-warning py-2 px-3 small mb-2">
                        {{ $refsFacturacion['mensaje_bloqueo'] }}
                    </div>
                @endif
                @include('facturacion.partials.referencias-facturacion-inline', ['refsFacturacion' => $refsFacturacion])
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
                <x-heroicon-o-pencil-square style="width: 14px; height: 14px;" class="me-1" />
                Referencias
            </button>
        </div>
    </div>
@endif

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true" data-refs-suffix="{{ $suffix }}">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $modalId }}Label">Referencias de facturación · Cotización #{{ $cotiNum }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Orden de compra, remito y demás datos que se imprimen en la factura. Cárguelos aquí antes de aprobar para facturación.</p>
                <form class="js-form-editar-refs" data-update-url="{{ $updateUrl }}" data-swal-guardar="{{ $swalAlGuardar ? '1' : '0' }}" data-coti-num="{{ $cotiNum }}">
                    <div class="mb-3">
                        <label for="modal_coti_oc_referencia_{{ $suffix }}" class="form-label">Orden de Compra (O.C.)</label>
                        <input type="text" class="form-control js-ref-oc" id="modal_coti_oc_referencia_{{ $suffix }}" name="coti_oc_referencia" value="{{ $refsFacturacion['oc'] ?? '' }}">
                        @if (! empty($refsFacturacion['oc_obligatorio']))
                            <div class="form-text text-danger">Esta referencia es obligatoria para facturar.</div>
                        @endif
                    </div>

                    @if (count($filasRefs) > 0)
                        <hr class="my-4">
                        <p class="small fw-semibold mb-2">Referencias de la cotización (máx. 4)</p>
                    @endif
                    <div class="js-modal-refs-container">
                        @forelse ($filasRefs as $fila)
                            <div class="row g-2 mb-2 ref-row">
                                <div class="col-md-4">
                                    <label class="small text-muted">Tipo</label>
                                    <input type="text" class="form-control form-control-sm ref-tipo" value="{{ $fila['tipo'] ?? '' }}" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="small text-muted">Valor</label>
                                    <input type="text" class="form-control form-control-sm ref-valor" value="{{ $fila['valor'] ?? '' }}">
                                    @if (! empty($fila['obligatorio_factura']))
                                        <div class="form-text text-danger mt-0 js-ref-obligatoria" style="font-size: 0.7rem;">Obligatoria</div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-muted small mb-0">Esta cotización no tiene filas de referencia (REMITO, HES, HAS, GR, OTRO). Solo aplica la O.C. de arriba, o edite la cotización para agregar referencias.</p>
                        @endforelse
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary js-guardar-refs-facturacion">Guardar cambios</button>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('click', async function (event) {
                const btn = event.target.closest('.js-guardar-refs-facturacion');
                if (!btn || btn.disabled) {
                    return;
                }

                const modal = btn.closest('.modal');
                const form = modal?.querySelector('.js-form-editar-refs');
                if (!form) {
                    return;
                }

                const usarSwal = form.dataset.swalGuardar === '1';

                if (usarSwal && typeof Swal !== 'undefined') {
                    const numCoti = form.dataset.cotiNum || '';
                    const confirmacion = await Swal.fire({
                        title: 'Guardar referencias',
                        html: '¿Guardar la orden de compra y las referencias de la cotización <strong>#' + numCoti + '</strong>?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, guardar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#0d6efd',
                        reverseButtons: true,
                        focusCancel: true,
                    });
                    if (!confirmacion.isConfirmed) {
                        return;
                    }
                }

                const ocInput = form.querySelector('.js-ref-oc');
                const oc = ocInput ? ocInput.value : '';
                const rows = [];
                form.querySelectorAll('.ref-row').forEach(function (row) {
                    rows.push({
                        tipo: row.querySelector('.ref-tipo')?.value ?? '',
                        valor: row.querySelector('.ref-valor')?.value ?? '',
                        obligatorio_factura: row.querySelector('.js-ref-obligatoria') !== null,
                    });
                });

                const originalHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = 'Guardando…';

                try {
                    const response = await fetch(form.dataset.updateUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            coti_oc_referencia: oc,
                            coti_refs_facturacion_json: JSON.stringify(rows),
                        }),
                    });

                    const data = await response.json().catch(function () {
                        return {};
                    });

                    if (response.ok && data.success) {
                        if (usarSwal && typeof Swal !== 'undefined') {
                            const modalInstance = bootstrap.Modal.getInstance(modal);
                            if (modalInstance) {
                                modalInstance.hide();
                            }
                            await Swal.fire({
                                title: 'Referencias guardadas',
                                text: data.message || 'Los datos se actualizaron correctamente.',
                                icon: 'success',
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: '#198754',
                            });
                            window.location.reload();
                            return;
                        }
                        window.location.reload();
                        return;
                    }

                    const mensajeError = data.message || 'No se pudieron guardar las referencias.';
                    if (usarSwal && typeof Swal !== 'undefined') {
                        await Swal.fire({
                            title: 'No se pudo guardar',
                            text: mensajeError,
                            icon: 'error',
                            confirmButtonText: 'Cerrar',
                        });
                    } else {
                        alert('Error: ' + mensajeError);
                    }
                } catch (error) {
                    if (usarSwal && typeof Swal !== 'undefined') {
                        await Swal.fire({
                            title: 'Error de conexión',
                            text: 'No se pudo contactar al servidor. Intente de nuevo.',
                            icon: 'error',
                            confirmButtonText: 'Cerrar',
                        });
                    } else {
                        alert('Error al conectar con el servidor');
                    }
                } finally {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            });
        </script>
    @endpush
@endonce
