@php
    $opcionesRol = $opcionesRol ?? [
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
        'cadena_custodia' => 'Cadena de custodia',
    ];
    $opcionesRolSinVacio = collect($opcionesRol)->except([''])->all();

    $modo = $modo ?? 'edit';
    $esCreate = $modo === 'create';
    $formId = $formId ?? ($esCreate ? 'form-crear-usuario' : 'form-editar-usuario');
    $puedeEditarUsuarios = $puedeEditarUsuarios ?? true;
    $esAdmin = $esAdmin ?? ((int) (Auth::user()->usu_nivel ?? 0) >= 900);
    $rolesAdicionales = $rolesAdicionales ?? [];
    $sectoresAsignados = $sectoresAsignados ?? [];
    $puedeEliminarUsuarios = $puedeEliminarUsuarios ?? false;
    $usuario = $usuario ?? null;

    $bloqueado = ! $esCreate && ! $puedeEditarUsuarios;

    $mostrarAdminLab = (string) old('rol', $esCreate ? '' : ($usuario->rol ?? '')) === 'coordinador_lab'
        || in_array('coordinador_lab', (array) old('roles_adicionales', $rolesAdicionales), true);
    $coordinadorLabEnAdicionalesGuardados = in_array('coordinador_lab', $rolesAdicionales, true);

    $mostrarBandejaSoloInformes = in_array((string) old('rol', $esCreate ? '' : ($usuario->rol ?? '')), ['coordinador_lab', 'coordinador_mediciones'], true)
        || ! empty(array_intersect(
            ['coordinador_lab', 'coordinador_mediciones'],
            (array) old('roles_adicionales', $rolesAdicionales)
        ));
    $bandejaSoloInformesEnAdicionalesGuardados = ! empty(array_intersect(
        ['coordinador_lab', 'coordinador_mediciones'],
        $rolesAdicionales
    ));

    $estadoVal = (string) old('usu_estado', $esCreate ? '1' : ($usuario->usu_estado ? '1' : '0'));
@endphp

