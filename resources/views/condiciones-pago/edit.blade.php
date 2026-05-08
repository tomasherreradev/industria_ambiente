@extends('layouts.app')

@section('title', 'Editar condición de pago')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Editar condición de pago</h1>
        <a href="{{ route('condiciones-pago.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('condiciones-pago.update', $condicion) }}">
                @csrf
                @method('PUT')

                @include('condiciones-pago._form', ['condicion' => $condicion])

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Actualizar</button>
                    <a href="{{ route('condiciones-pago.index') }}" class="btn btn-light">Cancelar</a>
                </div>
            </form>

            <div class="mt-3">
                <form action="{{ route('condiciones-pago.destroy', $condicion) }}" method="POST" class="js-delete-form d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>

    @if ($errors->any())
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: 'Corrige los errores',
            html: `{!! implode('<br>', $errors->all()) !!}`
        });
    });
    </script>
    @endif

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('.js-delete-form');
        if (!form) return;
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
    </script>
</div>
@endsection

