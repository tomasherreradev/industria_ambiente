@extends('layouts.app')

@section('title', 'Editar condición de pago')

@section('content')
@include('partials.ucrud-styles')

<div class="container py-4 ucrud">
    @include('partials.ucrud-form-header', [
        'title' => 'Editar condición de pago',
        'subtitle' => $condicion->pag_codigo,
        'backUrl' => route('condiciones-pago.index'),
    ])

    <div class="ucrud-panel">
        <div class="ucrud-form">
            <form method="POST" action="{{ route('condiciones-pago.update', $condicion) }}" id="form-condicion-update">
                @csrf
                @method('PUT')
                @include('condiciones-pago._form', ['condicion' => $condicion])
            </form>

            <div class="ucrud-form__actions">
                <button type="submit" form="form-condicion-update" class="ucrud-btn ucrud-btn--primary">Actualizar</button>
                <a href="{{ route('condiciones-pago.index') }}" class="ucrud-btn ucrud-btn--ghost">Cancelar</a>
                <form action="{{ route('condiciones-pago.destroy', $condicion) }}" method="POST" class="js-delete-form ms-auto">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="ucrud-btn ucrud-btn--ghost" style="color:#d0453c;border-color:#f0c4c1;">Eliminar condición</button>
                </form>
            </div>
        </div>
    </div>

    @if ($errors->any())
        @push('scripts')
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            Swal.fire({ icon: 'error', title: 'Corrige los errores', html: `{!! implode('<br>', $errors->all()) !!}` });
        });
        </script>
        @endpush
    @endif
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
                if (result.isConfirmed) form.submit();
            });
        });
    });
});
</script>
@endpush
