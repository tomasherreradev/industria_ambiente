<?php
// app/Exports/ReporteAuditoriaExport.php

namespace App\Exports;

use App\Models\CotioInstancia;
use App\Models\Coti;
use App\Models\Matriz;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Support\CotizacionClienteEtiqueta;

class ReporteAuditoriaExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $fechaDesde;
    protected $fechaHasta;

    public function __construct($fechaDesde = null, $fechaHasta = null)
    {
        $this->fechaDesde = $fechaDesde;
        $this->fechaHasta = $fechaHasta;
    }

    public function collection()
    {
        $query = CotioInstancia::with(['coti.matriz', 'coti.cliente', 'coti.sucursal']);

        // Aplicar filtros de fecha si existen
        if ($this->fechaDesde) {
            $query->whereDate('created_at', '>=', $this->fechaDesde);
        }

        if ($this->fechaHasta) {
            $query->whereDate('created_at', '<=', $this->fechaHasta);
        }

        $instancias = $query->get();
        CotizacionClienteEtiqueta::precargarEmpresasRelacionadas(
            $instancias->map->coti->filter()->values()
        );

        return $instancias;
    }

    public function headings(): array
    {
        return [
            'Empresa',
            'OT',
            'N° Cotización',
            'N° Precinto',
            'Fecha Ingreso LAB',
            'Matriz',
            'Fecha de Muestreo',
            'Precio',
            'Observaciones'
        ];
    }

    public function map($instancia): array
    {
        // Obtener empresa desde Coti
        $empresa = '';
        $matrizDescripcion = '';
        
        if ($instancia->coti) {
            $empresa = CotizacionClienteEtiqueta::paraLista($instancia->coti);
            
            // Obtener descripción de la matriz
            if ($instancia->coti->coti_codigomatriz) {
                $matriz = Matriz::where('matriz_codigo', trim($instancia->coti->coti_codigomatriz))->first();
                $matrizDescripcion = $matriz->matriz_descripcion ?? trim($instancia->coti->coti_codigomatriz);
            }
        }

        // Formatear fechas
        $fechaIngresoLab = '';
        if ($instancia->fecha_inicio_ot) {
            try {
                $fechaIngresoLab = \Carbon\Carbon::parse($instancia->fecha_inicio_ot)->format('d/m/Y');
            } catch (\Exception $e) {
                $fechaIngresoLab = $instancia->fecha_inicio_ot;
            }
        }

        $fechaMuestreo = '';
        if ($instancia->fecha_inicio_muestreo) {
            try {
                $fechaMuestreo = \Carbon\Carbon::parse($instancia->fecha_inicio_muestreo)->format('d/m/Y');
            } catch (\Exception $e) {
                $fechaMuestreo = $instancia->fecha_inicio_muestreo;
            }
        }

        return [
            $empresa,
            $instancia->id,
            $instancia->cotio_numcoti,
            '', // N° Precinto (vacío por ahora)
            $fechaIngresoLab,
            $matrizDescripcion,
            $fechaMuestreo,
            $instancia->monto ?? 0,
            $instancia->observacion_resultado_final ?? ''
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para el encabezado
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2C3E50']]
            ],
            // Ajustar ancho de columnas
            'A' => ['width' => 25],
            'B' => ['width' => 10],
            'C' => ['width' => 15],
            'D' => ['width' => 15],
            'E' => ['width' => 18],
            'F' => ['width' => 20],
            'G' => ['width' => 18],
            'H' => ['width' => 12],
            'I' => ['width' => 30],
        ];
    }
}