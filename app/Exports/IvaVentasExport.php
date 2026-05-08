<?php

namespace App\Exports;

use App\Models\Factura;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IvaVentasExport implements FromView, ShouldAutoSize, WithStyles
{
    protected $fechaDesde;
    protected $fechaHasta;

    public function __construct($fechaDesde, $fechaHasta)
    {
        $this->fechaDesde = $fechaDesde;
        $this->fechaHasta = $fechaHasta;
    }

    public function view(): View
    {
        $facturas = Factura::whereBetween('fecha_emision', [
            $this->fechaDesde . ' 00:00:00',
            $this->fechaHasta . ' 23:59:59'
        ])
        ->where('estado', 'aprobada')
        ->orderBy('fecha_emision', 'asc')
        ->get();

        return view('exports.iva_ventas', [
            'facturas' => $facturas,
            'fechaDesde' => $this->fechaDesde,
            'fechaHasta' => $this->fechaHasta,
            'empresa' => env('APP_NAME', 'Industria y Ambiente S.A.')
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            3 => ['font' => ['bold' => true, 'size' => 12]],
            9 => ['font' => ['bold' => true]],
            10 => ['font' => ['bold' => true]],
        ];
    }
}
