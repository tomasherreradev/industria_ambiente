@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h1 class="h3 mb-1">Resumen del informe (Editable)</h1>
                <p class="text-muted small mb-0">
                    Cotización #{{ $muestra->cotio_numcoti }} — Muestra {{ $muestra->cotio_descripcion }}
                    (#{{ $muestra->instance_number }})
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('informes.pdf', [
        'cotio_numcoti' => $muestra->cotio_numcoti,
        'cotio_item' => $muestra->cotio_item,
        'instance_number' => $muestra->instance_number,
    ]) }}" class="btn btn-outline-success btn-sm" target="_blank" rel="noopener">
                    <x-heroicon-o-document-text style="width: 16px; height: 16px;" class="me-1" />
                    Ver PDF
                </a>
                <a href="{{ route('informes.index', ['view' => 'lista']) }}" class="btn btn-outline-secondary btn-sm">Volver
                    a informes</a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form method="post" action="{{ route('informes.protocolo-pdf.update', [
        'cotio_numcoti' => $muestra->cotio_numcoti,
        'cotio_item' => $muestra->cotio_item,
        'instance_number' => $muestra->instance_number,
    ]) }}">
            @csrf
            @method('PUT')

            {{-- SECCIÓN 1: DATOS DE CABECERA --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">1. Datos del Protocolo (Cabecera)</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="fecha_emision">Fecha de emisión</label>
                            <input type="text" class="form-control" id="fecha_emision" name="fecha_emision"
                                value="{{ old('fecha_emision', $cab['fecha_emision']) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="otn">O.T. N°</label>
                            <input type="text" class="form-control" id="otn" name="otn"
                                value="{{ old('otn', $cab['otn']) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="protocolo_opds">Protocolo OPDS</label>
                            <input type="text" class="form-control" id="protocolo_opds" name="protocolo_opds"
                                value="{{ old('protocolo_opds', $cab['protocolo_opds']) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="razon_social">Razón social</label>
                            <input type="text" class="form-control" id="razon_social" name="razon_social"
                                value="{{ old('razon_social', $cab['razon_social']) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="direccion">Dirección</label>
                            <input type="text" class="form-control" id="direccion" name="direccion"
                                value="{{ old('direccion', $cab['direccion']) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="fecha_extraccion">Fecha de extracción</label>
                            <input type="text" class="form-control" id="fecha_extraccion" name="fecha_extraccion"
                                value="{{ old('fecha_extraccion', $cab['fecha_extraccion']) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="fecha_recepcion">Fecha de recepción</label>
                            <input type="text" class="form-control" id="fecha_recepcion" name="fecha_recepcion"
                                value="{{ old('fecha_recepcion', $cab['fecha_recepcion']) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="datos_muestra">Datos de la muestra</label>
                            <input type="text" class="form-control" id="datos_muestra" name="datos_muestra"
                                value="{{ old('datos_muestra', $cab['datos_muestra']) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="sitio_extraccion">Sitio de extracción</label>
                            <input type="text" class="form-control" id="sitio_extraccion" name="sitio_extraccion"
                                value="{{ old('sitio_extraccion', $cab['sitio_extraccion']) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="precinto">Precinto N°</label>
                            <input type="text" class="form-control" id="precinto" name="precinto"
                                value="{{ old('precinto', $cab['precinto']) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold" for="cadena_custodia">Cadena de Custodia N°</label>
                            <input type="text" class="form-control" id="cadena_custodia" name="cadena_custodia"
                                value="{{ old('cadena_custodia', $cab['cadena_custodia']) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold" for="identificacion_muestra">Identificación de muestra</label>
                            <input type="text" class="form-control" id="identificacion_muestra"
                                name="identificacion_muestra"
                                value="{{ old('identificacion_muestra', $cab['identificacion_muestra']) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="legislacion_normativa">Legislación / normativa
                                (categoría)</label>
                            <textarea class="form-control" id="legislacion_normativa" name="legislacion_normativa"
                                rows="2">{{ old('legislacion_normativa', $cab['legislacion_normativa']) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="muestra_extraida_por">Muestra extraída por</label>
                            <textarea class="form-control" id="muestra_extraida_por" name="muestra_extraida_por"
                                rows="2">{{ old('muestra_extraida_por', $cab['muestra_extraida_por']) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SECCIÓN 2: RESULTADOS DE ANÁLISIS --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">2. Resultados del Análisis</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 25%;">Determinación</th>
                                    <th style="width: 20%;">Metodología</th>
                                    <th style="width: 15%;">Resultado Final</th>
                                    <th style="width: 40%;">Observaciones del Resultado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($analisis as $index => $it)
                                    @php
                                        $metDesc = trim((string) ($it->metodoAnalisis->metodo_descripcion ?? $it->cotio_codigometodo_analisis ?? $it->cotio_codigometodo ?? '—'));
                                        $leyEnsayo = \App\Support\LeyNormativaPresentacion::textoPlano($it->tarea ?? null);
                                        // Misma lógica que en pdf.blade.php: resultado_final ?? resultado
                                        $valorResultado = $it->resultado_final ?? $it->resultado;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-bold">{{ $it->cotio_descripcion ?? '—' }}</div>
                                            <small class="text-muted">{{ $leyEnsayo }}</small>
                                            <input type="hidden" name="analisis[{{ $index }}][id]" value="{{ $it->id }}">
                                        </td>
                                        <td>
                                            <span class="small text-muted">{{ $metDesc }}</span>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm"
                                                name="analisis[{{ $index }}][resultado_final]"
                                                value="{{ old("analisis.$index.resultado_final", $valorResultado) }}">
                                            @if(!$it->resultado_final && $it->resultado)
                                                <small class="text-muted" style="font-size: 0.7rem;">Valor actual (base):
                                                    {{ $it->resultado }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <textarea class="form-control form-control-sm"
                                                name="analisis[{{ $index }}][observacion_resultado]"
                                                rows="1">{{ old("analisis.$index.observacion_resultado", $it->observacion_resultado) }}</textarea>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No hay análisis registrados para
                                            esta muestra.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- SECCIÓN 3: NOTAS DEL INFORME --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                </div>
                <div class="card-body">
                    <div class="col-12">
                        <label class="form-label fw-bold" for="notas_informe">Notas Adicionales (se agregarán a las notas
                            predeterminadas del catálogo)</label>
                        <textarea class="form-control" id="notas_informe" name="notas_informe" rows="3"
                            placeholder="Añada aquí notas técnicas adicionales. Estas se mostrarán después de las notas predeterminadas que ya tienen los componentes...">{{ old('notas_informe', $cab['notas_informe'] ?? '') }}</textarea>
                        <small class="text-muted">Las notas que escribas aquí aparecerán en el informe justo debajo de las
                            notas automáticas de cada determinación.</small>
                    </div>
                </div>
            </div>

            {{-- SECCIÓN 4: OBSERVACIONES --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">4. Observaciones del Informe</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold" for="observaciones_ot">Observaciones Generales</label>
                            <textarea class="form-control" id="observaciones_ot" name="observaciones_ot"
                                rows="4">{{ old('observaciones_ot', $muestra->observaciones_ot) }}</textarea>
                            <small class="text-muted">Estas observaciones se guardan en el registro base de la
                                muestra.</small>
                        </div>
                        <div class="col-12 mt-4">
                            <label class="form-label fw-bold text-primary" for="observaciones_adicionales">Observaciones
                                adicionales (Solo para este PDF)</label>
                            <textarea class="form-control border-primary" id="observaciones_adicionales"
                                name="observaciones_adicionales" rows="3"
                                placeholder="Añada aquí observaciones que solo deban aparecer en este protocolo...">{{ old('observaciones_adicionales', $cab['observaciones_adicionales'] ?? '') }}</textarea>
                            <small class="text-primary">Estas observaciones no modifican los registros base, se guardan solo
                                para la generación del PDF.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sticky-bottom bg-white border-top py-3 mt-4" style="z-index: 1020;">
                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary px-4">
                        <x-heroicon-o-check style="width: 20px; height: 20px;" class="me-1" />
                        Guardar Cambios
                    </button>
                </div>
            </div>
        </form>

        <div class="mt-5 pt-4 border-top">
            <h6 class="text-danger mb-3">Zona de peligro</h6>
            <div class="card border-danger shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Restaurar valores del sistema</h6>
                        <p class="small text-muted mb-0">Elimina las personalizaciones de la cabecera y observaciones
                            adicionales del PDF. Los resultados de análisis y observaciones generales NO se verán afectados.
                        </p>
                    </div>
                    <form method="post" action="{{ route('informes.protocolo-pdf.restaurar', [
        'cotio_numcoti' => $muestra->cotio_numcoti,
        'cotio_item' => $muestra->cotio_item,
        'instance_number' => $muestra->instance_number,
    ]) }}"
                        onsubmit="return confirm('¿Eliminar todos los textos personalizados del protocolo y volver a los datos del sistema?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">Restaurar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .form-label {
            margin-bottom: 0.25rem;
        }

        .table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.025em;
        }

        .sticky-bottom {
            box-shadow: 0 -5px 10px rgba(0, 0, 0, 0.05);
        }
    </style>
@endpush