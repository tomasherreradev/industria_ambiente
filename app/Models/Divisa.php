<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Divisa extends Model
{
    protected $table = 'divisas';

    public $timestamps = false;

    protected $fillable = [
        'divisa_codigo',
        'divisa_desc',
    ];
}
