<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotioItemsNota extends Model
{
    protected $table = 'cotio_items_notas';

    protected $fillable = [
        'cotio_item_id',
        'titulo',
        'contenido',
        'orden',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'orden' => 'integer',
    ];

    public function item()
    {
        return $this->belongsTo(CotioItems::class, 'cotio_item_id', 'id');
    }
}
