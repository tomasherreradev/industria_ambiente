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

if (! function_exists('userPuedeAutorizarFacturacion')) {
    function userPuedeAutorizarFacturacion(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        $user = Auth::user();

        return $user && $user->puedeAutorizarFacturacion();
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

if (! function_exists('userEsAdminNivel')) {
    function userEsAdminNivel(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        return (int) (Auth::user()?->usu_nivel ?? 0) >= 900;
    }
}

/**
 * Puede iniciar la firma digital de un informe (firmador con envío a firma, o administrador).
 */
if (! function_exists('userPuedeFirmarInforme')) {
    function userPuedeFirmarInforme($muestra): bool
    {
        if (! Auth::check() || ! $muestra || (bool) ($muestra->firmado ?? false)) {
            return false;
        }

        if (userEsAdminNivel()) {
            return true;
        }

        $user = Auth::user();

        return $user && $user->hasRole('firmador') && (bool) ($muestra->listo_para_firmar ?? false);
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

if (! function_exists('etiquetaNumeroCotizacion')) {
    /**
     * Texto estándar para badges y etiquetas de número de cotización.
     */
    function etiquetaNumeroCotizacion(int|string|null $numero): string
    {
        if ($numero === null || $numero === '' || $numero === '—') {
            return '—';
        }

        return 'Cotización N°' . trim((string) $numero);
    }
}

if (! function_exists('fechaActualLargaEs')) {
    function fechaActualLargaEs(): string
    {
        $fecha = \Carbon\Carbon::now()
            ->locale('es')
            ->translatedFormat('l, j \d\e F \d\e Y');

        return mb_strtoupper(mb_substr($fecha, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($fecha, 1, null, 'UTF-8');
    }
}
