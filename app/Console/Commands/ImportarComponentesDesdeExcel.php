<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\Matriz;
use App\Models\Metodo;
use App\Models\CotioItems;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportarComponentesDesdeExcel extends Command
{
    protected $signature = 'cotio:importar-componentes {archivo} {--truncate} {--dry-run}';
    protected $description = 'Importa agrupadores y componentes desde un Excel, creando matriz y métodos automáticamente.';

    public function handle()
    {
        $archivo = $this->argument('archivo');
        $dryRun = $this->option('dry-run');

        $this->info("---------------------------------------------------------");
        $this->info("IMPORTADOR DE COMPONENTES - " . ($dryRun ? "MODO DRY-RUN" : "EJECUCIÓN REAL"));
        $this->info("---------------------------------------------------------");

        Log::info("=== Iniciando importación de componentes ===");
        Log::info("Archivo recibido: $archivo");
        Log::info("Dry-run: " . ($dryRun ? "SI" : "NO"));

        if (!file_exists($archivo)) {
            $this->error("El archivo no existe: $archivo");
            Log::error("Archivo no encontrado: $archivo");
            return;
        }

        // Truncar componentes (es_muestra = false) y agrupadores (es_muestra = true) antes de importar
        if ($dryRun) {
            $countComponentes = CotioItems::where('es_muestra', false)->count();
            $countAgrupadores = CotioItems::where('es_muestra', true)->count();
            $this->warn("[Dry-run] Se habría truncado $countComponentes componentes y $countAgrupadores agrupadores");
        } else {
            // Eliminar relaciones primero
            DB::table('cotio_item_component')->truncate();
            
            $countComponentes = CotioItems::where('es_muestra', false)->count();
            $countAgrupadores = CotioItems::where('es_muestra', true)->count();
            
            CotioItems::where('es_muestra', false)->delete();
            CotioItems::where('es_muestra', true)->delete();
            
            $this->info("✓ Se truncaron $countComponentes componentes y $countAgrupadores agrupadores");
            Log::info("Componentes y agrupadores truncados correctamente. Componentes: $countComponentes, Agrupadores: $countAgrupadores");
        }

        // Configurar límite de memoria y tiempo de ejecución
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $spreadsheet = IOFactory::load($archivo);
        $hoja = $spreadsheet->getActiveSheet();
        
        // Obtener el número máximo de filas con datos
        $maxFila = $hoja->getHighestRow();
        $this->info("Total de filas a procesar: " . ($maxFila - 4));

        $fila = 5;
        $maxIteraciones = 100000; // Límite de seguridad
        $iteracion = 0;

        $creadasMatrices = 0;
        $creadosMetodos = 0;
        $creadosMetodosMuestreo = 0;
        $creadosComponentes = 0;
        $creadosAgrupadores = 0;
        $creadasRelaciones = 0;

        // Cache para evitar consultas repetidas
        $cacheMatrices = [];
        $cacheMetodos = [];
        $cacheMetodosMuestreo = [];
        $cacheAgrupadores = []; // Cache de agrupadores por nombre
        $cacheComponentes = []; // Cache de componentes por características únicas
        
        // Variable única para el ID incremental de cotio_items (agrupadores y componentes)
        $lastId = null;

        if (!$dryRun) DB::beginTransaction();

        try {
            // Leer matriz y agrupador desde la fila 4 (si existe)
            $matrizNombreGlobal = null;
            $agrupadorNombreGlobal = null;
            if ($maxFila >= 4) {
                $matrizNombreGlobal = $this->normalize($hoja->getCell("A4")->getValue());
                $agrupadorNombreGlobal = $this->normalize($hoja->getCell("B4")->getValue());
            }

            while ($fila <= $maxFila && $iteracion < $maxIteraciones) {
                $iteracion++;

                // Leer datos de la fila actual
                $parametro              = $this->normalize($hoja->getCell("C{$fila}")->getValue());
                $agrupadorNombre        = $this->normalize($hoja->getCell("B{$fila}")->getValue()) ?: $agrupadorNombreGlobal;
                $matrizNombre           = $this->normalize($hoja->getCell("A{$fila}")->getValue()) ?: $matrizNombreGlobal;
                $metodoMuestreoNombre   = $this->normalize($hoja->getCell("D{$fila}")->getValue());
                $metodoNombre           = $this->normalize($hoja->getCell("E{$fila}")->getValue());
                $unidad                 = $this->normalize($hoja->getCell("F{$fila}")->getValue());
                $limite                 = $this->normalize($hoja->getCell("H{$fila}")->getValue());
                $limiteCuantificacion   = $this->normalize($hoja->getCell("I{$fila}")->getValue());
                $precio                 = $this->normalize($hoja->getCell("Z{$fila}")->getValue());

                // Condición de salida mejorada: si todas las columnas clave están vacías
                if (empty($parametro) && empty($matrizNombre) && empty($metodoNombre) && empty($metodoMuestreoNombre)) {
                    // Verificar las siguientes 5 filas para asegurar que realmente terminó
                    $filasVacias = 0;
                    for ($i = 1; $i <= 5; $i++) {
                        $testFila = $fila + $i;
                        if ($testFila > $maxFila) break;
                        $testParam = $this->normalize($hoja->getCell("C{$testFila}")->getValue());
                        if (empty($testParam)) {
                            $filasVacias++;
                        }
                    }
                    if ($filasVacias >= 5) {
                        $this->info("Se encontraron 5 filas vacías consecutivas. Finalizando procesamiento en fila $fila.");
                        break;
                    }
                }
                
                if (empty($parametro)) { 
                    $fila++; 
                    continue; 
                }

                // Log cada 100 filas para reducir consumo de memoria
                if ($iteracion % 100 == 0) {
                    $this->info("Procesando fila $fila de $maxFila...");
                    Log::info("Procesando fila $fila: Parametro=$parametro, Matriz=$matrizNombre");
                }

                Log::info("Procesando fila $fila: Parametro=$parametro, Matriz=$matrizNombre, MétodoMuestreo=$metodoMuestreoNombre, Método=$metodoNombre");

                /*
                |--------------------------------------------------------------------------
                | 1) MATRIZ
                |--------------------------------------------------------------------------
                */

                if ($dryRun) {
                    $codigoMatriz = $this->nextPaddedCode('matriz', 'matriz_codigo');
                    if ($iteracion <= 10) {
                        $this->line("[Dry-run] Matriz requerida: $codigoMatriz ($matrizNombre)");
                    }
                } else {
                    // Usar cache para evitar consultas repetidas
                    if (isset($cacheMatrices[$matrizNombre])) {
                        $codigoMatriz = $cacheMatrices[$matrizNombre];
                    } else {
                        $matriz = Matriz::where('matriz_descripcion', $matrizNombre)->first();

                        if (!$matriz) {
                            $codigoMatriz = $this->nextPaddedCode('matriz', 'matriz_codigo');

                            $matriz = Matriz::create([
                                'matriz_codigo'      => $codigoMatriz,
                                'matriz_descripcion' => $matrizNombre,
                                'matriz_tmuestra'    => null
                            ]);

                            $creadasMatrices++;
                            if ($creadasMatrices <= 10) {
                                Log::info("⚡ Matriz creada: $codigoMatriz");
                            }
                        } else {
                            $codigoMatriz = $matriz->matriz_codigo;
                        }
                        $cacheMatrices[$matrizNombre] = $codigoMatriz;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | 2) MÉTODO DE MUESTREO
                |--------------------------------------------------------------------------
                */

                $codigoMetodoMuestreo = null;
                if ($metodoMuestreoNombre) {
                    if ($dryRun) {
                        // Simular código en la misma tabla 'metodo'
                        $codigoMetodoMuestreo = $this->nextPaddedCode('metodo', 'metodo_codigo');
                        if ($iteracion <= 10) {
                            $this->line("[Dry-run] Método de muestreo requerido (tabla metodo): $codigoMetodoMuestreo ($metodoMuestreoNombre)");
                        }
                    } else {
                        // Usar cache para evitar consultas repetidas
                        if (isset($cacheMetodosMuestreo[$metodoMuestreoNombre])) {
                            $codigoMetodoMuestreo = $cacheMetodosMuestreo[$metodoMuestreoNombre];
                        } else {
                            $metodoMuestreo = Metodo::where('metodo_descripcion', $metodoMuestreoNombre)->first();

                            if (!$metodoMuestreo) {
                                $codigoMetodoMuestreo = $this->nextPaddedCode('metodo', 'metodo_codigo');

                                $metodoMuestreo = Metodo::create([
                                    'metodo_codigo'      => $codigoMetodoMuestreo,
                                    'metodo_descripcion' => $metodoMuestreoNombre
                                ]);

                                $creadosMetodosMuestreo++;
                                if ($creadosMetodosMuestreo <= 10) {
                                    Log::info("⚡ Método de muestreo creado (tabla metodo): $codigoMetodoMuestreo");
                                }
                            } else {
                                $codigoMetodoMuestreo = $metodoMuestreo->metodo_codigo;
                            }
                            $cacheMetodosMuestreo[$metodoMuestreoNombre] = $codigoMetodoMuestreo;
                        }
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | 3) MÉTODO DE ANÁLISIS
                |--------------------------------------------------------------------------
                */

                if ($dryRun) {
                    $codigoMetodo = $this->nextPaddedCode('metodo', 'metodo_codigo');
                    if ($iteracion <= 10) {
                        $this->line("[Dry-run] Método de análisis requerido: $codigoMetodo ($metodoNombre)");
                    }
                } else {
                    // Usar cache para evitar consultas repetidas
                    if (isset($cacheMetodos[$metodoNombre])) {
                        $codigoMetodo = $cacheMetodos[$metodoNombre];
                    } else {
                        $metodo = Metodo::where('metodo_descripcion', $metodoNombre)->first();

                        if (!$metodo) {
                            $codigoMetodo = $this->nextPaddedCode('metodo', 'metodo_codigo');

                            $metodo = Metodo::create([
                                'metodo_codigo'      => $codigoMetodo,
                                'metodo_descripcion' => $metodoNombre
                            ]);

                            $creadosMetodos++;
                            if ($creadosMetodos <= 10) {
                                Log::info("⚡ Método de análisis creado: $codigoMetodo");
                            }
                        } else {
                            $codigoMetodo = $metodo->metodo_codigo;
                        }
                        $cacheMetodos[$metodoNombre] = $codigoMetodo;
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | 4) AGRUPADOR
                |--------------------------------------------------------------------------
                */

                $agrupadorId = null;
                if (!empty($agrupadorNombre)) {
                    if ($dryRun) {
                        $agrupadorId = $this->nextNumericId('cotio_items');
                        if ($iteracion <= 10) {
                            $this->line("[Dry-run] Agrupador ID=$agrupadorId: $agrupadorNombre | Matriz=$codigoMatriz");
                        }
                    } else {
                        // Usar cache para evitar consultas repetidas
                        if (isset($cacheAgrupadores[$agrupadorNombre])) {
                            $agrupadorId = $cacheAgrupadores[$agrupadorNombre];
                            
                            // La matriz ya se guardó en la tabla pivote, no es necesario actualizar matriz_codigo
                        } else {
                            $agrupador = CotioItems::where('cotio_descripcion', $agrupadorNombre)
                                ->where('es_muestra', true)
                                ->first();

                            if (!$agrupador) {
                                // Usar el contador global de IDs
                                if ($lastId === null) {
                                    $lastId = $this->nextNumericId('cotio_items');
                                } else {
                                    $lastId++;
                                }

                                $agrupador = CotioItems::create([
                                    'id'                => $lastId,
                                    'cotio_descripcion' => $agrupadorNombre,
                                    'es_muestra'        => true,
                                    'matriz_codigo'     => null // Ya no se guarda aquí, se usa tabla pivote
                                ]);

                                $agrupadorId = $agrupador->id;
                                $creadosAgrupadores++;
                                
                                if ($creadosAgrupadores <= 10) {
                                    Log::info("⚡ Agrupador creado: ID=$agrupadorId ($agrupadorNombre) con matriz $codigoMatriz");
                                }
                            } else {
                                $agrupadorId = $agrupador->id;
                                // La matriz se guarda en la tabla pivote, no es necesario actualizar matriz_codigo
                            }
                            $cacheAgrupadores[$agrupadorNombre] = $agrupadorId;
                        }
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | 5) COMPONENTE
                |--------------------------------------------------------------------------
                */

                // Crear clave única para el componente basada en sus características
                $componenteKey = $this->crearClaveComponente(
                    $parametro,
                    $limite,
                    $unidad,
                    $codigoMetodo,
                    $codigoMetodoMuestreo,
                    $precio,
                    $limiteCuantificacion
                );

                if ($dryRun) {
                    $id = $this->nextNumericId('cotio_items');
                    if ($iteracion <= 10) {
                        $this->line("[Dry-run] Componente ID=$id: $parametro | MétodoMuestreo=$codigoMetodoMuestreo | Método=$codigoMetodo | Agrupador=$agrupadorId");
                    }
                } else {
                    $componenteId = null;
                    
                    // Verificar si ya existe un componente con las mismas características
                    if (isset($cacheComponentes[$componenteKey])) {
                        // Reutilizar componente existente
                        $componenteId = $cacheComponentes[$componenteKey];
                        if ($iteracion <= 10) {
                            Log::info("♻️ Componente reutilizado ID=$componenteId: $parametro");
                        }
                    } else {
                        // Crear nuevo componente
                        // Usar el contador global de IDs
                        if ($lastId === null) {
                            $lastId = $this->nextNumericId('cotio_items');
                        } else {
                            $lastId++;
                        }
                        
                        $componente = CotioItems::create([
                            'id'                    => $lastId,
                            'cotio_descripcion'     => $parametro,
                            'es_muestra'            => false,
                            'limites_establecidos'  => $limite,
                            'limite_cuantificacion' => $limiteCuantificacion ? floatval($limiteCuantificacion) : null,
                            'metodo'                => $codigoMetodo,
                            'metodo_muestreo'       => $codigoMetodoMuestreo,
                            'matriz_codigo'         => null, // Los componentes NO tienen matriz
                            'unidad_medida'         => $unidad,
                            'precio'                => floatval($precio ?: 0)
                        ]);

                        $componenteId = $lastId;
                        $cacheComponentes[$componenteKey] = $componenteId;
                        $creadosComponentes++;
                        
                        if ($creadosComponentes <= 10) {
                            Log::info("⚡ Componente creado: ID=$componenteId ($parametro)");
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 6) RELACIÓN AGRUPADOR-COMPONENTE
                    |--------------------------------------------------------------------------
                    */
                    
                    if ($agrupadorId && $componenteId) {
                        // Verificar si la relación ya existe
                        $relacionExiste = DB::table('cotio_item_component')
                            ->where('agrupador_id', $agrupadorId)
                            ->where('componente_id', $componenteId)
                            ->exists();

                        if (!$relacionExiste) {
                            DB::table('cotio_item_component')->insert([
                                'agrupador_id'  => $agrupadorId,
                                'componente_id' => $componenteId,
                                'created_at'    => now(),
                                'updated_at'    => now()
                            ]);
                            $creadasRelaciones++;
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 7) RELACIÓN AGRUPADOR-MATRIZ (tabla pivote)
                    |--------------------------------------------------------------------------
                    */
                    
                    if ($agrupadorId && $codigoMatriz) {
                        // Limpiar código de matriz (trim para eliminar espacios)
                        $codigoMatrizLimpio = trim($codigoMatriz);
                        
                        // Verificar si la relación agrupador-matriz ya existe
                        $relacionMatrizExiste = DB::table('cotio_items_matriz')
                            ->where('cotio_item_id', $agrupadorId)
                            ->where('matriz_codigo', $codigoMatrizLimpio)
                            ->exists();

                        if (!$relacionMatrizExiste) {
                            DB::table('cotio_items_matriz')->insert([
                                'cotio_item_id' => $agrupadorId,
                                'matriz_codigo' => $codigoMatrizLimpio,
                                'created_at'    => now(),
                                'updated_at'    => now()
                            ]);
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 8) RELACIÓN COMPONENTE-MATRIZ (tabla pivote)
                    |--------------------------------------------------------------------------
                    | El componente se relaciona con la misma matriz que el agrupador
                    | al que pertenece. Esto permite filtrar componentes por matriz.
                    */
                    
                    if ($componenteId && $codigoMatriz) {
                        // Limpiar código de matriz (trim para eliminar espacios)
                        $codigoMatrizLimpio = trim($codigoMatriz);
                        
                        // Verificar si la relación componente-matriz ya existe
                        $relacionComponenteMatrizExiste = DB::table('cotio_items_matriz')
                            ->where('cotio_item_id', $componenteId)
                            ->where('matriz_codigo', $codigoMatrizLimpio)
                            ->exists();

                        if (!$relacionComponenteMatrizExiste) {
                            DB::table('cotio_items_matriz')->insert([
                                'cotio_item_id' => $componenteId,
                                'matriz_codigo' => $codigoMatrizLimpio,
                                'created_at'    => now(),
                                'updated_at'    => now()
                            ]);
                        }
                    }

                    if ($creadosComponentes % 100 == 0) {
                        $this->info("Componentes procesados: $creadosComponentes");
                    }
                }

                $fila++;
            }

            if (!$dryRun) DB::commit();

            $this->info("---------------------------------------------------------");
            $this->info("Proceso finalizado.");
            $this->info("Matrices nuevas: $creadasMatrices");
            $this->info("Métodos de muestreo nuevos: $creadosMetodosMuestreo");
            $this->info("Métodos de análisis nuevos: $creadosMetodos");
            $this->info("Agrupadores insertados: $creadosAgrupadores");
            $this->info("Componentes insertados: $creadosComponentes");
            $this->info("Relaciones creadas: $creadasRelaciones");
            $this->info("---------------------------------------------------------");

        } catch (\Exception $e) {
            if (!$dryRun) DB::rollBack();
            Log::error("ERROR en fila $fila: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            $this->error("ERROR en fila $fila: " . $e->getMessage());
            $this->error("Iteración: $iteracion de $maxIteraciones");
        } finally {
            // Liberar memoria del spreadsheet
            if (isset($spreadsheet)) {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | NORMALIZAR TEXTO
    |--------------------------------------------------------------------------
    */
    private function normalize($value)
    {
        if ($value === null) return '';
        return trim(preg_replace('/\s+/', ' ', (string)$value));
    }

    /*
    |--------------------------------------------------------------------------
    | AUTOINCREMENTO NUMÉRICO REAL
    |--------------------------------------------------------------------------
    */
    private function nextNumericId($table)
    {
        $max = DB::table($table)->max('id');
        return $max ? $max + 1 : 1;
    }

    /*
    |--------------------------------------------------------------------------
    | AUTOINCREMENTO PADDED PARA CODIGOS
    |--------------------------------------------------------------------------
    */
    private function nextPaddedCode($table, $column, $pad = 5)
    {
        $max = DB::table($table)
            ->select(DB::raw("MAX(CAST($column AS INTEGER)) AS max_code"))
            ->value('max_code');

        $next = $max ? $max + 1 : 1;

        return str_pad($next, $pad, '0', STR_PAD_LEFT);
    }

    /*
    |--------------------------------------------------------------------------
    | AUTOINCREMENTO PADDED PARA CODIGOS ALFANUMÉRICOS (MÉTODOS DE MUESTREO)
    |--------------------------------------------------------------------------
    */
    private function nextPaddedCodeMuestreo($table, $column, $prefix = 'MUE', $pad = 3)
    {
        // Obtener todos los códigos existentes
        $codigos = DB::table($table)
            ->where($column, 'like', $prefix . '%')
            ->pluck($column)
            ->toArray();

        $maxNum = 0;
        foreach ($codigos as $codigo) {
            // Extraer la parte numérica del código (ej: "MUE001" -> 1)
            if (preg_match('/' . preg_quote($prefix, '/') . '(\d+)/', $codigo, $matches)) {
                $num = intval($matches[1]);
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }

        $next = $maxNum + 1;
        return $prefix . str_pad($next, $pad, '0', STR_PAD_LEFT);
    }

    /*
    |--------------------------------------------------------------------------
    | CREAR CLAVE ÚNICA PARA COMPONENTE
    |--------------------------------------------------------------------------
    | Crea una clave única basada en las características del componente
    | para evitar duplicados
    */
    private function crearClaveComponente($descripcion, $limite, $unidad, $metodo, $metodoMuestreo, $precio, $limiteCuantificacion)
    {
        // Normalizar valores para la clave
        $descripcionNormalizada = strtolower(trim($descripcion ?? ''));
        $limiteNormalizado = trim($limite ?? '');
        $unidadNormalizada = strtolower(trim($unidad ?? ''));
        $metodoNormalizado = trim($metodo ?? '');
        $metodoMuestreoNormalizado = trim($metodoMuestreo ?? '');
        $precioNormalizado = number_format(floatval($precio ?: 0), 2, '.', '');
        $limiteCuantificacionNormalizado = $limiteCuantificacion ? number_format(floatval($limiteCuantificacion), 6, '.', '') : '';

        // Crear clave única concatenando todas las características
        return md5(implode('|', [
            $descripcionNormalizada,
            $limiteNormalizado,
            $unidadNormalizada,
            $metodoNormalizado,
            $metodoMuestreoNormalizado,
            $precioNormalizado,
            $limiteCuantificacionNormalizado
        ]));
    }
}
