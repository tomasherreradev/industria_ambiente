@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
@include('partials.operativo-styles')

@php
    $hayFiltros = request()->hasAny(['search', 'estado']);
@endphp

<div class="container py-4 ucrud ucrud-operativo" data-ucrud-root>
    <header class="ucrud-header">
        <div class="ucrud-header__titles">
            <h1 class="ucrud-title">
                Clientes
                <span class="ucrud-count">{{ $clientes->total() }}</span>
            </h1>
            <p class="ucrud-subtitle">
                {{ number_format($stats['total']) }} registrados ·
                {{ number_format($stats['activos']) }} activos ·
                {{ number_format($stats['inactivos']) }} inactivos
            </p>
        </div>

        @if(! $readOnly)
            <div class="ucrud-header__actions d-flex flex-wrap gap-2 justify-content-end">
                <div class="dropdown">
                    <button type="button" class="ucrud-btn ucrud-btn--ghost dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <x-heroicon-o-arrow-up-tray style="width: 16px; height: 16px;" />
                        Importar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#importModal">
                                <x-heroicon-o-arrow-up-tray style="width: 16px; height: 16px;" />
                                Subir archivo
                            </button>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('clientes.plantilla') }}">
                                <x-heroicon-o-arrow-down-tray style="width: 16px; height: 16px;" />
                                Plantilla vacía
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('clientes.plantilla-pendientes') }}">
                                <x-heroicon-o-arrow-down-tray style="width: 16px; height: 16px;" />
                                Plantilla pendientes
                            </a>
                        </li>
                    </ul>
                </div>
                <a href="{{ route('clientes.create') }}" class="ucrud-btn ucrud-btn--primary">
                    <x-heroicon-o-plus style="width: 16px; height: 16px;" />
                    Nuevo cliente
                </a>
            </div>
        @endif
    </header>

    <div class="ucrud-filters">
        <p class="ucrud-filters__title">Filtros</p>
        <form action="{{ route('clientes.index') }}" method="GET" class="ucrud-filters__grid">
            <div class="ucrud-field">
                <label for="search">Buscar</label>
                <input type="text" name="search" id="search" class="ucrud-input" style="padding-left: .9rem;"
                       placeholder="Código, razón social, CUIT…" value="{{ request('search') }}">
            </div>
            <div class="ucrud-field">
                <label for="estado">Estado</label>
                <select name="estado" id="estado" class="ucrud-select" style="width: 100%;">
                    <option value="">Todos</option>
                    <option value="1" @selected(request('estado') === '1')>Activos</option>
                    <option value="0" @selected(request('estado') === '0')>Inactivos</option>
                </select>
            </div>
            <div class="ucrud-filters__actions">
                @if($hayFiltros)
                    <a href="{{ route('clientes.index') }}" class="ucrud-btn ucrud-btn--ghost">Limpiar</a>
                @endif
                <button type="submit" class="ucrud-btn ucrud-btn--primary">Aplicar filtros</button>
            </div>
        </form>
    </div>

    <div class="ucrud-panel">
        @if($clientes->isEmpty())
            <div class="ucrud-empty">
                <div class="ucrud-empty__icon">
                    <x-heroicon-o-users style="width: 28px; height: 28px;" />
                </div>
                <p class="ucrud-empty__title">No se encontraron clientes</p>
                <p class="ucrud-empty__text">
                    {{ $hayFiltros ? 'Probá ajustar los filtros de búsqueda.' : 'Registrá el primer cliente del listado.' }}
                </p>
                @if(! $readOnly && ! $hayFiltros)
                    <a href="{{ route('clientes.create') }}" class="ucrud-btn ucrud-btn--primary mt-2">
                        <x-heroicon-o-plus style="width: 16px; height: 16px;" />
                        Nuevo cliente
                    </a>
                @endif
            </div>
        @else
            <div class="d-none d-lg-block ucrud-tablewrap">
                <table class="ucrud-table ucrud-table--sticky-actions">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Razón social</th>
                            <th>Localidad</th>
                            <th>CUIT</th>
                            <th>Cotizaciones</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($clientes as $i => $cliente)
                            @php
                                $codigo = trim($cliente->cli_codigo);
                                $totalCotizaciones = \App\Models\Ventas::where('coti_codigocli', 'LIKE', $codigo . '%')->count();
                            @endphp
                            <tr class="ucrud-animate-in" style="--i: {{ $i }}">
                                <td><span class="ucrud-code">{{ $codigo }}</span></td>
                                <td>{{ Str::limit($cliente->cli_razonsocial, 48) ?: '—' }}</td>
                                <td>{{ Str::limit($cliente->cli_localidad, 32) ?: '—' }}</td>
                                <td>{{ $cliente->cli_cuit ?: '—' }}</td>
                                <td>
                                    <a href="{{ route('ventas.index', ['cliente' => $codigo]) }}" class="ucrud-chip ucrud-chip--cyan text-decoration-none">
                                        {{ $totalCotizaciones }}
                                    </a>
                                </td>
                                <td>
                                    @if($cliente->cli_estado)
                                        <span class="ucrud-chip ucrud-chip--green">Activo</span>
                                    @else
                                        <span class="ucrud-chip ucrud-chip--rose">Inactivo</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="ucrud-actions">
                                        <a href="{{ route('clientes.edit', $codigo) }}"
                                           class="ucrud-iconbtn"
                                           title="{{ $readOnly ? 'Ver' : 'Editar' }}"
                                           aria-label="{{ $readOnly ? 'Ver cliente' : 'Editar cliente' }}">
                                            <x-heroicon-o-pencil style="width: 16px; height: 16px;" />
                                        </a>
                                        <a href="{{ route('ventas.index', ['cliente' => $codigo]) }}"
                                           class="ucrud-iconbtn"
                                           title="Ver cotizaciones"
                                           aria-label="Ver cotizaciones del cliente">
                                            <x-heroicon-o-document-text style="width: 16px; height: 16px;" />
                                        </a>
                                        @if(! $readOnly)
                                            <button type="button"
                                                    class="ucrud-iconbtn ucrud-iconbtn--danger js-delete-cliente"
                                                    data-codigo="{{ $codigo }}"
                                                    title="Eliminar"
                                                    aria-label="Eliminar cliente">
                                                <x-heroicon-o-trash style="width: 16px; height: 16px;" />
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-block d-lg-none">
                @foreach($clientes as $i => $cliente)
                    @php
                        $codigo = trim($cliente->cli_codigo);
                        $totalCotizaciones = \App\Models\Ventas::where('coti_codigocli', 'LIKE', $codigo . '%')->count();
                    @endphp
                    <a href="{{ route('clientes.edit', $codigo) }}" class="ucrud-card ucrud-animate-in" style="--i: {{ $i }}">
                        <span class="ucrud-card__body">
                            <span class="ucrud-user__name d-block">{{ Str::limit($cliente->cli_razonsocial, 56) ?: 'Sin razón social' }}</span>
                            <span class="ucrud-card__meta">
                                <span class="ucrud-code">{{ $codigo }}</span>
                                @if($cliente->cli_estado)
                                    <span class="ucrud-chip ucrud-chip--green">Activo</span>
                                @else
                                    <span class="ucrud-chip ucrud-chip--rose">Inactivo</span>
                                @endif
                                @if($cliente->cli_cuit)
                                    <span class="ucrud-user__meta">CUIT {{ $cliente->cli_cuit }}</span>
                                @endif
                            </span>
                            <span class="ucrud-user__meta d-block mt-1">
                                {{ Str::limit($cliente->cli_localidad, 40) ?: 'Sin localidad' }}
                                · {{ $totalCotizaciones }} cotizaciones
                            </span>
                        </span>
                        <span class="ucrud-card__chevron" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m9 6 6 6-6 6"/>
                            </svg>
                        </span>
                    </a>
                @endforeach
            </div>

            @if($clientes->hasPages())
                <div class="ucrud-pagination">
                    {{ $clientes->links() }}
                </div>
            @endif
        @endif
    </div>
