<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\FacadesLog;


use App\Models\Coti;
use App\Models\Cotio;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\InventarioLab;
use App\Models\CotioResponsable;
use App\Models\InventarioMuestreo;
use App\Models\CotioInstancia;
use App\Models\CotioInventarioMuestreo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\CotioValorVariable;

use App\Models\SimpleNotification;

class CotioController extends Controller
{



public function updateFechaCarga(Request $request)
{
    $request->validate([
        'cotio_numcoti' => 'required|string',
        'cotio_item' => 'required|integer',
        'cotio_subitem' => 'required|integer',
        'instance_number' => 'required|integer',
        'fecha_carga_ot' => 'required|date'
    ]);

    $instancia = CotioInstancia::where([
        'cotio_numcoti' => $request->cotio_numcoti,
        'cotio_item' => $request->cotio_item,
        'cotio_subitem' => $request->cotio_subitem,
        'instance_number' => $request->instance_number
    ])->firstOrFail();
    
    $instancia->fecha_carga_ot = $request->fecha_carga_ot;
    $instancia->save();

    return response()->json([
        'success' => true,
        'message' => 'Fecha de carga actualizada correctamente'
    ]);
}

public function pasarMuestreo(Request $request)
{
    $request->validate([
        'cotio_numcoti' => 'required|string',
        'cambios' => 'required|array'
    ]);

    try {
        DB::beginTransaction();

        $updatedCount = 0;
        $failedUpdates = [];
        $userId = Auth::user()->usu_codigo;
        $cotioNumcoti = $request->cotio_numcoti;
        $cambios = $request->cambios;

        // Precarga de ítems de cotio agrupados por subitem
        $allItems = Cotio::where('cotio_numcoti', $cotioNumcoti)
            ->get()
            ->keyBy(function ($item) {
                return "{$item->cotio_item}-{$item->cotio_subitem}";
            });

        // Función auxiliar para obtener descripción y precio
        $getCotioData = function ($item, $subitem) use ($allItems) {
            $key = "{$item}-{$subitem}";
            $cotio = $allItems->get($key);
            return [
                'descripcion' => $cotio?->cotio_descripcion,
                'precio' => $cotio?->cotio_precio ? round($cotio->cotio_precio, 2) : null,
                'cotio_codigometodo' => $cotio?->cotio_codigometodo,
                'cotio_codigometodo_analisis' => $cotio?->cotio_codigometodo_analisis
            ];
        };

        foreach ($cambios as $key => $activado) {
            if (!preg_match('/^(\d+)-(\d+)-(\d+)$/', $key, $m)) {
                throw new \Exception("Formato inválido para key: {$key}");
            }
            [, $item, $subitem, $instance] = $m;

            // 1) Instancia de muestra (subitem = 0)
            if ($subitem == 0) {
                $instancia = CotioInstancia::firstOrNew([
                    'cotio_numcoti' => $cotioNumcoti,
                    'cotio_item' => $item,
                    'cotio_subitem' => 0,
                    'instance_number' => $instance,
                ]);

                // Obtener descripción y precio
                $cotioData = $getCotioData($item, 0);
                if ($cotioData['descripcion'] && !$instancia->cotio_descripcion) {
                    $instancia->cotio_descripcion = $cotioData['descripcion'];
                }
                if ($cotioData['precio'] !== null) {
                    $instancia->monto = $cotioData['precio'];
                }
                
                // Copiar ambos métodos siempre desde Cotio
                if (isset($cotioData['cotio_codigometodo']) && $cotioData['cotio_codigometodo'] !== null) {
                    $instancia->cotio_codigometodo = $cotioData['cotio_codigometodo'];
                }
                if (isset($cotioData['cotio_codigometodo_analisis']) && $cotioData['cotio_codigometodo_analisis'] !== null) {
                    $instancia->cotio_codigometodo_analisis = $cotioData['cotio_codigometodo_analisis'];
                }

                $instancia->active_muestreo = $activado;
                if ($activado) {
                    $instancia->fecha_muestreo = now();
                    $instancia->coordinador_codigo = $userId;
                } else {
                    $instancia->fecha_muestreo = null;
                    $instancia->coordinador_codigo = null;
                }

                $instancia->save();
                $updatedCount++;

                // Si se activó la muestra, crear/copiar análisis
                if ($activado) {
                    $analisis = $allItems->filter(function ($tarea) use ($item) {
                        return $tarea->cotio_subitem > 0 && $tarea->cotio_item == $item;
                    });

                    foreach ($analisis as $tarea) {
                        $analisisKey = "{$item}-{$tarea->cotio_subitem}-{$instance}";
                        $checked = !empty($cambios[$analisisKey]);

                        $analisisInst = CotioInstancia::firstOrNew([
                            'cotio_numcoti' => $cotioNumcoti,
                            'cotio_item' => $item,
                            'cotio_subitem' => $tarea->cotio_subitem,
                            'instance_number' => $instance,
                        ]);

                        // Asignar descripción y precio del análisis
                        $cotioData = $getCotioData($item, $tarea->cotio_subitem);
                        if ($cotioData['descripcion'] && !$analisisInst->cotio_descripcion) {
                            $analisisInst->cotio_descripcion = $cotioData['descripcion'];
                        }
                        if ($cotioData['precio'] !== null) {
                            $analisisInst->monto = $cotioData['precio'];
                        }
                        
                        // Copiar ambos métodos siempre desde Cotio
                        if (isset($cotioData['cotio_codigometodo']) && $cotioData['cotio_codigometodo'] !== null) {
                            $analisisInst->cotio_codigometodo = $cotioData['cotio_codigometodo'];
                        }
                        if (isset($cotioData['cotio_codigometodo_analisis']) && $cotioData['cotio_codigometodo_analisis'] !== null) {
                            $analisisInst->cotio_codigometodo_analisis = $cotioData['cotio_codigometodo_analisis'];
                        }

                        $analisisInst->active_muestreo = $checked;
                        if ($checked) {
                            $analisisInst->fecha_muestreo = now();
                            $analisisInst->coordinador_codigo = $userId;
                        }

                        $analisisInst->save();
                        $updatedCount++;
                    }
                }

            // 2) Instancia de análisis individual
            } else {
                $instAn = CotioInstancia::firstOrNew([
                    'cotio_numcoti' => $cotioNumcoti,
                    'cotio_item' => $item,
                    'cotio_subitem' => $subitem,
                    'instance_number' => $instance,
                ]);

                // Obtener descripción y precio
                $cotioData = $getCotioData($item, $subitem);
                if ($cotioData['descripcion'] && !$instAn->cotio_descripcion) {
                    $instAn->cotio_descripcion = $cotioData['descripcion'];
                }
                if ($cotioData['precio'] !== null) {
                    $instAn->monto = $cotioData['precio'];
                }
                
                // Copiar ambos métodos siempre desde Cotio
                if (isset($cotioData['cotio_codigometodo']) && $cotioData['cotio_codigometodo'] !== null) {
                    $instAn->cotio_codigometodo = $cotioData['cotio_codigometodo'];
                }
                if (isset($cotioData['cotio_codigometodo_analisis']) && $cotioData['cotio_codigometodo_analisis'] !== null) {
                    $instAn->cotio_codigometodo_analisis = $cotioData['cotio_codigometodo_analisis'];
                }

                $instAn->active_muestreo = $activado;
                if ($activado) {
                    $instAn->fecha_muestreo = now();
                    $instAn->coordinador_codigo = $userId;
                } else {
                    $instAn->fecha_muestreo = null;
                    $instAn->coordinador_codigo = null;
                }

                $instAn->save();
                $updatedCount++;
            }
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => "{$updatedCount} registros creados/actualizados correctamente",
            'updated_count' => $updatedCount,
            'failed_updates' => $failedUpdates
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'error_details' => $e->getTraceAsString()
        ], 500);
    }
}
    
