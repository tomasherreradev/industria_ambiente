<?php

namespace App\Http\Controllers;

use App\Models\CotioItems;
use App\Models\Metodo;
use App\Models\Matriz;
use App\Models\CotioItemPrecioHistorial;
use App\Imports\ItemsImport;
use App\Exports\ItemsTemplateExport;
use App\Exports\ItemsExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ItemController extends Controller
{  
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('q');
        $tipo = $request->input('tipo');
        // En querystring, los "+" llegan como espacios. Normalizamos y trim para evitar filtros incompletos.
        $matrizCodigo = $request->input('matriz');
        $matrizCodigo = is_string($matrizCodigo) ? trim(preg_replace('/\s+/', ' ', $matrizCodigo)) : $matrizCodigo;
        $query = CotioItems::query();

        if ($search) {
            $query->buscar($search);
        }

        if ($tipo !== null && $tipo !== '') {
            if ($tipo === 'agrupador') {
                $query->where('es_muestra', true);
            } elseif ($tipo === 'componente') {
                $query->where('es_muestra', false);
            }
        }

        // Filtrar por matriz usando la tabla pivote
        if ($matrizCodigo !== null && $matrizCodigo !== '') {
            $matrizCodigoLimpio = trim((string) $matrizCodigo);
            $query->whereExists(function($q) use ($matrizCodigoLimpio) {
                $q->select(DB::raw(1))
                  ->from('cotio_items_matriz')
                  ->whereColumn('cotio_items_matriz.cotio_item_id', 'cotio_items.id')
                  // Comparar con TRIM para manejar espacios en blanco (legacy)
                  ->whereRaw('TRIM(cotio_items_matriz.matriz_codigo) = ?', [trim($matrizCodigoLimpio)]);
            });
        }

        $items = $query->with(['agrupadores', 'componentesAsociados', 'matrices', 'metodoAnalitico', 'metodoMuestreo'])
            ->orderBy('cotio_descripcion', 'asc')
            ->paginate(15)
            ->withQueryString();

        // Cargar matrices para el filtro
        $matrices = Matriz::orderBy('matriz_descripcion')->get();

        return view('items.index', compact('items', 'search', 'tipo', 'matrizCodigo', 'matrices'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $matrices = Matriz::orderBy('matriz_descripcion')->get();
        $metodos = Metodo::orderBy('metodo_codigo')->get();
        $componentes = CotioItems::componentes()
            ->with(['matrices', 'metodoAnalitico', 'metodoMuestreo'])
            ->orderBy('cotio_descripcion')
            ->get();

        return view('items.create', compact('matrices', 'componentes', 'metodos'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate(array_merge([
            'cotio_descripcion' => ['required', 'string', 'max:255'],
            'es_muestra' => ['nullable', 'boolean'],
            'limites_establecidos' => ['nullable', 'string', 'max:255'],
            'unidad_medida' => ['nullable', 'string', 'max:255'],
            'precio' => ['nullable', 'numeric', 'min:0'],
            'componentes' => ['array'],
            'componentes.*' => ['integer', 'exists:cotio_items,id'],
            'componentes_orden' => ['array'],
            'componentes_orden.*' => ['integer', 'exists:cotio_items,id'],
            'matrices' => ['array'],
            'matrices.*' => ['string', 'exists:matriz,matriz_codigo'],
            'nota_imprimible' => ['nullable', 'string'],
            'nota_interna' => ['nullable', 'string'],
            'notas_predeterminadas' => ['array'],
            'notas_predeterminadas.*.titulo' => ['nullable', 'string', 'max:255'],
            'notas_predeterminadas.*.contenido' => ['required', 'string'],
        ], $this->reglasMetodosItem()));

        [$metodoAnalisis, $metodoMuestreo] = $this->resolverMetodosItemValidados($validated);

        $componentesSeleccionados = collect($validated['componentes'] ?? [])->filter();
        $componentesSeleccionados = $componentesSeleccionados->map(fn ($id) => (int) $id)->filter()->unique();
        $componentesOrden = collect($validated['componentes_orden'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if (!empty($validated['es_muestra'])) {
            $componentesInvalidos = CotioItems::whereIn('id', $componentesSeleccionados)
                ->where('es_muestra', true)
                ->pluck('id');

            if ($componentesInvalidos->isNotEmpty()) {
                return back()
                    ->withErrors(['componentes' => 'Los componentes seleccionados no pueden ser agrupadores.'])
                    ->withInput();
            }

            // Si el agrupador tiene matrices asignadas, los componentes deben compartir al menos una de esas matrices
            $matricesSeleccionadas = $this->normalizarCodigosMatrices($validated['matrices'] ?? []);
            if (!empty($matricesSeleccionadas) && $componentesSeleccionados->isNotEmpty()) {
                $componentesFueraDeMatriz = $this->componentesFueraDeMatricesCompartidas(
                    $componentesSeleccionados,
                    $matricesSeleccionadas
                );

                if ($componentesFueraDeMatriz->isNotEmpty()) {
                    return back()
                        ->withErrors(['componentes' => 'Algunos componentes no comparten la(s) matriz(ces) del agrupador.'])
                        ->withInput();
                }
            }
        }

        // IMPORTANTE:
        // La tabla legacy `cotio_items` no tiene autoincrement en la columna `id`,
        // por lo que debemos asignar el ID manualmente y asegurarnos de que
        // se persista antes de crear registros en la tabla pivote.
        return DB::transaction(function () use ($validated, $componentesSeleccionados, $componentesOrden, $metodoAnalisis, $metodoMuestreo) {
            $nextId = DB::table('cotio_items')->max('id');
            $nextId = $nextId ? $nextId + 1 : 1;

            $item           = new CotioItems();
            $item->id       = $nextId;
            $item->cotio_descripcion     = $validated['cotio_descripcion'];
            $item->es_muestra            = (bool)($validated['es_muestra'] ?? false);
            $item->agregable_a_comps     = (bool)($validated['agregable_a_comps'] ?? false);
            $item->limites_establecidos  = $validated['limites_establecidos'] ?? null;
            $item->metodo                = $metodoAnalisis;
            $item->metodo_muestreo       = $metodoMuestreo;
            $item->unidad_medida         = $validated['unidad_medida'] ?? null;
            // Asegurar que el precio tenga 2 decimales
            $item->precio                = $validated['precio'] !== null ? round((float)$validated['precio'], 2) : null;

            $trimNota = static function ($v) {
                if ($v === null) {
                    return null;
                }
                $t = trim((string) $v);

                return $t === '' ? null : $t;
            };
            $item->nota_imprimible = $trimNota($validated['nota_imprimible'] ?? null);
            $item->nota_interna = $trimNota($validated['nota_interna'] ?? null);

            $item->save();

            // Guardar matrices en tabla pivote (sync usa la relación y evita conflictos de secuencia)
            $matricesSeleccionadas = collect($validated['matrices'] ?? [])->map(fn ($c) => trim($c))->filter()->unique()->values()->all();
            $item->matrices()->sync($matricesSeleccionadas);

            if ($item->es_muestra && $componentesSeleccionados->isNotEmpty()) {
                // Orden: si no llega desde UI, usar el orden en que se seleccionaron.
                $ordenFinal = $componentesOrden->filter(fn ($id) => $componentesSeleccionados->contains($id));
                if ($ordenFinal->isEmpty()) {
                    $ordenFinal = $componentesSeleccionados->values();
                } else {
                    $faltantes = $componentesSeleccionados->diff($ordenFinal);
                    $ordenFinal = $ordenFinal->concat($faltantes->values());
                }

                $syncData = [];
                foreach ($ordenFinal->values() as $idx => $id) {
                    $syncData[$id] = ['orden' => $idx + 1];
                }
                $item->componentesAsociados()->sync($syncData);
            }

            // Sincronizar notas predeterminadas
            if (!empty($validated['notas_predeterminadas'])) {
                foreach ($validated['notas_predeterminadas'] as $idx => $notaData) {
                    $item->notasPredeterminadas()->create([
                        'titulo' => $notaData['titulo'] ?? null,
                        'contenido' => $notaData['contenido'],
                        'orden' => $idx + 1,
                        'activa' => true
                    ]);
                }
            }

            return redirect()->route('items.index')->with('success', 'Ítem creado correctamente.');
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(CotioItems $cotio_items)
    {
       return redirect()->route('items.edit', $cotio_items);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CotioItems $cotio_items)
    {
        $item = $cotio_items->load(['componentesAsociados.matrices', 'matrices', 'notasPredeterminadas']);
        $matrices = Matriz::orderBy('matriz_descripcion')->get();
        $metodos = Metodo::orderBy('metodo_codigo')->get();
        $componentes = CotioItems::componentes()
            ->where('id', '!=', $item->id)
            ->with(['matrices', 'metodoAnalitico', 'metodoMuestreo'])
            ->orderBy('cotio_descripcion')
            ->get();

        return view('items.edit', compact('item', 'matrices', 'componentes', 'metodos'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CotioItems $cotio_items)
    {
        $item = $cotio_items;

        $validated = $request->validate(array_merge([
            'cotio_descripcion' => ['required', 'string', 'max:255'],
            'es_muestra' => ['nullable', 'boolean'],
            'agregable_a_comps' => ['nullable', 'boolean'],
            'limites_establecidos' => ['nullable', 'string', 'max:255'],
            'unidad_medida' => ['nullable', 'string', 'max:255'],
            'precio' => ['nullable', 'numeric', 'min:0'],
            'componentes' => ['array'],
            'componentes.*' => ['integer', 'exists:cotio_items,id'],
            'componentes_orden' => ['array'],
            'componentes_orden.*' => ['integer', 'exists:cotio_items,id'],
            'matrices' => ['array'],
            'matrices.*' => ['string', 'exists:matriz,matriz_codigo'],
            'nota_imprimible' => ['nullable', 'string'],
            'nota_interna' => ['nullable', 'string'],
            'notas_predeterminadas' => ['array'],
            'notas_predeterminadas.*.titulo' => ['nullable', 'string', 'max:255'],
            'notas_predeterminadas.*.contenido' => ['required', 'string'],
        ], $this->reglasMetodosItem()));

        [$metodoAnalisis, $metodoMuestreo] = $this->resolverMetodosItemValidados($validated);

        $componentesSeleccionados = collect($validated['componentes'] ?? [])->map(fn ($id) => (int) $id)->filter()->reject(fn ($id) => $id === $item->id)->unique();
        $componentesOrden = collect($validated['componentes_orden'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->reject(fn ($id) => $id === $item->id)
            ->unique()
            ->values();

        if (!empty($validated['es_muestra'])) {
            $componentesInvalidos = CotioItems::whereIn('id', $componentesSeleccionados)
                ->where('es_muestra', true)
                ->pluck('id');

            if ($componentesInvalidos->isNotEmpty()) {
                return back()
                    ->withErrors(['componentes' => 'Los componentes seleccionados no pueden ser agrupadores.'])
                    ->withInput();
            }

            // Si el agrupador tiene matrices asignadas, los componentes deben compartir al menos una de esas matrices
            $matricesSeleccionadas = $this->normalizarCodigosMatrices($validated['matrices'] ?? []);
            if (!empty($matricesSeleccionadas) && $componentesSeleccionados->isNotEmpty()) {
                $componentesFueraDeMatriz = $this->componentesFueraDeMatricesCompartidas(
                    $componentesSeleccionados,
                    $matricesSeleccionadas
                );

                if ($componentesFueraDeMatriz->isNotEmpty()) {
                    return back()
                        ->withErrors(['componentes' => 'Algunos componentes no comparten la(s) matriz(ces) del agrupador.'])
                        ->withInput();
                }
            }
        }

        $trimNota = static function ($v) {
            if ($v === null) {
                return null;
            }
            $t = trim((string) $v);

            return $t === '' ? null : $t;
        };
        $esAgrupador = (bool) ($validated['es_muestra'] ?? false);

        DB::transaction(function () use ($item, $validated, $esAgrupador, $trimNota, $componentesSeleccionados, $componentesOrden, $metodoAnalisis, $metodoMuestreo) {
            $item->update([
                'cotio_descripcion' => $validated['cotio_descripcion'],
                'es_muestra' => $esAgrupador,
                'agregable_a_comps' => (bool)($validated['agregable_a_comps'] ?? false),
                'limites_establecidos' => $validated['limites_establecidos'] ?? null,
                'metodo' => $metodoAnalisis,
                'metodo_muestreo' => $metodoMuestreo,
                'unidad_medida' => $validated['unidad_medida'] ?? null,
                // Asegurar que el precio tenga 2 decimales
                'precio' => $validated['precio'] !== null ? round((float)$validated['precio'], 2) : null,
                'nota_imprimible' => $trimNota($validated['nota_imprimible'] ?? null),
                'nota_interna' => $trimNota($validated['nota_interna'] ?? null),
            ]);

            // Sincronizar matrices en tabla pivote
            $matricesSeleccionadas = collect($validated['matrices'] ?? [])->map(fn ($c) => trim($c))->filter()->unique()->values()->all();
            $item->matrices()->sync($matricesSeleccionadas);

            if ($item->es_muestra) {
                $ordenFinal = $componentesOrden->filter(fn ($id) => $componentesSeleccionados->contains($id));
                if ($ordenFinal->isEmpty()) {
                    $ordenFinal = $componentesSeleccionados->values();
                } else {
                    $faltantes = $componentesSeleccionados->diff($ordenFinal);
                    $ordenFinal = $ordenFinal->concat($faltantes->values());
                }

                $syncData = [];
                foreach ($ordenFinal->values() as $idx => $id) {
                    $syncData[$id] = ['orden' => $idx + 1];
                }
                $item->componentesAsociados()->sync($syncData);
            } else {
                $item->componentesAsociados()->detach();
            }

            // Sincronizar notas predeterminadas
            $item->notasPredeterminadas()->delete();
            if (!empty($validated['notas_predeterminadas'])) {
                foreach ($validated['notas_predeterminadas'] as $idx => $notaData) {
                    $item->notasPredeterminadas()->create([
                        'titulo' => $notaData['titulo'] ?? null,
                        'contenido' => $notaData['contenido'],
                        'orden' => $idx + 1,
                        'activa' => true
                    ]);
                }
            }
        });

        return redirect()->route('items.index')->with('success', 'Ítem actualizado correctamente.');
    }

    /**
     * Show the form for confirming deletion.
     */
    public function delete(CotioItems $cotio_items)
    {
        DB::table('cotio_item_component')->where('agrupador_id', $cotio_items->id)->orWhere('componente_id', $cotio_items->id)->delete();
        $cotio_items->delete();
        return redirect()->route('items.index')->with('success', 'Ítem eliminado correctamente.');
    }

    /**
     * Remove a variable from the normativa
     */
    public function removeVariable(Request $request, CotioItems $cotio_items)
    {

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CotioItems $cotio_items)
    {
        DB::table('cotio_item_component')->where('agrupador_id', $cotio_items->id)->orWhere('componente_id', $cotio_items->id)->delete();
        $cotio_items->delete();
        return redirect()->route('items.index')->with('success', 'Ítem eliminado correctamente.');
    }

    /**
     * Mostrar formulario para cambios masivos de precios
     */
    public function showCambiosMasivos()
    {
        return view('items.cambios-masivos-precios');
    }

    /**
     * Aplicar cambios masivos de precios
     */
    public function aplicarCambiosMasivos(Request $request)
    {
        $validated = $request->validate([
            'tipo_cambio' => ['required', 'in:porcentaje,valor_fijo'],
            'valor' => ['required', 'numeric'],
            'filtro_tipo' => ['nullable', 'in:muestras,componentes,todos'],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ]);

        $tipoCambio = $validated['tipo_cambio'];
        $valor = $validated['valor'];
        $filtroTipo = $validated['filtro_tipo'] ?? 'todos';
        $descripcion = $validated['descripcion'] ?? '';

        // Construir query base
        $query = CotioItems::query();

        // Aplicar filtros
        if ($filtroTipo === 'muestras') {
            $query->muestras();
        } elseif ($filtroTipo === 'componentes') {
            $query->componentes();
        }

        // Solo items con precio
        $query->whereNotNull('precio');

        $items = $query->get();

        if ($items->isEmpty()) {
            return back()->withErrors(['error' => 'No se encontraron ítems para actualizar.'])->withInput();
        }

        // Generar ID de operación
        $operacionId = Str::uuid()->toString();
        $usuarioId = Auth::user()->usu_codigo ?? null;
        $cambiosRealizados = 0;

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $precioAnterior = $item->precio;
                $precioNuevo = null;

                if ($tipoCambio === 'porcentaje') {
                    // Calcular nuevo precio: precio * (1 + porcentaje/100)
                    $precioNuevo = $precioAnterior * (1 + ($valor / 100));
                } else {
                    // Sumar o restar valor fijo
                    $precioNuevo = $precioAnterior + $valor;
                }

                // Asegurar que el precio no sea negativo
                if ($precioNuevo < 0) {
                    $precioNuevo = 0;
                }

                // Redondear a 2 decimales
                $precioNuevo = round($precioNuevo, 2);

                // Actualizar precio del item
                $item->update(['precio' => $precioNuevo]);

                // Guardar en historial
                CotioItemPrecioHistorial::create([
                    'operacion_id' => $operacionId,
                    'item_id' => $item->id,
                    'precio_anterior' => $precioAnterior,
                    'precio_nuevo' => $precioNuevo,
                    'tipo_cambio' => $tipoCambio,
                    'valor_aplicado' => $valor,
                    'descripcion' => $descripcion,
                    'usuario_id' => $usuarioId,
                    'fecha_cambio' => now(),
                ]);

                $cambiosRealizados++;
            }

            DB::commit();

            return redirect()
                ->route('items.historial-precios')
                ->with('success', "Se actualizaron {$cambiosRealizados} precios correctamente. Operación ID: " . substr($operacionId, 0, 8));
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al aplicar los cambios: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Mostrar historial de cambios de precios
     */
    public function historialPrecios(Request $request)
    {
        $query = CotioItemPrecioHistorial::with(['item', 'usuario', 'usuarioReversion'])
            ->orderBy('fecha_cambio', 'desc');

        // Filtrar por operación si se proporciona
        if ($request->has('operacion_id')) {
            $query->porOperacion($request->operacion_id);
        }

        // Filtrar solo no revertidos si se solicita
        if ($request->has('solo_activos') && $request->solo_activos) {
            $query->noRevertidos();
        }

        $historial = $query->paginate(20)->withQueryString();

        // Obtener operaciones únicas para el filtro
        $operaciones = CotioItemPrecioHistorial::select('operacion_id', DB::raw('MIN(fecha_cambio) as fecha'), DB::raw('COUNT(*) as cantidad'))
            ->groupBy('operacion_id')
            ->orderBy('fecha', 'desc')
            ->get()
            ->map(function ($operacion) {
                // Convertir la fecha string a Carbon
                $operacion->fecha = \Carbon\Carbon::parse($operacion->fecha);
                return $operacion;
            });

        return view('items.historial-precios', compact('historial', 'operaciones'));
    }

    /**
     * Revertir cambios de una operación
     */
    public function revertirCambios(Request $request, $operacionId)
    {
        $cambios = CotioItemPrecioHistorial::porOperacion($operacionId)
            ->noRevertidos()
            ->with('item')
            ->get();

        if ($cambios->isEmpty()) {
            return back()->withErrors(['error' => 'No se encontraron cambios para revertir o ya fueron revertidos.']);
        }

        $usuarioId = Auth::user()->usu_codigo ?? null;

        DB::beginTransaction();
        try {
            foreach ($cambios as $cambio) {
                // Restaurar precio anterior
                $cambio->item->update(['precio' => $cambio->precio_anterior]);

                // Marcar como revertido
                $cambio->update([
                    'revertido' => true,
                    'fecha_reversion' => now(),
                    'usuario_reversion_id' => $usuarioId,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('items.historial-precios')
                ->with('success', "Se revirtieron {$cambios->count()} cambios correctamente.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error al revertir los cambios: ' . $e->getMessage()]);
        }
    }

    /**
     * Mostrar formulario de importación
     */
    public function showImportar()
    {
        return view('items.importar');
    }

    /**
     * Procesar archivo Excel de importación
     */
    public function procesarImportacion(Request $request)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'], // 10MB
        ]);

        try {
            @ini_set('memory_limit', '512M');
            @ini_set('max_execution_time', '600');
            if (function_exists('set_time_limit')) {
                set_time_limit(600);
            }

            Log::info('Iniciando importación de archivo', [
                'archivo' => $request->file('archivo')->getClientOriginalName(),
                'tamaño' => $request->file('archivo')->getSize()
            ]);

            $import = new ItemsImport();
            
            // Importar solo la primera hoja usando toCollection y luego procesar manualmente
            $collection = Excel::toCollection($import, $request->file('archivo'))->first();
            
            if ($collection) {
                $import->collection($collection);
            }

            $successCount = $import->getSuccessCount();
            $errorCount = $import->getErrorCount();
            $errors = $import->getErrors();

            Log::info('Importación completada', [
                'successCount' => $successCount,
                'errorCount' => $errorCount,
                'errors' => $errors
            ]);

            $message = "Importación completada. {$successCount} determinación(es) procesada(s) correctamente.";
            
            if ($successCount == 0 && $errorCount == 0) {
                $message = "No se encontraron datos para importar. Verifica que el archivo tenga datos en la primera hoja (después de los encabezados).";
                return redirect()
                    ->route('items.importar')
                    ->with('warning', $message);
            }
            
            if ($errorCount > 0) {
                $message .= " {$errorCount} error(es) encontrado(s).";
                return redirect()
                    ->route('items.importar')
                    ->with('success', $message)
                    ->with('import_errors', $errors);
            }

            return redirect()
                ->route('items.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Error al procesar importación', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()
                ->route('items.importar')
                ->withErrors(['error' => 'Error al procesar el archivo: ' . $e->getMessage()]);
        }
    }

    /**
     * Descargar plantilla Excel
     */
    public function descargarPlantilla()
    {
        $incluirComponentes = request()->has('incluir_componentes') && request()->get('incluir_componentes') == '1';
        return Excel::download(new ItemsTemplateExport($incluirComponentes), 'plantilla_importar_determinaciones.xlsx');
    }

    /**
     * Exportar determinaciones en formato de plantilla (para re-importar)
     */
    public function exportar(Request $request)
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '300');

        $search = $request->input('q');
        $tipo = $request->input('tipo');
        $matriz = $request->input('matriz');

        return Excel::download(
            new ItemsExport($search, $tipo, $matriz),
            'determinaciones_export.xlsx'
        );
    }

    /**
     * API endpoint para obtener cotio_items con matrices y métodos para leyes-normativas
     */
    public function apiIndexForLeyesNormativas(Request $request)
    {
        $search = $request->input('search', '');
        
        $query = CotioItems::with(['matriz', 'metodoAnalitico', 'metodoMuestreo']);
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('cotio_descripcion', 'ILIKE', "%{$search}%")
                  ->orWhere('id', 'ILIKE', "%{$search}%");
            });
        }
        
        $items = $query->orderBy('cotio_descripcion', 'asc')->get();
        
        $result = $items->map(function($item) {
            // Obtener matriz - verificar relación cargada
            $matrizNombre = 'Sin matriz';
            if ($item->relationLoaded('matriz') && $item->matriz) {
                $matrizNombre = $item->matriz->matriz_descripcion;
            } elseif ($item->matriz_codigo) {
                // Si no está cargada pero tiene código, intentar cargarla
                $matriz = \App\Models\Matriz::find($item->matriz_codigo);
                if ($matriz) {
                    $matrizNombre = $matriz->matriz_descripcion;
                }
            }
            
            // Obtener métodos (tabla metodo)
            $metodoAnalitico = $item->metodoAnalitico?->metodo_descripcion;
            if (!$metodoAnalitico && $item->metodo) {
                $metodoAnalitico = trim((string) $item->metodo);
            }

            $metodoMuestreo = $item->metodoMuestreo?->metodo_descripcion;
            if (!$metodoMuestreo && $item->metodo_muestreo) {
                $metodoMuestreo = trim((string) $item->metodo_muestreo);
            }
            
            $metodos = array_filter([$metodoAnalitico, $metodoMuestreo]);
            $metodosTexto = !empty($metodos) ? implode(' / ', $metodos) : 'Sin método';
            
            // Formato mejorado para el display_text - siempre mostrar matriz y métodos
            $displayText = "{$item->id} - {$item->cotio_descripcion}";
            $displayText .= " | Matriz: {$matrizNombre}";
            $displayText .= " | Métodos: {$metodosTexto}";
            
            return [
                'id' => $item->id,
                'descripcion' => $item->cotio_descripcion,
                'matriz' => $matrizNombre,
                'metodos' => $metodosTexto,
                'unidad_medida' => $item->unidad_medida,
                'display_text' => $displayText
            ];
        });
        
        return response()->json($result);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function reglasMetodosItem(): array
    {
        return [
            'metodo' => ['nullable', 'string', 'max:50'],
            'metodo_muestreo' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolverMetodosItemValidados(array $validated): array
    {
        return [
            $this->resolverCodigoMetodoItem($validated['metodo'] ?? null, 'metodo'),
            $this->resolverCodigoMetodoItem($validated['metodo_muestreo'] ?? null, 'metodo_muestreo'),
        ];
    }

    private function resolverCodigoMetodoItem(?string $raw, string $fieldName): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $codigo = trim($raw);
        $metodo = Metodo::query()
            ->whereRaw('trim(metodo_codigo) = ?', [$codigo])
            ->first();

        if (!$metodo) {
            throw ValidationException::withMessages([
                $fieldName => "El código «{$codigo}» no existe en la tabla metodo.",
            ]);
        }

        return $metodo->metodo_codigo;
    }

    /**
     * @param  array<int, mixed>  $matrices
     * @return list<string>
     */
    private function normalizarCodigosMatrices(array $matrices): array
    {
        return collect($matrices)
            ->map(fn ($codigo) => trim((string) $codigo))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Componentes que no comparten ninguna matriz con el agrupador (pivote + columna legacy).
     *
     * @param  \Illuminate\Support\Collection<int, int>  $componenteIds
     * @param  list<string>  $matricesSeleccionadas
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function componentesFueraDeMatricesCompartidas($componenteIds, array $matricesSeleccionadas)
    {
        if ($componenteIds->isEmpty() || empty($matricesSeleccionadas)) {
            return collect();
        }

        $placeholders = implode(',', array_fill(0, count($matricesSeleccionadas), '?'));

        return CotioItems::query()
            ->whereIn('id', $componenteIds)
            ->where(function ($q) use ($matricesSeleccionadas, $placeholders) {
                $q->whereDoesntHave('matrices', function ($sub) use ($matricesSeleccionadas, $placeholders) {
                    $sub->whereIn(DB::raw('TRIM(cotio_items_matriz.matriz_codigo)'), $matricesSeleccionadas);
                })->where(function ($legacy) use ($matricesSeleccionadas, $placeholders) {
                    $legacy->whereNull('matriz_codigo')
                        ->orWhereRaw(
                            'TRIM(cotio_items.matriz_codigo) NOT IN (' . $placeholders . ')',
                            $matricesSeleccionadas
                        );
                });
            })
            ->pluck('id');
    }
}
