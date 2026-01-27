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

class AnalisisExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $fechaDesde;
    protected $fechaHasta;
    protected $userCodigo;

    public function __construct($fechaDesde = null, $fechaHasta = null, $userCodigo = null)
    {
        $this->fechaDesde = $fechaDesde;
        $this->fechaHasta = $fechaHasta;
        $this->userCodigo = $userCodigo;
    }

    public function collection()
    {
        // Función helper para aplicar filtros de fecha
        $aplicarFiltrosFecha = function($query) {
            // Aplicar filtros de fecha de manera que ambas condiciones se cumplan juntas
            if ($this->fechaDesde && $this->fechaHasta) {
                // Cuando tenemos ambas fechas, aplicar ambas condiciones juntas
                $query->where(function($q) {
                    // Caso 1: Tiene fecha_fin_ot - debe estar en el rango completo
                    $q->where(function($subQ) {
                        $subQ->whereNotNull('fecha_fin_ot')
                             ->whereDate('fecha_fin_ot', '>=', $this->fechaDesde)
                             ->whereDate('fecha_fin_ot', '<=', $this->fechaHasta);
                    })
                    // Caso 2: No tiene fecha_fin_ot pero tiene fecha_inicio_ot - debe estar en el rango
                    ->orWhere(function($subQ) {
                        $subQ->whereNull('fecha_fin_ot')
                             ->whereNotNull('fecha_inicio_ot')
                             ->whereDate('fecha_inicio_ot', '>=', $this->fechaDesde)
                             ->whereDate('fecha_inicio_ot', '<=', $this->fechaHasta);
                    })
                    // Caso 3: No tiene ninguna fecha de OT - usar created_at
                    ->orWhere(function($subQ) {
                        $subQ->whereNull('fecha_fin_ot')
                             ->whereNull('fecha_inicio_ot')
                             ->whereDate('created_at', '>=', $this->fechaDesde)
                             ->whereDate('created_at', '<=', $this->fechaHasta);
                    });
                });
            } elseif ($this->fechaDesde) {
                // Solo fecha desde
                $query->where(function($q) {
                    $q->where(function($subQ) {
                        $subQ->whereNotNull('fecha_fin_ot')
                             ->whereDate('fecha_fin_ot', '>=', $this->fechaDesde);
                    })
                    ->orWhere(function($subQ) {
                        $subQ->whereNull('fecha_fin_ot')
                             ->whereNotNull('fecha_inicio_ot')
                             ->whereDate('fecha_inicio_ot', '>=', $this->fechaDesde);
                    })
                    ->orWhere(function($subQ) {
                        $subQ->whereNull('fecha_fin_ot')
                             ->whereNull('fecha_inicio_ot')
                             ->whereDate('created_at', '>=', $this->fechaDesde);
                    });
                });
            } elseif ($this->fechaHasta) {
                // Solo fecha hasta
                $query->where(function($q) {
                    $q->where(function($subQ) {
                        $subQ->whereNotNull('fecha_fin_ot')
                             ->whereDate('fecha_fin_ot', '<=', $this->fechaHasta);
                    })
                    ->orWhere(function($subQ) {
                        $subQ->whereNull('fecha_fin_ot')
                             ->whereNotNull('fecha_inicio_ot')
                             ->whereDate('fecha_inicio_ot', '<=', $this->fechaHasta);
                    })
                    ->orWhere(function($subQ) {
                        $subQ->whereNull('fecha_fin_ot')
                             ->whereNull('fecha_inicio_ot')
                             ->whereDate('created_at', '<=', $this->fechaHasta);
                    });
                });
            }
        };

        // Buscar muestras (cotio_subitem = 0) que tengan fecha_fin_ot en el rango
        // Para exportación, no aplicamos filtros de permisos estrictos, ya que es un reporte histórico
        $queryMuestras = CotioInstancia::where('cotio_instancias.cotio_subitem', 0)
            ->where('cotio_instancias.enable_ot', true);
        $aplicarFiltrosFecha($queryMuestras);
        $muestrasEnRango = $queryMuestras->get();

        // Buscar análisis (cotio_subitem > 0) que tengan fecha_fin_ot en el rango
        $queryAnalisis = CotioInstancia::where('cotio_instancias.cotio_subitem', '>', 0)
            ->where('cotio_instancias.enable_ot', true);
        $aplicarFiltrosFecha($queryAnalisis);
        $analisisEnRango = $queryAnalisis->get();

        // Obtener las muestras relacionadas con los análisis encontrados
        $muestrasRelacionadas = collect();
        if ($analisisEnRango->isNotEmpty()) {
            // Agrupar análisis por muestra para optimizar la consulta
            $muestrasKeysArray = [];
            foreach ($analisisEnRango as $analisis) {
                $key = $analisis->cotio_numcoti . '-' . $analisis->cotio_item . '-' . $analisis->instance_number;
                if (!isset($muestrasKeysArray[$key])) {
                    $muestrasKeysArray[$key] = [
                        'cotio_numcoti' => $analisis->cotio_numcoti,
                        'cotio_item' => $analisis->cotio_item,
                        'instance_number' => $analisis->instance_number
                    ];
                }
            }
            $muestrasKeys = collect($muestrasKeysArray);

            $muestrasRelacionadas = CotioInstancia::where('cotio_subitem', 0)
                ->where('enable_ot', true)
                ->where(function($query) use ($muestrasKeys) {
                    foreach ($muestrasKeys as $muestra) {
                        $query->orWhere(function($subQ) use ($muestra) {
                            $subQ->where('cotio_numcoti', $muestra['cotio_numcoti'])
                                 ->where('cotio_item', $muestra['cotio_item'])
                                 ->where('instance_number', $muestra['instance_number']);
                        });
                    }
                })
                ->get();
        }

        // Combinar muestras (las que cumplen el filtro + las relacionadas con análisis)
        $todasLasMuestras = $muestrasEnRango->merge($muestrasRelacionadas)->keyBy('id')->values();

        // Obtener todos los análisis de las muestras encontradas
        $todosLosAnalisis = collect();
        if ($todasLasMuestras->isNotEmpty()) {
            // Agrupar muestras para optimizar la consulta
            $muestrasKeysArray = [];
            foreach ($todasLasMuestras as $muestra) {
                $key = $muestra->cotio_numcoti . '-' . $muestra->cotio_item . '-' . $muestra->instance_number;
                if (!isset($muestrasKeysArray[$key])) {
                    $muestrasKeysArray[$key] = [
                        'cotio_numcoti' => $muestra->cotio_numcoti,
                        'cotio_item' => $muestra->cotio_item,
                        'instance_number' => $muestra->instance_number
                    ];
                }
            }
            $muestrasKeys = collect($muestrasKeysArray);

            if ($muestrasKeys->isNotEmpty()) {
                $todosLosAnalisis = CotioInstancia::where('cotio_subitem', '>', 0)
                    ->where('enable_ot', true)
                    ->where(function($query) use ($muestrasKeys) {
                        foreach ($muestrasKeys as $muestra) {
                            $query->orWhere(function($subQ) use ($muestra) {
                                $subQ->where('cotio_numcoti', $muestra['cotio_numcoti'])
                                     ->where('cotio_item', $muestra['cotio_item'])
                                     ->where('instance_number', $muestra['instance_number']);
                            });
                        }
                    })
                    ->get();
            }
        }

        // Combinar análisis encontrados directamente + análisis de las muestras
        $todosLosAnalisis = $analisisEnRango->merge($todosLosAnalisis)->keyBy('id')->values();

        // Combinar muestras y análisis
        $resultado = $todasLasMuestras->merge($todosLosAnalisis);

        // Cargar relaciones
        $resultado->load(['cotizacion.cliente', 'responsablesAnalisis']);

        // Ordenar por fecha_fin_ot (manejar nulls al final)
        return $resultado->sortBy(function($item) {
            if ($item->fecha_fin_ot) {
                return $item->fecha_fin_ot instanceof \Carbon\Carbon 
                    ? $item->fecha_fin_ot->timestamp 
                    : strtotime($item->fecha_fin_ot);
            }
            return PHP_INT_MAX;
        })->values();
    }

    public function headings(): array
    {
        return [
            'N° Cotización',
            'Tipo',
            'Descripción',
            'ID',
            'Estado',
            'Fecha Inicio OT',
            'Fecha Fin OT',
            'Responsables',
            'Cliente'
        ];
    }

    public function map($instancia): array
    {
        // Determinar si es muestra o análisis
        $esMuestra = $instancia->cotio_subitem == 0;
        $tipo = $esMuestra ? 'MUESTRA' : 'ANÁLISIS';

        // Formatear fecha de inicio OT
        $fechaInicioOt = '';
        if ($instancia->fecha_inicio_ot) {
            try {
                $fechaInicioOt = \Carbon\Carbon::parse($instancia->fecha_inicio_ot)->format('d/m/Y H:i');
            } catch (\Exception $e) {
                $fechaInicioOt = $instancia->fecha_inicio_ot;
            }
        }

        // Formatear fecha de fin OT
        $fechaFinOt = '';
        if ($instancia->fecha_fin_ot) {
            try {
                $fechaFinOt = \Carbon\Carbon::parse($instancia->fecha_fin_ot)->format('d/m/Y H:i');
            } catch (\Exception $e) {
                $fechaFinOt = $instancia->fecha_fin_ot;
            }
        }

        // Obtener responsables
        $responsables = '';
        if ($instancia->responsablesAnalisis && $instancia->responsablesAnalisis->count() > 0) {
            $responsables = $instancia->responsablesAnalisis->pluck('usu_descripcion')->implode(', ');
        }

        // Descripción y ID
        $descripcion = $instancia->cotio_descripcion ?? '';
        $idFormateado = $instancia->id ? '#' . str_pad($instancia->id, 8, '0', STR_PAD_LEFT) : 'N/A';

        // Estado
        $estado = $esMuestra 
            ? ($instancia->cotio_estado_analisis ?? 'N/A')
            : ($instancia->cotio_estado_analisis ?? 'N/A');

        // Obtener cliente
        $cliente = '';
        if ($instancia->cotizacion && $instancia->cotizacion->cliente) {
            $cliente = $instancia->cotizacion->cliente->cli_razonsocial ?? '';
        }

        return [
            $instancia->cotizacion->coti_num ?? 'N/A',
            $tipo,
            $descripcion,
            $idFormateado,
            str_replace('_', ' ', $estado),
            $fechaInicioOt,
            $fechaFinOt,
            $responsables ?: 'Sin asignar',
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
            'B' => 12,  // Tipo
            'C' => 30,  // Descripción
            'D' => 12,  // ID
            'E' => 20,  // Estado
            'F' => 18,  // Fecha Inicio OT
            'G' => 18,  // Fecha Fin OT
            'H' => 25,  // Responsables
            'I' => 30,  // Cliente
        ];
    }
}