protected function actualizarContadorMuestra($cotioNumcoti, $item, $cantidadTotal)
{
    $muestreadas = CotioInstancia::where('cotio_numcoti', $cotioNumcoti)
        ->where('cotio_item', $item)
        ->where('cotio_subitem', 0)
        ->whereNotNull('fecha_muestreo')
        ->count();
    
    Cotio::where('cotio_numcoti', $cotioNumcoti)
        ->where('cotio_item', $item)
        ->where('cotio_subitem', 0)
        ->update([
            'muestreo_contador' => "$muestreadas/$cantidadTotal",
            'enable_muestreo' => $muestreadas > 0
        ]);
}


public function asignarSuspensionMuestra(Request $request)
{
    $validated = $request->validate([
        'cotio_numcoti' => 'required|string',
        'cotio_item' => 'required|integer',
        'instance_number' => 'required|integer',
        'cotio_observaciones_suspension' => 'required|string|max:500',
    ]);

    try {
        $instancia = CotioInstancia::where([
            'cotio_numcoti' => $validated['cotio_numcoti'],
            'cotio_item' => $validated['cotio_item'],
            'cotio_subitem' => 0,
            'instance_number' => $validated['instance_number']
        ])->firstOrFail();

        $instancia->update([
            'cotio_observaciones_suspension' => $validated['cotio_observaciones_suspension'],
            'cotio_estado' => 'suspension',
        ]);


        return redirect()->back()->with('success', 'La muestra ha sido suspendida correctamente.');

    } catch (\Exception $e) {
        return redirect()->back()
            ->with('error', 'Ocurrió un error al suspender la muestra: ' . $e->getMessage())
            ->withInput();
    }
}



    
public function asignarDetalles(Request $request)
{
    $validated = $request->validate([
        'cotio_numcoti' => 'required|string',
        'cotio_item' => 'required|integer',
        'instance' => 'required|integer',
        'vehiculo_asignado' => 'sometimes|nullable|integer',
        'responsable_codigo' => 'sometimes|nullable|string',
        'fecha_inicio_muestreo' => 'sometimes|nullable|date',
        'fecha_fin_muestreo' => 'sometimes|nullable|date|after_or_equal:fecha_inicio_muestreo',
        'herramientas' => 'sometimes|nullable|array',
        'herramientas.*' => 'sometimes|integer',
        'tareas_seleccionadas' => 'sometimes|nullable|array',
        'tareas_seleccionadas.*' => 'sometimes|string'
    ]);

    DB::beginTransaction();
    try {
        $instanciaActual = CotioInstancia::firstOrNew([
            'cotio_numcoti' => $validated['cotio_numcoti'],
            'cotio_item' => $validated['cotio_item'],
            'cotio_subitem' => 0,
            'instance_number' => $validated['instance']
        ]);

        // Si es una nueva instancia, copiar métodos desde cotio
        if (!$instanciaActual->exists) {
            $muestra = Cotio::where('cotio_numcoti', $validated['cotio_numcoti'])
                ->where('cotio_item', $validated['cotio_item'])
                ->where('cotio_subitem', 0)
                ->first();
            if ($muestra) {
                $instanciaActual->cotio_codigometodo = $muestra->cotio_codigometodo;
            }
        }

        $updateData = [];
        
        if ($request->has('vehiculo_asignado')) {
            $updateData['vehiculo_asignado'] = $validated['vehiculo_asignado'];
        }
        
        if ($request->filled('responsable_codigo')) {
            $instanciaActual->responsable_muestreo = $validated['responsable_codigo'] === 'NULL' ? null : $validated['responsable_codigo'];
        }
        
        if ($request->has('fecha_inicio_muestreo')) {
            $updateData['fecha_inicio_muestreo'] = $validated['fecha_inicio_muestreo'];
        }
        
        if ($request->has('fecha_fin_muestreo')) {
            $updateData['fecha_fin_muestreo'] = $validated['fecha_fin_muestreo'];
        }

        // Actualizar solo si hay datos para actualizar
        if (!empty($updateData)) {
            $instanciaActual->fill($updateData)->save();
        }

        // Actualizar herramientas solo si vienen en la solicitud
        if ($request->has('herramientas')) {
            $this->actualizarHerramientas(
                $validated['cotio_numcoti'],
                $validated['cotio_item'],
                0, 
                $validated['instance'],
                $validated['herramientas']
            );
        }

        // Actualizar análisis seleccionados
        if ($request->has('tareas_seleccionadas') && !empty($validated['tareas_seleccionadas'])) {
            foreach ($validated['tareas_seleccionadas'] as $tarea) {
                [$item, $subitem] = explode('_', $tarea);
                
                $instanciaAnalisis = CotioInstancia::firstOrNew([
                    'cotio_numcoti' => $validated['cotio_numcoti'],
                    'cotio_item' => $item,
                    'cotio_subitem' => $subitem,
                    'instance_number' => $validated['instance']
                ]);

                // Si es una nueva instancia, copiar métodos desde cotio
                if (!$instanciaAnalisis->exists) {
                    $analisis = Cotio::where('cotio_numcoti', $validated['cotio_numcoti'])
                        ->where('cotio_item', $item)
                        ->where('cotio_subitem', $subitem)
                        ->first();
                    if ($analisis) {
                        $instanciaAnalisis->cotio_codigometodo_analisis = $analisis->cotio_codigometodo_analisis;
                    }
                }

                // Solo actualizar los campos que vienen en la solicitud
                $updateAnalisisData = [];
                
                if ($request->has('vehiculo_asignado')) {
                    $updateAnalisisData['vehiculo_asignado'] = $validated['vehiculo_asignado'];
                }
                
                if ($request->filled('responsable_codigo')) {
                    $updateAnalisisData['responsable_muestreo'] = $validated['responsable_codigo'] === 'NULL' ? null : $validated['responsable_codigo'];
                }
                
                if ($request->has('fecha_inicio_muestreo')) {
                    $updateAnalisisData['fecha_inicio_muestreo'] = $validated['fecha_inicio_muestreo'];
                }
                
                if ($request->has('fecha_fin_muestreo')) {
                    $updateAnalisisData['fecha_fin_muestreo'] = $validated['fecha_fin_muestreo'];
                }

                if (!empty($updateAnalisisData)) {
                    $instanciaAnalisis->fill($updateAnalisisData)->save();
                }

                // Actualizar herramientas solo si vienen en la solicitud
                if ($request->has('herramientas')) {
                    $this->actualizarHerramientas(
                        $validated['cotio_numcoti'],
                        $item,
                        $subitem,
                        $validated['instance'],
                        $validated['herramientas']
                    );
                }
            }
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Detalles asignados correctamente'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Error al asignar detalles: ' . $e->getMessage()
        ], 500);
    }
}



protected function actualizarHerramientas($cotioNumcoti, $cotioItem, $cotioSubitem, $instanceNumber, $herramientasSeleccionadas)
{
    CotioInventarioMuestreo::where([
        'cotio_numcoti' => $cotioNumcoti,
        'cotio_item' => $cotioItem,
        'cotio_subitem' => $cotioSubitem,
        'instance_number' => $instanceNumber
    ])->delete();

    foreach ($herramientasSeleccionadas as $herramientaId) {
        DB::table('cotio_inventario_muestreo')->insert([
            'cotio_numcoti' => $cotioNumcoti,
            'cotio_item' => $cotioItem,
            'cotio_subitem' => $cotioSubitem,
            'instance_number' => $instanceNumber,
            'inventario_muestreo_id' => $herramientaId,
            'cantidad' => 1,
            'observaciones' => null
        ]);
    }
}






