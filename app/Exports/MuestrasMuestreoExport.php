<?php

namespace App\Exports;

use App\Models\CotioInstancia;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class MuestrasMuestreoExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $fechaDesde;
    protected $fechaHasta;
    protected $userCodigo;
    protected $esCoordinadorMuestreo;

    public function __construct($fechaDesde = null, $fechaHasta = null, $userCodigo = null, $esCoordinadorMuestreo = false)
    {
        $this->fechaDesde = $fechaDesde;
        $this->fechaHasta = $fechaHasta;
        $this->userCodigo = $userCodigo;
        $this->esCoordinadorMuestreo = $esCoordinadorMuestreo;
    }

    public function collection()
    {
        // Base query para muestras - Para exportación incluimos todas las muestras del período
        // sin filtrar por enable_ot, ya que es un reporte histórico
        if ($this->esCoordinadorMuestreo) {
            $query = CotioInstancia::where('cotio_instancias.cotio_subitem', 0);
        } else {
            $query = CotioInstancia::where('cotio_instancias.cotio_subitem', 0)
                ->where(function($query) {
                    $query->where('cotio_instancias.coordinador_codigo', $this->userCodigo)
                          ->orWhereHas('responsablesMuestreo', function($q) {
                              $q->where('instancia_responsable_muestreo.usu_codigo', $this->userCodigo);
                          });
                });
        }

        // Aplicar filtros de fecha si existen
        // Usamos fecha_fin_muestreo como fecha principal para filtrar por el período de muestreo
        // Si no tiene fecha_fin_muestreo, usamos fecha_inicio_muestreo como alternativa
        if ($this->fechaDesde) {
            $query->where(function($q) {
                $q->where(function($subQ) {
                    // Muestras con fecha_fin_muestreo
                    $subQ->whereNotNull('fecha_fin_muestreo')
                         ->whereDate('fecha_fin_muestreo', '>=', $this->fechaDesde);
                })
                ->orWhere(function($subQ) {
                    // Muestras sin fecha_fin_muestreo pero con fecha_inicio_muestreo
                    $subQ->whereNull('fecha_fin_muestreo')
                         ->whereNotNull('fecha_inicio_muestreo')
                         ->whereDate('fecha_inicio_muestreo', '>=', $this->fechaDesde);
                })
                ->orWhere(function($subQ) {
                    // Muestras sin ninguna fecha de muestreo, usar created_at
                    $subQ->whereNull('fecha_fin_muestreo')
                         ->whereNull('fecha_inicio_muestreo')
                         ->whereDate('created_at', '>=', $this->fechaDesde);
                });
            });
        }

        if ($this->fechaHasta) {
            $query->where(function($q) {
                $q->where(function($subQ) {
                    // Muestras con fecha_fin_muestreo
                    $subQ->whereNotNull('fecha_fin_muestreo')
                         ->whereDate('fecha_fin_muestreo', '<=', $this->fechaHasta);
                })
                ->orWhere(function($subQ) {
                    // Muestras sin fecha_fin_muestreo pero con fecha_inicio_muestreo
                    $subQ->whereNull('fecha_fin_muestreo')
                         ->whereNotNull('fecha_inicio_muestreo')
                         ->whereDate('fecha_inicio_muestreo', '<=', $this->fechaHasta);
                })
                ->orWhere(function($subQ) {
                    // Muestras sin ninguna fecha de muestreo, usar created_at
                    $subQ->whereNull('fecha_fin_muestreo')
                         ->whereNull('fecha_inicio_muestreo')
                         ->whereDate('created_at', '<=', $this->fechaHasta);
                });
            });
        }

        return $query->with(['cotizacion.cliente', 'vehiculo', 'responsablesMuestreo'])
            ->orderBy('cotio_instancias.fecha_fin_muestreo')
            ->get();
    }

    public function headings(): array
    {
        return [
            'N° Cotización',
            'Nombre de la Muestra',
            'Descripción',
            'Responsables',
            'Estado',
            'Fecha de Inicio',
            'Fecha de Fin',
            'En OT',
            'Vehículo',
            'Zona',
            'Cliente'
        ];
    }

    public function map($muestra): array
    {
        // Formatear fecha de inicio
        $fechaInicio = '';
        if ($muestra->fecha_inicio_muestreo) {
            try {
                $fechaInicio = \Carbon\Carbon::parse($muestra->fecha_inicio_muestreo)->format('d/m/Y H:i');
            } catch (\Exception $e) {
                $fechaInicio = $muestra->fecha_inicio_muestreo;
            }
        }

        // Formatear fecha de fin
        $fechaFin = '';
        if ($muestra->fecha_fin_muestreo) {
            try {
                $fechaFin = \Carbon\Carbon::parse($muestra->fecha_fin_muestreo)->format('d/m/Y H:i');
            } catch (\Exception $e) {
                $fechaFin = $muestra->fecha_fin_muestreo;
            }
        }

        // Obtener responsables
        $responsables = $muestra->responsablesMuestreo->pluck('usu_descripcion')->implode(', ');

        // Obtener vehículo
        $vehiculo = '';
        if ($muestra->vehiculo) {
            $vehiculo = $muestra->vehiculo->patente . ' - ' . $muestra->vehiculo->marca . ' ' . $muestra->vehiculo->modelo;
        }

        // Obtener zona
        $zona = '';
        if ($muestra->cotizacion && $muestra->cotizacion->cliente && $muestra->cotizacion->cliente->zona) {
            $zona = $muestra->cotizacion->cliente->zona->zon_descripcion;
        }

        // Obtener cliente
        $cliente = '';
        if ($muestra->cotizacion && $muestra->cotizacion->cliente) {
            $cliente = $muestra->cotizacion->cliente->cli_razonsocial ?? '';
        }

        // Nombre de la muestra
        $nombreMuestra = $muestra->cotio_descripcion ?? '';
        if ($muestra->id) {
            $nombreMuestra .= ' - Muestra #' . str_pad($muestra->id, 8, '0', STR_PAD_LEFT);
        }

        return [
            $muestra->cotizacion->coti_num ?? 'N/A',
            $nombreMuestra,
            $muestra->cotio_descripcion ?? '',
            $responsables ?: 'Sin asignar',
            str_replace('_', ' ', $muestra->cotio_estado ?? ''),
            $fechaInicio,
            $fechaFin,
            $muestra->enable_ot ? 'Sí' : 'No',
            $vehiculo,
            $zona,
            $cliente
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
            'A' => 15,  // N° Cotización
            'B' => 40,  // Nombre de la Muestra
            'C' => 30,  // Descripción
            'D' => 25,  // Responsables
            'E' => 20,  // Estado
            'F' => 18,  // Fecha de Inicio
            'G' => 18,  // Fecha de Fin
            'H' => 10,  // En OT
            'I' => 25,  // Vehículo
            'J' => 20,  // Zona
            'K' => 30,  // Cliente
        ];
    }
}
