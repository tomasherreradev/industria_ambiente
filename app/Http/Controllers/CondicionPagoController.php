<?php

namespace App\Http\Controllers;

use App\Models\CondicionPago;
use Illuminate\Http\Request;

class CondicionPagoController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        $condiciones = CondicionPago::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('pag_codigo', 'ILIKE', "%{$q}%")
                    ->orWhere('pag_descripcion', 'ILIKE', "%{$q}%");
            })
            ->orderBy('pag_codigo')
            ->paginate(20)
            ->withQueryString();

        return view('condiciones-pago.index', compact('condiciones', 'q'));
    }

    public function create()
    {
        return view('condiciones-pago.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request, true);

        $condicion = new CondicionPago();
        $condicion->fill($validated);
        $condicion->save();

        return redirect()->route('condiciones-pago.index')->with('success', 'Condición de pago creada correctamente.');
    }

    public function edit(CondicionPago $condicionPago)
    {
        return view('condiciones-pago.edit', ['condicion' => $condicionPago]);
    }

    public function update(Request $request, CondicionPago $condicionPago)
    {
        $validated = $this->validatePayload($request, false, $condicionPago->pag_codigo);

        // Si cambian el código, se guarda como PK nuevo (legacy).
        // En Eloquent esto implica crear uno nuevo y eliminar el anterior.
        $nuevoCodigo = $validated['pag_codigo'];
        $codigoActual = (string) $condicionPago->pag_codigo;

        if ($nuevoCodigo !== $codigoActual) {
            $nuevo = new CondicionPago();
            $nuevo->fill($validated);
            $nuevo->save();
            $condicionPago->delete();

            return redirect()->route('condiciones-pago.index')->with('success', 'Condición de pago actualizada correctamente.');
        }

        $condicionPago->fill($validated);
        $condicionPago->save();

        return redirect()->route('condiciones-pago.index')->with('success', 'Condición de pago actualizada correctamente.');
    }

    public function destroy(CondicionPago $condicionPago)
    {
        $condicionPago->delete();
        return redirect()->route('condiciones-pago.index')->with('success', 'Condición de pago eliminada correctamente.');
    }

    private function validatePayload(Request $request, bool $isCreate, ?string $currentCodigo = null): array
    {
        $rules = [
            'pag_codigo' => ['required', 'string', 'max:50'],
            'pag_descripcion' => ['nullable', 'string', 'max:255'],
            'pag_descuento1' => ['nullable', 'numeric', 'min:0'],
            'pag_descuento2' => ['nullable', 'numeric', 'min:0'],
            'pag_interes' => ['nullable', 'numeric'],
            'pag_cuotas' => ['nullable', 'integer', 'min:0'],
            'pag_dias' => ['nullable', 'integer', 'min:0'],
            'pag_vencimiento' => ['nullable', 'boolean'],
            'pag_anticipo' => ['nullable', 'boolean'],
            'pag_clienteproveedor' => ['nullable', 'string', 'max:50'],
            'pag_estado' => ['nullable', 'boolean'],
        ];

        $validated = $request->validate($rules);

        $codigo = trim((string) ($validated['pag_codigo'] ?? ''));
        $validated['pag_codigo'] = $codigo;

        if ($codigo === '') {
            // Laravel ya lo valida como required, pero lo dejamos consistente.
            $validated['pag_codigo'] = '';
        }

        $existsQuery = CondicionPago::query()->where('pag_codigo', $codigo);
        if (!$isCreate && $currentCodigo !== null) {
            $existsQuery->where('pag_codigo', '!=', $currentCodigo);
        }

        if ($codigo !== '' && $existsQuery->exists()) {
            $field = 'pag_codigo';
            $message = 'El código ya existe.';
            $request->validate([$field => ['unique:pag,pag_codigo']]); // disparar formato estándar
        }

        // Checkboxes
        $validated['pag_vencimiento'] = (bool) ($validated['pag_vencimiento'] ?? false);
        $validated['pag_anticipo'] = (bool) ($validated['pag_anticipo'] ?? false);
        $validated['pag_estado'] = (bool) ($validated['pag_estado'] ?? false);

        // Normalizar numéricos (guardar null si vacío)
        foreach (['pag_descuento1', 'pag_descuento2', 'pag_interes'] as $k) {
            $validated[$k] = array_key_exists($k, $validated) && $validated[$k] !== null && $validated[$k] !== ''
                ? round((float) $validated[$k], 2)
                : null;
        }
        foreach (['pag_cuotas', 'pag_dias'] as $k) {
            $validated[$k] = array_key_exists($k, $validated) && $validated[$k] !== null && $validated[$k] !== ''
                ? (int) $validated[$k]
                : null;
        }

        return $validated;
    }
}