public function asignarFrecuencia(Request $request) 
{
    $request->validate([
        'cotio_numcoti' => 'required',
        'cotio_item' => 'required',
        'cotio_subitem' => 'required|numeric',
        'es_frecuente' => 'required|boolean',
        'frecuencia_dias' => 'required|string|in:diario,semanal,quincenal,mensual,trimestral,cuatr,semestral,anual',
        'tareas_seleccionadas' => 'nullable|array'
    ]);

    try {
        DB::beginTransaction();

        // Actualizar la categoría
        $categoria = Cotio::where('cotio_numcoti', $request->cotio_numcoti)
                        ->where('cotio_item', $request->cotio_item)
                        ->where('cotio_subitem', 0) 
                        ->firstOrFail();

        $categoria->es_frecuente = $request->es_frecuente;
        $categoria->frecuencia_dias = $request->frecuencia_dias;
        $categoria->save();

        // Actualizar las tareas seleccionadas si existen
        if ($request->has('tareas_seleccionadas') && !empty($request->tareas_seleccionadas)) {
            foreach ($request->tareas_seleccionadas as $tarea) {
                $tareaModel = Cotio::where('cotio_numcoti', $request->cotio_numcoti)
                                ->where('cotio_item', $tarea['item'])
                                ->where('cotio_subitem', $tarea['subitem'])
                                ->first();

                if ($tareaModel) {
                    $tareaModel->es_frecuente = $request->es_frecuente;
                    $tareaModel->frecuencia_dias = $request->frecuencia_dias;
                    $tareaModel->save();
                }
            }
        }

        DB::commit();

        return response()->json([
            'success' => true, 
            'message' => 'Frecuencia actualizada correctamente'
        ]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Error al asignar frecuencia: ' . $e->getMessage(),
            'error_details' => [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ], 500);
    }
}







public function asignarResponsableTareaIndividual(Request $request)
{
    $request->validate([
        'cotio_numcoti' => 'required',
        'cotio_item' => 'required',
        'cotio_subitem' => 'required',
        'usuario_id' => 'required|exists:usu,usu_codigo'
    ]);

    try {
        $tarea = Cotio::where('cotio_numcoti', $request->cotio_numcoti)
                     ->where('cotio_item', $request->cotio_item)
                     ->where('cotio_subitem', $request->cotio_subitem)
                     ->firstOrFail();

        $tarea->cotio_responsable_codigo = $request->usuario_id;
        $tarea->save();

        return response()->json(['success' => true, 'message' => 'Responsable asignado correctamente']);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => 'Error al asignar responsable: ' . $e->getMessage()], 500);
    }
}

// admin
public function asignarIdentificacion(Request $request)
{
    $request->validate([
        'cotio_numcoti' => 'required',
        'cotio_item' => 'required',
        'cotio_subitem' => 'nullable',
        'cotio_identificacion' => 'nullable|string|max:255',
        'volumen_muestra' => 'nullable|numeric|min:0',
        'cotio_obs' => 'nullable|string|max:255',
    ]);

    $categoria = Cotio::where('cotio_numcoti', $request->cotio_numcoti)
                    ->where('cotio_item', $request->cotio_item)
                    ->where('cotio_subitem', 0)
                    ->firstOrFail();

    $categoria->cotio_identificacion = $request->cotio_identificacion;
    $categoria->volumen_muestra = $request->volumen_muestra;
    $categoria->cotio_obs = $request->cotio_obs;
    $categoria->save();

    return response()->json([
        'success' => true,
        'message' => 'Identificación y observaciones guardadas correctamente'
    ]);
}

