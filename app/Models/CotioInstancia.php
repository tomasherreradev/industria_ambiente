<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'firmado',
        'identificador_documento_firma',
        'fecha_firma',
        'image_resultado_final',
        'cotio_codigometodo',
        'cotio_codigometodo_analisis',
        'otn',
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
        'es_priori' => 'boolean',
        'aprobado_informe' => 'boolean',
        'firmado' => 'boolean',
        'fecha_firma' => 'datetime',
        'fecha_aprobacion_informe' => 'datetime',
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

    public function valoresVariables()
    {
        return $this->hasMany(CotioValorVariable::class, 'cotio_instancia_id');
    }

    public function muestraRaw()
    {
        return $this->belongsTo(CotioInstancia::class, 'cotio_numcoti', 'cotio_numcoti')
                   ->where('cotio_item', $this->cotio_item)
                   ->where('cotio_subitem', 0);
    }

    
    
    public function muestra()
    {
        return $this->belongsTo(Cotio::class, 'cotio_numcoti', 'cotio_numcoti')
                   ->where('cotio_item', $this->cotio_item)
                   ->where('cotio_subitem', 0);
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
        return $this->belongsTo(Cotio::class, 'cotio_numcoti', 'cotio_numcoti')
                   ->where('cotio_item', $this->cotio_item)
                   ->where('cotio_subitem', $this->cotio_subitem);
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