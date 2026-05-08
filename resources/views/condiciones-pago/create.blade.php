@extends('layouts.app')

@section('title', 'Nueva condición de pago')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Nueva condición de pago</h1>
        <a href="{{ route('condiciones-pago.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('condiciones-pago.store') }}">
                @csrf

                @include('condiciones-pago._form')

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a href="{{ route('condiciones-pago.index') }}" class="btn btn-light">Cancelar</a>
                </div>
            </form>
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
</div>
@endsection

