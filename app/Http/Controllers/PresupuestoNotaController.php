<?php

namespace App\Http\Controllers;

use App\Models\PresupuestoNota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PresupuestoNotaController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        $notas = PresupuestoNota::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($sub) use ($like) {
                    if (DB::connection()->getDriverName() === 'pgsql') {
                        $sub->where('titulo', 'ILIKE', $like)
                            ->orWhere('contenido', 'ILIKE', $like);
                    } else {
                        $sub->where('titulo', 'like', $like)
                            ->orWhere('contenido', 'like', $like);
                    }
                });
            })
            ->ordenadas()
            ->paginate(20)
            ->withQueryString();

        return view('presupuesto-notas.index', compact('notas', 'q'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $maxOrden = (int) PresupuestoNota::query()->max('orden');
        $validated['orden'] = $maxOrden + 1;

        PresupuestoNota::create($validated);

        return redirect()
            ->route('ventas.notas.index')
            ->with('success', 'Nota predeterminada guardada correctamente.');
    }

    public function edit(PresupuestoNota $presupuestoNota)
    {
        return view('presupuesto-notas.edit', ['nota' => $presupuestoNota]);
    }

    public function update(Request $request, PresupuestoNota $presupuestoNota)
    {
        $validated = $this->validatePayload($request);
        $presupuestoNota->update($validated);

        return redirect()
            ->route('ventas.notas.index')
            ->with('success', 'Nota predeterminada actualizada correctamente.');
    }

    public function destroy(PresupuestoNota $presupuestoNota)
    {
        $presupuestoNota->delete();

        return redirect()
            ->route('ventas.notas.index')
            ->with('success', 'Nota predeterminada eliminada correctamente.');
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'titulo' => ['nullable', 'string', 'max:255'],
            'contenido' => ['required', 'string', 'max:8000'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activa' => ['nullable', 'boolean'],
        ]);

        $validated['titulo'] = trim((string) ($validated['titulo'] ?? '')) ?: null;
        $validated['contenido'] = trim((string) $validated['contenido']);
        $validated['activa'] = (bool) ($validated['activa'] ?? false);

        if (array_key_exists('orden', $validated) && $validated['orden'] !== null) {
            $validated['orden'] = (int) $validated['orden'];
        }

        return $validated;
    }
}
