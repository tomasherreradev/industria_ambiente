@php
    $m = $resumen['muestra'] ?? [];
    $c = $resumen['cotizacion'] ?? [];
    $informe = $resumen['informe'] ?? [];
    $revision = $resumen['revision_facturacion'] ?? [];

    $dato = function ($valor, $placeholder = '—') {
        if ($valor === null || $valor === '') {
            return $placeholder;
        }
        return $valor;
    };

    $lista = function ($items) {
        if (empty($items)) {
            return '—';
        }
        return is_array($items) ? implode(', ', $items) : $items;
    };
@endphp

<div class="fact-resumen-detalle">
    <div class="fact-resumen-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <span class="fact-coti-card__badge mb-2 d-inline-block">{{ etiquetaNumeroCotizacion($c['numero'] ?? null) }}</span>
                <h4 class="mb-1 fw-bold">{{ $m['descripcion'] ?? 'Muestra' }}</h4>
                <p class="text-muted mb-1">
                    {{ $c['cliente'] ?? '—' }}
                    · {{ $resumen['canal']['etiqueta'] ?? 'Laboratorio' }}
                    · Instancia {{ $m['instance_number'] ?? '—' }}
                </p>
                @if(!empty($m['otn']))
                    <p class="text-muted small mb-0">OT {{ $m['otn'] }}</p>
                @endif
            </div>
            @if(!empty($m['imagen_url']))
                <a href="{{ $m['imagen_url'] }}" target="_blank" rel="noopener" class="fact-resumen-thumb">
                    <img src="{{ $m['imagen_url'] }}" alt="Imagen muestra" class="img-fluid rounded">
                </a>
            @endif
        </div>
    </div>

    <div class="accordion fact-resumen-accordion" id="resumenAccordion{{ $instancia->id }}">
        {{-- Cotización y referencias --}}
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#resCot{{ $instancia->id }}" aria-expanded="false">
                    Cotización y referencias
                </button>
            </h2>
            <div id="resCot{{ $instancia->id }}" class="accordion-collapse collapse" data-bs-parent="#resumenAccordion{{ $instancia->id }}">
                <div class="accordion-body">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h6 class="fact-resumen-subtitle">Datos de la cotización</h6>
                            <dl class="fact-resumen-dl">
                                @if(!empty($c['descripcion']))
                                    <dt>Descripción</dt><dd>{{ $c['descripcion'] }}</dd>
                                @endif
                                <dt>Matriz</dt><dd>{{ $dato($c['matriz'] ?? null) }}</dd>
                                <dt>Sucursal</dt><dd>{{ $dato($c['sucursal'] ?? null) }}</dd>
                                @if(!empty($c['estado']))
                                    <dt>Estado cotización</dt><dd>{{ $c['estado'] }}</dd>
                                @endif
                                @if(!empty($c['fecha_alta']))
                                    <dt>Fecha alta</dt><dd>{{ $c['fecha_alta'] }}</dd>
                                @endif
                                @if(!empty($c['fecha_aprobado']))
                                    <dt>Fecha aprobación</dt><dd>{{ $c['fecha_aprobado'] }}</dd>
                                @endif
                                @if(!empty($c['establecimiento']))
                                    <dt>Establecimiento</dt><dd>{{ $c['establecimiento'] }}</dd>
                                @endif
                                @if(!empty($c['direccion']) || !empty($c['localidad']))
                                    <dt>Ubicación</dt>
                                    <dd>{{ trim(($c['direccion'] ?? '') . ($c['localidad'] ? ', ' . $c['localidad'] : '')) ?: '—' }}</dd>
                                @endif
                                @if(!empty($c['condicion_pago']))
                                    <dt>Condición de pago</dt><dd>{{ $c['condicion_pago'] }}</dd>
                                @endif
                                @if(isset($c['monto_instancia']) && $c['monto_instancia'] !== null)
                                    <dt>Monto instancia</dt><dd>${{ number_format((float) $c['monto_instancia'], 2, ',', '.') }}</dd>
                                @endif
                            </dl>
                        </div>
                        <div class="col-lg-6">
                            <h6 class="fact-resumen-subtitle">Custodia y referencias</h6>
                            <dl class="fact-resumen-dl">
                                @if(!empty($c['nro_precinto']))
                                    <dt>N° precinto</dt><dd>{{ $c['nro_precinto'] }}</dd>
                                @endif
                                @if(!empty($c['nro_cadena_custodia']))
                                    <dt>Cadena de custodia (muestra)</dt><dd>{{ $c['nro_cadena_custodia'] }}</dd>
                                @endif
                                @if(!empty($c['cadena_custodia_cotizacion']))
                                    <dt>Cadena de custodia (cotización)</dt><dd>{{ $c['cadena_custodia_cotizacion'] }}</dd>
                                @endif
                            </dl>

                            @if(!empty($resumen['referencias_facturacion']))
                                <div class="mt-3">
                                    <h6 class="fact-resumen-subtitle mb-2">Referencias de facturación</h6>
                                    @include('facturacion.partials.referencias-facturacion-inline', [
                                        'refsFacturacion' => $resumen['referencias_facturacion'],
                                    ])
                                </div>
                            @endif

                            @if(!empty($c['notas_facturacion']))
                                <div class="mt-3 fact-resumen-note">
                                    <strong>Notas de facturación</strong>
                                    <p class="mb-0">{{ $c['notas_facturacion'] }}</p>
                                </div>
                            @endif
                            @if(!empty($c['notas']))
                                <div class="mt-3 fact-resumen-note">
                                    <strong>Notas generales</strong>
                                    <p class="mb-0">{{ $c['notas'] }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Muestreo (incluye mediciones y herramientas) --}}
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#resMuest{{ $instancia->id }}">
                    Muestreo
                </button>
            </h2>
            <div id="resMuest{{ $instancia->id }}" class="accordion-collapse collapse" data-bs-parent="#resumenAccordion{{ $instancia->id }}">
                <div class="accordion-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <dl class="fact-resumen-dl">
                                <dt>Identificación</dt><dd>{{ $dato($m['identificacion'] ?? null) }}</dd>
                                <dt>Fecha muestreo</dt><dd>{{ $dato($m['fecha_muestreo'] ?? null) }}</dd>
                                <dt>Inicio / fin</dt><dd>{{ $dato($m['fecha_inicio_muestreo'] ?? null) }} — {{ $dato($m['fecha_fin_muestreo'] ?? null) }}</dd>
                                <dt>Coordinador</dt><dd>{{ $dato($m['coordinador_muestreo'] ?? null) }}</dd>
                                <dt>Responsables</dt><dd>{{ $lista($m['responsables_muestreo'] ?? []) }}</dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="fact-resumen-dl">
                                <dt>Vehículo</dt><dd>{{ $dato($m['vehiculo'] ?? null) }}</dd>
                                <dt>Coordenadas</dt>
                                <dd>
                                    @if($m['latitud'] && $m['longitud'])
                                        {{ $m['latitud'] }}, {{ $m['longitud'] }}
                                    @else
                                        —
                                    @endif
                                </dd>
                            </dl>
                        </div>
                    </div>

                    <div class="fact-resumen-block mb-4">
                        <h6 class="fact-resumen-subtitle">Mediciones de campo</h6>
                        @if(empty($resumen['mediciones_campo']))
                            <p class="text-muted small mb-0">Sin mediciones registradas.</p>
                        @else
                            <div class="fact-mediciones-grid">
                                @foreach($resumen['mediciones_campo'] as $med)
                                    <div class="fact-medicion-item">
                                        <span class="fact-medicion-item__var">{{ $med['variable'] }}</span>
                                        <span class="fact-medicion-item__val">{{ $med['valor'] ?: '—' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="fact-resumen-block mb-3">
                        <h6 class="fact-resumen-subtitle">Herramientas de muestreo</h6>
                        @if(empty($resumen['herramientas_muestreo']))
                            <p class="text-muted small mb-0">Sin herramientas registradas.</p>
                        @else
                            <div class="fact-tools-grid">
                                @foreach($resumen['herramientas_muestreo'] as $h)
                                    <div class="fact-tool-chip">
                                        <span class="fact-tool-chip__name">{{ $h['equipamiento'] }}</span>
                                        @if($h['marca_modelo'])
                                            <span class="fact-tool-chip__meta">{{ $h['marca_modelo'] }}</span>
                                        @endif
                                        @if(($h['cantidad'] ?? 1) > 1)
                                            <span class="fact-tool-chip__qty">×{{ $h['cantidad'] }}</span>
                                        @endif
                                        @if($h['observaciones'])
                                            <span class="fact-tool-chip__obs">{{ $h['observaciones'] }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @php
                        $observacionesMuestreo = [];
                        $textosObservacionVistos = [];
                        foreach ([
                            'Observaciones del coordinador' => $m['observaciones_coord_muestreo'] ?? '',
                            'Observaciones del muestreador' => $m['observaciones_muestreador'] ?? '',
                            'Observaciones del coordinador (muestreo)' => $m['observaciones_muestreo_coord'] ?? '',
                            'Observaciones del muestreador (muestreo)' => $m['observaciones_muestreo_muestreador'] ?? '',
                        ] as $tituloObs => $textoObs) {
                            $textoObs = trim((string) $textoObs);
                            if ($textoObs === '') {
                                continue;
                            }
                            $clave = mb_strtolower($textoObs);
                            if (isset($textosObservacionVistos[$clave])) {
                                continue;
                            }
                            $textosObservacionVistos[$clave] = true;
                            $observacionesMuestreo[] = ['titulo' => $tituloObs, 'texto' => $textoObs];
                        }
                    @endphp
                    @foreach($observacionesMuestreo as $obs)
                        <div class="fact-resumen-note mt-3">
                            <strong>{{ $obs['titulo'] }}</strong>
                            <p class="mb-0">{{ $obs['texto'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Análisis y resultados --}}
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#resAn{{ $instancia->id }}">
                    Análisis y resultados ({{ count($resumen['analisis'] ?? []) }})
                </button>
            </h2>
            <div id="resAn{{ $instancia->id }}" class="accordion-collapse collapse" data-bs-parent="#resumenAccordion{{ $instancia->id }}">
                <div class="accordion-body fact-analisis-list">
                    @forelse($resumen['analisis'] ?? [] as $analisis)
                        <article class="fact-analisis-card">
                            <header class="fact-analisis-card__header">
                                <div>
                                    <h6 class="fact-analisis-card__title">{{ $analisis['descripcion'] }}</h6>
                                    <div class="fact-analisis-card__meta">
                                        @if($analisis['fecha_informe'])
                                            <span>Informe: {{ $analisis['fecha_informe'] }}</span>
                                        @endif
                                        @if($analisis['fecha_carga_ot'])
                                            <span>Carga: {{ $analisis['fecha_carga_ot'] }}</span>
                                        @endif
                                        @if($analisis['coordinador_lab'])
                                            <span>Coord. lab: {{ $analisis['coordinador_lab'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            </header>

                            @if(!empty($analisis['responsables_por_sector']))
                                <div class="fact-sector-groups mb-3">
                                    @foreach($analisis['responsables_por_sector'] as $grupo)
                                        <div class="fact-sector-group">
                                            <span class="fact-sector-group__label">{{ $grupo['sector_nombre'] }}</span>
                                            <div class="fact-sector-group__users">
                                                @foreach($grupo['usuarios'] as $usuario)
                                                    <span class="fact-user-chip">{{ $usuario['usu_descripcion'] ?: $usuario['usu_codigo'] }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if(!empty($analisis['resultados']))
                                <div class="fact-result-grid mb-3">
                                    @foreach($analisis['resultados'] as $res)
                                        <div class="fact-result-card {{ !empty($res['es_final']) ? 'fact-result-card--final' : '' }}">
                                            <div class="fact-result-card__label">{{ $res['label'] }}</div>
                                            <div class="fact-result-card__value">{{ $res['valor'] ?: '—' }}</div>
                                            @if(!empty($res['observacion']))
                                                <div class="fact-result-card__obs">{{ $res['observacion'] }}</div>
                                            @endif
                                            <div class="fact-result-card__footer">
                                                @if($res['fecha_carga'])
                                                    <span>{{ $res['fecha_carga'] }}</span>
                                                @endif
                                                @if($res['responsable'])
                                                    <span>{{ $res['responsable'] }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-muted small mb-3">Sin resultados cargados.</p>
                            @endif

                            @if(!empty($analisis['herramientas_lab']))
                                <div class="fact-resumen-block">
                                    <h6 class="fact-resumen-subtitle mb-2">Herramientas de laboratorio</h6>
                                    <div class="fact-tools-grid">
                                        @foreach($analisis['herramientas_lab'] as $hl)
                                            <div class="fact-tool-chip fact-tool-chip--lab">
                                                <span class="fact-tool-chip__name">{{ $hl['equipamiento'] }}</span>
                                                @if($hl['marca_modelo'])
                                                    <span class="fact-tool-chip__meta">{{ $hl['marca_modelo'] }}</span>
                                                @endif
                                                @if(($hl['cantidad'] ?? 1) > 1)
                                                    <span class="fact-tool-chip__qty">×{{ $hl['cantidad'] }}</span>
                                                @endif
                                                @if($hl['observaciones'])
                                                    <span class="fact-tool-chip__obs">{{ $hl['observaciones'] }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if(!empty($analisis['observaciones_ot']))
                                <div class="fact-resumen-note mt-3">
                                    <strong>Observaciones OT</strong>
                                    <p class="mb-0">{{ $analisis['observaciones_ot'] }}</p>
                                </div>
                            @endif
                        </article>
                    @empty
                        <p class="text-muted mb-0">Sin análisis asociados a esta instancia.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Informe y revisión --}}
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#resInf{{ $instancia->id }}">
                    Informe y revisión de facturación
                </button>
            </h2>
            <div id="resInf{{ $instancia->id }}" class="accordion-collapse collapse" data-bs-parent="#resumenAccordion{{ $instancia->id }}">
                <div class="accordion-body">
                    <dl class="fact-resumen-dl">
                        <dt>Informe aprobado</dt><dd>{{ !empty($informe['aprobado']) ? 'Sí' : 'No' }}</dd>
                        <dt>Fecha aprobación</dt><dd>{{ $dato($informe['fecha_aprobacion'] ?? null) }}</dd>
                        <dt>Aprobador</dt><dd>{{ $dato($informe['aprobador'] ?? null) }}</dd>
                        <dt>Firmado digitalmente</dt><dd>{{ !empty($informe['firmado']) ? 'Sí' : 'No' }}</dd>
                        <dt>Fecha firma</dt><dd>{{ $dato($informe['fecha_firma'] ?? null) }}</dd>
                        <dt>Revisión facturación</dt>
                        <dd>
                            @if(!empty($revision['aprobada']))
                                Aprobada {{ $revision['fecha'] ? 'el ' . $revision['fecha'] : '' }}
                                @if($revision['usuario']) por {{ $revision['usuario'] }} @endif
                            @else
                                Pendiente
                            @endif
                        </dd>
                    </dl>
                    @if(!empty($m['observaciones_ot']))
                        <div class="fact-resumen-note mt-2">
                            <strong>Observaciones OT (muestra)</strong>
                            <p class="mb-0">{{ $m['observaciones_ot'] }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(!empty($puedeAprobar))
        <div class="fact-resumen-footer mt-4 pt-3 border-top d-flex justify-content-end">
            <form method="POST" action="{{ route('facturacion-revision.aprobar', $instancia) }}" class="js-aprobar-facturacion"
                  data-swal-title="Aprobar muestra"
                  data-swal-html="¿Aprobar la muestra <strong>{{ $m['descripcion'] ?? 'seleccionada' }}</strong> (inst. {{ $m['instance_number'] ?? '—' }}) para facturación?">
                @csrf
                <button type="submit" class="btn btn-success">
                    <x-heroicon-o-check-circle style="width: 18px; height: 18px;" class="me-1" />
                    Aprobar para facturación
                </button>
            </form>
        </div>
    @endif
</div>

<style>
    .fact-resumen-dl { margin: 0; }
    .fact-resumen-dl dt {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6c757d;
        margin-top: 0.65rem;
    }
    .fact-resumen-dl dd { margin-bottom: 0.15rem; font-size: 0.92rem; }
    .fact-resumen-subtitle {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #495057;
        margin-bottom: 0.75rem;
    }
    .fact-resumen-thumb { max-width: 120px; display: block; }
    .fact-resumen-thumb img { max-height: 90px; object-fit: cover; }
    .fact-resumen-accordion .accordion-button { font-weight: 600; font-size: 0.95rem; }
    .fact-resumen-note {
        background: #f8f9fc;
        border-left: 3px solid #dee2e6;
        padding: 0.65rem 0.85rem;
        border-radius: 0 6px 6px 0;
        font-size: 0.88rem;
    }
    .fact-ref-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.65rem;
        background: #eef2ff;
        border: 1px solid #dbe4ff;
        border-radius: 999px;
        font-size: 0.82rem;
    }
    .fact-mediciones-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 0.5rem;
    }
    .fact-medicion-item {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 0.55rem 0.75rem;
    }
    .fact-medicion-item__var {
        display: block;
        font-size: 0.72rem;
        text-transform: uppercase;
        color: #6c757d;
        letter-spacing: 0.03em;
    }
    .fact-medicion-item__val {
        display: block;
        font-weight: 600;
        font-size: 0.95rem;
        color: #212529;
    }
    .fact-tools-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .fact-tool-chip {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 0.45rem 0.65rem;
        font-size: 0.85rem;
        max-width: 100%;
    }
    .fact-tool-chip--lab { border-color: #c5d4f7; background: #f8faff; }
    .fact-tool-chip__name { font-weight: 600; margin-right: 0.25rem; }
    .fact-tool-chip__meta { color: #6c757d; font-size: 0.8rem; }
    .fact-tool-chip__qty {
        display: inline-block;
        margin-left: 0.35rem;
        padding: 0 0.35rem;
        background: #0d6efd;
        color: #fff;
        border-radius: 4px;
        font-size: 0.75rem;
    }
    .fact-tool-chip__obs {
        display: block;
        font-size: 0.78rem;
        color: #6c757d;
        margin-top: 0.15rem;
    }
    .fact-analisis-list { display: flex; flex-direction: column; gap: 1rem; padding: 1rem !important; }
    .fact-analisis-card {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        padding: 1rem 1.15rem;
        box-shadow: 0 1px 4px rgba(0,0,0,0.04);
    }
    .fact-analisis-card__title { margin: 0 0 0.25rem; font-weight: 700; font-size: 1rem; }
    .fact-analisis-card__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        font-size: 0.78rem;
        color: #6c757d;
    }
    .fact-sector-groups { display: flex; flex-direction: column; gap: 0.5rem; }
    .fact-sector-group {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        gap: 0.5rem;
        padding: 0.5rem 0.65rem;
        background: #f8f9fc;
        border-radius: 8px;
    }
    .fact-sector-group__label {
        flex-shrink: 0;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #4e73df;
        min-width: 90px;
        padding-top: 0.15rem;
    }
    .fact-sector-group__users { display: flex; flex-wrap: wrap; gap: 0.35rem; flex: 1; }
    .fact-user-chip {
        display: inline-block;
        padding: 0.2rem 0.55rem;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 999px;
        font-size: 0.8rem;
    }
    .fact-result-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 0.65rem;
    }
    .fact-result-card {
        background: #f8f9fc;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        padding: 0.75rem 0.85rem;
    }
    .fact-result-card--final {
        background: linear-gradient(135deg, #eef2ff 0%, #f8f9fc 100%);
        border-color: #4e73df;
        grid-column: 1 / -1;
    }
    .fact-result-card__label {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }
    .fact-result-card__value {
        font-size: 1.35rem;
        font-weight: 700;
        color: #212529;
        line-height: 1.2;
        word-break: break-word;
    }
    .fact-result-card--final .fact-result-card__value { color: #4e73df; }
    .fact-result-card__obs {
        margin-top: 0.35rem;
        font-size: 0.82rem;
        color: #495057;
        font-style: italic;
    }
    .fact-result-card__footer {
        margin-top: 0.5rem;
        padding-top: 0.45rem;
        border-top: 1px dashed #dee2e6;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem 1rem;
        font-size: 0.75rem;
        color: #6c757d;
    }
</style>
