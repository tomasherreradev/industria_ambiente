@extends('layouts.app')

@section('title', 'Condiciones de pago')

@section('content')
<link rel="stylesheet" href="{{ asset('css/usuarios-crud.css') }}?v={{ filemtime(public_path('css/usuarios-crud.css')) }}">

@php
    $hayFiltros = trim((string) $q) !== '';
@endphp

<div class="container py-4 ucrud" data-ucrud-root>
    <header class="ucrud-header">
        <div class="ucrud-header__titles">
            <h1 class="ucrud-title">
                Condiciones de pago
                <span class="ucrud-count">{{ $condiciones->total() }}</span>
            </h1>
            <p class="ucrud-subtitle">Plazos y códigos de condición para cotizaciones y facturación.</p>
        </div>

        <div class="ucrud-header__actions">
            <a href="{{ route('condiciones-pago.create') }}" class="ucrud-btn ucrud-btn--primary">
                <x-heroicon-o-plus style="width: 16px; height: 16px;" />
                Nueva
            </a>
        </div>
    </header>

    <form method="GET" action="{{ route('condiciones-pago.index') }}" class="ucrud-toolbar">
        <label class="ucrud-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <circle cx="11" cy="11" r="7"/>
                <path d="m20 20-3.1-3.1"/>
            </svg>
            <input type="text" name="q" class="ucrud-input" value="{{ $q }}" placeholder="Buscar código o descripción…" aria-label="Buscar condiciones de pago">
        </label>

        <button type="submit" class="ucrud-btn ucrud-btn--primary">Filtrar</button>

        @if($hayFiltros)
            <a href="{{ route('condiciones-pago.index') }}" class="ucrud-btn ucrud-btn--ghost">Limpiar</a>
        @endif
    </form>

    @if(session('success'))
        <div class="ucrud-alert ucrud-alert--success" role="status">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/>
                <path d="m8.5 12.5 2.5 2.5 4.5-5"/>
            </svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="ucrud-panel">
        @if($condiciones->isEmpty())
            <div class="ucrud-empty">
                <div class="ucrud-empty__icon">
                    <x-heroicon-o-banknotes style="width: 28px; height: 28px;" />
                </div>
                <p class="ucrud-empty__title">No hay condiciones de pago</p>
                <p class="ucrud-empty__text">
                    {{ $hayFiltros ? 'Probá ajustar la búsqueda.' : 'Creá la primera condición de pago.' }}
                </p>
            </div>
        @else
            <div class="ucrud-tablewrap">
                <table class="ucrud-table ucrud-table--sticky-actions">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Días</th>
                            <th>Activa</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($condiciones as $i => $c)
                            <tr class="ucrud-animate-in" style="--i: {{ $i }}">
                                <td><span class="ucrud-code">{{ $c->pag_codigo }}</span></td>
                                <td><span class="ucrud-user__name">{{ $c->pag_descripcion }}</span></td>
                                <td class="text-end">{{ $c->pag_dias ?? '—' }}</td>
                                <td>
                                    <span class="ucrud-chip {{ $c->pag_estado ? 'ucrud-chip--green' : 'ucrud-chip--muted' }}">
                                        {{ $c->pag_estado ? 'Sí' : 'No' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="ucrud-actions">
                                        <a href="{{ route('condiciones-pago.edit', $c) }}" class="ucrud-iconbtn" title="Editar" aria-label="Editar condición">
                                            <x-heroicon-o-pencil style="width: 16px; height: 16px;" />
                                        </a>
                                        <form action="{{ route('condiciones-pago.destroy', $c) }}" method="POST" class="d-inline js-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ucrud-iconbtn ucrud-iconbtn--danger" title="Eliminar" aria-label="Eliminar condición">
                                                <x-heroicon-o-trash style="width: 16px; height: 16px;" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($condiciones->hasPages())
                <div class="ucrud-pagination">
                    {{ $condiciones->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            Swal.fire({
                title: '¿Eliminar condición de pago?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33'
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});
</script>
@endpush
