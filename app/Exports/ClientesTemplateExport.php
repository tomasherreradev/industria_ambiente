<?php

namespace App\Exports;

use App\Models\CondicionIva;
use App\Models\CondicionPago;
use App\Models\Provincia;
use App\Models\Zona;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ClientesTemplateExport implements WithMultipleSheets
{
    public function __construct(
        private ?array $clientesRows = null,
        private ?array $contactosRows = null,
        private ?array $empresasRows = null,
        private ?array $facturacionRows = null,
        private bool $incluirEjemplosSecundarios = true,
    ) {}

    public function sheets(): array
    {
        return [
            new ClientesInstruccionesSheet(),
            new ClientesDataSheet($this->clientesRows),
            new ClientesContactosSheet($this->contactosRows, $this->incluirEjemplosSecundarios),
            new ClientesEmpresasRelacionadasSheet($this->empresasRows, $this->incluirEjemplosSecundarios),
            new ClientesFacturacionSheet($this->facturacionRows, $this->incluirEjemplosSecundarios),
            new ClientesReferenciaSheet(),
        ];
    }
}

class ClientesInstruccionesSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    public function array(): array
    {
        return [
            ['Razón Social', 'Sí', 'cli_razonsocial', 'Nombre legal del cliente'],
            ['Estado (Activo/Inactivo)', 'Sí', 'cli_estado', 'Usar "Activo" salvo excepción'],
            ['Código (Referencia)', 'Recomendado', 'cli_codigo', 'Dejar el REF-xxx precargado para vincular contactos. Al importar se genera el código real.'],
            ['CUIT', 'Recomendado', 'cli_cuit', 'Formato 30-12345678-9'],
            ['Nombre Fantasía (Sucursal)', 'Si aplica', 'cli_fantasia', 'Usar cuando el mismo cliente tiene sucursales/plantas distintas'],
            ['Dirección / Localidad / CP / Provincia', 'Recomendado', 'cli_direccion, cli_localidad, cli_codigopostal, cli_codigoprv', 'Completar manualmente'],
            ['Condición IVA / Pago', 'Recomendado', 'cli_codigociva, cli_codigopag', 'Ver códigos en hoja Referencias'],
            ['Lista Precio', 'Opcional', 'cli_codigolp', 'Por defecto UNO'],
            ['Fecha Alta', 'Opcional', 'cli_fechaalta', 'AAAA-MM-DD'],
            ['Es Consultor', 'Opcional', 'es_consultor', 'Si / No'],
            ['Contactos (hoja aparte)', 'Recomendado', 'cliente_contactos', 'Email facturas/cobranzas ya precargados cuando existían en el listado'],
            ['Empresas relacionadas', 'Solo consultores', 'cli_rel_empresa_*', 'Completar en hoja Empresas Relacionadas'],
            ['Facturación alternativa', 'Opcional', 'cliente_razones_sociales_facturacion', 'Completar en hoja Facturación'],
        ];
    }

    public function headings(): array
    {
        return ['Campo', 'Obligatorio', 'Columna BD', 'Notas'];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getRowDimension(1)->setRowHeight(24);

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '44546A'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 32, 'B' => 14, 'C' => 34, 'D' => 70];
    }

    public function title(): string
    {
        return 'Instrucciones';
    }
}

class ClientesDataSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(private ?array $rows = null) {}

    public function array(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        return [
            [
                '000164',
                'MASSALIN PARTICULARES S.R.L.',
                '',
                'AV. Pte. Juan Domingo Peron 26950',
                'MERLO',
                '1722',
                'ARG',
                'B',
                '33-50060698-9',
                'INSCR',
                'CTE',
                'Activo',
                'UNO',
                '2015-01-22',
                'Contra Informe',
                'No',
                '',
                '',
            ],
            [
                '1846',
                'ACHERNAR SA',
                'PLANTA 2',
                'HORNOS 1350',
                'CABA',
                '',
                'ARG',
                'C',
                '__-________-_',
                'INSCR',
                'CTE',
                'Activo',
                'UNO',
                '2026-03-27',
                '',
                'No',
                '',
                '',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Código (Dejar vacío para nuevo)',
            'Razón Social',
            'Nombre Fantasía (Sucursal)',
            'Dirección',
            'Localidad',
            'Código Postal',
            'Código País (Ejem: ARG)',
            'Código Provincia',
            'CUIT',
            'Condición IVA (Código)',
            'Condición Pago (Código)',
            'Estado (Activo/Inactivo)',
            'Lista Precio (Código)',
            'Fecha Alta (AAAA-MM-DD)',
            'Tipo Factura',
            'Es Consultor (Si/No)',
            'Referencia Excel (No importar)',
            'Motivo original (No importar)',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getRowDimension(1)->setRowHeight(30);

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2F75B5'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, 'B' => 35, 'C' => 25, 'D' => 35, 'E' => 20,
            'F' => 15, 'G' => 12, 'H' => 12, 'I' => 20, 'J' => 20,
            'K' => 20, 'L' => 15, 'M' => 15, 'N' => 15, 'O' => 20,
            'P' => 15, 'Q' => 18, 'R' => 40,
        ];
    }

    public function title(): string
    {
        return 'Clientes';
    }
}

class ClientesContactosSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(
        private ?array $rows = null,
        private bool $incluirEjemplos = true,
    ) {}

    public function array(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        if (! $this->incluirEjemplos) {
            return [];
        }

        return [
            ['000164', 'Juan Perez', '45005222', 'juan@ejemplo.com', 'Compras'],
        ];
    }

    public function headings(): array
    {
        return [
            'Código Cliente (Referencia)',
            'Nombre',
            'Teléfono',
            'Email',
            'Tipo/Sector',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '548235'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 30, 'C' => 20, 'D' => 30, 'E' => 20];
    }

    public function title(): string
    {
        return 'Contactos';
    }
}

class ClientesEmpresasRelacionadasSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(
        private ?array $rows = null,
        private bool $incluirEjemplos = true,
    ) {}

    public function array(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        if (! $this->incluirEjemplos) {
            return [];
        }

        return [
            ['000164', 'Empresa Relacionada SA', '30-11111111-9', 'Calle 123', 'Lanus', 'Lanus', 'Pedro'],
        ];
    }

    public function headings(): array
    {
        return [
            'Código Cliente (Referencia)',
            'Razón Social',
            'CUIT',
            'Direcciones',
            'Localidad',
            'Partido',
            'Contacto',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'BF8F00'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 30, 'C' => 20, 'D' => 30, 'E' => 20, 'F' => 20, 'G' => 20];
    }

    public function title(): string
    {
        return 'Empresas Relacionadas';
    }
}

class ClientesFacturacionSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(
        private ?array $rows = null,
        private bool $incluirEjemplos = true,
    ) {}

    public function array(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        if (! $this->incluirEjemplos) {
            return [];
        }

        return [
            ['000164', 'RAZON SOCIAL FACT SA', '30-22222222-9', 'Av Siempre Viva 742', 'INSCR', 'Responsable Inscripto', 'CTE', '30 días', 'A', 'Si'],
        ];
    }

    public function headings(): array
    {
        return [
            'Código Cliente (Referencia)',
            'Razón Social Facturación',
            'CUIT',
            'Dirección',
            'Condición IVA (Código)',
            'Condición IVA (Desc)',
            'Condición Pago (Código)',
            'Condición Pago (Desc)',
            'Tipo Factura (A/B/C)',
            'Es Predeterminada (Si/No)',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '7030A0'],
                ],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 30, 'C' => 20, 'D' => 30, 'E' => 15, 'F' => 20, 'G' => 15, 'H' => 20, 'I' => 15, 'J' => 15];
    }

    public function title(): string
    {
        return 'Facturación';
    }
}

class ClientesReferenciaSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    public function array(): array
    {
        $ivas = CondicionIva::all()->map(fn ($i) => [$i->civa_codigo, $i->civa_descripcion])->toArray();
        $pagos = CondicionPago::all()->map(fn ($p) => [$p->pag_codigo, $p->pag_descripcion])->toArray();
        $provincias = Provincia::all()->map(fn ($pr) => [$pr->prv_codigo, $pr->prv_descripcion])->toArray();
        $zonas = Zona::all()->map(fn ($z) => [$z->zon_codigo, $z->zon_descripcion])->toArray();

        $data = [];
        $max = max(count($ivas), count($pagos), count($provincias), count($zonas));

        for ($i = 0; $i < $max; $i++) {
            $data[] = [
                $ivas[$i][0] ?? '', $ivas[$i][1] ?? '',
                '',
                $pagos[$i][0] ?? '', $pagos[$i][1] ?? '',
                '',
                $provincias[$i][0] ?? '', $provincias[$i][1] ?? '',
                '',
                $zonas[$i][0] ?? '', $zonas[$i][1] ?? '',
            ];
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'IVA Cod', 'IVA Desc', '', 'Pago Cod', 'Pago Desc', '', 'Prov Cod', 'Prov Desc', '', 'Zona Cod', 'Zona Desc',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 10, 'B' => 25, 'C' => 5, 'D' => 10, 'E' => 25, 'F' => 5, 'G' => 10, 'H' => 25, 'I' => 5, 'J' => 10, 'K' => 25];
    }

    public function title(): string
    {
        return 'Referencias';
    }
}
