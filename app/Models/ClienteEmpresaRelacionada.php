<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClienteEmpresaRelacionada extends Model
{
    protected $table = 'cliente_empresas_relacionadas';
    
    protected $fillable = [
        'cli_codigo',
        'razon_social',
        'cuit',
        'direcciones',
        'localidad',
        'partido',
        'contacto',
    ];

    public function cliente()
    {
        return $this->belongsTo(Clientes::class, 'cli_codigo', 'cli_codigo');
    }
}
