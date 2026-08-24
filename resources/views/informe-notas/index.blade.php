@extends('layouts.app')

@section('title', 'Notas para informes')

@section('content')
<div class="container py-3 py-md-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1">Notas para informes</h1>
            <p class="text-muted small mb-0">Plantillas reutilizables para agregar al PDF del protocolo.</p>
        </div>
        <a href="{{ route('informes.index') }}" class="btn btn-outline-secondary">
            <x-heroicon-o-arrow-left style="width: 16px; height: 16px;" class="me-1" />
            Volver a informes
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h2 class="h6 mb-0">Nueva nota</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('informes.notas.store') }}" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label for="titulo" class="form-label">Título (opcional)</label>
                    <input type="text" class="form-control @error('titulo') is-invalid @enderror" id="titulo" name="titulo"
                           value="{{ old('titulo') }}" maxlength="255" placeholder="Ej: Límite de detección">
                    @error('titulo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-8">
                    <label for="contenido" class="form-label">Contenido <span class="text-danger">*</span></label>
                    <textarea class="form-control @error('contenido') is-invalid @enderror" id="contenido" name="contenido"
                              rows="4" required placeholder="Texto que aparecerá en el informe...">{{ old('contenido') }}</textarea>
                    @error('contenido')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Podés usar <code>**texto**</code> para negrita, igual que en el editor del protocolo.</small>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="activa" id="activa_nueva" value="1" checked>
                        <label class="form-check-label" for="activa_nueva">Activa (disponible al armar informes)</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <x-heroicon-o-plus style="width: 16px; height: 16px;" class="me-1" />
                        Guardar nota
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('informes.notas.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="q">Buscar en notas guardadas</label>
                    <input type="text" name="q" id="q" class="form-control" value="{{ $q }}" placeholder="Título o descripción">
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">Buscar</button>
                </div>
                @if($q !== '')
                    <div class="col-auto">
                        <a class="btn btn-light" href="{{ route('informes.notas.index') }}">Limpiar</a>
                    </div>
                @endif
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;">Orden</th>
                            <th style="width: 220px;">Título</th>
                            <th>Contenido</th>
                            <th style="width: 90px;" class="text-center">Activa</th>
                            <th style="width: 160px;" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($notas as $nota)
                            <tr>
                                <td class="text-muted">{{ $nota->orden }}</td>
                                <td>{{ $nota->titulo ?: '—' }}</td>
                                <td>
                                    <div class="small text-muted text-truncate" style="max-width: 480px;" title="{{ $nota->contenido }}">
                                        {{ \Illuminate\Support\Str::limit($nota->contenido, 120) }}
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if($nota->activa)
                                        <span class="badge bg-success">Sí</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('informes.notas.edit', $nota) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                    <form action="{{ route('informes.notas.destroy', $nota) }}" method="POST" class="d-inline js-delete-nota-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No hay notas guardadas. Creá la primera con el formulario de arriba.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($notas->hasPages())
            <div class="card-footer">
                {{ $notas->links() }}
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.js-delete-nota-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: '¿Eliminar esta nota?',
                text: 'No se podrá recuperar.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33'
            }).then(function(result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
});
</script>
@endsection