<form id="{{ $formId }}"
      action="{{ $esCreate ? route('users.storeUser') : url('/users/' . $usuario->usu_codigo) }}"
      method="POST"
      class="ucrud-form ucrud-form--usuario"
      novalidate>
    @csrf
    @unless($esCreate)
        @method('PUT')
    @endunless

    <div class="ucrud-subpanel">
        <div class="ucrud-subpanel__header d-flex align-items-center gap-2">
            <x-heroicon-o-identification style="width: 18px; height: 18px;" />
            Identidad y contacto
        </div>
        <div class="ucrud-subpanel__body">
            @if($esCreate)
                <div class="mb-3">
                    <label for="usu_codigo" class="form-label">Código (usuario de ingreso)</label>
                    <input type="text" name="usu_codigo" id="usu_codigo" class="form-control"
                           value="{{ old('usu_codigo') }}" required autocomplete="username" maxlength="50"
                           placeholder="ej. jperez">
                    <div class="form-text">Será el nombre de usuario para iniciar sesión. No se puede cambiar después.</div>
                </div>
            @else
                <div class="mb-3">
                    <label for="usu_codigo_display" class="form-label">Código (usuario de ingreso)</label>
                    <input type="text" id="usu_codigo_display" class="form-control" value="{{ $usuario->usu_codigo }}" readonly disabled>
                    <div class="form-text">La clave primaria no se modifica desde aquí.</div>
                </div>
            @endif

            <div class="mb-3">
                <label for="usu_descripcion" class="form-label">Nombre completo</label>
                <input type="text" name="usu_descripcion" id="usu_descripcion" class="form-control"
                       value="{{ old('usu_descripcion', $esCreate ? '' : $usuario->usu_descripcion) }}" required
                       @readonly($bloqueado) @disabled($bloqueado)>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="dni" class="form-label">DNI / documento</label>
                    <input type="text" name="dni" id="dni" class="form-control"
                           value="{{ old('dni', $esCreate ? '' : $usuario->dni) }}"
                           @readonly($bloqueado) @disabled($bloqueado)>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Correo</label>
                    <input type="text" name="email" id="email" class="form-control"
                           value="{{ old('email', $esCreate ? '' : $usuario->email) }}"
                           @readonly($bloqueado) @disabled($bloqueado)>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3 mb-md-0">
                    <label for="departamento" class="form-label">Departamento / área</label>
                    <input type="text" name="departamento" id="departamento" class="form-control"
                           value="{{ old('departamento', $esCreate ? '' : $usuario->departamento) }}"
                           @readonly($bloqueado) @disabled($bloqueado)>
                </div>
                <div class="col-md-6">
                    <label for="sector_trabajo" class="form-label">Sector de trabajo (texto libre)</label>
                    <input type="text" name="sector_trabajo" id="sector_trabajo" class="form-control"
                           value="{{ old('sector_trabajo', $esCreate ? '' : $usuario->sector_trabajo) }}"
                           placeholder="p. ej. química, microbiología"
                           @readonly($bloqueado) @disabled($bloqueado)>
                </div>
            </div>
        </div>
    </div>

    <div class="ucrud-subpanel">
        <div class="ucrud-subpanel__header d-flex align-items-center gap-2">
            <x-heroicon-o-key style="width: 18px; height: 18px;" />
            Acceso y estado
        </div>
        <div class="ucrud-subpanel__body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="usu_estado" class="form-label">Estado de la cuenta</label>
                    <select name="usu_estado" id="usu_estado" class="form-select" required @disabled($bloqueado)>
                        <option value="1" @selected($estadoVal === '1')>Activo</option>
                        <option value="0" @selected($estadoVal === '0')>Inactivo</option>
                    </select>
                </div>
                @if($esAdmin && ($esCreate || $puedeEditarUsuarios))
                    <div class="col-md-6 mb-3">
                        <label for="usu_nivel" class="form-label">Nivel de acceso</label>
                        <input type="number" name="usu_nivel" id="usu_nivel" class="form-control" min="0" max="9999"
                               value="{{ old('usu_nivel', $esCreate ? 500 : $usuario->usu_nivel) }}">
                        <div class="form-text">≥900 = administrador. @if($esCreate) Por defecto 500.@endif</div>
                    </div>
                @endif
            </div>

            @if($esCreate)
                <div class="row">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" name="password" id="password" class="form-control" required
                               autocomplete="new-password" minlength="4">
                    </div>
                    <div class="col-md-6">
                        <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control"
                               required autocomplete="new-password" minlength="4">
                    </div>
                </div>
            @elseif($esAdmin && $puedeEditarUsuarios)
                <div class="row">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label for="password" class="form-label">Nueva contraseña</label>
                        <input type="password" name="password" id="password" class="form-control"
                               autocomplete="new-password" placeholder="Dejar vacío para no cambiar">
                    </div>
                    <div class="col-md-6">
                        <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control"
                               autocomplete="new-password">
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="ucrud-subpanel">
        <div class="ucrud-subpanel__header d-flex align-items-center gap-2">
            <x-heroicon-o-user-group style="width: 18px; height: 18px;" />
            Roles y permisos
        </div>
        <div class="ucrud-subpanel__body">
            <div class="row">
                <div class="@if($esAdmin && ($esCreate || $puedeEditarUsuarios)) col-lg-6 @else col-12 @endif mb-3">
                    <label for="rol" class="form-label">Rol principal</label>
                    <select name="rol" id="rol" class="form-select" @if($esCreate) required @endif @disabled($bloqueado)>
                        @if($esCreate)
                            <option value="" hidden disabled @selected((string) old('rol', '') === '')>Seleccioná un rol</option>
                            @foreach($opcionesRolSinVacio as $val => $etq)
                                <option value="{{ $val }}" @selected((string) old('rol') === (string) $val)>{{ $etq }}</option>
                            @endforeach
                        @else
                            @foreach($opcionesRol as $val => $etq)
                                <option value="{{ $val }}" @selected((string) old('rol', $usuario->rol ?? '') === (string) $val)>{{ $etq }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
                @if($esAdmin && ($esCreate || $puedeEditarUsuarios))
                    <div class="col-lg-6 mb-3">
                        <label for="roles_adicionales" class="form-label">Roles adicionales</label>
                        <select name="roles_adicionales[]" id="roles_adicionales" class="form-select" multiple size="6">
                            @foreach($opcionesRolSinVacio as $val => $etq)
                                @php
                                    $isSaved = ! $esCreate && in_array($val, $rolesAdicionales, true);
                                    $isSelected = in_array($val, old('roles_adicionales', $esCreate ? [] : $rolesAdicionales), true);
                                @endphp
                                <option value="{{ $val }}"
                                    @if(! $esCreate) data-original="{{ $isSaved ? 'true' : 'false' }}" data-clean-text="{{ $etq }}" @endif
                                    @selected($isSelected)>{{ $etq }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Varios con Ctrl+clic. No repitas el rol principal.</div>
                    </div>
                @endif
            </div>

            <div id="wrapper_admin_lab"
                 style="display: {{ $mostrarAdminLab ? 'block' : 'none' }};"
                 @unless($esCreate) data-coordinador-lab-adicional="{{ $coordinadorLabEnAdicionalesGuardados ? '1' : '0' }}" @endunless
                 class="ucrud-permisos-box mb-3">
                <div class="form-check">
                    <input type="checkbox" name="admin_lab" id="admin_lab" class="form-check-input" value="1"
                           @checked((bool) old('admin_lab', $esCreate ? false : ($usuario->admin_lab ?? false)))
                           @disabled($bloqueado)>
                    <label for="admin_lab" class="form-check-label">Administrador de laboratorio</label>
                    <div class="form-text">Puede ver quién aprobó los informes en órdenes de trabajo.</div>
                </div>
                <div class="form-check mt-2">
                    <input type="checkbox" name="puede_gestionar_ordenes" id="puede_gestionar_ordenes" class="form-check-input" value="1"
                           @checked((bool) old('puede_gestionar_ordenes', $esCreate ? false : ($usuario->puede_gestionar_ordenes ?? false)))
                           @disabled($bloqueado)>
                    <label for="puede_gestionar_ordenes" class="form-check-label">Puede gestionar órdenes</label>
                    <div class="form-text">Asignar y modificar órdenes de trabajo (analistas, sectores, fechas).</div>
                </div>
            </div>

            <div id="wrapper_bandeja_solo_informes"
                 style="display: {{ $mostrarBandejaSoloInformes ? 'block' : 'none' }};"
                 @unless($esCreate) data-coordinador-lab-o-mediciones-adicional="{{ $bandejaSoloInformesEnAdicionalesGuardados ? '1' : '0' }}" @endunless
                 class="ucrud-permisos-box mb-3">
                <div class="form-check">
                    <input type="checkbox" name="bandeja_solo_informes" id="bandeja_solo_informes" class="form-check-input" value="1"
                           @checked((bool) old('bandeja_solo_informes', $esCreate ? false : ($usuario->bandeja_solo_informes ?? false)))
                           @disabled($bloqueado)>
                    <label for="bandeja_solo_informes" class="form-check-label">Bandeja limitada (solo informes)</label>
                    <div class="form-text">Oculta Dashboard Lab, Cotizaciones, OT, Inventario Lab y Mediciones de Campo en el menú.</div>
                </div>
            </div>

            <div class="ucrud-permisos-box">
                <div class="form-check">
                    <input type="checkbox" name="puede_cargar_items" id="puede_cargar_items" class="form-check-input" value="1"
                           @checked((bool) old('puede_cargar_items', $esCreate ? false : ($usuario->puede_cargar_items ?? false)))
                           @disabled($bloqueado)>
                    <label for="puede_cargar_items" class="form-check-label">Puede cargar determinaciones (items)</label>
                    <div class="form-text">Alta, edición, importación y exportación del módulo de determinaciones.</div>
                </div>
                <div class="form-check mt-2">
                    <input type="checkbox" name="puede_autorizar_facturacion" id="puede_autorizar_facturacion" class="form-check-input" value="1"
                           @checked((bool) old('puede_autorizar_facturacion', $esCreate ? false : ($usuario->puede_autorizar_facturacion ?? false)))
                           @disabled($bloqueado)>
                    <label for="puede_autorizar_facturacion" class="form-check-label">Puede autorizar facturación</label>
                    <div class="form-text">Panel de revisión de facturación y aprobación de muestras antes de facturar.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="ucrud-subpanel mb-0">
        <div class="ucrud-subpanel__header d-flex align-items-center gap-2">
            <x-heroicon-o-beaker style="width: 18px; height: 18px;" />
            Laboratorios y sectores
        </div>
        <div class="ucrud-subpanel__body">
            <p class="form-text mb-3">Para analistas y coordinadores de laboratorio podés asignar uno o más sectores. Otros roles usan un sector único opcional.</p>

            <div class="mb-3" id="wrapper_sector_unico">
                <label for="sector_codigo" class="form-label">Laboratorio (sector único)</label>
                <select name="sector_codigo" id="sector_codigo" class="form-select" @disabled($bloqueado)>
                    <option value="">Sin sector</option>
                    @foreach($sectores as $sector)
                        @php
                            $isSaved = ! $esCreate && trim($sector->usu_codigo) == trim($usuario->sector_codigo ?? '');
                            $isSelected = $esCreate
                                ? (string) old('sector_codigo', '') === (string) $sector->usu_codigo
                                : (trim($sector->usu_codigo) == trim($usuario->sector_codigo ?? '') || trim(old('sector_codigo', '')) == trim($sector->usu_codigo));
                        @endphp
                        <option value="{{ $sector->usu_codigo }}" @selected($isSelected)
                            @if($isSaved) style="font-weight: bold; color: #2e7d32; background-color: #e8f5e9;" @endif>
                            {{ $isSaved ? '✓ ' : '' }}{{ $sector->usu_descripcion }} ({{ $sector->usu_codigo }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-0" id="wrapper_sectores_multiples">
                <label for="sectores_codigos" class="form-label">Laboratorios / sectores (múltiples)</label>
                <select name="sectores_codigos[]" id="sectores_codigos" class="form-select" multiple size="6" @disabled($bloqueado)>
                    @foreach($sectores as $sector)
                        @php
                            $trimmedSectorCodigo = trim($sector->usu_codigo);
                            $trimmedSectoresAsignados = array_map('trim', $sectoresAsignados);
                            $isSaved = ! $esCreate && in_array($trimmedSectorCodigo, $trimmedSectoresAsignados, true);
                            $trimmedOldSectores = array_map('trim', (array) old('sectores_codigos', $esCreate ? [] : $sectoresAsignados));
                            $isSelected = in_array($trimmedSectorCodigo, $trimmedOldSectores, true);
                            $labelSector = $sector->usu_descripcion . ' (' . $sector->usu_codigo . ')';
                        @endphp
                        <option value="{{ $sector->usu_codigo }}"
                            @if(! $esCreate) data-original="{{ $isSaved ? 'true' : 'false' }}" data-clean-text="{{ $labelSector }}" @endif
                            @selected($isSelected)>{{ $labelSector }}</option>
                    @endforeach
                </select>
                <div class="form-text">Ctrl+clic (Cmd en Mac) para seleccionar varios.</div>
            </div>
        </div>
    </div>

    <div class="ucrud-form__actions">
        @if(! $esCreate && $puedeEliminarUsuarios && trim((string) Auth::user()->usu_codigo) !== trim((string) $usuario->usu_codigo))
            <button type="submit" form="form-eliminar-usuario" class="ucrud-btn ucrud-btn--ghost text-danger">Eliminar usuario</button>
        @endif
        <div class="ms-auto d-flex flex-wrap gap-2">
            <a href="{{ route('users.showUsers') }}" class="ucrud-btn ucrud-btn--ghost">Cancelar</a>
            @if($esCreate || $puedeEditarUsuarios)
                <button type="submit" class="ucrud-btn ucrud-btn--primary">
                    {{ $esCreate ? 'Crear usuario' : 'Guardar cambios' }}
                </button>
            @endif
        </div>
    </div>
</form>

@if(! $esCreate && $puedeEliminarUsuarios && trim((string) Auth::user()->usu_codigo) !== trim((string) $usuario->usu_codigo))
    <form id="form-eliminar-usuario"
          action="{{ route('users.destroy', ['usu_codigo' => $usuario->usu_codigo]) }}"
          method="POST"
          class="d-none"
          onsubmit="return confirm('¿Eliminar definitivamente a {{ $usuario->usu_descripcion }} ({{ trim($usuario->usu_codigo) }})? Esta acción no se puede deshacer.');">
        @csrf
        @method('DELETE')
    </form>
@endif
