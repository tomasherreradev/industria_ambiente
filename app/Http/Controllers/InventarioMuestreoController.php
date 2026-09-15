<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InventarioMuestreo;
use Illuminate\Support\Facades\Log;



class InventarioMuestreoController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'estado' => 'nullable|string|in:libre,ocupado',
            'n_serie_lote' => 'nullable|string|max:255',
        ]);
    
        $query = InventarioMuestreo::query();
    
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where(function($q) use ($searchTerm) {
                $q->where('equipamiento', 'like', "%{$searchTerm}%")
                  ->orWhere('marca_modelo', 'like', "%{$searchTerm}%");
            });
        }
    
        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }
    
        if ($request->filled('n_serie_lote')) {
            $serieTerm = $request->input('n_serie_lote');
            $query->where(function($q) use ($serieTerm) {
                $q->where('n_serie_lote', 'like', "%{$serieTerm}%");
            });
        }
    
        $query->orderBy('equipamiento');
    
        $inventarios = $query->paginate(50);
    
        return view('inventarios-muestreo.index', compact('inventarios'));
    }

    

    public function show($id)
    {
        $inventario = InventarioMuestreo::findOrFail($id);

        return view('inventarios-muestreo.edit', compact('inventario'));
    }

    public function update(Request $request, $id)
    {
        try {
            // Validación
            $validated = $request->validate([
                'equipamiento' => 'required|string|max:255',
                'marca_modelo' => 'nullable|string|max:255',
                'n_serie_lote' => 'nullable|string|max:255',
                'fecha_calibracion' => 'nullable|date',
                'activo' => 'required|boolean',
                'certificado' => [
                    'nullable',
                    function ($attribute, $value, $fail) use ($request) {
                        if (is_string($value)) return;
                        
                        if ($request->hasFile($attribute)) {
                            $file = $request->file($attribute);
                            if ($file->getClientOriginalExtension() !== 'pdf') {
                                $fail('El certificado debe ser un archivo PDF.');
                            }
                            if ($file->getSize() > 5120 * 1024) {
                                $fail('El certificado no debe exceder los 5MB.');
                            }
                        }
                    }
                ],
                'observaciones' => 'nullable|string'
            ]);
    
            $inventario = InventarioMuestreo::findOrFail($id);
            
            // Inicializar datos con todos los campos validados
            $data = $validated;
    
            // Manejo del certificado
            if ($request->hasFile('certificado')) {
                // Eliminar el anterior si existe
                if ($inventario->certificado) {
                    Storage::disk('public')->delete($inventario->certificado);
                }
                
                // Guardar el nuevo
                $path = $request->file('certificado')->store('certificados', 'public');
                $data['certificado'] = $path;
            } else {
                // Conservar el certificado existente si no se sube uno nuevo
                $data['certificado'] = $inventario->certificado;
            }
    
            // Actualización
            $inventario->update($data);
    
            return redirect()->route('inventarios-muestreo.index')
                ->with('success', 'Equipamiento actualizado correctamente.');
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return back()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }
    
    public function destroy($id)
    {
        $inventario = InventarioMuestreo::find($id);
        $inventario->delete();
        return redirect()->route('inventarios-muestreo.index');
    }


    public function create()
    {
        return view('inventarios-muestreo.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'equipamiento' => 'required|string|max:255',
            'marca_modelo' => 'nullable|string|max:255',
            'n_serie_lote' => 'nullable|string|max:255',
            'fecha_calibracion' => 'nullable|date',
            'activo' => 'required|boolean',
            'certificado' => 'nullable|file|mimes:pdf|max:5120', // 5MB máximo
            'observaciones' => 'nullable|string'
        ]);

        $data = $request->except('certificado');
        
        if ($request->hasFile('certificado')) {
            $path = $request->file('certificado')->store('certificados', 'public');
            $data['certificado'] = $path;
        }

        InventarioMuestreo::create($data);
        
        return redirect()->route('inventarios-muestreo.index')
            ->with('success', 'Equipamiento creado correctamente.');
    }
    
}