<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Facades\Log;

class SectoresTrabajoImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    protected $actualizados = 0;
    protected $noEncontrados = [];
    protected $errores = [];

    /**
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            try {
                // Obtener código de usuario (puede venir como 'codigo_usuario' o 'Código Usuario')
                $codigoUsuario = $row['codigo_usuario'] ?? $row['codigo usuario'] ?? null;
                
                if (empty($codigoUsuario)) {
                    continue;
                }

                // Limpiar el código (quitar espacios)
                $codigoUsuario = trim($codigoUsuario);

                // Obtener el sector
                $sector = $row['sector'] ?? null;

                if (empty($sector)) {
                    Log::warning("Usuario {$codigoUsuario} sin sector asignado en el Excel");
                    continue;
                }

                // Buscar usuario (considerando que puede tener espacios al final)
                $user = User::whereRaw('TRIM(usu_codigo) = ?', [$codigoUsuario])->first();

                if (!$user) {
                    $this->noEncontrados[] = $codigoUsuario;
                    Log::warning("Usuario no encontrado: {$codigoUsuario}");
                    continue;
                }

                // Actualizar sector_trabajo
                $user->sector_trabajo = trim($sector);
                $user->save();
                
                $this->actualizados++;
                Log::info("Sector de trabajo actualizado para {$codigoUsuario}: {$sector}");

            } catch (\Exception $e) {
                $this->errores[] = "Error procesando fila: " . $e->getMessage();
                Log::error("Error en SectoresTrabajoImport: " . $e->getMessage());
            }
        }
    }

    /**
     * Obtener estadísticas de la importación
     */
    public function getStats(): array
    {
        return [
            'actualizados' => $this->actualizados,
            'no_encontrados' => $this->noEncontrados,
            'errores' => $this->errores,
        ];
    }
}
