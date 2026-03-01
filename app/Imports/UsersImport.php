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
     * Procesa la colección de filas del Excel para actualizar roles de usuarios existentes
     * 
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        Log::info('UsersImport: Iniciando actualización de roles', ['total_filas' => $rows->count()]);
        
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
                    // Buscar variaciones de los nombres de columnas del Excel
                    $usuCodigo = $this->getRowValue($row, [
                        'codigo_usuario', 
                        'codigo usuario', 
                        'código usuario',
                        'codigo',
                        'usuario'
                    ]);
                    $dni = $this->getRowValue($row, ['dni', 'd.n.i.']);
                    $rolSistema = $this->getRowValue($row, [
                        'rol_del_sistema',
                        'rol del sistema',
                        'rol',
                        'rol_sistema'
                    ]);
                    
                    // Obtener roles adicionales (buscar variaciones de nombres de columnas)
                    $rolAdicional1 = $this->getRowValue($row, [
                        'roles_adicional_1',      // "Roles adicional 1" (plural, con espacio)
                        'roles adicional 1',     // Variación con espacio
                        'rol_adicional_1',       // "Rol adicional 1" (singular)
                        'rol adicional 1',       // Variación con espacio
                        'roles_adicional1',      // Sin guión bajo
                        'roles adicional1',     // Sin guión bajo con espacio
                        'rol_adicional1',        // Singular sin guión bajo
                        'rol adicional1'         // Singular sin guión bajo con espacio
                    ]);
                    $rolAdicional2 = $this->getRowValue($row, [
                        'roles_adicional_2',
                        'roles adicional 2',
                        'rol_adicional_2',
                        'rol adicional 2',
                        'roles_adicional2',
                        'roles adicional2',
                        'rol_adicional2',
                        'rol adicional2'
                    ]);
                    $rolAdicional3 = $this->getRowValue($row, [
                        'roles_adicional_3',
                        'roles adicional 3',
                        'rol_adicional_3',
                        'rol adicional 3',
                        'roles_adicional3',
                        'roles adicional3',
                        'rol_adicional3',
                        'rol adicional3'
                    ]);

                    // Validar que el código de usuario esté presente
                    if (empty($usuCodigo)) {
                        $this->errors[] = "Fila {$rowNumber}: Código de usuario es requerido";
                        $this->errorCount++;
                        continue;
                    }

                    // Limpiar y normalizar el código de usuario
                    $usuCodigo = trim(strtolower($usuCodigo));

                    // Buscar el usuario existente por código
                    $user = User::where('usu_codigo', $usuCodigo)->first();

                    if (!$user) {
                        $this->errors[] = "Fila {$rowNumber}: Usuario con código '{$usuCodigo}' no encontrado";
                        $this->errorCount++;
                        Log::warning("UsersImport: Usuario no encontrado", [
                            'fila' => $rowNumber,
                            'usu_codigo' => $usuCodigo
                        ]);
                        continue;
                    }

                    // Preparar datos para actualizar
                    $updateData = [];
                    $rolesAdicionales = [];

                    // Actualizar rol principal si está presente
                    if (!empty($rolSistema)) {
                        $rolNormalizado = $this->normalizeRol($rolSistema);
                        if ($rolNormalizado) {
                            $updateData['rol'] = $rolNormalizado;
                            
                            // Actualizar usu_nivel según el rol principal
                            $updateData['usu_nivel'] = $this->determineUsuNivel($rolSistema);
                        }
                    }

                    // Recopilar roles adicionales
                    foreach ([$rolAdicional1, $rolAdicional2, $rolAdicional3] as $rolAdicional) {
                        if (!empty($rolAdicional)) {
                            $rolNormalizado = $this->normalizeRol($rolAdicional);
                            if ($rolNormalizado) {
                                $rolesAdicionales[] = $rolNormalizado;
                            }
                        }
                    }

                    // Actualizar DNI si está presente (limpiar puntos)
                    if (!empty($dni)) {
                        $dniClean = str_replace('.', '', trim($dni));
                        if (!empty($dniClean)) {
                            $updateData['dni'] = $dniClean;
                        }
                    }

                    // Solo actualizar si hay datos para actualizar
                    if (!empty($updateData) || !empty($rolesAdicionales)) {
                        // Guardar valores anteriores para el log
                        $rolAnterior = $user->rol;
                        $dniAnterior = $user->dni;
                        $rolesAdicionalesAnteriores = DB::table('user_roles')
                            ->where('usu_codigo', $usuCodigo)
                            ->pluck('rol')
                            ->toArray();
                        
                        // Actualizar datos básicos
                        if (!empty($updateData)) {
                            $user->update($updateData);
                        }
                        
                        // Sincronizar roles adicionales
                        if (!empty($rolesAdicionales)) {
                            $user->syncRoles($rolesAdicionales);
                        }
                        
                        $this->successCount++;
                        Log::info("UsersImport: Usuario actualizado correctamente", [
                            'fila' => $rowNumber,
                            'usu_codigo' => $usuCodigo,
                            'usu_descripcion' => $user->usu_descripcion,
                            'rol_principal_anterior' => $rolAnterior,
                            'rol_principal_nuevo' => $updateData['rol'] ?? $rolAnterior,
                            'roles_adicionales_anteriores' => $rolesAdicionalesAnteriores,
                            'roles_adicionales_nuevos' => $rolesAdicionales,
                            'dni_anterior' => $dniAnterior,
                            'dni_nuevo' => $updateData['dni'] ?? $dniAnterior
                        ]);
                    } else {
                        $this->errors[] = "Fila {$rowNumber}: No se proporcionaron datos para actualizar (rol principal, roles adicionales o DNI)";
                        $this->errorCount++;
                        Log::warning("UsersImport: No hay datos para actualizar", [
                            'fila' => $rowNumber,
                            'usu_codigo' => $usuCodigo
                        ]);
                    }

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
            Log::info('UsersImport: Actualización de roles completada', [
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
