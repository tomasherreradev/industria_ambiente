<?php

namespace App\Imports;

use App\Models\Clientes;
use App\Models\ClienteContacto;
use App\Models\ClienteEmpresaRelacionada;
use App\Models\ClienteRazonSocialFacturacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ClientesImport implements WithMultipleSheets
{
    protected $mapping = []; // Maps temporary/original codes to final cli_codigo

    public function sheets(): array
    {
        return [
            'Clientes' => new MainClientesImport($this),
            'Contactos' => new ContactosImport($this),
            'Empresas Relacionadas' => new EmpresasRelacionadasImport($this),
            'Facturación' => new FacturacionImport($this),
        ];
    }

    public function setMapping($old, $new)
    {
        $this->mapping[trim($old)] = trim($new);
    }

    public function getMapping($old)
    {
        $old = trim($old);
        return $this->mapping[$old] ?? $old;
    }
}

class MainClientesImport implements ToCollection, WithHeadingRow
{
    protected $parent;

    public function __construct(ClientesImport $parent)
    {
        $this->parent = $parent;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $originalCodigo = trim($row['codigo_dejar_vacio_para_nuevo'] ?? '');
            $razonSocial = trim($row['razon_social'] ?? '');

            if (empty($razonSocial)) continue;

            $cliente = null;
            if (!empty($originalCodigo)) {
                $cliente = Clientes::find(str_pad($originalCodigo, 10, ' ', STR_PAD_RIGHT));
            }

            if (!$cliente) {
                $cliente = new Clientes();
                // Generar código
                $ultimoCliente = Clientes::orderBy('cli_codigo', 'desc')->first();
                $nuevoCodigoNum = $ultimoCliente ? intval(trim($ultimoCliente->cli_codigo)) + 1 : 1;
                $nuevoCodigo = str_pad($nuevoCodigoNum, 10, ' ', STR_PAD_RIGHT);
                $cliente->cli_codigo = $nuevoCodigo;
                
                if (!empty($originalCodigo)) {
                    $this->parent->setMapping($originalCodigo, $nuevoCodigo);
                }
            } else {
                $this->parent->setMapping($originalCodigo, $cliente->cli_codigo);
            }

            $cliente->cli_razonsocial = str_pad(substr($razonSocial, 0, 60), 60, ' ', STR_PAD_RIGHT);
            $cliente->cli_fantasia = !empty($row['nombre_fantasia_sucursal']) ? str_pad(substr($row['nombre_fantasia_sucursal'], 0, 60), 60, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_direccion = !empty($row['direccion']) ? str_pad(substr($row['direccion'], 0, 60), 60, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_localidad = !empty($row['localidad']) ? str_pad(substr($row['localidad'], 0, 50), 50, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_codigopostal = !empty($row['codigo_postal']) ? str_pad(substr($row['codigo_postal'], 0, 10), 10, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_codigopais = str_pad(substr($row['codigo_pais_ejem_arg'] ?? 'ARG', 0, 5), 5, ' ', STR_PAD_RIGHT);
            $cliente->cli_codigoprv = !empty($row['codigo_provincia']) ? str_pad(substr($row['codigo_provincia'], 0, 5), 5, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_cuit = !empty($row['cuit']) ? str_pad(substr($row['cuit'], 0, 13), 13, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_codigociva = !empty($row['condicion_iva_codigo']) ? str_pad(substr($row['condicion_iva_codigo'], 0, 5), 5, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_codigopag = !empty($row['condicion_pago_codigo']) ? str_pad(substr($row['condicion_pago_codigo'], 0, 5), 5, ' ', STR_PAD_RIGHT) : null;
            $cliente->cli_estado = (strtolower($row['estado_activo_inactivo'] ?? '') === 'inactivo') ? false : true;
            $cliente->cli_codigolp = !empty($row['lista_precio_codigo']) ? str_pad(substr($row['lista_precio_codigo'], 0, 5), 5, ' ', STR_PAD_RIGHT) : 'UNO  ';
            $cliente->cli_fechaalta = !empty($row['fecha_alta_aaaa_mm_dd']) ? $row['fecha_alta_aaaa_mm_dd'] : now()->format('Y-m-d');
            $cliente->cli_factura = !empty($row['tipo_factura']) ? str_pad(substr($row['tipo_factura'], 0, 25), 25, ' ', STR_PAD_RIGHT) : null;
            $cliente->es_consultor = (strtolower($row['es_consultor_si_no'] ?? '') === 'si') ? true : false;

            $cliente->save();
        }
    }
}

class ContactosImport implements ToCollection, WithHeadingRow
{
    protected $parent;

    public function __construct(ClientesImport $parent)
    {
        $this->parent = $parent;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $ref = trim($row['codigo_cliente_referencia'] ?? '');
            if (empty($ref)) continue;

            $cliCodigo = $this->parent->getMapping($ref);
            
            // Si el cliente no existe en la base (ni fue creado en el paso anterior), saltar
            if (!Clientes::where('cli_codigo', $cliCodigo)->exists()) continue;

            ClienteContacto::create([
                'cli_codigo' => $cliCodigo,
                'nombre' => $row['nombre'] ?? '',
                'telefono' => $row['telefono'] ?? null,
                'email' => $row['email'] ?? null,
                'tipo' => $row['tipo_sector'] ?? null,
            ]);
        }
    }
}

class EmpresasRelacionadasImport implements ToCollection, WithHeadingRow
{
    protected $parent;

    public function __construct(ClientesImport $parent)
    {
        $this->parent = $parent;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $ref = trim($row['codigo_cliente_referencia'] ?? '');
            if (empty($ref)) continue;

            $cliCodigo = $this->parent->getMapping($ref);
            if (!Clientes::where('cli_codigo', $cliCodigo)->exists()) continue;

            ClienteEmpresaRelacionada::create([
                'cli_codigo' => $cliCodigo,
                'razon_social' => $row['razon_social'] ?? '',
                'cuit' => $row['cuit'] ?? null,
                'direcciones' => $row['direcciones'] ?? null,
                'localidad' => $row['localidad'] ?? null,
                'partido' => $row['partido'] ?? null,
                'contacto' => $row['contacto'] ?? null,
            ]);
        }
    }
}

class FacturacionImport implements ToCollection, WithHeadingRow
{
    protected $parent;

    public function __construct(ClientesImport $parent)
    {
        $this->parent = $parent;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $ref = trim($row['codigo_cliente_referencia'] ?? '');
            if (empty($ref)) continue;

            $cliCodigo = $this->parent->getMapping($ref);
            if (!Clientes::where('cli_codigo', $cliCodigo)->exists()) continue;

            ClienteRazonSocialFacturacion::create([
                'cli_codigo' => $cliCodigo,
                'razon_social' => $row['razon_social_facturacion'] ?? '',
                'cuit' => $row['cuit'] ?? null,
                'direccion' => $row['direccion'] ?? null,
                'condicion_iva' => $row['condicion_iva_codigo'] ?? null,
                'condicion_iva_desc' => $row['condicion_iva_desc'] ?? null,
                'condicion_pago' => $row['condicion_pago_codigo'] ?? null,
                'condicion_pago_desc' => $row['condicion_pago_desc'] ?? null,
                'tipo_factura' => $row['tipo_factura_abc'] ?? null,
                'es_predeterminada' => (strtolower($row['es_predeterminada_si_no'] ?? '') === 'si') ? true : false,
            ]);
        }
    }
}
