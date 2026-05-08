<?php

namespace App\Services\Afip;

use Illuminate\Support\Facades\Cache;
use RuntimeException;
use SimpleXMLElement;
use SoapClient;
use SoapFault;

/**
 * WSAA + WSFEv1 contra ARCA sin pasar por app.afipsdk.com (requiere cert + key PEM).
 */
final class AfipDirectWsfeClient
{
    private const WSAA_SERVICE = 'wsfe';

    private const CACHE_PREFIX = 'afip_direct_ta:';

    public function __construct(
        private readonly string $cuit11,
        private readonly bool $production,
        private readonly string $certPem,
        private readonly string $keyPem,
        private readonly string $passphrase,
        private readonly string $wsaaWsdl,
        private readonly string $wsfeWsdl,
    ) {}

    public static function fromConfig(string $cuit11, string $certPem, string $keyPem, ?string $passphrase = null): self
    {
        $production = (bool) config('afip.production', false);

        return new self(
            preg_replace('/\D/', '', $cuit11),
            $production,
            $certPem,
            $keyPem,
            (string) ($passphrase ?? config('afip.passphrase', '')),
            (string) config('afip.wsaa_wsdl'),
            (string) config('afip.wsfe_wsdl'),
        );
    }

    public function getLastVoucher(int $ptoVta, int $cbteTipo): int
    {
        $ta = $this->getTicketAccess();
        $client = $this->soapWsfe();

        try {
            $res = $client->FECompUltimoAutorizado([
                'Auth' => $this->authArray($ta),
                'PtoVta' => $ptoVta,
                'CbteTipo' => $cbteTipo,
            ]);
        } catch (SoapFault $e) {
            throw new RuntimeException('WSFE FECompUltimoAutorizado: '.$e->getMessage(), 0, $e);
        }

        $this->assertFeErrors($res->FECompUltimoAutorizadoResult ?? null, 'FECompUltimoAutorizado');

        $nro = $res->FECompUltimoAutorizadoResult->CbteNro ?? null;

        return is_numeric($nro) ? (int) $nro : 0;
    }

    public function getVoucherInfo(int $cbteNro, int $ptoVta, int $cbteTipo): ?object
    {
        $ta = $this->getTicketAccess();
        $client = $this->soapWsfe();

        try {
            $res = $client->FECompConsultar([
                'Auth' => $this->authArray($ta),
                'FeCompConsReq' => [
                    'CbteTipo' => $cbteTipo,
                    'PtoVta' => $ptoVta,
                    'CbteNro' => $cbteNro,
                ],
            ]);
        } catch (SoapFault $e) {
            if ($e->getCode() === 602 || str_contains($e->getMessage(), '602')) {
                return null;
            }
            throw new RuntimeException('WSFE FECompConsultar: '.$e->getMessage(), 0, $e);
        }

        $this->assertFeErrors($res->FECompConsultarResult ?? null, 'FECompConsultar');

        return $res->FECompConsultarResult->ResultGet ?? null;
    }

    /**
     * @return array{CAE: string, CAEFchVto: string}
     */
    public function createVoucher(array $data): array
    {
        $ta = $this->getTicketAccess();
        $client = $this->soapWsfe();
        $req = $this->buildFecaeRequest($data);

        try {
            $res = $client->FECAESolicitar([
                'Auth' => $this->authArray($ta),
                'FeCAEReq' => $req['FeCAEReq'],
            ]);
        } catch (SoapFault $e) {
            throw new RuntimeException('WSFE FECAESolicitar: '.$e->getMessage(), 0, $e);
        }

        $result = $res->FECAESolicitarResult ?? null;
        $this->assertFeErrors($result, 'FECAESolicitar');

        $det = $result->FeDetResp->FECAEDetResponse ?? null;
        if (is_array($det)) {
            $det = $det[0];
        }
        if ($det === null || ! isset($det->CAE)) {
            throw new RuntimeException('WSFE: respuesta FECAESolicitar sin CAE.');
        }

        if (isset($det->Resultado) && (string) $det->Resultado !== 'A') {
            $obs = $this->formatObservaciones($det->Observaciones ?? null);
            throw new RuntimeException('WSFE rechazó el comprobante: '.$obs);
        }

        return [
            'CAE' => (string) $det->CAE,
            'CAEFchVto' => $this->formatCaeFecha((string) $det->CAEFchVto),
        ];
    }

