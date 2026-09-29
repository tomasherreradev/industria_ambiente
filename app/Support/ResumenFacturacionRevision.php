<?php

namespace App\Support;

use App\Models\Cotio;
use App\Models\CotioInstancia;
use App\Models\User;
use Illuminate\Support\Collection;

final class ResumenFacturacionRevision
{
    public static function construir(CotioInstancia $muestra): array
    {
        $muestra->load([
            'cotizacion.matriz',
            'cotizacion.cliente',
            'cotizacion.sucursal',
            'responsablesMuestreo',
            'coordinador',
            'vehiculo',
            'valoresVariables',
            'aprobadorInforme',
        ]);

        $cotizacion = $muestra->cotizacion;
        $cotio = Cotio::query()
            ->where('cotio_numcoti', $muestra->cotio_numcoti)
            ->where('cotio_item', $muestra->cotio_item)
            ->where('cotio_subitem', 0)
            ->first();

        $canal = CotizacionCanalEnsayo::resolverCanalEnsayo(
            $cotio ?? $muestra,
            $cotizacion?->matriz?->matriz_descripcion
        );

        $analisis = CotioInstancia::query()
            ->where('cotio_numcoti', $muestra->cotio_numcoti)
            ->where('cotio_item', $muestra->cotio_item)
            ->where('instance_number', $muestra->instance_number)
            ->where('cotio_subitem', '>', 0)
            ->with(['responsablesAnalisis', 'herramientasLab', 'coordinadorLab', 'aprobadorInforme'])
            ->orderBy('cotio_subitem')
            ->get()
            ->map(fn (CotioInstancia $item) => self::mapearAnalisis($item))
            ->values();

        return [
            'muestra' => self::mapearMuestra($muestra),
            'cotizacion' => self::mapearCotizacion($cotizacion, $muestra),
            'canal' => [
                'codigo' => $canal,
                'etiqueta' => CotizacionCanalEnsayo::etiquetaCanalEnsayo($canal),
            ],
            'referencias' => CotizacionReferenciasFacturacion::rowsFromModel($cotizacion),
            'referencias_facturacion' => $cotizacion
                ? CotizacionReferenciasFacturacion::datosParaVista($cotizacion)
                : null,
            'mediciones_campo' => $muestra->valoresVariables
                ->sortBy('variable')
                ->map(fn ($v) => [
                    'variable' => $v->variable,
                    'valor' => $v->valor,
                ])
                ->values()
                ->all(),
            'herramientas_muestreo' => $muestra->getHerramientasMuestreo()
                ->map(fn ($h) => [
                    'equipamiento' => $h->equipamiento,
                    'marca_modelo' => $h->marca_modelo,
                    'cantidad' => $h->cantidad ?? 1,
                    'observaciones' => $h->pivot_observaciones ?? null,
                ])
                ->values()
                ->all(),
            'analisis' => $analisis->all(),
            'informe' => [
                'enable_inform' => (bool) $muestra->enable_inform,
                'aprobado' => (bool) $muestra->aprobado_informe,
                'fecha_aprobacion' => self::fmt($muestra->fecha_aprobacion_informe),
                'aprobador' => self::nombreUsuario($muestra->aprobadorInforme, $muestra->aprobado_informe_usuario),
                'fecha_creacion_inform' => self::fmt($muestra->fecha_creacion_inform),
                'firmado' => (bool) $muestra->firmado,
                'fecha_firma' => self::fmt($muestra->fecha_firma),
            ],
            'revision_facturacion' => [
                'aprobada' => (bool) $muestra->facturacion_aprobada,
                'fecha' => self::fmt($muestra->fecha_facturacion_aprobada),
                'usuario' => trim((string) ($muestra->facturacion_aprobada_usuario ?? '')) ?: null,
            ],
        ];
    }

