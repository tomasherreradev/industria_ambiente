<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\InventarioLab;
use App\Models\CotioResponsable;
use Illuminate\Support\Facades\Auth;
use App\Models\CotioInstancia;
use App\Models\MetodoMuestreo;
use App\Models\MetodoAnalisis;
use App\Models\LeyNormativa;

class Cotio extends Model
{

    protected $table = 'cotio';
    protected $primaryKey = ['cotio_numcoti', 'cotio_item', 'cotio_subitem'];
    public $incrementing = false;
    protected $fillable = [
        'cotio_numcoti',
        'cotio_item',
        'cotio_subitem',
        'cotio_descripcion',
        'cotio_cantidad',
        'cotio_precio',
        'cotio_codigoprod',
        'cotio_codigoum',
        'cotio_codigometodo',
        'cotio_codigometodo_analisis',
        'limite_deteccion',
        'limite_cuantificacion',
        'ley_aplicacion',
        'cotio_nota_tipo',
        'cotio_nota_contenido',
        'req_cadena_custodia',
        'req_prot_mapba',
        'lleva_muestreo',
        'cotio_canal_especial',
        'de_agrupador',

    ];
    
    protected $casts = [
        'cotio_precio' => 'decimal:2',
        'limite_deteccion' => 'decimal:6',
        'limite_cuantificacion' => 'decimal:6',
        'de_agrupador' => 'boolean',
    ];
    
    public $timestamps = false;
    
    
    public function instancias()
    {
        return $this->hasMany(CotioInstancia::class, 'cotio_numcoti', 'cotio_numcoti')
                    ->whereColumn('cotio_item', 'cotio_item')
                    ->whereColumn('cotio_subitem', 'cotio_subitem');
    }

    public function getInstance($instanceNumber)
    {
        return $this->instancias()->where('instance_number', $instanceNumber)->first();
    }

    public function createInstance($instanceNumber)
    {
        $data = [
            'instance_number' => $instanceNumber,
            'responsable_muestreo' => Auth::user()->usu_codigo
        ];
        
        // Copiar ambos métodos siempre desde Cotio
        if ($this->cotio_codigometodo) {
            $data['cotio_codigometodo'] = $this->cotio_codigometodo;
        }
        if ($this->cotio_codigometodo_analisis) {
            $data['cotio_codigometodo_analisis'] = $this->cotio_codigometodo_analisis;
        }
        
        return $this->instancias()->create($data);
    }

    public function getOrCreateInstance($instanceNumber)
    {
        $defaults = ['responsable_muestreo' => Auth::user()->usu_codigo];
        
        // Copiar ambos métodos siempre desde Cotio
        if ($this->cotio_codigometodo) {
            $defaults['cotio_codigometodo'] = $this->cotio_codigometodo;
        }
        if ($this->cotio_codigometodo_analisis) {
            $defaults['cotio_codigometodo_analisis'] = $this->cotio_codigometodo_analisis;
        }
        
        return $this->instancias()->firstOrCreate(
            ['instance_number' => $instanceNumber],
            $defaults
        );
    }

    

    public function responsablesManual()
    {
        $responsables = CotioResponsable::where('cotio_numcoti', $this->cotio_numcoti)
            ->where('cotio_item', $this->cotio_item)
            ->where('cotio_subitem', $this->cotio_subitem)
            ->get();
    
        $responsables->load('usuario');
        

        return $responsables->map(function ($item) {
            return $item->usuario;
        })->filter()->values(); 
    }
    
    


