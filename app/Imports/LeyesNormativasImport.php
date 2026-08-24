<?php

namespace App\Imports;

use App\Models\LeyNormativa;
use App\Models\Variable;
use App\Models\CotioItems;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Str;

class LeyesNormativasImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    protected $errors = [];
    protected $successCount = 0;
    protected $errorCount = 0;
    protected $leyesCreadas = 0;
    protected $variablesAsociadas = 0;
    protected $variablesActualizadas = 0;

    /** Activar para ver logs detallados en storage/logs/laravel.log */
    protected $debug = true;

    /** Si se llegó a procesar alguna fila */
    public $sheetProcessed = false;

    /** Mapa normalizeTexto(nombre) => id para resolver leyes sin duplicar */
    protected array $leyNormativaNombreIndex = [];

    /** Cache de matrices: trim(codigo) => codigo y normalizeTexto(descripcion) => codigo */
    protected array $matrizCache = [];

    /** Cache de métodos: trim(codigo) => codigo y normalizeTexto(descripcion) => codigo */
    protected array $metodoCache = [];

    /** Cache de cotio_items: normalizeTexto(cotio_descripcion) => array of CotioItems */
    protected array $cotioItemsCache = [];

    /** Cache de variables: cotio_item_id => Variable */
    protected array $variableCache = [];

    /**
     * Normalización canónica de texto para comparaciones:
     * - trim, colapsa múltiples espacios a uno
     * - minúsculas
     * - reemplaza vocales acentuadas y ñ por su equivalente ASCII
     */
    protected function normalizeTexto(string $texto): string
    {
        $texto = trim($texto);
        $texto = preg_replace('/\s+/u', ' ', $texto);
        $texto = mb_strtolower($texto, 'UTF-8');

        $mapa = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ];

        return strtr($texto, $mapa);
    }

    /**
     * Expresión SQL para normalizar una columna de texto de la misma forma que normalizeTexto():
     * - colapsa espacios, lowercase, y si la extensión unaccent está disponible, quita tildes.
     * Usamos una función auxiliar portable: regexp_replace + lower + btrim.
     */
    protected function sqlNormalizeCol(string $col): string
    {
        // regexp_replace colapsa espacios internos múltiples
        return "regexp_replace(lower(btrim({$col})), '\\s+', ' ', 'g')";
    }

    protected function debug(string $message, array $context = []): void
    {
        if ($this->debug) {
            Log::debug('[LeyesNormativasImport] ' . $message, $context);
        }
    }

    public function collection(Collection $rows)
    {
        $this->sheetProcessed = true;
        Log::info('LeyesNormativasImport: Iniciando procesamiento', ['total_filas' => $rows->count()]);
        $this->debug('Cabeceras detectadas', [
            'keys' => $rows->isNotEmpty() ? array_keys($rows->first()->toArray()) : []
        ]);

        if ($rows->isEmpty()) {
            Log::warning('LeyesNormativasImport: No se encontraron filas para procesar');
            $this->errors[] = 'No se encontraron filas para procesar';
            return;
        }

        DB::beginTransaction();

        try {
            $this->buildLeyNormativaNombreIndex();
            $this->buildMatrizAndMetodoCache();
            $this->buildCotioItemsCache();
            $this->buildVariableCache();

            $leyesData = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

                $this->debug("Procesando fila {$rowNumber}", ['row_raw' => $row->toArray()]);

                $analito    = $this->getRowValue($row, ['analito_cotio_descripcion', 'analito', 'cotio_descripcion']);
                $matriz     = $this->getRowValue($row, ['matriz_opcional', 'matriz']);
                $metodo     = $this->getRowValue($row, ['metodo_opcional', 'metodo']);
                $nombreLey  = $this->getRowValue($row, ['nombre_de_la_ley', 'nombre_ley', 'nombre']);
                $unidadMedida = $this->getRowValue($row, ['unidad_de_medida', 'unidad_medida', 'unidad']);
                $valorLimite  = $this->getRowValue($row, ['valor_límite', 'valor_limite', 'valor']);

                $this->debug("Fila {$rowNumber} - Valores extraídos", compact(
                    'analito', 'matriz', 'metodo', 'nombreLey', 'unidadMedida', 'valorLimite'
                ));

                if (empty($analito)) {
                    $this->errors[] = "Fila {$rowNumber}: El analito (cotio_descripcion) es requerido";
                    $this->errorCount++;
                    continue;
                }

                if (empty($nombreLey)) {
                    $this->errors[] = "Fila {$rowNumber}: El nombre de la ley es requerido";
                    $this->errorCount++;
                    continue;
                }

                $leyNormKey = $this->normalizeTexto($nombreLey);
                $aplicarATodosBool = empty($matriz) && empty($metodo);

                if (!isset($leyesData[$leyNormKey])) {
                    $leyesData[$leyNormKey] = [];
                }

                $leyesData[$leyNormKey][] = [
                    'row_number'    => $rowNumber,
                    'nombre_ley'    => trim($nombreLey),
                    'analito'       => trim($analito),
                    'aplicar_a_todos' => $aplicarATodosBool,
                    'matriz'        => !empty($matriz) ? trim($matriz) : null,
                    'metodo'        => !empty($metodo) ? trim($metodo) : null,
                    'unidad_medida' => !empty($unidadMedida) ? trim($unidadMedida) : null,
                    'valor_limite'  => !empty($valorLimite) ? trim($valorLimite) : null,
                ];
            }

            $this->debug('Agrupación por ley', [
                'leyes' => array_keys($leyesData),
                'total_variables_por_ley' => array_map('count', $leyesData),
            ]);

            foreach ($leyesData as $leyNormKey => $variablesData) {
                $nombreLey = trim((string)($variablesData[0]['nombre_ley'] ?? ''));
                $this->debug("Procesando ley '{$leyNormKey}'", [
                    'nombre_representativo' => $nombreLey,
                    'variables_count' => count($variablesData),
                ]);

                try {
                    $leyNormativa = $this->findOrCreateLeyNormativa($nombreLey);

                    if (!$leyNormativa) {
                        $this->errors[] = "No se pudo crear o encontrar la ley: {$nombreLey}";
                        $this->errorCount++;
                        continue;
                    }

                    $this->debug("Ley usada: id={$leyNormativa->id} codigo={$leyNormativa->codigo}");

                    foreach ($variablesData as $varData) {
                        try {
                            $this->processVariable($leyNormativa, $varData);
                        } catch (\Exception $e) {
                            $this->errors[] = "Fila {$varData['row_number']}: " . $e->getMessage();
                            $this->errorCount++;
                            Log::error('LeyesNormativasImport: Error procesando variable', [
                                'fila'    => $varData['row_number'],
                                'varData' => $varData,
                                'error'   => $e->getMessage(),
                            ]);
                        }
                    }

                    $this->successCount++;
                } catch (\Exception $e) {
                    $this->errors[] = "Error procesando ley '{$nombreLey}': " . $e->getMessage();
                    $this->errorCount++;
                    Log::error('LeyesNormativasImport: Error procesando ley', [
                        'ley'   => $nombreLey,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            Log::info('LeyesNormativasImport: Procesamiento completado', [
                'leyes_creadas'         => $this->leyesCreadas,
                'variables_asociadas'   => $this->variablesAsociadas,
                'variables_actualizadas' => $this->variablesActualizadas,
                'successCount'          => $this->successCount,
                'errorCount'            => $this->errorCount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('LeyesNormativasImport: Error general', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->errors[] = 'Error general: ' . $e->getMessage();
            throw $e;
        }
    }

    /**
     * Precarga índice nombre normalizado → id.
     * Aplica la misma normalizeTexto() que se usa al agrupar filas.
     */
    protected function buildLeyNormativaNombreIndex(): void
    {
        $this->leyNormativaNombreIndex = [];

        foreach (LeyNormativa::query()->get(['id', 'nombre']) as $ley) {
            $key = $this->normalizeTexto((string)$ley->nombre);
            if ($key === '') {
                continue;
            }
            $this->leyNormativaNombreIndex[$key] ??= (int)$ley->id;
        }
    }

    protected function buildMatrizAndMetodoCache(): void
    {
        $this->matrizCache = [];
        foreach (DB::table('matriz')->get(['matriz_codigo', 'matriz_descripcion']) as $row) {
            $this->matrizCache[trim((string)$row->matriz_codigo)] = trim((string)$row->matriz_codigo);
            $keyDesc = $this->normalizeTexto((string)$row->matriz_descripcion);
            if ($keyDesc !== '') {
                $this->matrizCache[$keyDesc] = trim((string)$row->matriz_codigo);
            }
        }

        $this->metodoCache = [];
        foreach (DB::table('metodo')->get(['metodo_codigo', 'metodo_descripcion']) as $row) {
            $this->metodoCache[trim((string)$row->metodo_codigo)] = trim((string)$row->metodo_codigo);
            $keyDesc = $this->normalizeTexto((string)$row->metodo_descripcion);
            if ($keyDesc !== '') {
                $this->metodoCache[$keyDesc] = trim((string)$row->metodo_codigo);
            }
        }
    }

    protected function buildCotioItemsCache(): void
    {
        $this->cotioItemsCache = [];
        
        $items = CotioItems::where('es_muestra', false)
            ->orWhereNull('es_muestra')
            ->get();

        foreach ($items as $item) {
            $descNorm = $this->normalizeTexto((string)$item->cotio_descripcion);
            if ($descNorm === '') {
                continue;
            }
            $this->cotioItemsCache[$descNorm][] = $item;
        }
    }

    protected function buildVariableCache(): void
    {
        $this->variableCache = [];
        foreach (Variable::all() as $variable) {
            if ($variable->cotio_item_id) {
                $this->variableCache[(int)$variable->cotio_item_id] = $variable;
            }
        }
    }

    /**
     * Buscar ley existente por nombre normalizado o crear una nueva.
     */
    protected function findOrCreateLeyNormativa(string $nombreLey): ?LeyNormativa
    {
        $nombreTrim = trim($nombreLey);
        if ($nombreTrim === '') {
            return null;
        }

        $key = $this->normalizeTexto($nombreTrim);

        // 1. Buscar en caché in-memory
        if (isset($this->leyNormativaNombreIndex[$key])) {
            $ley = LeyNormativa::with('variables')->find($this->leyNormativaNombreIndex[$key]);
            if ($ley) {
                return $ley;
            }
        }

        // 2. Buscar en DB normalizando la columna igual que en PHP
        $normalizedCol = $this->sqlNormalizeCol('nombre');
        $ley = LeyNormativa::with('variables')->whereRaw("{$normalizedCol} = ?", [$key])->first();

        if ($ley) {
            $this->leyNormativaNombreIndex[$key] = (int)$ley->id;
            return $ley;
        }

        // 3. No existe → crear
        $codigo = $this->generateCodigoLey($nombreTrim);

        $ley = LeyNormativa::create([
            'codigo' => $codigo,
            'nombre' => $nombreTrim,
            'grupo'  => null,
            'activo' => true,
        ]);

        $ley->setRelation('variables', collect());

        $this->leyNormativaNombreIndex[$key] = (int)$ley->id;
        $this->leyesCreadas++;
        Log::info("LeyesNormativasImport: Ley creada: {$codigo} - {$nombreTrim}");

        return $ley;
    }

    /**
     * Generar código único para la ley
     */
    protected function generateCodigoLey(string $nombreLey): string
    {
        $baseCodigo = strtoupper(Str::slug(Str::limit($nombreLey, 20, ''), ''));

        $codigo  = $baseCodigo;
        $counter = 1;

        while (LeyNormativa::where('codigo', $codigo)->exists()) {
            $codigo = $baseCodigo . '-' . $counter;
            $counter++;
        }

        return $codigo;
    }

    /**
     * Procesar variable (analito) y asociarla a la ley.
     */
    protected function processVariable($leyNormativa, $varData): void
    {
        $analito      = $varData['analito'];
        $aplicarATodos = $varData['aplicar_a_todos'];
        $matriz       = $varData['matriz'];
        $metodo       = $varData['metodo'];
        $unidadMedida = $varData['unidad_medida'];
        $valorLimite  = $varData['valor_limite'];

        $analitoNorm = $this->normalizeTexto($analito);

        $this->debug("processVariable: analito='{$analito}' (norm='{$analitoNorm}') aplicar_a_todos=" . ($aplicarATodos ? '1' : '0'));

        $candidates = $this->cotioItemsCache[$analitoNorm] ?? [];
        $cotioItems = collect();

        $matrizCodigo = null;
        if (!$aplicarATodos && $matriz) {
            $matrizCodigo = $this->findMatrizCode($matriz) ?? trim($matriz);
        }

        $metodoCodigo = null;
        if (!$aplicarATodos && $metodo) {
            $metodoCodigo = $this->findMetodoCode($metodo) ?? trim($metodo);
        }

        foreach ($candidates as $item) {
            // Filtrar por matriz si se solicita
            if (!$aplicarATodos && $matriz) {
                $itemMatriz = trim((string)$item->matriz_codigo);
                if ($itemMatriz !== trim((string)$matrizCodigo)) {
                    continue;
                }
            }

            // Filtrar por método si se solicita
            if (!$aplicarATodos && $metodo) {
                $itemMetodo = trim((string)$item->metodo);
                if ($itemMetodo !== trim((string)$metodoCodigo)) {
                    continue;
                }
            }

            $cotioItems->push($item);
        }

        if ($cotioItems->isEmpty()) {
            $filtros = [];
            if ($matriz) $filtros[] = "matriz: {$matriz}";
            if ($metodo) $filtros[] = "método: {$metodo}";
            $filtrosStr = !empty($filtros) ? ' (' . implode(', ', $filtros) . ')' : '';
            $this->debug("Cero cotio_items para analito '{$analito}'{$filtrosStr}");
            throw new \Exception("No se encontraron cotio_items con descripción '{$analito}'{$filtrosStr}");
        }

        Log::info("LeyesNormativasImport: Encontrados {$cotioItems->count()} cotio_items para '{$analito}'");
        $this->debug("CotioItems encontrados: " . $cotioItems->count(), [
            'ids'          => $cotioItems->pluck('id')->toArray(),
            'descripciones' => $cotioItems->pluck('cotio_descripcion')->toArray(),
        ]);

        foreach ($cotioItems as $cotioItem) {
            // Buscar o crear Variable ligada al cotio_item
            $variable = $this->variableCache[(int)$cotioItem->id] ?? null;

            if (!$variable) {
                $variable = Variable::create([
                    'codigo'         => (string)$cotioItem->id,
                    'nombre'         => $cotioItem->cotio_descripcion,
                    'descripcion'    => $cotioItem->cotio_descripcion,
                    'unidad_medicion' => $unidadMedida ?? $cotioItem->unidad_medida,
                    'cotio_item_id'  => $cotioItem->id,
                    'activo'         => true,
                ]);
                $this->variableCache[(int)$cotioItem->id] = $variable;
                $this->debug("Variable creada: id={$variable->id} cotio_item_id={$cotioItem->id}");
            } else {
                $this->debug("Variable existente: id={$variable->id} cotio_item_id={$cotioItem->id}");
            }

            // Verificar si la asociación ley ↔ variable ya existe
            $existeAsociacion = $leyNormativa->variables->contains('id', $variable->id);

            $pivotData = [
                'valor_limite' => $valorLimite,
                'unidad_medida' => $unidadMedida ?? $cotioItem->unidad_medida,
            ];

            if (!$existeAsociacion) {
                $leyNormativa->variables()->attach($variable->id, $pivotData);
                $leyNormativa->variables->push($variable);
                $this->variablesAsociadas++;
                $this->debug("Variable {$variable->id} ASOCIADA a ley {$leyNormativa->codigo}");
            } else {
                $leyNormativa->variables()->updateExistingPivot($variable->id, $pivotData);
                $this->variablesActualizadas++;
                $this->debug("Variable {$variable->id} ACTUALIZADA en ley {$leyNormativa->codigo}");
            }
        }
    }

    /**
     * Buscar código de matriz (por código o nombre normalizado)
     */
    protected function findMatrizCode(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // 1. Exact match by code
        if (isset($this->matrizCache[$value])) {
            return $this->matrizCache[$value];
        }

        // 2. Try padded numeric variations
        if (is_numeric($value)) {
            $numero = (int)$value;
            for ($length = strlen($value); $length <= 10; $length++) {
                $codigoPadded = str_pad($numero, $length, '0', STR_PAD_LEFT);
                if (isset($this->matrizCache[$codigoPadded])) {
                    return $this->matrizCache[$codigoPadded];
                }
            }
        }

        // 3. Normalized match
        $valueNorm = $this->normalizeTexto($value);
        if (isset($this->matrizCache[$valueNorm])) {
            return $this->matrizCache[$valueNorm];
        }

        return null;
    }

    /**
     * Buscar código de método (por código o nombre normalizado)
     */
    protected function findMetodoCode(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // 1. Exact match by code
        if (isset($this->metodoCache[$value])) {
            return $this->metodoCache[$value];
        }

        // 2. Try padded numeric variations
        if (is_numeric($value)) {
            $numero = (int)$value;
            for ($length = strlen($value); $length <= 10; $length++) {
                $codigoPadded = str_pad($numero, $length, '0', STR_PAD_LEFT);
                if (isset($this->metodoCache[$codigoPadded])) {
                    return $this->metodoCache[$codigoPadded];
                }
            }
        }

        // 3. Normalized match
        $valueNorm = $this->normalizeTexto($value);
        if (isset($this->metodoCache[$valueNorm])) {
            return $this->metodoCache[$valueNorm];
        }

        return null;
    }

    /**
     * Obtener valor de fila probando múltiples nombres de columna posibles
     */
    protected function getRowValue($row, array $possibleKeys): string
    {
        foreach ($possibleKeys as $key) {
            $variations = [
                $key,
                Str::slug($key, '_'),
                Str::slug($key, '-'),
                str_replace('_', ' ', $key),
                str_replace('-', ' ', $key),
            ];

            foreach ($variations as $variation) {
                if (isset($row[$variation]) && $row[$variation] !== '' && $row[$variation] !== null) {
                    return trim((string)$row[$variation]);
                }
            }
        }

        return '';
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function getLeyesCreadas(): int
    {
        return $this->leyesCreadas;
    }

    public function getVariablesAsociadas(): int
    {
        return $this->variablesAsociadas;
    }

    public function getVariablesActualizadas(): int
    {
        return $this->variablesActualizadas;
    }

    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }
}