</div>

@if(! $readOnly)
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('clientes.importar') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="importModalLabel">Importar clientes</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3">Seleccioná un archivo Excel (.xlsx, .xls) o CSV con los datos de los clientes.</p>
                        <div class="ucrud-field mb-0">
                            <label for="archivo">Archivo</label>
                            <input type="file" name="archivo" id="archivo" class="form-control" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <div class="alert alert-light border mt-3 mb-0 py-2 small">
                            <x-heroicon-o-information-circle style="width: 16px; height: 16px;" class="me-1" />
                            Usá la plantilla vacía o la de pendientes para asegurar el formato correcto.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="ucrud-btn ucrud-btn--ghost" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="ucrud-btn ucrud-btn--primary">Procesar importación</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-delete-cliente').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const codigo = btn.getAttribute('data-codigo');
            Swal.fire({
                title: '¿Eliminar este cliente?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33',
            }).then(function (result) {
                if (!result.isConfirmed) return;

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/clientes/' + encodeURIComponent(codigo);

                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = '{{ csrf_token() }}';
                form.appendChild(csrf);

                const method = document.createElement('input');
                method.type = 'hidden';
                method.name = '_method';
                method.value = 'DELETE';
                form.appendChild(method);

                document.body.appendChild(form);
                form.submit();
            });
        });
    });

    @if(session('success'))
        Swal.fire({ icon: 'success', title: '¡Éxito!', text: @json(session('success')), timer: 3000, showConfirmButton: false });
    @endif
    @if(session('error'))
        Swal.fire({ icon: 'error', title: 'Error', text: @json(session('error')) });
    @endif
    @if(session('warning'))
        Swal.fire({ icon: 'warning', title: 'Atención', text: @json(session('warning')) });
    @endif
});
</script>
@endpush
