<?php

namespace App\Console\Commands;

use Afip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Equivalente a la automatización create-cert-dev de Afip SDK
 * (@see https://afipsdk.com/docs/automations/create-cert-dev/nodejs/ ).
 */
class AfipCreateCertDevCommand extends Command
{
    protected $signature = 'afip:create-cert-dev
                            {--username= : CUIT para ingresar a ARCA (por defecto el de AFIP_CUIT)}
                            {--password= : Contraseña ARCA (preferible usar el prompt; queda en historial del shell)}
                            {--alias= : Alias alfanumérico del certificado en ARCA}
                            {--authorize-ws : Autorizar el web service wsfe en homologación tras crear el certificado}';

    protected $description = 'Genera certificado y clave de desarrollo ARCA vía Afip SDK y los guarda como PEM (AFIP_CERT_PATH / AFIP_KEY_PATH).';

    public function handle(): int
    {
        $token = config('afip.access_token');
        if (! is_string($token) || trim($token) === '') {
            $this->error('Definí AFIPSDK_ACCESS_TOKEN en .env.');

            return self::FAILURE;
        }

        $cuitDigits = preg_replace('/\D/', '', (string) config('afip.cuit', ''));
        if (strlen($cuitDigits) !== 11) {
            $this->error('AFIP_CUIT debe tener 11 dígitos (CUIT del contribuyente en homologación).');

            return self::FAILURE;
        }

        $username = $this->option('username') ?: $cuitDigits;
        $username = preg_replace('/\D/', '', (string) $username);
        if (strlen($username) !== 11) {
            $this->error('El usuario ARCA (--username) debe ser un CUIT de 11 dígitos.');

            return self::FAILURE;
        }

        $password = (string) $this->option('password');
        if ($password === '') {
            $password = $this->secret('Contraseña de ARCA/AFIP (homologación)');
        }
        if ($password === '') {
            $this->error('La contraseña no puede estar vacía.');

            return self::FAILURE;
        }

        $alias = $this->option('alias');
        if (! is_string($alias) || trim($alias) === '') {
            $alias = $this->ask('Alias del certificado en ARCA (alfanumérico)', 'muestreo_dev');
        }
        $alias = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $alias);
        if ($alias === '') {
            $this->error('El alias debe ser alfanumérico (guiones y guión bajo permitidos).');

            return self::FAILURE;
        }

        $certRel = trim((string) (config('afip.cert_path') ?: 'storage/certificates/certificado.crt'));
        $keyRel = trim((string) (config('afip.key_path') ?: 'storage/certificates/clave.key'));
        $certPath = base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $certRel));
        $keyPath = base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $keyRel));

        $dir = dirname($certPath);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        if ((File::exists($certPath) || File::exists($keyPath)) && ! $this->confirm('Ya existen archivos en la ruta configurada. ¿Sobrescribir certificado y clave?', false)) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        $this->info('Solicitando certificado de desarrollo a Afip SDK (puede tardar hasta ~2 minutos)...');
        $this->line("CUIT certificado: {$cuitDigits} | usuario ARCA: {$username} | alias: {$alias}");

        try {
            $afip = new Afip([
                'CUIT' => (int) $cuitDigits,
                'production' => false,
                'access_token' => trim($token),
            ]);

            $data = $afip->CreateCert($username, $password, $alias);
        } catch (Throwable $e) {
            $this->error('Afip SDK: '.$e->getMessage());

            return self::FAILURE;
        }

        $certPem = is_object($data) && isset($data->cert) ? (string) $data->cert : '';
        $keyPem = is_object($data) && isset($data->key) ? (string) $data->key : '';
        if ($certPem === '' || $keyPem === '') {
            $this->error('La respuesta del SDK no incluye cert o key PEM.');

            return self::FAILURE;
        }

        if (! str_contains($certPem, 'BEGIN CERTIFICATE')) {
            $this->error('El campo cert no parece un PEM de certificado X.509.');

            return self::FAILURE;
        }

        File::put($certPath, rtrim($certPem)."\n");
        File::put($keyPath, rtrim($keyPem)."\n");
        @chmod($certPath, 0640);
        @chmod($keyPath, 0600);

        $this->info('Certificado guardado en: '.$certPath);
        $this->info('Clave privada en: '.$keyPath);

        if ($this->option('authorize-ws')) {
            $this->info('Autorizando web service wsfe...');
            try {
                $afip->CreateWSAuth($username, $password, $alias, 'wsfe');
                $this->info('Autorización wsfe registrada en homologación.');
            } catch (Throwable $e) {
                $this->error('CreateWSAuth falló: '.$e->getMessage());
                $this->warn('El certificado ya está guardado; podés autorizar wsfe manualmente en ARCA o reintentar con: php artisan afip:create-cert-dev --authorize-ws (mismo alias).');

                return self::FAILURE;
            }
        } else {
            $this->newLine();
            $this->comment('Si aún no autorizaste wsfe para este alias en homologación, ejecutá de nuevo con --authorize-ws (mismos usuario, contraseña y alias) o hacelo desde ARCA.');
        }

        return self::SUCCESS;
    }
}
