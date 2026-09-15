@extends('layouts.app')

@section('title', 'Editar grupo de variables')

@section('content')
@include('partials.ucrud-styles')

<div class="container py-4 ucrud">
    @include('partials.ucrud-form-header', [
        'title' => 'Editar grupo',
        'subtitle' => $groupName,
        'backUrl' => route('variables-requeridas.index'),
    ])

    <div class="ucrud-panel">
        <div class="ucrud-form">
            <form action="{{ route('variables-requeridas.update-group', urlencode($groupName)) }}" method="POST">
                @csrf
                @method('PUT')

                <input type="hidden" name="group_name" value="{{ $groupName }}">
                <input type="hidden" name="cotio_id" value="{{ $cotioId }}">

                <div class="ucrud-tablewrap">
                    <table class="ucrud-table ucrud-table--sticky-actions">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Obligatorio</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($variables as $variable)
                                <tr>
                                    <td><span class="ucrud-code">{{ $variable->id }}</span></td>
                                    <td>
                                        <input type="hidden" name="variables[{{ $loop->index }}][id]" value="{{ $variable->id }}">
                                        <input type="text" name="variables[{{ $loop->index }}][nombre]"
                                               value="{{ old('variables.'.$loop->index.'.nombre', $variable->nombre) }}"
                                               class="form-control form-control-sm" required>
                                    </td>
                                    <td>
                                        <select name="variables[{{ $loop->index }}][obligatorio]" class="form-select form-select-sm" required>
                                            <option value="1" @selected($variable->obligatorio)>Sí</option>
                                            <option value="0" @selected(! $variable->obligatorio)>No</option>
                                        </select>
                                    </td>
                                    <td><span class="ucrud-dim">Existente</span></td>
                                </tr>
                            @endforeach

                            <tr class="new-variable-row">
                                <td><span class="ucrud-chip ucrud-chip--cyan">Nueva</span></td>
                                <td>
                                    <input type="text" name="new_variables[0][nombre]"
                                           class="form-control form-control-sm new-variable-nombre"
                                           placeholder="Nombre de la nueva variable">
                                </td>
                                <td>
                                    <select name="new_variables[0][obligatorio]" class="form-select form-select-sm">
                                        <option value="1">Sí</option>
                                        <option value="0">No</option>
                                    </select>
                                </td>
                                <td>
                                    <button type="button" class="ucrud-iconbtn add-variable" title="Agregar fila">
                                        <x-heroicon-o-plus style="width: 16px; height: 16px;" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="ucrud-form__actions">
                    <button type="submit" class="ucrud-btn ucrud-btn--primary">Guardar cambios</button>
                    <a href="{{ route('variables-requeridas.index') }}" class="ucrud-btn ucrud-btn--ghost">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    let rowCount = 1;

    document.querySelector('.add-variable').addEventListener('click', function () {
        const tbody = document.querySelector('.ucrud-table tbody');
        const lastRow = document.querySelector('.new-variable-row:last-child');
        const newRow = document.createElement('tr');
        newRow.className = 'new-variable-row';
        newRow.innerHTML = `
            <td><span class="ucrud-chip ucrud-chip--cyan">Nueva</span></td>
            <td>
                <input type="text" name="new_variables[${rowCount}][nombre]"
                       class="form-control form-control-sm new-variable-nombre"
                       placeholder="Nombre de la nueva variable">
            </td>
            <td>
                <select name="new_variables[${rowCount}][obligatorio]" class="form-select form-select-sm">
                    <option value="1">Sí</option>
                    <option value="0">No</option>
                </select>
            </td>
            <td>
                <button type="button" class="ucrud-iconbtn ucrud-iconbtn--danger remove-variable" title="Quitar fila">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" width="16" height="16"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M18.5 6 17.6 20H6.4L5.5 6"/></svg>
                </button>
            </td>
        `;
        tbody.insertBefore(newRow, lastRow);
        rowCount++;
    });

    document.querySelector('.ucrud-table tbody').addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-variable');
        if (btn) btn.closest('tr').remove();
    });

    document.querySelector('form').addEventListener('submit', function (e) {
        let hasEmptyFields = false;
        document.querySelectorAll('.new-variable-nombre').forEach(function (input) {
            if (input.value.trim() === '') {
                input.classList.add('is-invalid');
                hasEmptyFields = true;
            } else {
                input.classList.remove('is-invalid');
            }
        });

        if (hasEmptyFields) {
            e.preventDefault();
            alert('Completá todos los campos de nuevas variables o eliminá las filas vacías.');
        }
    });
});
</script>
@endpush
