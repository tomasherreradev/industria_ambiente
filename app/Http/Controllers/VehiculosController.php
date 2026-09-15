<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vehiculo;

class VehiculosController extends Controller
{
    public function index(Request $request)
    {
        $query = Vehiculo::query();
    
        if ($request->filled('marca')) {
            $query->where('marca', 'like', '%' . $request->marca . '%');
        }
    
        if ($request->filled('modelo')) {
            $query->where('modelo', 'like', '%' . $request->modelo . '%');
        }
    
        if ($request->filled('anio')) {
            $query->where('anio', $request->anio);
        }
    
        if ($request->filled('patente')) {
            $query->where('patente', 'like', '%' . strtoupper($request->patente) . '%');
        }

        if ($request->filled('estado')) {
            $query->where('estado', strtolower($request->estado));
        }
    
        $vehiculos = $query->with(['tareas' => function ($q) {
            $q->orderByDesc('cotio_numcoti'); 
        }])->paginate(40)->appends($request->query());
    
        return view('vehiculos.index', compact('vehiculos'));
    }
    
    
    public function getVehiculo($id)
    {
        $vehiculo = Vehiculo::findOrFail($id);

        return view('vehiculos.edit', compact('vehiculo'));
    }



    public function update(Request $request, $id)
    {
        try {
            $vehiculo = Vehiculo::find($id);
    
            if (!$vehiculo) {
                return redirect()->route('vehiculos.index')
                    ->with('error', 'Vehículo no encontrado.');
            }
    
            $request->validate([
                'marca' => 'nullable|string|max:255',
                'modelo' => 'nullable|string|max:255',
                'anio' => 'nullable|integer',
                'patente' => 'required|string|max:255',
                'tipo' => 'nullable|string|max:255',
                'descripcion' => 'nullable|string',
                'estado' => 'nullable|string|max:255',
                'ultimo_mantenimiento' => 'nullable|date',
                'estado_gral' => 'nullable|string|max:255',
            ]);
    
            $vehiculo->update($request->all());
    
            return redirect()->route('vehiculos.index')
                ->with('success', 'Vehículo actualizado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('vehiculos.index')
                ->with('error', 'Error al actualizar el vehículo: ' . $e->getMessage());
        }
    }    



    public function create()
    {
        return view('vehiculos.create');
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'marca' => 'required|string|max:255',
                'modelo' => 'required|string|max:255',
                'anio' => 'required|integer',
                'patente' => 'required|string|max:255',
                'tipo' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'estado' => 'nullable|string|max:255',
                'ultimo_mantenimiento' => 'nullable|date',
                'estado_gral' => 'nullable|string|max:255',
            ]);
    
            $vehiculo = Vehiculo::create($request->all());
    
            return redirect()->route('vehiculos.index')
                ->with('success', 'Vehículo creado correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('vehiculos.index')
                ->with('error', 'Error al crear el vehículo: ' . $e->getMessage());
        }
    }



    public function destroy($id)
    {
        $vehiculo = Vehiculo::find($id);
        $vehiculo->delete();
        return redirect()->route('vehiculos.index')
            ->with('success', 'Vehículo eliminado correctamente.');
    }

    public function getVehiculosApi()
    {
        $vehiculos = Vehiculo::where('estado', 'libre')
            ->orWhere('estado', 'ocupado')
            ->get(['id', 'marca', 'modelo', 'patente', 'estado']);
        
        return response()->json($vehiculos);
    }
}