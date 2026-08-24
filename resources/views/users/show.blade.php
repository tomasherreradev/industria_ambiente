@extends('layouts.app')

@section('title', 'Editar usuario')

@php
    $esAdmin = (int) (Auth::user()->usu_nivel ?? 0) >= 900;
    $opcionesRol = [
        '' => 'Sin rol',
        'laboratorio' => 'Analista',
        'muestreador' => 'Muestreador',
        'coordinador_lab' => 'Coordinador Laboratorio',
        'coordinador_muestreo' => 'Coordinador Muestreo',
        'facturador' => 'Facturador',
        'ventas' => 'Vendedor',
        'firmador' => 'Firmador',
        'coordinador_consul' => 'Coordinador Consultoría',
        'coordinador_mediciones' => 'Coordinador Mediciones',
        'asp' => 'ASP',
        'clarke_fire' => 'Clarke Fire',
        'cliente' => 'Usuario Cliente',
    ];
@endphp

@section('content')
<div class="container py-4">
    @php
        $puedeEditarUsuarios = $puedeEditarUsuarios ?? false;
        $puedeEliminarUsuarios = $puedeEliminarUsuarios ?? false;
    @endphp
    <h1 class="mb-4">Editando: {{ $usuario->usu_descripcion }}</h1>

    <div class="row">
        <div class="col-lg-8">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white">
                    <strong>Formulario de edición</strong>
                    {{-- @if($esAdmin)
                        <span class="badge bg-warning text-dark ms-2">Administración</span>
                    @endif --}}
                </div>
                <div class="card-body">
                    <form id="form-editar-usuario" action="{{ url('/users/' . $usuario->usu_codigo) }}" method="POST" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="usu_codigo_display" class="form-label">Código (usuario de ingreso)</label>
                            <input type="text" id="usu_codigo_display" class="form-control" value="{{ $usuario->usu_codigo }}" readonly disabled>
                            <div class="form-text">La clave primaria no se modifica desde aquí.</div>
                        </div>

                        <div class="mb-3">
                            <label for="usu_descripcion" class="form-label">Nombre</label>
                            <input type="text" name="usu_descripcion" id="usu_descripcion" class="form-control" value="{{ old('usu_descripcion', $usuario->usu_descripcion) }}" required @readonly(!$puedeEditarUsuarios) @disabled(!$puedeEditarUsuarios)>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="dni" class="form-label">DNI / documento</label>
                                <input type="text" name="dni" id="dni" class="form-control" value="{{ old('dni', $usuario->dni) }}" @readonly(!$puedeEditarUsuarios) @disabled(!$puedeEditarUsuarios)>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Correo</label>
                                <input type="text" name="email" id="email" class="form-control" value="{{ old('email', $usuario->email) }}" @readonly(!$puedeEditarUsuarios) @disabled(!$puedeEditarUsuarios)>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="departamento" class="form-label">Departamento / área</label>
                            <input type="text" name="departamento" id="departamento" class="form-control" value="{{ old('departamento', $usuario->departamento) }}" @readonly(!$puedeEditarUsuarios) @disabled(!$puedeEditarUsuarios)>
                        </div>

                        <div class="mb-3">
                            <label for="sector_trabajo" class="form-label">Sector de trabajo (texto libre)</label>
                            <input type="text" name="sector_trabajo" id="sector_trabajo" class="form-control" value="{{ old('sector_trabajo', $usuario->sector_trabajo) }}" placeholder="p. ej. química, microbiología" @readonly(!$puedeEditarUsuarios) @disabled(!$puedeEditarUsuarios)>
                        </div>

                        @php
                            $estadoVal = (string) old('usu_estado', $usuario->usu_estado ? '1' : '0');
                        @endphp
                        <div class="mb-3">
                            <label for="usu_estado" class="form-label">Estado</label>
                            <select name="usu_estado" id="usu_estado" class="form-select" required @disabled(!$puedeEditarUsuarios)>
                                <option value="1" @selected($estadoVal === '1')>Activo</option>
                                <option value="0" @selected($estadoVal === '0')>Inactivo</option>
                            </select>
                        </div>

                        @if($esAdmin && $puedeEditarUsuarios)
                            <div class="mb-3">
                                <label for="usu_nivel" class="form-label">Nivel de acceso (≥900 = administrador)</label>
                                <input type="number" name="usu_nivel" id="usu_nivel" class="form-control" min="0" max="9999" value="{{ old('usu_nivel', $usuario->usu_nivel) }}">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Nueva contraseña</label>
                                    <input type="password" name="password" id="password" class="form-control" autocomplete="new-password" placeholder="Dejar vacío para no cambiar">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" autocomplete="new-password">
                                </div>
                            </div>

                            {{-- <div class="mb-3 form-check">
                                <input type="checkbox" name="limpiar_sesion" id="limpiar_sesion" class="form-check-input" value="1" {{ old('limpiar_sesion') ? 'checked' : '' }}>
                                <label for="limpiar_sesion" class="form-check-label">Cerrar otras sesiones (invalidar inicio de sesión en otros dispositivos)</label>
                            </div> --}}
                        @endif

                        <div class="mb-3">
                            <label for="rol" class="form-label">Rol principal</label>
                            <select name="rol" id="rol" class="form-select" @disabled(!$puedeEditarUsuarios)>
                                @foreach($opcionesRol as $val => $etq)
                                    <option value="{{ $val }}" @selected((string) old('rol', $usuario->rol ?? '') === (string) $val)>{{ $etq }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($esAdmin && $puedeEditarUsuarios)
                            <div class="mb-3">
                                <label for="roles_adicionales" class="form-label">Roles adicionales (varios; Ctrl+clic)</label>
                                <select name="roles_adicionales[]" id="roles_adicionales" class="form-select" multiple size="8">
                                    @foreach($opcionesRol as $val => $etq)
                                        @if($val === '') @continue @endif
                                        @php
                                            $isSaved = in_array($val, $rolesAdicionales, true);
                                            $isSelected = in_array($val, old('roles_adicionales', $rolesAdicionales), true);
                                        @endphp
                                        <option value="{{ $val }}"
                                            data-original="{{ $isSaved ? 'true' : 'false' }}"
                                            data-clean-text="{{ $etq }}"
                                            @selected($isSelected)>
                                            {{ $etq }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">No hace falta repetir el rol principal; se ignorará si lo marcás.</div>
                            </div>
                        @endif

                        @php
                            $mostrarAdminLab = (string) old('rol', $usuario->rol ?? '') === 'coordinador_lab'
                                || in_array('coordinador_lab', (array) old('roles_adicionales', $rolesAdicionales ?? []), true);
                            $coordinadorLabEnAdicionalesGuardados = in_array('coordinador_lab', $rolesAdicionales ?? [], true);
                            $mostrarBandejaSoloInformes = in_array((string) old('rol', $usuario->rol ?? ''), ['coordinador_lab', 'coordinador_mediciones'], true)
                                || ! empty(array_intersect(
                                    ['coordinador_lab', 'coordinador_mediciones'],
                                    (array) old('roles_adicionales', $rolesAdicionales ?? [])
                                ));
                            $bandejaSoloInformesEnAdicionalesGuardados = ! empty(array_intersect(
                                ['coordinador_lab', 'coordinador_mediciones'],
                                $rolesAdicionales ?? []
                            ));
                        @endphp
                        <div class="mb-3"
                             id="wrapper_admin_lab"
                             style="display: {{ $mostrarAdminLab ? 'block' : 'none' }};"
                             data-coordinador-lab-adicional="{{ $coordinadorLabEnAdicionalesGuardados ? '1' : '0' }}">
                            <div class="form-check">
                                <input type="checkbox"
                                       name="admin_lab"
                                       id="admin_lab"
                                       class="form-check-input"
                                       value="1"
                                       @checked((bool) old('admin_lab', $usuario->admin_lab ?? false))
                                       @disabled(!$puedeEditarUsuarios)>
                                <label for="admin_lab" class="form-check-label">Administrador de laboratorio</label>
                                <div class="form-text">Puede ver quién aprobó los informes en órdenes de trabajo.</div>
                            </div>
                        </div>

                        <div class="mb-3"
                             id="wrapper_bandeja_solo_informes"
                             style="display: {{ $mostrarBandejaSoloInformes ? 'block' : 'none' }};"
                             data-coordinador-lab-o-mediciones-adicional="{{ $bandejaSoloInformesEnAdicionalesGuardados ? '1' : '0' }}">
                            <div class="form-check">
                                <input type="checkbox"
                                       name="bandeja_solo_informes"
                                       id="bandeja_solo_informes"
                                       class="form-check-input"
                                       value="1"
                                       @checked((bool) old('bandeja_solo_informes', $usuario->bandeja_solo_informes ?? false))
                                       @disabled(!$puedeEditarUsuarios)>
                                <label for="bandeja_solo_informes" class="form-check-label">Bandeja de trabajo limitada (solo informes)</label>
                                <div class="form-text">Oculta Dashboard Lab, Cotizaciones, Órdenes de Trabajo, Inventario Lab y Mediciones de Campo en el menú lateral.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox"
                                       name="puede_cargar_items"
                                       id="puede_cargar_items"
                                       class="form-check-input"
                                       value="1"
                                       @checked((bool) old('puede_cargar_items', $usuario->puede_cargar_items ?? false))
                                       @disabled(!$puedeEditarUsuarios)>
                                <label for="puede_cargar_items" class="form-check-label">Puede cargar determinaciones (items)</label>
                                <div class="form-text">Habilita el acceso al módulo de determinaciones: alta, edición, importación y exportación.</div>
                            </div>
                        </div>

                        <div class="mb-3" id="wrapper_sector_unico">
                            <label for="sector_codigo" class="form-label">Laboratorio (sector)</label>
                            <select name="sector_codigo" id="sector_codigo" class="form-select" @disabled(!$puedeEditarUsuarios)>
                                <option value="">Sin sector</option>
                                @foreach($sectores as $sector)
                                    @php
                                        $isSaved = trim($sector->usu_codigo) == trim($usuario->sector_codigo ?? '');
                                        $isSelected = trim($sector->usu_codigo) == trim($usuario->sector_codigo ?? '') || trim(old('sector_codigo', '')) == trim($sector->usu_codigo);
                                    @endphp
                                    <option value="{{ $sector->usu_codigo }}" 
                                        @selected($isSelected)
                                        style="{{ $isSaved ? 'font-weight: bold; color: #2e7d32; background-color: #e8f5e9;' : '' }}"
                                        class="{{ $isSaved ? 'fw-bold text-success' : '' }}">
                                        {{ $isSaved ? '✓ ' : '' }}{{ $sector->usu_descripcion }} ({{ $sector->usu_codigo }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3" id="wrapper_sectores_multiples">
                            <label for="sectores_codigos" class="form-label">Laboratorios / Sectores (múltiples; Ctrl+clic)</label>
                            <select name="sectores_codigos[]" id="sectores_codigos" class="form-select" multiple size="6" @disabled(!$puedeEditarUsuarios)>
                                @foreach($sectores as $sector)
                                    @php
                                        $trimmedSectorCodigo = trim($sector->usu_codigo);
                                        $trimmedSectoresAsignados = array_map('trim', $sectoresAsignados ?? []);
                                        $isSaved = in_array($trimmedSectorCodigo, $trimmedSectoresAsignados, true);
                                        
                                        $trimmedOldSectores = array_map('trim', (array) old('sectores_codigos', $sectoresAsignados ?? []));
                                        $isSelected = in_array($trimmedSectorCodigo, $trimmedOldSectores, true);
                                    @endphp
                                    <option value="{{ $sector->usu_codigo }}"
                                        data-original="{{ $isSaved ? 'true' : 'false' }}"
                                        data-clean-text="{{ $sector->usu_descripcion }} ({{ $sector->usu_codigo }})"
                                        @selected($isSelected)>
                                        {{ $sector->usu_descripcion }} ({{ $sector->usu_codigo }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Mantené presionado Ctrl (o Cmd en Mac) para seleccionar más de un sector.</div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <a href="{{ url('/users') }}" class="btn btn-secondary">← Volver al listado</a>
                            <div class="d-flex gap-2">
                                @if($puedeEliminarUsuarios && trim((string) Auth::user()->usu_codigo) !== trim((string) $usuario->usu_codigo))
                                    <button type="submit" form="form-eliminar-usuario" class="btn btn-outline-danger">Eliminar usuario</button>
                                @endif
                                @if($puedeEditarUsuarios)
                                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                                @endif
                            </div>
                        </div>
                    </form>

                    {{-- Fuera del formulario de edición: anidarlo haría que _method=DELETE viaje al guardar. --}}
                    @if($puedeEliminarUsuarios && trim((string) Auth::user()->usu_codigo) !== trim((string) $usuario->usu_codigo))
                        <form id="form-eliminar-usuario"
                              action="{{ route('users.destroy', ['usu_codigo' => $usuario->usu_codigo]) }}"
                              method="POST"
                              class="d-none"
                              onsubmit="return confirm('¿Eliminar definitivamente a {{ $usuario->usu_descripcion }} ({{ trim($usuario->usu_codigo) }})? Esta acción no se puede deshacer.');">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('form-editar-usuario');
        const rolSelect = document.getElementById('rol');
        const sectorSelect = document.getElementById('sector_codigo');
        const rolesMultiSelect = document.getElementById('roles_adicionales');
        const sectoresMultiSelect = document.getElementById('sectores_codigos');
        const wrapperSectorUnico = document.getElementById('wrapper_sector_unico');
        const wrapperSectoresMultiples = document.getElementById('wrapper_sectores_multiples');
        const wrapperAdminLab = document.getElementById('wrapper_admin_lab');
        const adminLabCheckbox = document.getElementById('admin_lab');
        const wrapperBandejaSoloInformes = document.getElementById('wrapper_bandeja_solo_informes');
        const bandejaSoloInformesCheckbox = document.getElementById('bandeja_solo_informes');

        if (!form || !rolSelect) return;

        function tieneCoordinadorLabOMediciones() {
            if (['coordinador_lab', 'coordinador_mediciones'].includes(rolSelect.value)) {
                return true;
            }
            if (rolesMultiSelect) {
                return Array.from(rolesMultiSelect.selectedOptions).some(option =>
                    ['coordinador_lab', 'coordinador_mediciones'].includes(option.value)
                );
            }
            return wrapperBandejaSoloInformes?.dataset.coordinadorLabOMedicionesAdicional === '1';
        }

        function toggleBandejaSoloInformes() {
            if (!wrapperBandejaSoloInformes) return;
            if (tieneCoordinadorLabOMediciones()) {
                wrapperBandejaSoloInformes.style.display = 'block';
            } else {
                wrapperBandejaSoloInformes.style.display = 'none';
                if (bandejaSoloInformesCheckbox) bandejaSoloInformesCheckbox.checked = false;
            }
        }

        function tieneCoordinadorLab() {
            if (rolSelect.value === 'coordinador_lab') {
                return true;
            }
            if (rolesMultiSelect) {
                return Array.from(rolesMultiSelect.selectedOptions).some(option => option.value === 'coordinador_lab');
            }
            return wrapperAdminLab?.dataset.coordinadorLabAdicional === '1';
        }

        function toggleAdminLab() {
            if (!wrapperAdminLab) return;
            if (tieneCoordinadorLab()) {
                wrapperAdminLab.style.display = 'block';
            } else {
                wrapperAdminLab.style.display = 'none';
                if (adminLabCheckbox) adminLabCheckbox.checked = false;
            }
        }

        function rolPermiteSector() {
            const rol = rolSelect.value;
            return rol === 'laboratorio' || rol === 'coordinador_lab';
        }

        function toggleSectores() {
            const permite = rolPermiteSector();
            if (permite) {
                if (wrapperSectorUnico) wrapperSectorUnico.style.display = 'none';
                if (wrapperSectoresMultiples) wrapperSectoresMultiples.style.display = 'block';
                if (sectorSelect) sectorSelect.disabled = true;
                if (sectoresMultiSelect) sectoresMultiSelect.disabled = false;
            } else {
                if (wrapperSectorUnico) wrapperSectorUnico.style.display = 'block';
                if (wrapperSectoresMultiples) wrapperSectoresMultiples.style.display = 'none';
                if (sectorSelect) sectorSelect.disabled = true;
                if (sectoresMultiSelect) sectoresMultiSelect.disabled = true;
            }
        }

        function updateMultiSelectStyles(selectElement) {
            if (!selectElement) return;
            
            Array.from(selectElement.options).forEach(option => {
                const isOriginal = option.getAttribute('data-original') === 'true';
                const isSelected = option.selected;
                const cleanText = option.getAttribute('data-clean-text') || option.textContent.replace(/^[✓✗✚\s]+/, '').trim();
                
                if (!option.getAttribute('data-clean-text')) {
                    option.setAttribute('data-clean-text', cleanText);
                }
                
                if (isOriginal) {
                    if (isSelected) {
                        // Conservar: guardado y seleccionado
                        option.style.fontWeight = 'bold';
                        option.style.color = '#2e7d32'; // Verde oscuro
                        option.style.backgroundColor = '#e8f5e9'; // Verde claro
                        option.style.textDecoration = 'none';
                        option.textContent = '✓ ' + cleanText;
                    } else {
                        // Eliminar: guardado pero deseleccionado (¡se va a quitar!)
                        option.style.fontWeight = 'bold';
                        option.style.color = '#c62828'; // Rojo oscuro
                        option.style.backgroundColor = '#ffebee'; // Rojo claro
                        option.style.textDecoration = 'line-through';
                        option.textContent = '✗ ' + cleanText + ' (se quitará)';
                    }
                } else {
                    option.style.textDecoration = 'none';
                    if (isSelected) {
                        // Agregar: nuevo seleccionado (¡se va a guardar!)
                        option.style.fontWeight = 'bold';
                        option.style.color = '#0d47a1'; // Azul oscuro
                        option.style.backgroundColor = '#e3f2fd'; // Azul claro
                        option.textContent = '✚ ' + cleanText + ' (nuevo)';
                    } else {
                        // Neutro
                        option.style.fontWeight = 'normal';
                        option.style.color = '';
                        option.style.backgroundColor = '';
                        option.textContent = cleanText;
                    }
                }
            });
        }

        // Attach listeners and initial runs
        if (rolesMultiSelect) {
            rolesMultiSelect.addEventListener('change', () => {
                updateMultiSelectStyles(rolesMultiSelect);
                toggleAdminLab();
                toggleBandejaSoloInformes();
            });
            updateMultiSelectStyles(rolesMultiSelect);
        }
        if (sectoresMultiSelect) {
            sectoresMultiSelect.addEventListener('change', () => updateMultiSelectStyles(sectoresMultiSelect));
            updateMultiSelectStyles(sectoresMultiSelect);
        }

        rolSelect.addEventListener('change', () => {
            toggleSectores();
            toggleAdminLab();
            toggleBandejaSoloInformes();
        });
        toggleSectores();
        toggleAdminLab();
        toggleBandejaSoloInformes();

        form.addEventListener('submit', function () {
            if (sectorSelect) sectorSelect.disabled = false;
            if (sectoresMultiSelect) sectoresMultiSelect.disabled = false;
        });
    });
</script>
@endsection
