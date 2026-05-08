@extends('layouts.app')

@section('title', 'Condiciones de pago')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Condiciones de pago</h1>
        <a href="{{ route('condiciones-pago.create') }}" class="btn btn-primary">Nueva</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('condiciones-pago.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-6">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="q" class="form-control" value="{{ $q }}" placeholder="Código o descripción">
                </div>
                <div class="col-12 col-md-auto">
                    <button class="btn btn-outline-primary w-100" type="submit">Filtrar</button>
                </div>
                <div class="col-12 col-md-auto">
                    <a class="btn btn-light w-100" href="{{ route('condiciones-pago.index') }}">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 160px;">Código</th>
                            <th>Descripción</th>
                            <th style="width: 90px;" class="text-end">Días</th>
                            <th style="width: 110px;" class="text-center">Activa</th>
                            <th style="width: 160px;" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($condiciones as $c)
                            <tr>
                                <td class="fw-semibold">{{ $c->pag_codigo }}</td>
                                <td>{{ $c->pag_descripcion }}</td>
                                <td class="text-end">{{ $c->pag_dias ?? '—' }}</td>
                                <td class="text-center">
                                    @if($c->pag_estado)
                                        <span class="badge bg-success">Sí</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('condiciones-pago.edit', $c) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                    <form action="{{ route('condiciones-pago.destroy', $c) }}" method="POST" class="d-inline js-delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No hay condiciones de pago.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($condiciones->hasPages())
        <div class="card-footer">
            {{ $condiciones->links() }}
        </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.js-delete-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: '¿Eliminar condición de pago?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});
</script>
@endsection

