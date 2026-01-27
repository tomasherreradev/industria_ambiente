<?php

namespace App\Http\Controllers;

use App\Models\CotioInstancia;
use App\Models\Matriz;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\FirmaDigitalService;
use Illuminate\Http\Response;


class InformeController extends Controller
{
    /**
     * Genera un PDF masivo con todos los informes de una cotización
     */
    public function generarPdfMasivo($cotizacion)
    {
        $cotizacionObj = \App\Models\Coti::with('matriz')->where('coti_num', $cotizacion)->firstOrFail();
        
        // Obtener todas las muestras principales
        $muestras = \App\Models\CotioInstancia::with([
            'tareas' => function($query) {
                $query->where('enable_inform', true)->orderBy('cotio_subitem');
            },
            'valoresVariables' => function($query) {
                $query->orderBy('variable');
            },
            'responsablesAnalisis',
            'herramientasLab' => function($query) {
                $query->select('inventario_lab.*', 'cotio_inventario_lab.cantidad',
                    'cotio_inventario_lab.observaciones as pivot_observaciones');
            },
            'vehiculo',
            'cotizacion.matriz'
        ])
        ->where('cotio_numcoti', $cotizacion)
        ->where('enable_inform', true)
        ->where('cotio_subitem', 0)
        ->orderBy('cotio_item')
        ->orderBy('instance_number')
        ->get();
    
        // Directorio temporal para mapas
        $tempDir = storage_path('app/temp_maps/');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $apiKey = config('services.google.maps_api_key');
        $mapPaths = [];
    
        // Procesar cada muestra
        foreach ($muestras as $muestra) {
            $muestra->showMap = !empty($muestra->latitud) && !empty($muestra->longitud);
            $muestra->localMapPath = null;
    
            if ($muestra->showMap) {
                $lat = $muestra->latitud;
                $lng = $muestra->longitud;
                $filename = 'map_'.$muestra->cotio_numcoti.'_'.$muestra->cotio_item.'_'.$muestra->instance_number.'.png';
                $localPath = $tempDir.$filename;
                
                try {
                    $mapUrl = "https://maps.googleapis.com/maps/api/staticmap?center=$lat,$lng&zoom=15&size=600x300&maptype=roadmap&markers=color:red%7C$lat,$lng&key=$apiKey";
                    file_put_contents($localPath, file_get_contents($mapUrl));
                    $muestra->localMapPath = $localPath;
                    $mapPaths[] = $localPath; // Guardar para limpieza posterior
                } catch (\Exception $e) {
                    Log::error("Error al descargar mapa para muestra {$muestra->id}: ".$e->getMessage());
                    $muestra->showMap = false;
                }
            }
    
            // Obtener análisis para esta muestra
            $muestra->analisis = \App\Models\CotioInstancia::with([
                'valoresVariables',
                'responsablesAnalisis'
            ])
            ->where('cotio_numcoti', $cotizacion)
            ->where('cotio_item', $muestra->cotio_item)
            ->where('instance_number', $muestra->instance_number)
            ->where('cotio_subitem', '>', 0)
            ->orderBy('cotio_subitem')
            ->get();
        }
    
        // Generar PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('informes.pdf_masivo', [
            'cotizacion' => $cotizacionObj,
            'muestras'   => $muestras
        ]);
    
        // Limpiar archivos temporales de mapas
        foreach ($mapPaths as $mapPath) {
            if (file_exists($mapPath)) {
                unlink($mapPath);
            }
        }

        // Descargar PDF masivo directamente (sin firma)
        return response()->make($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="informe-masivo-cotizacion-' . $cotizacion . '.pdf"',
        ]);
    }




    public function index(Request $request)
    {
        $verTodas = $request->query('ver_todas', false);
        $viewType = $request->get('view', 'lista');
        $matrices = Matriz::orderBy('matriz_descripcion')->get();
        $currentMonth = $request->get('month') ? Carbon::parse($request->get('month')) : now();
        
        // Consulta base para informes
        $baseQuery = CotioInstancia::with([
            'cotizacion.matriz',
            'tareas' => function($query) {
                $query->where('enable_inform', true)
                      ->orderBy('cotio_subitem');
            },
            'cotizacion.instancias'
        ])
        ->where('enable_inform', true)
        ->where('aprobado_informe', true)
        ->where('cotio_subitem', 0);

        // Aplicar filtros comunes
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = '%'.$request->search.'%';
            $baseQuery->whereHas('cotizacion', function($q) use ($searchTerm) {
                $q->where('coti_num', 'like', $searchTerm)
                  ->orWhereRaw('LOWER(coti_empresa) LIKE ?', [strtolower($searchTerm)])
                  ->orWhereRaw('LOWER(coti_establecimiento) LIKE ?', [strtolower($searchTerm)]);
            });
        }

        if ($request->has('matriz') && !empty($request->matriz)) {
            $baseQuery->whereHas('cotizacion', function($q) use ($request) {
                $q->where('coti_matriz', $request->matriz);
            });
        }

        if ($request->has('tipo_informe') && !empty($request->tipo_informe)) {
            $baseQuery->where(function($q) use ($request) {
                foreach ($this->getMuestrasPorTipoInforme($request->tipo_informe) as $muestraId) {
                    $q->orWhere('id', $muestraId);
                }
            });
        }

        if ($request->has('fecha_inicio_muestreo') && !empty($request->fecha_inicio_muestreo)) {
            $baseQuery->whereDate('fecha_inicio_muestreo', '>=', $request->fecha_inicio_muestreo);
        }

        if ($request->has('fecha_fin_muestreo') && !empty($request->fecha_fin_muestreo)) {
            $baseQuery->whereDate('fecha_fin_muestreo', '<=', $request->fecha_fin_muestreo);
        }

        // Vista de calendario
        if ($viewType === 'calendario') {
            $instancias = $baseQuery->orderBy('fecha_inicio_muestreo', 'asc')->get();

            $events = $instancias->map(function($instancia) {
                return [
                    'title' => $instancia->cotizacion->coti_empresa . ' - ' . $instancia->cotio_numcoti,
                    'start' => $instancia->fecha_creacion_inform,
                    'url' => route('informes.pdf', [
                        'cotio_numcoti' => $instancia->cotio_numcoti,
                        'cotio_item' => $instancia->cotio_item,
                        'instance_number' => $instancia->instance_number
                    ]),
                    'extendedProps' => [
                        'empresa' => $instancia->cotizacion->coti_empresa,
                        'muestra' => $instancia->cotio_descripcion,
                        'instancia' => $instancia->instance_number,
                    ],
                    'className' => 'informe-' . $this->determinarTipoInforme($instancia),
                ];
            });

            return view('informes.index', [
                'events' => $events,
                'viewType' => $viewType,
                'matrices' => $matrices,
                'currentMonth' => $currentMonth
            ]);
        }

        // Vista de lista o documento
        $pagination = $baseQuery
            ->orderBy('cotio_numcoti', $request->get('orden_cotizacion', 'desc'))
            ->orderBy('cotio_item', 'asc')
            ->orderBy('instance_number', 'asc')
            ->paginate($viewType === 'documento' ? 20 : 10);

        // Agrupar por cotización
        $informesPorCotizacion = $pagination->groupBy('cotio_numcoti')->map(function ($group) {
            $cotizacion = $group->first()->cotizacion;
            
            return [
                'cotizacion' => $cotizacion,
                'muestras' => $group->map(function($muestra) {
                    $muestra->tipo_informe = $this->determinarTipoInforme($muestra);
                    return $muestra;
                }),
                'total_muestras' => $group->count(),
                'informes_finales' => $group->filter(function($muestra) {
                    return $this->determinarTipoInforme($muestra) === 'final';
                })->count(),
                'informes_firmados' => $group->filter(function($muestra) {
                    return $muestra->firmado;
                })->count()
            ];
        });


        return view('informes.index', [
            'informesPorCotizacion' => $informesPorCotizacion,
            'pagination' => $pagination,
            'viewType' => $viewType,
            'matrices' => $matrices,
            'request' => $request
        ]);
    }







    /**
     * Obtiene IDs de muestras según el tipo de informe solicitado
     */
    protected function getMuestrasPorTipoInforme($tipo)
    {
        return CotioInstancia::where('enable_inform', true)
            ->where('aprobado_informe', true)
            ->where('cotio_subitem', 0)
            ->get()
            ->filter(function($muestra) use ($tipo) {
                return $this->determinarTipoInforme($muestra) === $tipo;
            })
            ->pluck('id');
    }

    /**
     * Determina el tipo de informe para una muestra
     */
    protected function determinarTipoInforme($muestra)
    {
        if ($muestra->tareas->isEmpty()) {
            return 'sin-analisis';
        }

        foreach ($muestra->tareas as $analisis) {
            if (empty($analisis->resultado) && 
                empty($analisis->resultado_2) && 
                empty($analisis->resultado_3) && 
                empty($analisis->resultado_final)) {
                return 'parcial';
            }
        }

        return 'final';
    }


    public function show($cotio_numcoti, $cotio_item, $instance_number)
    {
        // Obtener la muestra específica con sus análisis
        $muestra = CotioInstancia::with(['tareas' => function($query) {
                        $query->where('enable_inform', true)
                              ->orderBy('cotio_subitem');
                    }])
                    ->where('cotio_numcoti', $cotio_numcoti)
                    ->where('cotio_item', $cotio_item)
                    ->where('instance_number', $instance_number)
                    ->where('cotio_subitem', 0)
                    ->firstOrFail();

        $tipoInforme = $this->determinarTipoInforme($muestra);

        return view('informes.show', compact('muestra', 'tipoInforme'));
    }


    
    /**
     * Genera PDF sin firma digital
     */
    public function generarPdf($cotio_numcoti, $cotio_item, $instance_number)
    {
        $muestra = CotioInstancia::with([
            'tareas',
            'cotizacion.matriz',
            'valoresVariables' => function($query) {
                $query->orderBy('variable');
            },
            'responsablesAnalisis',
            'herramientasLab' => function($query) {
                $query->select('inventario_lab.*', 'cotio_inventario_lab.cantidad', 
                              'cotio_inventario_lab.observaciones as pivot_observaciones');
            },
            'vehiculo'
        ])
        ->where('cotio_numcoti', $cotio_numcoti)
        ->where('cotio_item', $cotio_item)
        ->where('instance_number', $instance_number)
        ->where('cotio_subitem', 0)
        ->firstOrFail();
    
        $analisis = CotioInstancia::with([
            'responsablesAnalisis',
            'herramientasLab'
        ])
        ->where('cotio_numcoti', $cotio_numcoti)
        ->where('cotio_item', $cotio_item)
        ->where('instance_number', $instance_number)
        ->where('cotio_subitem', '>', 0)
        ->get();
    
        $tipoInforme = $this->determinarTipoInforme($muestra);
        $showMap = !empty($muestra->latitud) && !empty($muestra->longitud);
        $localMapPath = null;

        if ($showMap) {
            $apiKey = config('services.google.maps_api_key');
            $lat = $muestra->latitud;
            $lng = $muestra->longitud;
            $mapUrl = "https://maps.googleapis.com/maps/api/staticmap?center=$lat,$lng&zoom=15&size=600x300&maptype=roadmap&markers=color:red%7C$lat,$lng&key=$apiKey";
            
            // Descargar la imagen y guardarla temporalmente
            $tempDir = storage_path('app/temp_maps/');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            $filename = 'map_' . $cotio_numcoti . '_' . $cotio_item . '_' . $instance_number . '.png';
            $localMapPath = $tempDir . $filename;
            
            try {
                file_put_contents($localMapPath, file_get_contents($mapUrl));
            } catch (\Exception $e) {
                Log::error("Error al descargar el mapa: " . $e->getMessage());
                $showMap = false;
            }
        }
    


        $pdf = Pdf::loadView('informes.pdf', [
            'muestra'    => $muestra,
            'analisis'   => $analisis,
            'tipoInforme'=> $tipoInforme,
            'showMap'    => $showMap,
            'localMapPath'=> $localMapPath ?? null
        ]);
    
        // Si ya está firmado, obtener el documento firmado
        if ($muestra->firmado && $muestra->identificador_documento_firma) {
            $firmaService = new FirmaDigitalService();
            $resultado = $firmaService->obtenerDocumentoFirmado($muestra->identificador_documento_firma);
            
            if ($resultado['success'] && $resultado['documento_base64']) {
                $pdfFirmado = base64_decode($resultado['documento_base64']);
                
                return response()->make($pdfFirmado, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="informe-firmado-' . $cotio_numcoti . '-' . $cotio_item . '-' . $instance_number . '.pdf"',
                ]);
            }
        }

        // Generar PDF normal (sin firma)
        return response()->make($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="informe-' . $cotio_numcoti . '-' . $cotio_item . '-' . $instance_number . '.pdf"',
        ]);
    }

    /**
     * Inicia el proceso de firma digital para un informe individual
     */
    public function firmarInforme($cotio_numcoti, $cotio_item, $instance_number)
    {
        $muestra = CotioInstancia::where('cotio_numcoti', $cotio_numcoti)
            ->where('cotio_item', $cotio_item)
            ->where('instance_number', $instance_number)
            ->where('cotio_subitem', 0)
            ->firstOrFail();

        // Si ya está firmado, no hacer nada
        if ($muestra->firmado) {
            return response()->json(['error' => 'Este informe ya está firmado'], 400);
        }

        // Generar PDF para firmar
        $pdf = $this->generarPdfParaFirma($cotio_numcoti, $cotio_item, $instance_number);
        $pdfBinary = $pdf->output();

        // Enviar a la API de firma digital
        $firmaService = new FirmaDigitalService();
        $resultado = $firmaService->firmarDocumento($pdfBinary, "20000000019", "30765432109");

        if ($resultado && $resultado['success'] === true) {
            // Guardar identificador del documento en la BD
            $muestra->update([
                'identificador_documento_firma' => $resultado['identificador_documento']
            ]);

            // Si la API devuelve success, redirigir al usuario a la URL de autorización
            $urlAutorizacion = $resultado['url_autorizacion'];
            
            Log::info("🔗 Redirigiendo a URL de autorización", [
                'url' => $urlAutorizacion,
                'url_length' => strlen($urlAutorizacion),
                'identificador_documento' => $resultado['identificador_documento'] ?? null
            ]);
            
            if ($urlAutorizacion) {
                // Guardar información del documento para después de la firma
                session([
                    'documento_pendiente_firma' => [
                        'identificador' => $resultado['identificador_documento'],
                        'tipo' => 'informe_individual',
                        'cotio_numcoti' => $cotio_numcoti,
                        'cotio_item' => $cotio_item,
                        'instance_number' => $instance_number,
                        'timestamp' => now()
                    ]
                ]);
                
                // Usar redirect()->away() para redirigir a URL externa
                // Si esto no funciona, probar con header Location directo
                try {
                    return redirect()->away($urlAutorizacion);
                } catch (\Exception $e) {
                    Log::error("❌ Error en redirect()->away(), usando header Location directo", [
                        'error' => $e->getMessage(),
                        'url' => $urlAutorizacion
                    ]);
                    
                    // Fallback: usar header Location directo
                    return response('', 302)->header('Location', $urlAutorizacion);
                }
            }
        }
        
        // Si hay error, mostrar detalles
        $errorMessage = 'No se pudo procesar la firma digital';
        if ($resultado && isset($resultado['error'])) {
            $errorMessage = $resultado['error'];
        }

        return response()->json([
            'error' => $errorMessage,
            'details' => $resultado['details'] ?? null
        ], 500);
    }

    /**
     * Método auxiliar para generar PDF sin exponer la lógica de generación
     */
    private function generarPdfParaFirma($cotio_numcoti, $cotio_item, $instance_number)
    {
        $muestra = CotioInstancia::with([
            'tareas',
            'cotizacion.matriz',
            'valoresVariables' => function($query) {
                $query->orderBy('variable');
            },
            'responsablesAnalisis',
            'herramientasLab' => function($query) {
                $query->select('inventario_lab.*', 'cotio_inventario_lab.cantidad', 
                              'cotio_inventario_lab.observaciones as pivot_observaciones');
            },
            'vehiculo'
        ])
        ->where('cotio_numcoti', $cotio_numcoti)
        ->where('cotio_item', $cotio_item)
        ->where('instance_number', $instance_number)
        ->where('cotio_subitem', 0)
        ->firstOrFail();
    
        $analisis = CotioInstancia::with([
            'responsablesAnalisis',
            'herramientasLab'
        ])
        ->where('cotio_numcoti', $cotio_numcoti)
        ->where('cotio_item', $cotio_item)
        ->where('instance_number', $instance_number)
        ->where('cotio_subitem', '>', 0)
        ->get();
    
        $tipoInforme = $this->determinarTipoInforme($muestra);
        $showMap = !empty($muestra->latitud) && !empty($muestra->longitud);
        $localMapPath = null;

        if ($showMap) {
            $apiKey = config('services.google.maps_api_key');
            $lat = $muestra->latitud;
            $lng = $muestra->longitud;
            $mapUrl = "https://maps.googleapis.com/maps/api/staticmap?center=$lat,$lng&zoom=15&size=600x300&maptype=roadmap&markers=color:red%7C$lat,$lng&key=$apiKey";
            
            // Descargar la imagen y guardarla temporalmente
            $tempDir = storage_path('app/temp_maps/');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            $filename = 'map_' . $cotio_numcoti . '_' . $cotio_item . '_' . $instance_number . '.png';
            $localMapPath = $tempDir . $filename;
            
            try {
                file_put_contents($localMapPath, file_get_contents($mapUrl));
            } catch (\Exception $e) {
                Log::error("Error al descargar el mapa: " . $e->getMessage());
                $showMap = false;
            }
        }

        return Pdf::loadView('informes.pdf', [
            'muestra'    => $muestra,
            'analisis'   => $analisis,
            'tipoInforme'=> $tipoInforme,
            'showMap'    => $showMap,
            'localMapPath'=> $localMapPath ?? null
        ]);
    }

    public function getInformeData($cotio_numcoti, $cotio_item, $instance_number)
    {
        $muestra = CotioInstancia::with([
            'cotizacion.matriz',
            'tareas',
            'valoresVariables' => function($query) {
                $query->select('id', 'cotio_instancia_id', 'variable', 'valor')
                      ->orderBy('variable');
            },
            'responsablesAnalisis',
            'herramientasLab' => function($query) {
                $query->select('inventario_lab.*', 'cotio_inventario_lab.cantidad', 
                              'cotio_inventario_lab.observaciones as pivot_observaciones');
            },
            'vehiculo' // Asegúrate de que esta relación esté definida en el modelo
        ])
        ->where('cotio_numcoti', $cotio_numcoti)
        ->where('cotio_item', $cotio_item)
        ->where('instance_number', $instance_number)
        ->where('cotio_subitem', 0)
        ->firstOrFail();

        Log::info($muestra->valoresVariables);
    
        $analisis = CotioInstancia::with([
            'responsablesAnalisis',
            'herramientasLab' => function($query) {
                $query->select('inventario_lab.*', 'cotio_inventario_lab.cantidad', 
                              'cotio_inventario_lab.observaciones as pivot_observaciones');
            }
        ])
        ->where('cotio_numcoti', $cotio_numcoti)
        ->where('cotio_item', $cotio_item)
        ->where('instance_number', $instance_number)
        ->where('cotio_subitem', '>', 0)
        ->get();
    
        return response()->json([
            'cotizacion' => $muestra->cotizacion,
            'muestra' => $muestra,
            'analisis' => $analisis,
            'vehiculo' => $muestra->vehiculo, // Asegúrate de incluir esto si lo necesitas
            'valoresVariables' => $muestra->valoresVariables
        ]);
    }



    
    public function updateInforme(Request $request, $cotio_numcoti, $cotio_item, $instance_number)
    {
        DB::beginTransaction();

        Log::info($request->all());
        
        try {
            // Actualizar muestra principal
            $muestra = CotioInstancia::where('cotio_numcoti', $cotio_numcoti)
                        ->where('cotio_item', $cotio_item)
                        ->where('instance_number', $instance_number)
                        ->where('cotio_subitem', 0)
                        ->firstOrFail();
            
            $muestra->update([
                'resultado' => $request->input('muestra.resultado'),
                'resultado_2' => $request->input('muestra.resultado_2'),
                'resultado_3' => $request->input('muestra.resultado_3'),
                'resultado_final' => $request->input('muestra.resultado_final'),
                'observaciones' => $request->input('muestra.observaciones_generales'),
                'observacion_resultado' => $request->input('muestra.observacion_resultado'),
                'observacion_resultado_2' => $request->input('muestra.observacion_resultado_2'),
                'observacion_resultado_3' => $request->input('muestra.observacion_resultado_3'),
                'observacion_resultado_final' => $request->input('muestra.observacion_resultado_final'),
                'cotio_identificacion' => $request->input('muestra.cotio_identificacion'),
                // 'vehiculo_asignado' => $request->input('muestra.vehiculo_asignado'),
                'latitud' => $request->input('muestra.latitud'),
                'longitud' => $request->input('muestra.longitud'),
            ]);
            
            // Actualizar análisis
            if ($request->has('analisis')) {
                foreach ($request->input('analisis') as $analisisData) {
                    $analisis = CotioInstancia::where('id', $analisisData['id'])
                                  ->where('cotio_numcoti', $cotio_numcoti)
                                  ->where('cotio_item', $cotio_item)
                                  ->where('instance_number', $instance_number)
                                  ->first();
                    
                    if ($analisis) {
                        $analisis->update([
                            'resultado' => $analisisData['resultado'],
                            'observacion_resultado' => $analisisData['observacion_resultado'],
                            'resultado_2' => $analisisData['resultado_2'],
                            'observacion_resultado_2' => $analisisData['observacion_resultado_2'],
                            'resultado_3' => $analisisData['resultado_3'],
                            'observacion_resultado_3' => $analisisData['observacion_resultado_3'],
                            'resultado_final' => $analisisData['resultado_final'],
                            'observacion_resultado_final' => $analisisData['observacion_resultado_final'],
                        ]);
                    }
                }
            }
            
            // Actualizar variables
            if ($request->has('variables')) {
                foreach ($request->input('variables') as $variableData) {
                    // Si no tiene 'id', lo ignoramos
                    if (empty($variableData['id'])) {
                        continue;
                    }
            
                    $variable = $muestra->valoresVariables()
                                  ->where('id', $variableData['id'])
                                  ->first();
            
                    if ($variable) {
                        $variable->update([
                            'valor' => $variableData['valor'] ?? null,
                            'observaciones' => $variableData['observaciones'] ?? null,
                        ]);
                    }
                }
            }
            
            DB::commit();
            
            return response()->json(['success' => true]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Callback para cuando la firma es exitosa
     */
    public function firmaExitosa(Request $request)
    {
        Log::info("✅ Callback de firma exitosa recibido", $request->all());
        
        $documentoPendiente = session('documento_pendiente_firma');
        
        if (!$documentoPendiente) {
            Log::warning("⚠️ No hay documento pendiente de firma en la sesión");
            return redirect()->route('informes.index')->with('error', 'No hay documento pendiente de firma');
        }
        
        try {
            // Marcar el documento como firmado en la BD inmediatamente
            if ($documentoPendiente['tipo'] === 'informe_individual') {
                $muestra = CotioInstancia::where('cotio_numcoti', $documentoPendiente['cotio_numcoti'])
                    ->where('cotio_item', $documentoPendiente['cotio_item'])
                    ->where('instance_number', $documentoPendiente['instance_number'])
                    ->where('cotio_subitem', 0)
                    ->first();
                    
                if ($muestra) {
                    $muestra->update([
                        'firmado' => true,
                        'fecha_firma' => now(),
                        'identificador_documento_firma' => $documentoPendiente['identificador']
                    ]);
                    
                    Log::info("✅ Documento marcado como firmado en la BD", [
                        'cotio_numcoti' => $documentoPendiente['cotio_numcoti'],
                        'cotio_item' => $documentoPendiente['cotio_item'],
                        'instance_number' => $documentoPendiente['instance_number'],
                        'identificador' => $documentoPendiente['identificador']
                    ]);
                } else {
                    Log::error("❌ No se encontró la muestra para marcar como firmada", $documentoPendiente);
                }
            }
            
            // Limpiar la sesión
            session()->forget('documento_pendiente_firma');
            
            Log::info("🎉 Proceso de firma completado exitosamente");
            
            // Redirigir a informes con mensaje de éxito
            return redirect()->route('informes.index')->with('success', 'El documento ha sido firmado exitosamente. El archivo firmado estará disponible en breve.');
            
        } catch (\Exception $e) {
            Log::error("🚨 Error en callback de firma exitosa", [
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine()
            ]);
            
            return redirect()->route('informes.index')->with('error', 'Error al procesar la firma: ' . $e->getMessage());
        }
    }
    
    /**
     * Callback para cuando hay error en la firma
     */
    public function firmaError(Request $request)
    {
        Log::error("❌ Callback de error en firma", $request->all());
        
        session()->forget('documento_pendiente_firma');
        
        return view('errors.firma-error', [
            'mensaje' => 'Hubo un error durante el proceso de firma digital'
        ]);
    }
    
    /**
     * Callback para cuando se rechaza la firma
     */
    public function firmaRechazada(Request $request)
    {
        Log::info("⚠️ Callback de firma rechazada", $request->all());
        
        session()->forget('documento_pendiente_firma');
        
        return view('errors.firma-rechazada', [
            'mensaje' => 'La firma digital fue rechazada por el usuario'
        ]);
    }
    
    /**
     * Descarga el documento firmado
     */
    public function descargarDocumentoFirmado($cotio_numcoti, $cotio_item, $instance_number)
    {
        try {
            $muestra = CotioInstancia::where('cotio_numcoti', $cotio_numcoti)
                ->where('cotio_item', $cotio_item)
                ->where('instance_number', $instance_number)
                ->where('cotio_subitem', 0)
                ->first();
                
            if (!$muestra) {
                return redirect()->route('informes.index')->with('error', 'Muestra no encontrada');
            }
            
            if (!$muestra->firmado || !$muestra->identificador_documento_firma) {
                return redirect()->route('informes.index')->with('error', 'El documento no está firmado');
            }
            
            $firmaService = new FirmaDigitalService();
            $resultado = $firmaService->obtenerDocumentoFirmado($muestra->identificador_documento_firma);
            
            if ($resultado['success'] && $resultado['documento_base64']) {
                $pdfFirmado = base64_decode($resultado['documento_base64']);
                $timestamp = now()->format('Y-m-d_H-i-s');
                $filename = "informe-firmado-{$cotio_numcoti}-{$cotio_item}-{$instance_number}-{$timestamp}.pdf";
                
                return response()->make($pdfFirmado, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ]);
            }
            
            return redirect()->route('informes.index')->with('error', 'No se pudo obtener el documento firmado: ' . ($resultado['error'] ?? 'Error desconocido'));
            
        } catch (\Exception $e) {
            Log::error("🚨 Error al descargar documento firmado", [
                'mensaje' => $e->getMessage(),
                'cotio_numcoti' => $cotio_numcoti,
                'cotio_item' => $cotio_item,
                'instance_number' => $instance_number
            ]);
            
            return redirect()->route('informes.index')->with('error', 'Error al descargar el documento: ' . $e->getMessage());
        }
    }

    /**
     * Genera el nombre del archivo según el tipo de documento
     */
    private function generarNombreArchivo($documentoPendiente)
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        
        if ($documentoPendiente['tipo'] === 'cotizacion_masiva') {
            return "informe-firmado-cotizacion-{$documentoPendiente['cotizacion']}-{$timestamp}.pdf";
        } elseif ($documentoPendiente['tipo'] === 'informe_individual') {
            return "informe-firmado-{$documentoPendiente['cotio_numcoti']}-{$documentoPendiente['cotio_item']}-{$documentoPendiente['instance_number']}-{$timestamp}.pdf";
        }
        
        return "documento-firmado-{$timestamp}.pdf";
    }


}