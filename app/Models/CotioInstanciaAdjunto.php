<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CotioInstanciaAdjunto extends Model
{
    protected $table = 'cotio_instancia_adjuntos';

    protected $fillable = [
        'cotio_instancia_id',
        'path',
        'original_name',
        'mime',
        'size',
        'context',
        'uploaded_by',
    ];

    public function instancia(): BelongsTo
    {
        return $this->belongsTo(CotioInstancia::class, 'cotio_instancia_id');
    }

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
