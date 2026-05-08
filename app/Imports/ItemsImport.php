<?php

namespace App\Imports;

use App\Models\CotioItems;
use App\Models\Metodo;
use App\Models\Matriz;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class ItemsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    protected $errors = [];
    protected $successCount = 0;
    protected $errorCount = 0;
    protected $nextId = null;
    
    // Caches para evitar consultas repetidas
    protected $cacheMatrices = [];
    protected $cacheMetodos = [];
    protected $cacheAgrupadores = [];
    protected $cacheComponentes = [];

    // Seguimiento de matrices asociadas en esta importación para cada item
    protected $matricesPorItem = [];

    /** @var Collection<string, Matriz>|null Índice matriz_codigo (TRIM) → fila para resolver códigos con/sin padding */
    protected ?Collection $matrizLookupByTrim = null;

    /**
     * Normaliza un texto para comparaciones (trim, lowercase, remover espacios dobles, etc.)
     */
    protected function normalizeText($text): string
    {
        if ($text === null) return '';
        $text = (string)$text;
        // Quitar espacios extra y normalizar a minúsculas
        $text = mb_strtolower(trim($text), 'UTF-8');
        // Reemplazar múltiples espacios/tabulaciones por uno solo
        $text = preg_replace('/\s+/', ' ', $text);
        // Quitar espacios alrededor de paréntesis si existen
        $text = preg_replace('/\s*\(\s*/', '(', $text);
        $text = preg_replace('/\s*\)\s*/', ')', $text);
        return $text;
    }

    /**
     * Procesa la colección de filas del Excel
     * Nota: Solo procesa la primera hoja. Las hojas adicionales (como la lista de métodos) se ignoran automáticamente.
     * 
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        Log::info('ItemsImport: Iniciando procesamiento', ['total_filas' => $rows->count()]);
        
        if ($rows->isEmpty()) {
            Log::warning('ItemsImport: No se encontraron filas para procesar');
            return;
        }

        // Detectar formato: nuevo (Tipo, Agrupador, Parámetro) o antiguo
        $firstRow = $rows->first();
        $rowKeys = array_keys($firstRow->toArray());
        
        // Detectar formato nuevo: buscar columnas que indiquen el nuevo formato
        // WithHeadingRow convierte a slug: "Parámetro" -> "parametro", "Tipo" -> "tipo", etc.
        $hasTipo = $this->hasColumn($firstRow, ['tipo']);
        $hasAgrupador = $this->hasColumn($firstRow, ['agrupador']);
        $hasParametro = $this->hasColumn($firstRow, ['parámetro', 'parametro']);
        
        $isNewFormat = $hasTipo || $hasAgrupador || $hasParametro;
        
        Log::info('ItemsImport: Detección de formato', [
            'total_filas' => $rows->count(),
            'columnas_encontradas' => $rowKeys,
            'has_tipo' => $hasTipo,
            'has_agrupador' => $hasAgrupador,
            'has_parametro' => $hasParametro,
            'is_new_format' => $isNewFormat
        ]);
        
        if ($isNewFormat) {
            Log::info('ItemsImport: Detectado formato nuevo (Tipo, Agrupador, Parámetro)');
            $this->processNewFormat($rows);
        } else {
            Log::info('ItemsImport: Detectado formato antiguo, procesando con lógica legacy');
            $this->processOldFormat($rows);
        }
    }

    /**
     * Procesar formato nuevo: Tipo, Agrupador, Parámetro
     */
    protected function processNewFormat(Collection $rows)
    {
        // Obtener el último ID para generar IDs secuenciales
        $ultimoItem = CotioItems::orderBy('id', 'desc')->first();
        $this->nextId = $ultimoItem ? $ultimoItem->id + 1 : 1;
        Log::info('ItemsImport: Siguiente ID a usar', ['next_id' => $this->nextId]);

        $filasProcesadas = 0;
        $filasSaltadas = 0;

        foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 porque el índice empieza en 0 y hay encabezado
                
                // Verificar si esta fila tiene las columnas esperadas (usar getRowValue para flexibilidad)
                $parametroCheck = $this->getRowValue($row, ['parámetro', 'parametro']);
                
                // También verificar si la fila está completamente vacía
                $rowArray = $row->toArray();
                $rowHasData = false;
                foreach ($rowArray as $value) {
                    if (!empty(trim((string) $value))) {
                        $rowHasData = true;
                        break;
                    }
                }
                
                if (empty($parametroCheck)) {
                    if ($rowHasData) {
                        Log::debug('ItemsImport: Saltando fila - no tiene parámetro pero tiene otros datos', [
                            'fila' => $rowNumber,
                            'columnas' => array_keys($rowArray),
                            'valores' => $rowArray
                        ]);
                    }
                    $filasSaltadas++;
                    continue;
                }
                
                $filasProcesadas++;
                
                Log::debug('ItemsImport: Procesando fila', ['fila' => $rowNumber, 'datos' => $row->toArray()]);
                
                try {
                    // Leer datos del nuevo formato (manejar espacios y guiones bajos)
                    $tipoNombre = $this->getRowValue($row, ['tipo']);
                    $agrupadorNombre = $this->getRowValue($row, ['agrupador']);
                    $parametroNombre = $this->getRowValue($row, ['parámetro', 'parametro']);
                    
                    if (empty($parametroNombre)) {
                        $this->errors[] = "Fila {$rowNumber}: El parámetro es requerido";
                        $this->errorCount++;
                        continue;
                    }
                    
                    // 1. Buscar/crear Matriz (Tipo)
                    $matrizCodigo = null;
                    if (!empty($tipoNombre)) {
                        $matrizCodigo = $this->findOrCreateMatriz($tipoNombre, $rowNumber);
                        if (!$matrizCodigo) {
                            continue; // Error ya registrado
                        }
                    }
                    
                    // 2. Agrupadores: permitir varios en la misma celda separados por punto y coma o coma
                    $agrupadorIds = [];
                    if (!empty($agrupadorNombre)) {
                        $nombresAgrupadores = array_filter(array_map('trim', preg_split('/[;,]/', (string) $agrupadorNombre)));
                        foreach ($nombresAgrupadores as $nombre) {
                            if ($nombre === '') {
                                continue;
                            }
                            $agrupadorId = $this->findOrCreateAgrupador($nombre, $rowNumber);
                            if ($agrupadorId) {
                                $agrupadorIds[] = $agrupadorId;
                            }
                        }
                        if (!empty($nombresAgrupadores) && empty($agrupadorIds)) {
                            continue; // Todos fallaron, error ya registrado en findOrCreateAgrupador
                        }
                    }
                    
                    // 3. Buscar/crear Método de Muestreo
                    $metodoMuestreoCodigo = null;
                    $metodoMuestreoNombre = $this->getRowValue($row, [
                        'metodología_muestreo', 'metodología muestreo', 
                        'metodologia_muestreo', 'metodologia muestreo',
                        'metodología muestreo', 'metodologia_muestreo'
                    ]);
                    if (!empty($metodoMuestreoNombre)) {
                        $metodoMuestreoCodigo = $this->findOrCreateMetodo($metodoMuestreoNombre, $rowNumber);
                        if (!$metodoMuestreoCodigo) {
                            continue; // Error ya registrado
                        }
                    }
                    
                    // 4. Buscar/crear Método de Análisis
                    $metodoCodigo = null;
                    $metodoAnalisisNombre = $this->getRowValue($row, [
                        'metodología_análisis', 'metodología análisis',
                        'metodologia_analisis', 'metodologia analisis',
                        'metodología análisis', 'metodologia_analisis'
                    ]);
                    if (!empty($metodoAnalisisNombre)) {
                        $metodoCodigo = $this->findOrCreateMetodo($metodoAnalisisNombre, $rowNumber);
                        if (!$metodoCodigo) {
                            continue; // Error ya registrado
                        }
                    }
                    
                    // 5. Leer otros campos
                    $unidadMedida = $this->getRowValue($row, [
                        'unidades_de_medición', 'unidades de medición',
                        'unidades_de_medicion', 'unidades de medicion'
                    ]);
                    $limiteDeteccion = $this->parseNumeric($this->getRowValue($row, [
                        'límite_de_detección', 'límite de detección',
                        'limite_de_deteccion', 'limite de deteccion'
                    ]));
                    $limiteCuantificacion = $this->parseNumeric($this->getRowValue($row, [
                        'límite_de_cuantificación', 'límite de cuantificación',
                        'limite_de_cuantificacion', 'limite de cuantificacion'
                    ]));
                    $precio = $this->parseNumeric($this->getRowValue($row, [
                        'precio_de_venta', 'precio de venta'
                    ]));
                    
                    // Usar límite de detección como límite de cuantificación si no se especifica
                    if (!$limiteCuantificacion && $limiteDeteccion) {
                        $limiteCuantificacion = $limiteDeteccion;
                    }
                    
                    // 6. Guardar límite de detección en limites_establecidos
                    $limitesEstablecidos = null;
                    if ($limiteDeteccion) {
                        $limitesEstablecidos = (string) $limiteDeteccion;
                    }
                    
                    // 7. Buscar/crear Componente (Parámetro, es_muestra = false)
                    // Clave de equivalencia: parámetro + agrupador(es) + tipo (matriz) + metodología muestreo + metodología análisis
                    $componente = $this->findOrCreateComponente(
                        $parametroNombre,
                        $metodoCodigo,
                        $metodoMuestreoCodigo,
                        $matrizCodigo,
                        $agrupadorIds,
                        $unidadMedida,
                        $limitesEstablecidos,
                        $limiteCuantificacion,
                        $precio,
                        $rowNumber
                    );
                    
                    if (!$componente) {
                        continue; // Error ya registrado
                    }
                    
                    // 7.1. Asociar matriz al componente en tabla pivote si existe
                    if ($componente && $matrizCodigo) {
                        $this->asociarMatrizAItem($componente->id, $matrizCodigo);
                    }
                    
                    // 8. Asociar componente a cada agrupador (puede haber varios por fila, separados por ;)
                    foreach ($agrupadorIds as $agrupadorId) {
                        $agrupadorId = (int) $agrupadorId;
                        if ($agrupadorId <= 0 || ! $componente) {
                            continue;
                        }
                        if (! CotioItems::query()->whereKey($agrupadorId)->where('es_muestra', true)->exists()) {
                            continue;
                        }
                        $this->ensurePivotAgrupadorComponente($agrupadorId, (int) $componente->id);
                        Log::debug('ItemsImport: Vínculo agrupador-componente asegurado', [
                            'componente_id' => $componente->id,
                            'agrupador_id' => $agrupadorId,
                        ]);
                        if ($matrizCodigo) {
                            $this->asociarMatrizAItem($agrupadorId, $matrizCodigo);
                        }
                    }
                    
                    $this->successCount++;
                    Log::debug('ItemsImport: Fila procesada exitosamente', [
                        'fila' => $rowNumber,
                        'componente_id' => $componente->id
                    ]);
                } catch (\Throwable $e) {
                    $this->errors[] = "Fila {$rowNumber}: " . $e->getMessage();
                    $this->errorCount++;
                    Log::error('ItemsImport: Error en fila', [
                        'fila' => $rowNumber,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
        }

        // Sin transacción global: en PostgreSQL un fallo (p. ej. CAST inválido) abortaba toda la importación (25P02).
        $this->syncMatricesPivot();

        Log::info('ItemsImport: Procesamiento completado (formato nuevo)', [
            'successCount' => $this->successCount,
            'errorCount' => $this->errorCount,
            'filasProcesadas' => $filasProcesadas ?? 0,
            'filasSaltadas' => $filasSaltadas ?? 0
        ]);
    }

    /**
     * Procesar formato antiguo (compatibilidad)
     */
    protected function processOldFormat(Collection $rows)
    {
        // Obtener el último ID para generar IDs secuenciales
        $ultimoItem = CotioItems::orderBy('id', 'desc')->first();
        $this->nextId = $ultimoItem ? $ultimoItem->id + 1 : 1;
        Log::info('ItemsImport: Siguiente ID a usar', ['next_id' => $this->nextId]);

        foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;
                
                // Verificar si esta fila tiene las columnas esperadas
                $hasDescripcion = isset($row['descripcion']) || isset($row['determinacion']);
                if (!$hasDescripcion) {
                    continue;
                }
                
                try {
                    // Validar y limpiar datos
                    $descripcion = trim($row['descripcion'] ?? $row['determinacion'] ?? '');
                    if (empty($descripcion)) {
                        $this->errors[] = "Fila {$rowNumber}: La descripción es requerida";
                        $this->errorCount++;
                        continue;
                    }

                    // Determinar si es muestra (agrupador)
                    $esMuestra = false;
                    $esMuestraValue = '';
                    if (isset($row['es_muestra']) && !empty($row['es_muestra'])) {
                        $esMuestraValue = strtolower(trim((string) $row['es_muestra']));
                    } elseif (isset($row['tipo']) && !empty($row['tipo'])) {
                        $esMuestraValue = strtolower(trim((string) $row['tipo']));
                    }
                    
                    if (in_array($esMuestraValue, ['si', 'sí', 'yes', 'y', '1', 'true', 'agrupador', 'muestra'])) {
                        $esMuestra = true;
                    }

                    // Validar método si se proporciona - puede ser código o nombre
                    $metodoCodigo = null;
                    $metodoValue = $row['metodo'] ?? $row['metodo_codigo'] ?? null;
                    if (!empty($metodoValue)) {
                        // Convertir a string
                        $metodoValueStr = is_numeric($metodoValue) 
                            ? (string) (int) $metodoValue 
                            : trim((string) $metodoValue);
                        
                        if (!empty($metodoValueStr)) {
                            // Buscar el método - primero por código exacto
                            $metodo = Metodo::where('metodo_codigo', $metodoValueStr)->first();
                            
                            // Si no se encuentra y es numérico, intentar con diferentes formatos (con ceros a la izquierda)
                            if (!$metodo && is_numeric($metodoValueStr)) {
                                $numero = (int) $metodoValueStr;
                                
                                // Intentar con diferentes longitudes de padding (hasta 10 dígitos)
                                for ($length = strlen($metodoValueStr); $length <= 10; $length++) {
                                    $metodoCodigoPadded = str_pad($numero, $length, '0', STR_PAD_LEFT);
                                    $metodo = Metodo::where('metodo_codigo', $metodoCodigoPadded)->first();
                                    if ($metodo) {
                                        $metodoCodigo = $metodoCodigoPadded;
                                        break;
                                    }
                                }
                                
                                // Si aún no se encuentra, intentar sin ceros a la izquierda
                                if (!$metodo) {
                                    $metodo = Metodo::where('metodo_codigo', (string) $numero)->first();
                                    if ($metodo) {
                                        $metodoCodigo = (string) $numero;
                                    }
                                }
                            }
                            
                            // Si no se encontró por código, buscar por descripción/nombre
                            if (!$metodo) {
                                $metodo = Metodo::where('metodo_descripcion', 'ILIKE', $metodoValueStr)->first();
                                if ($metodo) {
                                    $metodoCodigo = $metodo->metodo_codigo;
                                }
                            }
                            
                            if (!$metodo) {
                                $this->errors[] = "Fila {$rowNumber}: El método '{$metodoValueStr}' no existe (buscado por código y descripción)";
                                $this->errorCount++;
                                continue;
                            } else {
                                // Asegurar que tenemos el código del método
                                if (!$metodoCodigo) {
                                    $metodoCodigo = $metodo->metodo_codigo;
                                }
                            }
                        }
                    }

                    // Obtener límites establecidos
                    $limitesEstablecidos = null;
                    if (isset($row['limites_establecidos']) && !empty($row['limites_establecidos'])) {
                        $limitesEstablecidos = is_numeric($row['limites_establecidos']) 
                            ? (string) $row['limites_establecidos'] 
                            : trim((string) $row['limites_establecidos']);
                    } elseif (isset($row['limites']) && !empty($row['limites'])) {
                        $limitesEstablecidos = is_numeric($row['limites']) 
                            ? (string) $row['limites'] 
                            : trim((string) $row['limites']);
                    }

                    // Obtener unidad de medida
                    $unidadMedida = null;
                    if (isset($row['unidad_medida']) && !empty($row['unidad_medida'])) {
                        $unidadMedida = trim((string) $row['unidad_medida']);
                    } elseif (isset($row['unidad']) && !empty($row['unidad'])) {
                        $unidadMedida = trim((string) $row['unidad']);
                    }

                    // Procesar matriz_codigo - restaurar ceros a la izquierda si es necesario
                    // NOTA: Ya no se guarda en matriz_codigo, solo se usa para asociar en tabla pivote
                    $matrizCodigo = null;
                    if (isset($row['matriz_codigo']) && !empty($row['matriz_codigo'])) {
                        $matrizValue = $row['matriz_codigo'];
                        
                        // Si es numérico, Excel lo convirtió a número (perdió los ceros)
                        if (is_numeric($matrizValue)) {
                            // Intentar restaurar con padding de 5 dígitos (formato estándar)
                            $matrizCodigo = $this->restorePaddedCode($matrizValue, 'matriz', 'matriz_codigo', 5);
                        } else {
                            // Ya viene como string, usar directamente
                            $matrizCodigo = trim((string) $matrizValue);
                        }
                        
                        if ($matrizCodigo) {
                            $matriz = $this->resolveMatrizFromCodigoOrDescripcion((string) $matrizCodigo);
                            if (! $matriz) {
                                $this->errors[] = "Fila {$rowNumber}: La matriz con código '{$matrizCodigo}' no existe";
                                $this->errorCount++;
                                continue;
                            }
                            $matrizCodigo = $matriz->matriz_codigo;
                        }
                    }

                    // Procesar metodo_muestreo - usa la misma tabla y formato que metodo
                    $metodoMuestreoCodigo = null;
                    if (isset($row['metodo_muestreo']) && !empty($row['metodo_muestreo'])) {
                        $metodoMuestreoValue = $row['metodo_muestreo'];
                        
                        // Convertir a string
                        $metodoMuestreoValueStr = is_numeric($metodoMuestreoValue) 
                            ? (string) (int) $metodoMuestreoValue 
                            : trim((string) $metodoMuestreoValue);
                        
                        if (!empty($metodoMuestreoValueStr)) {
                            // Si es numérico, Excel lo convirtió a número (perdió los ceros)
                            if (is_numeric($metodoMuestreoValueStr)) {
                                // Restaurar con padding de 5 dígitos (mismo formato que metodo)
                                $metodoMuestreoCodigo = $this->restorePaddedCode($metodoMuestreoValueStr, 'metodo', 'metodo_codigo', 5);
                            } else {
                                // Ya viene como string, usar directamente
                                $metodoMuestreoCodigo = $metodoMuestreoValueStr;
                            }
                            
                            // Buscar el método - primero por código exacto
                            $metodoMuestreo = Metodo::where('metodo_codigo', $metodoMuestreoCodigo)->first();
                            
                            // Si no se encuentra y es numérico, intentar con diferentes formatos (con ceros a la izquierda)
                            if (!$metodoMuestreo && is_numeric($metodoMuestreoValueStr)) {
                                $numero = (int) $metodoMuestreoValueStr;
                                
                                // Intentar con diferentes longitudes de padding (hasta 10 dígitos)
                                for ($length = strlen($metodoMuestreoValueStr); $length <= 10; $length++) {
                                    $metodoCodigoPadded = str_pad($numero, $length, '0', STR_PAD_LEFT);
                                    $metodoMuestreo = Metodo::where('metodo_codigo', $metodoCodigoPadded)->first();
                                    if ($metodoMuestreo) {
                                        $metodoMuestreoCodigo = $metodoCodigoPadded;
                                        break;
                                    }
                                }
                                
                                // Si aún no se encuentra, intentar sin ceros a la izquierda
                                if (!$metodoMuestreo) {
                                    $metodoMuestreo = Metodo::where('metodo_codigo', (string) $numero)->first();
                                    if ($metodoMuestreo) {
                                        $metodoMuestreoCodigo = (string) $numero;
                                    }
                                }
                            }
                            
                            // Si no se encontró por código, buscar por descripción/nombre
                            if (!$metodoMuestreo) {
                                $metodoMuestreo = Metodo::where('metodo_descripcion', 'ILIKE', $metodoMuestreoValueStr)->first();
                                if ($metodoMuestreo) {
                                    $metodoMuestreoCodigo = $metodoMuestreo->metodo_codigo;
                                }
                            }
                            
                            if (!$metodoMuestreo) {
                                $this->errors[] = "Fila {$rowNumber}: El método de muestreo '{$metodoMuestreoValueStr}' no existe (buscado por código y descripción)";
                                $this->errorCount++;
                                continue;
                            } else {
                                // Asegurar que tenemos el código del método
                                if (!$metodoMuestreoCodigo) {
                                    $metodoMuestreoCodigo = $metodoMuestreo->metodo_codigo;
                                }
                            }
                        }
                    }

                    // Procesar limite_cuantificacion
                    $limiteCuantificacion = null;
                    if (isset($row['limite_cuantificacion']) && !empty($row['limite_cuantificacion'])) {
                        $limiteCuantValue = $row['limite_cuantificacion'];
                        if (is_numeric($limiteCuantValue)) {
                            $limiteCuantificacion = (float) $limiteCuantValue;
                        } else {
                            $limiteCuantificacion = is_numeric(trim((string) $limiteCuantValue)) 
                                ? (float) trim((string) $limiteCuantValue) 
                                : null;
                        }
                    }

                    // Buscar si ya existe un item con esta descripción
                    $itemQuery = CotioItems::where('cotio_descripcion', $descripcion);

                    if ($esMuestra) {
                        // Para agrupadores, solo diferenciamos por es_muestra
                        $itemQuery->where('es_muestra', true);
                    } else {
                        // Para componentes, diferenciamos también por métodos
                        $itemQuery->where('es_muestra', false);

                        if ($metodoCodigo) {
                            $itemQuery->where('metodo', $metodoCodigo);
                        } else {
                            $itemQuery->whereNull('metodo');
                        }

                        if ($metodoMuestreoCodigo) {
                            $itemQuery->where('metodo_muestreo', $metodoMuestreoCodigo);
                        } else {
                            $itemQuery->whereNull('metodo_muestreo');
                        }
                    }

                    $item = $itemQuery->first();
                    
                    if ($item) {
                        // Actualizar el item existente (sin matriz_codigo)
                        $item->update([
                            'es_muestra' => $esMuestra,
                            'limites_establecidos' => $limitesEstablecidos,
                            'limite_cuantificacion' => $limiteCuantificacion,
                            'metodo' => $metodoCodigo,
                            'metodo_muestreo' => $metodoMuestreoCodigo,
                            'matriz_codigo' => null, // Ya no se guarda aquí
                            'unidad_medida' => $unidadMedida,
                            'precio' => !empty($row['precio']) ? (float) $row['precio'] : null,
                        ]);
                        
                        // Sincronizar matrices en tabla pivote
                        if ($matrizCodigo) {
                            $this->asociarMatrizAItem($item->id, $matrizCodigo);
                        }
                        
                        Log::debug('ItemsImport: Item actualizado', ['item_id' => $item->id, 'descripcion' => $descripcion]);
                    } else {
                        // Crear nuevo item con ID secuencial usando insert para poder especificar el ID
                        $itemData = [
                            'id' => $this->nextId,
                            'cotio_descripcion' => $descripcion,
                            'es_muestra' => $esMuestra,
                            'limites_establecidos' => $limitesEstablecidos,
                            'limite_cuantificacion' => $limiteCuantificacion,
                            'metodo' => $metodoCodigo,
                            'metodo_muestreo' => $metodoMuestreoCodigo,
                            'matriz_codigo' => null, // Ya no se guarda aquí
                            'unidad_medida' => $unidadMedida,
                            'precio' => !empty($row['precio']) ? (float) $row['precio'] : null,
                        ];
                        
                        DB::table('cotio_items')->insert($itemData);
                        
                        // Obtener el item creado
                        $item = CotioItems::find($this->nextId);
                        
                        // Asociar matriz en tabla pivote si existe
                        if ($matrizCodigo) {
                            $this->asociarMatrizAItem($item->id, $matrizCodigo);
                        }
                        
                        Log::debug('ItemsImport: Nuevo item creado', ['item_id' => $this->nextId, 'descripcion' => $descripcion]);
                        
                        // Incrementar el siguiente ID
                        $this->nextId++;
                    }

                    // Si es agrupador, asociar componentes
                    if ($esMuestra && !empty($row['componentes'] ?? $row['componentes_asociados'] ?? '')) {
                        $componentesStr = $row['componentes'] ?? $row['componentes_asociados'];
                        $componenteIds = $this->parseComponentes($componentesStr, $rowNumber);
                        
                        if (!empty($componenteIds)) {
                            // Validar que los componentes existan y no sean agrupadores
                            $componentesValidos = CotioItems::whereIn('id', $componenteIds)
                                ->where('es_muestra', false)
                                ->pluck('id')
                                ->toArray();
                            
                            $componentesInvalidos = array_diff($componenteIds, $componentesValidos);
                            if (!empty($componentesInvalidos)) {
                                $this->errors[] = "Fila {$rowNumber}: Los componentes con IDs " . implode(', ', $componentesInvalidos) . " no existen o son agrupadores";
                            }
                            
                            // Sincronizar componentes válidos
                            if (!empty($componentesValidos)) {
                                $item->componentesAsociados()->sync($componentesValidos);
                            }
                        }
                    }

                    $this->successCount++;
                    Log::debug('ItemsImport: Fila procesada exitosamente', ['fila' => $rowNumber, 'item_id' => $item->id]);
                } catch (\Throwable $e) {
                    $this->errors[] = "Fila {$rowNumber}: " . $e->getMessage();
                    $this->errorCount++;
                    Log::error('ItemsImport: Error en fila', [
                        'fila' => $rowNumber,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
        }

        $this->syncMatricesPivot();

        Log::info('ItemsImport: Procesamiento completado', [
            'successCount' => $this->successCount,
            'errorCount' => $this->errorCount
        ]);
    }

    /**
     * Parsear string de componentes (puede ser IDs separados por coma, punto y coma, etc.)
     */
    protected function parseComponentes($componentesStr, $rowNumber)
    {
        if (empty($componentesStr)) {
            return [];
        }

        // Separar por coma, punto y coma, o espacio
        $ids = preg_split('/[,;\s]+/', trim($componentesStr));
        $ids = array_filter(array_map('trim', $ids));
        $ids = array_filter($ids, function($id) {
            return is_numeric($id) && $id > 0;
        });

        return array_map('intval', $ids);
    }

    /**
     * Restaurar código con padding (ceros a la izquierda)
     * Intenta encontrar el código correcto en la base de datos probando diferentes longitudes de padding
     * 
     * @param mixed $value Valor numérico que Excel convirtió
     * @param string $table Nombre de la tabla
     * @param string $column Nombre de la columna
     * @param int $defaultPad Longitud de padding por defecto
     * @return string|null Código restaurado o null si no se encuentra
     */
    protected function restorePaddedCode($value, $table, $column, $defaultPad = 5)
    {
        if (empty($value)) {
            return null;
        }

        $numero = (int) $value;
        
        // Primero intentar con el padding por defecto
        $codigoPadded = str_pad($numero, $defaultPad, '0', STR_PAD_LEFT);
        $exists = DB::table($table)->where($column, $codigoPadded)->exists();
        
        if ($exists) {
            return $codigoPadded;
        }
        
        // Si no existe, intentar con diferentes longitudes (desde la longitud mínima hasta 10)
        $minLength = strlen((string) $numero);
        for ($length = $minLength; $length <= 10; $length++) {
            $codigoPadded = str_pad($numero, $length, '0', STR_PAD_LEFT);
            $exists = DB::table($table)->where($column, $codigoPadded)->exists();
            
            if ($exists) {
                return $codigoPadded;
            }
        }
        
        // Si no se encuentra con padding, intentar sin ceros
        $codigoSinPadding = (string) $numero;
        $exists = DB::table($table)->where($column, $codigoSinPadding)->exists();
        
        if ($exists) {
            return $codigoSinPadding;
        }
        
        // Si no se encuentra, devolver el código con padding por defecto (asumimos que es el formato correcto)
        return str_pad($numero, $defaultPad, '0', STR_PAD_LEFT);
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
     * Normaliza el texto de la columna Tipo (trim ASCII + bordes de espacio Unicode que Excel suele insertar).
     */
    protected function normalizeMatrizTipoInput(string $texto): string
    {
        $s = trim((string) $texto);
        if ($s === '') {
            return '';
        }

        return trim(preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $s));
    }

    /**
     * True si el valor de Tipo parece un código de matriz (solo dígitos), no un nombre descriptivo.
     */
    protected function tipoPareceCodigoMatrizNumerico(string $normalizado): bool
    {
        return $normalizado !== '' && (bool) preg_match('/^\d+$/', $normalizado);
    }

    /**
     * Busca en BD por código ignorando padding/espacios en matriz_codigo (respaldo si el índice en memoria falló).
     */
    protected function findMatrizEnDbPorCodigoTrim(string $codigoCandidato): ?Matriz
    {
        $c = trim($codigoCandidato);
        if ($c === '') {
            return null;
        }

        $col = Matriz::query()->getConnection()->getQueryGrammar()->wrap('matriz_codigo');

        return Matriz::query()->whereRaw("TRIM({$col}) = ?", [$c])->first();
    }

    /**
     * Resuelve una fila de catálogo matriz a partir del valor de columna "Tipo" de la plantilla
     * (código con/sin padding o descripción).
     */
    protected function resolveMatrizFromCodigoOrDescripcion(string $texto): ?Matriz
    {
        $t = $this->normalizeMatrizTipoInput($texto);
        if ($t === '') {
            return null;
        }

        if ($this->matrizLookupByTrim === null) {
            $this->matrizLookupByTrim = Matriz::query()
                ->get()
                ->keyBy(static fn (Matriz $m) => trim((string) $m->matriz_codigo));
        }

        $remember = function (?Matriz $m): ?Matriz {
            if ($m && $this->matrizLookupByTrim !== null) {
                $this->matrizLookupByTrim->put(trim((string) $m->matriz_codigo), $m);
            }

            return $m;
        };

        $byCode = $this->matrizLookupByTrim->get($t);
        if ($byCode) {
            return $byCode;
        }

        // Excel suele mandar el código como número (102); en matriz.matriz_codigo suele venir "00102" + espacios (CHAR).
        $normNum = str_replace(',', '.', $t);
        if (is_numeric($normNum) && preg_match('/^-?\d+(\.\d+)?$/', $normNum)) {
            $n = (int) round((float) $normNum);
            if ($n >= 0) {
                $minLen = strlen((string) $n);
                for ($len = max($minLen, 1); $len <= 20; $len++) {
                    $candidate = str_pad((string) $n, $len, '0', STR_PAD_LEFT);
                    $byCode = $this->matrizLookupByTrim->get($candidate);
                    if ($byCode) {
                        return $byCode;
                    }
                    $fromDb = $this->findMatrizEnDbPorCodigoTrim($candidate);
                    if ($fromDb) {
                        return $remember($fromDb);
                    }
                }
            }
        }

        $fromDbExact = $this->findMatrizEnDbPorCodigoTrim($t);
        if ($fromDbExact) {
            return $remember($fromDbExact);
        }

        $matriz = Matriz::whereRaw('LOWER(TRIM(matriz_descripcion)) = LOWER(TRIM(?))', [$t])->first();
        if ($matriz && $this->matrizLookupByTrim !== null) {
            $this->matrizLookupByTrim->put(trim((string) $matriz->matriz_codigo), $matriz);
        }

        return $matriz;
    }

    /**
     * Buscar o crear matriz por descripción o código (columna Tipo de la plantilla exportada).
     * No crea filas nuevas si Tipo es solo dígitos (código): debe existir en tabla matriz.
     */
    protected function findOrCreateMatriz($nombre, $rowNumber)
    {
        $nombre = $this->normalizeText($nombre);
        if ($nombre === '') {
            return null;
        }

        if (isset($this->cacheMatrices[$nombre])) {
            return $this->cacheMatrices[$nombre];
        }

        $matriz = $this->resolveMatrizFromCodigoOrDescripcion($nombre);

        if (! $matriz) {
            if ($this->tipoPareceCodigoMatrizNumerico($nombre)) {
                $this->errors[] = "Fila {$rowNumber}: No existe matriz con código «{$nombre}» en el catálogo (tabla matriz). No se crean matrices automáticamente a partir de códigos.";
                $this->errorCount++;

                return null;
            }

            $codigo = $this->nextPaddedCode('matriz', 'matriz_codigo', 5);
            $matriz = Matriz::create([
                'matriz_codigo' => $codigo,
                'matriz_descripcion' => $nombre,
                'matriz_tmuestra' => null,
            ]);
            Log::info("ItemsImport: Matriz creada: {$codigo} - {$nombre}");
            if ($this->matrizLookupByTrim !== null) {
                $this->matrizLookupByTrim->put(trim((string) $matriz->matriz_codigo), $matriz);
            }
        }

        $this->cacheMatrices[$nombre] = $matriz->matriz_codigo;

        return $matriz->matriz_codigo;
    }

    /**
     * Buscar o crear método por descripción
     */
    protected function findOrCreateMetodo($nombre, $rowNumber)
    {
        $nombre = $this->normalizeText($nombre);
        if ($nombre === '') {
            return null;
        }

        // Usar cache
        if (isset($this->cacheMetodos[$nombre])) {
            return $this->cacheMetodos[$nombre];
        }

        // Buscar por descripción (case-insensitive)
        $metodo = Metodo::where('metodo_descripcion', 'ILIKE', $nombre)->first();

        if (!$metodo) {
            // Crear nuevo método
            $codigo = $this->nextPaddedCode('metodo', 'metodo_codigo', 5);
            $metodo = Metodo::create([
                'metodo_codigo' => $codigo,
                'metodo_descripcion' => $nombre
            ]);
            Log::info("ItemsImport: Método creado: {$codigo} - {$nombre}");
        }

        $this->cacheMetodos[$nombre] = $metodo->metodo_codigo;
        return $metodo->metodo_codigo;
    }

    /**
     * Buscar o crear agrupador (es_muestra = true) por descripción
     */
    protected function findOrCreateAgrupador($nombre, $rowNumber)
    {
        $nombre = $this->normalizeText($nombre);
        if ($nombre === '') {
            return null;
        }

        // Usar cache
        if (isset($this->cacheAgrupadores[$nombre])) {
            return $this->cacheAgrupadores[$nombre];
        }

        $agrupador = CotioItems::where('es_muestra', true)
            ->whereRaw('LOWER(TRIM(cotio_descripcion)) = LOWER(TRIM(?))', [$nombre])
            ->first();

        if (!$agrupador) {
            // Crear nuevo agrupador
            $agrupador = CotioItems::create([
                'id' => $this->nextId++,
                'cotio_descripcion' => $nombre,
                'es_muestra' => true,
                'precio' => null
            ]);
            Log::info("ItemsImport: Agrupador creado: ID {$agrupador->id} - {$nombre}");
        }

        $this->cacheAgrupadores[$nombre] = $agrupador->id;
        return $agrupador->id;
    }

    /**
     * Asegura el vínculo agrupador ↔ componente en cotio_item_component (re-importación idempotente).
     * Evita INSERT duplicado y violaciones de PK por secuencia desalineada frente a syncWithoutDetaching/attach.
     */
    protected function ensurePivotAgrupadorComponente(int $agrupadorId, int $componenteId): void
    {
        if ($agrupadorId <= 0 || $componenteId <= 0) {
            return;
        }

        $now = now();

        DB::table('cotio_item_component')->upsert(
            [
                [
                    'agrupador_id' => $agrupadorId,
                    'componente_id' => $componenteId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['agrupador_id', 'componente_id'],
            ['updated_at']
        );
    }

    /**
     * Asociar matriz a un item en la tabla pivote
     */
    protected function asociarMatrizAItem($itemId, $matrizCodigo)
    {
        if (empty($matrizCodigo) || empty($itemId)) {
            return;
        }

        $matriz = $this->resolveMatrizFromCodigoOrDescripcion((string) $matrizCodigo);
        if (! $matriz) {
            Log::warning('ItemsImport: asociarMatrizAItem no se resolvió matriz desde plantilla', [
                'item_id' => $itemId,
                'matriz_codigo_o_texto' => $matrizCodigo,
            ]);

            return;
        }

        $codigoCanon = $matriz->matriz_codigo;
        $trimCanon = trim((string) $codigoCanon);

        if (! isset($this->matricesPorItem[$itemId])) {
            $this->matricesPorItem[$itemId] = [];
        }
        $yaListado = false;
        foreach ($this->matricesPorItem[$itemId] as $ex) {
            if (trim((string) $ex) === $trimCanon) {
                $yaListado = true;
                break;
            }
        }
        if (! $yaListado) {
            $this->matricesPorItem[$itemId][] = $codigoCanon;
        }

        $pivotCodes = DB::table('cotio_items_matriz')
            ->where('cotio_item_id', $itemId)
            ->pluck('matriz_codigo')
            ->map(static fn ($c) => trim((string) $c));

        if ($pivotCodes->contains($trimCanon)) {
            return;
        }

        DB::table('cotio_items_matriz')->insert([
            'cotio_item_id' => $itemId,
            'matriz_codigo' => $codigoCanon,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Log::debug('ItemsImport: Matriz asociada a item', [
            'item_id' => $itemId,
            'matriz_codigo' => $codigoCanon,
        ]);
    }

    /**
     * Sincronizar la tabla pivote de matrices para que cada item tenga
     * solo las matrices asignadas en esta importación.
     */
    protected function syncMatricesPivot()
    {
        foreach ($this->matricesPorItem as $itemId => $matrices) {
            $permitidos = array_values(array_unique(array_map(static fn ($c) => trim((string) $c), $matrices)));
            $actuales = DB::table('cotio_items_matriz')
                ->where('cotio_item_id', $itemId)
                ->get(['matriz_codigo']);

            foreach ($actuales as $row) {
                $t = trim((string) $row->matriz_codigo);
                if (! in_array($t, $permitidos, true)) {
                    DB::table('cotio_items_matriz')
                        ->where('cotio_item_id', $itemId)
                        ->where('matriz_codigo', $row->matriz_codigo)
                        ->delete();
                }
            }

            Log::debug('ItemsImport: Matrices sincronizadas para item', [
                'item_id' => $itemId,
                'matrices_actuales' => $matrices,
            ]);
        }

        $this->matricesPorItem = [];
    }

    /**
     * Busca un componente ya importado que coincida con la fila de la plantilla:
     * parámetro + metodología muestreo + metodología análisis + tipo (matriz en pivote) + vínculo a cada agrupador de la fila.
     */
    protected function findExistingComponenteForImportMatch(
        string $nombre,
        ?string $metodoCodigo,
        ?string $metodoMuestreoCodigo,
        ?string $matrizCodigo,
        array $agrupadorIds,
        $unidadMedida = null,
        $limitesEstablecidos = null,
        $limiteCuantificacion = null
    ): ?CotioItems {
        $nombreN = $this->normalizeText($nombre);
        $normCod = static function (?string $v): string {
            if ($v === null) {
                return '';
            }

            return trim((string) $v);
        };
        $wantM = $normCod($metodoCodigo);
        $wantMs = $normCod($metodoMuestreoCodigo);
        $wantMat = $matrizCodigo !== null && trim((string) $matrizCodigo) !== ''
            ? trim((string) $matrizCodigo)
            : null;

        // Estrategia de búsqueda robusta:
        // En lugar de confiar en que la normalización de SQL coincida con la de PHP,
        // buscamos por los criterios técnicos más específicos (Método) y luego
        // filtramos por nombre normalizado en PHP.
        
        $query = CotioItems::query()
            ->where(function($q) {
                $q->where('es_muestra', false)
                  ->orWhereNull('es_muestra');
            });

        // Filtrar por método en SQL si se proporciona (es muy específico y eficiente)
        if ($wantM !== '') {
            // Usamos LIKE para manejar posibles espacios en blanco en la columna CHAR/VARCHAR
            $query->where('metodo', 'LIKE', trim($wantM) . '%');
        }

        $candidates = $query->get();

        foreach ($candidates as $cand) {
            // 1. Verificar Nombre Normalizado (Ambos lados normalizados en PHP)
            $candNombreNorm = $this->normalizeText($cand->cotio_descripcion);
            if ($candNombreNorm !== $nombreN) {
                continue;
            }

            // 2. Verificar Métodos (ya filtrado en parte por SQL, pero aseguramos)
            if ($wantM !== trim((string)$cand->metodo)) {
                continue;
            }
            if ($wantMs !== trim((string)$cand->metodo_muestreo)) {
                continue;
            }

            // 3. Verificar Matriz en pivote o columna legacy
            $matchedMatriz = false;
            if ($wantMat !== null) {
                // Primero ver pivot
                $pivotCodes = DB::table('cotio_items_matriz')
                    ->where('cotio_item_id', $cand->id)
                    ->pluck('matriz_codigo')
                    ->map(static fn ($c) => trim((string) $c));
                
                if ($pivotCodes->contains($wantMat)) {
                    $matchedMatriz = true;
                } else {
                    // Ver columna legacy
                    if (trim((string)$cand->matriz_codigo) === $wantMat) {
                        $matchedMatriz = true;
                    }
                }
            } else {
                // Si no se pide matriz, consideramos que coincide (o podríamos ser más estrictos)
                $matchedMatriz = true;
            }

            if (!$matchedMatriz) {
                continue;
            }

            // 3. Verificar Unidades y Límites (si el usuario pide reutilización estricta)
            // Normalizamos las unidades para la comparación
            $candUnidad = $this->normalizeText($cand->unidad_medida);
            $wantUnidad = $this->normalizeText($unidadMedida ?? '');
            
            if ($wantUnidad !== '' && $candUnidad !== $wantUnidad) {
                continue;
            }

            // Comparación de límites (con tolerancia para decimales)
            $candLim = (float)($cand->limite_cuantificacion ?? $cand->limites_establecidos ?? 0);
            $wantLim = (float)($limiteCuantificacion ?? $limitesEstablecidos ?? 0);
            
            if (abs($candLim - $wantLim) > 0.000001) {
                continue;
            }

            // Si llegamos aquí, coincide en lo técnico.
            // Priorizamos si ya está vinculado a alguno de los agrupadores solicitados.
            if (! empty($agrupadorIds)) {
                $vinculadoAAlguno = false;
                foreach ($agrupadorIds as $aid) {
                    $existePivot = DB::table('cotio_item_component')
                        ->where('agrupador_id', $aid)
                        ->where('componente_id', $cand->id)
                        ->exists();
                    if ($existePivot) {
                        $vinculadoAAlguno = true;
                        break;
                    }
                }
                
                // NOTA: No descartamos si no está vinculado a ninguno, porque el objetivo es REUTILIZAR
                // si el componente es técnicamente el mismo.
            }

            return $cand;
        }

        return null;
    }

    /**
     * Buscar o crear componente (es_muestra = false) por descripción y clave de plantilla
     */
    protected function findOrCreateComponente(
        $nombre,
        $metodoCodigo,
        $metodoMuestreoCodigo,
        $matrizCodigo,
        array $agrupadorIds,
        $unidadMedida,
        $limitesEstablecidos,
        $limiteCuantificacion,
        $precio,
        $rowNumber
    ) {
        $nombreNormalizado = $this->normalizeText($nombre);
        if (empty($nombreNormalizado)) {
            $this->errors[] = "Fila {$rowNumber}: El nombre del parámetro es requerido";
            $this->errorCount++;
            return null;
        }

        $matrizCodigoNorm = $matrizCodigo ? trim((string) $matrizCodigo) : null;
        $agrupadorIdsSorted = array_values(array_unique(array_map('intval', $agrupadorIds)));
        sort($agrupadorIdsSorted);

        // Usar cache extendido para incluir todos los criterios técnicos
        $cacheKey = md5(implode('|', [
            $nombreNormalizado,
            $metodoCodigo ?? 'null',
            $metodoMuestreoCodigo ?? 'null',
            $matrizCodigoNorm ?? 'null',
            $this->normalizeText($unidadMedida ?? 'null'),
            (float)($limiteCuantificacion ?? $limitesEstablecidos ?? 0),
            implode(',', $agrupadorIdsSorted),
        ]));
        if (isset($this->cacheComponentes[$cacheKey])) {
            return $this->cacheComponentes[$cacheKey];
        }

        $componente = $this->findExistingComponenteForImportMatch(
            $nombreNormalizado,
            $metodoCodigo,
            $metodoMuestreoCodigo,
            $matrizCodigoNorm,
            $agrupadorIdsSorted,
            $unidadMedida,
            $limitesEstablecidos,
            $limiteCuantificacion
        );

        if ($componente) {
            // Actualizar componente existente (sin matriz_codigo)
            $componente->update([
                'metodo' => $metodoCodigo,
                'metodo_muestreo' => $metodoMuestreoCodigo,
                'matriz_codigo' => null, // Ya no se guarda aquí
                'unidad_medida' => $unidadMedida,
                'limites_establecidos' => $limitesEstablecidos,
                'limite_cuantificacion' => $limiteCuantificacion,
                'precio' => $precio
            ]);
            
            // Asociar matriz en tabla pivote si existe
            if ($matrizCodigo) {
                $this->asociarMatrizAItem($componente->id, $matrizCodigo);
            }
            
            Log::debug("ItemsImport: Componente actualizado: ID {$componente->id} - {$nombre}");
        } else {
            // Crear nuevo componente
            $componente = CotioItems::create([
                'id' => $this->nextId++,
                'cotio_descripcion' => $nombre,
                'es_muestra' => false,
                'metodo' => $metodoCodigo,
                'metodo_muestreo' => $metodoMuestreoCodigo,
                'matriz_codigo' => null, // Ya no se guarda aquí
                'unidad_medida' => $unidadMedida,
                'limites_establecidos' => $limitesEstablecidos,
                'limite_cuantificacion' => $limiteCuantificacion,
                'precio' => $precio
            ]);
            
            // Asociar matriz en tabla pivote si existe
            if ($matrizCodigo) {
                $this->asociarMatrizAItem($componente->id, $matrizCodigo);
            }
            
            Log::info("ItemsImport: Componente creado: ID {$componente->id} - {$nombre}");
        }

        $this->cacheComponentes[$cacheKey] = $componente;
        return $componente;
    }

    /**
     * Parsear valor numérico
     */
    protected function parseNumeric($value)
    {
        if (empty($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $cleaned = trim((string) $value);
        $cleaned = str_replace(',', '', $cleaned); // Remover comas de miles
        
        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    /**
     * Generar siguiente código con padding
     */
    protected function nextPaddedCode($table, $column, $pad = 5)
    {
        $allowed = [
            'metodo' => ['metodo_codigo'],
            'matriz' => ['matriz_codigo'],
        ];
        if (!isset($allowed[$table]) || !in_array($column, $allowed[$table], true)) {
            throw new \InvalidArgumentException('nextPaddedCode: combinación tabla/columna no permitida.');
        }

        $grammar = DB::connection()->getQueryGrammar();
        $w = $grammar->wrap($column);
        $driver = DB::connection()->getDriverName();

        // En PostgreSQL, MAX(CAST(codigo AS int)) sobre filas con códigos no numéricos aborta la consulta
        // y deja la transacción en estado de error (25P02). Solo considerar códigos formados solo por dígitos.
        if ($driver === 'pgsql') {
            $max = DB::table($table)
                ->whereRaw("btrim({$w}::text) ~ ?", ['^[0-9]+$'])
                ->select(DB::raw("MAX(({$w})::bigint) AS max_code"))
                ->value('max_code');
        } elseif ($driver === 'mysql') {
            $max = DB::table($table)
                ->whereRaw("{$w} REGEXP ?", ['^[0-9]+$'])
                ->select(DB::raw("MAX(CAST({$w} AS UNSIGNED)) AS max_code"))
                ->value('max_code');
        } else {
            $max = DB::table($table)
                ->whereRaw("CAST({$w} AS TEXT) GLOB ?", ['[0-9]*'])
                ->select(DB::raw("MAX(CAST({$w} AS INTEGER)) AS max_code"))
                ->value('max_code');
        }

        $next = ($max !== null && $max !== '') ? (int) $max + 1 : 1;

        return str_pad((string) $next, $pad, '0', STR_PAD_LEFT);
    }

    /**
     * Obtener valor de fila probando múltiples nombres de columna
     */
    protected function getRowValue($row, array $possibleKeys)
    {
        foreach ($possibleKeys as $key) {
            if (isset($row[$key]) && !empty($row[$key])) {
                return trim((string) $row[$key]);
            }
        }
        return '';
    }

    /**
     * Verificar si una fila tiene alguna de las columnas especificadas
     */
    protected function hasColumn($row, array $possibleKeys)
    {
        foreach ($possibleKeys as $key) {
            if (isset($row[$key])) {
                return true;
            }
        }
        return false;
    }
}
