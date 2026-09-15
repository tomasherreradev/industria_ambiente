@extends('layouts.app')

@section('title', $leyNormativa->nombre)

@section('content')
@include('partials.ucrud-styles')

<div class="container py-4 ucrud">
    @include('partials.ucrud-form-header', [
        'title' => $leyNormativa->nombre,
        'subtitle' => $leyNormativa->codigo,
        'actions' => '<a href="' . route('leyes-normativas.edit', $leyNormativa) . '" class="ucrud-btn ucrud-btn--primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" width="16" height="16"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg> Editar</a><a href="' . route('leyes-normativas.index') . '" class="ucrud-btn ucrud-btn--ghost"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" width="16" height="16"><path d="m15 18-6-6 6-6"/></svg> Volver</a>',
    ])

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="ucrud-panel">
                <div class="ucrud-detail-block">
                    <h2 class="ucrud-detail-block__title">Información de la normativa</h2>
                    <div class="ucrud-detail-grid">
                        <div>
                            <div class="ucrud-detail-item__label">Código</div>
                            <div class="ucrud-detail-item__value"><span class="ucrud-code">{{ $leyNormativa->codigo }}</span></div>
                        </div>
                        <div>
                            <div class="ucrud-detail-item__label">Grupo</div>
                            <div class="ucrud-detail-item__value"><span class="ucrud-chip ucrud-chip--cyan">{{ $leyNormativa->grupo }}</span></div>
                        </div>
                        <div>
                            <div class="ucrud-detail-item__label">Estado</div>
                            <div class="ucrud-detail-item__value">
                                <span class="ucrud-chip {{ $leyNormativa->activo ? 'ucrud-chip--green' : 'ucrud-chip--muted' }}">
                                    {{ $leyNormativa->activo ? 'Activa' : 'Inactiva' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="ucrud-detail-block">

                    @if($leyNormativa->articulo)
                        <div class="mb-3">
                            <div class="ucrud-detail-item__label">Artículo</div>
                            <div class="ucrud-detail-item__value">{{ $leyNormativa->articulo }}</div>
                        </div>
                    @endif

                    @if($leyNormativa->descripcion)
                        <div class="mb-3">
                            <div class="ucrud-detail-item__label">Descripción</div>
                            <div class="ucrud-detail-item__value">{{ $leyNormativa->descripcion }}</div>
                        </div>
                    @endif

                    @if($leyNormativa->variables->count() > 0)
                        <div class="mb-3">
                            <label class="form-label fw-bold">Variables Asociadas:</label>
                            <div class="row">
                                @foreach($leyNormativa->variables as $variable)
                                    <div class="col-md-12 mb-3">
                                        <div class="card">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1">
                                                            @if($variable->cotioItem)
                                                                <code>{{ $variable->cotioItem->id }}</code> - {{ $variable->cotioItem->cotio_descripcion }}
                                                            @else
                                                                {{ $variable->nombre }}
                                                            @endif
                                                        </h6>
                                                        <div class="mt-2">
                                                            @if($variable->cotioItem)
                                                                @if($variable->cotioItem->matriz)
                                                                    <span class="badge bg-primary me-1">
                                                                        <i class="fas fa-flask"></i> Matriz: {{ $variable->cotioItem->matriz->matriz_descripcion }}
                                                                    </span>
                                                                @endif
                                                                @php
                                                                    $metodos = [];
                                                                    if($variable->cotioItem->metodoAnalitico) {
                                                                        $metodos[] = $variable->cotioItem->metodoAnalitico->metodo_descripcion;
                                                                    }
                                                                    if($variable->cotioItem->metodoMuestreo) {
                                                                        $metodos[] = $variable->cotioItem->metodoMuestreo->metodo_descripcion;
                                                                    }
                                                                @endphp
                                                                @if(!empty($metodos))
                                                                    <span class="badge bg-info me-1">
                                                                        <i class="fas fa-cogs"></i> Métodos: {{ implode(' / ', $metodos) }}
                                                                    </span>
                                                                @endif
                                                            @else
                                                                <small class="text-muted">
                                                                    <code>{{ $variable->codigo }}</code>
                                                                    @if($variable->tipo_variable)
                                                                        • {{ $variable->tipo_variable }}
                                                                    @endif
                                                                </small>
                                                            @endif
                                                        </div>
                                                        @if($variable->pivot->valor_limite)
                                                            <div class="mt-2">
                                                                <small class="text-info">
                                                                    <strong><i class="fas fa-chart-line"></i> Valor Límite:</strong> 
                                                                    {{ $variable->pivot->valor_limite }}
                                                                    @if($variable->pivot->unidad_medida)
                                                                        <span class="badge bg-secondary">{{ $variable->pivot->unidad_medida }}</span>
                                                                    @endif
                                                                </small>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($leyNormativa->variables_aplicables)
                        <div class="mb-3">
                            <label class="form-label fw-bold">Variables Aplicables (Texto):</label>
                            <p>{{ $leyNormativa->variables_aplicables }}</p>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-6">
                            @if($leyNormativa->organismo_emisor)
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Organismo Emisor:</label>
                                    <p>{{ $leyNormativa->organismo_emisor }}</p>
                                </div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            @if($leyNormativa->fecha_vigencia)
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Fecha de Vigencia:</label>
                                    <p>{{ $leyNormativa->fecha_vigencia->format('d/m/Y') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($leyNormativa->fecha_actualizacion)
                        <div class="mb-3">
                            <label class="form-label fw-bold">Última Actualización:</label>
                            <p>{{ $leyNormativa->fecha_actualizacion->format('d/m/Y') }}</p>
                        </div>
                    @endif

                    @if($leyNormativa->observaciones)
                        <div class="mb-3">
                            <label class="form-label fw-bold">Observaciones:</label>
                            <div class="border rounded p-3 bg-light">
                                <p class="mb-0">{{ $leyNormativa->observaciones }}</p>
                            </div>
                        </div>
                    @endif

                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="ucrud-panel">
                <div class="ucrud-detail-block">
                    <h2 class="ucrud-detail-block__title">Uso de la normativa</h2>
                    @if($leyNormativa->cotios->count() > 0)
                        <p class="text-success">
                            <i class="fas fa-check-circle"></i>
                            Esta normativa está siendo usada en <strong>{{ $leyNormativa->cotios->count() }}</strong> cotización(es).
                        </p>
                        <div class="alert alert-warning">
                            <small>
                                <i class="fas fa-exclamation-triangle"></i>
                                No se puede eliminar esta normativa mientras esté en uso.
                            </small>
                        </div>
                    @else
                        <p class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            Esta normativa no está siendo usada actualmente.
                        </p>
                    @endif
                </div>
            </div>

            <div class="ucrud-panel mt-3">
                <div class="ucrud-detail-block">
                    <h2 class="ucrud-detail-block__title">Acciones</h2>
                    <div class="d-flex flex-column gap-2">
                        <a href="{{ route('leyes-normativas.edit', $leyNormativa) }}" class="ucrud-btn ucrud-btn--primary">
                            <x-heroicon-o-pencil style="width: 16px; height: 16px;" /> Editar normativa
                        </a>
                        <a href="{{ route('leyes-normativas.delete', $leyNormativa) }}" class="ucrud-btn ucrud-btn--ghost" style="color:#d0453c;border-color:#f0c4c1;">
                            <x-heroicon-o-trash style="width: 16px; height: 16px;" /> Eliminar normativa
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