    /**
     * @return object{token: string, sign: string}
     */
    private function getTicketAccess(): object
    {
        $cacheKey = self::CACHE_PREFIX.hash('sha256', $this->cuit11.'|'.($this->production ? '1' : '0').'|'.self::WSAA_SERVICE);

        return Cache::remember($cacheKey, now()->addMinutes(8), function () {
            $tra = $this->buildLoginTicketRequestXml();
            $cms = $this->firmarTraPkcs7($tra);
            $loginReturn = $this->loginCms($cms);
            $parsed = $this->parseLoginTicketResponse($loginReturn);

            return (object) [
                'token' => $parsed['token'],
                'sign' => $parsed['sign'],
            ];
        });
    }

    private function buildLoginTicketRequestXml(): string
    {
        $tz = new \DateTimeZone('America/Argentina/Buenos_Aires');
        $uniqueId = random_int(1, 2147483646);
        $gen = (new \DateTimeImmutable('now', $tz))->modify('-10 minutes')->format('Y-m-d\TH:i:sP');
        $exp = (new \DateTimeImmutable('now', $tz))->modify('+12 hours')->format('Y-m-d\TH:i:sP');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<loginTicketRequest version="1.0">'
            .'<header>'
            .'<uniqueId>'.$uniqueId.'</uniqueId>'
            .'<generationTime>'.$gen.'</generationTime>'
            .'<expirationTime>'.$exp.'</expirationTime>'
            .'</header>'
            .'<service>'.self::WSAA_SERVICE.'</service>'
            .'</loginTicketRequest>';
    }

    private function firmarTraPkcs7(string $traXml): string
    {
        $traFile = tempnam(sys_get_temp_dir(), 'afip_tra_');
        $cmsFile = tempnam(sys_get_temp_dir(), 'afip_cms_');
        if ($traFile === false || $cmsFile === false) {
            throw new RuntimeException('No se pudo crear archivo temporal para WSAA.');
        }

        try {
            file_put_contents($traFile, $traXml);
            $cert = openssl_x509_read($this->certPem);
            $key = openssl_pkey_get_private($this->keyPem, $this->passphrase === '' ? null : $this->passphrase);
            if ($cert === false) {
                throw new RuntimeException('Certificado PEM inválido para WSAA.');
            }
            if ($key === false) {
                throw new RuntimeException('Clave privada PEM inválida o passphrase incorrecta para WSAA.');
            }

            $ok = openssl_pkcs7_sign(
                $traFile,
                $cmsFile,
                $cert,
                $key,
                [],
                PKCS7_BINARY | PKCS7_DETACHED
            );
            if (! $ok) {
                throw new RuntimeException('openssl_pkcs7_sign falló al firmar el loginTicketRequest.');
            }

            $cmsPem = (string) file_get_contents($cmsFile);
            $cmsPem = preg_replace('/\s*-----BEGIN PKCS7-----\s*|\s*-----END PKCS7-----\s*/', '', $cmsPem);
            $cmsPem = preg_replace('/\s+/', '', $cmsPem);
            if ($cmsPem === '') {
                throw new RuntimeException('CMS vacío tras firmar el loginTicketRequest.');
            }

            return $cmsPem;
        } finally {
            @unlink($traFile);
            @unlink($cmsFile);
        }
    }

    private function loginCms(string $cmsBase64): string
    {
        $client = new SoapClient($this->wsaaWsdl, [
            'soap_version' => SOAP_1_2,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'connection_timeout' => 30,
            'exceptions' => true,
        ]);

        try {
            $res = $client->loginCms(['in0' => $cmsBase64]);
        } catch (SoapFault $e) {
            throw new RuntimeException('WSAA loginCms: '.$e->getMessage(), 0, $e);
        }

        $ret = $res->loginCmsReturn ?? null;
        if (! is_string($ret) || trim($ret) === '') {
            throw new RuntimeException('WSAA: respuesta loginCms vacía.');
        }

        return $ret;
    }

