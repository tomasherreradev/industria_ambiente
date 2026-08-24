<?php

namespace App\Http\Controllers;

use App\Models\InformeNota;
use Illuminate\Http\Request;

class InformeNotaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeInformeNotas();
        $q = trim((string) $request->input('q', ''));

        $notas = InformeNota::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($sub) use ($like) {
                    $sub->where('titulo', 'ILIKE', $like)
                        ->orWhere('contenido', 'ILIKE', $like);
                });
            })
            ->ordenadas()
            ->paginate(20)
            ->withQueryString();

        return view('informe-notas.index', compact('notas', 'q'));
    }

    public function store(Request $request)
    {
        $this->authorizeInformeNotas();

        $validated = $this->validatePayload($request);

        $maxOrden = (int) InformeNota::query()->max('orden');
        $validated['orden'] = $maxOrden + 1;

        InformeNota::create($validated);

        return redirect()
            ->route('informes.notas.index')
            ->with('success', 'Nota guardada correctamente.');
    }

    public function edit(InformeNota $informeNota)
    {
        $this->authorizeInformeNotas();

        return view('informe-notas.edit', ['nota' => $informeNota]);
    }

    public function update(Request $request, InformeNota $informeNota)
    {
        $this->authorizeInformeNotas();

        $validated = $this->validatePayload($request);
        $informeNota->update($validated);

        return redirect()
            ->route('informes.notas.index')
            ->with('success', 'Nota actualizada correctamente.');
    }

    public function destroy(InformeNota $informeNota)
    {
        $this->authorizeInformeNotas();

        $informeNota->delete();

        return redirect()
            ->route('informes.notas.index')
            ->with('success', 'Nota eliminada correctamente.');
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

    private function authorizeInformeNotas(): void
    {
        abort_unless(userCanEditInformeProtocoloPdf(), 403, 'No tiene permiso para gestionar notas de informes.');
    }
}
