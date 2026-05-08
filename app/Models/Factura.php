<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Factura extends Model
{
    protected $table = 'facturas';

    protected $fillable = [
        'cotizacion_id',
        'numero_factura',
        'cotio_descripcion',
        'instance_number',
        'cae',
        'fecha_emision',
        'fecha_vencimiento_cae',
        'monto_total',
        'items',
        'estado',
        'cliente_razon_social',
        'cliente_cuit',
        'pdf_url',
        'observaciones'
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'fecha_vencimiento_cae' => 'date',
        'monto_total' => 'decimal:2',
        'items' => 'json'
    ];

    /**
     * Relación con la cotización
     */
    public function cotizacion()
    {
        return $this->belongsTo(Coti::class, 'cotizacion_id', 'coti_num');
    }

    /**
     * Scope para facturas por estado
     */
    public function scopeByEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    /**
     * Obtener el estado formateado
     */
    public function getEstadoFormateadoAttribute()
    {
        $estados = [
            'pendiente' => 'Pendiente',
            'aprobada' => 'Aprobada',
            'rechazada' => 'Rechazada',
            'anulada' => 'Anulada'
        ];

        return $estados[$this->estado] ?? ucfirst($this->estado);
    }

    /**
     * Obtener el monto total formateado
     */
    public function getMontoTotalFormateadoAttribute()
    {
        return '$' . number_format($this->monto_total, 2, ',', '.');
    }
}