    private static function mapearMuestra(CotioInstancia $muestra): array
    {
        return [
            'id' => $muestra->id,
            'descripcion' => $muestra->cotio_descripcion,
            'identificacion' => $muestra->cotio_identificacion,
            'instance_number' => $muestra->instance_number,
            'otn' => $muestra->otn,
            'estado_muestreo' => $muestra->cotio_estado,
            'estado_analisis' => $muestra->cotio_estado_analisis,
            'fecha_muestreo' => self::fmt($muestra->fecha_muestreo),
            'fecha_inicio_muestreo' => self::fmt($muestra->fecha_inicio_muestreo),
            'fecha_fin_muestreo' => self::fmt($muestra->fecha_fin_muestreo),
            'fecha_identificacion' => self::fmt($muestra->fecha_identificacion),
            'coordinador_muestreo' => self::nombreUsuario($muestra->coordinador),
            'responsables_muestreo' => self::nombresUsuarios($muestra->responsablesMuestreo),
            'vehiculo' => $muestra->vehiculo
                ? trim(($muestra->vehiculo->marca ?? '') . ' ' . ($muestra->vehiculo->modelo ?? '') . ' ' . ($muestra->vehiculo->patente ?? ''))
                : null,
            'observaciones_coord_muestreo' => trim((string) ($muestra->observaciones_medicion_coord_muestreo ?? '')),
            'observaciones_muestreador' => trim((string) ($muestra->observaciones_medicion_muestreador ?? '')),
            'observaciones_muestreo_coord' => trim((string) ($muestra->observaciones_muestreo_coord ?? '')),
            'observaciones_muestreo_muestreador' => trim((string) ($muestra->observaciones_muestreo_muestreador ?? '')),
            'observaciones_ot' => trim((string) ($muestra->observaciones_ot ?? '')),
            'latitud' => $muestra->latitud,
            'longitud' => $muestra->longitud,
            'imagen_url' => $muestra->image_url,
            'fecha_inicio_ot' => self::fmt($muestra->fecha_inicio_ot),
            'fecha_fin_ot' => self::fmt($muestra->fecha_fin_ot),
            'fecha_carga_ot' => self::fmt($muestra->fecha_carga_ot),
        ];
    }

    private static function mapearCotizacion($cotizacion, CotioInstancia $muestra): array
    {
        if (! $cotizacion) {
            return [];
        }

        return [
            'numero' => $cotizacion->coti_num,
            'cliente' => CotizacionClienteEtiqueta::paraLista($cotizacion),
            'descripcion' => trim((string) ($cotizacion->coti_descripcion ?? '')),
            'estado' => trim((string) ($cotizacion->coti_estado ?? '')),
            'matriz' => $cotizacion->matriz?->matriz_descripcion,
            'sucursal' => $cotizacion->sucursal?->sucursal_descripcion ?? null,
            'establecimiento' => trim((string) ($cotizacion->coti_establecimiento ?? '')),
            'direccion' => trim((string) ($cotizacion->coti_direccioncli ?? '')),
            'localidad' => trim((string) ($cotizacion->coti_localidad ?? '')),
            'fecha_alta' => self::fmtFecha($cotizacion->coti_fechaalta),
            'fecha_aprobado' => self::fmtFecha($cotizacion->coti_fechaaprobado),
            'condicion_pago' => trim((string) ($cotizacion->coti_cond_pago ?? '')),
            'cadena_custodia_cotizacion' => trim((string) ($cotizacion->coti_cadena_custodia ?? '')),
            'notas_facturacion' => trim((string) ($cotizacion->coti_notas_facturacion ?? '')),
            'notas' => trim((string) ($cotizacion->coti_notas ?? '')),
            'monto_instancia' => $muestra->monto,
            'nro_precinto' => trim((string) ($muestra->nro_precinto ?? '')),
            'nro_cadena_custodia' => trim((string) ($muestra->nro_cadena ?? '')),
        ];
    }

