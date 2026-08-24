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

    public static function esTipoEnvioFactura(?string $tipo): bool
    {
        $normalizado = mb_strtolower(trim((string) $tipo));

        return in_array($normalizado, ['envío de factura', 'envio de factura'], true);
    }

    public function scopeEnvioFactura($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('tipo', ['Envío de factura', 'Envio de factura'])
                ->orWhereRaw("LOWER(LTRIM(RTRIM(tipo))) IN ('envío de factura', 'envio de factura')");
        });
    }
}

