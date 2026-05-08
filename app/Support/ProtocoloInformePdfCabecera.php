<?php

namespace App\Support;

use App\Models\CotioInstancia;
use Carbon\Carbon;

/**
 * Cabecera del recuadro "PROTOCOLO DE ENSAYO" en el PDF de informe.
 * Valores por defecto desde instancia/cotización; personalización en protocolo_informe_json.
 */
class ProtocoloInformePdfCabecera
{
    public const KEYS = [
        'fecha_emision',
        'otn',
        'razon_social',
        'direccion',
        'fecha_extraccion',
        'fecha_recepcion',
        'datos_muestra',
        'sitio_extraccion',
        'precinto',
        'cadena_custodia',
        'identificacion_muestra',
        'legislacion_normativa',
        'muestra_extraida_por',
        'observaciones_adicionales',
        'notas_informe',
        'protocolo_opds',
    ];

    public static function defaults(CotioInstancia $muestra): array
    {
        $muestra->loadMissing(['cotizacion', 'muestra.leyNormativa', 'responsablesMuestreo']);

        $cotiInf = $muestra->cotizacion;
        $destInfPdf = CotizacionClienteEtiqueta::destinatarioPdfCamposPrincipales($cotiInf);
        $razonInformePdf = $destInfPdf['dRazon'] !== '' ? $destInfPdf['dRazon'] : '—';
        $dirInformePdf = trim(CotizacionClienteEtiqueta::direccionDestinatarioTexto($cotiInf));
        $dirInformePdf = $dirInformePdf !== '' ? $dirInformePdf : '—';

        $fechaEmision = $muestra->fecha_aprobacion_informe ?? $muestra->fecha_creacion_inform ?? now();
        $respsMuestreo = ($muestra->responsablesMuestreo ?? collect())->pluck('usu_descripcion')->filter()->implode(', ');
        $muestraExtraida = $respsMuestreo !== ''
            ? $respsMuestreo
            : 'Personal de Laboratorio según indicaciones del cliente.';

        $otn = $muestra->otn !== null && trim((string) $muestra->otn) !== '' ? trim((string) $muestra->otn) : '—';

        return [
            'fecha_emision' => Carbon::parse($fechaEmision)->format('d/m/Y'),
            'otn' => $otn,
            'razon_social' => $razonInformePdf,
            'direccion' => $dirInformePdf,
            'fecha_extraccion' => $muestra->fecha_inicio_muestreo
                ? Carbon::parse($muestra->fecha_inicio_muestreo)->format('d/m/Y')
                : '—',
            'fecha_recepcion' => $muestra->fecha_carga_ot
                ? Carbon::parse($muestra->fecha_carga_ot)->format('d/m/Y')
                : '—',
            'datos_muestra' => $muestra->cotio_descripcion !== null && trim((string) $muestra->cotio_descripcion) !== ''
                ? trim((string) $muestra->cotio_descripcion)
                : '—',
            'sitio_extraccion' => $cotiInf && ($cotiInf->coti_establecimiento ?? '') !== ''
                ? trim((string) $cotiInf->coti_establecimiento)
                : '—',
            'precinto' => $muestra->nro_precinto !== null && trim((string) $muestra->nro_precinto) !== ''
                ? trim((string) $muestra->nro_precinto)
                : '—',
            'cadena_custodia' => $muestra->nro_cadena !== null && trim((string) $muestra->nro_cadena) !== ''
                ? trim((string) $muestra->nro_cadena)
                : '—',
            'identificacion_muestra' => trim((string) ($muestra->cotio_identificacion ?? '')) !== ''
                ? trim((string) $muestra->cotio_identificacion)
                : '—',
            'legislacion_normativa' => LeyNormativaPresentacion::textoPlano($muestra->muestra),
            'muestra_extraida_por' => $muestraExtraida,
            'observaciones_adicionales' => '',
            'notas_informe' => '',
            'protocolo_opds' => '—',
        ];
    }

