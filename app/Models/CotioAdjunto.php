<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CotioAdjunto extends Model
{
    protected $table = 'cotio_adjuntos';

    protected $fillable = [
        'cotio_numcoti',
        'cotio_item',
        'path',
        'original_name',
        'mime',
        'size',
        'uploaded_by',
    ];

    public function url(): ?string
    {
        if ($this->path === null || trim($this->path) === '') {
            return null;
        }

        return Storage::disk('public')->url($this->path);
    }

    public function esImagen(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
