<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehiculo extends Model
{

    const ESTADO_LIBRE = 'libre';
    const ESTADO_OCUPADO = 'ocupado';

    protected $table = 'vehiculos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'marca',
        'modelo',
        'anio',
        'patente',
        'tipo',
        'descripcion',
        'estado',
        'ultimo_mantenimiento',
        'estado_gral',
    ];

    public $timestamps = true;

    public function tareas()
    {
        return $this->hasMany(CotioInstancia::class, 'vehiculo_asignado');
    }

    public function cotioInstancias()
    {
        return $this->hasMany(CotioInstancia::class, 'vehiculo_asignado', 'id');
    }
}
