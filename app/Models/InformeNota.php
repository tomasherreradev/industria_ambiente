<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InformeNota extends Model
{
    protected $table = 'informe_notas';

    protected $fillable = [
        'titulo',
        'contenido',
        'orden',
        'activa',
    ];

    protected $casts = [
        'orden' => 'integer',
        'activa' => 'boolean',
    ];

    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    public function scopeOrdenadas($query)
    {
        return $query->orderBy('orden')->orderBy('id');
    }
}
