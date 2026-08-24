<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

class AdjuntosArchivoValidacion
{
    public const MIME_PERMITIDOS = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    public const EXTENSIONES_PERMITIDAS = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

    public const TAMANO_MAX_KB = 10240;

    public static function reglasArchivo(): string
    {
        return 'file|mimes:pdf,jpg,jpeg,png,gif,webp|max:' . self::TAMANO_MAX_KB;
    }

    public static function esValido(UploadedFile $file): bool
    {
        if (! $file->isValid()) {
            return false;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, self::EXTENSIONES_PERMITIDAS, true)) {
            return false;
        }

        return $file->getSize() <= (self::TAMANO_MAX_KB * 1024);
    }

    public static function nombreSeguro(UploadedFile $file): string
    {
        $nombre = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName()) ?: 'archivo';

        return time() . '_' . $nombre;
    }
}
