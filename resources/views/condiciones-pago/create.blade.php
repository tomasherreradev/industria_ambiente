@extends('layouts.app')

@section('title', 'Nueva condición de pago')

@section('content')
@include('partials.ucrud-styles')

<div class="container py-4 ucrud">
    @include('partials.ucrud-form-header', [
        'title' => 'Nueva condición de pago',
        'subtitle' => 'Alta de plazos, descuentos e intereses.',
        'backUrl' => route('condiciones-pago.index'),
    ])

    <div class="ucrud-panel">
        <div class="ucrud-form">
            <form method="POST" action="{{ route('condiciones-pago.store') }}">
                @csrf
                @include('condiciones-pago._form')
                <div class="ucrud-form__actions">
                    <button type="submit" class="ucrud-btn ucrud-btn--primary">Guardar</button>
                    <a href="{{ route('condiciones-pago.index') }}" class="ucrud-btn ucrud-btn--ghost">Cancelar</a>
                </div>
            </form>
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