// user
public function asignarIdentificacionMuestra(Request $request)
{
    try {
        // Validación
        $validated = $request->validate([
            'cotio_identificacion' => 'nullable|string|max:255',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            'nro_precinto' => 'nullable|string|max:100',
            'nro_cadena' => 'nullable|string|max:100',
            'cotio_numcoti' => 'required',
            'cotio_item' => 'required',
            'instance_number' => 'required',
            'image_base64' => 'nullable|string',
            'remove_image' => 'nullable|boolean'
        ]);

        Log::info('Datos recibidos:', $request->all());

        $instancia = CotioInstancia::where([
            'cotio_numcoti' => $request->cotio_numcoti,
            'cotio_item' => $request->cotio_item,
            'cotio_subitem' => 0,
            'instance_number' => $request->instance_number
        ])->firstOrFail();

        $data = [
            'cotio_identificacion' => $request->cotio_identificacion,
            'nro_precinto' => $request->nro_precinto,
            'nro_cadena' => $request->nro_cadena,
        ];

        $esBorrador = $request->get('accion') === 'borrador';

        if (!$esBorrador) {
            $data['cotio_estado'] = 'en revision muestreo';
        }

        // Guardar automáticamente la fecha y hora cuando se actualiza la identificación (solo si no es borrador)
        if (!$esBorrador && $request->filled('cotio_identificacion')) {
            $data['fecha_identificacion'] = now();
            // Actualizar fecha fin solo si se establece fecha_identificacion
            $data['fecha_fin_muestreo'] = now();
        }

        try {
            // Procesamiento de coordenadas
            if ($request->filled('latitud') && $request->filled('longitud')) {
                $lat = (float)$request->latitud;
                $lng = (float)$request->longitud;
                
                Log::info('Coordenadas procesadas:', ['lat' => $lat, 'lng' => $lng]);
                
                $data['cotio_georef'] = "{$lat}, {$lng}";
                $data['latitud'] = $lat;
                $data['longitud'] = $lng;
            } else {
                // Si no vienen coordenadas, limpiar los campos
                $data['cotio_georef'] = null;
                $data['latitud'] = null;
                $data['longitud'] = null;
            }

            // Manejo de imagen
            if ($request->has('remove_image') && $request->remove_image && $instancia->image) {
                Storage::delete('public/images/' . $instancia->image);
                $data['image'] = null;
            }

            if ($request->filled('image_base64')) {
                try {
                    // Decodificar la imagen base64
                    $image_parts = explode(";base64,", $request->image_base64);
                    $image_type_aux = explode("image/", $image_parts[0]);
                    $image_type = $image_type_aux[1];
                    $image_base64 = base64_decode($image_parts[1]);
                    
                    // Generar nombre único para la imagen
                    $imageName = 'muestra_'.$instancia->id.'_'.time().'.'.$image_type;
                    
                    // Asegurarse de que el directorio existe
                    if (!Storage::disk('public')->exists('images')) {
                        Storage::disk('public')->makeDirectory('images');
                    }
                    
                    // Guardar la imagen
                    $path = Storage::disk('public')->put('images/'.$imageName, $image_base64);
                    
                    if (!$path) {
                        throw new \Exception("No se pudo guardar la imagen");
                    }
                    
                    $data['image'] = $imageName;
                    
                    Log::info('Imagen guardada exitosamente:', [
                        'path' => $path,
                        'name' => $imageName
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error al procesar la imagen:', [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);
                    throw new \Exception("Error al procesar la imagen: " . $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            Log::error('Error crítico al guardar imagen:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la imagen: '.$e->getMessage()
            ], 500);
        }

        Log::info('Datos a actualizar:', $data);
        
        $instancia->update($data);
        
        Log::info('Registro actualizado:', $instancia->fresh()->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Datos actualizados correctamente',
            'redirect' => url()->previous()
        ]);

    } catch (\Exception $e) {
        Log::error('Error al actualizar muestra:', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'request' => $request->all()
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Ocurrió un error: ' . $e->getMessage()
        ], 500);
    }
}




// public function verCategoria($cotizacion, $item, $instance = null)
// {
//     $cotizacion = Coti::findOrFail($cotizacion);
//     $instance = $instance ?? 1;
    
//     // Obtener la muestra principal
//     $categoria = Cotio::where('cotio_numcoti', $cotizacion->coti_num)
//                 ->where('cotio_item', $item)
//                 ->where('cotio_subitem', 0)
//                 ->firstOrFail();
    
//     // Obtener la instancia de la muestra
//     $instanciaMuestra = CotioInstancia::where([
//                     'cotio_numcoti' => $cotizacion->coti_num,
//                     'cotio_item' => $item,
//                     'cotio_subitem' => 0,
//                     'instance_number' => $instance,
//                     'active_muestreo' => true
//                 ])->first();
    
//     // Obtener herramientas manualmente para la instancia de muestra
//     if ($instanciaMuestra) {
//         $herramientasMuestra = DB::table('cotio_inventario_muestreo')
//             ->where('cotio_numcoti', $instanciaMuestra->cotio_numcoti)
//             ->where('cotio_item', $instanciaMuestra->cotio_item)
//             ->where('cotio_subitem', $instanciaMuestra->cotio_subitem)
//             ->where('instance_number', $instanciaMuestra->instance_number)
//             ->join('inventario_muestreo', 'cotio_inventario_muestreo.inventario_muestreo_id', '=', 'inventario_muestreo.id')
//             ->select(
//                 'inventario_muestreo.*',
//                 'cotio_inventario_muestreo.cantidad',
//                 'cotio_inventario_muestreo.observaciones as pivot_observaciones'
//             )
//             ->get();
            
//         $instanciaMuestra->herramientas = $herramientasMuestra;
//     }
    
//     if (!$instanciaMuestra) {
//         return view('cotizaciones.tareasporcategoria', [
//             'cotizacion' => $cotizacion,
//             'categoria' => $categoria,
//             'tareas' => collect(),
//             'usuarios' => collect(),
//             'inventario' => collect(),
//             'instance' => $instance,
//             'instanciaActual' => null, 
//             'instanciasMuestra' => collect()
//         ]);
//     }

//     // Obtener tareas (análisis)
//     $tareas = Cotio::where('cotio_numcoti', $cotizacion->coti_num)
//                 ->where('cotio_item', $item)
//                 ->where('cotio_subitem', '!=', 0)
//                 ->orderBy('cotio_subitem')
//                 ->get();
    
//     $tareasConInstancias = $tareas->map(function($tarea) use ($instance) {
//         $instancia = CotioInstancia::where([
//             'cotio_numcoti' => $tarea->cotio_numcoti,
//             'cotio_item' => $tarea->cotio_item,
//             'cotio_subitem' => $tarea->cotio_subitem,
//             'instance_number' => $instance,
//             'active_muestreo' => true
//         ])->first();
        
//         if ($instancia) {
//             // Obtener herramientas manualmente para cada análisis
//             $herramientasAnalisis = DB::table('cotio_inventario_muestreo')
//                 ->where('cotio_numcoti', $instancia->cotio_numcoti)
//                 ->where('cotio_item', $instancia->cotio_item)
//                 ->where('cotio_subitem', $instancia->cotio_subitem)
//                 ->where('instance_number', $instancia->instance_number)
//                 ->join('inventario_muestreo', 'cotio_inventario_muestreo.inventario_muestreo_id', '=', 'inventario_muestreo.id')
//                 ->select(
//                     'inventario_muestreo.*',
//                     'cotio_inventario_muestreo.cantidad',
//                     'cotio_inventario_muestreo.observaciones as pivot_observaciones'
//                 )
//                 ->get();
                
//             $instancia->herramientas = $herramientasAnalisis;
//             $tarea->instancia = $instancia;
//             return $tarea;
//         }
//         return null;
//     })->filter();
    
//     $usuarios = User::where('usu_nivel', '<=', 500)
//                 ->orderBy('usu_descripcion')
//                 ->get();
    
//     $inventario = InventarioMuestreo::all();
//     $vehiculos = Vehiculo::all();
    
//     $instanciasMuestra = CotioInstancia::where('cotio_numcoti', $cotizacion->coti_num)
//                             ->where('cotio_item', $item)
//                             ->where('cotio_subitem', 0)
//                             ->where('active_muestreo', true)
//                             ->get()
//                             ->keyBy('instance_number');
    
//     return view('cotizaciones.tareasporcategoria', [
//         'cotizacion' => $cotizacion,
//         'categoria' => $categoria,
//         'tareas' => $tareasConInstancias,
//         'usuarios' => $usuarios,
//         'inventario' => $inventario,
//         'instance' => $instance,
//         'vehiculos' => $vehiculos,
//         'instanciaActual' => $instanciaMuestra, 
//         'instanciasMuestra' => $instanciasMuestra
//     ]);
// }






public function desasignarHerramienta($cotizacion, $item, $subitem, $herramienta_id)
{
    $tarea = Cotio::where('cotio_numcoti', $cotizacion)
                  ->where('cotio_item', $item)
                  ->where('cotio_subitem', $subitem)
                  ->firstOrFail();

    DB::table('cotio_inventario_lab')
        ->where('cotio_numcoti', $cotizacion)
        ->where('cotio_item', $item)
        ->where('cotio_subitem', $subitem)
        ->where('inventario_lab_id', $herramienta_id) 
        ->delete();

    InventarioLab::where('id', $herramienta_id)
                 ->update(['estado' => 'libre']);

    return redirect()->back()->with('success', 'Herramienta desasignada correctamente.');
}

public function desasignarVehiculo($cotizacion, $item, $subitem, $vehiculo_id)
{
    $tarea = Cotio::where('cotio_numcoti', $cotizacion)
                  ->where('cotio_item', $item)
                  ->where('cotio_subitem', $subitem)
                  ->firstOrFail();

    DB::table('cotio')
        ->where('cotio_numcoti', $cotizacion)
        ->where('cotio_item', $item)
        ->where('cotio_subitem', $subitem)
        ->where('vehiculo_asignado', $vehiculo_id)
        ->update(['vehiculo_asignado' => null]);

    Vehiculo::where('id', $vehiculo_id)
            ->update(['estado' => 'libre']);

    return redirect()->back()->with('success', 'Vehículo desasignado correctamente.');
}




public function updateEstado(Request $request, $cotio_numcoti, $cotio_item, $cotio_subitem)
{
    $request->validate([
        'nuevo_estado' => 'required|in:pendiente,en proceso,finalizado',
    ]);

    try {
        $userCodigo = trim(Auth::user()->usu_codigo);

        $tarea = Cotio::where('cotio_numcoti', $cotio_numcoti)
                      ->where('cotio_item', $cotio_item)
                      ->where('cotio_subitem', $cotio_subitem)
                      ->firstOrFail();

        if (trim($tarea->cotio_responsable_codigo) !== $userCodigo) {
            abort(403, 'No autorizado');
        }

        if ($request->nuevo_estado === 'finalizado' && $tarea->vehiculo_asignado) {
            $vehiculo = Vehiculo::find($tarea->vehiculo_asignado);
            if ($vehiculo) {
                $vehiculo->estado = Vehiculo::ESTADO_LIBRE;
                $vehiculo->save();
            }
            $tarea->vehiculo_asignado = null;
        }

        $tarea->cotio_estado = $request->nuevo_estado;
        $tarea->save();

        Cotio::actualizarEstadoCategoria($cotio_numcoti, $cotio_item);

        return redirect()->back()->with('success', 'Estado actualizado correctamente');
    } catch (\Exception $e) {
        return redirect()->back()->with('error', 'Error al actualizar el estado: ' . $e->getMessage());
    }
}



public function actualizarEstado(Request $request)
{
    $validated = $request->validate([
        'cotio_numcoti' => 'required|numeric',
        'cotio_item' => 'required|numeric',
        'cotio_subitem' => 'required|numeric',
        'instance_number' => 'required|numeric',
        'estado' => 'required|in:coordinado analisis,en revision analisis,analizado,suspension,coordinado muestreo,en revision muestreo,muestreado',
    ]);

    try {
        DB::beginTransaction();

        $item = CotioInstancia::where([
            'cotio_numcoti' => $validated['cotio_numcoti'],
            'cotio_item' => $validated['cotio_item'],
            'cotio_subitem' => $validated['cotio_subitem'],
            'instance_number' => $validated['instance_number']
        ])->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Elemento no encontrado'
            ], 404);
        }

        $vehiculoAsignado = $item->vehiculo_asignado;

        if(Auth::user()->hasRole('coordinador_muestreo') || Auth::user()->usu_nivel >= '900') {
            $item->cotio_estado = $validated['estado'];
        } 

        if ($validated['estado'] === 'finalizado') {
            if (empty($item->fecha_fin)) {
                $item->fecha_fin = now();
            }
            
            if ($vehiculoAsignado) {
                $item->vehiculo_asignado = null;
                
                Vehiculo::where('id', $vehiculoAsignado)
                    ->update(['estado' => 'libre']);
            }

            $herramientasAsignadas = DB::table('cotio_inventario_muestreo')
                ->where('cotio_numcoti', $validated['cotio_numcoti'])
                ->where('cotio_item', $validated['cotio_item'])
                ->where('cotio_subitem', $validated['cotio_subitem'])
                ->where('instance_number', $validated['instance_number'])
                ->pluck('inventario_muestreo_id');

            if ($herramientasAsignadas->isNotEmpty()) {
                DB::table('cotio_inventario_muestreo')
                    ->where('cotio_numcoti', $validated['cotio_numcoti'])
                    ->where('cotio_item', $validated['cotio_item'])
                    ->where('cotio_subitem', $validated['cotio_subitem'])
                    ->where('instance_number', $validated['instance_number'])
                    ->delete();

                InventarioMuestreo::whereIn('id', $herramientasAsignadas)
                    ->update(['estado' => 'libre']);
            }
        }

        $item->save();

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado correctamente'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error en actualizarEstado: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error al actualizar el estado: ' . $e->getMessage()
        ], 500);
    }
}

