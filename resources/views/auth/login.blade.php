@extends('layouts.login')

@section('content')
<div class="container mt-5">
    <div class="bg-white p-4 rounded-3 mx-auto d-flex flex-column justify-content-between" style="max-width: 430px; min-height: 370px">
        <div class="text-center mb-3">
            <img src="{{ asset('/assets/img/logo.png') }}" alt="Logo" width="100">
            <p class="my-3" style="color: #1a6fa3; font-weight: bold; font-size: 18px;">Iniciar Sesión</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger py-2 px-3 mb-3" role="alert" style="font-size: 0.92rem;">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div class="fw-semibold">No se pudo iniciar sesión</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
                <div class="mt-2" style="max-height: 86px; overflow: auto;">
                    <ul class="mb-0 ps-3" style="line-height: 1.2;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf
            <div class="mb-3">
                <label for="usu_codigo" class="form-label">Usuario</label>
                <input type="text" name="usu_codigo" id="usu_codigo" class="form-control" value="{{ old('usu_codigo') }}" autocomplete="username" required>
            </div>
            <div class="mb-3">
                <label for="usu_clave" class="form-label">Contraseña</label>
                <input type="password" name="usu_clave" id="usu_clave" class="form-control" autocomplete="current-password" required>
            </div>
            <div class="d-flex justify-content-center">
                <button type="submit" class="btn btn-secondary my-3">Entrar</button>
            </div>
        </form>
    </div>
</div>
@endsection
