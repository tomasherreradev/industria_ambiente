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

    /**
     * Procesa la colección de filas del Excel
     * 
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        Log::info('LeyesNormativasImport: Iniciando procesamiento', ['total_filas' => $rows->count()]);
        
        if ($rows->isEmpty()) {
            Log::warning('LeyesNormativasImport: No se encontraron filas para procesar');
            $this->errors[] = 'No se encontraron filas para procesar';
            return;
        }

        DB::beginTransaction();
        
        try {
            // Agrupar filas por nombre de ley
            $leyesData = [];
            
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 porque el índice empieza en 0 y hay encabezado
                
                // Obtener valores de la fila
                $analito = $this->getRowValue($row, ['analito_cotio_descripcion', 'analito', 'cotio_descripcion']);
                $matriz = $this->getRowValue($row, ['matriz_opcional', 'matriz']);
                $metodo = $this->getRowValue($row, ['metodo_opcional', 'metodo']);
                $nombreLey = $this->getRowValue($row, ['nombre_de_la_ley', 'nombre_ley', 'nombre']);
                $unidadMedida = $this->getRowValue($row, ['unidad_de_medida', 'unidad_medida', 'unidad']);
                $valorLimite = $this->getRowValue($row, ['valor_límite', 'valor_limite', 'valor']);
                
                // Validar campos requeridos
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
                
                // Si no se especifica matriz ni método, aplicar a todos
                $aplicarATodosBool = empty($matriz) && empty($metodo);
                
                // Agrupar por nombre de ley
                if (!isset($leyesData[$nombreLey])) {
                    $leyesData[$nombreLey] = [];
                }
                
                $leyesData[$nombreLey][] = [
                    'row_number' => $rowNumber,
                    'analito' => trim($analito),
                    'aplicar_a_todos' => $aplicarATodosBool,
                    'matriz' => !empty($matriz) ? trim($matriz) : null,
                    'metodo' => !empty($metodo) ? trim($metodo) : null,
                    'unidad_medida' => !empty($unidadMedida) ? trim($unidadMedida) : null,
                    'valor_limite' => !empty($valorLimite) ? trim($valorLimite) : null,
                ];
            }
            
            // Procesar cada ley
            foreach ($leyesData as $nombreLey => $variablesData) {
                try {
                    // Buscar o crear la ley normativa
                    $leyNormativa = $this->findOrCreateLeyNormativa($nombreLey);
                    
                    if (!$leyNormativa) {
                        $this->errors[] = "No se pudo crear o encontrar la ley: {$nombreLey}";
                        $this->errorCount++;
                        continue;
                    }
                    
                    // Procesar cada variable de esta ley
                    foreach ($variablesData as $varData) {
                        try {
                            $this->processVariable($leyNormativa, $varData);
                        } catch (\Exception $e) {
                            $this->errors[] = "Fila {$varData['row_number']}: " . $e->getMessage();
                            $this->errorCount++;
                            Log::error('LeyesNormativasImport: Error procesando variable', [
                                'fila' => $varData['row_number'],
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                    
                    $this->successCount++;
                } catch (\Exception $e) {
                    $this->errors[] = "Error procesando ley '{$nombreLey}': " . $e->getMessage();
                    $this->errorCount++;
                    Log::error('LeyesNormativasImport: Error procesando ley', [
                        'ley' => $nombreLey,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            DB::commit();
            
            Log::info('LeyesNormativasImport: Procesamiento completado', [
                'leyes_creadas' => $this->leyesCreadas,
                'variables_asociadas' => $this->variablesAsociadas,
                'successCount' => $this->successCount,
                'errorCount' => $this->errorCount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('LeyesNormativasImport: Error general', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->errors[] = 'Error general: ' . $e->getMessage();
            throw $e;
        }
    }

    /**
     * Buscar o crear ley normativa
     */
    protected function findOrCreateLeyNormativa($nombreLey)
    {
        // Buscar por nombre exacto
        $ley = LeyNormativa::where('nombre', $nombreLey)->first();
        
        if ($ley) {
            return $ley;
        }
        
        // Generar código único para la nueva ley
        $codigo = $this->generateCodigoLey($nombreLey);
        
        // Crear nueva ley normativa
        $ley = LeyNormativa::create([
            'codigo' => $codigo,
            'nombre' => $nombreLey,
            'grupo' => null, // Se puede completar manualmente después
            'activo' => true
        ]);
        
        $this->leyesCreadas++;
        Log::info("LeyesNormativasImport: Ley creada: {$codigo} - {$nombreLey}");
        
        return $ley;
    }

    /**
     * Generar código único para la ley
     */
    protected function generateCodigoLey($nombreLey)
    {
        // Intentar generar un código basado en el nombre
        $baseCodigo = Str::slug(Str::limit($nombreLey, 20, ''), '');
        $baseCodigo = strtoupper($baseCodigo);
        
        // Verificar si ya existe
        $codigo = $baseCodigo;
        $counter = 1;
        
        while (LeyNormativa::where('codigo', $codigo)->exists()) {
            $codigo = $baseCodigo . '-' . $counter;
            $counter++;
        }
        
        return $codigo;
    }

    /**
     * Procesar variable (analito) y asociarla a la ley
     */
    protected function processVariable($leyNormativa, $varData)
    {
        $analito = $varData['analito'];
        $aplicarATodos = $varData['aplicar_a_todos'];
        $matriz = $varData['matriz'];
        $metodo = $varData['metodo'];
        $unidadMedida = $varData['unidad_medida'];
        $valorLimite = $varData['valor_limite'];
        
        // Buscar cotio_items que coincidan (case-insensitive, con trim)
        $analitoTrimmed = trim($analito);
        $query = CotioItems::where(DB::raw('TRIM(cotio_descripcion)'), 'ILIKE', $analitoTrimmed)
                           ->where('es_muestra', false); // Solo componentes, no agrupadores
        
        // Si no es "aplicar a todos", filtrar por matriz y/o método
        if (!$aplicarATodos) {
            if ($matriz) {
                // Buscar matriz normalizada en la base de datos
                $matrizCodigo = $this->findMatrizCode($matriz);
                if ($matrizCodigo) {
                    $query->where('matriz_codigo', $matrizCodigo);
                } else {
                    // Si no se encuentra, intentar con el valor original
                    $query->where('matriz_codigo', trim($matriz));
                }
            }
            
            if ($metodo) {
                // Buscar método normalizado en la base de datos
                $metodoCodigo = $this->findMetodoCode($metodo);
                if ($metodoCodigo) {
                    $query->where('metodo', $metodoCodigo);
                } else {
                    // Si no se encuentra, intentar con el valor original
                    $query->where('metodo', trim($metodo));
                }
            }
        }
        
        $cotioItems = $query->get();
        
        if ($cotioItems->isEmpty()) {
            $filtros = [];
            if ($matriz) $filtros[] = "matriz: {$matriz}";
            if ($metodo) $filtros[] = "método: {$metodo}";
            $filtrosStr = !empty($filtros) ? ' (' . implode(', ', $filtros) . ')' : '';
            
            throw new \Exception("No se encontraron cotio_items con descripción '{$analito}'{$filtrosStr}");
        }
        
        Log::info("LeyesNormativasImport: Encontrados " . $cotioItems->count() . " cotio_items para '{$analito}'");
        
        // Para cada cotio_item encontrado, crear o actualizar variable y asociarla a la ley
        foreach ($cotioItems as $cotioItem) {
            // Buscar o crear variable basada en el cotio_item
            $variable = Variable::where('cotio_item_id', $cotioItem->id)->first();
            
            if (!$variable) {
                // Crear nueva variable
                $variable = Variable::create([
                    'codigo' => (string) $cotioItem->id,
                    'nombre' => $cotioItem->cotio_descripcion,
                    'descripcion' => $cotioItem->cotio_descripcion,
                    'unidad_medicion' => $unidadMedida ?? $cotioItem->unidad_medida,
                    'cotio_item_id' => $cotioItem->id,
                    'activo' => true
                ]);
                
                Log::debug("LeyesNormativasImport: Variable creada: ID {$variable->id} para cotio_item {$cotioItem->id}");
            }
            
            // Verificar si ya está asociada a esta ley
            $existeAsociacion = $leyNormativa->variables()
                ->where('variable_id', $variable->id)
                ->exists();
            
            if (!$existeAsociacion) {
                // Asociar variable a la ley normativa
                $leyNormativa->variables()->attach($variable->id, [
                    'valor_limite' => $valorLimite,
                    'unidad_medida' => $unidadMedida ?? $cotioItem->unidad_medida,
                ]);
                
                $this->variablesAsociadas++;
                Log::debug("LeyesNormativasImport: Variable {$variable->id} asociada a ley {$leyNormativa->codigo}");
            } else {
                // Actualizar valores si ya existe
                $leyNormativa->variables()->updateExistingPivot($variable->id, [
                    'valor_limite' => $valorLimite,
                    'unidad_medida' => $unidadMedida ?? $cotioItem->unidad_medida,
                ]);
                
                Log::debug("LeyesNormativasImport: Variable {$variable->id} actualizada en ley {$leyNormativa->codigo}");
            }
        }
    }

    /**
     * Buscar código de matriz en la base de datos (por código o nombre)
     */
    protected function findMatrizCode($value)
    {
        if (empty($value)) {
            return null;
        }
        
        $value = trim($value);
        
        // Primero buscar por código exacto
        $matriz = DB::table('matriz')->where('matriz_codigo', $value)->first();
        if ($matriz) {
            return $matriz->matriz_codigo;
        }
        
        // Si es numérico, intentar con diferentes paddings
        if (is_numeric($value)) {
            $numero = (int) $value;
            
            // Intentar con diferentes longitudes de padding (hasta 10 dígitos)
            for ($length = strlen($value); $length <= 10; $length++) {
                $codigoPadded = str_pad($numero, $length, '0', STR_PAD_LEFT);
                $matriz = DB::table('matriz')->where('matriz_codigo', $codigoPadded)->first();
                if ($matriz) {
                    return $matriz->matriz_codigo;
                }
            }
        }
        
        // Si no se encontró por código, buscar por nombre/descripción (case-insensitive)
        $matriz = DB::table('matriz')
            ->where('matriz_descripcion', 'ILIKE', $value)
            ->first();
        if ($matriz) {
            return $matriz->matriz_codigo;
        }
        
        return null;
    }

    /**
     * Buscar código de método en la base de datos (por código o nombre)
     */
    protected function findMetodoCode($value)
    {
        if (empty($value)) {
            return null;
        }
        
        $value = trim($value);
        
        // Primero buscar por código exacto
        $metodo = DB::table('metodo')->where('metodo_codigo', $value)->first();
        if ($metodo) {
            return $metodo->metodo_codigo;
        }
        
        // Si es numérico, intentar con diferentes paddings
        if (is_numeric($value)) {
            $numero = (int) $value;
            
            // Intentar con diferentes longitudes de padding (hasta 10 dígitos)
            for ($length = strlen($value); $length <= 10; $length++) {
                $codigoPadded = str_pad($numero, $length, '0', STR_PAD_LEFT);
                $metodo = DB::table('metodo')->where('metodo_codigo', $codigoPadded)->first();
                if ($metodo) {
                    return $metodo->metodo_codigo;
                }
            }
        }
        
        // Si no se encontró por código, buscar por nombre/descripción (case-insensitive)
        $metodo = DB::table('metodo')
            ->where('metodo_descripcion', 'ILIKE', $value)
            ->first();
        if ($metodo) {
            return $metodo->metodo_codigo;
        }
        
        return null;
    }

    /**
     * Obtener valor de fila probando múltiples nombres de columna
     */
    protected function getRowValue($row, array $possibleKeys)
    {
        foreach ($possibleKeys as $key) {
            // WithHeadingRow convierte a slug, probar diferentes variaciones
            $variations = [
                $key,
                Str::slug($key, '_'),
                Str::slug($key, '-'),
                str_replace('_', ' ', $key),
                str_replace('-', ' ', $key),
            ];
            
            foreach ($variations as $variation) {
                if (isset($row[$variation]) && !empty($row[$variation])) {
                    return trim((string) $row[$variation]);
                }
            }
        }
        return '';
    }

    /**
     * Obtener errores
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Obtener contador de éxitos
     */
    public function getSuccessCount()
    {
        return $this->successCount;
    }

    /**
     * Obtener contador de errores
     */
    public function getErrorCount()
    {
        return $this->errorCount;
    }

    /**
     * Obtener número de leyes creadas
     */
    public function getLeyesCreadas()
    {
        return $this->leyesCreadas;
    }

    /**
     * Obtener número de variables asociadas
     */
    public function getVariablesAsociadas()
    {
        return $this->variablesAsociadas;
    }
}