    /**
     * @return array{token: string, sign: string}
     */
    private function parseLoginTicketResponse(string $xml): array
    {
        $xml = preg_replace('/\sxmlns="[^"]+"/', '', $xml);
        $sx = new SimpleXMLElement($xml);
        $credentials = $sx->credentials ?? null;
        if ($credentials === null) {
            throw new RuntimeException('WSAA: no se encontró <credentials> en loginCmsReturn.');
        }
        $token = trim((string) $credentials->token);
        $sign = trim((string) $credentials->sign);
        if ($token === '' || $sign === '') {
            throw new RuntimeException('WSAA: token o sign vacío en la respuesta.');
        }

        return ['token' => $token, 'sign' => $sign];
    }

    private function soapWsfe(): SoapClient
    {
        return new SoapClient($this->wsfeWsdl, [
            'soap_version' => SOAP_1_2,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'connection_timeout' => 45,
            'exceptions' => true,
        ]);
    }

    /**
     * @param  object{token: string, sign: string}  $ta
     * @return array{Token: string, Sign: string, Cuit: int}
     */
    private function authArray(object $ta): array
    {
        return [
            'Token' => $ta->token,
            'Sign' => $ta->sign,
            'Cuit' => (int) $this->cuit11,
        ];
    }

    private function buildFecaeRequest(array $data): array
    {
        $det = $data;
        $cab = [
            'CantReg' => $data['CbteHasta'] - $data['CbteDesde'] + 1,
            'PtoVta' => $data['PtoVta'],
            'CbteTipo' => $data['CbteTipo'],
        ];
        unset($det['CantReg'], $det['PtoVta'], $det['CbteTipo']);

        if (isset($det['Tributos'])) {
            $det['Tributos'] = ['Tributo' => $det['Tributos']];
        }
        if (isset($det['Compradores'])) {
            $det['Compradores'] = ['Comprador' => $det['Compradores']];
        }
        if (isset($det['CbtesAsoc'])) {
            $det['CbtesAsoc'] = ['CbteAsoc' => $det['CbtesAsoc']];
        }
        if (isset($det['Iva'])) {
            $det['Iva'] = ['AlicIva' => $det['Iva']];
        }
        if (isset($det['Opcionales'])) {
            $det['Opcionales'] = ['Opcional' => $det['Opcionales']];
        }

        return [
            'FeCAEReq' => [
                'FeCabReq' => $cab,
                'FeDetReq' => [
                    'FECAEDetRequest' => $det,
                ],
            ],
        ];
    }

    private function assertFeErrors(?object $result, string $operacion): void
    {
        if ($result === null) {
            throw new RuntimeException("WSFE {$operacion}: sin resultado.");
        }
        if (isset($result->Errors)) {
            $err = $result->Errors->Err ?? null;
            if (is_array($err)) {
                $err = $err[0] ?? null;
            }
            $code = $err && isset($err->Code) ? (string) $err->Code : '?';
            $msg = $err && isset($err->Msg) ? (string) $err->Msg : 'Error desconocido';

            throw new RuntimeException("WSFE {$operacion} ({$code}): {$msg}");
        }
    }

    private function formatObservaciones(mixed $obs): string
    {
        if ($obs === null) {
            return 'sin observaciones';
        }
        $parts = [];
        $list = is_array($obs->Obs ?? null) ? $obs->Obs : [$obs->Obs ?? $obs];
        foreach ($list as $o) {
            if ($o === null) {
                continue;
            }
            $parts[] = '('.(isset($o->Code) ? (string) $o->Code : '?').') '.(isset($o->Msg) ? (string) $o->Msg : '');
        }

        return $parts === [] ? 'sin detalle' : implode('; ', $parts);
    }

    private function formatCaeFecha(string $yyyymmdd): string
    {
        if (strlen($yyyymmdd) === 8 && ctype_digit($yyyymmdd)) {
            return substr($yyyymmdd, 0, 4).'-'.substr($yyyymmdd, 4, 2).'-'.substr($yyyymmdd, 6, 2);
        }

        return $yyyymmdd;
    }
}
