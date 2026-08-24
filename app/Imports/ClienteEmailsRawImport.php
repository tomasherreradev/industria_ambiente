<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

/** Solo lee filas del Excel sin transformar encabezados. */
class ClienteEmailsRawImport implements ToCollection
{
    public function collection(Collection $rows): void
    {
    }
}
