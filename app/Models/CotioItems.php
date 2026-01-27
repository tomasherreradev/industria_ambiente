<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Matriz;
use App\Models\Metodo;

class CotioItems extends Model
{
    protected $table = 'cotio_items';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id', // IMPORTANTE: la tabla legacy no tiene autoincrement; se asigna manualmente
        'cotio_descripcion',
        'es_muestra',
        'agregable_a_comps',
        'limites_establecidos',
        'limite_cuantificacion',
        'metodo',
        'metodo_muestreo',
        'matriz_codigo',
        'unidad_medida',
        'precio'
    ];

    protected $casts = [
        'es_muestra' => 'boolean',
        'agregable_a_comps' => 'boolean',
        'precio' => 'decimal:2',
        'limite_cuantificacion' => 'decimal:6'
    ];

    /**
     * Scope para obtener solo muestras (ensayos)
     */
    public function scopeMuestras($query)
    {
        return $query->where('es_muestra', true);
    }

    /**
     * Scope para obtener solo componentes (análisis)
     */
    public function scopeComponentes($query)
    {
        return $query->where('es_muestra', false);
    }

    /**
     * Scope para buscar por descripción
     */
    public function scopeBuscar($query, $termino)
    {
        return $query->where('cotio_descripcion', 'ILIKE', '%' . $termino . '%');
    }

    /**
     * Relación con el método analítico asociado
     */
    public function metodoAnalitico()
    {
        return $this->belongsTo(Metodo::class, 'metodo', 'metodo_codigo');
    }
    

    public function componentesAsociados()
    {
        return $this->belongsToMany(self::class, 'cotio_item_component', 'agrupador_id', 'componente_id')
            ->withTimestamps();
    }

    public function agrupadores()
    {
        return $this->belongsToMany(self::class, 'cotio_item_component', 'componente_id', 'agrupador_id')
            ->withTimestamps();
    }

    /**
     * Relación con la matriz asociada (directa, para agrupadores).
     */
    public function matriz()
    {
        return $this->belongsTo(Matriz::class, 'matriz_codigo', 'matriz_codigo');
    }

    /**
     * Relación many-to-many con matrices a través de la tabla pivote.
     * Permite que componentes y agrupadores estén relacionados con múltiples matrices.
     */
    public function matrices()
    {
        return $this->belongsToMany(Matriz::class, 'cotio_items_matriz', 'cotio_item_id', 'matriz_codigo')
            ->withTimestamps();
    }

    /**
     * Relación con el historial de precios
     */
    public function historialPrecios()
    {
        return $this->hasMany(CotioItemPrecioHistorial::class, 'item_id');
    }

    /**
     * Relación con el método de muestreo asociado (usa la misma tabla metodo)
     */
    public function metodoMuestreo()
    {
        return $this->belongsTo(Metodo::class, 'metodo_muestreo', 'metodo_codigo');
    }
}