protected function actualizarEstadoCategoriaPadre($numcoti, $item, $instanceNumber)
{
    // Verificar el estado de todas las tareas de esta categoría
    $tareas = CotioInstancia::where([
        'cotio_numcoti' => $numcoti,
        'cotio_item' => $item,
        'instance_number' => $instanceNumber
    ])->where('cotio_subitem', '>', 0)->get();

    if ($tareas->isEmpty()) return;

    $todosFinalizados = $tareas->every(fn($t) => $t->cotio_estado === 'finalizado');
    $algunoEnProceso = $tareas->contains(fn($t) => $t->cotio_estado === 'en proceso');

    $categoria = CotioInstancia::where([
        'cotio_numcoti' => $numcoti,
        'cotio_item' => $item,
        'cotio_subitem' => 0,
        'instance_number' => $instanceNumber
    ])->first();

    if ($todosFinalizados) {
        $categoria->cotio_estado = 'finalizado';
    } elseif ($algunoEnProceso) {
        $categoria->cotio_estado = 'en proceso';
    } else {
        $categoria->cotio_estado = 'pendiente';
    }

    $categoria->save();
}




public function updateResultado(Request $request, $cotio_numcoti, $cotio_item, $cotio_subitem, $instance)
{
    $request->validate([
        'resultado' => 'nullable|string|max:255',
        'resultado_2' => 'nullable|string|max:255',
        'resultado_3' => 'nullable|string|max:255',
        'resultado_final' => 'nullable|string|max:255',
        'valores' => 'nullable|array',
        'observacion_resultado' => 'nullable|string|max:255',
        'observacion_resultado_2' => 'nullable|string|max:255',
        'observacion_resultado_3' => 'nullable|string|max:255',
        'observacion_resultado_final' => 'nullable|string|max:255',
        'observaciones_ot' => 'nullable|string|max:1000',
        'image_resultado_final' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
    ]);

    DB::beginTransaction();
    try {
        $instancia = CotioInstancia::where([
            'cotio_numcoti' => $cotio_numcoti,
            'cotio_item' => $cotio_item,
            'cotio_subitem' => $cotio_subitem,
            'instance_number' => $instance,
        ])->firstOrFail();

        // Handle image upload
        if ($request->hasFile('image_resultado_final')) {
            $file = $request->file('image_resultado_final');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('uploads/analisis', $filename, 'public');
            $instancia->image_resultado_final = $path; // Store the file path in the `image` column
        }

        // Determine if there are changes that require state update
        $hasResultado = $request->filled('resultado') || $request->filled('resultado_2') || $request->filled('resultado_3') || $request->filled('resultado_final');

        if ($hasResultado) {
            $user = Auth::user();

            // Considerar al usuario como "laboratorio" si:
            // - su rol principal es laboratorio, o
            // - la función helper userHasRole indica que tiene rol laboratorio
            $tieneRolLaboratorio = $user && (
                ($user->rol ?? null) === 'laboratorio'
                || (function_exists('userHasRole') && userHasRole('laboratorio'))
            );

            // "Solo muestreador" = tiene rol muestreador y NO tiene laboratorio
            $esSoloMuestreador = $user
                && function_exists('userHasRole')
                && userHasRole('muestreador')
                && !$tieneRolLaboratorio;

            if ($esSoloMuestreador) {
                // Usuarios que solo son muestreadores: fluyen por el estado de muestreo
                $instancia->cotio_estado = 'en revision muestreo';
            } else {
                // Cualquier usuario que tenga rol de laboratorio (principal o adicional)
                // debe pasar por el flujo de análisis
                $instancia->cotio_estado_analisis = 'en revision analisis';
                $muestra = CotioInstancia::where([
                    'cotio_numcoti' => $cotio_numcoti,
                    'cotio_item' => $cotio_item,
                    'cotio_subitem' => 0,
                    'instance_number' => $instance,
                ])->firstOrFail();
                $muestra->cotio_estado_analisis = 'en revision analisis';
                $muestra->save();
            }
        }

        // Update results
        if ($request->filled('resultado') && $instancia->resultado !== $request->resultado) {
            $instancia->resultado = $request->resultado;
            if (is_null($instancia->fecha_carga_resultado_1)) {
                $instancia->responsable_resultado_1 = Auth::user()->usu_codigo;
                $instancia->fecha_carga_resultado_1 = now();
            }
        }
        if ($request->filled('resultado_2') && $instancia->resultado_2 !== $request->resultado_2) {
            $instancia->resultado_2 = $request->resultado_2;
            if (is_null($instancia->fecha_carga_resultado_2)) {
                $instancia->responsable_resultado_2 = Auth::user()->usu_codigo;
                $instancia->fecha_carga_resultado_2 = now();
            }
        }
        if ($request->filled('resultado_3') && $instancia->resultado_3 !== $request->resultado_3) {
            $instancia->resultado_3 = $request->resultado_3;
            if (is_null($instancia->fecha_carga_resultado_3)) {
                $instancia->responsable_resultado_3 = Auth::user()->usu_codigo;
                $instancia->fecha_carga_resultado_3 = now();
            }
        }
        if ($request->filled('resultado_final') && $instancia->resultado_final !== $request->resultado_final) {
            $instancia->resultado_final = $request->resultado_final;
            $instancia->responsable_resultado_final = Auth::user()->usu_codigo;
            $instancia->fecha_carga_ot = now();
        }

        // Update observations
        if ($request->filled('observacion_resultado')) {
            $instancia->observacion_resultado = $request->observacion_resultado;
        }
        if ($request->filled('observacion_resultado_2')) {
            $instancia->observacion_resultado_2 = $request->observacion_resultado_2;
        }
        if ($request->filled('observacion_resultado_3')) {
            $instancia->observacion_resultado_3 = $request->observacion_resultado_3;
        }
        if ($request->filled('observacion_resultado_final')) {
            $instancia->observacion_resultado_final = $request->observacion_resultado_final;
        }
        if ($request->has('observaciones_ot')) {
            $instancia->observaciones_ot = $request->observaciones_ot;
        }

        if ($instancia->request_review) {
            $instancia->request_review = false;
        }

        $instancia->save();
        DB::commit();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Resultado del análisis actualizado correctamente',
                'data' => [
                    'resultado' => $instancia->resultado,
                    'resultado_2' => $instancia->resultado_2,
                    'resultado_3' => $instancia->resultado_3,
                    'resultado_final' => $instancia->resultado_final,
                    'observacion_resultado' => $instancia->observacion_resultado,
                    'observacion_resultado_2' => $instancia->observacion_resultado_2,
                    'observacion_resultado_3' => $instancia->observacion_resultado_3,
                    'observacion_resultado_final' => $instancia->observacion_resultado_final,
                    'image' => $instancia->image, // Include image path in response
                ],
            ]);
        }

        return redirect()->back()->with('success', 'Resultado del análisis actualizado correctamente');
    } catch (\Exception $e) {
        DB::rollBack();
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el análisis: ' . $e->getMessage()
            ], 500);
        }
        return redirect()->back()->with('error', 'Error al actualizar el análisis: ' . $e->getMessage());
    }
}



