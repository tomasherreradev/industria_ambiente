<?php

namespace App\Exports;

use App\Models\LeyNormativa;
use App\Models\CotioItems;
use App\Models\Metodo;
use App\Models\Matriz;
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

class LeyesNormativasTemplateExport implements WithMultipleSheets
{
    /**
     * @return array
     */
    public function sheets(): array
    {
        return [
            new LeyesNormativasDataSheet(),
            new LeyesNormativasListSheet(),
            new MetodosListSheet(),
            new MatricesListSheet(),
        ];
    }
}

/**
 * Hoja principal con datos de ejemplo para importar leyes
 */
class LeyesNormativasDataSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    /**
     * @return array
     */
    public function array(): array
    {
        // Datos de ejemplo para la plantilla
        return [
            [
                'pH',  // Analito (cotio_descripcion)
                '',  // Matriz (opcional) - Si se especifica, solo aplica a items con esa matriz
                '',  // Método (opcional) - Si se especifica, solo aplica a items con ese método
                'Ley Ejemplo 1',  // Nombre de la Ley (editable)
                'UpH',  // Unidad de medida
                '6.5-8.5'  // Valor límite
            ],
            [
                'pH',
                'AGUA',  // Matriz específica (puede ser código o nombre)
                'SM 4500 H+ B',  // Método específico (puede ser código o nombre)
                'Ley Ejemplo 1',
                'UpH',
                '7.0-8.0'
            ],
        ];
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Analito (cotio_descripcion)',
            'Matriz (opcional - código o nombre)',
            'Método (opcional - código o nombre)',
            'Nombre de la Ley',
            'Unidad de medida',
            'Valor límite'
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        // Configurar la altura de la fila 1
        $sheet->getRowDimension(1)->setRowHeight(45);
        
        // Configurar alineación vertical para todas las columnas del encabezado
        foreach (range('A', 'F') as $column) {
            $sheet->getStyle($column . '1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }
        
        return [
            1 => [
                'font' => [
                    'bold' => true, 
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 12
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        return [
            'A' => 30,  // Analito
            'B' => 25,  // Matriz
            'C' => 25,  // Método
            'D' => 30,  // Nombre de la Ley
            'E' => 20,  // Unidad de medida
            'F' => 20,  // Valor límite
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Datos';
    }
}

/**
 * Hoja con lista de leyes existentes en el sistema
 */
class LeyesNormativasListSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    /**
     * @return array
     */
    public function array(): array
    {
        $leyes = LeyNormativa::orderBy('grupo')->orderBy('codigo')->get();
        
        return $leyes->map(function ($ley) {
            return [
                $ley->codigo,
                $ley->nombre,
                $ley->grupo ?? '',
                $ley->articulo ?? '',
                $ley->organismo_emisor ?? '',
                $ley->fecha_vigencia ? $ley->fecha_vigencia->format('d/m/Y') : '',
                $ley->activo ? 'Activa' : 'Inactiva',
            ];
        })->toArray();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Código',
            'Nombre',
            'Grupo',
            'Artículo',
            'Organismo Emisor',
            'Fecha Vigencia',
            'Estado'
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true, 
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '70AD47']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        return [
            'A' => 20,  // Código
            'B' => 40,  // Nombre
            'C' => 30,  // Grupo
            'D' => 20,  // Artículo
            'E' => 30,  // Organismo Emisor
            'F' => 15,  // Fecha Vigencia
            'G' => 15,  // Estado
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Leyes Existentes';
    }
}

/**
 * Hoja con lista de métodos disponibles
 */
class MetodosListSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    /**
     * @return array
     */
    public function array(): array
    {
        $metodos = Metodo::orderBy('metodo_codigo')->get();
        
        return $metodos->map(function ($metodo) {
            return [
                $metodo->metodo_codigo,
                $metodo->metodo_descripcion ?? '',
            ];
        })->toArray();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Código',
            'Descripción'
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true, 
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFC000']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        return [
            'A' => 20,  // Código
            'B' => 50,  // Descripción
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Métodos';
    }
}

/**
 * Hoja con lista de matrices disponibles
 */
class MatricesListSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    /**
     * @return array
     */
    public function array(): array
    {
        $matrices = Matriz::orderBy('matriz_codigo')->get();
        
        return $matrices->map(function ($matriz) {
            return [
                $matriz->matriz_codigo,
                $matriz->matriz_descripcion ?? '',
            ];
        })->toArray();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Código',
            'Descripción'
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true, 
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '7030A0']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array
     */
    public function columnWidths(): array
    {
        return [
            'A' => 20,  // Código
            'B' => 50,  // Descripción
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Matrices';
    }
}