    private static function mapearAnalisis(CotioInstancia $analisis): array
    {
        $fechaInforme = FechaAnalisisInformePdf::fechaManualInforme($analisis);

        return [
            'id' => $analisis->id,
            'descripcion' => $analisis->cotio_descripcion,
            'subitem' => $analisis->cotio_subitem,
            'estado' => $analisis->cotio_estado_analisis,
            'active_ot' => (bool) $analisis->active_ot,
            'enable_inform' => (bool) $analisis->enable_inform,
            'responsables_por_sector' => AsignacionSectorLaboratorio::sectoresConResponsablesAsignadosParaApi(
                $analisis->responsablesAnalisis
            ),
            'coordinador_lab' => self::nombreUsuario($analisis->coordinadorLab),
            'fecha_informe' => $fechaInforme ? $fechaInforme->format('d/m/Y') : null,
            'fecha_carga_ot' => self::fmt($analisis->fecha_carga_ot),
            'resultados' => self::mapearResultados($analisis),
            'herramientas_lab' => $analisis->herramientasLab
                ->map(fn ($h) => [
                    'equipamiento' => $h->equipamiento,
                    'marca_modelo' => $h->marca_modelo,
                    'cantidad' => $h->pivot->cantidad ?? 1,
                    'observaciones' => $h->pivot->observaciones ?? null,
                ])
                ->values()
                ->all(),
            'observaciones_ot' => trim((string) ($analisis->observaciones_ot ?? '')),
        ];
    }

    private static function mapearResultados(CotioInstancia $analisis): array
    {
        $bloques = [
            ['label' => 'Resultado 1', 'valor' => $analisis->resultado, 'obs' => $analisis->observacion_resultado, 'fecha' => $analisis->fecha_carga_resultado_1, 'resp' => $analisis->responsable_resultado_1],
            ['label' => 'Resultado 2', 'valor' => $analisis->resultado_2, 'obs' => $analisis->observacion_resultado_2, 'fecha' => $analisis->fecha_carga_resultado_2, 'resp' => $analisis->responsable_resultado_2],
            ['label' => 'Resultado 3', 'valor' => $analisis->resultado_3, 'obs' => $analisis->observacion_resultado_3, 'fecha' => $analisis->fecha_carga_resultado_3, 'resp' => $analisis->responsable_resultado_3],
            ['label' => 'Resultado final', 'valor' => $analisis->resultado_final, 'obs' => $analisis->observacion_resultado_final, 'fecha' => $analisis->fecha_carga_ot, 'resp' => $analisis->responsable_resultado_final],
        ];

        return collect($bloques)
            ->filter(fn ($b) => self::valorPresente($b['valor']) || self::valorPresente($b['obs']))
            ->map(fn ($b) => [
                'label' => $b['label'],
                'valor' => $b['valor'],
                'observacion' => $b['obs'],
                'fecha_carga' => self::fmt($b['fecha']),
                'responsable' => trim((string) ($b['resp'] ?? '')) ?: null,
                'es_final' => $b['label'] === 'Resultado final',
            ])
            ->values()
            ->all();
    }

    private static function valorPresente(mixed $valor): bool
    {
        return $valor !== null && trim((string) $valor) !== '';
    }

    private static function fmt(mixed $fecha): ?string
    {
        if (! $fecha) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($fecha)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $fecha;
        }
    }

    private static function fmtFecha(mixed $fecha): ?string
    {
        if (! $fecha) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $fecha;
        }
    }

    private static function nombreUsuario(?User $user, ?string $codigoFallback = null): ?string
    {
        if ($user) {
            return trim((string) ($user->usu_descripcion ?: $user->usu_codigo));
        }

        $codigo = trim((string) ($codigoFallback ?? ''));

        return $codigo !== '' ? $codigo : null;
    }

    /** @param  Collection<int, User>|iterable<User>  $usuarios */
    private static function nombresUsuarios(iterable $usuarios): array
    {
        return collect($usuarios)
            ->map(fn (User $u) => trim((string) ($u->usu_descripcion ?: $u->usu_codigo)))
            ->filter()
            ->values()
            ->all();
    }
}
