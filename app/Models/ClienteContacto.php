<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClienteContacto extends Model
{
    protected $table = 'cliente_contactos';

    protected $fillable = [
        'cli_codigo',
        'nombre',
        'telefono',
        'email',
        'tipo',
    ];

    public function cliente()
    {
        return $this->belongsTo(Clientes::class, 'cli_codigo', 'cli_codigo');
    }
}