    /**
     * Obtiene las notas predeterminadas desde el catálogo
     */
    public static function obtenerNotasPredeterminadas(CotioInstancia $muestra): string
    {
        // 1. Cargamos las relaciones necesarias
        // Cargamos 'tarea' que es la que apunta a la fila de Cotio exacta (mismo subitem)
        $muestra->loadMissing('tarea');
        
        $todasLasNotas = collect();

        // 2. Notas del agrupador (muestra)
        if ($muestra->tarea) {
            $itemCatalogo = static::buscarItemCatalogo($muestra->tarea);
            if ($itemCatalogo) {
                $todasLasNotas = $todasLasNotas->concat($itemCatalogo->notasPredeterminadas);
            }
        }

        // 3. Notas de los componentes (analitos)
        // Buscamos todas las instancias relacionadas que sean analitos (subitem > 0)
        $instanciasAnalitos = CotioInstancia::with('tarea')
            ->where('cotio_numcoti', $muestra->cotio_numcoti)
            ->where('cotio_item', $muestra->cotio_item)
            ->where('instance_number', $muestra->instance_number)
            ->where('cotio_subitem', '>', 0)
            ->get();

        foreach ($instanciasAnalitos as $analito) {
            if ($analito->tarea) {
                $itemCatalogo = static::buscarItemCatalogo($analito->tarea);
                if ($itemCatalogo) {
                    $todasLasNotas = $todasLasNotas->concat($itemCatalogo->notasPredeterminadas);
                }
            }
        }

        if ($todasLasNotas->isEmpty()) {
            return '';
        }

        // 4. Devolvemos el contenido de las notas, ordenadas por el campo 'orden'
        return $todasLasNotas->sortBy('orden')
            ->unique('contenido')
            ->map(function($nota) {
                $titulo = trim((string)$nota->titulo);
                $contenido = trim((string)$nota->contenido);

                if ($titulo !== '') {
                    return "**" . $titulo . "**: " . $contenido;
                }

                return $contenido;
            })
            ->map(fn($c) => trim((string)$c))
            ->filter()
            ->implode("\n\n");
    }

    /**
     * Busca el ítem del catálogo correspondiente a una línea de cotización.
     * Implementa una búsqueda robusta por ID o por Descripción + Límites.
     */
    protected static function buscarItemCatalogo($linea): ?\App\Models\CotioItems
    {
        if (!$linea) return null;

        // Determinamos si es una muestra o un componente según el subitem
        $esMuestra = ((int)$linea->cotio_subitem === 0);
        $baseQuery = \App\Models\CotioItems::query();
        if ($esMuestra) {
            $baseQuery->muestras();
        } else {
            $baseQuery->componentes();
        }

        $codigoProd = trim((string) ($linea->cotio_codigoprod ?? ''));
        
        // 1. Intento por ID (si el código es numérico y coincide con un ID)
        if ($codigoProd !== '' && is_numeric($codigoProd)) {
            $idNumerico = intval($codigoProd);
            $item = (clone $baseQuery)->find($idNumerico);
            if ($item) return $item;
        }

        // 2. Intento por Descripción + Límites (Best Effort para ítems legacy)
        $descripcion = trim((string) ($linea->cotio_descripcion ?? ''));
        if ($descripcion !== '') {
            $query = (clone $baseQuery)->where('cotio_descripcion', $descripcion);
            
            // Si tiene límite de detección, lo usamos para filtrar entre duplicados
            if ($linea->limite_deteccion) {
                $valorLimite = floatval($linea->limite_deteccion);
                $query->where(function($q) use ($valorLimite) {
                    $q->where('limites_establecidos', 'like', '%' . $valorLimite . '%')
                      ->orWhere('limite_cuantificacion', $valorLimite);
                });
            }
            
            $item = $query->first();
            if ($item) return $item;
            
            // Fallback final: solo descripción dentro del scope correcto
            return (clone $baseQuery)->where('cotio_descripcion', $descripcion)->first();
        }

        return null;
    }

    /**
     * Valores finales para el PDF (y para el formulario de edición).
     */
    public static function forPdf(CotioInstancia $muestra): array
    {
        $out = static::defaults($muestra);
        $o = $muestra->protocolo_informe_json;
        if (! is_array($o) || $o === []) {
            return $out;
        }
        foreach (self::KEYS as $k) {
            if (! array_key_exists($k, $o)) {
                continue;
            }
            $v = $o[$k];
            
            // Si el valor en el JSON es nulo o vacío, y es 'notas_informe', 
            // mantenemos el valor por defecto (que son las notas del catálogo)
            if (($v === null || trim((string)$v) === '') && $k === 'notas_informe') {
                continue;
            }

            if ($v === null) {
                continue;
            }
            $out[$k] = is_string($v) ? $v : (string) $v;
        }

        return $out;
    }
}