public function onlyUpdateResultado(Request $request, $cotio_numcoti, $cotio_item, $cotio_subitem, $instance)
{
    $request->validate([
        'resultado' => 'nullable|string|max:255',
        'resultado_2' => 'nullable|string|max:255',
        'resultado_3' => 'nullable|string|max:255',
        'resultado_final' => 'nullable|string|max:255',
        'valores' => 'nullable|array',
        'observacion_resultado' => 'nullable|string|max:255',
        'observacion_resultado_2' => 'nullable|string|max:255',
        'observacion_resultado_3' => 'nullable|string|max:255',
        'observacion_resultado_final' => 'nullable|string|max:255',
        'observaciones_ot' => 'nullable|string|max:1000',
        'image_resultado_final' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
    ]);

    DB::beginTransaction();
    try {
        $instancia = CotioInstancia::where([
            'cotio_numcoti' => $cotio_numcoti,
            'cotio_item' => $cotio_item,
            'cotio_subitem' => $cotio_subitem,
            'instance_number' => $instance,
        ])->firstOrFail();

        // Handle image upload
        if ($request->hasFile('image_resultado_final')) {
            $file = $request->file('image_resultado_final');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('uploads/analisis', $filename, 'public');
            $instancia->image_resultado_final = $path; // Store the file path in the `image` column
        }

        // Update other fields
        if ($request->filled('resultado') && $instancia->resultado !== $request->resultado) {
            $instancia->resultado = $request->resultado;
            if (is_null($instancia->fecha_carga_resultado_1)) {
                $instancia->responsable_resultado_1 = Auth::user()->usu_codigo;
                $instancia->fecha_carga_resultado_1 = now();
            }
        }
        if ($request->filled('resultado_2') && $instancia->resultado_2 !== $request->resultado_2) {
            $instancia->resultado_2 = $request->resultado_2;
            if (is_null($instancia->fecha_carga_resultado_2)) {
                $instancia->responsable_resultado_2 = Auth::user()->usu_codigo;
                $instancia->fecha_carga_resultado_2 = now();
            }
        }
        if ($request->filled('resultado_3') && $instancia->resultado_3 !== $request->resultado_3) {
            $instancia->resultado_3 = $request->resultado_3;
            if (is_null($instancia->fecha_carga_resultado_3)) {
                $instancia->responsable_resultado_3 = Auth::user()->usu_codigo;
                $instancia->fecha_carga_resultado_3 = now();
            }
        }
        if ($request->filled('resultado_final') && $instancia->resultado_final !== $request->resultado_final) {
            $instancia->resultado_final = $request->resultado_final;
            $instancia->responsable_resultado_final = Auth::user()->usu_codigo;
            $instancia->fecha_carga_ot = now();
        }

        if ($request->filled('observacion_resultado')) {
            $instancia->observacion_resultado = $request->observacion_resultado;
        }
        if ($request->filled('observacion_resultado_2')) {
            $instancia->observacion_resultado_2 = $request->observacion_resultado_2;
        }
        if ($request->filled('observacion_resultado_3')) {
            $instancia->observacion_resultado_3 = $request->observacion_resultado_3;
        }
        if ($request->filled('observacion_resultado_final')) {
            $instancia->observacion_resultado_final = $request->observacion_resultado_final;
        }
        if ($request->has('observaciones_ot')) {
            $instancia->observaciones_ot = $request->observaciones_ot;
        }

        $instancia->save();
        DB::commit();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Resultado guardado correctamente',
                'data' => [
                    'image' => $instancia->image, // Include image path in response
                ],
            ]);
        }

        return redirect()->back()->with('success', 'Resultado guardado correctamente');
    } catch (\Exception $e) {
        DB::rollBack();
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el análisis: ' . $e->getMessage()
            ], 500);
        }
        return redirect()->back()->with('error', 'Error al guardar el análisis: ' . $e->getMessage());
    }
}

