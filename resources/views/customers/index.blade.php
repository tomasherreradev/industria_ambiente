@extends('layouts.customer')

@section('title', 'Panel de Cliente')

@section('content')
<div class="container py-4">
    <h1 class="mb-4">Bienvenido, {{ Auth::user()->usu_descripcion }}</h1>
    
    <div class="card shadow-sm">
        <div class="card-body">
            <p class="text-muted">Panel de cliente - Contenido pendiente</p>
        </div>
    </div>
</div>
@endsection

