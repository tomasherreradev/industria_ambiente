<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\InventarioLab;
use Illuminate\Support\Facades\Log;



class InventarioLabController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'estado' => 'nullable|string|in:libre,ocupado',
            'n_serie_lote' => 'nullable|string|max:255',
        ]);
    
        $query = InventarioLab::query();
    
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where(function($q) use ($searchTerm) {
                $q->where('equipamiento', 'like', "%{$searchTerm}%")
                  ->orWhere('marca_modelo', 'like', "%{$searchTerm}%");
            });
        }
    
        if ($request->filled('activo')) {
            $query->where('activo', $request->input('activo'));
        }
    
        if ($request->filled('n_serie_lote')) {
            $serieTerm = $request->input('n_serie_lote');
            $query->where(function($q) use ($serieTerm) {
                $q->where('n_serie_lote', 'like', "%{$serieTerm}%")
                  ->orWhere('codigo_ficha', 'like', "%{$serieTerm}%");
            });
        }
    
        $query->orderBy('equipamiento');
    
        $inventarios = $query->paginate(50);
    
        return view('inventarios.index', compact('inventarios'));
    }

    

    public function show($id)
    {
        $inventario = InventarioLab::findOrFail($id);

        return view('inventarios.edit', compact('inventario'));
    }

    public function update(Request $request, $id)
    {
        // Log::info($request->all());
        $inventario = InventarioLab::find($id);
        $inventario->equipamiento = $request->equipamiento;
        $inventario->marca_modelo = $request->marca_modelo;
        $inventario->n_serie_lote = $request->n_serie_lote;
        $inventario->codigo_ficha = $request->codigo_ficha;
        $inventario->observaciones = $request->observaciones;
        $inventario->activo = $request->activo;
        $inventario->fecha_calibracion = $request->fecha_calibracion;

        if ($request->hasFile('certificado')) {
            if ($inventario->certificado) {
                Storage::disk('public')->delete($inventario->certificado);
            }
            
            $path = $request->file('certificado')->store('certificados', 'public');
            $inventario->certificado = $path;
        } else {
            $inventario->certificado = $inventario->certificado;
        }

        $inventario->save();
        return redirect()->route('inventarios.index')->with('success', 'Equipamiento actualizado correctamente.');
    }
    
    public function destroy($id)
    {
        $inventario = InventarioLab::find($id);
        $inventario->delete();
        return redirect()->route('inventarios.index');
    }


    public function create()
    {
        return view('inventarios.create');
    }

    public function store(Request $request)
    {
        Log::info($request->all());
        $inventario = new InventarioLab();
        $inventario->equipamiento = $request->equipamiento;
        $inventario->marca_modelo = $request->marca_modelo;
        $inventario->n_serie_lote = $request->n_serie_lote;
        $inventario->codigo_ficha = $request->codigo_ficha;
        $inventario->observaciones = $request->observaciones;
        $inventario->activo = $request->activo;
        $inventario->fecha_calibracion = $request->fecha_calibracion;

        if ($request->hasFile('certificado_calibracion')) {
            $path = $request->file('certificado_calibracion')->store('certificados', 'public');
            $inventario->certificado = $path;
        }


        $inventario->save();
        return redirect()->route('inventarios.index')->with('success', 'Equipamiento creado correctamente.');
    }
    
}