@extends('layouts.app')

@section('title', 'Variables Requeridas')

@section('content')
<link rel="stylesheet" href="{{ asset('css/usuarios-crud.css') }}?v={{ filemtime(public_path('css/usuarios-crud.css')) }}">

@php
    $hayFiltros = request()->filled('search') || request()->filled('obligatorio');
    $totalVariables = $groupedVariables->flatten(1)->count();
@endphp

<div class="container py-4 ucrud" data-ucrud-root>
    <header class="ucrud-header">
        <div class="ucrud-header__titles">
            <h1 class="ucrud-title">
                Variables requeridas
                <span class="ucrud-count">{{ $totalVariables }}</span>
            </h1>
            <p class="ucrud-subtitle">Variables de muestreo agrupadas por determinación.</p>
        </div>

        <div class="ucrud-header__actions">
            <a href="{{ route('variables-requeridas.create') }}" class="ucrud-btn ucrud-btn--primary">
                <x-heroicon-o-plus style="width: 16px; height: 16px;" />
                Crear nueva
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

    <form action="{{ route('variables-requeridas.index') }}" method="GET" class="ucrud-toolbar ucrud-toolbar--wide">
        <label class="ucrud-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <circle cx="11" cy="11" r="7"/>
                <path d="m20 20-3.1-3.1"/>
            </svg>
            <input type="text" name="search" class="ucrud-input" placeholder="Buscar por descripción o nombre…"
                   value="{{ request('search') }}" aria-label="Buscar variables">
        </label>

        <select name="obligatorio" class="ucrud-select" aria-label="Filtrar por obligatoriedad">
            <option value="">Todas</option>
            <option value="1" @selected(request('obligatorio') == '1')>Obligatorias</option>
            <option value="0" @selected(request('obligatorio') == '0')>Opcionales</option>
        </select>

        <button type="submit" class="ucrud-btn ucrud-btn--primary">Buscar</button>

        @if($hayFiltros)
            <a href="{{ route('variables-requeridas.index') }}" class="ucrud-btn ucrud-btn--ghost">Limpiar</a>
        @endif
    </form>

    @if($groupedVariables->isEmpty())
        <div class="ucrud-panel">
            <div class="ucrud-empty">
                <div class="ucrud-empty__icon">
                    <x-heroicon-o-variable style="width: 28px; height: 28px;" />
                </div>
                <p class="ucrud-empty__title">No hay variables para mostrar</p>
                <p class="ucrud-empty__text">
                    {{ $hayFiltros ? 'Probá ajustar la búsqueda o quitar los filtros.' : 'Creá la primera variable requerida.' }}
                </p>
            </div>
        </div>
    @else
        <div class="ucrud-accordion" id="variablesAccordion">
            @foreach($groupedVariables as $cotioDescripcion => $variables)
                <div class="ucrud-accordion__item">
                    <div class="ucrud-accordion__header" id="heading{{ $loop->index }}">
                        <button class="ucrud-accordion__toggle" type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#collapse{{ $loop->index }}"
                                aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                                aria-controls="collapse{{ $loop->index }}">
                            <span>{{ $cotioDescripcion }}</span>
                            <span class="ucrud-chip ucrud-chip--blue">{{ count($variables) }} variables</span>
                        </button>
                        <a href="{{ route('variables-requeridas.edit-group', urlencode($cotioDescripcion)) }}"
                           class="ucrud-btn ucrud-btn--ghost" title="Editar grupo">
                            <x-heroicon-o-pencil-square style="width: 16px; height: 16px;" />
                            Editar grupo
                        </a>
                    </div>

                    <div id="collapse{{ $loop->index }}"
                         class="collapse ucrud-accordion__body {{ $loop->first ? 'show' : '' }}"
                         aria-labelledby="heading{{ $loop->index }}"
                         data-bs-parent="#variablesAccordion">
                        <div class="ucrud-tablewrap">
                            <table class="ucrud-table ucrud-table--sticky-actions">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Obligatorio</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($variables as $i => $variable)
                                        <tr class="ucrud-animate-in" style="--i: {{ $i }}">
                                            <td><span class="ucrud-code">{{ $variable->id }}</span></td>
                                            <td><span class="ucrud-user__name">{{ $variable->nombre }}</span></td>
                                            <td>
                                                <span class="ucrud-chip {{ $variable->obligatorio ? 'ucrud-chip--green' : 'ucrud-chip--amber' }}">
                                                    {{ $variable->obligatorio ? 'Sí' : 'No' }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="ucrud-actions">
                                                    <a href="{{ route('variables-requeridas.edit', $variable->id) }}"
                                                       class="ucrud-iconbtn"
                                                       title="Editar"
                                                       aria-label="Editar variable {{ $variable->nombre }}">
                                                        <x-heroicon-o-pencil style="width: 16px; height: 16px;" />
                                                    </a>
                                                    <button type="button"
                                                            class="ucrud-iconbtn ucrud-iconbtn--danger"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#deleteModal{{ $variable->id }}"
                                                            title="Eliminar"
                                                            aria-label="Eliminar variable {{ $variable->nombre }}">
                                                        <x-heroicon-o-trash style="width: 16px; height: 16px;" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @foreach($groupedVariables as $variables)
            @foreach($variables as $variable)
                <div class="modal fade" id="deleteModal{{ $variable->id }}" tabindex="-1"
                     aria-labelledby="deleteModalLabel{{ $variable->id }}" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteModalLabel{{ $variable->id }}">Confirmar eliminación</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                ¿Estás seguro de que deseas eliminar la variable "{{ $variable->nombre }}"?
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <form action="{{ route('variables-requeridas.destroy', $variable->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger">Eliminar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @endforeach
    @endif
</div>
@endsection
