<?php

namespace App\Http\Controllers;

use App\Models\VariableRequerida;
use App\Models\Cotio;
use App\Models\CotioItems;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class VariableRequeridaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = VariableRequerida::query();
    
        // Aplicar filtros
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('cotio_descripcion', 'ilike', "%{$search}%")
                  ->orWhere('nombre', 'ilike', "%{$search}%");
            });
        }
    
        if ($request->filled('obligatorio') && in_array($request->input('obligatorio'), ['0', '1'])) {
            $query->where('obligatorio', $request->input('obligatorio'));
        }
    
        // Agrupar los mismos resultados para el acordeón
        $groupedVariables = $query->orderBy('cotio_descripcion')->get()->groupBy('cotio_descripcion');
    
        return view('variables-requeridas.index', compact('groupedVariables'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $cotioDescripciones = CotioItems::select('cotio_descripcion')
            ->whereNotNull('cotio_descripcion')
            ->distinct()
            ->orderBy('cotio_descripcion')
            ->pluck('cotio_descripcion');
        return view('variables-requeridas.create', compact('cotioDescripciones'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'cotio_descripciones' => 'required|array',
            'cotio_descripciones.*' => 'string|max:255',
            'variables' => 'required|array',
            'variables.*.nombre' => 'required|string|max:255',
            'variables.*.obligatorio' => 'required|boolean',
            'variables.*.unidad_medicion' => 'nullable|string|max:255',
        ]);

        foreach ($request->cotio_descripciones as $cotioDesc) {
            foreach ($request->variables as $variableData) {
                $exists = VariableRequerida::where('cotio_descripcion', $cotioDesc)
                                        ->where('nombre', $variableData['nombre'])
                                        ->exists();
                
                if (!$exists) {
                    VariableRequerida::create([
                        'cotio_descripcion' => $cotioDesc,
                        'nombre' => $variableData['nombre'],
                        'obligatorio' => $variableData['obligatorio'],
                        'unidad_medicion' => $variableData['unidad_medicion'] ?? null,
                    ]);
                }
            }
        }
        return redirect()->route('variables-requeridas.index')
                    ->with('success', 'Variables creadas exitosamente.');
    }


    /**
     * Display the specified resource.
     */
    public function show(VariableRequerida $variableRequerida)
    {
        return view('variables-requeridas.show', compact('variableRequerida'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(VariableRequerida $variableRequerida)
    {
        $cotioDescripciones = CotioItems::select('cotio_descripcion')
            ->where('es_muestra', true)
            ->whereNotNull('cotio_descripcion')
            ->distinct()
            ->orderBy('cotio_descripcion')
            ->pluck('cotio_descripcion');
        
        return view('variables-requeridas.edit', compact('variableRequerida', 'cotioDescripciones'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, VariableRequerida $variableRequerida)
    {
        $request->validate([
            'cotio_descripcion' => 'required|string|max:255',
            'nombre' => 'required|string|max:255',
            'obligatorio' => 'required|boolean',
            'unidad_medicion' => 'nullable|string|max:255',
        ]);

        $variableRequerida->update($request->all());

        return redirect()->route('variables-requeridas.index')
                         ->with('success', 'Variable actualizada exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(VariableRequerida $variableRequerida)
    {
        $variableRequerida->delete();

        return redirect()->route('variables-requeridas.index')
                         ->with('success', 'Variable eliminada exitosamente.');
    }


    /**
 * Muestra el formulario para editar un grupo de variables.
 */

 public function editGroup($groupName)
 {
     // Decodificar el nombre del grupo
     $groupName = urldecode($groupName);
     
     // Obtener las variables del grupo
     $variables = VariableRequerida::where('cotio_descripcion', $groupName)
         ->orderBy('id')
         ->get();
         
     if ($variables->isEmpty()) {
         abort(404, 'Grupo no encontrado');
     }
     
     // Obtener el cotio_id de la primera variable, o buscar uno relacionado
     $cotioId = $variables->first()->cotio_id;
     
     // Si no hay cotio_id, intentar obtenerlo de la tabla cotios
     if (!$cotioId) {
         $cotio = Cotio::where('cotio_descripcion', $groupName)->first();
         $cotioId = $cotio ? $cotio->id : null;
     }
     
     return view('variables-requeridas.edit-group', [
         'groupName' => $groupName,
         'variables' => $variables,
         'cotioId' => $cotioId
     ]);
 }

 

 public function updateGroup(Request $request, $groupName)
 {
     $groupName = urldecode($groupName);
     
     $request->validate([
         'variables' => 'sometimes|array',
         'variables.*.id' => 'required|exists:variables_requeridas,id',
         'variables.*.nombre' => 'required|string|max:255',
         'variables.*.obligatorio' => 'required|boolean',
         'new_variables' => 'sometimes|array',
         'new_variables.*.nombre' => 'nullable|string|max:255',
         'new_variables.*.obligatorio' => 'nullable|boolean',
         'cotio_id' => 'nullable|exists:cotios,id',
     ]);
 
     DB::beginTransaction();
     try {
         // Actualizar variables existentes
         if ($request->has('variables')) {
             foreach ($request->variables as $variableData) {
                 $variable = VariableRequerida::findOrFail($variableData['id']);
                 $variable->update([
                     'nombre' => $variableData['nombre'],
                     'obligatorio' => $variableData['obligatorio'],
                     'unidad_medicion' => $variableData['unidad_medicion'] ?? null,
                 ]);
             }
         }
 
         // Crear nuevas variables (solo las que tengan nombre)
         if ($request->has('new_variables')) {
             foreach ($request->new_variables as $newVariable) {
                 if (!empty(trim($newVariable['nombre']))) {
                     VariableRequerida::create([
                         'cotio_id' => $request->cotio_id,
                         'cotio_descripcion' => $groupName,
                         'nombre' => trim($newVariable['nombre']),
                         'obligatorio' => $newVariable['obligatorio'] ?? false,
                         'unidad_medicion' => $newVariable['unidad_medicion'] ?? null,
                     ]);
                 }
             }
         }
         
         DB::commit();
         return redirect()->route('variables-requeridas.index', ['groupName' => urlencode($groupName)])
             ->with('success', 'Grupo de variables actualizado exitosamente.');
             
     } catch (\Exception $e) {
         DB::rollBack();
         return back()->with('error', 'Ocurrió un error al actualizar el grupo de variables: ' . $e->getMessage())
                      ->withInput();
     }
 }

}