public function updateMediciones(Request $request, $instanciaId)
{
    try {
        $usuarioActual = trim(Auth::user()->usu_codigo);
        $instancia = CotioInstancia::where('id', $instanciaId)
            ->whereHas('responsablesMuestreo', function($query) use ($usuarioActual) {
                $query->where('usu.usu_codigo', $usuarioActual);
            })
            ->firstOrFail();

        $validated = $request->validate([
            'valores' => 'required|array',
            'valores.*.valor' => 'nullable|string|max:255', // Allow empty or null values
            'valores.*.variable_id' => 'required|integer|exists:cotio_valores_variables,id,cotio_instancia_id,'.$instanciaId,
        ]);

        DB::beginTransaction();

        foreach ($request->valores as $variableId => $valorData) {
            $variable = CotioValorVariable::where('id', $valorData['variable_id'])
                ->where('cotio_instancia_id', $instanciaId)
                ->firstOrFail();

            $variable->update([
                'valor' => $valorData['valor'] ?? null // Store null if empty
            ]);
        }

        $instancia->update([
            'observaciones_medicion_muestreador' => trim($request->observaciones_medicion_muestreador),
        ]);

        DB::commit();

        return redirect()->back()->with('success', 'Variables actualizadas correctamente.');

    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::debug('Validation errors', ['errors' => $e->errors()]);
        return redirect()->back()->withErrors($e->validator)->withInput();

    } catch (\Exception $e) {
        Log::error('Error updating mediciones', [
            'instancia_id' => $instanciaId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return redirect()->back()->with('error', 'Error al actualizar las variables: ' . $e->getMessage());
    }
}


public function showTarea($cotio_numcoti, $cotio_item, $cotio_subitem) 
{
    $tarea = Cotio::with('vehiculo', 'cotizacion')
              ->where([
                  'cotio_numcoti' => $cotio_numcoti,
                  'cotio_item' => $cotio_item,
                  'cotio_subitem' => $cotio_subitem
              ])
              ->firstOrFail();

    $tarea->herramientas = InventarioLab::whereHas('cotioInventarioLab', function($q) use ($tarea) {
        $q->where([
            'cotio_numcoti' => $tarea->cotio_numcoti,
            'cotio_item' => $tarea->cotio_item,
            'cotio_subitem' => $tarea->cotio_subitem
        ]);
    })->get();

    return view('tareas.show', compact('tarea'));
}





public function showTareasAll($cotio_numcoti, $cotio_item, $cotio_subitem = 0, $instance = null)
{
    $instance = $instance ?? 1;
    $usuario = Auth::user();
    $usuarioActual = trim($usuario->usu_codigo);
    $esPrivilegiado = ((int) $usuario->usu_nivel >= 900) || $usuario->hasRole('coordinador_muestreo');

    try {
        // Obtener la instancia de muestra principal con sus variables
        $muestraQuery = CotioInstancia::with([
            'muestra.vehiculo',
            'muestra.cotizacion',
            'valoresVariables' => function($query) {
                $query->select('id', 'cotio_instancia_id', 'variable', 'valor');
            }
        ])
        ->where('cotio_numcoti', $cotio_numcoti)
        ->where('cotio_item', $cotio_item)
        ->where('cotio_subitem', 0)
        ->where('instance_number', $instance);

        if (!$esPrivilegiado) {
            $muestraQuery->whereHas('responsablesMuestreo', function ($query) use ($usuarioActual) {
                $query->where('usu.usu_codigo', $usuarioActual);
            });
        }

        $instanciaMuestra = $muestraQuery->firstOrFail();

        // Cargar herramientas para la instancia de muestra
        $herramientasMuestra = $instanciaMuestra->getHerramientasMuestreo();
        
        // Obtener todas las herramientas disponibles para edición
        $todasHerramientas = \App\Models\InventarioMuestreo::where('activo', 1)->get();

        // Obtener TODOS los análisis de la muestra (subitems > 0) 
        $analisis = CotioInstancia::with([
            'tarea.vehiculo',
            'tarea.cotizacion',
            'responsablesMuestreo' // Cargar responsables para mostrar quién está asignado
        ])
        ->where('cotio_numcoti', $cotio_numcoti)
        ->where('cotio_item', $cotio_item)
        ->where('cotio_subitem', '>', 0)
        ->where('instance_number', $instance)
        ->orderBy('cotio_subitem')
        ->get();

        // Cargar herramientas para cada análisis y marcar cuáles puede editar el usuario
        $analisis->each(function ($item) use ($usuarioActual, $esPrivilegiado) {
            $item->herramientas = $item->getHerramientasMuestreo();
            
            // Determinar si el usuario puede editar este análisis
            $item->puede_editar = $esPrivilegiado || 
                $item->responsablesMuestreo->pluck('usu_codigo')->contains($usuarioActual);
        });

        Log::debug('Tasks retrieved for muestreador', [
            'user' => $usuarioActual,
            'cotio_numcoti' => $cotio_numcoti,
            'cotio_item' => $cotio_item,
            'instance_number' => $instance,
            'sample_id' => $instanciaMuestra->id,
            'analysis_ids' => $analisis->pluck('id')->toArray(),
            'variables_count' => $instanciaMuestra->valoresVariables->count(),
            'es_privilegiado' => $esPrivilegiado,
        ]);

        return view('tareas.show-by-categoria', [
            'instancia' => $instanciaMuestra,
            'analisis' => $analisis,
            'instanceNumber' => $instance,
            'variables' => $instanciaMuestra->valoresVariables, // Pasamos solo las variables de la muestra principal
            'herramientasMuestra' => $herramientasMuestra, // Herramientas de la muestra principal
            'todasHerramientas' => $todasHerramientas // Todas las herramientas disponibles
        ]);

    } catch (\Exception $e) {
        Log::error('Error retrieving tasks for muestreador', [
            'user' => $usuarioActual,
            'cotio_numcoti' => $cotio_numcoti,
            'cotio_item' => $cotio_item,
            'instance' => $instance,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        abort(404, 'Muestra o tareas no encontradas o no asignadas al usuario.');
    }
}

public function updateHerramientas(Request $request, $cotio_numcoti, $cotio_item, $cotio_subitem, $instance)
{
    try {
        $request->validate([
            'herramientas' => 'nullable|array',
            'herramientas.*' => 'integer|exists:inventario_muestreo,id'
        ]);

        $usuario = Auth::user();
        $usuarioActual = trim($usuario->usu_codigo);
        $esPrivilegiado = ((int) $usuario->usu_nivel >= 900) || $usuario->hasRole('coordinador_muestreo');

        // Verificar que el usuario tiene acceso a esta instancia
        $instanciaQuery = CotioInstancia::where([
            'cotio_numcoti' => $cotio_numcoti,
            'cotio_item' => $cotio_item,
            'cotio_subitem' => $cotio_subitem,
            'instance_number' => $instance
        ]);

        if (!$esPrivilegiado) {
            $instanciaQuery->whereHas('responsablesMuestreo', function ($query) use ($usuarioActual) {
                $query->where('usu.usu_codigo', $usuarioActual);
            });
        }

        $instancia = $instanciaQuery->firstOrFail();

        // Eliminar herramientas existentes
        \App\Models\CotioInventarioMuestreo::where([
            'cotio_numcoti' => $cotio_numcoti,
            'cotio_item' => $cotio_item,
            'cotio_subitem' => $cotio_subitem,
            'instance_number' => $instance
        ])->delete();

        // Agregar nuevas herramientas
        if (!empty($request->herramientas)) {
            foreach ($request->herramientas as $herramientaId) {
                \App\Models\CotioInventarioMuestreo::create([
                    'cotio_numcoti' => $cotio_numcoti,
                    'cotio_item' => $cotio_item,
                    'cotio_subitem' => $cotio_subitem,
                    'instance_number' => $instance,
                    'inventario_muestreo_id' => $herramientaId,
                    'cantidad' => 1
                ]);
            }
        }

        Log::info('Herramientas actualizadas por muestreador', [
            'usuario' => $usuarioActual,
            'cotio_numcoti' => $cotio_numcoti,
            'cotio_item' => $cotio_item,
            'cotio_subitem' => $cotio_subitem,
            'instance_number' => $instance,
            'herramientas' => $request->herramientas ?? []
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Herramientas actualizadas correctamente'
        ]);

    } catch (\Exception $e) {
        Log::error('Error al actualizar herramientas', [
            'usuario' => Auth::user()->usu_codigo,
            'cotio_numcoti' => $cotio_numcoti,
            'cotio_item' => $cotio_item,
            'cotio_subitem' => $cotio_subitem,
            'instance_number' => $instance,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error al actualizar herramientas: ' . $e->getMessage()
        ], 500);
    }
}

public function qrUniversalRedirect($cotio_numcoti, $cotio_item, $cotio_subitem = 0, $instance = null)
{
    $user = Auth::user();
    
    if (!$user) {
        return redirect()->route('login');
    }
    
    // Determinar a qué vista redirigir según el rol específico del usuario
    $userRole = $user->rol;
    
    // Roles que deben ir a ordenes.all.show (laboratorio/análisis)
    $laboratorioRoles = ['coordinador_lab', 'laboratorio'];
    
    // Roles que deben ir a tareas.all.show (muestreo/campo)
    $muestreoRoles = ['coordinador_muestreo', 'muestreador'];
    
    // Redireccionar según el rol específico
    if (in_array($userRole, $laboratorioRoles)) {
        return redirect()->route('ordenes.all.show', [
            'cotio_numcoti' => $cotio_numcoti,
            'cotio_item' => $cotio_item,
            'cotio_subitem' => $cotio_subitem,
            'instance' => $instance
        ]);
    } elseif (in_array($userRole, $muestreoRoles)) {
        return redirect()->route('tareas.all.show', [
            'cotio_numcoti' => $cotio_numcoti,
            'cotio_item' => $cotio_item,
            'cotio_subitem' => $cotio_subitem,
            'instance' => $instance
        ]);
    }
    
    // Para administradores (usu_nivel >= 900) sin rol específico, mostrar selector
    if ($user->usu_nivel >= 900) {
        return redirect()->route('qr.selector', [
            'cotio_numcoti' => $cotio_numcoti,
            'cotio_item' => $cotio_item,
            'cotio_subitem' => $cotio_subitem,
            'instance' => $instance
        ]);
    }
    
    // Si no tiene rol específico definido, redirigir a login con error
    return redirect()->route('login')->with('error', 'No tienes permisos para acceder a esta funcionalidad.');
}

public function qrViewSelector($cotio_numcoti, $cotio_item, $cotio_subitem = 0, $instance = null)
{
    $user = Auth::user();
    
    if (!$user) {
        return redirect()->route('login');
    }
    
    // Solo administradores pueden acceder a este selector
    if ($user->usu_nivel < 900) {
        return redirect()->route('qr.universal', [
            'cotio_numcoti' => $cotio_numcoti,
            'cotio_item' => $cotio_item,
            'cotio_subitem' => $cotio_subitem,
            'instance' => $instance
        ]);
    }
    
    // Obtener información de la muestra para mostrar en el selector
    $instancia = CotioInstancia::with(['muestra.cotizacion'])
        ->where('cotio_numcoti', $cotio_numcoti)
        ->where('cotio_item', $cotio_item)
        ->where('cotio_subitem', 0)
        ->where('instance_number', $instance)
        ->first();
    
    if (!$instancia) {
        abort(404, 'Muestra no encontrada');
    }
    
    return view('qr.selector', [
        'instancia' => $instancia,
        'cotio_numcoti' => $cotio_numcoti,
        'cotio_item' => $cotio_item,
        'cotio_subitem' => $cotio_subitem,
        'instance' => $instance
    ]);
}






public function generateAllQRs($cotizacion)
{
    try {
        $cotizacion = Coti::with('tareas')
            ->where('coti_num', $cotizacion)
            ->firstOrFail();
        
        $categorias = $cotizacion->tareas
            ->where('cotio_subitem', 0)
            ->reject(function ($tarea) {
                return $tarea->cotio_descripcion === 'TRABAJO TECNICO EN CAMPO';
            })
            ->values();
        
        return response()->json([
            'success' => true,
            'data' => $categorias,
            'cotizacion' => [
                'numero' => $cotizacion->coti_num,
                'cliente' => $cotizacion->coti_empresa,
                'establecimiento' => $cotizacion->coti_establecimiento
            ]
        ]);
        
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Cotización no encontrada'
        ], 404);
        
    } catch (\Exception $e) {
        Log::error('Error en generateAllQRs: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error interno del servidor'
        ], 500);
    }
}





public function enableOt(Request $request) 
{
    $request->validate([
        'cotio_numcoti' => 'required|string',
        'cotio_item' => 'required|string',
        'cotio_subitem' => 'required|string',
        'instance' => 'required|string',
        'es_priori' => 'nullable|boolean',
    ]);

    try {
        DB::beginTransaction();
        
        // Buscar la instancia específica usando el modelo Eloquent
        $instancia = CotioInstancia::where('cotio_numcoti', $request->cotio_numcoti)
            ->where('cotio_item', $request->cotio_item)
            ->where('cotio_subitem', $request->cotio_subitem)
            ->where('instance_number', $request->instance)
            ->first();

        if (!$instancia) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró la instancia especificada'
            ], 404);
        }

        // Generar número OT solo si es una muestra (cotio_subitem = 0)
        $instancia->enable_ot = true;
        $instancia->complete_muestreo = true;
        $instancia->cotio_estado_analisis = null;
        $instancia->es_priori = $request->es_priori ?? false;

        // Solo asignar número OT si es una muestra y no tiene uno ya asignado
        if (($request->cotio_subitem == '0' || $request->cotio_subitem == 0) && !$instancia->otn) {
            $instancia->otn = CotioInstancia::generarNumeroOT();
        }

        // Guardar la instancia
        $result = $instancia->save();
        
        DB::commit();
        
        if ($result) {
            return redirect()->back()->with('success', 'Instancia actualizada correctamente');
        } else {
            return redirect()->back()->with('error', 'No se pudo actualizar la instancia');
        }
        
    } catch (\Exception $e) {
        DB::rollBack();
        
        return response()->json([
            'success' => false,
            'message' => 'Error al actualizar estados: ' . $e->getMessage(),
            'error_details' => $e->getTraceAsString()
        ], 500);
    }
}


