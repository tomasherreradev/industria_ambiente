<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Relations\BelongsToCotioLine;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use App\Models\InstanciaResponsableMuestreo;
use App\Models\InstanciaResponsableAnalisis;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class CotioInstancia extends Model
{
    protected $table = 'cotio_instancias';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'cotio_numcoti', 
        'cotio_item', 
        'cotio_subitem', 
        'cotio_descripcion',
        'cotio_codigometodo',
        'cotio_codigometodo_analisis',
        'instance_number',
        'fecha_muestreo', 
        'observaciones',
        'observaciones_medicion_muestreador',
        'observaciones_medicion_coord_muestreo',
        'observaciones_muestreo_coord',
        'observaciones_muestreo_muestreador',
        'resultado', 
        'resultado_2',
        'resultado_3',
        'resultado_final',
        // 'observaciones_medicion',
        'completado', 
        'enable_muestreo', 
        'fecha_inicio_muestreo',
        'fecha_fin_muestreo',
        'fecha_inicio_ot', 
        'fecha_fin_ot', 
        'cotio_estado', 
        'cotio_identificacion',
        'fecha_identificacion',
        'volumen_muestra',
        'vehiculo_asignado',
        'cotio_observaciones_suspension',
        'image',
        'active_ot',
        'latitud',
        'longitud',
        'nro_precinto',
        'nro_cadena',
        'coordinador_codigo',
        'enable_inform',
        'enable_modulo_mediciones',
        'enable_ot',
        'cotio_estado_analisis',
        'observacion_resultado',
        'observacion_resultado_2',
        'observacion_resultado_3',
        'observacion_resultado_final',
        'responsable_resultado_1',
        'responsable_resultado_2',
        'responsable_resultado_3',
        'responsable_resultado_final',
        'observaciones_ot',
        'fecha_carga_ot',
        'monto',
        'facturado',
        'es_priori',
        'cotio_codigoum',
        'time_annulled',
        'request_review',
        'observaciones_request_review',
        'fecha_carga_resultado_1',
        'fecha_carga_resultado_2',
        'fecha_carga_resultado_3',
        'coordinador_codigo_lab',
        'aprobado_informe',
        'fecha_aprobacion_informe',
        'aprobado_informe_usuario',
        'facturacion_aprobada',
        'fecha_facturacion_aprobada',
        'facturacion_aprobada_usuario',
        'firmado',
        'listo_para_firmar',
        'fecha_listo_para_firmar',
        'listo_para_firmar_usuario',
        'identificador_documento_firma',
        'fecha_firma',
        'image_resultado_final',
        'cotio_codigometodo',
        'cotio_codigometodo_analisis',
        'otn',
        'protocolo_informe_json',
        'analista_fecha_inicio',
        'analista_fecha_fin',
        'archivo_informe',
    ];

    protected $casts = [
        'fecha_muestreo' => 'datetime',
        'completado' => 'boolean',
        'enable_muestreo' => 'boolean',
        'fecha_inicio_muestreo' => 'datetime', 
        'fecha_fin_muestreo' => 'datetime',
        'fecha_inicio_ot' => 'datetime', 
        'fecha_fin_ot' => 'datetime',
        'fecha_carga_ot' => 'datetime',
        'fecha_identificacion' => 'datetime',
        'enable_ot' => 'boolean',
        'enable_modulo_mediciones' => 'boolean',
        'es_priori' => 'boolean',
        'aprobado_informe' => 'boolean',
        'facturacion_aprobada' => 'boolean',
        'firmado' => 'boolean',
        'listo_para_firmar' => 'boolean',
        'fecha_listo_para_firmar' => 'datetime',
        'fecha_firma' => 'datetime',
        'fecha_aprobacion_informe' => 'datetime',
        'fecha_facturacion_aprobada' => 'datetime',
        'protocolo_informe_json' => 'array',
        'analista_fecha_inicio' => 'date',
        'analista_fecha_fin' => 'date',
    ];

    public function responsablesMuestreo()
    {
        return $this->belongsToMany(
            User::class,
            'instancia_responsable_muestreo',
            'cotio_instancia_id',
            'usu_codigo',
            'id',
            'usu_codigo'
        )->using(InstanciaResponsableMuestreo::class)
          ->withTimestamps()
          ->withPivot(['created_at', 'updated_at']);
    }

    public function responsablesAnalisis()
    {
        return $this->belongsToMany(
            User::class,
            'instancia_responsable_analisis',
            'cotio_instancia_id',
            'usu_codigo',
            'id',
            'usu_codigo'
        )->using(InstanciaResponsableAnalisis::class)
          ->withTimestamps()
          ->withPivot(['created_at', 'updated_at']);
    }

    /**
     * Códigos exactos de usu asignados como responsables de análisis (evita pluck ambiguo en PostgreSQL).
     *
     * @return array<int, string>
     */
    public function codigosResponsablesAnalisisAsignados(): array
    {
        return $this->responsablesAnalisis()
            ->get()
            ->map(fn (User $usuario) => $usuario->usu_codigo)
            ->values()
            ->all();
    }

    public function valoresVariables()
    {
        return $this->hasMany(CotioValorVariable::class, 'cotio_instancia_id');
    }

    public function adjuntos()
    {
        return $this->hasMany(CotioInstanciaAdjunto::class, 'cotio_instancia_id');
    }

    public function muestraRaw()
    {
        return $this->belongsTo(CotioInstancia::class, 'cotio_numcoti', 'cotio_numcoti')
                   ->where('cotio_item', $this->cotio_item)
                   ->where('cotio_subitem', 0);
    }

    
    
    /**
     * Fila Cotio de la muestra (cotio_subitem = 0) del mismo ítem de cotización.
     * @see BelongsToCotioLine (evita belongsTo con claves compuestas sobre Cotio y su PK compuesta)
     */
    public function muestra()
    {
        $related = $this->newRelatedInstance(Cotio::class);

        return new BelongsToCotioLine(
            $related->newQuery(),
            $this,
            'cotio_numcoti',
            'cotio_numcoti',
            __FUNCTION__,
            true
        );
    }

    public function cotizacion()
    {
        return $this->belongsTo(Coti::class, 'cotio_numcoti', 'coti_num');
    }


    public function gemelos()
    {
        return $this->newQuery()
            ->where('cotio_numcoti', $this->cotio_numcoti)
            ->where('cotio_item', $this->cotio_item)
            ->where('cotio_subitem', $this->cotio_subitem)
            ->where('instance_number', '!=', $this->instance_number)
            ->where('enable_ot', true)
            ->get();
    }

    public function tarea()
    {
        $related = $this->newRelatedInstance(Cotio::class);

        return new BelongsToCotioLine(
            $related->newQuery(),
            $this,
            'cotio_numcoti',
            'cotio_numcoti',
            __FUNCTION__,
            false
        );
    }

    public function tareas()
    {
        return $this->hasMany(CotioInstancia::class, 'cotio_numcoti', 'cotio_numcoti')
                    ->where('cotio_item', $this->cotio_item)
                    ->where('instance_number', $this->instance_number)
                    ->where('cotio_subitem', '>', 0); // Fetch analyses
    }

    public function coordinador()
    {
        return $this->belongsTo(User::class, 'coordinador_codigo', 'usu_codigo');
    }

    public function coordinadorLab()
    {
        return $this->belongsTo(User::class, 'coordinador_codigo_lab', 'usu_codigo');
    }

    public function aprobadorInforme()
    {
        return $this->belongsTo(User::class, 'aprobado_informe_usuario', 'usu_codigo');
    }

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'vehiculo_asignado');
    }

    public function herramientas(): BelongsToMany
    {
        return $this->belongsToMany(
            InventarioMuestreo::class,
            'cotio_inventario_muestreo',
            'cotio_instancia_id', 
            'inventario_muestreo_id'
        )->withPivot([
            'cantidad', 
            'observaciones',
            'cotio_numcoti',
            'cotio_item',
            'cotio_subitem',
            'instance_number',
        ]);
    }

    // Método para obtener herramientas de muestreo con información del pivote
    public function getHerramientasMuestreo()
    {
        return InventarioMuestreo::join('cotio_inventario_muestreo', 'inventario_muestreo.id', '=', 'cotio_inventario_muestreo.inventario_muestreo_id')
            ->where('cotio_inventario_muestreo.cotio_numcoti', $this->cotio_numcoti)
            ->where('cotio_inventario_muestreo.cotio_item', $this->cotio_item)
            ->where('cotio_inventario_muestreo.cotio_subitem', $this->cotio_subitem)
            ->where('cotio_inventario_muestreo.instance_number', $this->instance_number)
            ->select(
                'inventario_muestreo.*',
                'cotio_inventario_muestreo.cantidad',
                'cotio_inventario_muestreo.observaciones as pivot_observaciones'
            )
            ->get();
    }

    public function getImageUrlAttribute()
    {
        return $this->image ? Storage::url('images/' . $this->image) : null;
    }

    public function herramientasLab()
    {
        return $this->belongsToMany(
            InventarioLab::class,
            'cotio_inventario_lab',
            'cotio_instancia_id',   
            'inventario_lab_id'
        )
        ->withPivot(['cantidad', 'observaciones', 'cotio_numcoti', 'cotio_item', 'cotio_subitem', 'instance_number']);
    }

    public function variablesMuestreo()
    {
        return $this->hasMany(CotioValorVariable::class, 'cotio_instancia_id');
    }


    protected static function booted()
{
        static::updated(function ($instancia) {
            if (($instancia->isDirty('cotio_estado') || $instancia->isDirty('cotio_estado_analisis')) && $instancia->coordinador_codigo) {
                $tipoEstado = $instancia->isDirty('cotio_estado') ? 'muestreo' : 'análisis';
                $nuevoEstado = $instancia->isDirty('cotio_estado') ? 
                    $instancia->cotio_estado : $instancia->cotio_estado_analisis;
                
                $usuario = Auth::user();

                if (! $usuario) {
                    return;
                }

                SimpleNotification::create([
                    'coordinador_codigo' => $instancia->coordinador_codigo,
                    'sender_codigo' => $usuario->usu_codigo,
                    'instancia_id' => $instancia->id,
                    'mensaje' => sprintf(
                        '%s cambió el estado de %s a "%s" para la muestra "%s" de la cotización %s',
                        $usuario->usu_descripcion,
                        $tipoEstado,
                        $nuevoEstado,
                        $instancia->cotio_descripcion,
                        $instancia->cotio_numcoti
                    ),
                    'url' => SimpleNotification::generarUrlPorRol($instancia->coordinador_codigo, $instancia->id),
                ]);
            }
        });
    }


    public function coti()
    {
        return $this->belongsTo(Coti::class, 'cotio_numcoti', 'coti_num');
    }

    /**
     * Genera el siguiente número OT correlativo
     * Solo para muestras (cotio_subitem = 0)
     * 
     * @return string Número OT en formato '0000000001', '0000000002', etc.
     */
    public static function generarNumeroOT()
    {
        // Obtener el último número OT asignado (solo para muestras)
        $ultimoOT = self::where('cotio_subitem', 0)
            ->whereNotNull('otn')
            ->orderBy('otn', 'desc')
            ->value('otn');

        if ($ultimoOT) {
            // Convertir a entero, incrementar y formatear
            $siguienteNumero = (int) $ultimoOT + 1;
        } else {
            // Si no hay ningún OT, empezar desde 1
            $siguienteNumero = 1;
        }

        // Formatear con ceros a la izquierda (10 dígitos)
        return str_pad($siguienteNumero, 10, '0', STR_PAD_LEFT);
    }

    /**
     * Determina si esta instancia debe llevar un número OT (otn).
     * Se excluyen canales especiales: asp, clarke_fire, consultoria.
     */
    public function debeLlevarOTN()
    {
        // Solo aplica a muestras (subitem 0)
        if ($this->cotio_subitem != 0) return false;

        // Intentar obtener el canal especial desde la tarea (cotio)
        $this->loadMissing('tarea');
        $canal = trim((string) ($this->tarea->cotio_canal_especial ?? ''));

        return ! in_array($canal, ['asp', 'clarke_fire', 'consultoria'], true);
    }

    /**
     * Asigna número OT al aprobar para facturación (todos los canales, incl. consultoría, ASP y Clarke).
     */
    public function asignarOtnParaFacturacionSiPendiente(): bool
    {
        if ((int) $this->cotio_subitem !== 0) {
            return false;
        }

        if (trim((string) ($this->otn ?? '')) !== '') {
            return false;
        }

        $this->otn = self::generarNumeroOT();

        return true;
    }

    public function metodoAnalisis()
    {
        return $this->belongsTo(Metodo::class, 'cotio_codigometodo_analisis', 'metodo_codigo');
    }

    public function metodoMuestreo()
    {
        return $this->belongsTo(Metodo::class, 'cotio_codigometodo', 'metodo_codigo');
    }

    /**
     * Obtener el método de análisis con trim automático
     * Este método se usa cuando la relación normal no funciona debido a espacios
     */
    public function getMetodoAnalisisConTrim()
    {
        // Primero intentar con la relación normal
        $metodo = $this->metodoAnalisis;
        
        // Si no se encontró y hay código, buscar con trim
        if (!$metodo && !empty($this->cotio_codigometodo_analisis)) {
            $codigo = trim($this->cotio_codigometodo_analisis);
            if (!empty($codigo)) {
                $metodo = Metodo::whereRaw('TRIM(metodo_codigo) = ?', [$codigo])->first();
            }
        }
        
        return $metodo;
    }

    /**
     * Obtener el método de muestreo con trim automático
     * Este método se usa cuando la relación normal no funciona debido a espacios
     */
    public function getMetodoMuestreoConTrim()
    {
        // Primero intentar con la relación normal
        $metodo = $this->metodoMuestreo;
        
        // Si no se encontró y hay código, buscar con trim
        if (!$metodo && !empty($this->cotio_codigometodo)) {
            $codigo = trim($this->cotio_codigometodo);
            if (!empty($codigo)) {
                $metodo = Metodo::whereRaw('TRIM(metodo_codigo) = ?', [$codigo])->first();
            }
        }
        
        return $metodo;
    }

}