    public function herramientas()
    {
        return $this->belongsToMany(
            InventarioLab::class,
            'cotio_inventario_lab',
            'cotio_numcoti', 
            'inventario_lab_id'
        )
        ->wherePivot('cotio_item', $this->cotio_item)
        ->wherePivot('cotio_subitem', $this->cotio_subitem)
        ->withPivot('cantidad', 'observaciones');
    }



    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'vehiculo_asignado');
    }


    public function cotizacion()
    {
        return $this->belongsTo(Coti::class, 'cotio_numcoti');
    }

    /** Alias legacy usado en eager loads (`muestra.cotizado`, `tarea.cotizado`). */
    public function cotizado()
    {
        return $this->cotizacion();
    }

    



    public function responsable()
    {
        return $this->belongsTo(User::class, 'cotio_responsable_codigo', 'usu_codigo');
    }

    /**
     * Relación con el ítem del catálogo (CotioItems)
     */
    public function itemCatalogo()
    {
        return $this->belongsTo(CotioItems::class, 'cotio_codigoprod', 'id');
    }

    /**
     * Relación con método de muestreo
     */
    public function metodoMuestreo()
    {
        return $this->belongsTo(MetodoMuestreo::class, 'cotio_codigometodo', 'codigo');
    }

    /**
     * Relación con método de análisis
     */
    public function metodoAnalisis()
    {
        return $this->belongsTo(MetodoAnalisis::class, 'cotio_codigometodo_analisis', 'codigo');
    }

    /**
     * Relación con ley/normativa aplicable
     */
    public function leyNormativa()
    {
        return $this->belongsTo(LeyNormativa::class, 'ley_aplicacion', 'codigo');
    }


    public function getIsAsignadaAttribute()
    {
        return !is_null($this->cotio_responsable_codigo);
    }




    protected function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if(!is_array($keys)){
            return parent::setKeysForSaveQuery($query);
        }

        foreach($keys as $keyName){
            $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
        }

        return $query;
    }

    protected function getKeyForSaveQuery($keyName = null)
    {
        if(is_null($keyName)){
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }

    /**
     * Normaliza una fila de nota desde JSON u orígenes heterogéneos (claves en inglés, etc.).
     */
    private static function normalizarFilaNotaArray(array $nota): ?array
    {
        $tipoRaw = $nota['tipo'] ?? $nota['type'] ?? $nota['nota_tipo'] ?? null;
        $tipo = ($tipoRaw !== null && $tipoRaw !== '') ? trim((string) $tipoRaw) : null;

        $cont = $nota['contenido'] ?? $nota['nota_contenido'] ?? $nota['text'] ?? $nota['body'] ?? $nota['mensaje'] ?? '';
        if (is_array($cont)) {
            $cont = json_encode($cont, JSON_UNESCAPED_UNICODE);
        }
        $cont = trim((string) $cont);

        if ($tipo === null && $cont === '') {
            return null;
        }

        return [
            'tipo' => $tipo,
            'contenido' => $cont,
        ];
    }

    private static function esTipoImprimible(?string $tipo): bool
    {
        $t = strtolower(trim((string) $tipo));

        return in_array($t, ['imprimible', 'nota_imprimible', 'printable', 'print'], true);
    }

    /**
     * Lista de notas: [ ['tipo' => ..., 'contenido' => ...], ... ].
     * Soporta: array JSON, un solo objeto, JSON doblemente codificado, texto plano con cotio_nota_tipo.
     */
    public function parsedNotasList(): array
    {
        $raw = $this->cotio_nota_contenido;
        if ($raw === null || trim((string) $raw) === '') {
            return [];
        }

        $str = trim((string) $raw);
        $decoded = json_decode($str, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (!empty($this->cotio_nota_tipo)) {
                return [['tipo' => $this->cotio_nota_tipo, 'contenido' => $str]];
            }

            return [];
        }

        if (is_string($decoded)) {
            $inner = json_decode($decoded, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $decoded = $inner;
            }
        }

        if (!is_array($decoded)) {
            if (!empty($this->cotio_nota_tipo)) {
                return [['tipo' => $this->cotio_nota_tipo, 'contenido' => $str]];
            }

            return [];
        }

        if ($decoded === []) {
            return [];
        }

        $list = [];
        if (array_is_list($decoded)) {
            $list = $decoded;
        } elseif (
            isset($decoded['tipo'])
            || isset($decoded['contenido'])
            || isset($decoded['type'])
            || isset($decoded['nota_contenido'])
            || isset($decoded['text'])
        ) {
            $list = [$decoded];
        } else {
            $list = array_values($decoded);
        }

        $out = [];
        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }
            $norm = self::normalizarFilaNotaArray($row);
            if ($norm !== null) {
                $out[] = $norm;
            }
        }

        return $out;
    }

    /**
     * Solo notas de tipo imprimible; si la fila JSON no trae tipo, se usa cotio_nota_tipo de la fila cotio.
     */
    public function notasImprimiblesList(): array
    {
        $defaultTipo = strtolower(trim((string) ($this->cotio_nota_tipo ?? '')));

        return collect($this->parsedNotasList())
            ->filter(function ($nota) use ($defaultTipo) {
                if (!is_array($nota)) {
                    return false;
                }
                $tipoNota = isset($nota['tipo']) && $nota['tipo'] !== null && $nota['tipo'] !== ''
                    ? strtolower(trim((string) $nota['tipo']))
                    : '';
                $effective = $tipoNota !== '' ? $tipoNota : $defaultTipo;

                if (!self::esTipoImprimible($effective)) {
                    return false;
                }

                return trim((string) ($nota['contenido'] ?? '')) !== '';
            })
            ->map(function ($nota) {
                return [
                    'tipo' => $nota['tipo'] ?? 'imprimible',
                    'contenido' => (string) ($nota['contenido'] ?? ''),
                ];
            })
            ->values()
            ->all();
    }

   public static function actualizarEstadoCategoria($cotio_numcoti, $cotio_item)
{
    $categoria = self::where([
        'cotio_numcoti' => $cotio_numcoti,
        'cotio_item' => $cotio_item,
        'cotio_subitem' => 0
    ])->first();

    if (!$categoria) {
        return;
    }

    $tareas = self::where('cotio_numcoti', $cotio_numcoti)
        ->where('cotio_item', $cotio_item)
        ->where('cotio_subitem', '>', 0)
        ->where('active_muestreo', true)
        ->get();

    if ($tareas->isEmpty()) {
        return;
    }

    $todosFinalizados = $tareas->every(function ($tarea) {
        return strtolower($tarea->cotio_estado) === 'finalizado';
    });

    $todosPendientes = $tareas->every(function ($tarea) {
        return strtolower($tarea->cotio_estado) === 'pendiente';
    });

    if ($todosFinalizados) {
        $categoria->cotio_estado = 'finalizado';
        $vehiculo = Vehiculo::find($categoria->vehiculo_asignado);
        if ($vehiculo) {
            $vehiculo->estado = 'libre';
            $vehiculo->save();
        }
        $categoria->vehiculo_asignado = null;
    } elseif ($todosPendientes) {
        $categoria->cotio_estado = 'pendiente';
    } else {
        $categoria->cotio_estado = 'en proceso';
    }

    $categoria->save();
}
    
}
