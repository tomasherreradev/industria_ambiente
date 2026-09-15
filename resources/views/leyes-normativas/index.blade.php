@extends('layouts.app')

@section('title', 'Leyes y Normativas')

@section('content')
<link rel="stylesheet" href="{{ asset('css/usuarios-crud.css') }}?v={{ filemtime(public_path('css/usuarios-crud.css')) }}">

@php
    $hayFiltros = request()->filled('search') || request()->filled('grupo') || request()->filled('activo');
@endphp

<div class="container py-4 ucrud" data-ucrud-root>
    <header class="ucrud-header">
        <div class="ucrud-header__titles">
            <h1 class="ucrud-title">
                Leyes y normativas
                <span class="ucrud-count">{{ $normativas->total() }}</span>
            </h1>
            @if($ultimaImportacion)
                <span class="ucrud-header__meta">
                    <x-heroicon-o-clock style="width: 14px; height: 14px; display: inline; vertical-align: -2px;" />
                    Última importación: {{ $ultimaImportacion->format('d/m/Y H:i') }}
                </span>
            @else
                <p class="ucrud-subtitle">Catálogo de normativas aplicables a determinaciones.</p>
            @endif
        </div>

        <div class="ucrud-header__actions">
            <a href="{{ route('leyes-normativas.export.template') }}" class="ucrud-btn ucrud-btn--outline-success">
                <x-heroicon-o-arrow-down-tray style="width: 16px; height: 16px;" />
                Plantilla
            </a>
            <a href="{{ route('leyes-normativas.import') }}" class="ucrud-btn ucrud-btn--outline-primary">
                <x-heroicon-o-arrow-up-tray style="width: 16px; height: 16px;" />
                Importar
            </a>
            <a href="{{ route('leyes-normativas.create') }}" class="ucrud-btn ucrud-btn--primary">
                <x-heroicon-o-plus style="width: 16px; height: 16px;" />
                Nueva normativa
            </a>
        </div>
    </header>

    @if(session('success'))
        <div class="ucrud-alert ucrud-alert--success" role="status">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/>
                <path d="m8.5 12.5 2.5 2.5 4.5-5"/>
            </svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="ucrud-alert ucrud-alert--danger" role="alert">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M10.3 3.9 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>
                <path d="M12 9v4M12 17h.01"/>
            </svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if(session('warning'))
        <div class="ucrud-alert ucrud-alert--warning" role="alert">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M10.3 3.9 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>
                <path d="M12 9v4M12 17h.01"/>
            </svg>
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    @if(session('import_errors'))
        <div class="ucrud-alert ucrud-alert--warning" role="alert">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M10.3 3.9 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>
                <path d="M12 9v4M12 17h.01"/>
            </svg>
            <span>
                <strong>Errores durante la importación:</strong>
                <ul class="mb-0 mt-2 ps-3">
                    @foreach(session('import_errors') as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </span>
        </div>
    @endif

    <div class="ucrud-filters">
        <p class="ucrud-filters__title">Filtros</p>
        <form method="GET" action="{{ route('leyes-normativas.index') }}" class="ucrud-filters__grid">
            <div class="ucrud-field">
                <label for="search">Buscar</label>
                <input type="text" class="ucrud-input" style="padding-left: .9rem;" id="search" name="search"
                       value="{{ request('search') }}" placeholder="Código, nombre o grupo…">
            </div>
            <div class="ucrud-field">
                <label for="grupo">Grupo</label>
                <select class="ucrud-select" style="width: 100%;" id="grupo" name="grupo">
                    <option value="">Todos los grupos</option>
                    @foreach($grupos as $grupo)
                        <option value="{{ $grupo }}" @selected(request('grupo') == $grupo)>{{ $grupo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ucrud-field">
                <label for="activo">Estado</label>
                <select class="ucrud-select" style="width: 100%;" id="activo" name="activo">
                    <option value="">Todas</option>
                    <option value="1" @selected(request('activo') == '1')>Activas</option>
                    <option value="0" @selected(request('activo') == '0')>Inactivas</option>
                </select>
            </div>
            <div class="ucrud-filters__actions">
                @if($hayFiltros)
                    <a href="{{ route('leyes-normativas.index') }}" class="ucrud-btn ucrud-btn--ghost">Limpiar</a>
                @endif
                <button type="submit" class="ucrud-btn ucrud-btn--primary">Filtrar</button>
            </div>
        </form>
    </div>

    <div class="ucrud-panel">
        @if($normativas->isEmpty())
            <div class="ucrud-empty">
                <div class="ucrud-empty__icon">
                    <x-heroicon-o-scale style="width: 28px; height: 28px;" />
                </div>
                <p class="ucrud-empty__title">No se encontraron leyes o normativas</p>
                <p class="ucrud-empty__text">
                    {{ $hayFiltros ? 'Probá ajustar los filtros de búsqueda.' : 'Creá la primera normativa del catálogo.' }}
                </p>
                @unless($hayFiltros)
                    <a href="{{ route('leyes-normativas.create') }}" class="ucrud-btn ucrud-btn--primary mt-3">Crear la primera normativa</a>
                @endunless
            </div>
        @else
            <div class="ucrud-tablewrap">
                <table class="ucrud-table ucrud-table--sticky-actions">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Vigencia</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($normativas as $i => $normativa)
                            <tr class="ucrud-animate-in" style="--i: {{ $i }}">
                                <td><span class="ucrud-code">{{ $normativa->codigo }}</span></td>
                                <td><span class="ucrud-user__name">{{ Str::limit($normativa->nombre, 60) }}</span></td>
                                <td>
                                    @if($normativa->fecha_vigencia)
                                        {{ $normativa->fecha_vigencia->format('d/m/Y') }}
                                    @else
                                        <span class="ucrud-dim">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="ucrud-chip {{ $normativa->activo ? 'ucrud-chip--green' : 'ucrud-chip--muted' }}">
                                        {{ $normativa->activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="ucrud-actions">
                                        <a href="{{ route('leyes-normativas.show', $normativa) }}"
                                           class="ucrud-iconbtn" title="Ver" aria-label="Ver normativa">
                                            <x-heroicon-o-eye style="width: 16px; height: 16px;" />
                                        </a>
                                        <a href="{{ route('leyes-normativas.edit', $normativa) }}"
                                           class="ucrud-iconbtn" title="Editar" aria-label="Editar normativa">
                                            <x-heroicon-o-pencil style="width: 16px; height: 16px;" />
                                        </a>
                                        <a href="{{ route('leyes-normativas.delete', $normativa) }}"
                                           class="ucrud-iconbtn ucrud-iconbtn--danger" title="Eliminar" aria-label="Eliminar normativa">
                                            <x-heroicon-o-trash style="width: 16px; height: 16px;" />
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($normativas->hasPages())
                <div class="ucrud-pagination">
                    {{ $normativas->appends(request()->query())->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
