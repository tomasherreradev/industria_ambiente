@extends('layouts.app')

@section('title', 'Editar inventario de muestreo')

@section('content')
@include('partials.ucrud-styles')

<div class="container py-4 ucrud">
    @include('partials.ucrud-form-header', [
        'title' => 'Editar inventario de muestreo',
        'subtitle' => $inventario->equipamiento,
        'backUrl' => route('inventarios-muestreo.index'),
    ])

    @if(session('success'))
        <div class="ucrud-alert ucrud-alert--success" role="status">
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div class="ucrud-panel">
        <div class="ucrud-form">
            <form action="{{ url('/inventarios-muestreo/' . $inventario->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('inventarios-muestreo.partials.form', ['inventario' => $inventario])
                <div class="ucrud-form__actions">
                    <button type="submit" class="ucrud-btn ucrud-btn--primary">Guardar cambios</button>
                    <a href="{{ route('inventarios-muestreo.index') }}" class="ucrud-btn ucrud-btn--ghost">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
