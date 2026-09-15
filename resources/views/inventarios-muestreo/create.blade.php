@extends('layouts.app')

@section('title', 'Crear inventario de muestreo')

@section('content')
@include('partials.ucrud-styles')

<div class="container py-4 ucrud">
    @include('partials.ucrud-form-header', [
        'title' => 'Crear inventario de muestreo',
        'subtitle' => 'Alta de equipamiento de campo.',
        'backUrl' => route('inventarios-muestreo.index'),
    ])

    <div class="ucrud-panel">
        <div class="ucrud-form">
            <form action="{{ route('inventarios-muestreo.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @include('inventarios-muestreo.partials.form')
                <div class="ucrud-form__actions">
                    <button type="submit" class="ucrud-btn ucrud-btn--primary">Guardar</button>
                    <a href="{{ route('inventarios-muestreo.index') }}" class="ucrud-btn ucrud-btn--ghost">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
