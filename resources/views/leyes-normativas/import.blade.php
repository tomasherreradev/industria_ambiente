@extends('layouts.app')

@section('title', 'Importar leyes y normativas')

@section('content')
@include('partials.ucrud-styles')

<div class="container py-4 ucrud">
    @include('partials.ucrud-form-header', [
        'title' => 'Importar leyes y normativas',
        'subtitle' => 'Carga masiva desde archivo Excel.',
        'backUrl' => route('leyes-normativas.index'),
    ])

    @if(session('success'))
        <div class="ucrud-alert ucrud-alert--success" role="status"><span>{{ session('success') }}</span></div>
    @endif
    @if(session('error'))
        <div class="ucrud-alert ucrud-alert--danger" role="alert"><span>{{ session('error') }}</span></div>
    @endif
    @if(session('warning'))
        <div class="ucrud-alert ucrud-alert--warning" role="alert"><span>{{ session('warning') }}</span></div>
    @endif
    @if($errors->any())
        <div class="ucrud-alert ucrud-alert--danger" role="alert">
            <ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="ucrud-panel">
                <div class="ucrud-form">
                    <div class="ucrud-alert ucrud-alert--success" role="note" style="background:#e7f8fb;border-color:#c3ebf2;color:#0f7f92;">
                        <span>
                            <strong>Instrucciones</strong>
                            <ol class="mb-0 mt-2 ps-3">
                                <li>Descargá la plantilla Excel con el botón inferior.</li>
                                <li>La plantilla incluye: Datos, Leyes existentes, Métodos y Matrices.</li>
                                <li>Completá la hoja <strong>Datos</strong> (no cambies su nombre).</li>
                                <li>Para Matriz y Método podés usar código o nombre.</li>
                                <li>Si no especificás Matriz ni Método, la ley aplica a todos los ítems con ese analito.</li>
                            </ol>
                        </span>
                    </div>

                    <form method="POST" action="{{ route('leyes-normativas.import.process') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="archivo" class="form-label">Archivo Excel <span class="text-danger">*</span></label>
                            <input type="file"
                                   class="form-control @error('archivo') is-invalid @enderror"
                                   id="archivo"
                                   name="archivo"
                                   accept=".xlsx,.xls"
                                   required>
                            @error('archivo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Formatos: .xlsx, .xls (máximo 10MB)</small>
                        </div>

                        <div class="ucrud-form__actions">
                            <a href="{{ route('leyes-normativas.export.template') }}" class="ucrud-btn ucrud-btn--outline-success">
                                <x-heroicon-o-arrow-down-tray style="width: 16px; height: 16px;" />
                                Descargar plantilla
                            </a>
                            <span class="flex-grow-1"></span>
                            <a href="{{ route('leyes-normativas.index') }}" class="ucrud-btn ucrud-btn--ghost">Cancelar</a>
                            <button type="submit" class="ucrud-btn ucrud-btn--primary">
                                <x-heroicon-o-arrow-up-tray style="width: 16px; height: 16px;" />
                                Importar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="ucrud-panel">
                <div class="ucrud-detail-block">
                    <h2 class="ucrud-detail-block__title">Estructura de la plantilla</h2>
                    <div class="ucrud-tablewrap">
                        <table class="ucrud-table">
                            <thead>
                                <tr>
                                    <th>Columna</th>
                                    <th>Req.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Analito (cotio_descripcion)</td>
                                    <td><span class="ucrud-chip ucrud-chip--rose">Sí</span></td>
                                </tr>
                                <tr>
                                    <td>Matriz (opcional)</td>
                                    <td><span class="ucrud-chip ucrud-chip--muted">No</span></td>
                                </tr>
                                <tr>
                                    <td>Método (opcional)</td>
                                    <td><span class="ucrud-chip ucrud-chip--muted">No</span></td>
                                </tr>
                                <tr>
                                    <td>Nombre de la ley</td>
                                    <td><span class="ucrud-chip ucrud-chip--rose">Sí</span></td>
                                </tr>
                                <tr>
                                    <td>Unidad de medida</td>
                                    <td><span class="ucrud-chip ucrud-chip--amber">Rec.</span></td>
                                </tr>
                                <tr>
                                    <td>Valor límite</td>
                                    <td><span class="ucrud-chip ucrud-chip--muted">No</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
