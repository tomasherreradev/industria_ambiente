<?php

namespace App\Support;

use App\Models\Clientes;
use Illuminate\Support\Str;

class ClienteEmailsImportResolver
{
    public function esClientePrincipal(Clientes $cliente): bool
    {
        $cuit = trim((string) $cliente->cli_cuit);

        return $cuit !== '' && $cuit !== '__-________-_';
    }

    /**
     * Resuelve el cliente principal de una fila del Excel.
     */
    public function resolverPrincipal(?string $codigoExcel, ?string $razonSocial, ?string $cuitExcel): ?Clientes
    {
        $codigoExcel = trim((string) $codigoExcel);
        $razonSocial = trim((string) $razonSocial);
        $cuitExcel = trim((string) $cuitExcel);

        if ($codigoExcel !== '') {
            $padded = str_pad($codigoExcel, 10, ' ', STR_PAD_RIGHT);
            $porCodigo = Clientes::where('cli_codigo', $padded)->first();
            if ($porCodigo) {
                if ($this->esClientePrincipal($porCodigo)) {
                    return $porCodigo;
                }

                $principalDesdeSucursal = $this->buscarPrincipalPorGrupo($porCodigo, $cuitExcel);
                if ($principalDesdeSucursal) {
                    return $principalDesdeSucursal;
                }
            }
        }

        if ($cuitExcel !== '' && $cuitExcel !== '__-________-_') {
            $query = Clientes::query()
                ->whereRaw('TRIM(cli_cuit) = ?', [$cuitExcel]);

            if ($razonSocial !== '') {
                $query->whereRaw('UPPER(TRIM(cli_razonsocial)) = ?', [mb_strtoupper($razonSocial)]);
            }

            $porCuit = $query->first();
            if ($porCuit && $this->esClientePrincipal($porCuit)) {
                return $porCuit;
            }
        }

        if ($razonSocial !== '') {
            return Clientes::query()
                ->whereRaw('UPPER(TRIM(cli_razonsocial)) = ?', [mb_strtoupper($razonSocial)])
                ->whereNotNull('cli_cuit')
                ->whereRaw("TRIM(cli_cuit) <> ''")
                ->whereRaw("TRIM(cli_cuit) <> '__-________-_'")
                ->orderBy('cli_codigo')
                ->first();
        }

        return null;
    }

    /**
     * @return array{cliente: ?Clientes, ambiguo: bool}
     */
    public function resolverSucursal(Clientes $principal, string $etiquetaSuc): array
    {
        $etiqueta = $this->normalizarEtiqueta($etiquetaSuc);
        if ($etiqueta === '') {
            return ['cliente' => null, 'ambiguo' => false];
        }

        $sucursales = $principal->sucursales()->get();
        $coincidencias = [];

        foreach ($sucursales as $sucursal) {
            if ($this->etiquetaCoincideConSucursal($etiqueta, $sucursal)) {
                $coincidencias[] = $sucursal;
            }
        }

        if (count($coincidencias) === 1) {
            return ['cliente' => $coincidencias[0], 'ambiguo' => false];
        }

        return [
            'cliente' => null,
            'ambiguo' => count($coincidencias) > 1,
        ];
    }

    /**
     * Listado de sucursales del cliente para ayudar a corregir etiquetas SUC en el log.
     *
     * @return list<array{codigo: string, fantasia: string, localidad: string, direccion: string}>
     */
    public function listarSucursalesReferencia(Clientes $principal): array
    {
        return $principal->sucursales()
            ->get()
            ->map(fn (Clientes $s) => [
                'codigo' => trim($s->cli_codigo),
                'fantasia' => trim((string) $s->cli_fantasia),
                'localidad' => trim((string) $s->cli_localidad),
                'direccion' => trim((string) $s->cli_direccion),
            ])
            ->values()
            ->all();
    }

    private function buscarPrincipalPorGrupo(Clientes $registro, string $cuitExcel): ?Clientes
    {
        $razon = trim((string) $registro->cli_razonsocial);
        if ($razon === '') {
            return null;
        }

        $query = Clientes::query()
            ->whereRaw('UPPER(TRIM(cli_razonsocial)) = ?', [mb_strtoupper($razon)])
            ->whereNotNull('cli_cuit')
            ->whereRaw("TRIM(cli_cuit) <> ''")
            ->whereRaw("TRIM(cli_cuit) <> '__-________-_'");

        if ($cuitExcel !== '' && $cuitExcel !== '__-________-_') {
            $query->whereRaw('TRIM(cli_cuit) = ?', [$cuitExcel]);
        }

        return $query->orderBy('cli_codigo')->first();
    }

    private function etiquetaCoincideConSucursal(string $etiqueta, Clientes $sucursal): bool
    {
        $candidatos = array_filter([
            trim((string) $sucursal->cli_fantasia),
            trim((string) $sucursal->cli_localidad),
            trim((string) $sucursal->cli_direccion),
            trim((string) $sucursal->cli_partido),
            $this->parteDespuesDeGuion(trim((string) $sucursal->cli_fantasia)),
        ]);

        foreach ($candidatos as $candidato) {
            if ($candidato === '') {
                continue;
            }

            if ($this->textosCoinciden($etiqueta, $candidato)) {
                return true;
            }
        }

        return false;
    }

    private function textosCoinciden(string $a, string $b): bool
    {
        $na = $this->compactar($this->normalizarEtiqueta($a));
        $nb = $this->compactar($this->normalizarEtiqueta($b));

        if ($na === '' || $nb === '') {
            return false;
        }

        return str_contains($nb, $na)
            || str_contains($na, $nb)
            || similar_text($na, $nb) / max(strlen($na), strlen($nb)) >= 0.82;
    }

    private function normalizarEtiqueta(string $texto): string
    {
        $texto = Str::ascii(mb_strtoupper(trim($texto)));
        $texto = str_replace('_', ' ', $texto);

        return trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
    }

    private function compactar(string $texto): string
    {
        return preg_replace('/[^A-Z0-9]/', '', $texto) ?? '';
    }

    private function parteDespuesDeGuion(string $fantasia): string
    {
        if (!str_contains($fantasia, ' - ')) {
            return '';
        }

        $partes = explode(' - ', $fantasia, 2);

        return trim($partes[1] ?? '');
    }
}
