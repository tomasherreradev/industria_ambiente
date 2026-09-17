<?php

namespace App\Support;

use App\Models\ClienteContacto;
use App\Models\Coti;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CotizacionContactosFacturacion
{
    public const TIPO_ENVIO_FACTURA_COTI = 'Envio de factura';

    public static function normalizarEmail(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    public static function esTipoEnvioFactura(?string $tipo): bool
    {
        $normalizado = mb_strtolower(trim((string) $tipo));

        return in_array($normalizado, ['envio de factura', 'envío de factura'], true);
    }

    /**
     * @return array<int, array{slot: int, nombre: string, correo: string, telefono: string, tipo: string}>
     */
    public static function contactosDesdeCoti(Coti $cotizacion): array
    {
        $slots = [
            [1, $cotizacion->coti_contacto, $cotizacion->coti_mail1, $cotizacion->coti_telefono, $cotizacion->coti_contacto_tipo1],
            [2, $cotizacion->coti_contacto2, $cotizacion->coti_mail2, $cotizacion->coti_telefono2, $cotizacion->coti_contacto_tipo2],
            [3, $cotizacion->coti_contacto3, $cotizacion->coti_mail3, $cotizacion->coti_telefono3, $cotizacion->coti_contacto_tipo3],
            [4, $cotizacion->coti_contacto4, $cotizacion->coti_mail4, $cotizacion->coti_telefono4, $cotizacion->coti_contacto_tipo4],
        ];

        $out = [];
        foreach ($slots as [$slot, $nombre, $correo, $telefono, $tipo]) {
            $correo = trim((string) $correo);
            $nombre = trim((string) $nombre);
            $telefono = trim((string) $telefono);
            $tipo = trim((string) $tipo);

            if ($nombre === '' && $correo === '' && $telefono === '') {
                continue;
            }

            $out[] = [
                'slot' => $slot,
                'nombre' => $nombre,
                'correo' => $correo,
                'telefono' => $telefono,
                'tipo' => $tipo,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{slot: int, nombre: string, correo: string, telefono: string, tipo: string}>
     */
    public static function contactosEnvioFacturaCoti(Coti $cotizacion): array
    {
        return array_values(array_filter(
            self::contactosDesdeCoti($cotizacion),
            fn (array $c) => self::esTipoEnvioFactura($c['tipo'] ?? '')
                && self::normalizarEmail($c['correo'] ?? '') !== ''
        ));
    }

    /**
     * @param  Collection<int, ClienteContacto>  $contactosCliente
     * @return array{
     *     contactos_coti: array,
     *     emails_coti: array<int, string>,
     *     emails_coincidentes: array<int, string>,
     *     requiere_seleccion: bool,
     *     tiene_envio_configurado: bool
     * }
     */
    public static function estadoEnvioFactura(Coti $cotizacion, Collection $contactosCliente): array
    {
        $contactosCoti = self::contactosEnvioFacturaCoti($cotizacion);
        $emailsCoti = collect($contactosCoti)
            ->pluck('correo')
            ->map(fn ($e) => self::normalizarEmail($e))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $emailsCliente = $contactosCliente
            ->map(fn ($c) => self::normalizarEmail($c->email ?? ''))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'contactos_coti' => $contactosCoti,
            'emails_coti' => $emailsCoti,
            'emails_coincidentes' => array_values(array_intersect($emailsCoti, $emailsCliente)),
            'requiere_seleccion' => count($emailsCoti) === 0,
            'tiene_envio_configurado' => count($emailsCoti) > 0,
        ];
    }

    /**
     * Emails válidos para envío: cotización (envío factura) + contactos del cliente.
     *
     * @return Collection<string, string> normalizado => email
     */
    public static function emailsPermitidosParaEnvio(Coti $cotizacion, Collection $contactosCliente): Collection
    {
        $map = collect();

        foreach (self::contactosEnvioFacturaCoti($cotizacion) as $contacto) {
            $email = trim((string) ($contacto['correo'] ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $map->put(self::normalizarEmail($email), $email);
            }
        }

        foreach ($contactosCliente as $contacto) {
            $email = trim((string) ($contacto->email ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $map->put(self::normalizarEmail($email), $email);
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string>|null  $seleccionRequest
     * @return array<int, string>
     */
    public static function resolverEmailsDestino(Coti $cotizacion, Collection $contactosCliente, ?array $seleccionRequest = null): array
    {
        $estado = self::estadoEnvioFactura($cotizacion, $contactosCliente);
        $permitidos = self::emailsPermitidosParaEnvio($cotizacion, $contactosCliente);

        if ($seleccionRequest !== null) {
            $emails = [];
            foreach (collect($seleccionRequest)->map(fn ($e) => self::normalizarEmail($e))->filter()->unique() as $norm) {
                if ($permitidos->has($norm)) {
                    $emails[] = $permitidos->get($norm);
                }
            }

            return array_values(array_unique($emails));
        }

        if ($estado['tiene_envio_configurado']) {
            return collect($estado['contactos_coti'])
                ->pluck('correo')
                ->map(fn ($e) => trim((string) $e))
                ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
                ->unique()
                ->values()
                ->all();
        }

        return [];
    }

    /**
     * Actualiza en la cotización los contactos tipo «Envío de factura» según la selección del facturador.
     *
     * @param  array<int, string>  $emailsSeleccionados
     */
    public static function aplicarSeleccionEnvioFacturaEnCoti(Coti $cotizacion, Collection $contactosCliente, array $emailsSeleccionados): void
    {
        $emailsSeleccionados = collect($emailsSeleccionados)
            ->map(fn ($e) => trim((string) $e))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();

        if ($emailsSeleccionados === []) {
            return;
        }

        $slotsDef = [
            ['contacto' => 'coti_contacto', 'mail' => 'coti_mail1', 'tel' => 'coti_telefono', 'tipo' => 'coti_contacto_tipo1'],
            ['contacto' => 'coti_contacto2', 'mail' => 'coti_mail2', 'tel' => 'coti_telefono2', 'tipo' => 'coti_contacto_tipo2'],
            ['contacto' => 'coti_contacto3', 'mail' => 'coti_mail3', 'tel' => 'coti_telefono3', 'tipo' => 'coti_contacto_tipo3'],
            ['contacto' => 'coti_contacto4', 'mail' => 'coti_mail4', 'tel' => 'coti_telefono4', 'tipo' => 'coti_contacto_tipo4'],
        ];

        foreach ($slotsDef as $fields) {
            if (self::esTipoEnvioFactura($cotizacion->{$fields['tipo']} ?? '')) {
                $cotizacion->{$fields['contacto']} = null;
                $cotizacion->{$fields['mail']} = null;
                $cotizacion->{$fields['tel']} = null;
                $cotizacion->{$fields['tipo']} = null;
            }
        }

        $mapaCliente = $contactosCliente->keyBy(fn ($c) => self::normalizarEmail($c->email ?? ''));
        $mapaCoti = collect(self::contactosDesdeCoti($cotizacion))
            ->keyBy(fn (array $c) => self::normalizarEmail($c['correo'] ?? ''));

        $slotsLibres = [];
        foreach ($slotsDef as $fields) {
            $mail = trim((string) ($cotizacion->{$fields['mail']} ?? ''));
            $nombre = trim((string) ($cotizacion->{$fields['contacto']} ?? ''));
            if ($mail === '' && $nombre === '') {
                $slotsLibres[] = $fields;
            }
        }

        foreach ($emailsSeleccionados as $email) {
            if ($slotsLibres === []) {
                break;
            }

            $fields = array_shift($slotsLibres);
            $norm = self::normalizarEmail($email);
            $contactoCliente = $mapaCliente->get($norm);
            $contactoCoti = $mapaCoti->get($norm);

            $nombre = $contactoCliente
                ? trim((string) $contactoCliente->nombre)
                : trim((string) ($contactoCoti['nombre'] ?? ''));
            if ($nombre === '') {
                $nombre = Str::before($email, '@') ?: 'Facturación';
            }

            $cotizacion->{$fields['mail']} = $email;
            $cotizacion->{$fields['tipo']} = self::TIPO_ENVIO_FACTURA_COTI;
            $cotizacion->{$fields['contacto']} = $nombre;

            $telefono = $contactoCliente
                ? trim((string) ($contactoCliente->telefono ?? ''))
                : trim((string) ($contactoCoti['telefono'] ?? ''));
            if ($telefono !== '') {
                $cotizacion->{$fields['tel']} = $telefono;
            }
        }

        $cotizacion->save();
    }

    /**
     * @param  Collection<int, ClienteContacto>  $contactosCliente
     * @param  array<int, string>  $emails
     */
    public static function persistirContactosEnvioFacturaEnCoti(Coti $cotizacion, Collection $contactosCliente, array $emails): void
    {
        if ($emails === []) {
            return;
        }

        $mapaCliente = $contactosCliente->keyBy(fn ($c) => self::normalizarEmail($c->email ?? ''));
        $slots = [
            ['contacto' => 'coti_contacto', 'mail' => 'coti_mail1', 'tel' => 'coti_telefono', 'tipo' => 'coti_contacto_tipo1'],
            ['contacto' => 'coti_contacto2', 'mail' => 'coti_mail2', 'tel' => 'coti_telefono2', 'tipo' => 'coti_contacto_tipo2'],
            ['contacto' => 'coti_contacto3', 'mail' => 'coti_mail3', 'tel' => 'coti_telefono3', 'tipo' => 'coti_contacto_tipo3'],
            ['contacto' => 'coti_contacto4', 'mail' => 'coti_mail4', 'tel' => 'coti_telefono4', 'tipo' => 'coti_contacto_tipo4'],
        ];

        $yaGuardados = collect(self::contactosEnvioFacturaCoti($cotizacion))
            ->pluck('correo')
            ->map(fn ($e) => self::normalizarEmail($e))
            ->all();

        foreach ($emails as $email) {
            $norm = self::normalizarEmail($email);
            if (in_array($norm, $yaGuardados, true)) {
                continue;
            }

            $slotLibre = null;
            foreach ($slots as $fields) {
                $mailVal = trim((string) ($cotizacion->{$fields['mail']} ?? ''));
                if ($mailVal === '') {
                    $slotLibre = $fields;
                    break;
                }
            }

            if ($slotLibre === null) {
                continue;
            }

            $contactoCliente = $mapaCliente->get($norm);
            $cotizacion->{$slotLibre['mail']} = trim($email);
            $cotizacion->{$slotLibre['tipo']} = self::TIPO_ENVIO_FACTURA_COTI;
            $cotizacion->{$slotLibre['contacto']} = $contactoCliente
                ? trim((string) $contactoCliente->nombre)
                : (Str::before(trim($email), '@') ?: 'Facturación');

            if ($contactoCliente && trim((string) ($contactoCliente->telefono ?? '')) !== '') {
                $cotizacion->{$slotLibre['tel']} = trim((string) $contactoCliente->telefono);
            }

            $yaGuardados[] = $norm;
        }

        $cotizacion->save();
    }
}
