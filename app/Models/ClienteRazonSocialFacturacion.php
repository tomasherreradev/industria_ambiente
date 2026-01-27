<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClienteRazonSocialFacturacion extends Model
{
    protected $table = 'cliente_razones_sociales_facturacion';
    
    protected $fillable = [
        'cli_codigo',
        'razon_social',
        'cuit',
        'direccion',
        'condicion_iva',
        'condicion_iva_desc',
        'condicion_pago',
        'condicion_pago_desc',
        'tipo_factura',
        'es_predeterminada',
    ];

    protected $casts = [
        'es_predeterminada' => 'boolean',
    ];

    public function cliente()
    {
        return $this->belongsTo(Clientes::class, 'cli_codigo', 'cli_codigo');
    }
}
