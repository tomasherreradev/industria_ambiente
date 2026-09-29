@extends('layouts.app')

@section('title', 'Nuevo usuario')

@php
    $esAdmin = (int) (Auth::user()->usu_nivel ?? 0) >= 900;
@endphp

@section('content')
@include('partials.ucrud-styles')

<div class="container py-4 ucrud ucrud-perfil" data-ucrud-root>
    <div class="mb-3">
        <a href="{{ route('users.showUsers') }}" class="ucrud-btn ucrud-btn--ghost ucrud-btn--sm">
            <x-heroicon-o-arrow-left style="width: 16px; height: 16px;" />
            Volver al listado
        </a>
    </div>

    @if($errors->any())
        <div class="ucrud-alert ucrud-alert--danger mb-3" role="alert">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="ucrud-panel mb-3">
        <div class="ucrud-perfil__hero">
            <div class="ucrud-perfil__avatar" aria-hidden="true">+</div>
            <div class="ucrud-perfil__identity">
                <h1 class="ucrud-perfil__name">Nuevo usuario</h1>
                <div class="ucrud-perfil__meta">
                    <span>Completá los datos y asigná rol y permisos.</span>
                    @if($esAdmin)
                        <span class="ucrud-role-chip">Administración</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 align-items-start">
        <div class="col-lg-8">
            <div class="ucrud-panel">
                @include('users.partials.formulario-usuario', [
                    'modo' => 'create',
                    'esAdmin' => $esAdmin,
                    'sectores' => $sectores,
                ])
            </div>
        </div>
        <div class="col-lg-4">
            @include('users.partials.sidebar-usuario-create')
        </div>
    </div>
</div>

@include('users.partials.formulario-usuario-scripts', ['formId' => 'form-crear-usuario'])
@endsection
