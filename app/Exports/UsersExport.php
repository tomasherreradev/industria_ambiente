<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class UsersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $rol;

    public function __construct($rol = null)
    {
        $this->rol = $rol;
    }

    public function collection()
    {
        $query = User::where('rol', '!=', 'sector');
        
        if ($this->rol) {
            $query->where('rol', $this->rol);
        }
        
        return $query->orderBy('usu_codigo')->get();
    }

    public function headings(): array
    {
        return [
            'Código Usuario',
            'DNI',
            'Rol'
        ];
    }

    public function map($user): array
    {
        // Remover puntos del DNI
        $dni = $user->dni ? str_replace('.', '', $user->dni) : '';
        
        return [
            $user->usu_codigo,
            $dni,
            $user->rol ?? 'Sin rol'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para el encabezado
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2C3E50']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,  // Código Usuario
            'B' => 15,  // DNI
            'C' => 25,  // Rol
        ];
    }
}
