<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CotiVersion extends Model
{
    use HasFactory;

    protected $table = 'coti_versions';
    
    protected $fillable = [
        'coti_num',
        'version',
        'fecha_version',
        'coti_data',
        'cotio_data',
    ];

    protected $casts = [
        'fecha_version' => 'date',
        'coti_data' => 'array',
        'cotio_data' => 'array',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Ventas::class, 'coti_num', 'coti_num');
    }
}
