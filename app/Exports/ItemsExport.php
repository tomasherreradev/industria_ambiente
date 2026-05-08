<?php

namespace App\Exports;

use App\Models\Matriz;
use App\Models\Metodo;
use App\Models\CotioItems;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Exporta determinaciones en el mismo formato que la plantilla de importación.
 */
class ItemsExport implements WithMultipleSheets
{
    protected $search;
    protected $tipo;
    protected $matrizCodigo;

    public function __construct(?string $search = null, ?string $tipo = null, ?string $matrizCodigo = null)
    {
        $this->search = $search;
        $this->tipo = $tipo;
        $this->matrizCodigo = $matrizCodigo;
    }

    public function sheets(): array
    {
        return [
            new ItemsExportDataSheet($this->search, $this->tipo, $this->matrizCodigo),
            new ItemsExportMetodosSheet(),
            new ItemsExportMatricesSheet(),
        ];
    }
}

class ItemsExportDataSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $search;

    protected $tipo;

    protected $matrizCodigo;

    /** @var array<int, Collection> cotio_item_id → códigos de matriz (trim) en pivote */
    protected array $pivotCodigosCache = [];

    public function __construct(?string $search = null, ?string $tipo = null, ?string $matrizCodigo = null)
    {
        $this->search = $search;
        $this->tipo = $tipo;
        $this->matrizCodigo = $matrizCodigo;
    }

    /**
     * Primera marca temporal conocida: asociación a matriz y/o vínculo en cotio_item_component (import ABM).
     */
    protected function fechasImportacionOriginalPorItem(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $porId = array_fill_keys($ids, []);

        $desdeMatriz = DB::table('cotio_items_matriz')
            ->whereIn('cotio_item_id', $ids)
            ->selectRaw('cotio_item_id, MIN(created_at) as primera')
            ->groupBy('cotio_item_id')
            ->pluck('primera', 'cotio_item_id');

        $desdePivotComponente = DB::table('cotio_item_component')
            ->whereIn('componente_id', $ids)
            ->selectRaw('componente_id, MIN(created_at) as primera')
            ->groupBy('componente_id')
            ->pluck('primera', 'componente_id');

        $desdePivotAgrupador = DB::table('cotio_item_component')
            ->whereIn('agrupador_id', $ids)
            ->selectRaw('agrupador_id, MIN(created_at) as primera')
            ->groupBy('agrupador_id')
            ->pluck('primera', 'agrupador_id');

        foreach ($ids as $id) {
            $candidatos = array_filter([
                $desdeMatriz[$id] ?? null,
                $desdePivotComponente[$id] ?? null,
                $desdePivotAgrupador[$id] ?? null,
            ]);
            if ($candidatos === []) {
                $porId[$id] = '';

                continue;
            }
            $minTs = min(array_map(fn ($d) => strtotime((string) $d), $candidatos));
            $porId[$id] = \Carbon\Carbon::createFromTimestamp($minTs)->format('d/m/Y H:i');
        }

        return $porId;
    }

    /**
     * Precarga pivote cotio_items_matriz para muchos ítems en pocas consultas (evita N+1 y reduce memoria en export).
     *
     * @param  list<int>  $itemIds
     */
    protected function prefetchPivotMatrizCodigosForItems(array $itemIds): void
    {
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds))));
        $missing = array_values(array_diff($itemIds, array_keys($this->pivotCodigosCache)));
        if ($missing === []) {
            return;
        }

        foreach ($missing as $id) {
            $this->pivotCodigosCache[$id] = collect();
        }

        foreach (array_chunk($missing, 500) as $chunk) {
            $rows = DB::table('cotio_items_matriz')
                ->whereIn('cotio_item_id', $chunk)
                ->get(['cotio_item_id', 'matriz_codigo']);
            foreach ($rows as $row) {
                $id = (int) $row->cotio_item_id;
                $t = trim((string) $row->matriz_codigo);
                if ($t !== '' && isset($this->pivotCodigosCache[$id])) {
                    $this->pivotCodigosCache[$id]->push($t);
                }
            }
        }

        foreach ($missing as $id) {
            $this->pivotCodigosCache[$id] = $this->pivotCodigosCache[$id]->unique()->values();
        }
    }

    /**
     * Códigos de matriz en pivote (trim), usando caché poblada con prefetchPivotMatrizCodigosForItems.
     */
    protected function codigosMatrizPivotPorItemId(int $cotioItemId): Collection
    {
        if (! array_key_exists($cotioItemId, $this->pivotCodigosCache)) {
            $this->prefetchPivotMatrizCodigosForItems([$cotioItemId]);
        }

        return $this->pivotCodigosCache[$cotioItemId] ?? collect();
    }

    /**
     * Busca la matriz en catálogo por código (TRIM); si no estaba en el mapa en memoria, consulta BD y la agrega al mapa.
     */
    protected function matrizCatalogoPorCodigoTrim(string $cod, Collection $matrizPorTrimCodigo): ?Matriz
    {
        $cod = trim($cod);
        if ($cod === '') {
            return null;
        }

        $m = $matrizPorTrimCodigo->get($cod);
        if ($m) {
            return $m;
        }

        $col = Matriz::query()->getConnection()->getQueryGrammar()->wrap('matriz_codigo');
        $m = Matriz::query()->whereRaw("TRIM({$col}) = ?", [$cod])->first();
        if ($m) {
            $matrizPorTrimCodigo->put(trim((string) $m->matriz_codigo), $m);
        }

        return $m;
    }

    /**
     * Matrices vinculadas al ítem: descripción desde catálogo usando código pivot normalizado.
     * Si no hay pivote pero existe matriz_codigo legacy en cotio_items, se usa una fila sintética.
     * La columna «Tipo» de la exportación debe llevar siempre la descripción humana, nunca el código.
     *
     * @return list<array{codigo: string, descripcion: string}>
     */
    protected function matricesFilaExportPorItem(CotioItems $item, Collection $matrizPorTrimCodigo): array
    {
        $rows = [];
        foreach ($this->codigosMatrizPivotPorItemId((int) $item->id) as $cod) {
            $codTrim = trim((string) $cod);
            $m = $this->matrizCatalogoPorCodigoTrim($codTrim, $matrizPorTrimCodigo);
            $desc = $m ? trim((string) ($m->matriz_descripcion ?? '')) : '';
            $rows[] = [
                'codigo' => $codTrim,
                'descripcion' => $desc,
            ];
        }

        $legacy = trim((string) ($item->matriz_codigo ?? ''));
        if ($rows === [] && $legacy !== '') {
            $m = $this->matrizCatalogoPorCodigoTrim($legacy, $matrizPorTrimCodigo);
            $desc = $m ? trim((string) ($m->matriz_descripcion ?? '')) : '';
            $rows[] = [
                'codigo' => $legacy,
                'descripcion' => $desc,
            ];
        }

        return $rows;
    }

    /**
     * Una fila de exportación alineada a la plantilla de importación (datos del componente).
     *
     * @param  array<string, string>  $fechasImport
     */
    protected function filaExportacionComponente(
        string $tipoMatrizDesc,
        string $agrupadorDesc,
        CotioItems $componente,
        array $fechasImport
    ): array {
        $metodoMuestreo = $componente->metodoMuestreo?->metodo_descripcion ?? '';
        $metodoAnalisis = $componente->metodoAnalitico?->metodo_descripcion ?? '';
        $unidadMedida = $componente->unidad_medida ?? '';
        $limiteDeteccion = $componente->limites_establecidos ?? '';
        $limiteCuantificacion = $componente->limite_cuantificacion !== null ? (string) $componente->limite_cuantificacion : '';
        $precio = $componente->precio !== null ? (string) $componente->precio : '';
        $fechaImport = $fechasImport[$componente->id] ?? '';

        return [
            $tipoMatrizDesc,
            $agrupadorDesc,
            $componente->cotio_descripcion ?? '',
            $metodoMuestreo,
            $metodoAnalisis,
            $unidadMedida,
            $limiteDeteccion,
            $limiteCuantificacion,
            $precio,
            $fechaImport,
        ];
    }

    public function array(): array
    {
        $this->pivotCodigosCache = [];

        $matrizPorTrimCodigo = Matriz::query()->get()->keyBy(fn (Matriz $m) => trim((string) $m->matriz_codigo));

        $query = CotioItems::query()
            ->where('es_muestra', true)
            ->with([
                'componentesAsociados' => function ($q) {
                    $q->with(['metodoAnalitico', 'metodoMuestreo'])
                        ->orderBy('cotio_item_component.orden')
                        ->orderBy('cotio_item_component.id');
                },
            ]);

        if ($this->tipo === 'componente') {
            $query->whereHas('componentesAsociados', function ($q) {
                $q->where('es_muestra', false);
            });
        }

        if ($this->search) {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim($this->search)) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('cotio_descripcion', 'ILIKE', $term)
                    ->orWhereHas('componentesAsociados', function ($sub) use ($term) {
                        $sub->where('cotio_descripcion', 'ILIKE', $term);
                    });
            });
        }

        if ($this->matrizCodigo) {
            $matrizCodigoLimpio = trim((string) $this->matrizCodigo);
            $query->where(function ($q) use ($matrizCodigoLimpio) {
                $q->whereExists(function ($sub) use ($matrizCodigoLimpio) {
                    $sub->select(DB::raw(1))
                        ->from('cotio_items_matriz')
                        ->whereColumn('cotio_items_matriz.cotio_item_id', 'cotio_items.id')
                        ->whereRaw('TRIM(cotio_items_matriz.matriz_codigo) = ?', [$matrizCodigoLimpio]);
                })->orWhereHas('componentesAsociados', function ($sub) use ($matrizCodigoLimpio) {
                    $sub->whereExists(function ($sub2) use ($matrizCodigoLimpio) {
                        $sub2->select(DB::raw(1))
                            ->from('cotio_items_matriz')
                            ->whereColumn('cotio_items_matriz.cotio_item_id', 'cotio_items.id')
                            ->whereRaw('TRIM(cotio_items_matriz.matriz_codigo) = ?', [$matrizCodigoLimpio]);
                    });
                });
            });
        }

        /** @var list<array{0: string, 1: string, 2: int}> Tipo, agrupador, id componente (modelos ligeros hasta el final) */
        $filasLight = [];
        $idsComponentes = [];

        $agrupadores = $query->orderBy('cotio_descripcion', 'asc')->get();

        foreach ($agrupadores as $agr) {
            $componentes = $agr->componentesAsociados;
            if ($componentes->isEmpty()) {
                continue;
            }

            $idsPrefetch = array_merge([(int) $agr->id], $componentes->pluck('id')->map(fn ($id) => (int) $id)->all());
            $this->prefetchPivotMatrizCodigosForItems($idsPrefetch);

            $nombreAgrupador = trim((string) ($agr->cotio_descripcion ?? ''));
            $matricesAgrRows = collect($this->matricesFilaExportPorItem($agr, $matrizPorTrimCodigo))
                ->sortBy('codigo')
                ->values();

            if ($matricesAgrRows->isNotEmpty()) {
                foreach ($matricesAgrRows as $matRow) {
                    $tipoDesc = (string) $matRow['descripcion'];
                    $mCod = (string) $matRow['codigo'];
                    foreach ($componentes as $comp) {
                        $codigosMatComp = $this->codigosMatrizPivotPorItemId((int) $comp->id);
                        if ($codigosMatComp->isNotEmpty() && ! $codigosMatComp->contains($mCod)) {
                            continue;
                        }
                        $filasLight[] = [$tipoDesc, $nombreAgrupador, (int) $comp->id];
                        $idsComponentes[] = (int) $comp->id;
                    }
                }
            } else {
                foreach ($componentes as $comp) {
                    $matsComp = collect($this->matricesFilaExportPorItem($comp, $matrizPorTrimCodigo))->sortBy('codigo')->values();
                    $tipoDesc = $matsComp->isNotEmpty() ? (string) $matsComp->first()['descripcion'] : '';
                    $filasLight[] = [$tipoDesc, $nombreAgrupador, (int) $comp->id];
                    $idsComponentes[] = (int) $comp->id;
                }
            }
        }

        $idsYaExportados = array_values(array_unique($idsComponentes));
        $qResto = CotioItems::query()
            ->where('es_muestra', false);

        if ($idsYaExportados !== []) {
            $qResto->whereNotIn('id', $idsYaExportados);
        }

        if ($this->search) {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim($this->search)) . '%';
            $qResto->where('cotio_descripcion', 'ILIKE', $term);
        }

        if ($this->matrizCodigo) {
            $matrizCodigoLimpio = trim((string) $this->matrizCodigo);
            $qResto->whereExists(function ($sub) use ($matrizCodigoLimpio) {
                $sub->select(DB::raw(1))
                    ->from('cotio_items_matriz')
                    ->whereColumn('cotio_items_matriz.cotio_item_id', 'cotio_items.id')
                    ->whereRaw('TRIM(cotio_items_matriz.matriz_codigo) = ?', [$matrizCodigoLimpio]);
            });
        }

        $restoComponentes = $qResto->orderBy('cotio_descripcion', 'asc')->get();
        $this->prefetchPivotMatrizCodigosForItems($restoComponentes->pluck('id')->map(fn ($id) => (int) $id)->all());
        foreach ($restoComponentes as $comp) {
            $matsC = collect($this->matricesFilaExportPorItem($comp, $matrizPorTrimCodigo))->sortBy('codigo')->values();
            $tipoDesc = $matsC->isNotEmpty() ? (string) $matsC->first()['descripcion'] : '';
            $filasLight[] = [$tipoDesc, '', (int) $comp->id];
            $idsComponentes[] = (int) $comp->id;
        }

        $idsComponentes = array_values(array_unique($idsComponentes));
        $fechasImport = $this->fechasImportacionOriginalPorItem($idsComponentes);

        $compsPorId = collect();
        foreach (array_chunk($idsComponentes, 400) as $chunkIds) {
            $compsPorId = $compsPorId->merge(
                CotioItems::query()
                    ->whereIn('id', $chunkIds)
                    ->with(['metodoAnalitico', 'metodoMuestreo'])
                    ->get()
                    ->keyBy('id')
            );
        }

        $salida = [];
        foreach ($filasLight as $triple) {
            [$tipoDesc, $nombreAgrupador, $cid] = $triple;
            $comp = $compsPorId->get($cid);
            if (! $comp) {
                continue;
            }
            $salida[] = $this->filaExportacionComponente($tipoDesc, $nombreAgrupador, $comp, $fechasImport);
        }

        return $salida;
    }

    public function headings(): array
    {
        return [
            'Tipo',
            'Agrupador',
            'Parámetro',
            'Metodología muestreo',
            'Metodología análisis',
            'Unidades de medición',
            'Límite de detección',
            'Límite de cuantificación',
            'Precio de venta',
            'Fecha importación original',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getRowDimension(1)->setRowHeight(45);
        foreach (range('A', 'J') as $column) {
            $sheet->getStyle($column . '1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 30,
            'C' => 30,
            'D' => 35,
            'E' => 30,
            'F' => 20,
            'G' => 20,
            'H' => 25,
            'I' => 18,
            'J' => 22,
        ];
    }

    public function title(): string
    {
        return 'Datos';
    }
}

class ItemsExportMetodosSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    public function array(): array
    {
        return Metodo::orderBy('metodo_codigo')->get()->map(fn ($m) => [$m->metodo_codigo, $m->metodo_descripcion ?? ''])->toArray();
    }

    public function headings(): array
    {
        return ['Código', 'Descripción'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '70AD47']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 50];
    }

    public function title(): string
    {
        return 'Métodos';
    }
}

class ItemsExportMatricesSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    public function array(): array
    {
        return Matriz::orderBy('matriz_codigo')->get()->map(fn ($m) => [$m->matriz_codigo, $m->matriz_descripcion ?? ''])->toArray();
    }

    public function headings(): array
    {
        return ['Código', 'Descripción'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC000']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 50];
    }

    public function title(): string
    {
        return 'Matrices';
    }
}
