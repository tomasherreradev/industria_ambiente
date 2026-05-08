<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CondicionPago extends Model
{
    protected $table = 'pag';
    protected $primaryKey = 'pag_codigo';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public function getRouteKeyName()
    {
        return 'pag_codigo';
    }

    protected $fillable = [
        'pag_codigo',
        'pag_descripcion',
        'pag_descuento1',
        'pag_descuento2',
        'pag_interes',
        'pag_cuotas',
        'pag_dias',
        'pag_vencimiento',
        'pag_anticipo',
        'pag_clienteproveedor',
        'pag_estado'
    ];

    protected $casts = [
        'pag_descuento1' => 'decimal:2',
        'pag_descuento2' => 'decimal:2',
        'pag_interes' => 'decimal:2',
        'pag_cuotas' => 'integer',
        'pag_dias' => 'integer',
        'pag_vencimiento' => 'boolean',
        'pag_anticipo' => 'boolean',
        'pag_estado' => 'boolean'
    ];

    public function clientes()
    {
        return $this->hasMany(Clientes::class, 'cli_codigopag', 'pag_codigo');
    }
}

