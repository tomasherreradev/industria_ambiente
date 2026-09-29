<?php

namespace App\Support;

use App\Models\CotioHistorialCambios;
use App\Models\CotioInstancia;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PerfilUsuarioResumen
{
    /** @var array<string, string> */
    private const ETIQUETAS_ROL = [
        'laboratorio' => 'Analista',
        'muestreador' => 'Muestreador',
        'coordinador_lab' => 'Coordinador de laboratorio',
        'coordinador_muestreo' => 'Coordinador de muestreo',
        'facturador' => 'Facturador',
        'ventas' => 'Vendedor',
        'firmador' => 'Firmador',
        'coordinador_consul' => 'Coordinador de consultoría',
        'coordinador_mediciones' => 'Coordinador de mediciones',
        'asp' => 'ASP',
        'clarke_fire' => 'Clarke Fire',
        'cliente' => 'Usuario cliente',
        'cadena_custodia' => 'Cadena de custodia',
    ];

    public static function construir(User $user): array
    {
        $codigo = trim((string) $user->usu_codigo);
        $roles = collect($user->all_roles)->map(fn ($r) => trim((string) $r))->filter()->unique()->values();

        $permisos = self::permisosEfectivos($user, $roles);
        $metricas = self::metricas($user, $roles, $codigo);
        $operativo = self::bloquesOperativos($user, $roles, $codigo);
        $actividad = self::actividadReciente($codigo);

        return [
            'iniciales' => self::inicialesDesdeNombre((string) $user->usu_descripcion),
            'cargo' => self::etiquetaRol(trim((string) ($user->rol ?? ''))),
            'cuenta' => self::camposCuenta($user),
            'permisos' => $permisos,
            'metricas' => $metricas,
            'operativo' => $operativo,
            'actividad' => $actividad,
            'seguridad' => [
                'estado_cuenta' => ($user->usu_estado ?? false) ? 'Activo' : 'Inactivo',
                'estado_es_activo' => (bool) ($user->usu_estado ?? false),
                'ultima_actualizacion' => $user->updated_at,
                'alta_cuenta' => $user->created_at,
                'tiene_sesion_registrada' => trim((string) ($user->current_session_id ?? '')) !== '',
            ],
        ];
    }

    public static function etiquetaRol(string $rol): string
    {
        if ($rol === '') {
            return '';
        }

        return self::ETIQUETAS_ROL[$rol] ?? ucfirst(str_replace('_', ' ', $rol));
    }

    /** @return array<string, string> */
    private static function camposCuenta(User $user): array
    {
        $campos = [
            'Código' => trim((string) $user->usu_codigo),
            'Nombre' => trim((string) $user->usu_descripcion),
        ];

        if (trim((string) ($user->email ?? '')) !== '') {
            $campos['Email'] = trim((string) $user->email);
        }
        if (trim((string) ($user->dni ?? '')) !== '') {
            $campos['DNI'] = trim((string) $user->dni);
        }
        if (trim((string) ($user->departamento ?? '')) !== '') {
            $campos['Departamento'] = trim((string) $user->departamento);
        }
        if (trim((string) ($user->sector_trabajo ?? '')) !== '') {
            $campos['Sector de trabajo'] = trim((string) $user->sector_trabajo);
        } elseif (trim((string) ($user->sector_codigo ?? '')) !== '') {
            $campos['Sector'] = trim((string) $user->sector_codigo);
        }

        $campos['Nivel'] = (string) (int) ($user->usu_nivel ?? 0);

        if ($user->created_at) {
            $campos['Alta en el sistema'] = $user->created_at->format('d/m/Y H:i');
        }
        if ($user->updated_at) {
            $campos['Última actualización'] = $user->updated_at->format('d/m/Y H:i');
        }

        return $campos;
    }

    /**
     * @param  Collection<int, string>  $roles
     * @return array{items: list<array{clave: string, etiqueta: string}>, total: int}
     */
    private static function permisosEfectivos(User $user, Collection $roles): array
    {
        $items = [];

        if ((int) ($user->usu_nivel ?? 0) >= 900) {
            $items[] = ['clave' => 'admin', 'etiqueta' => 'Acceso administrativo (nivel ≥ 900)'];
        }
        foreach ($roles as $rol) {
            $items[] = ['clave' => 'rol:'.$rol, 'etiqueta' => 'Rol: '.self::etiquetaRol($rol)];
        }
        if ($user->isAdminLab()) {
            $items[] = ['clave' => 'admin_lab', 'etiqueta' => 'Administrador de laboratorio'];
        }
        if ($user->puedeCargarItems()) {
            $items[] = ['clave' => 'items', 'etiqueta' => 'Puede cargar ítems / determinaciones'];
        }
        if ($user->puedeGestionarOrdenes()) {
            $items[] = ['clave' => 'ordenes', 'etiqueta' => 'Puede gestionar órdenes'];
        }
        if ($user->puedeAutorizarFacturacion()) {
            $items[] = ['clave' => 'facturacion', 'etiqueta' => 'Puede autorizar facturación'];
        }
        if ($user->bandeja_solo_informes ?? false) {
            $items[] = ['clave' => 'informes', 'etiqueta' => 'Bandeja restringida a informes'];
        }

        return ['items' => $items, 'total' => count($items)];
    }

    /**
     * @param  Collection<int, string>  $roles
     * @return list<array{valor: int, etiqueta: string, detalle: string}>
     */
    private static function metricas(User $user, Collection $roles, string $codigo): array
    {
        $tarjetas = [];

        if ($roles->contains('laboratorio') || self::tieneAnalisisAsignados($codigo)) {
            foreach (self::conteosAnalisisAsignados($codigo) as $fila) {
                if ($fila['valor'] > 0 || $fila['clave'] === 'en_curso') {
                    $tarjetas[] = $fila;
                }
            }
        }

        if ($roles->contains('muestreador') || self::tieneMuestreoAsignado($codigo)) {
            foreach (self::conteosMuestreoAsignados($codigo) as $fila) {
                if ($fila['valor'] > 0) {
                    $tarjetas[] = $fila;
                }
            }
        }

        if ($user->hasAnyRole(['coordinador_lab', 'coordinador_muestreo']) || (int) ($user->usu_nivel ?? 0) >= 900) {
            foreach (self::conteosCoordinacion($user) as $fila) {
                if ($fila['valor'] > 0) {
                    $tarjetas[] = $fila;
                }
            }
        }

        if ((int) ($user->usu_nivel ?? 0) >= 900) {
            $activos = User::query()->where('usu_estado', true)->count();
            $tarjetas[] = [
                'valor' => $activos,
                'etiqueta' => 'Usuarios activos',
                'detalle' => 'Cuentas habilitadas en el sistema',
            ];
        }

        return array_slice($tarjetas, 0, 8);
    }

    /**
     * @param  Collection<int, string>  $roles
     * @param  list<array{valor: int, etiqueta: string, detalle: string}>  $metricas
     * @return list<array{titulo: string, filas: list<array{label: string, value: int|string}>}>
     */
    private static function bloquesOperativos(User $user, Collection $roles, string $codigo): array
    {
        $bloques = [];

        if ($roles->contains('laboratorio') || self::tieneAnalisisAsignados($codigo)) {
            $c = self::conteosAnalisisAsignados($codigo, true);
            $bloques[] = [
                'titulo' => 'Analista — análisis asignados',
                'filas' => [
                    ['label' => 'En análisis (coordinado)', 'value' => $c['coordinado analisis'] ?? 0],
                    ['label' => 'En revisión', 'value' => $c['en revision analisis'] ?? 0],
                    ['label' => 'Finalizados (analizado)', 'value' => $c['analizado'] ?? 0],
                    ['label' => 'Total activos asignados', 'value' => $c['total'] ?? 0],
                ],
            ];
        }

        if ($roles->contains('muestreador') || self::tieneMuestreoAsignado($codigo)) {
            $c = self::conteosMuestreoAsignados($codigo, true);
            $bloques[] = [
                'titulo' => 'Muestreador — muestras asignadas',
                'filas' => [
                    ['label' => 'Coordinado muestreo', 'value' => $c['coordinado muestreo'] ?? 0],
                    ['label' => 'En revisión muestreo', 'value' => $c['en revision muestreo'] ?? 0],
                    ['label' => 'Muestreado', 'value' => $c['muestreado'] ?? 0],
                    ['label' => 'Total asignadas', 'value' => $c['total'] ?? 0],
                ],
            ];
        }

        if ($user->hasAnyRole(['coordinador_lab', 'coordinador_muestreo'])) {
            $c = self::conteosCoordinacion($user, true);
            $bloques[] = [
                'titulo' => 'Coordinación — ámbito operativo',
                'filas' => [
                    ['label' => 'Análisis activos en alcance', 'value' => $c['analisis_activos'] ?? 0],
                    ['label' => 'En revisión de análisis', 'value' => $c['en revision analisis'] ?? 0],
                    ['label' => 'Muestras en muestreo (subitem 0)', 'value' => $c['muestras_muestreo'] ?? 0],
                ],
            ];
        }

        if ((int) ($user->usu_nivel ?? 0) >= 900) {
            $bloques[] = [
                'titulo' => 'Administración',
                'filas' => [
                    ['label' => 'Usuarios activos', 'value' => User::query()->where('usu_estado', true)->count()],
                    ['label' => 'Usuarios totales', 'value' => User::query()->count()],
                ],
            ];
        }

        return $bloques;
    }

    private static function tieneAnalisisAsignados(string $codigo): bool
    {
        return DB::table('instancia_responsable_analisis')
            ->whereRaw('TRIM(usu_codigo) = ?', [$codigo])
            ->exists();
    }

    private static function tieneMuestreoAsignado(string $codigo): bool
    {
        return DB::table('instancia_responsable_muestreo')
            ->whereRaw('TRIM(usu_codigo) = ?', [$codigo])
            ->exists();
    }

    /**
     * @return list<array{valor: int, etiqueta: string, detalle: string, clave?: string}>|array<string, int>
     */
    private static function conteosAnalisisAsignados(string $codigo, bool $asMap = false): array
    {
        $ids = DB::table('instancia_responsable_analisis')
            ->whereRaw('TRIM(usu_codigo) = ?', [$codigo])
            ->pluck('cotio_instancia_id');

        if ($ids->isEmpty()) {
            return $asMap ? ['total' => 0, 'coordinado analisis' => 0, 'en revision analisis' => 0, 'analizado' => 0] : [];
        }

        $filas = CotioInstancia::query()
            ->whereIn('id', $ids)
            ->where('cotio_subitem', '>', 0)
            ->where('active_ot', true)
            ->selectRaw("COALESCE(NULLIF(TRIM(cotio_estado_analisis), ''), 'sin_estado') as estado, COUNT(*) as total")
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $map = [
            'total' => (int) $filas->sum(),
            'coordinado analisis' => (int) ($filas['coordinado analisis'] ?? 0),
            'en revision analisis' => (int) ($filas['en revision analisis'] ?? 0),
            'analizado' => (int) ($filas['analizado'] ?? 0),
        ];

        if ($asMap) {
            return $map;
        }

        $out = [];
        if ($map['coordinado analisis'] > 0) {
            $out[] = ['clave' => 'en_curso', 'valor' => $map['coordinado analisis'], 'etiqueta' => 'Análisis en curso', 'detalle' => 'Asignados a vos'];
        }
        if ($map['en revision analisis'] > 0) {
            $out[] = ['valor' => $map['en revision analisis'], 'etiqueta' => 'En revisión', 'detalle' => 'Análisis asignados'];
        }
        if ($map['analizado'] > 0) {
            $out[] = ['valor' => $map['analizado'], 'etiqueta' => 'Análisis finalizados', 'detalle' => 'Estado analizado'];
        }

        return $out;
    }

    /**
     * @return list<array{valor: int, etiqueta: string, detalle: string}>|array<string, int>
     */
    private static function conteosMuestreoAsignados(string $codigo, bool $asMap = false): array
    {
        $ids = DB::table('instancia_responsable_muestreo')
            ->whereRaw('TRIM(usu_codigo) = ?', [$codigo])
            ->pluck('cotio_instancia_id');

        if ($ids->isEmpty()) {
            return $asMap ? [
                'total' => 0,
                'coordinado muestreo' => 0,
                'en revision muestreo' => 0,
                'muestreado' => 0,
            ] : [];
        }

        $filas = CotioInstancia::query()
            ->whereIn('id', $ids)
            ->where('cotio_subitem', 0)
            ->selectRaw("COALESCE(NULLIF(TRIM(cotio_estado), ''), 'sin_estado') as estado, COUNT(*) as total")
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $map = [
            'total' => (int) $filas->sum(),
            'coordinado muestreo' => (int) ($filas['coordinado muestreo'] ?? 0),
            'en revision muestreo' => (int) ($filas['en revision muestreo'] ?? 0),
            'muestreado' => (int) ($filas['muestreado'] ?? 0),
        ];

        if ($asMap) {
            return $map;
        }

        $out = [];
        if ($map['coordinado muestreo'] > 0) {
            $out[] = ['valor' => $map['coordinado muestreo'], 'etiqueta' => 'Muestreo pendiente', 'detalle' => 'Coordinado muestreo'];
        }
        if ($map['en revision muestreo'] > 0) {
            $out[] = ['valor' => $map['en revision muestreo'], 'etiqueta' => 'Muestreo en curso', 'detalle' => 'En revisión'];
        }
        if ($map['muestreado'] > 0) {
            $out[] = ['valor' => $map['muestreado'], 'etiqueta' => 'Muestreos finalizados', 'detalle' => 'Estado muestreado'];
        }

        return $out;
    }

    /**
     * @return list<array{valor: int, etiqueta: string, detalle: string}>|array<string, int>
     */
    private static function conteosCoordinacion(User $user, bool $asMap = false): array
    {
        $aplicarSector = (int) ($user->usu_nivel ?? 0) < 900 && OrdenesAccesoPorSector::debeFiltrarPorSector($user);

        if ((int) ($user->usu_nivel ?? 0) < 900 && $user->hasRole('coordinador_muestreo') && ! $aplicarSector) {
            return $asMap ? ['analisis_activos' => 0, 'en revision analisis' => 0, 'muestras_muestreo' => 0] : [];
        }

        $analisisQuery = function () use ($user, $aplicarSector) {
            $q = CotioInstancia::query()
                ->where('cotio_subitem', '>', 0)
                ->where('active_ot', true);
            if ($aplicarSector) {
                OrdenesAccesoPorSector::aplicarFiltroInstanciaPorSectoresUsuario($q, $user);
            }

            return $q;
        };

        $muestrasQuery = function () use ($user, $aplicarSector) {
            $q = CotioInstancia::query()
                ->where('cotio_subitem', 0)
                ->where(function ($sub) {
                    $sub->where('enable_ot', false)->orWhereNull('enable_ot');
                });
            if ($aplicarSector) {
                OrdenesAccesoPorSector::aplicarFiltroInstanciaPorSectoresUsuario($q, $user);
            }

            return $q;
        };

        $map = [
            'analisis_activos' => $analisisQuery()->count(),
            'en revision analisis' => $analisisQuery()->where('cotio_estado_analisis', 'en revision analisis')->count(),
            'muestras_muestreo' => $muestrasQuery()->count(),
        ];

        if ($asMap) {
            return $map;
        }

        $out = [];
        if ($map['analisis_activos'] > 0) {
            $out[] = ['valor' => $map['analisis_activos'], 'etiqueta' => 'Análisis activos', 'detalle' => 'Alcance de coordinación'];
        }
        if ($map['en revision analisis'] > 0) {
            $out[] = ['valor' => $map['en revision analisis'], 'etiqueta' => 'En revisión', 'detalle' => 'Laboratorio'];
        }

        return $out;
    }

    /**
     * @return list<array{descripcion: string, fecha: \Carbon\Carbon|null}>
     */
    private static function actividadReciente(string $codigo): array
    {
        return CotioHistorialCambios::query()
            ->whereRaw('TRIM(usuario_id) = ?', [$codigo])
            ->orderByDesc('fecha_cambio')
            ->limit(8)
            ->get()
            ->map(function (CotioHistorialCambios $row) {
                return [
                    'descripcion' => self::describirHistorial($row),
                    'fecha' => $row->fecha_cambio,
                ];
            })
            ->all();
    }

    private static function describirHistorial(CotioHistorialCambios $row): string
    {
        $accion = trim((string) ($row->accion ?? 'Cambio'));
        $campo = trim((string) ($row->campo_modificado ?? ''));
        $tabla = trim((string) ($row->tabla_afectada ?? ''));

        if ($campo !== '') {
            return ucfirst($accion).' · '.$campo.($tabla !== '' ? ' ('.$tabla.')' : '');
        }

        return ucfirst($accion).($tabla !== '' ? ' en '.$tabla : '');
    }

    public static function inicialesDesdeNombre(string $nombre): string
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return '?';
        }

        $partes = preg_split('/\s+/u', $nombre) ?: [];
        if (count($partes) >= 2) {
            return mb_strtoupper(mb_substr($partes[0], 0, 1).mb_substr($partes[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($nombre, 0, 2));
    }
}
