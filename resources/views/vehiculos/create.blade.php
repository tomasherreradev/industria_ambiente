@extends('layouts.app')

@section('title', 'Nuevo vehículo')

@section('content')
@include('partials.ucrud-styles')

<div class="container py-4 ucrud">
    @include('partials.ucrud-form-header', [
        'title' => 'Nuevo vehículo',
        'subtitle' => 'Alta de unidad para la flota de muestreo.',
        'backUrl' => route('vehiculos.index'),
    ])

    <div class="ucrud-panel">
        <div class="ucrud-form">
            <form action="{{ route('vehiculos.store') }}" method="POST">
                @csrf
                @include('vehiculos.partials.form')
                <div class="ucrud-form__actions">
                    <button type="submit" class="ucrud-btn ucrud-btn--primary">Guardar</button>
                    <a href="{{ route('vehiculos.index') }}" class="ucrud-btn ucrud-btn--ghost">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
