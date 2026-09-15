@extends('layouts.app')

@section('title', 'Editar vehículo')

@section('content')
@include('partials.ucrud-styles')

<div class="container py-4 ucrud">
    @include('partials.ucrud-form-header', [
        'title' => 'Editar vehículo',
        'subtitle' => trim(($vehiculo->marca ?? '') . ' ' . ($vehiculo->modelo ?? '') . ' · ' . $vehiculo->patente),
        'backUrl' => route('vehiculos.index'),
    ])

    @if ($errors->any())
        <div class="ucrud-alert ucrud-alert--danger" role="alert">
            <span>
                <strong>Revisá los siguientes errores:</strong>
                <ul class="mb-0 mt-2 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </span>
        </div>
    @endif

    <div class="ucrud-panel">
        <div class="ucrud-form">
            <form action="{{ route('vehiculos.update', $vehiculo->id) }}" method="POST">
                @csrf
                @method('PUT')
                @include('vehiculos.partials.form', ['vehiculo' => $vehiculo])
                <div class="ucrud-form__actions">
                    <button type="submit" class="ucrud-btn ucrud-btn--primary">Guardar cambios</button>
                    <a href="{{ route('vehiculos.index') }}" class="ucrud-btn ucrud-btn--ghost">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
