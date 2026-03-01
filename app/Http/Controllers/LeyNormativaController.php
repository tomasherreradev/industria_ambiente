<?php

namespace App\Http\Controllers;

use App\Models\LeyNormativa;
use App\Models\Variable;
use App\Models\CotioItems;
use App\Exports\LeyesNormativasTemplateExport;
use App\Imports\LeyesNormativasImportWrapper;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class LeyNormativaController extends Controller
{
    public function __construct()
    {
  
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = LeyNormativa::query();

        // Filtro de búsqueda
        if ($request->filled('search')) {
            $query->buscar($request->search);
        }

        // Filtro por grupo
        if ($request->filled('grupo')) {
            $query->porGrupo($request->grupo);
        }

        // Filtro por estado
        if ($request->filled('activo')) {
            $query->where('activo', $request->activo);
        }

        $normativas = $query->orderBy('grupo')->orderBy('codigo')->paginate(15);
        $grupos = LeyNormativa::getGruposUnicos();
        
        // Obtener última fecha de importación y asegurar que sea Carbon
        $ultimaImportacionRaw = Cache::get('leyes_normativas_ultima_importacion');
        $ultimaImportacion = $ultimaImportacionRaw ? Carbon::parse($ultimaImportacionRaw) : null;

        return view('leyes-normativas.index', compact('normativas', 'grupos', 'ultimaImportacion'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $grupos = LeyNormativa::getGruposUnicos();
        return view('leyes-normativas.create', compact('grupos'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo' => 'required|string|max:255|unique:leyes_normativas,codigo',
            'nombre' => 'required|string|max:255',
            'grupo' => 'nullable|string|max:255',
            'articulo' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'variables_aplicables' => 'nullable|string',
            'organismo_emisor' => 'nullable|string|max:255',
            'fecha_vigencia' => 'nullable|date',
            'fecha_actualizacion' => 'nullable|date',
            'observaciones' => 'nullable|string',
            'activo' => 'boolean'
        ]);

        $validated['activo'] = $request->has('activo');

        $leyNormativa = LeyNormativa::create($validated);

        // Asociar variables basadas en cotio_items si se enviaron
        if ($request->has('variables') && is_array($request->variables)) {
            foreach ($request->variables as $variableData) {
                if (!empty($variableData['cotio_item_id'])) {
                    // Buscar o crear la variable basada en el cotio_item_id
                    $cotioItem = CotioItems::find($variableData['cotio_item_id']);
                    if ($cotioItem) {
                        // Buscar si ya existe una variable para este cotio_item
                        $variable = Variable::where('cotio_item_id', $variableData['cotio_item_id'])->first();
                        
                        if (!$variable) {
                            // Crear nueva variable basada en el cotio_item
                            $variable = Variable::create([
                                'codigo' => $cotioItem->id,
                                'nombre' => $cotioItem->cotio_descripcion,
                                'descripcion' => $cotioItem->cotio_descripcion,
                                'unidad_medicion' => $cotioItem->unidad_medida,
                                'cotio_item_id' => $cotioItem->id,
                                'activo' => true
                            ]);
                        }
                        
                        // Asociar la variable a la ley normativa
                        $leyNormativa->variables()->attach($variable->id, [
                            'valor_limite' => $variableData['valor_limite'] ?? null,
                            'unidad_medida' => $variableData['unidad_medida'] ?? $cotioItem->unidad_medida,
                        ]);
                    }
                }
            }
        }

        return redirect()->route('leyes-normativas.index')
                        ->with('success', 'Ley/Normativa creada exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(LeyNormativa $leyNormativa)
    {
        $leyNormativa->load([
            'cotios', 
            'variables.cotioItem' => function($query) {
                $query->with(['matriz', 'metodoAnalitico', 'metodoMuestreo']);
            }
        ]);
        $leyNormativa->load(['variables' => function($query) {
            $query->withPivot('valor_limite', 'unidad_medida');
        }]);
        return view('leyes-normativas.show', compact('leyNormativa'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LeyNormativa $leyNormativa)
    {
        $grupos = LeyNormativa::getGruposUnicos();
        // Cargar las variables relacionadas con sus valores pivote y cotio_items
        $leyNormativa->load(['variables.cotioItem' => function($query) {
            $query->with(['matriz', 'metodoAnalitico', 'metodoMuestreo']);
        }]);
        $leyNormativa->load(['variables' => function($query) {
            $query->withPivot(['valor_limite', 'unidad_medida']);
        }]);
        return view('leyes-normativas.edit', compact('leyNormativa', 'grupos'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LeyNormativa $leyNormativa)
    {
        $validated = $request->validate([
            'codigo' => 'required|string|max:255|unique:leyes_normativas,codigo,' . $leyNormativa->id,
            'nombre' => 'required|string|max:255',
            'grupo' => 'nullable|string|max:255',
            'articulo' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'variables_aplicables' => 'nullable|string',
            'organismo_emisor' => 'nullable|string|max:255',
            'fecha_vigencia' => 'nullable|date',
            'fecha_actualizacion' => 'nullable|date',
            'observaciones' => 'nullable|string',
            'activo' => 'boolean',
            'variables' => 'nullable|array',
            'variables.*.cotio_item_id' => 'required|exists:cotio_items,id',
            'variables.*.valor_limite' => 'nullable|string|max:255',
            'variables.*.unidad_medida' => 'nullable|string|max:50'
        ]);

        $validated['activo'] = $request->has('activo');

        // Actualizar los datos básicos de la norma
        $leyNormativa->update($validated);

        // Sincronizar variables relacionadas basadas en cotio_items
        $variablesData = [];
        if ($request->has('variables') && is_array($request->variables)) {
            foreach ($request->variables as $variableData) {
                if (!empty($variableData['cotio_item_id'])) {
                    // Buscar o crear la variable basada en el cotio_item_id
                    $cotioItem = CotioItems::find($variableData['cotio_item_id']);
                    if ($cotioItem) {
                        // Buscar si ya existe una variable para este cotio_item
                        $variable = Variable::where('cotio_item_id', $variableData['cotio_item_id'])->first();
                        
                        if (!$variable) {
                            // Crear nueva variable basada en el cotio_item
                            $variable = Variable::create([
                                'codigo' => $cotioItem->id,
                                'nombre' => $cotioItem->cotio_descripcion,
                                'descripcion' => $cotioItem->cotio_descripcion,
                                'unidad_medicion' => $cotioItem->unidad_medida,
                                'cotio_item_id' => $cotioItem->id,
                                'activo' => true
                            ]);
                        }
                        
                        $variablesData[$variable->id] = [
                            'valor_limite' => $variableData['valor_limite'] ?? null,
                            'unidad_medida' => $variableData['unidad_medida'] ?? $cotioItem->unidad_medida,
                        ];
                    }
                }
            }
        }
        $leyNormativa->variables()->sync($variablesData);

        return redirect()->route('leyes-normativas.index')
                        ->with('success', 'Ley/Normativa actualizada exitosamente.');
    }

    /**
     * Show the form for confirming deletion.
     */
    public function delete(LeyNormativa $leyNormativa)
    {
        $leyNormativa->load('cotios');
        return view('leyes-normativas.delete', compact('leyNormativa'));
    }

    /**
     * Remove a variable from the normativa
     */
    public function removeVariable(Request $request, LeyNormativa $leyNormativa)
    {
        $request->validate([
            'variable_id' => 'required|exists:variables,id'
        ]);

        $variableId = $request->variable_id;
        
        // Verificar que la variable esté asociada a la normativa
        if (!$leyNormativa->variables()->where('variable_id', $variableId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'La variable no está asociada a esta normativa.'
            ], 404);
        }

        // Eliminar la relación
        $leyNormativa->variables()->detach($variableId);

        return response()->json([
            'success' => true,
            'message' => 'Variable eliminada de la normativa exitosamente.'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LeyNormativa $leyNormativa)
    {
        // Verificar si está siendo usado
        if ($leyNormativa->cotios()->count() > 0) {
            return redirect()->route('leyes-normativas.index')
                            ->with('error', 'No se puede eliminar la normativa porque está siendo usada en cotizaciones.');
        }

        $leyNormativa->delete();

        return redirect()->route('leyes-normativas.index')
                        ->with('success', 'Ley/Normativa eliminada exitosamente.');
    }

    /**
     * Exportar plantilla Excel para importación masiva
     */
    public function exportTemplate()
    {
        return Excel::download(new LeyesNormativasTemplateExport(), 'plantilla_importacion_leyes_normativas.xlsx');
    }

    /**
     * Mostrar formulario de importación
     */
    public function showImport()
    {
        return view('leyes-normativas.import');
    }

    /**
     * Procesar importación de archivo Excel
     */
    public function import(Request $request)
    {
        $request->validate([
            'archivo' => 'required|mimes:xlsx,xls|max:10240' // Máximo 10MB
        ]);

        try {
            $import = new LeyesNormativasImportWrapper();
            Excel::import($import, $request->file('archivo'));

            // Guardar la fecha de la última importación
            Cache::forever('leyes_normativas_ultima_importacion', now());

            $mensaje = 'Importación completada. ';
            $mensaje .= "Leyes creadas: {$import->getLeyesCreadas()}, ";
            $mensaje .= "Variables asociadas: {$import->getVariablesAsociadas()}, ";
            $mensaje .= "Filas procesadas: {$import->getSuccessCount()}";

            $errores = $import->getErrors();
            if (!empty($errores)) {
                $mensaje .= ". Errores: " . count($errores);
                return redirect()->route('leyes-normativas.index')
                    ->with('warning', $mensaje)
                    ->with('import_errors', $errores);
            }

            return redirect()->route('leyes-normativas.index')
                ->with('success', $mensaje);
        } catch (\Exception $e) {
            return redirect()->route('leyes-normativas.index')
                ->with('error', 'Error al importar: ' . $e->getMessage());
        }
    }
}
