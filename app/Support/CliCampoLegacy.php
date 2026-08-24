<?php

namespace App\Support;

/**
 * Campos de texto fijo en la tabla legacy `cli` (sistema anterior).
 * Trunca y rellena para no superar el tamaño de columna en PostgreSQL.
 */
final class CliCampoLegacy
{
    public const EMAIL = 120;

    public const CONTACTO = 120;

    public const TELEFONO = 50;

    public const RAZON_SOCIAL = 255;

    public const FANTASIA = 255;

    public const DIRECCION = 255;

    /**
     * Texto libre en columnas varchar (sin relleno a ancho fijo).
     */
    public static function truncarTexto(?string $value, int $maxLength, bool $required = false): ?string
    {
        $trimmed = mb_substr(trim((string) $value), 0, $maxLength);

        if ($required) {
            return $trimmed;
        }

        return $trimmed === '' ? null : $trimmed;
    }

    /** Contacto principal: legacy exige char relleno aunque el nombre esté vacío. */
    public static function padContacto(?string $value): string
    {
        $trimmed = mb_substr(trim((string) $value), 0, self::CONTACTO);

        return str_pad($trimmed, self::CONTACTO, ' ', STR_PAD_RIGHT);
    }

    public static function padNullable(?string $value, int $length): ?string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        return str_pad(mb_substr($trimmed, 0, $length), $length, ' ', STR_PAD_RIGHT);
    }

    /**
     * @param  array{nombre?: mixed, telefono?: mixed, email?: mixed}  $contacto
     */
    public static function aplicarContactoPrincipalEnCli(object $cliente, array $contacto): void
    {
        $cliente->cli_contacto = self::padContacto($contacto['nombre'] ?? '');
        $cliente->cli_telefono = self::padNullable($contacto['telefono'] ?? null, self::TELEFONO);
        $cliente->cli_email = self::padNullable($contacto['email'] ?? null, self::EMAIL);
    }
}
