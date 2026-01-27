<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class UsersImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    protected $errors = [];
    protected $successCount = 0;
    protected $errorCount = 0;

    /**
     * Procesa la colección de filas del Excel
     * 
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        Log::info('UsersImport: Iniciando procesamiento', ['total_filas' => $rows->count()]);
        
        if ($rows->isEmpty()) {
            Log::warning('UsersImport: No se encontraron filas para procesar');
            $this->errors[] = 'No se encontraron filas para procesar';
            return;
        }

        DB::beginTransaction();
        
        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 porque el índice empieza en 0 y hay encabezado
                
                try {
                    // Obtener valores de la fila (WithHeadingRow convierte a slug)
                    $nombre = $this->getRowValue($row, ['nombre']);
                    $apellido = $this->getRowValue($row, ['apellido']);
                    $dni = $this->getRowValue($row, ['dni', 'd.n.i.']);
                    $correo = $this->getRowValue($row, ['correo', 'email']);
                    $departamento = $this->getRowValue($row, ['departamento']);
                    $rol = $this->getRowValue($row, ['rol']);
                    $sector = $this->getRowValue($row, ['sector']);

                    // Validar campos requeridos
                    if (empty($nombre) || empty($apellido)) {
                        $this->errors[] = "Fila {$rowNumber}: Nombre y Apellido son requeridos";
                        $this->errorCount++;
                        continue;
                    }

                    // Generar usu_descripcion
                    $usuDescripcion = trim($nombre) . ' ' . trim($apellido);

                    // Generar usu_codigo (usar iniciales del nombre y apellido)
                    $usuCodigo = $this->generateUsuCodigo($nombre, $apellido, $correo);

                    // Verificar si el código ya existe
                    if (User::where('usu_codigo', $usuCodigo)->exists()) {
                        // Si existe, agregar un número
                        $counter = 1;
                        $originalCodigo = $usuCodigo;
                        while (User::where('usu_codigo', $usuCodigo)->exists()) {
                            $usuCodigo = $originalCodigo . $counter;
                            $counter++;
                        }
                    }

                    // Normalizar sector_codigo
                    $sectorNormalizado = $this->normalizeSector($sector);
                    $sectorCodigo = null;
                    
                    // Si el sector normalizado existe como usu_codigo, usarlo
                    // Si no existe, dejar null para evitar errores de foreign key
                    if (!empty($sectorNormalizado)) {
                        $sectorUsuario = User::where('usu_codigo', $sectorNormalizado)->first();
                        if ($sectorUsuario) {
                            $sectorCodigo = $sectorNormalizado;
                        } else {
                            // Si no existe como usuario, dejar null
                            // (el usuario puede crear los sectores manualmente después si es necesario)
                            Log::warning("UsersImport: Sector normalizado no existe como usuario, se dejará null", [
                                'sector_original' => $sector,
                                'sector_normalizado' => $sectorNormalizado
                            ]);
                        }
                    }

                    // Normalizar rol
                    $rolNormalizado = $this->normalizeRol($rol);
                    
                    // Determinar usu_nivel según el rol (usar el rol original para la lógica)
                    $usuNivel = $this->determineUsuNivel($rol);

                    // Generar usu_clave (usar MD5 del DNI o contraseña por defecto)
                    $defaultPassword = !empty($dni) ? str_replace('.', '', $dni) : 'password123';
                    $usuClave = md5($defaultPassword);

                    // Limpiar DNI (quitar puntos)
                    $dniClean = !empty($dni) ? str_replace('.', '', $dni) : null;

                    // Crear o actualizar usuario
                    $user = User::updateOrCreate(
                        ['usu_codigo' => $usuCodigo],
                        [
                            'usu_descripcion' => $usuDescripcion,
                            'usu_clave' => $usuClave,
                            'usu_nivel' => $usuNivel,
                            'usu_estado' => true,
                            'usu_sesionauto' => false,
                            'rol' => $rolNormalizado,
                            'sector_codigo' => $sectorCodigo,
                            'dni' => $dniClean,
                            'email' => $correo,
                            'departamento' => $departamento,
                        ]
                    );

                    $this->successCount++;
                    Log::info("UsersImport: Usuario procesado correctamente", [
                        'fila' => $rowNumber,
                        'usu_codigo' => $usuCodigo,
                        'usu_descripcion' => $usuDescripcion
                    ]);

                } catch (\Exception $e) {
                    $this->errors[] = "Fila {$rowNumber}: " . $e->getMessage();
                    $this->errorCount++;
                    Log::error("UsersImport: Error procesando fila {$rowNumber}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            DB::commit();
            Log::info('UsersImport: Procesamiento completado', [
                'success' => $this->successCount,
                'errors' => $this->errorCount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->errors[] = "Error general: " . $e->getMessage();
            Log::error('UsersImport: Error en transacción', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Obtiene el valor de una fila buscando en múltiples posibles nombres de columna
     */
    protected function getRowValue($row, array $possibleKeys)
    {
        // Convertir la fila a array para facilitar la búsqueda
        $rowArray = $row->toArray();
        
        foreach ($possibleKeys as $key) {
            // WithHeadingRow convierte a slug, así que buscamos variaciones
            $keySlug = Str::slug($key, '_');
            $keySlugDash = Str::slug($key, '-');
            $keyLower = strtolower($key);
            
            // Lista de variaciones a buscar
            $variations = [
                $keySlug,
                $keySlugDash,
                $keyLower,
                str_replace('_', '-', $keySlug),
                str_replace('-', '_', $keySlugDash),
                $key
            ];
            
            // Buscar en todas las variaciones
            foreach ($variations as $variation) {
                // Buscar exacto (case-insensitive)
                foreach ($rowArray as $rowKey => $rowValue) {
                    $rowKeyLower = strtolower($rowKey);
                    $variationLower = strtolower($variation);
                    
                    if ($rowKeyLower === $variationLower || 
                        str_replace(['_', '-', ' '], '', $rowKeyLower) === str_replace(['_', '-', ' '], '', $variationLower)) {
                        $value = $rowValue;
                        return is_string($value) ? trim($value) : ($value !== null ? $value : null);
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Genera un usu_codigo basado en nombre, apellido y correo
     */
    protected function generateUsuCodigo($nombre, $apellido, $correo)
    {
        // Si hay correo, usar la parte antes del @ como base
        if (!empty($correo) && strpos($correo, '@') !== false) {
            $emailPart = explode('@', $correo)[0];
            return substr($emailPart, 0, 10); // Máximo 10 caracteres, mantiene formato original
        }

        // Si no hay correo, usar iniciales del nombre y apellido
        $inicialNombre = !empty($nombre) ? substr(trim($nombre), 0, 1) : '';
        $inicialApellido = !empty($apellido) ? substr(trim($apellido), 0, 1) : '';
        $restoApellido = !empty($apellido) ? substr(trim($apellido), 1, 7) : '';
        
        return $inicialNombre . $inicialApellido . $restoApellido;
    }

    /**
     * Normaliza el sector eliminando espacios, caracteres especiales y convirtiendo a minúsculas
     */
    protected function normalizeSector($sector)
    {
        if (empty($sector)) {
            return null;
        }

        // Convertir a minúsculas
        $normalized = strtolower(trim($sector));
        
        // Reemplazar caracteres especiales
        $normalized = str_replace(['/', '(', ')', ' ', '-'], '_', $normalized);
        
        // Eliminar múltiples guiones bajos consecutivos
        $normalized = preg_replace('/_+/', '_', $normalized);
        
        // Eliminar guiones bajos al inicio y final
        $normalized = trim($normalized, '_');
        
        return $normalized;
    }

    /**
     * Normaliza el rol eliminando espacios, caracteres especiales y convirtiendo a minúsculas
     */
    protected function normalizeRol($rol)
    {
        if (empty($rol)) {
            return null;
        }

        // Convertir a minúsculas
        $normalized = strtolower(trim($rol));
        
        // Reemplazar caracteres especiales (espacios, barras, guiones)
        $normalized = str_replace(['/', ' ', '-'], '_', $normalized);
        
        // Eliminar múltiples guiones bajos consecutivos
        $normalized = preg_replace('/_+/', '_', $normalized);
        
        // Eliminar guiones bajos al inicio y final
        $normalized = trim($normalized, '_');
        
        return $normalized;
    }

    /**
     * Determina el usu_nivel según el rol
     */
    protected function determineUsuNivel($rol)
    {
        if (empty($rol)) {
            return 500; // Por defecto
        }

        $rolLower = strtolower(trim($rol));
        
        // Si el rol contiene "gerente" o "admin", nivel 900
        if (strpos($rolLower, 'gerente') !== false || strpos($rolLower, 'admin') !== false) {
            return 900;
        }
        
        // Todos los demás, nivel 500
        return 500;
    }

    /**
     * Obtiene el número de registros procesados correctamente
     */
    public function getSuccessCount()
    {
        return $this->successCount;
    }

    /**
     * Obtiene el número de errores
     */
    public function getErrorCount()
    {
        return $this->errorCount;
    }

    /**
     * Obtiene los errores encontrados
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
