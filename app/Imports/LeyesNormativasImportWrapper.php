<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeReading;

/**
 * Wrapper que fuerza la lectura de la hoja "Datos" de la plantilla.
 * La plantilla tiene 4 hojas: Datos (a rellenar), Leyes Existentes, Métodos, Matrices.
 * Sin este wrapper, Excel lee la primera hoja; si el usuario reordenó hojas,
 * se podía leer "Leyes Existentes" o "Métodos" y todas las filas se saltaban.
 */
class LeyesNormativasImportWrapper implements WithMultipleSheets, SkipsUnknownSheets, WithEvents
{
    protected LeyesNormativasImport $dataImport;

    public function __construct()
    {
        $this->dataImport = new LeyesNormativasImport();
    }

    public function sheets(): array
    {
        // Solo leer la hoja "Datos" (primera hoja de la plantilla). Así no se lee
        // por error "Leyes Existentes", "Métodos" o "Matrices" si están primero.
        return [
            'Datos' => $this->dataImport,
        ];
    }

    /**
     * Configurar el lector de PhpSpreadsheet para que únicamente cargue en memoria la hoja "Datos",
     * evitando procesar el resto de hojas de referencia ("Leyes Existentes", "Métodos", "Matrices")
     * lo cual consume gran cantidad de memoria RAM.
     */
    public function registerEvents(): array
    {
        return [
            BeforeReading::class => function(BeforeReading $event) {
                $event->getReader()->getDelegate()->setLoadSheetsOnly(['Datos']);
            }
        ];
    }

    /**
     * @param string|int $sheetName
     */
    public function onUnknownSheet($sheetName)
    {
        // No hacer nada; las hojas no listadas se ignoran
    }

    public function getErrors(): array
    {
        $errors = $this->dataImport->getErrors();
        if (!$this->dataImport->sheetProcessed) {
            array_unshift($errors,
                "No se encontró la hoja 'Datos'. La plantilla tiene 4 hojas; la que debe completar es la primera, llamada \"Datos\". Descargue la plantilla de nuevo y rellene esa hoja sin cambiar su nombre."
            );
        }
        return $errors;
    }

    public function getSuccessCount(): int
    {
        return $this->dataImport->getSuccessCount();
    }

    public function getErrorCount(): int
    {
        $count = $this->dataImport->getErrorCount();
        if (!$this->dataImport->sheetProcessed) {
            $count++;
        }
        return $count;
    }

    public function getLeyesCreadas(): int
    {
        return $this->dataImport->getLeyesCreadas();
    }

    public function getVariablesAsociadas(): int
    {
        return $this->dataImport->getVariablesAsociadas();
    }

    public function getVariablesActualizadas(): int
    {
        return $this->dataImport->getVariablesActualizadas();
    }
}
