@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h1 class="mb-4">Nuevo Laboratorio</h1>
    <form action="{{ route('sectores.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="usu_descripcion" class="form-label">Nombre</label>
            <input type="text" name="usu_descripcion" id="usu_descripcion" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="usu_codigo" class="form-label">Código</label>
            <input type="text" name="usu_codigo" id="usu_codigo" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
</div>
@endsection
