<?php
use Illuminate\Support\Facades\Auth;
/**
 * Helper global para verificar si el usuario autenticado tiene un rol específico
 * 
 * @param string|array $role Rol o array de roles a verificar
 * @return bool
 */
if (!function_exists('userHasRole')) {
    function userHasRole($role)
    {
        if (!Auth::check()) {
            return false;
        }
        
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        
        return $user->hasRole($role);
    }
}

/**
 * Helper global para verificar si el usuario autenticado tiene alguno de los roles especificados
 * 
 * @param array $roles Array de roles a verificar
 * @return bool
 */
if (!function_exists('userHasAnyRole')) {
    function userHasAnyRole(array $roles)
    {
        if (!Auth::check()) {
            return false;
        }
        
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        
        return $user->hasAnyRole($roles);
    }
}

/**
 * Quien puede editar la cabecera del protocolo en PDF (misma idea que acceder a /informes con CheckAdminOrRole).
 */
/** Roles de coordinación por canal (consultoría, ASP, Clarke Fire). */
if (! function_exists('rolesCoordinadorCanalEspecial')) {
    function rolesCoordinadorCanalEspecial(): array
    {
        return \App\Support\CotizacionCanalEnsayo::ROLES_COORDINADOR_CANAL;
    }
}

if (! function_exists('userEsCoordinadorCanalEspecial')) {
    function userEsCoordinadorCanalEspecial(): bool
    {
        return userHasAnyRole(rolesCoordinadorCanalEspecial());
    }
}

/**
 * Puede abrir /show/{coti} para coordinar muestras o pasarlas a facturación (portales por canal incluidos).
 */
if (! function_exists('userPuedeGestionarMuestrasCotizacion')) {
    function userPuedeGestionarMuestrasCotizacion(): bool
    {
        if (! Auth::check()) {
            return false;
        }
        $user = Auth::user();
        if (! $user) {
            return false;
        }
        if ((int) ($user->usu_nivel ?? 0) >= 900) {
            return true;
        }
        if ($user->hasRole('coordinador_muestreo')) {
            return true;
        }

        if ($user->hasRole('coordinador_mediciones')) {
            return true;
        }

        return $user->hasAnyRole(rolesCoordinadorCanalEspecial());
    }
}

if (! function_exists('userTieneBandejaSoloInformes')) {
    function userTieneBandejaSoloInformes(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        $user = Auth::user();
        if (! $user || ! (bool) ($user->bandeja_solo_informes ?? false)) {
            return false;
        }

        return $user->hasAnyRole(['coordinador_lab', 'coordinador_mediciones']);
    }
}

if (! function_exists('userPuedeCargarItems')) {
    function userPuedeCargarItems(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        $user = Auth::user();

        return $user && $user->puedeCargarItems();
    }
}

if (! function_exists('userPuedeGestionarOrdenes')) {
    function userPuedeGestionarOrdenes(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        $user = Auth::user();

        return $user && $user->puedeGestionarOrdenes();
    }
}

if (! function_exists('userDebeVerOrdenesPorSector')) {
    function userDebeVerOrdenesPorSector(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        $user = Auth::user();

        return $user && \App\Support\OrdenesAccesoPorSector::debeFiltrarPorSector($user);
    }
}

if (! function_exists('userCanEditInformeProtocoloPdf')) {
    function userCanEditInformeProtocoloPdf(): bool
    {
        if (! Auth::check()) {
            return false;
        }
        $user = Auth::user();
        if (! $user) {
            return false;
        }
        if ((int) ($user->usu_nivel ?? 0) >= 900) {
            return true;
        }

        return $user->hasAnyRole([
            'informes',
            'firmador',
            'coordinador_lab',
            'coordinador_muestreo',
            'ventas',
            'facturador',
            'cadena_custodia',
            'coordinador_consul',
            'asp',
            'clarke_fire',
        ]);
    }
}