public function disableOt(Request $request)
{
    $request->validate([
        'cotio_numcoti' => 'required|string',
        'cotio_item' => 'required|string',
        'cotio_subitem' => 'required|string',
        'instance' => 'required|string',
    ]);

    try {
        DB::beginTransaction();
        
        $instancia = DB::table('cotio_instancias')
            ->where('cotio_numcoti', $request->cotio_numcoti)
            ->where('cotio_item', $request->cotio_item)
            ->where('cotio_subitem', $request->cotio_subitem)
            ->where('instance_number', $request->instance)
            ->first();

        if (!$instancia) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró la instancia especificada'
            ], 404);
        }

        $result = DB::table('cotio_instancias')
            ->where('cotio_numcoti', $request->cotio_numcoti)
            ->where('cotio_item', $request->cotio_item)
            ->where('cotio_subitem', $request->cotio_subitem)
            ->where('instance_number', $request->instance)
            ->update([
                'enable_ot' => false,
                'active_ot' => false,
                'complete_muestreo' => false,
                'cotio_estado_analisis' => null,
            ]);
        
        DB::commit();
        
        if ($result === 1) {
            return redirect()->back()->with('success', 'OT desactivada correctamente');

        } else {
            return redirect()->back()->with('error', 'No se pudo desactivar la OT');
        }
        
    } catch (\Exception $e) {
        DB::rollBack();
        
        return response()->json([
            'success' => false,
            'message' => 'Error al actualizar estados: ' . $e->getMessage(),
            'error_details' => $e->getTraceAsString()
        ], 500);
    }
}



}
