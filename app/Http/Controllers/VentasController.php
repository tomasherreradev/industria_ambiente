<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Models\Ventas;
use App\Models\Coti;
use App\Models\Cotio;
use App\Models\CotioItems;
use App\Models\Clientes;
use App\Models\Matriz;
use App\Models\Divis;
use App\Models\CondicionPago;
use App\Models\ListaPrecio;
use App\Models\Metodo;
use App\Models\MetodoAnalisis;
use App\Models\MetodoMuestreo;
use App\Models\LeyNormativa;
use App\Models\ClienteEmpresaRelacionada;
use App\Models\ClienteRazonSocialFacturacion;
use App\Models\CotiVersion;
use App\Models\ClienteContacto;
use App\Models\Divisa;
use App\Models\User;
use App\Models\CotioInstancia;
use App\Support\CotizacionClienteEtiqueta;
use App\Support\CotizacionPrecioEnsayo;
use App\Support\CotizacionCanalEnsayo;
use App\Support\CotizacionReferenciasFacturacion;
use App\Support\CotizacionResumenEconomico;
use App\Support\CotizacionEdicionBloqueo;
use App\Support\CotizacionNotasGenerales;
use App\Support\AdjuntosArchivoValidacion;
use App\Support\MetodoAnalisisItemCatalogo;
use App\Models\CotioAdjunto;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VentasController extends Controller {
    
    /** Longitud máxima de cotio_descripcion en la tabla cotio (varchar 60) */
    private const COTIO_DESCRIPCION_MAX_LENGTH = 60;

    /** Tope de copias por muestra en listado /ventas (evita cotio_cantidad corruptos) */
    private const VENTAS_LISTADO_MAX_COPIAS_MUESTRA = 200;

    /** @var array{cerrada: int[], proceso: int[]}|null */
    private ?array $cacheEstadosDerivadosVentas = null;

    private ?string $cacheEstadosDerivadosCanal = null;

    /**
     * @return array{total: float, enEspera: float, aprobadas: float, enProceso: float, rechazadas: float, suspendidas: float, cerradas: float, procesoDeriv: float}
     */
    private function montosPorEstadoVacios(): array
    {
        return [
            'total' => 0.0,
            'enEspera' => 0.0,
            'aprobadas' => 0.0,
            'enProceso' => 0.0,
            'rechazadas' => 0.0,
            'suspendidas' => 0.0,
            'cerradas' => 0.0,
            'procesoDeriv' => 0.0,
        ];
    }

    /**
     * @param  int[]  $nums
     */
    private function aplicarWhereInCotiNums(\Illuminate\Database\Eloquent\Builder $query, array $nums): void
    {
        $nums = array_values(array_unique(array_map('intval', $nums)));
        if ($nums === []) {
            $query->whereRaw('1 = 0');

            return;
        }
        if (count($nums) <= 400) {
            $query->whereIn('coti_num', $nums);

            return;
        }
        $query->where(function ($q) use ($nums) {
            foreach (array_chunk($nums, 400) as $chunk) {
                $q->orWhereIn('coti_num', $chunk);
            }
        });
    }

    /**
     * Helper para truncar y padear strings correctamente
     */
    private function truncateAndPad($value, $length, $padChar = ' ')
    {
        if (empty($value)) {
            return null;
        }
        return str_pad(substr($value, 0, $length), $length, $padChar, STR_PAD_RIGHT);
    }

    /**
     * cotio.cotio_codigometodo_analisis referencia metodos_analisis.codigo (FK).
     * Solo se puede persistir un código que exista en esa tabla; los códigos de la
     * tabla legado `metodo` no alcanzan para la FK si no hay fila en metodos_analisis.
     */
    private function resolverCodigoMetodoAnalisisParaForeignKey(?string $codigoRaw): ?string
    {
        if ($codigoRaw === null || trim((string) $codigoRaw) === '') {
            return null;
        }
        $t = trim((string) $codigoRaw);
        $padded = $this->truncateAndPad($t, 15);

        $ma = MetodoAnalisis::query()->where('codigo', $t)->first();
        if ($ma) {
            return $ma->codigo;
        }
        if ($padded !== null) {
            $maPad = MetodoAnalisis::query()->where('codigo', $padded)->first();
            if ($maPad) {
                return $maPad->codigo;
            }
        }
        $maTrim = MetodoAnalisis::query()->whereRaw('trim(codigo) = ?', [$t])->first();
        if ($maTrim) {
            return $maTrim->codigo;
        }

        // Se eliminó la resolución contra la tabla legado `metodo` porque cotio.cotio_codigometodo_analisis
        // tiene una FK estricta hacia metodos_analisis. Si el código solo existe en `metodo`,
        // debe persistirse como null en esta columna para no violar la integridad referencial.
        return null;
    }

    /**
     * Trunca la descripción para cotio_descripcion (varchar 60) respetando UTF-8.
     */
    private function truncateCotioDescripcion(?string $descripcion): string
    {
        if ($descripcion === null || $descripcion === '') {
            return '';
        }
        return mb_substr(trim($descripcion), 0, self::COTIO_DESCRIPCION_MAX_LENGTH);
    }

    private function sanitizeNullableString($value, $length = null)
    {
        if (is_null($value)) {
            return null;
        }

        $sanitized = trim($value);

        if ($sanitized === '') {
            return null;
        }

        if (!is_null($length)) {
            return mb_substr($sanitized, 0, $length);
        }

        return $sanitized;
    }

    private function aplicarReferenciasFacturacionVentas(Request $request, Ventas $cotizacion): void
    {
        $cotizacion->coti_oc_requerido_factura = $request->boolean('coti_oc_requerido_factura');
        $cotizacion->coti_oc_referencia = $this->sanitizeNullableString($request->coti_oc_referencia, 120);
        $rows = CotizacionReferenciasFacturacion::parseRowsFromRequest($request);
        $cotizacion->coti_refs_facturacion_json = count($rows) > 0 ? $rows : null;
        CotizacionReferenciasFacturacion::sincronizarColumnasLegacyDesdeFilas($cotizacion, $rows);
    }

    /**
     * Si la cotización queda aprobada (coti_estado A) y no hay fecha de aprobado, guarda la fecha del día.
     * Si el usuario envió coti_fechaaprobado en el formulario, debe asignarse antes de llamar a este método.
     */
    private function completarCotiFechaAprobadoSiAprobada(Ventas $cotizacion): void
    {
        if (trim((string) ($cotizacion->coti_estado ?? '')) !== 'A') {
            return;
        }
        if ($cotizacion->coti_fechaaprobado) {
            return;
        }
        $cotizacion->coti_fechaaprobado = Carbon::now()->format('Y-m-d');
    }

    private function parseDecimalValue($value)
    {
        if (is_null($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $trim = trim($value);
            // Formato tipo 10.500 o 10.500,50 (miles con punto, decimal con coma)
            if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $trim)) {
                $trim = str_replace('.', '', $trim);
                $trim = str_replace(',', '.', $trim);
                if (is_numeric($trim)) {
                    return (float) $trim;
                }
            }

            $normalized = str_replace(',', '.', $value);
            if (is_numeric($normalized)) {
                return (float) $normalized;
            }

            if (preg_match('/-?\d+(?:\.\d+)?/', $normalized, $matches)) {
                return (float) $matches[0];
            }
        }

        return null;
    }

    /**
     * Cantidad de copias de muestra segura para listado/stats (cotio_cantidad legacy puede venir inflado).
     */
    private function cantidadCopiasMuestraVentasListado($raw): int
    {
        $n = (int) round($this->parseDecimalValue($raw) ?? 1);
        if ($n <= 0) {
            return 1;
        }
        if ($n > self::VENTAS_LISTADO_MAX_COPIAS_MUESTRA) {
            Log::warning('ventas: cotio_cantidad acotada en listado', [
                'original' => $raw,
                'usado' => self::VENTAS_LISTADO_MAX_COPIAS_MUESTRA,
            ]);

            return self::VENTAS_LISTADO_MAX_COPIAS_MUESTRA;
        }

        return $n;
    }

    /**
     * Empresa relacionada del consultor: id en cliente_empresas_relacionadas.
     * coti_cli_empresa se mantiene igual por compatibilidad con código existente.
     *
     * @return array{coti_empresa_rel: ?int, coti_para_empresa_rel: bool, coti_cli_empresa: ?int}
     */
    private function resolverIdsEmpresaRelacionadaParaGuardado(Request $request, ?Clientes $cliente): array
    {
        $raw = $request->input('coti_empresa_rel');
        if ($raw === null || $raw === '') {
            $raw = $request->input('coti_cli_empresa');
        }
        $id = $raw !== null && $raw !== '' ? (int) $raw : null;
        $esConsultor = $cliente && (bool) ($cliente->es_consultor ?? false);

        if (!$esConsultor || !$id || !$cliente) {
            return [
                'coti_empresa_rel' => null,
                'coti_para_empresa_rel' => false,
                'coti_cli_empresa' => null,
            ];
        }

        $codPad = $cliente->cli_codigo;
        $codTrim = trim((string) $cliente->cli_codigo);
        $ok = ClienteEmpresaRelacionada::where('id', $id)
            ->where(function ($q) use ($codPad, $codTrim) {
                $q->where('cli_codigo', $codPad)
                    ->orWhereRaw('TRIM(cli_codigo) = ?', [$codTrim]);
            })
            ->exists();

        if (!$ok) {
            return [
                'coti_empresa_rel' => null,
                'coti_para_empresa_rel' => false,
                'coti_cli_empresa' => null,
            ];
        }

        return [
            'coti_empresa_rel' => $id,
            'coti_para_empresa_rel' => true,
            'coti_cli_empresa' => $id,
        ];
    }

    private const MAX_NOTA_ITEM_CARACTERES = 150;

    /**
     * Limita el texto de una nota de ítem en cotización.
     */
    private function limitarContenidoNotaItem(string $contenido): string
    {
        $contenido = trim($contenido);
        if ($contenido === '') {
            return '';
        }

        return function_exists('mb_substr')
            ? mb_substr($contenido, 0, self::MAX_NOTA_ITEM_CARACTERES)
            : substr($contenido, 0, self::MAX_NOTA_ITEM_CARACTERES);
    }

    /**
     * Convierte nota_contenido del formulario/API a string persistible (JSON o texto).
     */
    private function normalizarNotaContenidoParaPersistencia($valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_array($valor)) {
            $valor = json_encode($valor, JSON_UNESCAPED_UNICODE);
        }
        $str = trim((string) $valor);
        if ($str === '') {
            return null;
        }

        $decoded = json_decode($str, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $list = array_is_list($decoded)
                ? $decoded
                : (isset($decoded['contenido']) || isset($decoded['tipo']) ? [$decoded] : array_values($decoded));

            $out = [];
            foreach ($list as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $cont = $this->limitarContenidoNotaItem((string) ($row['contenido'] ?? ''));
                if ($cont === '') {
                    continue;
                }
                $row['contenido'] = $cont;
                $out[] = $row;
            }

            return $out === [] ? null : json_encode($out, JSON_UNESCAPED_UNICODE);
        }

        $limited = $this->limitarContenidoNotaItem($str);

        return $limited === '' ? null : $limited;
    }

    /**
     * Rol de coordinación por canal (consultoría / ASP / Clarke Fire), sin nivel admin ni ventas.
     */
    private function ventasCanalRestringidoUsuario(): ?string
    {
        return CotizacionCanalEnsayo::soloCanalUsuario(Auth::user());
    }

    private function denegarVentasSiUsuarioSoloCanal(): void
    {
        if ($this->ventasCanalRestringidoUsuario()) {
            abort(403);
        }
    }

    /**
     * Cotizaciones que tienen al menos un ensayo (cotio_subitem=0) del canal indicado.
     */
    private function aplicarFiltroVentasPorCanalEnsayo($query, string $canal): void
    {
        CotizacionCanalEnsayo::aplicarWhereCotiTieneEnsayoDelCanal($query, $canal);
    }

    private function cotizacionTieneEnsayoDeCanal(int $cotiNum, string $canal): bool
    {
        $q = Ventas::query()->where('coti_num', $cotiNum);
        $this->aplicarFiltroVentasPorCanalEnsayo($q, $canal);

        return $q->exists();
    }

    /**
     * Canal del ensayo persistido o inferido (matriz catálogo + descripción).
     *
     * @param  \Illuminate\Support\Collection<string,\App\Models\CotioItems>|array  $agrupadoresCatalogo
     */
    private function resolverCanalCotioEnsayo(Cotio $ensayo, $agrupadoresCatalogo): ?string
    {
        $v = isset($ensayo->cotio_canal_especial) ? trim((string) $ensayo->cotio_canal_especial) : '';
        if ($v !== '') {
            return strtolower($v);
        }

        $matrizDesc = null;
        if ($agrupadoresCatalogo instanceof \Illuminate\Support\Collection) {
            $descClave = Str::lower(trim($ensayo->cotio_descripcion ?? ''));
            $agr = $agrupadoresCatalogo->get($descClave);
            if ($agr && $agr->matrices && $agr->matrices->isNotEmpty()) {
                $matrizDesc = trim((string) ($agr->matrices->first()->matriz_descripcion ?? ''));
            }
        }

        return CotizacionCanalEnsayo::resolverDesdeEnsayoPayload([
            'matriz_descripcion' => $matrizDesc,
            'descripcion' => $ensayo->cotio_descripcion,
        ]);
    }

    private function resolverPrecioExtraEnsayoDesdeCotioRow(?float $cotioPrecioEnsayo, float $sumaComponentesUnitaria, bool $esNuevaLogica = false): float
    {
        return CotizacionPrecioEnsayo::resolverPrecioExtraEnsayoDesdeCotioRow($cotioPrecioEnsayo, $sumaComponentesUnitaria, $esNuevaLogica);
    }

    private function buscarCotioItemComponenteCatalogo(?string $codigoProducto, ?string $descripcion): ?CotioItems
    {
        $codigoProducto = trim((string) ($codigoProducto ?? ''));
        $descripcionComponente = trim((string) ($descripcion ?? ''));

        $codigosGenericos = ['000010000100006', '000010000000000'];

        if ($descripcionComponente !== '') {
            $porDescripcion = CotioItems::componentes()
                ->where('cotio_descripcion', $descripcionComponente)
                ->first();
            if ($porDescripcion) {
                return $porDescripcion;
            }
        }

        if ($codigoProducto !== '' && !in_array($codigoProducto, $codigosGenericos, true)) {
            $candidatos = [$codigoProducto];
            if (is_numeric($codigoProducto)) {
                $sinCeros = ltrim($codigoProducto, '0');
                $candidatos[] = $sinCeros !== '' ? $sinCeros : '0';
                $candidatos[] = (string) (int) $codigoProducto;
            }

            foreach (array_unique($candidatos) as $id) {
                $item = CotioItems::componentes()->find($id);
                if ($item) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * Precio de referencia del componente al abrir ventas/edit.
     * La cotización persistida en cotio es la fuente de verdad; el catálogo solo completa si cotio_precio es 0.
     *
     * @return array{precio: float, precio_minimo_venta: float, analisis_id: int|null}
     */
    private function resolverPrecioReferenciaComponenteParaEdicion(?CotioItems $itemCatalogo, $precioCotioRaw): array
    {
        $precioCotio = (float) ($this->parseDecimalValue($precioCotioRaw) ?? 0);
        $tienePrecioCatalogo = $itemCatalogo
            && $itemCatalogo->precio !== null
            && $itemCatalogo->precio !== '';
        $precioCatalogo = $tienePrecioCatalogo ? (float) $itemCatalogo->precio : 0.0;
        $precioMinimo = $tienePrecioCatalogo ? $precioCatalogo : 5000.0;

        $precioRef = $precioCotio > 0
            ? $precioCotio
            : ($tienePrecioCatalogo ? $precioCatalogo : 0.0);

        return [
            'precio' => max(0.0, $precioRef),
            'precio_minimo_venta' => $precioMinimo,
            'analisis_id' => $itemCatalogo?->id,
        ];
    }

    /**
     * Cotizaciones aprobadas y no canceladas (base para estados derivados Cerrada / Proceso).
     */
    private function baseVentasQueryCotizacionesActivasAprobadas(?string $canal): \Illuminate\Database\Eloquent\Builder
    {
        $q = Ventas::query();
        if ($canal) {
            $this->aplicarFiltroVentasPorCanalEnsayo($q, $canal);
        }

        return $q->where('coti_estado', 'LIKE', 'A%')
            ->where(function ($w) {
                $w->whereNull('cancelada')
                    ->orWhere('cancelada', false);
            });
    }

    /**
     * Fila padre cotio (subitem 0) incluida en el resumen de ventas según canal del usuario.
     */
    private function ventasCotioMuestraPadreCoincideCanal(object $m, ?string $soloCanal): bool
    {
        if (! $soloCanal) {
            return true;
        }

        $canalFila = strtolower(trim((string) ($m->cotio_canal_especial ?? '')));
        $desc = mb_strtolower(trim((string) ($m->cotio_descripcion ?? '')));
        $match = false;
        if ($canalFila !== '') {
            $match = ($canalFila === $soloCanal);
        } elseif ($soloCanal === 'consultoria') {
            $match = str_contains($desc, 'consultoria');
        } elseif ($soloCanal === 'clarke_fire') {
            $match = str_contains($desc, 'clarke fire')
                || str_contains($desc, 'clarke-fire')
                || str_contains($desc, 'clarke_fire')
                || (str_contains($desc, 'clarke') && str_contains($desc, 'fire'));
        } elseif ($soloCanal === 'asp') {
            $match = str_contains($desc, 'asp');
        }

        return $match;
    }

    /**
     * Copia de muestra alineada al detalle “Informes — aprobado / firmado” (requiere enable_inform).
     */
    private function ventasInstanciaCopyInformeListo(?object $inst): bool
    {
        if (! $inst || ! (bool) $inst->enable_inform) {
            return false;
        }

        return (bool) $inst->aprobado_informe || (bool) $inst->firmado;
    }

    /**
     * Copias de muestra esperadas según cotio (subitem 0) y canal.
     *
     * @return array<int, array{num:int, item:int, instance:int}>
     */
    private function clavesCopiasMuestraVentasDesdeCotio(int $num, iterable $lines, ?string $soloCanal): array
    {
        $claves = [];
        foreach ($lines as $m) {
            if (! $this->ventasCotioMuestraPadreCoincideCanal($m, $soloCanal)) {
                continue;
            }
            $cantidad = $this->cantidadCopiasMuestraVentasListado($m->cotio_cantidad ?? 1);
            for ($i = 1; $i <= $cantidad; $i++) {
                $claves[] = [
                    'num' => $num,
                    'item' => (int) $m->cotio_item,
                    'instance' => $i,
                ];
            }
        }

        return $claves;
    }

    /**
     * Solo trae instancias de las copias esperadas (evita cargar decenas de miles de filas huérfanas).
     *
     * @param  array<int, array{num:int, item:int, instance:int}>  $claves
     * @return array<string, object>
     */
    private function fetchInstanciasVentasPorClaves(array $claves): array
    {
        if ($claves === []) {
            return [];
        }

        $instMap = [];
        foreach (array_chunk($claves, 80) as $chunk) {
            $rows = DB::table('cotio_instancias')
                ->where('cotio_subitem', 0)
                ->where(function ($q) use ($chunk) {
                    foreach ($chunk as $c) {
                        $q->orWhere(function ($q2) use ($c) {
                            $q2->where('cotio_numcoti', $c['num'])
                                ->where('cotio_item', $c['item'])
                                ->where('instance_number', $c['instance']);
                        });
                    }
                })
                ->select([
                    'cotio_numcoti',
                    'cotio_item',
                    'instance_number',
                    'enable_inform',
                    'aprobado_informe',
                    'firmado',
                    'enable_muestreo',
                    'enable_ot',
                ])
                ->get();

            foreach ($rows as $inst) {
                $k = sprintf(
                    '%d|%d|0|%d',
                    (int) $inst->cotio_numcoti,
                    (int) $inst->cotio_item,
                    (int) $inst->instance_number
                );
                $instMap[$k] = $inst;
            }
        }

        return $instMap;
    }

    /**
     * Por cotización: total de copias de muestra esperadas (cotio_cantidad) y flags Cerrada / Proceso.
     * Debe alinearse con {@see buildDetalleMuestrasVentasAprobadas} (misma expansión por cantidad).
     * "Proceso" operativo solo si existe al menos una {@see CotioInstancia}; si aún no hay instancias
     * (cotización recién aprobada / lab no generó filas), se muestra como Aprobado.
     *
     * @return array<int, array{total:int, all_approved:bool, has_proceso:bool}>
     */
    private function buildInstanciaStatsForNums(array $cotiNums, ?string $soloCanal = null): array
    {
        $cotiNums = array_values(array_unique(array_map('intval', $cotiNums)));
        if ($cotiNums === []) {
            return [];
        }

        if (count($cotiNums) <= 200) {
            return $this->buildInstanciaStatsForNumsChunk($cotiNums, $soloCanal);
        }

        $out = [];
        foreach (array_chunk($cotiNums, 200) as $chunk) {
            $out += $this->buildInstanciaStatsForNumsChunk($chunk, $soloCanal);
        }

        return $out;
    }

    /**
     * @param  int[]  $cotiNums
     * @return array<int, array{total:int, all_approved:bool, has_proceso:bool}>
     */
    private function buildInstanciaStatsForNumsChunk(array $cotiNums, ?string $soloCanal = null): array
    {
        $cotios = collect();
        foreach (array_chunk($cotiNums, 200) as $chunk) {
            $cotios = $cotios->concat(
                DB::table('cotio')
                    ->whereIn('cotio_numcoti', $chunk)
                    ->where('cotio_subitem', 0)
                    ->select([
                        'cotio_numcoti',
                        'cotio_item',
                        'cotio_cantidad',
                        'cotio_canal_especial',
                        'cotio_descripcion',
                    ])
                    ->orderBy('cotio_item')
                    ->get()
            );
        }
        $cotios = $cotios->groupBy(fn ($r) => (int) $r->cotio_numcoti);

        $todasLasClaves = [];
        foreach ($cotiNums as $num) {
            $lines = $cotios->get($num, collect());
            foreach ($this->clavesCopiasMuestraVentasDesdeCotio($num, $lines, $soloCanal) as $clave) {
                $todasLasClaves[] = $clave;
            }
        }
        $instMap = $this->fetchInstanciasVentasPorClaves($todasLasClaves);

        $out = [];
        foreach ($cotiNums as $num) {
            $lines = collect($cotios->get($num, collect()));
            $expected = 0;
            $approvedCopies = 0;
            $hayAlgunaInstancia = false;

            foreach ($lines as $m) {
                if (! $this->ventasCotioMuestraPadreCoincideCanal($m, $soloCanal)) {
                    continue;
                }
                $cantidad = $this->cantidadCopiasMuestraVentasListado($m->cotio_cantidad ?? 1);
                for ($i = 1; $i <= $cantidad; $i++) {
                    $expected++;
                    $k = sprintf('%d|%d|%d|%d', $num, (int) $m->cotio_item, 0, $i);
                    $inst = $instMap[$k] ?? null;
                    if ($inst !== null) {
                        $hayAlgunaInstancia = true;
                    }
                    if ($this->ventasInstanciaCopyInformeListo($inst)) {
                        $approvedCopies++;
                    }
                }
            }

            $allApproved = $expected > 0 && $approvedCopies === $expected;
            $hasProceso = $expected > 0 && ! $allApproved && $hayAlgunaInstancia;

            $out[$num] = [
                'total' => $expected,
                'all_approved' => $allApproved,
                'has_proceso' => $hasProceso,
            ];
        }

        return $out;
    }

    /**
     * @return array{cerrada: int[], proceso: int[]}
     */
    private function cotizacionesEstadosDerivados(?string $canal): array
    {
        if ($this->cacheEstadosDerivadosVentas !== null && $this->cacheEstadosDerivadosCanal === $canal) {
            return $this->cacheEstadosDerivadosVentas;
        }

        $nums = $this->baseVentasQueryCotizacionesActivasAprobadas($canal)->pluck('coti_num')->all();
        if ($nums === []) {
            $this->cacheEstadosDerivadosVentas = ['cerrada' => [], 'proceso' => []];
            $this->cacheEstadosDerivadosCanal = $canal;

            return $this->cacheEstadosDerivadosVentas;
        }

        $stats = $this->buildInstanciaStatsForNums($nums, $canal);
        $cerrada = [];
        $proceso = [];
        foreach ($nums as $n) {
            $n = (int) $n;
            $st = $stats[$n] ?? null;
            if (! $st) {
                continue;
            }
            if ($st['total'] > 0 && $st['all_approved']) {
                $cerrada[] = $n;
            }
            if ($st['has_proceso']) {
                $proceso[] = $n;
            }
        }

        $this->cacheEstadosDerivadosVentas = ['cerrada' => $cerrada, 'proceso' => $proceso];
        $this->cacheEstadosDerivadosCanal = $canal;

        return $this->cacheEstadosDerivadosVentas;
    }

    /**
     * @return int[]
     */
    private function cotizacionesNumsEstadoCerrada(?string $canal): array
    {
        return $this->cotizacionesEstadosDerivados($canal)['cerrada'];
    }

    /**
     * Números de cotización en estado derivado Proceso: hay muestras (por cantidad), existe al menos una instancia
     * y no todas las copias tienen informe listo.
     *
     * @return int[]
     */
    private function cotizacionesNumsEstadoProceso(?string $canal): array
    {
        return $this->cotizacionesEstadosDerivados($canal)['proceso'];
    }

    /**
     * Contadores para tarjetas del listado /ventas (respeta canal del usuario si aplica).
     *
     * @return array{total:int,enEspera:int,aprobadas:int,enProceso:int,rechazadas:int,suspendidas:int,cerrada:int,procesoDeriv:int}
     */
    private function conteosTarjetasVentas(?string $canal, bool $incluirEstadosDerivados = false): array
    {
        $q = Ventas::query();
        if ($canal) {
            $this->aplicarFiltroVentasPorCanalEnsayo($q, $canal);
        }

        $conteos = [
            'total' => (clone $q)->count(),
            'enEspera' => (clone $q)->where('coti_estado', 'LIKE', 'E%')->count(),
            'aprobadas' => (clone $q)->where('coti_estado', 'LIKE', 'A%')->count(),
            'enProceso' => (clone $q)->where('coti_estado', 'LIKE', 'P%')->count(),
            'rechazadas' => (clone $q)->where('coti_estado', 'LIKE', 'R%')->count(),
            'suspendidas' => (clone $q)->where('coti_estado', 'LIKE', 'S%')->count(),
            'cerrada' => 0,
            'procesoDeriv' => 0,
        ];

        if ($incluirEstadosDerivados) {
            $derivados = $this->cotizacionesEstadosDerivados($canal);
            $conteos['cerrada'] = count($derivados['cerrada']);
            $conteos['procesoDeriv'] = count($derivados['proceso']);
        }

        return $conteos;
    }

    /**
     * Texto y clase CSS del badge de estado en el listado (incluye derivados Cerrada / Proceso).
     *
     * @param  array<int, array{total:int, all_approved:bool, has_proceso:bool}>  $instStats
     * @return array{texto: string, class: string}
     */
    private function resolverBadgeEstadoListadoVentas(Ventas $c, array $instStats): array
    {
        if (! empty($c->cancelada)) {
            return ['texto' => 'Cancelado', 'class' => 'estado-C'];
        }

        $est = trim((string) ($c->coti_estado ?? ''));
        $letter = $est !== '' ? strtoupper($est[0]) : 'E';

        if ($letter === 'S') {
            return ['texto' => 'Suspendida', 'class' => 'estado-S'];
        }
        if ($letter === 'R') {
            return ['texto' => 'Rechazado', 'class' => 'estado-R'];
        }
        if ($letter === 'E') {
            return ['texto' => 'En Espera', 'class' => 'estado-E'];
        }
        if ($letter === 'P') {
            return ['texto' => 'En Proceso', 'class' => 'estado-P'];
        }
        if ($letter === 'A') {
            $num = (int) $c->coti_num;
            $st = $instStats[$num] ?? null;
            if ($st && $st['total'] > 0 && $st['all_approved']) {
                return ['texto' => 'Cerrada', 'class' => 'estado-CERR'];
            }
            if ($st && $st['total'] > 0 && ! $st['all_approved']) {
                return ['texto' => 'Proceso', 'class' => 'estado-PROC'];
            }

            return ['texto' => 'Aprobado', 'class' => 'estado-A'];
        }

        return ['texto' => 'En Espera', 'class' => 'estado-E'];
    }

    /**
     * @return int[]
     */
    private function numerosCotiEnQueryFiltrada(\Illuminate\Database\Eloquent\Builder $baseQuery): array
    {
        return (clone $baseQuery)->pluck('coti_num')->all();
    }

    public function index(Request $request)
    {
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '256M');
        }

        $estadoFiltroActual = (string) $request->get('estado', '');
        $necesitaEstadosDerivados = in_array($estadoFiltroActual, ['_CERRADA', '_PROCESO'], true);

        // Obtener clientes para el filtro (solo columnas necesarias)
        $clientes = Clientes::where('cli_estado', true)
            ->soloPrincipales()
            ->orderBy('cli_razonsocial')
            ->get(['cli_codigo', 'cli_razonsocial', 'cli_fantasia']);

        // Sucursales para el filtro (dependen del cliente seleccionado)
        $sucursales = collect();
        if ($request->filled('cliente')) {
            $cliCodigo = trim((string) $request->cliente);
            $clienteSel = Clientes::whereRaw('LTRIM(RTRIM(cli_codigo)) = ?', [$cliCodigo])->first();
            if ($clienteSel) {
                // Relación legacy: sucursales = mismos datos de razón social sin CUIT real
                $sucursales = $clienteSel->sucursales()
                    ->where('cli_estado', true)
                    ->orderBy('cli_codigo')
                    ->get();
            }
        }

        // Obtener vendedores para el filtro (usuarios con rol ventas activos)
        // Nota: no depende de que las cotizaciones tengan coti_responsable cargado.
        $vendedores = User::query()
            ->where('usu_estado', true)
            ->where('rol', 'ventas')
            ->orderBy('usu_descripcion')
            ->get();
        
        // Construir query con filtros
        $query = Ventas::query();

        $canalVistaVentas = $this->ventasCanalRestringidoUsuario();
        if ($canalVistaVentas) {
            $this->aplicarFiltroVentasPorCanalEnsayo($query, $canalVistaVentas);
        }
        
        // Filtro por cliente
        if ($request->filled('cliente')) {
            $query->where('coti_codigocli', 'LIKE', $request->cliente . '%');
        }

        // Filtro por sucursal destinataria (coti_codigosuc)
        if ($request->filled('sucursal')) {
            $suc = trim((string) $request->sucursal);
            if ($suc === '_SIN_') {
                $query->where(function ($q) {
                    $q->whereNull('coti_codigosuc')
                      ->orWhereRaw("LTRIM(RTRIM(coti_codigosuc)) = ''");
                });
            } else {
                $query->whereRaw('LTRIM(RTRIM(coti_codigosuc)) = ?', [$suc]);
            }
        }
        
        // Filtro por estado (incluye derivados Cerrada / Proceso desde cotio_instancias)
        if ($request->filled('estado')) {
            $est = $request->estado;
            if ($est === '_CERRADA') {
                $lista = $this->cotizacionesNumsEstadoCerrada($canalVistaVentas);
                if ($lista === []) {
                    $query->whereRaw('1 = 0');
                } else {
                    $this->aplicarWhereInCotiNums($query, $lista);
                }
            } elseif ($est === '_PROCESO') {
                $lista = $this->cotizacionesNumsEstadoProceso($canalVistaVentas);
                if ($lista === []) {
                    $query->whereRaw('1 = 0');
                } else {
                    $this->aplicarWhereInCotiNums($query, $lista);
                }
            } else {
                $query->where('coti_estado', 'LIKE', $est . '%');
            }
        }

        // Filtro por vendedor (responsable o creador)
        if ($request->filled('vendedor')) {
            $vend = trim((string) $request->vendedor);
            $query->where(function ($q) use ($vend) {
                // En tablas legacy algunos campos vienen con padding y/o espacios.
                $q->whereRaw('LTRIM(RTRIM(coti_responsable)) = ?', [$vend])
                  ->orWhereRaw('LTRIM(RTRIM(coti_creador)) = ?', [$vend]);
            });
        }
        
        // Filtro por fecha desde
        if ($request->filled('fecha_desde')) {
            $query->whereDate('coti_fechaalta', '>=', $request->fecha_desde);
        }
        
        // Filtro por fecha hasta
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('coti_fechaalta', '<=', $request->fecha_hasta);
        }
        
        // Ordenar y paginar
        $cotizaciones = $query->with(['cliente', 'empresaRelacionada', 'sucursal'])
            ->orderBy('coti_num', 'desc')
            ->paginate(20)
            ->withQueryString(); // Mantener filtros en la paginación

        CotizacionClienteEtiqueta::precargarEmpresasRelacionadas($cotizaciones->getCollection());

        $ventasInstanciaStats = [];
        try {
            $ventasInstanciaStats = $this->buildInstanciaStatsForNums(
                $cotizaciones->getCollection()->pluck('coti_num')->all(),
                $canalVistaVentas
            );
        } catch (\Throwable $e) {
            Log::error('ventas.index buildInstanciaStatsForNums: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $conteosVentasTarjetas = [
            'total' => 0,
            'enEspera' => 0,
            'aprobadas' => 0,
            'enProceso' => 0,
            'rechazadas' => 0,
            'suspendidas' => 0,
            'cerrada' => 0,
            'procesoDeriv' => 0,
        ];
        try {
            $conteosVentasTarjetas = $this->conteosTarjetasVentas($canalVistaVentas, $necesitaEstadosDerivados);
        } catch (\Throwable $e) {
            Log::error('ventas.index conteosTarjetasVentas: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $ventasBadgesListado = [];
        foreach ($cotizaciones as $c) {
            $ventasBadgesListado[$c->coti_num] = $this->resolverBadgeEstadoListadoVentas($c, $ventasInstanciaStats);
        }

        // Detalle muestra/ensayo por cotización aprobada (acordeón en vista)
        $detalleMuestrasVentas = [];
        try {
            $detalleMuestrasVentas = $this->buildDetalleMuestrasVentasAprobadas($cotizaciones->getCollection(), $canalVistaVentas);
        } catch (\Throwable $e) {
            Log::error('ventas.index buildDetalleMuestrasVentasAprobadas: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $montosPorEstado = $this->montosPorEstadoVacios();
        try {
            $montosPorEstado = $this->calcularMontosPorEstado($request, $necesitaEstadosDerivados);
        } catch (\Throwable $e) {
            Log::error('ventas.index calcularMontosPorEstado: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $estadoFiltro = $estadoFiltroActual;
        $montoMostrar = match ($estadoFiltro) {
            'E' => $montosPorEstado['enEspera'],
            'A' => $montosPorEstado['aprobadas'],
            'P' => $montosPorEstado['enProceso'],
            'R' => $montosPorEstado['rechazadas'],
            'S' => $montosPorEstado['suspendidas'] ?? $montosPorEstado['total'],
            '_CERRADA' => $montosPorEstado['cerradas'] ?? $montosPorEstado['total'],
            '_PROCESO' => $montosPorEstado['procesoDeriv'] ?? $montosPorEstado['total'],
            default => $montosPorEstado['total'],
        };

        return View::make('ventas.index', compact(
            'cotizaciones',
            'clientes',
            'sucursales',
            'vendedores',
            'montosPorEstado',
            'montoMostrar',
            'detalleMuestrasVentas',
            'canalVistaVentas',
            'ventasInstanciaStats',
            'conteosVentasTarjetas',
            'ventasBadgesListado'
        ));
    }

    /**
     * Lista de filas (muestra + ensayos por copia) con estado operativo para cotizaciones aprobadas.
     * Lab directo sin instancia se muestra como análisis pendiente (misma categoría que análisis).
     */
    private function buildDetalleMuestrasVentasAprobadas($cotizaciones, ?string $soloCanal = null): array
    {
        $aprobadas = collect($cotizaciones)->filter(function ($c) {
            $estado = trim((string) ($c->coti_estado ?? ''));
            return $estado !== '' && strtoupper($estado[0]) === 'A';
        })->values();

        if ($aprobadas->isEmpty()) {
            return [];
        }

        $cotiNums = $aprobadas->pluck('coti_num')->map(fn ($n) => (int) $n)->values()->all();

        $cotios = DB::table('cotio')
            ->whereIn('cotio_numcoti', $cotiNums)
            ->where('cotio_subitem', 0)
            ->select([
                'cotio_numcoti',
                'cotio_item',
                'cotio_subitem',
                'cotio_cantidad',
                'cotio_descripcion',
                'lleva_muestreo',
                'cotio_canal_especial',
            ])
            ->orderBy('cotio_item')
            ->get()
            ->groupBy(fn ($r) => (int) $r->cotio_numcoti);

        $todasLasClaves = [];
        foreach ($aprobadas as $coti) {
            $num = (int) $coti->coti_num;
            $lines = $cotios->get($num, collect());
            foreach ($this->clavesCopiasMuestraVentasDesdeCotio($num, $lines, $soloCanal) as $clave) {
                $todasLasClaves[] = $clave;
            }
        }
        $instMap = $this->fetchInstanciasVentasPorClaves($todasLasClaves);

        $out = [];
        foreach ($aprobadas as $coti) {
            $num = (int) $coti->coti_num;
            $lines = collect($cotios->get($num, collect()));
            $rows = [];

            foreach ($lines->sortBy('cotio_item') as $m) {
                if (! $this->ventasCotioMuestraPadreCoincideCanal($m, $soloCanal)) {
                    continue;
                }
                $cantidad = $this->cantidadCopiasMuestraVentasListado($m->cotio_cantidad ?? 1);
                $esLabDirecto = $this->esLabDirectoMuestraRow($m);
                for ($i = 1; $i <= $cantidad; $i++) {
                    $k = sprintf('%d|%d|%d|%d', $num, (int) $m->cotio_item, 0, $i);
                    $inst = $instMap[$k] ?? null;
                    $rows[] = [
                        'tipo' => 'muestra',
                        'titulo' => trim((string) ($m->cotio_descripcion ?? '')) ?: 'Muestra',
                        'copia' => $cantidad > 1 ? 'Copia '.$i : null,
                        'estado' => $this->labelEstadoInstanciaVentas($inst, $esLabDirecto, 'muestra'),
                    ];
                }
            }

            if (count($rows) > 0) {
                $out[$num] = $rows;
            }
        }

        return $out;
    }

    private function esLabDirectoMuestraRow(object $cotioRow): bool
    {
        $lleva = $cotioRow->lleva_muestreo ?? null;

        return $lleva === false || $lleva === 0 || $lleva === '0';
    }

    private function labelEstadoInstanciaVentas(?object $inst, bool $esLabDirecto, string $tipo): string
    {
        if ($inst) {
            if ($inst->enable_inform) {
                if ($inst->firmado) {
                    return 'Informes — firmado';
                }
                if ($inst->aprobado_informe) {
                    return 'Informes — aprobado';
                }

                return 'Informes';
            }
            if ($tipo === 'muestra' && ($inst->enable_muestreo ?? false)) {
                return 'Muestreo';
            }
            if ($inst->enable_ot ?? false) {
                return 'Análisis';
            }
            if ($tipo !== 'muestra' && ($inst->enable_muestreo ?? false)) {
                return 'Muestreo';
            }

            return 'Pendiente';
        }
        if ($esLabDirecto) {
            return 'Análisis (pendiente coordinar)';
        }

        return 'Pendiente (muestreo)';
    }

    /**
     * Montos del listado: suma coti_cuota_monto_total guardado (sin recalcular cotio en memoria).
     */
    private function calcularMontosPorEstado(Request $request, bool $incluirDerivados = false): array
    {
        $vacío = $this->montosPorEstadoVacios();

        $baseQuery = Ventas::query();

        if ($request->filled('cliente')) {
            $baseQuery->where('coti_codigocli', 'LIKE', $request->cliente . '%');
        }

        if ($request->filled('vendedor')) {
            $vend = trim((string) $request->vendedor);
            $baseQuery->where(function ($q) use ($vend) {
                $q->whereRaw('LTRIM(RTRIM(coti_responsable)) = ?', [$vend])
                  ->orWhereRaw('LTRIM(RTRIM(coti_creador)) = ?', [$vend]);
            });
        }

        if ($request->filled('fecha_desde')) {
            $baseQuery->whereDate('coti_fechaalta', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $baseQuery->whereDate('coti_fechaalta', '<=', $request->fecha_hasta);
        }

        $canalMontosUsuario = $this->ventasCanalRestringidoUsuario();
        if ($canalMontosUsuario) {
            $this->aplicarFiltroVentasPorCanalEnsayo($baseQuery, $canalMontosUsuario);
        }

        $resultado = $vacío;
        $montosPorNum = [];

        foreach ((clone $baseQuery)
            ->select(['coti_num', 'coti_estado', 'coti_cuota_monto_total'])
            ->orderBy('coti_num')
            ->cursor() as $coti) {
            $num = (int) $coti->coti_num;
            $monto = max(0.0, (float) ($coti->coti_cuota_monto_total ?? 0));
            $montosPorNum[$num] = $monto;
            $resultado['total'] += $monto;

            $est = trim((string) ($coti->coti_estado ?? ''));
            $letter = $est !== '' ? strtoupper($est[0]) : 'E';

            match ($letter) {
                'E' => $resultado['enEspera'] += $monto,
                'A' => $resultado['aprobadas'] += $monto,
                'P' => $resultado['enProceso'] += $monto,
                'R' => $resultado['rechazadas'] += $monto,
                'S' => $resultado['suspendidas'] += $monto,
                default => null,
            };
        }

        if ($incluirDerivados) {
            $derivados = $this->cotizacionesEstadosDerivados($canalMontosUsuario);
            foreach ($derivados['cerrada'] as $n) {
                if (isset($montosPorNum[$n])) {
                    $resultado['cerradas'] += $montosPorNum[$n];
                }
            }
            foreach ($derivados['proceso'] as $n) {
                if (isset($montosPorNum[$n])) {
                    $resultado['procesoDeriv'] += $montosPorNum[$n];
                }
            }
        }

        foreach ($resultado as $clave => $valor) {
            $resultado[$clave] = round($valor, 2);
        }

        return $resultado;
    }

    public function create()
    {
        $this->denegarVentasSiUsuarioSoloCanal();

        // Cargar datos para los selectores
        try {
            $matrices = Matriz::orderBy('matriz_descripcion')->get();
        } catch (\Exception $e) {
            Log::warning('Error cargando matrices:', ['error' => $e->getMessage()]);
            $matrices = collect();
        }

        try {
            $sectores = Divis::orderBy('divis_descripcion')->get();
        } catch (\Exception $e) {
            Log::warning('Error cargando sectores:', ['error' => $e->getMessage()]);
            $sectores = collect();
        }

        try {
            $condicionesPago = CondicionPago::where('pag_estado', true)
                ->orderBy('pag_descripcion')
                ->get();
        } catch (\Exception $e) {
            Log::warning('Error cargando condiciones de pago:', ['error' => $e->getMessage()]);
            $condicionesPago = collect();
        }

        try {
            $sectoresCliente = Divis::where('divis_lab', true)
                ->orderBy('divis_descripcion')
                ->get();
        } catch (\Exception $e) {
            Log::warning('Error cargando sectores de cliente:', ['error' => $e->getMessage()]);
            $sectoresCliente = collect();
        }

        try {
            $listasPrecios = ListaPrecio::where('lp_estado', true)
                ->orderBy('lp_descripcion')
                ->get();
        } catch (\Exception $e) {
            Log::warning('Error cargando listas de precios:', ['error' => $e->getMessage()]);
            $listasPrecios = collect([
                (object)['lp_codigo' => 'UNO  ', 'lp_descripcion' => 'Lista Principal'],
                (object)['lp_codigo' => 'DOS  ', 'lp_descripcion' => 'Lista Secundaria'],
            ]);
        }

        try {
            $divisas = Divisa::orderBy('divisa_codigo')->get();
        } catch (\Exception $e) {
            Log::warning('Error cargando divisas:', ['error' => $e->getMessage()]);
            $divisas = collect();
        }

        $cotizacionConfig = [
            'modo' => 'create',
            'puedeEditar' => true,
            'ensayosIniciales' => [],
            'componentesIniciales' => [],
            'coti_req_cadena_custodia_relacionada' => false,
        ];

        return View::make('ventas.create', compact(
            'matrices',
            'sectores',
            'condicionesPago',
            'listasPrecios',
            'cotizacionConfig',
            'sectoresCliente',
            'divisas'
        ));
    }

    public function store(Request $request)
    {
        try {
            $this->denegarVentasSiUsuarioSoloCanal();

            Log::info('=== INICIO CREACIÓN DE COTIZACIÓN ===');
            $ensayosRaw = $request->input('ensayos_data');
            $componentesRaw = $request->input('componentes_data');
            Log::info('Store ventas - payload items/componentes', [
                'tiene_ensayos_data' => $request->has('ensayos_data'),
                'ensayos_data_len' => $ensayosRaw ? strlen($ensayosRaw) : 0,
                'tiene_componentes_data' => $request->has('componentes_data'),
                'componentes_data_len' => $componentesRaw ? strlen($componentesRaw) : 0,
                'componentes_data_preview' => $componentesRaw ? substr($componentesRaw, 0, 400) : '(vacío)',
            ]);
            Log::info('Datos recibidos en store:', $request->all());

            // Validar datos básicos
            Log::info('Iniciando validación de datos básicos');
            Log::info('Campos para validación:', [
                'coti_codigocli' => $request->coti_codigocli,
                'coti_fechaalta' => $request->coti_fechaalta,
            ]);
            
            $request->validate([
                'coti_codigocli' => 'required|string',
                'coti_fechaalta' => 'required|date',
            ], [
                'coti_codigocli.required' => 'El código de cliente es obligatorio',
                'coti_fechaalta.required' => 'La fecha de alta es obligatoria',
            ]);

            $erroresRefs = CotizacionReferenciasFacturacion::validarRequest($request);
            if ($erroresRefs !== []) {
                throw ValidationException::withMessages($erroresRefs);
            }
            
            Log::info('Validación completada exitosamente');

            // Generar número de cotización automático
            Log::info('Generando número de cotización');
            $ultimaCotizacion = Ventas::orderBy('coti_num', 'desc')->first();
            $nuevoNumero = $ultimaCotizacion ? intval($ultimaCotizacion->coti_num) + 1 : 1;
            Log::info('Número de cotización generado:', ['numero' => $nuevoNumero]);

            // Crear la cotización
            Log::info('Creando instancia de cotización');
            $cotizacion = new Ventas();

            // Obtener datos del cliente
            Log::info('Obteniendo datos del cliente');
            $codigoCliente = $this->truncateAndPad($request->coti_codigocli, 10);
            $cliente = Clientes::where('cli_codigo', $codigoCliente)
                ->where('cli_estado', true)
                ->first();
                
            if (!$cliente) {
                Log::error('Cliente no encontrado:', ['codigo' => $request->coti_codigocli]);
                throw new \Exception('Cliente no encontrado con código: ' . $request->coti_codigocli);
            }
            
            Log::info('Cliente encontrado:', [
                'codigo' => trim($cliente->cli_codigo),
                'razon_social' => trim($cliente->cli_razonsocial),
                'condicion_pago' => $cliente->cli_codigopag ? trim($cliente->cli_codigopag) : null,
                'lista_precios' => $cliente->cli_codigolp ? trim($cliente->cli_codigolp) : null,
            ]);

            // Campos principales
            Log::info('Asignando campos principales');
            $cotizacion->coti_num = $nuevoNumero;
            $cotizacion->coti_para = $this->sanitizeNullableString($request->coti_para, null);
            $relIds = $this->resolverIdsEmpresaRelacionadaParaGuardado($request, $cliente);
            $cotizacion->coti_empresa_rel = $relIds['coti_empresa_rel'];
            $cotizacion->coti_para_empresa_rel = $relIds['coti_para_empresa_rel'];
            $cotizacion->coti_cli_empresa = $relIds['coti_cli_empresa'];
            $cotizacion->coti_descripcion = $request->coti_descripcion;
            $cotizacion->coti_codigocli = $codigoCliente;
            $cotizacion->coti_fechaalta = $request->coti_fechaalta ?: now()->format('Y-m-d');
            $cotizacion->coti_fechafin = $request->coti_fechafin;
            // Sector (se valida más adelante contra divis)
            $cotizacion->coti_sector = null;
            // Mapear estados del formulario a códigos de BD
            $estadosMap = [
                'En Espera' => 'E    ',
                'Aprobado' => 'A    ',
                'Rechazado' => 'R    ',
                'En Proceso' => 'P    ',
                'Suspendida' => 'S    ',
            ];
            
            $estadoFormulario = $request->coti_estado ?: 'En Espera';
            $cotizacion->coti_estado = $estadosMap[$estadoFormulario] ?? 'E    ';
            
            Log::info('Estado mapeado:', [
                'estado_formulario' => $estadoFormulario,
                'estado_bd' => $cotizacion->coti_estado
            ]);
            // Campo coti_vigencia eliminado - no existe en la tabla

            // Campos de gestión
            Log::info('Asignando campos de gestión');
            $cotizacion->coti_responsable = $this->truncateAndPad($request->coti_responsable, 20);
            // Creador: usuario que genera la cotización (solo en creación)
            $cotizacion->coti_creador = $this->truncateAndPad(Auth::user()?->usu_codigo, 20);
            $cotizacion->coti_aprobo = $this->truncateAndPad($request->coti_aprobo, 20);
            $cotizacion->coti_fechaaprobado = $request->input('coti_fechaaprobado');
            $this->completarCotiFechaAprobadoSiAprobada($cotizacion);
            $cotizacion->coti_fechaencurso = $request->coti_fechaencurso;
            $cotizacion->coti_fechaaltatecnica = $request->coti_fechaaltatecnica;

            // Campos técnicos - Solo asignar campos que existen en la tabla
            Log::info('Asignando campos técnicos');
            Log::info('Valores técnicos recibidos:', [
                'coti_codigomatriz' => $request->coti_codigomatriz,
            ]);
            
            // Solo asignar coti_codigomatriz que sí existe en la tabla
            $cotizacion->coti_codigomatriz = $this->truncateAndPad($request->coti_codigomatriz, 15);
            
            Log::info('Valores técnicos asignados:', [
                'coti_codigomatriz' => "'" . $cotizacion->coti_codigomatriz . "'",
            ]);

            // Campos de empresa/cliente (usar datos del cliente como base)
            Log::info('=== ASIGNANDO CAMPOS DE EMPRESA ===');
            Log::info('Datos de empresa recibidos:', [
                'coti_empresa' => $request->coti_empresa,
                'coti_establecimiento' => $request->coti_establecimiento,
                'coti_contacto' => $request->coti_contacto,
                'coti_contacto_tipo1' => $request->coti_contacto_tipo1,
                'coti_contacto2' => $request->coti_contacto2,
                'coti_mail2' => $request->coti_mail2,
                'coti_telefono2' => $request->coti_telefono2,
                'coti_contacto_tipo2' => $request->coti_contacto_tipo2,
                'coti_contacto3' => $request->coti_contacto3,
                'coti_mail3' => $request->coti_mail3,
                'coti_telefono3' => $request->coti_telefono3,
                'coti_contacto_tipo3' => $request->coti_contacto_tipo3,
                'coti_contacto4' => $request->coti_contacto4,
                'coti_mail4' => $request->coti_mail4,
                'coti_telefono4' => $request->coti_telefono4,
                'coti_contacto_tipo4' => $request->coti_contacto_tipo4,
                'coti_direccioncli' => $request->coti_direccioncli,
                'coti_localidad' => $request->coti_localidad,
                'coti_partido' => $request->coti_partido,
                'coti_cuit' => $request->coti_cuit,
                'coti_codigopostal' => $request->coti_codigopostal,
                'coti_telefono' => $request->coti_telefono,
            ]);
            
            // Usar datos del formulario si están presentes, sino usar datos del cliente
            // Aplicar truncamiento a campos con límites de caracteres
            $cotizacion->coti_empresa = $this->sanitizeNullableString(
                $request->coti_empresa ?: trim($cliente->cli_razonsocial),
                50
            );
            $cotizacion->coti_establecimiento = $this->sanitizeNullableString(
                $request->coti_establecimiento,
                50
            );
            $cotizacion->coti_contacto = $this->sanitizeNullableString(
                $request->coti_contacto,
                120
            ) ?: ($cliente->cli_contacto ? trim($cliente->cli_contacto) : null);
            $cotizacion->coti_direccioncli = $this->sanitizeNullableString(
                $request->coti_direccioncli ?: 
                ($cliente->cli_direccion ? trim($cliente->cli_direccion) : null),
                50
            );
            $cotizacion->coti_localidad = $this->sanitizeNullableString(
                $request->coti_localidad ?: 
                ($cliente->cli_localidad ? trim($cliente->cli_localidad) : null),
                50
            );
            $cotizacion->coti_partido = $this->sanitizeNullableString($request->coti_partido, 50);
            $cotizacion->coti_cuit = $this->sanitizeNullableString($request->coti_cuit ?: $cliente->cli_cuit, 13);
            $cotizacion->coti_codigopostal = $this->sanitizeNullableString(
                $request->coti_codigopostal ?: 
                ($cliente->cli_codigopostal ? trim($cliente->cli_codigopostal) : null),
                10
            );
            $cotizacion->coti_telefono = $request->coti_telefono ?: $cliente->cli_telefono;
            $cotizacion->coti_mail1 = $this->sanitizeNullableString(
                $request->coti_mail1,
                120
            ) ?: ($cliente->cli_email ? trim($cliente->cli_email) : null);
            $cotizacion->coti_contacto_tipo1 = $this->sanitizeNullableString($request->coti_contacto_tipo1, 30);
            $cotizacion->coti_contacto2 = $this->sanitizeNullableString($request->coti_contacto2, 120);
            $cotizacion->coti_mail2 = $this->sanitizeNullableString($request->coti_mail2, 120);
            $cotizacion->coti_telefono2 = $this->sanitizeNullableString($request->coti_telefono2, 50);
            $cotizacion->coti_contacto_tipo2 = $this->sanitizeNullableString($request->coti_contacto_tipo2, 30);
            $cotizacion->coti_contacto3 = $this->sanitizeNullableString($request->coti_contacto3, 120);
            $cotizacion->coti_mail3 = $this->sanitizeNullableString($request->coti_mail3, 120);
            $cotizacion->coti_telefono3 = $this->sanitizeNullableString($request->coti_telefono3, 50);
            $cotizacion->coti_contacto_tipo3 = $this->sanitizeNullableString($request->coti_contacto_tipo3, 30);
            $cotizacion->coti_contacto4 = $this->sanitizeNullableString($request->coti_contacto4, 120);
            $cotizacion->coti_mail4 = $this->sanitizeNullableString($request->coti_mail4, 120);
            $cotizacion->coti_telefono4 = $this->sanitizeNullableString($request->coti_telefono4, 50);
            $cotizacion->coti_contacto_tipo4 = $this->sanitizeNullableString($request->coti_contacto_tipo4, 30);
            $sectorFormulario = $this->sanitizeNullableString($request->coti_sector, 4);
            $sectorCliente = $this->sanitizeNullableString($cliente->cli_codigocrub ?? null, 4);

            $sectorCandidato = null;
            if ($sectorFormulario) {
                $sectorFormulario = $this->truncateAndPad($sectorFormulario, 4);
                if (Divis::where('divis_codigo', $sectorFormulario)->exists()) {
                    $sectorCandidato = $sectorFormulario;
                }
            }

            if (!$sectorCandidato && $sectorCliente) {
                $sectorCliente = $this->truncateAndPad($sectorCliente, 4);
                if (Divis::where('divis_codigo', $sectorCliente)->exists()) {
                    $sectorCandidato = $sectorCliente;
                }
            }

            $cotizacion->coti_sector = $sectorCandidato;
            
            Log::info('Campos de empresa asignados (con datos del cliente):', [
                'coti_empresa' => $cotizacion->coti_empresa,
                'coti_direccioncli' => $cotizacion->coti_direccioncli,
                'coti_localidad' => $cotizacion->coti_localidad,
                'coti_cuit' => $cotizacion->coti_cuit,
            ]);

            // Campos adicionales - Solo campos que existen en la tabla
            Log::info('Asignando campos adicionales');
            $this->aplicarReferenciasFacturacionVentas($request, $cotizacion);
            $cotizacion->coti_notas = CotizacionNotasGenerales::persistirDesdeRequest($request->coti_notas);
            $codSuc = trim((string) $request->input('coti_codigosuc', ''));
            $cotizacion->coti_codigosuc = $codSuc !== '' ? $this->truncateAndPad($codSuc, 10) : null;
            
            // Campos de descuentos / aumentos
            $cotizacion->coti_descuentoglobal = $request->filled('descuento') ? floatval($request->descuento) : 0.00;
            $cotizacion->coti_aumentoglobal = $request->filled('aumento') ? floatval($request->aumento) : 0.00;
            // Si el checkbox está presente, es true; si no, false
            $cotizacion->coti_mostrar_descuento = $request->has('coti_mostrar_descuento');
            $cotizacion->divisa_codigo = $request->filled('divisa_codigo') ? $this->sanitizeNullableString($request->divisa_codigo, 10) : 'PES';
            $cotizacion->coti_cond_pago = $request->filled('coti_cond_pago') ? $this->sanitizeNullableString($request->coti_cond_pago, 10) : null;
            $esCuotas = ($cotizacion->coti_cond_pago === 'CUOTAS');
            $cotizacion->coti_cuotas = $esCuotas;
            if ($esCuotas) {
                $cotizacion->coti_cuota_desc = $this->sanitizeNullableString($request->coti_cuota_desc, 100);
                $cotizacion->coti_cuota_cant = $request->filled('coti_cuota_cant') ? (int) $request->coti_cuota_cant : null;
                $cotizacion->coti_cuota_monto_total = $request->filled('coti_cuota_monto_total') ? $this->parseDecimalValue($request->coti_cuota_monto_total) : null;
                $cotizacion->coti_cuota_monto_indiv = $request->filled('coti_cuota_monto_indiv') ? $this->parseDecimalValue($request->coti_cuota_monto_indiv) : null;
                $interesCrear = $this->parseDecimalValue($request->coti_cuota_interes);
                $cotizacion->coti_cuota_interes = $interesCrear !== null ? $interesCrear : 0.0;
                $cotizacion->coti_cuota_fact_fin_mes = $request->has('coti_cuota_fact_fin_mes') && $request->coti_cuota_fact_fin_mes == '1';
                $cotizacion->coti_cuota_fact_inicio_mes = $request->has('coti_cuota_fact_inicio_mes') && $request->coti_cuota_fact_inicio_mes == '1';
            } else {
                $cotizacion->coti_cuota_desc = null;
                $cotizacion->coti_cuota_cant = null;
                $cotizacion->coti_cuota_monto_total = null;
                $cotizacion->coti_cuota_monto_indiv = null;
                $cotizacion->coti_cuota_interes = null;
                $cotizacion->coti_cuota_fact_fin_mes = null;
                $cotizacion->coti_cuota_fact_inicio_mes = null;
            }
            $cotizacion->coti_sector_laboratorio_pct = $request->filled('sector_laboratorio_porcentaje') ? floatval($request->sector_laboratorio_porcentaje) : 0.00;
            $cotizacion->coti_sector_higiene_pct = $request->filled('sector_higiene_porcentaje') ? floatval($request->sector_higiene_porcentaje) : 0.00;
            $cotizacion->coti_sector_microbiologia_pct = $request->filled('sector_microbiologia_porcentaje') ? floatval($request->sector_microbiologia_porcentaje) : 0.00;
            $cotizacion->coti_sector_cromatografia_pct = $request->filled('sector_cromatografia_porcentaje') ? floatval($request->sector_cromatografia_porcentaje) : 0.00;
            $cotizacion->coti_sector_laboratorio_contacto = $this->sanitizeNullableString($request->sector_laboratorio_contacto, 100);
            $cotizacion->coti_sector_higiene_contacto = $this->sanitizeNullableString($request->sector_higiene_contacto, 100);
            $cotizacion->coti_sector_microbiologia_contacto = $this->sanitizeNullableString($request->sector_microbiologia_contacto, 100);
            $cotizacion->coti_sector_cromatografia_contacto = $this->sanitizeNullableString($request->sector_cromatografia_contacto, 100);
            $cotizacion->coti_sector_laboratorio_observaciones = $this->sanitizeNullableString($request->sector_laboratorio_observaciones);
            $cotizacion->coti_sector_higiene_observaciones = $this->sanitizeNullableString($request->sector_higiene_observaciones);
            $cotizacion->coti_sector_microbiologia_observaciones = $this->sanitizeNullableString($request->sector_microbiologia_observaciones);
            $cotizacion->coti_sector_cromatografia_observaciones = $this->sanitizeNullableString($request->sector_cromatografia_observaciones);
            
            // Campos de cadena de custodia y muestreo
            $cotizacion->coti_cadena_custodia = $request->has('coti_cadena_custodia') && $request->coti_cadena_custodia == '1';
            $cotizacion->coti_muestreo = $request->has('coti_muestreo') && $request->coti_muestreo == '1';
            $cotizacion->coti_req_cadena_custodia_relacionada = $request->boolean('coti_req_cadena_custodia_relacionada');
            $cotizacion->coti_prioridad_global = false;
            
            // Campos financieros eliminados - no existen en la tabla real
            Log::info('=== CAMPOS FINANCIEROS OMITIDOS ===');
            Log::info('Los siguientes campos no existen en la tabla: coti_abono, coti_importe, coti_usos, coti_codigopag, coti_codigolp, coti_nroprecio');

            Log::info('=== PREPARANDO PARA GUARDAR COTIZACIÓN ===');
            Log::info('Datos finales de la cotización antes de save:', [
                'coti_num' => $cotizacion->coti_num . ' (tipo: ' . gettype($cotizacion->coti_num) . ')',
                'coti_descripcion' => $cotizacion->coti_descripcion,
                'coti_codigocli' => "'" . $cotizacion->coti_codigocli . "'",
                'coti_fechaalta' => $cotizacion->coti_fechaalta,
                'coti_estado' => "'" . $cotizacion->coti_estado . "'",
                'coti_codigomatriz' => $cotizacion->coti_codigomatriz ? "'" . $cotizacion->coti_codigomatriz . "'" : 'NULL',
                'coti_empresa' => $cotizacion->coti_empresa,
                'coti_direccioncli' => $cotizacion->coti_direccioncli,
                'coti_localidad' => $cotizacion->coti_localidad,
                'coti_cuit' => $cotizacion->coti_cuit,
                'coti_codigosuc' => $cotizacion->coti_codigosuc ? "'" . $cotizacion->coti_codigosuc . "'" : 'NULL'
            ]);

            // Verificar que todos los campos requeridos estén presentes
            Log::info('Verificando campos requeridos:');
            if (!$cotizacion->coti_num) {
                Log::error('ERROR: coti_num está vacío');
            }
            if (!$cotizacion->coti_codigocli) {
                Log::error('ERROR: coti_codigocli está vacío');
            }
            if (!$cotizacion->coti_fechaalta) {
                Log::error('ERROR: coti_fechaalta está vacío');
            }

            Log::info('Ejecutando save()...');
            try {
                $result = $cotizacion->save();
                Log::info('Save() ejecutado exitosamente', ['result' => $result]);
                Log::info('ID de cotización guardada:', ['coti_num' => $cotizacion->coti_num]);
            } catch (\Exception $saveException) {
                Log::error('ERROR EN SAVE():', [
                    'message' => $saveException->getMessage(),
                    'file' => $saveException->getFile(),
                    'line' => $saveException->getLine(),
                    'trace' => $saveException->getTraceAsString()
                ]);
                throw $saveException;
            }

            Log::info('Cotización creada exitosamente', ['numero' => $cotizacion->coti_num]);
            
            // Establecer versión inicial
            $cotizacion->coti_version = 1;
            $cotizacion->save();
            
            // Procesar ensayos y componentes
            $this->procesarEnsayosYComponentes($request, $cotizacion->coti_num);
            $this->procesarAdjuntosEnsayos($request, $cotizacion->coti_num);
            $this->sincronizarMontosCuotasDesdeItems($cotizacion);
            
            // IMPORTANTE: Guardar versión 1 en coti_versions DESPUÉS de procesar los items
            // para que los items ya estén guardados en la tabla cotio
            Log::info('Guardando versión 1 en coti_versions');
            
            // Obtener todos los datos de la cotización desde la BD
            $cotizacion->refresh(); // Refrescar para obtener todos los campos actualizados
            $cotiData = $cotizacion->getAttributes();
            
            // Asegurar que el sector tenga el formato correcto antes de guardar
            if (isset($cotiData['coti_sector'])) {
                $sectorValue = $cotiData['coti_sector'];
                if ($sectorValue !== null && trim($sectorValue) !== '') {
                    $cotiData['coti_sector'] = $this->truncateAndPad(trim($sectorValue), 4);
                } else {
                    $cotiData['coti_sector'] = null;
                }
            } else {
                $cotiData['coti_sector'] = null;
            }
            
            // Obtener todos los items desde la BD (ya guardados por procesarEnsayosYComponentes)
            $cotioItemsRaw = DB::table('cotio')
                ->where('cotio_numcoti', $cotizacion->coti_num)
                ->orderBy('cotio_item')
                ->orderBy('cotio_subitem')
                ->get();
            
            // Convertir a array asociativo con todos los campos
            $cotioItems = $cotioItemsRaw->map(function($item) {
                return (array) $item;
            })->toArray();
            
            // Log detallado para debugging
            $ensayosCount = collect($cotioItems)->where('cotio_subitem', 0)->count();
            $componentesCount = collect($cotioItems)->where('cotio_subitem', '>', 0)->count();
            
            Log::info('Guardando versión 1 en coti_versions', [
                'coti_num' => $cotizacion->coti_num,
                'cotio_items_total' => count($cotioItems),
                'ensayos_count' => $ensayosCount,
                'componentes_count' => $componentesCount,
                'cotio_items_sample' => array_slice($cotioItems, 0, 3)
            ]);
            
            // Guardar versión 1 (updateOrCreate evita duplicate key si ya existe por reintento)
            CotiVersion::updateOrCreate(
                [
                    'coti_num' => $cotizacion->coti_num,
                    'version' => 1,
                ],
                [
                    'fecha_version' => now(),
                    'coti_data' => $cotiData,
                    'cotio_data' => $cotioItems,
                ]
            );

            Log::info('Versión 1 guardada exitosamente en coti_versions');
            
            Log::info('=== FIN CREACIÓN DE COTIZACIÓN EXITOSA ===');

            return redirect()->route('ventas.index')
                ->with('success', 'Cotización creada exitosamente con número: ' . $cotizacion->coti_num);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('=== ERROR DE VALIDACIÓN EN COTIZACIÓN ===', [
                'errors' => $e->validator->errors()->toArray(),
                'input' => $request->all()
            ]);
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            Log::error('=== ERROR AL CREAR COTIZACIÓN ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'input_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Error al crear la cotización: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function edit($id, Request $request)
    {
        if (!is_numeric($id)) {
            abort(404, 'Invalid ID');
        }
        
        $cotizacion = Ventas::with(['cliente'])->find($id);
        if (!$cotizacion) {
            abort(404, 'Cotización not found');
        }

        $canalPreCheck = $this->ventasCanalRestringidoUsuario();
        if ($canalPreCheck && ! $this->cotizacionTieneEnsayoDeCanal((int) $id, $canalPreCheck)) {
            abort(403);
        }

        $versionSolicitada = $request->get('version');
        $editandoVersionActual = ! $versionSolicitada
            || (int) $versionSolicitada === (int) ($cotizacion->coti_version ?? 1);

        $edicionLockActivo = false;
        if ($editandoVersionActual && ! $canalPreCheck && CotizacionEdicionBloqueo::requiereBloqueoConcurrente($cotizacion)) {
            $bloqueo = CotizacionEdicionBloqueo::adquirir((int) $id, Auth::user());
            if (! $bloqueo['ok']) {
                return view('ventas.edicion-bloqueada', [
                    'cotizacion' => $cotizacion,
                    'titular' => $bloqueo['titular'] ?? ['nombre' => 'Otro usuario', 'usu_codigo' => '', 'desde' => null],
                ]);
            }
            $edicionLockActivo = true;
        }
        
        // Verificar si se solicita una versión específica
        $ensayos = collect();
        $componentes = collect();
        
        if ($versionSolicitada && $versionSolicitada != $cotizacion->coti_version) {
            // Cargar versión histórica
            $versionHistorica = CotiVersion::where('coti_num', $id)
                ->where('version', $versionSolicitada)
                ->first();
            
            if ($versionHistorica) {
                // Actualizar datos de la cotización con los de la versión
                $cotizacion->fill($versionHistorica->coti_data);
                $cotizacion->coti_num = $id; // Mantener el número original
                
                // Asegurar que el sector tenga el formato correcto (4 caracteres con padding)
                // Si es null, mantenerlo como null explícitamente
                if ($cotizacion->coti_sector !== null && trim($cotizacion->coti_sector) !== '') {
                    $cotizacion->coti_sector = $this->truncateAndPad(trim($cotizacion->coti_sector), 4);
                } else {
                    $cotizacion->coti_sector = null;
        }

                // Procesar items de cotio
                // IMPORTANTE: Limpiar las colecciones antes de cargar items de la versión histórica
                $ensayos = collect();
                $componentes = collect();
                
                // Obtener cotio_data y decodificarlo si es necesario
                $cotioDataRaw = $versionHistorica->cotio_data ?? [];
                
                // Decodificar cotio_data si es string JSON
                if (is_string($cotioDataRaw)) {
                    $cotioData = json_decode($cotioDataRaw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::error('Error decodificando cotio_data en edit', [
                            'coti_num' => $id,
                            'version' => $versionSolicitada,
                            'json_error' => json_last_error_msg()
                        ]);
                        $cotioData = [];
                    }
                } else {
                    $cotioData = $cotioDataRaw ?? [];
                }
                
                // Asegurar que cotioData sea un array
                if (!is_array($cotioData)) {
                    $cotioData = [];
                }
                
                // Log para debugging
                $ensayosCount = collect($cotioData)->where('cotio_subitem', 0)->count();
                $componentesCount = collect($cotioData)->where('cotio_subitem', '>', 0)->count();
                
                Log::info('Cargando versión histórica en edit', [
                    'coti_num' => $id,
                    'version_solicitada' => $versionSolicitada,
                    'cotio_data_count' => count($cotioData),
                    'ensayos_count' => $ensayosCount,
                    'componentes_count' => $componentesCount,
                    'cotio_data_sample' => array_slice($cotioData, 0, 3)
                ]);
                
                foreach ($cotioData as $itemData) {
                    // Asegurar que itemData sea un array
                    if (!is_array($itemData)) {
                        continue;
                    }
                    
                    $subitem = isset($itemData['cotio_subitem']) ? (int)$itemData['cotio_subitem'] : -1;
                    
                    if ($subitem == 0) {
                        // Es un ensayo
                        $ensayo = new Cotio();
                        $ensayo->fill($itemData);
                        $ensayos->push($ensayo);
                    } else if ($subitem > 0) {
                        // Es un componente
                        $componente = new Cotio();
                        $componente->fill($itemData);
                        $componentes->push($componente);
                    }
                }
                
                Log::info('Items procesados en edit', [
                    'ensayos_count' => $ensayos->count(),
                    'componentes_count' => $componentes->count()
                ]);
            }
        }
        
        // Si no hay versión solicitada o no se encontró, cargar versión actual
        if ($ensayos->isEmpty() && $componentes->isEmpty()) {
        // Cargar items de la cotización (ensayos y componentes) con relaciones
        $ensayos = Cotio::where('cotio_numcoti', $cotizacion->coti_num)
            ->where('cotio_subitem', 0)
            ->orderBy('cotio_item')
            ->get();
        
        // Cargar componentes con el método (cotio_codigometodo apunta a tabla metodo)
        $componentes = Cotio::where('cotio_numcoti', $cotizacion->coti_num)
            ->where('cotio_subitem', '>', 0)
            ->orderBy('cotio_item')
            ->orderBy('cotio_subitem')
            ->get();
        }
        
        // Cargar datos para los selectores
        try {
            $matrices = Matriz::orderBy('matriz_descripcion')->get();
        } catch (\Exception $e) {
            Log::warning('Error cargando matrices:', ['error' => $e->getMessage()]);
            $matrices = collect();
        }

        try {
            $sectoresCliente = Divis::where('divis_lab', true)
                ->orderBy('divis_descripcion')
                ->get();
        } catch (\Exception $e) {
            Log::warning('Error cargando sectores de cliente:', ['error' => $e->getMessage()]);
            $sectoresCliente = collect();
        }

        try {
            $condicionesPago = CondicionPago::where('pag_estado', true)
                ->orderBy('pag_descripcion')
                ->get();
        } catch (\Exception $e) {
            Log::warning('Error cargando condiciones de pago:', ['error' => $e->getMessage()]);
            $condicionesPago = collect();
        }

        // Ahora siempre permitimos editar la cotización, incluso si está aprobada.
        $puedeEditar = true;

        $agrupadoresCatalogo = CotioItems::muestras()
            ->with(['componentesAsociados', 'matrices'])
            ->get()
            ->keyBy(function ($item) {
                return Str::lower(trim($item->cotio_descripcion));
            });

        $canalRestringidoEdit = $this->ventasCanalRestringidoUsuario();
        if ($canalRestringidoEdit) {
            $ensayos = $ensayos->filter(function ($ensayo) use ($agrupadoresCatalogo, $canalRestringidoEdit) {
                return $this->resolverCanalCotioEnsayo($ensayo, $agrupadoresCatalogo) === $canalRestringidoEdit;
            })->values();
            $itemsPermitidos = $ensayos->pluck('cotio_item')->map(fn ($i) => (int) $i)->all();
            $componentes = $componentes->filter(function ($c) use ($itemsPermitidos) {
                return in_array((int) $c->cotio_item, $itemsPermitidos, true);
            })->values();
            $puedeEditar = false;
        }

        $adjuntosPorItem = CotioAdjunto::where('cotio_numcoti', $cotizacion->coti_num)
            ->orderBy('created_at')
            ->get()
            ->groupBy('cotio_item');

        $ensayosIniciales = $ensayos->map(function ($ensayo) use ($componentes, $agrupadoresCatalogo, $adjuntosPorItem) {
            $cantidad = $ensayo->cotio_cantidad ?? 1;
            $componentesDelEnsayo = $componentes->where('cotio_item', $ensayo->cotio_item);

            $descripcionClave = Str::lower(trim($ensayo->cotio_descripcion ?? ''));
            $agrupador = $agrupadoresCatalogo->get($descripcionClave);
            $idsParametrosPack = $agrupador
                ? $agrupador->componentesAsociados->pluck('id')->map(fn ($id) => (string) $id)->values()->all()
                : [];

            $cotioPrecioEnsayo = $this->parseDecimalValue($ensayo->cotio_precio ?? null);
            $precioPackEnsayo = ($cotioPrecioEnsayo !== null && $cotioPrecioEnsayo > 0) ? (float) $cotioPrecioEnsayo : 0.0;

            $precioUnitario = $componentesDelEnsayo->sum(function ($comp) use ($idsParametrosPack, $precioPackEnsayo) {
                if ($comp->de_agrupador) {
                    return 0;
                }
                if ($precioPackEnsayo > 0 && !empty($idsParametrosPack)) {
                    $analisisId = trim((string) ($comp->cotio_codigoprod ?? ''));
                    if ($analisisId !== '' && in_array($analisisId, $idsParametrosPack, true)) {
                        return 0;
                    }
                }
                $precio = $comp->cotio_precio ?? 0;
                $cantidadComp = $comp->cotio_cantidad ?? 1;

                return $precio * $cantidadComp;
            });

            $esNuevaLogica = $componentesDelEnsayo->contains(fn ($c) => (bool) ($c->de_agrupador ?? false))
                || ($precioPackEnsayo > 0 && !empty($idsParametrosPack));
            $precioExtraEnsayo = $this->resolverPrecioExtraEnsayoDesdeCotioRow($cotioPrecioEnsayo, (float) $precioUnitario, $esNuevaLogica);
            $precioUnitarioTotal = (float) $precioUnitario + $precioExtraEnsayo;

            // Obtener matriz desde la tabla pivote o desde matriz_codigo directo
            $matrizCodigo = null;
            $matrizDescripcion = null;
            
            if ($agrupador) {
                if ($agrupador->matrices->isNotEmpty()) {
                    $matriz = $agrupador->matrices->first();
                    $matrizCodigo = $matriz->matriz_codigo;
                    $matrizDescripcion = $matriz->matriz_descripcion;
                } elseif ($agrupador->matriz_codigo) {
                    // Fallback: usar matriz_codigo directo si existe
                    $matrizCodigo = trim($agrupador->matriz_codigo);
                    $matriz = \App\Models\Matriz::where('matriz_codigo', $matrizCodigo)->first();
                    $matrizDescripcion = $matriz ? trim($matriz->matriz_descripcion) : null;
                }
            }

            return [
                'item' => (int) $ensayo->cotio_item,
                'muestra_id' => $agrupador?->id,
                'descripcion' => $ensayo->cotio_descripcion,
                'codigo' => $agrupador ? str_pad($agrupador->id, 15, '0', STR_PAD_LEFT) : ($ensayo->cotio_codigoprod ?? ''),
                'cantidad' => (float) $cantidad,
                'precio_extra_ensayo' => (float) $precioExtraEnsayo,
                'precio' => (float) $precioUnitarioTotal,
                'total' => (float) ($precioUnitarioTotal * $cantidad),
                'tipo' => 'ensayo',
                'componentes_sugeridos' => $agrupador ? $agrupador->componentesAsociados->pluck('id')->values()->all() : [],
                'nota_tipo' => $ensayo->cotio_nota_tipo ?? null,
                'nota_contenido' => $ensayo->cotio_nota_contenido ?? null,
                'matriz_codigo' => $matrizCodigo,
                'matriz_descripcion' => $matrizDescripcion,
                'canal_especial' => $this->resolverCanalCotioEnsayo($ensayo, $agrupadoresCatalogo),
                'lleva_muestreo' => $ensayo->lleva_muestreo ?? true,
                'req_cadena_custodia' => (bool) ($ensayo->req_cadena_custodia ?? false),
                'req_prot_mapba' => (bool) ($ensayo->req_prot_mapba ?? false),
                'ley_normativa_id' => ($ensayo->ley_aplicacion !== null && trim((string) $ensayo->ley_aplicacion) !== '')
                    ? trim((string) $ensayo->ley_aplicacion)
                    : null,
                'es_priori' => (bool) (($cotizacion->coti_prioridad_global ?? false) || ($ensayo->es_priori ?? false)),
                'adjuntos' => ($adjuntosPorItem->get($ensayo->cotio_item) ?? collect())->map(function ($adjunto) {
                    return [
                        'id' => $adjunto->id,
                        'name' => $adjunto->original_name,
                        'url' => $adjunto->url(),
                    ];
                })->values()->all(),
            ];
        })->values();

        $componentesIniciales = [];
        $contadorComponentes = 0;
        $maxItemEnsayo = (int) ($ensayos->max('cotio_item') ?? 0);
        foreach ($componentes as $componente) {
            $contadorComponentes++;
            $metodoTexto = '-';

            if ($componente->cotio_codigometodo) {
                $metodoCodigo = trim($componente->cotio_codigometodo);
                $metodo = Metodo::where('metodo_codigo', $metodoCodigo)->first();
                $metodoTexto = $metodo
                    ? $metodo->metodo_codigo . ' - ' . ($metodo->metodo_descripcion ?? '')
                    : $metodoCodigo;
            } elseif ($componente->cotio_codigometodo_analisis) {
                $metodoCodigo = trim($componente->cotio_codigometodo_analisis);
                $metodoAnalisis = MetodoAnalisis::where('codigo', $metodoCodigo)->first();
                $metodoTexto = $metodoAnalisis
                    ? $metodoAnalisis->codigo . ' - ' . ($metodoAnalisis->nombre ?? $metodoAnalisis->descripcion ?? '')
                    : $metodoCodigo;
            }

            $codigoProducto = trim($componente->cotio_codigoprod ?? '');
            $descripcionComponente = trim($componente->cotio_descripcion ?? '');
            $componenteCatalogo = $this->buscarCotioItemComponenteCatalogo($codigoProducto, $descripcionComponente);
            $precioReferencia = $this->resolverPrecioReferenciaComponenteParaEdicion(
                $componenteCatalogo,
                $componente->cotio_precio ?? null
            );
            $analisisId = $precioReferencia['analisis_id'];
            $precioComponente = (float) $precioReferencia['precio'];
            $precioMinimoVenta = (float) $precioReferencia['precio_minimo_venta'];
            $cantidadComponente = (float) ($componente->cotio_cantidad ?? 1);
            if ($cantidadComponente <= 0) {
                $cantidadComponente = 1;
            }

            $ensayoPadre = $ensayos->firstWhere('cotio_item', $componente->cotio_item);
            $descripcionEnsayoClave = Str::lower(trim($ensayoPadre->cotio_descripcion ?? ''));
            $agrupadorPadre = $agrupadoresCatalogo->get($descripcionEnsayoClave);
            $idsParametrosPack = $agrupadorPadre
                ? $agrupadorPadre->componentesAsociados->pluck('id')->map(fn ($id) => (string) $id)->values()->all()
                : [];
            $precioPackPadre = 0.0;
            if ($ensayoPadre) {
                $cotioPrecioPadre = $this->parseDecimalValue($ensayoPadre->cotio_precio ?? null);
                $precioPackPadre = ($cotioPrecioPadre !== null && $cotioPrecioPadre > 0) ? (float) $cotioPrecioPadre : 0.0;
            }

            $deAgrupador = (bool) ($componente->de_agrupador ?? false);
            if (!$deAgrupador && $precioPackPadre > 0 && $analisisId && !empty($idsParametrosPack)) {
                $deAgrupador = in_array((string) $analisisId, $idsParametrosPack, true);
            }

            $componentesIniciales[] = [
                'item' => $maxItemEnsayo + $contadorComponentes,
                'analisis_id' => $analisisId,
                'descripcion' => $componente->cotio_descripcion,
                'codigo' => $componente->cotio_codigoprod ?? '',
                'cantidad' => $cantidadComponente,
                'precio' => $precioComponente,
                'precio_minimo_venta' => $precioMinimoVenta,
                'total' => $precioComponente * $cantidadComponente,
                'tipo' => 'componente',
                'ensayo_asociado' => (int) $componente->cotio_item,
                'metodo_analisis_id' => $componente->cotio_codigometodo_analisis ? trim($componente->cotio_codigometodo_analisis) : null,
                'metodo_codigo' => $componente->cotio_codigometodo ? trim($componente->cotio_codigometodo) : null,
                'metodo_descripcion' => $metodoTexto,
                'unidad_medida' => $componente->cotio_codigoum ? trim($componente->cotio_codigoum) : null,
                'limite_deteccion' => $componente->limite_deteccion ?? null,
                'ley_normativa_id' => ($componente->ley_aplicacion !== null && trim((string) $componente->ley_aplicacion) !== '')
                    ? trim((string) $componente->ley_aplicacion)
                    : null,
                'nota_tipo' => $componente->cotio_nota_tipo ?? null,
                'nota_contenido' => $componente->cotio_nota_contenido ?? null,
                'req_cadena_custodia' => (bool) ($componente->req_cadena_custodia ?? false),
                'req_prot_mapba' => (bool) ($componente->req_prot_mapba ?? false),
                'de_agrupador' => $deAgrupador,
            ];
        }

        $cotizacionConfig = [
            'modo' => 'edit',
            'puedeEditar' => $puedeEditar,
            'ensayosIniciales' => $ensayosIniciales,
            'componentesIniciales' => $componentesIniciales,
            'coti_req_cadena_custodia_relacionada' => (bool) ($cotizacion->coti_req_cadena_custodia_relacionada ?? false),
            'coti_prioridad_global' => (bool) ($cotizacion->coti_prioridad_global ?? false),
        ];
        
        $descuentoCliente = $this->calcularDescuentoCotizacion($cotizacion);
        
        // Prioridad: primero descuentos de la cotización, luego del cliente
        $descuentoGlobalCliente = 0.0;
        if (isset($cotizacion->coti_descuentoglobal) && $cotizacion->coti_descuentoglobal > 0) {
            $descuentoGlobalCliente = (float) $cotizacion->coti_descuentoglobal;
        } elseif ($cotizacion->cliente) {
            $descuentoGlobalCliente = (float) ($cotizacion->cliente->cli_descuentoglobal ?? 0);
        }
        
        $sectorCodigoOriginal = $cotizacion->coti_sector ?? optional($cotizacion->cliente)->cli_codigocrub;
        $sectorCodigo = $this->normalizarCodigoSector($sectorCodigoOriginal);
        $descuentoSectorAplicado = 0.0;
        if ($sectorCodigo) {
            $descuentoSectorAplicado = $this->obtenerDescuentoSectorCotizacion($cotizacion, $sectorCodigo);
        }
        if ($descuentoSectorAplicado == 0.0 && $cotizacion->cliente) {
            $descuentoSectorAplicado = $this->obtenerDescuentoSector($cotizacion->cliente, $sectorCodigo);
        }
        
        $sectorEtiqueta = trim(optional($cotizacion->sector)->divis_descripcion ?? $cotizacion->coti_sector ?? '');

        try {
            $divisas = Divisa::orderBy('divisa_codigo')->get();
        } catch (\Exception $e) {
            Log::warning('Error cargando divisas:', ['error' => $e->getMessage()]);
            $divisas = collect();
        }

        return View::make('ventas.edit', compact(
            'cotizacion',
            'matrices',
            'ensayos',
            'componentes',
            'puedeEditar',
            'cotizacionConfig',
            'descuentoCliente',
            'descuentoGlobalCliente',
            'descuentoSectorAplicado',
            'sectorEtiqueta',
            'sectoresCliente',
            'divisas',
            'condicionesPago',
            'edicionLockActivo'
        ));
    }
    
    public function edicionLockHeartbeat($id)
    {
        if (! is_numeric($id)) {
            return response()->json(['ok' => false], 404);
        }

        if (! Ventas::where('coti_num', $id)->exists()) {
            return response()->json(['ok' => false], 404);
        }

        $ok = CotizacionEdicionBloqueo::renovar((int) $id, Auth::user());

        return response()->json(['ok' => $ok], $ok ? 200 : 403);
    }

    public function edicionLockRelease($id)
    {
        if (! is_numeric($id)) {
            return response()->json(['ok' => false], 404);
        }

        CotizacionEdicionBloqueo::liberar((int) $id, Auth::user());

        return response()->json(['ok' => true]);
    }

    public function destroy($id)
    {
        if (!is_numeric($id)) {
            abort(404, 'Invalid ID');
        }

        $this->denegarVentasSiUsuarioSoloCanal();
        
        try {
            $cotizacion = Ventas::find($id);
            if (!$cotizacion) {
                return redirect()->route('ventas.index', request()->query())
                    ->with('error', 'Cotización no encontrada');
            }

            // Eliminar versiones históricas de la cotización en coti_versions
            CotiVersion::where('coti_num', $cotizacion->coti_num)->delete();

            $cotizacion->delete();
            
            // Preservar los filtros activos en la redirección
            return redirect()->route('ventas.index', request()->query())
                ->with('success', 'Cotización eliminada exitosamente');
                
        } catch (\Exception $e) {
            Log::error('Error al eliminar cotización:', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->route('ventas.index', request()->query())
                ->with('error', 'Error al eliminar la cotización');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $this->denegarVentasSiUsuarioSoloCanal();

            $cotizacion = Ventas::find($id);
            
            if (!$cotizacion) {
                return redirect()->route('ventas.index')
                    ->with('error', 'Cotización no encontrada');
            }

            if (
                CotizacionEdicionBloqueo::requiereBloqueoConcurrente($cotizacion)
                && ! $this->ventasCanalRestringidoUsuario()
            ) {
                $bloqueo = CotizacionEdicionBloqueo::adquirir((int) $id, Auth::user());
                if (! $bloqueo['ok']) {
                    return redirect()->route('ventas.edit', $id)
                        ->with('error', 'Otro usuario está editando este presupuesto o tu sesión de edición expiró. Volvé a abrirlo.');
                }
            }

            $erroresRefs = CotizacionReferenciasFacturacion::validarRequest($request);
            if ($erroresRefs !== []) {
                throw ValidationException::withMessages($erroresRefs);
            }
            
            // IMPORTANTE: Guardar versión histórica ANTES de actualizar
            // Debemos guardar el estado ACTUAL (anterior) de la cotización, no el nuevo
            $versionActual = (int)($cotizacion->coti_version ?? 1);
            
            // Obtener todos los datos ACTUALES de la cotización desde la BD (antes de actualizar)
            $cotiData = $cotizacion->getAttributes();
            
            // Asegurar que el sector tenga el formato correcto antes de guardar
            // Si es null o vacío, mantenerlo como null explícitamente
            if (isset($cotiData['coti_sector'])) {
                $sectorValue = $cotiData['coti_sector'];
                if ($sectorValue !== null && trim($sectorValue) !== '') {
                    $cotiData['coti_sector'] = $this->truncateAndPad(trim($sectorValue), 4);
                } else {
                    $cotiData['coti_sector'] = null;
                }
            } else {
                $cotiData['coti_sector'] = null;
            }
            
            // IMPORTANTE: Obtener los items ACTUALES desde la BD (no desde el formulario)
            // Estos son los items que existían ANTES de la actualización
            // Usar DB::table para obtener TODOS los campos, no solo los fillable
            $cotioItemsRaw = DB::table('cotio')
                ->where('cotio_numcoti', $cotizacion->coti_num)
                ->orderBy('cotio_item')
                ->orderBy('cotio_subitem')
                ->get();
            
            // Convertir a array asociativo con todos los campos
            $cotioItems = $cotioItemsRaw->map(function($item) {
                return (array) $item;
            })->toArray();
            
            // Log detallado para debugging
            $ensayosCount = collect($cotioItems)->where('cotio_subitem', 0)->count();
            $componentesCount = collect($cotioItems)->where('cotio_subitem', '>', 0)->count();
            
            Log::info('Guardando versión histórica ANTES de actualizar', [
                'coti_num' => $cotizacion->coti_num,
                'version_actual' => $versionActual,
                'cotio_items_total' => count($cotioItems),
                'ensayos_count' => $ensayosCount,
                'componentes_count' => $componentesCount,
                'cotio_items_sample' => array_slice($cotioItems, 0, 3)
            ]);
            
            // Verificar si ya existe esta versión (puede existir si se guardó al crear la cotización)
            $versionExistente = CotiVersion::where('coti_num', $cotizacion->coti_num)
                ->where('version', $versionActual)
                ->first();
            
            if ($versionExistente) {
                // Si ya existe, actualizarla con los datos actuales
                Log::info('Versión ya existe, actualizando', [
                    'coti_num' => $cotizacion->coti_num,
                    'version' => $versionActual
                ]);
                $versionExistente->update([
                    'fecha_version' => now(),
                    'coti_data' => $cotiData,
                    'cotio_data' => $cotioItems,
                ]);
            } else {
                // Si no existe, crearla
                Log::info('Versión no existe, creando nueva', [
                    'coti_num' => $cotizacion->coti_num,
                    'version' => $versionActual
                ]);
                CotiVersion::create([
                    'coti_num' => $cotizacion->coti_num,
                    'version' => $versionActual,
                    'fecha_version' => now(),
                    'coti_data' => $cotiData,
                    'cotio_data' => $cotioItems,
                ]);
            }
            
            // Actualizar campos principales
            $cotizacion->coti_descripcion = $request->coti_descripcion;
            $cotizacion->coti_para = $this->sanitizeNullableString($request->coti_para, null);
            $cotizacion->coti_codigocli = $this->truncateAndPad($request->coti_codigocli, 10);
            $clienteCotizacion = Clientes::where('cli_codigo', $cotizacion->coti_codigocli)
                ->where('cli_estado', true)
                ->first();
            $relIds = $this->resolverIdsEmpresaRelacionadaParaGuardado($request, $clienteCotizacion);
            $cotizacion->coti_empresa_rel = $relIds['coti_empresa_rel'];
            $cotizacion->coti_para_empresa_rel = $relIds['coti_para_empresa_rel'];
            $cotizacion->coti_cli_empresa = $relIds['coti_cli_empresa'];
            $cotizacion->coti_fechaalta = $request->coti_fechaalta;
            $cotizacion->coti_fechafin = $request->coti_fechafin;
            
            // Mapear estado
            $estadosMap = [
                'E' => 'E    ',
                'A' => 'A    ',
                'R' => 'R    ',
                'P' => 'P    ',
                'S' => 'S    ',
            ];
            
            $estadoFormulario = $request->coti_estado ?: 'E';
            $cotizacion->coti_estado = $estadosMap[$estadoFormulario] ?? 'E    ';
            
            // Campos técnicos
            $cotizacion->coti_codigomatriz = $this->truncateAndPad($request->coti_codigomatriz, 15);
            $codSuc = trim((string) $request->input('coti_codigosuc', ''));
            $cotizacion->coti_codigosuc = $codSuc !== '' ? $this->truncateAndPad($codSuc, 10) : null;
            
            // Campos de gestión
            $cotizacion->coti_responsable = $this->truncateAndPad($request->coti_responsable, 20);
            $cotizacion->coti_aprobo = $this->truncateAndPad($request->coti_aprobo, 20);
            $cotizacion->coti_fechaaprobado = $request->input('coti_fechaaprobado');
            $this->completarCotiFechaAprobadoSiAprobada($cotizacion);
            $cotizacion->coti_fechaencurso = $request->coti_fechaencurso;
            $cotizacion->coti_fechaaltatecnica = $request->coti_fechaaltatecnica;
            
            // Campos de empresa (aplicar truncamiento a campos con límites de caracteres)
            $cotizacion->coti_empresa = $this->sanitizeNullableString($request->coti_empresa, 50);
            $cotizacion->coti_establecimiento = $this->sanitizeNullableString($request->coti_establecimiento, 50);
            $cotizacion->coti_contacto = $this->sanitizeNullableString($request->coti_contacto, 120);
            $cotizacion->coti_direccioncli = $this->sanitizeNullableString($request->coti_direccioncli, 50);
            $cotizacion->coti_localidad = $this->sanitizeNullableString($request->coti_localidad, 50);
            $cotizacion->coti_partido = $this->sanitizeNullableString($request->coti_partido, 50);
            $cotizacion->coti_cuit = $this->sanitizeNullableString($request->coti_cuit, 13);
            $cotizacion->coti_codigopostal = $this->sanitizeNullableString($request->coti_codigopostal, 10);
            $cotizacion->coti_telefono = $request->coti_telefono;
            $cotizacion->coti_mail1 = $this->sanitizeNullableString($request->coti_mail1, 120);
            $cotizacion->coti_contacto_tipo1 = $this->sanitizeNullableString($request->coti_contacto_tipo1, 30);
            $cotizacion->coti_contacto2 = $this->sanitizeNullableString($request->coti_contacto2, 120);
            $cotizacion->coti_mail2 = $this->sanitizeNullableString($request->coti_mail2, 120);
            $cotizacion->coti_telefono2 = $this->sanitizeNullableString($request->coti_telefono2, 50);
            $cotizacion->coti_contacto_tipo2 = $this->sanitizeNullableString($request->coti_contacto_tipo2, 30);
            $cotizacion->coti_contacto3 = $this->sanitizeNullableString($request->coti_contacto3, 120);
            $cotizacion->coti_mail3 = $this->sanitizeNullableString($request->coti_mail3, 120);
            $cotizacion->coti_telefono3 = $this->sanitizeNullableString($request->coti_telefono3, 50);
            $cotizacion->coti_contacto_tipo3 = $this->sanitizeNullableString($request->coti_contacto_tipo3, 30);
            $cotizacion->coti_contacto4 = $this->sanitizeNullableString($request->coti_contacto4, 120);
            $cotizacion->coti_mail4 = $this->sanitizeNullableString($request->coti_mail4, 120);
            $cotizacion->coti_telefono4 = $this->sanitizeNullableString($request->coti_telefono4, 50);
            $cotizacion->coti_contacto_tipo4 = $this->sanitizeNullableString($request->coti_contacto_tipo4, 30);
            
            // Procesar sector correctamente (debe ser 4 caracteres)
            $sectorFormulario = $this->sanitizeNullableString($request->coti_sector, 4);
            $sectorCandidato = null;
            if ($sectorFormulario) {
                $sectorFormulario = $this->truncateAndPad($sectorFormulario, 4);
                if (Divis::where('divis_codigo', $sectorFormulario)->exists()) {
                    $sectorCandidato = $sectorFormulario;
                }
            }
            $cotizacion->coti_sector = $sectorCandidato;
            
            $this->aplicarReferenciasFacturacionVentas($request, $cotizacion);
            
            // Notas
            $cotizacion->coti_notas = CotizacionNotasGenerales::persistirDesdeRequest($request->coti_notas);

            // Cancelación (manejada desde el checkbox en la vista de edición)
            $cotizacion->cancelada = $request->boolean('cancelada');
            if ($cotizacion->cancelada) {
                $cotizacion->razon_cancelada = $this->sanitizeNullableString($request->razon_cancelada, 1000);
            } else {
                $cotizacion->razon_cancelada = null;
            }

            // Campos de descuentos / aumentos
            $cotizacion->coti_descuentoglobal = $request->filled('descuento') ? floatval($request->descuento) : 0.00;
            $cotizacion->coti_aumentoglobal = $request->filled('aumento') ? floatval($request->aumento) : 0.00;
            $cotizacion->coti_mostrar_descuento = $request->has('coti_mostrar_descuento');
            $cotizacion->divisa_codigo = $request->filled('divisa_codigo') ? $this->sanitizeNullableString($request->divisa_codigo, 10) : ($cotizacion->divisa_codigo ?? 'PES');
            $cotizacion->coti_cond_pago = $request->filled('coti_cond_pago') ? $this->sanitizeNullableString($request->coti_cond_pago, 10) : null;
            $esCuotas = ($cotizacion->coti_cond_pago === 'CUOTAS');
            $cotizacion->coti_cuotas = $esCuotas;
            if ($esCuotas) {
                $cotizacion->coti_cuota_desc = $this->sanitizeNullableString($request->coti_cuota_desc, 100);
                $cotizacion->coti_cuota_cant = $request->filled('coti_cuota_cant') ? (int) $request->coti_cuota_cant : null;
                $cotizacion->coti_cuota_monto_total = $request->filled('coti_cuota_monto_total') ? $this->parseDecimalValue($request->coti_cuota_monto_total) : null;
                $cotizacion->coti_cuota_monto_indiv = $request->filled('coti_cuota_monto_indiv') ? $this->parseDecimalValue($request->coti_cuota_monto_indiv) : null;
                $interesEditar = $this->parseDecimalValue($request->coti_cuota_interes);
                $cotizacion->coti_cuota_interes = $interesEditar !== null ? $interesEditar : 0.0;
                $cotizacion->coti_cuota_fact_fin_mes = $request->has('coti_cuota_fact_fin_mes') && $request->coti_cuota_fact_fin_mes == '1';
                $cotizacion->coti_cuota_fact_inicio_mes = $request->has('coti_cuota_fact_inicio_mes') && $request->coti_cuota_fact_inicio_mes == '1';
            } else {
                $cotizacion->coti_cuota_desc = null;
                $cotizacion->coti_cuota_cant = null;
                $cotizacion->coti_cuota_monto_total = null;
                $cotizacion->coti_cuota_monto_indiv = null;
                $cotizacion->coti_cuota_interes = null;
                $cotizacion->coti_cuota_fact_fin_mes = null;
                $cotizacion->coti_cuota_fact_inicio_mes = null;
            }
            $cotizacion->coti_sector_laboratorio_pct = $request->filled('sector_laboratorio_porcentaje') ? floatval($request->sector_laboratorio_porcentaje) : 0.00;
            $cotizacion->coti_sector_higiene_pct = $request->filled('sector_higiene_porcentaje') ? floatval($request->sector_higiene_porcentaje) : 0.00;
            $cotizacion->coti_sector_microbiologia_pct = $request->filled('sector_microbiologia_porcentaje') ? floatval($request->sector_microbiologia_porcentaje) : 0.00;
            $cotizacion->coti_sector_cromatografia_pct = $request->filled('sector_cromatografia_porcentaje') ? floatval($request->sector_cromatografia_porcentaje) : 0.00;
            $cotizacion->coti_sector_laboratorio_contacto = $this->sanitizeNullableString($request->sector_laboratorio_contacto, 100);
            $cotizacion->coti_sector_higiene_contacto = $this->sanitizeNullableString($request->sector_higiene_contacto, 100);
            $cotizacion->coti_sector_microbiologia_contacto = $this->sanitizeNullableString($request->sector_microbiologia_contacto, 100);
            $cotizacion->coti_sector_cromatografia_contacto = $this->sanitizeNullableString($request->sector_cromatografia_contacto, 100);
            $cotizacion->coti_sector_laboratorio_observaciones = $this->sanitizeNullableString($request->sector_laboratorio_observaciones);
            $cotizacion->coti_sector_higiene_observaciones = $this->sanitizeNullableString($request->sector_higiene_observaciones);
            $cotizacion->coti_sector_microbiologia_observaciones = $this->sanitizeNullableString($request->sector_microbiologia_observaciones);
            $cotizacion->coti_sector_cromatografia_observaciones = $this->sanitizeNullableString($request->sector_cromatografia_observaciones);
            
            // Campos de cadena de custodia y muestreo
            $cotizacion->coti_cadena_custodia = $request->has('coti_cadena_custodia') && $request->coti_cadena_custodia == '1';
            $cotizacion->coti_muestreo = $request->has('coti_muestreo') && $request->coti_muestreo == '1';
            $cotizacion->coti_req_cadena_custodia_relacionada = $request->boolean('coti_req_cadena_custodia_relacionada');
            $cotizacion->coti_prioridad_global = false;

            // Versionado simple: cada vez que se actualiza, incrementamos la versión.
            // Si no existe (migración recién aplicada), asumimos versión 1 y luego sumamos.
            $cotizacion->coti_version = (int)($cotizacion->coti_version ?? 1) + 1;
            
            $cotizacion->save();

            Log::info('Update ventas - antes de procesarEnsayosYComponentes', [
                'coti_num' => $cotizacion->coti_num,
                'tiene_componentes_data' => $request->has('componentes_data'),
                'componentes_data_len' => $request->componentes_data ? strlen($request->componentes_data) : 0,
            ]);
            $this->procesarEnsayosYComponentes($request, $cotizacion->coti_num, true);
            $this->procesarAdjuntosEnsayos($request, $cotizacion->coti_num);
            $this->sincronizarMontosCuotasDesdeItems($cotizacion);

            CotizacionEdicionBloqueo::liberar((int) $id, Auth::user());

            return redirect()->route('ventas.index')
                ->with('success', 'Cotización actualizada exitosamente');
                
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Error al actualizar cotización:', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->route('ventas.index')
                ->with('error', 'Error al actualizar la cotización: ' . $e->getMessage());
        }
    }

    public function descancelar(Request $request, $id)
    {
        try {
            $this->denegarVentasSiUsuarioSoloCanal();

            $user = Auth::user();
            $hasPermission = $user && ($user->usu_nivel >= 900 || $user->hasRole('ventas'));
            if (!$hasPermission) {
                return redirect()->back()->with('error', 'No autorizado');
            }

            $cotizacion = Ventas::find($id);
            if (!$cotizacion) {
                return redirect()->back()->with('error', 'Cotización no encontrada');
            }

            $cotizacion->cancelada = false;
            $cotizacion->razon_cancelada = null;
            $cotizacion->save();

            return redirect()->back()->with('success', 'Cancelación removida correctamente');
        } catch (\Exception $e) {
            Log::error('Error al descancelar cotización', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Error al remover la cancelación');
        }
    }

    public function imprimir($id)
    {
        if (!is_numeric($id)) {
            abort(404, 'Invalid ID');
        }

        $this->denegarVentasSiUsuarioSoloCanal();

        $cotizacion = Ventas::with([
                'cliente.condicionPago',
                'matriz',
                'condicionPago',
                'listaPrecio',
            ])->find($id);

        if (!$cotizacion) {
            abort(404, 'Cotización not found');
        }

        $ensayos = Cotio::where('cotio_numcoti', $cotizacion->coti_num)
            ->where('cotio_subitem', 0)
            ->orderBy('cotio_item')
            ->get();

        $componentes = Cotio::where('cotio_numcoti', $cotizacion->coti_num)
            ->where('cotio_subitem', '>', 0)
            ->with(['metodoAnalisis', 'metodoMuestreo'])
            ->orderBy('cotio_item')
            ->orderBy('cotio_subitem')
            ->get();

        MetodoAnalisisItemCatalogo::preload();

        $componentesAgrupados = $componentes->groupBy(function ($componente) {
            return (int) $componente->cotio_item;
        });

        $agrupadoresPackPdf = CotioItems::muestras()
            ->with('componentesAsociados:id')
            ->get()
            ->keyBy(fn ($item) => Str::lower(trim($item->cotio_descripcion ?? '')));

        $esNuevaLogicaBasePdf = $componentes->contains(function ($c) {
            return (bool) ($c->de_agrupador ?? false);
        });

        $items = $ensayos->map(function ($ensayo) use ($componentesAgrupados, $agrupadoresPackPdf, $esNuevaLogicaBasePdf) {
            $componentesEnsayo = $componentesAgrupados->get((int) $ensayo->cotio_item, collect());

            if (!$componentesEnsayo instanceof \Illuminate\Support\Collection) {
                $componentesEnsayo = collect($componentesEnsayo);
            }

            $cantidadEnsayo = $this->parseDecimalValue($ensayo->cotio_cantidad ?? 1) ?? 1;
            if ($cantidadEnsayo <= 0) {
                $cantidadEnsayo = 1;
            }

            $agrupadorPack = $agrupadoresPackPdf->get(Str::lower(trim($ensayo->cotio_descripcion ?? '')));
            $idsParametrosPack = $agrupadorPack
                ? $agrupadorPack->componentesAsociados->pluck('id')->map(fn ($id) => (string) $id)->values()->all()
                : [];
            $precioPackEnsayo = max(0.0, (float) ($this->parseDecimalValue($ensayo->cotio_precio ?? null) ?? 0));
            $esNuevaLogica = $esNuevaLogicaBasePdf || ($precioPackEnsayo > 0 && !empty($idsParametrosPack));

            $subtotalComponentes = CotizacionPrecioEnsayo::sumaComponentesUnitariaParaEnsayo(
                $componentesEnsayo,
                $precioPackEnsayo,
                $idsParametrosPack
            );

            $cotioPrecioEnsayo = $this->parseDecimalValue($ensayo->cotio_precio ?? null);
            $precioExtraEnsayo = $this->resolverPrecioExtraEnsayoDesdeCotioRow($cotioPrecioEnsayo, (float) $subtotalComponentes, $esNuevaLogica);
            
            // Nueva lógica: Precio Unitario = Extra + Suma de componentes
            $precioUnitario = $precioExtraEnsayo + (float) $subtotalComponentes;
            // Importe = Cantidad de muestras * Precio Unitario Total
            $total = $precioUnitario * $cantidadEnsayo;

            $notasImprimibles = $ensayo->notasImprimiblesList();

            return [
                'item' => (int) $ensayo->cotio_item,
                'descripcion' => trim($ensayo->cotio_descripcion ?? 'Sin descripción'),
                'cantidad' => $cantidadEnsayo,
                'precio_unitario' => $precioUnitario,
                'total' => $total,
                'notas' => $notasImprimibles,
                'req_cadena_custodia' => (bool) ($ensayo->req_cadena_custodia ?? false),
                'req_prot_mapba' => (bool) ($ensayo->req_prot_mapba ?? false),
                'lleva_muestreo' => (bool) ($ensayo->lleva_muestreo ?? true),
                'componentes' => $componentesEnsayo->map(function ($componente) {
                    $precio = $this->parseDecimalValue($componente->cotio_precio ?? 0) ?? 0;
                    $cantidad = $this->parseDecimalValue($componente->cotio_cantidad ?? 1) ?? 1;

                    if ($cantidad <= 0) {
                        $cantidad = 1;
                    }

                    $metodoNombre = MetodoAnalisisItemCatalogo::etiquetaParaLineaCotio($componente);
                    $metodoAnalisisCodigo = trim($componente->cotio_codigometodo_analisis ?? '');

                    return [
                        'descripcion' => trim($componente->cotio_descripcion ?? ''),
                        'metodo' => $metodoNombre,
                        'metodo_codigo' => $metodoAnalisisCodigo,
                        'unidad' => trim($componente->cotio_codigoum ?? ''),
                        'cantidad' => $cantidad,
                        'precio' => $precio,
                        'total' => $precio * $cantidad,
                        'req_cadena_custodia' => (bool) ($componente->req_cadena_custodia ?? false),
                        'req_prot_mapba' => (bool) ($componente->req_prot_mapba ?? false),
                        'de_agrupador' => (bool) ($componente->de_agrupador ?? false),
                        'notas' => $componente->notasImprimiblesList(),
                    ];
                })->values(),
            ];
        })->sortBy('item')->values();

        $ensayoItems = $ensayos->pluck('cotio_item')->map(function ($item) {
            return (int) $item;
        });

        $componentesSueltos = $componentes->filter(function ($componente) use ($ensayoItems) {
            return !$ensayoItems->contains((int) $componente->cotio_item);
        })->map(function ($componente) {
            $precio = $this->parseDecimalValue($componente->cotio_precio ?? 0) ?? 0;
            $cantidad = $this->parseDecimalValue($componente->cotio_cantidad ?? 1) ?? 1;

            if ($cantidad <= 0) {
                $cantidad = 1;
            }

            $metodoNombre = MetodoAnalisisItemCatalogo::etiquetaParaLineaCotio($componente);
            $metodoAnalisisCodigo = trim($componente->cotio_codigometodo_analisis ?? '');

            return [
                'descripcion' => trim($componente->cotio_descripcion ?? ''),
                'metodo' => $metodoNombre,
                'metodo_codigo' => $metodoAnalisisCodigo,
                'unidad' => trim($componente->cotio_codigoum ?? ''),
                'cantidad' => $cantidad,
                'precio' => $precio,
                'total' => $precio * $cantidad,
                'de_agrupador' => (bool) ($componente->de_agrupador ?? false),
            ];
        })->values();

        // Aplicar aumento global de la cotización a cada ítem/componentes del PDF
        $aumentoPorcentaje = max(0.0, min((float) ($cotizacion->coti_aumentoglobal ?? 0.0), 100.0));
        $factorAumento = 1.0 + ($aumentoPorcentaje / 100.0);

        if ($aumentoPorcentaje > 0) {
            $items = $items->map(function ($item) use ($factorAumento) {
                $item['precio_unitario'] = ($item['precio_unitario'] ?? 0) * $factorAumento;
                $item['total'] = ($item['total'] ?? 0) * $factorAumento;

                if (isset($item['componentes']) && $item['componentes'] instanceof \Illuminate\Support\Collection) {
                    $item['componentes'] = $item['componentes']->map(function ($componente) use ($factorAumento) {
                        $componente['precio'] = ($componente['precio'] ?? 0) * $factorAumento;
                        $componente['total'] = ($componente['total'] ?? 0) * $factorAumento;
                        return $componente;
                    });
                }

                return $item;
            });

            $componentesSueltos = $componentesSueltos->map(function ($componente) use ($factorAumento) {
                $componente['precio'] = ($componente['precio'] ?? 0) * $factorAumento;
                $componente['total'] = ($componente['total'] ?? 0) * $factorAumento;
                return $componente;
            });
        }

        $subtotalItems = $items->sum(function ($item) {
            // El 'total' ya incluye base + componentes multiplicado por cantidad de muestras
            return (float) ($item['total'] ?? 0);
        });

        $totalComponentesSueltos = $componentesSueltos->sum(function ($componente) {
            return $componente['total'];
        });

        $subtotal = $subtotalItems + $totalComponentesSueltos;

        $subtotalComponentesEnItems = $items->sum(function ($item) use ($componentesAgrupados) {
            $itemNum = (int) $item['item'];
            $componentesEnsayo = $componentesAgrupados->get($itemNum, collect());
            
            $sumaComponentesUnitaria = $componentesEnsayo->sum(function ($componente) {
                if ($componente->de_agrupador) {
                    return 0;
                }
                $precio = $this->parseDecimalValue($componente->cotio_precio ?? 0) ?? 0;
                $cantidad = $this->parseDecimalValue($componente->cotio_cantidad ?? 1) ?? 1;
                return $precio * ($cantidad <= 0 ? 1 : $cantidad);
            });
            
            return (float) $item['cantidad'] * $sumaComponentesUnitaria;
        });

        $subtotalAdicionalEnsayos = $subtotalItems - $subtotalComponentesEnItems;


        $cliente = $cotizacion->cliente;
        $descuentoPorcentaje = max($this->calcularDescuentoCotizacion($cotizacion), 0);
        $descuentoMonto = $subtotal * ($descuentoPorcentaje / 100);
        $totalConDescuento = $subtotal - $descuentoMonto;

        $totalMuestras = $ensayos->sum(function ($ensayo) {
            $cantidad = $this->parseDecimalValue($ensayo->cotio_cantidad ?? 1) ?? 1;
            return $cantidad > 0 ? $cantidad : 1;
        });

        // Condición de pago: priorizar la guardada en la cotización
        $codigoCondicion = $cotizacion->coti_cond_pago ? trim($cotizacion->coti_cond_pago) : null;
        $condicionPago = null;

        // Caso especial: "CUOTAS" — montos alineados al total del presupuesto
        if ($codigoCondicion === 'CUOTAS') {
            $descuentoGlobalPdf = max((float) ($cotizacion->coti_descuentoglobal ?? 0), 0);
            $sectorCodigoPdf = $this->normalizarCodigoSector($cotizacion->coti_sector);
            $descuentoSectorPdf = 0.0;
            if ($sectorCodigoPdf) {
                $descuentoSectorPdf = $this->obtenerDescuentoSectorCotizacion($cotizacion, $sectorCodigoPdf);
            }
            $resumenPdf = CotizacionResumenEconomico::calcular(
                $ensayos->concat($componentes),
                (float) ($cotizacion->coti_aumentoglobal ?? 0),
                $descuentoGlobalPdf,
                $descuentoSectorPdf
            );
            $cantCuotasPdf = max(1, (int) ($cotizacion->coti_cuota_cant ?? 1));
            $montosCuotasPdf = CotizacionResumenEconomico::calcularCuotas(
                $resumenPdf['total_final'],
                $cantCuotasPdf,
                (float) ($cotizacion->coti_cuota_interes ?? 0)
            );
            $condicionPago = CotizacionResumenEconomico::textoCondicionPagoCuotas(
                $cotizacion->coti_cuota_desc,
                $cantCuotasPdf,
                $montosCuotasPdf['monto_individual'],
                $montosCuotasPdf['monto_total']
            );
        } elseif ($codigoCondicion) {
            // pag_codigo suele venir con padding (CHAR). La relación puede fallar si el código viene trimmeado.
            // Resolver por query trim para respetar siempre la condición guardada en la cotización.
            $registroCondicion = CondicionPago::whereRaw('LTRIM(RTRIM(pag_codigo)) = ?', [$codigoCondicion])->first();
            $condicionPago = $registroCondicion ? trim((string) ($registroCondicion->pag_descripcion ?? '')) : null;
        }

        if (!$condicionPago && $cliente) {
            $condicionPago = optional($cliente->condicionPago)->pag_descripcion;
        }

        if (!$condicionPago) {
            $condicionPago = 'Contra entrega';
        }

        // Verificar si hay empresa relacionada (coti_empresa_rel o legado coti_cli_empresa)
        $tieneEmpresaRelacionada = false;
        $empresaRelacionada = null;
        
        $idEmpresaRel = $cotizacion->coti_empresa_rel ?? $cotizacion->coti_cli_empresa;
        if ($idEmpresaRel) {
            $empresa = ClienteEmpresaRelacionada::find($idEmpresaRel);
            if ($empresa) {
                $tieneEmpresaRelacionada = true;
                $empresaRelacionada = [
                    'razon_social' => trim($empresa->razon_social),
                    'cuit' => $empresa->cuit ? trim($empresa->cuit) : null,
                    'direcciones' => $empresa->direcciones ? trim($empresa->direcciones) : null,
                    'localidad' => $empresa->localidad ? trim($empresa->localidad) : null,
                    'partido' => $empresa->partido ? trim($empresa->partido) : null,
                    'contacto' => $empresa->contacto ? trim($empresa->contacto) : null,
                ];
            }
        }

        // Sucursal como destinatario (prioridad sobre empresa relacionada cuando está seleccionada)
        $tieneSucursal = false;
        $sucursalDestinatario = null;
        if (trim((string) ($cotizacion->coti_codigosuc ?? '')) !== '') {
            $sucursal = Clientes::where('cli_codigo', trim($cotizacion->coti_codigosuc))->first();
            if ($sucursal) {
                $tieneSucursal = true;
                $nombreBase = trim($sucursal->cli_razonsocial ?? $sucursal->cli_fantasia ?? 'Sucursal');
                $sucursalDestinatario = [
                    'razon_social' => $nombreBase . ' (sucursal)',
                    'cuit' => $sucursal->cli_cuit ? trim($sucursal->cli_cuit) : null,
                    'direcciones' => $sucursal->cli_direccion ? trim($sucursal->cli_direccion) : null,
                    'localidad' => $sucursal->cli_localidad ? trim($sucursal->cli_localidad) : null,
                    'partido' => $sucursal->cli_partido ? trim($sucursal->cli_partido) : null,
                    'codigo_postal' => $sucursal->cli_codigopostal ? trim($sucursal->cli_codigopostal) : null,
                    'contacto' => $sucursal->cli_contacto ? trim($sucursal->cli_contacto) : null,
                ];
            }
        }

        // Obtener razón social de facturación predeterminada si existe
        $razonSocialPredeterminada = null;
        if ($cliente) {
            $razonSocialPredeterminada = ClienteRazonSocialFacturacion::where('cli_codigo', $cliente->cli_codigo)
                ->where('es_predeterminada', true)
                ->first();
        }

        // Contactos de la cotización (1 a 4) ordenados por tipo (igual que showDetalle)
        $contactosCoti = [];
        $contactosCoti[] = ['nombre' => trim($cotizacion->coti_contacto ?? ''), 'correo' => trim($cotizacion->coti_mail1 ?? ''), 'telefono' => trim($cotizacion->coti_telefono ?? ''), 'tipo' => trim($cotizacion->coti_contacto_tipo1 ?? '')];
        $contactosCoti[] = ['nombre' => trim($cotizacion->coti_contacto2 ?? ''), 'correo' => trim($cotizacion->coti_mail2 ?? ''), 'telefono' => trim($cotizacion->coti_telefono2 ?? ''), 'tipo' => trim($cotizacion->coti_contacto_tipo2 ?? '')];
        $contactosCoti[] = ['nombre' => trim($cotizacion->coti_contacto3 ?? ''), 'correo' => trim($cotizacion->coti_mail3 ?? ''), 'telefono' => trim($cotizacion->coti_telefono3 ?? ''), 'tipo' => trim($cotizacion->coti_contacto_tipo3 ?? '')];
        $contactosCoti[] = ['nombre' => trim($cotizacion->coti_contacto4 ?? ''), 'correo' => trim($cotizacion->coti_mail4 ?? ''), 'telefono' => trim($cotizacion->coti_telefono4 ?? ''), 'tipo' => trim($cotizacion->coti_contacto_tipo4 ?? '')];
        $contactosCoti = array_filter($contactosCoti, function ($c) {
            return ($c['nombre'] ?? '') !== '' || ($c['correo'] ?? '') !== '' || ($c['telefono'] ?? '') !== '';
        });
        usort($contactosCoti, function ($a, $b) {
            $ta = $a['tipo'] ?? '';
            $tb = $b['tipo'] ?? '';
            if ($ta === '' && $tb === '') return 0;
            if ($ta === '') return 1;
            if ($tb === '') return -1;
            return strcasecmp($ta, $tb);
        });
        $contactosOrdenados = array_values($contactosCoti);

        $creadorCodigoPdf = trim((string) ($cotizacion->coti_creador ?? ''));
        $nombreCreadorCoti = '';
        if ($creadorCodigoPdf !== '') {
            $nombreCreadorCoti = trim((string) (User::where('usu_codigo', $creadorCodigoPdf)->value('usu_descripcion') ?? ''));
        }

        $facturacionLocalidadLinePdf = trim((string) ($cotizacion->coti_localidad ?? ''));
        $partidoFactPdf = trim((string) ($cotizacion->coti_partido ?? ''));
        if ($partidoFactPdf !== '') {
            $facturacionLocalidadLinePdf = $facturacionLocalidadLinePdf !== ''
                ? $facturacionLocalidadLinePdf . ' - ' . $partidoFactPdf
                : $partidoFactPdf;
        }

        $cotizacionVerUrl = null;
        $paraPdfCtrl = trim((string) ($cotizacion->coti_para ?? ''));
        $cliNombrePdfCtrl = trim((string) (optional($cliente)->cli_razonsocial ?? ''));
        if ($cliNombrePdfCtrl === '') {
            $cliNombrePdfCtrl = trim((string) (optional($cliente)->cli_fantasia ?? ''));
        }
        if ($cliNombrePdfCtrl === '') {
            $cliNombrePdfCtrl = trim((string) ($cotizacion->coti_empresa ?? ''));
        }
        $esConsPdfCtrl = (bool) (optional($cliente)->es_consultor ?? false);
        $paraDistintoCliPdfCtrl = $cliNombrePdfCtrl !== '' && $paraPdfCtrl !== '' && strcasecmp($paraPdfCtrl, $cliNombrePdfCtrl) !== 0;
        $mostrarEnlaceCotPdf = ($tieneEmpresaRelacionada && $empresaRelacionada)
            || (bool) ($cotizacion->coti_para_empresa_rel ?? false)
            || !empty($cotizacion->coti_empresa_rel)
            || !empty($cotizacion->coti_cli_empresa)
            || ($esConsPdfCtrl && $paraDistintoCliPdfCtrl);
        if ($mostrarEnlaceCotPdf) {
            try {
                $cotizacionVerUrl = URL::route('cotizaciones.ver-detalle', $cotizacion->coti_num);
            } catch (\Throwable $e) {
                $cotizacionVerUrl = url('/cotizaciones/' . $cotizacion->coti_num);
            }
        }

        $data = [
            'cotizacion' => $cotizacion,
            'items' => $items,
            'componentesSueltos' => $componentesSueltos,
            'totales' => [
                'subtotal_items' => $subtotalItems,
                'subtotal_adicional_ensayos' => $subtotalAdicionalEnsayos,
                'subtotal_componentes_en_items' => $subtotalComponentesEnItems,
                'subtotal_componentes' => $totalComponentesSueltos,
                'subtotal' => $subtotal,
                'descuento_porcentaje' => $descuentoPorcentaje,
                'descuento_monto' => $descuentoMonto,
                'total' => $totalConDescuento,
                'total_muestras' => $totalMuestras,
            ],
            'condicionPagoDescripcion' => $condicionPago,
            'fechaActual' => Carbon::now(),
            'tieneEmpresaRelacionada' => $tieneEmpresaRelacionada,
            'empresaRelacionada' => $empresaRelacionada,
            'tieneSucursal' => $tieneSucursal,
            'sucursalDestinatario' => $sucursalDestinatario,
            'razonSocialPredeterminada' => $razonSocialPredeterminada,
            'contactosOrdenados' => $contactosOrdenados,
            'nombreCreadorCoti' => $nombreCreadorCoti,
            'facturacionLocalidadLinePdf' => $facturacionLocalidadLinePdf,
            'cotizacionVerUrl' => $cotizacionVerUrl,
        ];

        $pdf = Pdf::loadView('ventas.pdf', $data);
        $pdf->setPaper('A4', 'portrait');

        $fileName = 'Cotizacion_' . trim((string) $cotizacion->coti_num) . '.pdf';

        return $pdf->stream($fileName);
    }

    // API para buscar clientes
    public function buscarClientes(Request $request)
    {
        $termino = $request->get('q', '');
        
        if (strlen($termino) < 2) {
            return response()->json([]);
        }

        try {
            // Solo buscar clientes principales (no sucursales)
            $clientes = Clientes::soloPrincipales()
                ->where('cli_estado', true)
                ->where(function($query) use ($termino) {
                    $query->where('cli_codigo', 'ILIKE', "%{$termino}%")
                          ->orWhere('cli_razonsocial', 'ILIKE', "%{$termino}%")
                          ->orWhere('cli_fantasia', 'ILIKE', "%{$termino}%");
                })
                ->limit(10)
                ->get()
                ->map(function($cliente) {
                    return [
                        'id' => trim($cliente->cli_codigo),
                        'codigo' => trim($cliente->cli_codigo),
                        'text' => trim($cliente->cli_codigo) . ' - ' . trim($cliente->cli_razonsocial),
                        'razon_social' => trim($cliente->cli_razonsocial),
                        'fantasia' => $cliente->cli_fantasia ? trim($cliente->cli_fantasia) : null,
                        'direccion' => $cliente->cli_direccion ? trim($cliente->cli_direccion) : null,
                        'localidad' => $cliente->cli_localidad ? trim($cliente->cli_localidad) : null,
                        'cuit' => $cliente->cli_cuit,
                        'codigo_postal' => $cliente->cli_codigopostal ? trim($cliente->cli_codigopostal) : null,
                        'telefono' => $cliente->cli_telefono,
                        'email' => $cliente->cli_email ? trim($cliente->cli_email) : null,
                        'contacto' => $cliente->cli_contacto ? trim($cliente->cli_contacto) : null,
                        'sector' => $cliente->cli_codigocrub ? trim($cliente->cli_codigocrub) : null,
                    ];
                });

            return response()->json($clientes);

        } catch (\Exception $e) {
            Log::error('Error buscando clientes:', ['error' => $e->getMessage()]);
            return response()->json([]);
        }
    }

    // API para obtener empresas relacionadas de un cliente
    public function obtenerEmpresasRelacionadas($codigoCliente)
    {
        try {
            // Normalizar el código del cliente (trim y padding)
            $codigoTrimmed = trim($codigoCliente);
            $codigoNormalizado = str_pad($codigoTrimmed, 10, ' ', STR_PAD_RIGHT);
            
            Log::info('Buscando empresas relacionadas:', [
                'codigo_original' => $codigoCliente,
                'codigo_trimmed' => $codigoTrimmed,
                'codigo_normalizado' => "'" . $codigoNormalizado . "'"
            ]);
            
            // Buscar con el código normalizado
            $empresas = ClienteEmpresaRelacionada::where('cli_codigo', $codigoNormalizado)
                ->orderBy('razon_social')
                ->get();
            
            // Si no se encontraron, intentar buscar sin padding (por si acaso)
            if ($empresas->isEmpty() && $codigoTrimmed !== $codigoNormalizado) {
                Log::info('No se encontraron empresas con código normalizado, intentando con código trimmed');
                $empresas = ClienteEmpresaRelacionada::whereRaw("TRIM(cli_codigo) = ?", [$codigoTrimmed])
                    ->orderBy('razon_social')
                    ->get();
            }
            
            Log::info('Empresas encontradas:', ['count' => $empresas->count()]);
            
            $empresasMapeadas = $empresas->map(function($empresa) {
                return [
                    'id' => $empresa->id,
                    'razon_social' => trim($empresa->razon_social),
                    'cuit' => $empresa->cuit ? trim($empresa->cuit) : null,
                    'direcciones' => $empresa->direcciones ? trim($empresa->direcciones) : null,
                    'localidad' => $empresa->localidad ? trim($empresa->localidad) : null,
                    'partido' => $empresa->partido ? trim($empresa->partido) : null,
                    'contacto' => $empresa->contacto ? trim($empresa->contacto) : null,
                ];
            });

            return response()->json($empresasMapeadas);
        } catch (\Exception $e) {
            Log::error('Error obteniendo empresas relacionadas:', [
                'codigo' => $codigoCliente,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([]);
        }
    }

    /**
     * Una empresa relacionada por id (tabla cliente_empresas_relacionadas).
     */
    public function obtenerEmpresaRelacionadaPorId(int $id)
    {
        try {
            $emp = ClienteEmpresaRelacionada::find($id);
            if (!$emp) {
                return response()->json(['error' => 'No encontrada'], 404);
            }

            return response()->json([
                'id' => $emp->id,
                'razon_social' => trim((string) ($emp->razon_social ?? '')),
                'cuit' => $emp->cuit ? trim((string) $emp->cuit) : null,
                'direcciones' => $emp->direcciones ? trim((string) $emp->direcciones) : null,
                'localidad' => $emp->localidad ? trim((string) $emp->localidad) : null,
                'partido' => $emp->partido ? trim((string) $emp->partido) : null,
                'contacto' => $emp->contacto ? trim((string) $emp->contacto) : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo empresa relacionada por id', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['error' => 'Error interno'], 500);
        }
    }

    // API para obtener datos de un cliente específico
    public function obtenerCliente($codigo)
    {
        try {
            Log::info('=== API OBTENER CLIENTE ===');
            Log::info('Código recibido:', ['codigo_original' => $codigo]);
            
            $codigoPadded = str_pad($codigo, 10, ' ', STR_PAD_RIGHT);
            Log::info('Código con padding:', ['codigo_padded' => "'" . $codigoPadded . "'"]);
            
            $cliente = Clientes::where('cli_codigo', $codigoPadded)
                ->where('cli_estado', true)
                ->first();

            if (!$cliente) {
                Log::warning('Cliente no encontrado:', ['codigo' => $codigo]);
                return response()->json(['error' => 'Cliente no encontrado'], 404);
            }

            // Cargar sucursales, contactos y razones sociales de facturación del cliente
            $cliente->load(['sucursales', 'contactos']);

            // Buscar razón social de facturación predeterminada
            $razonSocialPredeterminada = ClienteRazonSocialFacturacion::where('cli_codigo', $codigoPadded)
                ->where('es_predeterminada', true)
                ->first();

            // Listado completo de razones sociales de facturación (para selector en solapa Empresa)
            $razonesSociales = ClienteRazonSocialFacturacion::where('cli_codigo', $codigoPadded)
                ->orderBy('es_predeterminada', 'desc')
                ->orderBy('razon_social')
                ->get()
                ->map(function ($razon) {
                    return [
                        'id' => $razon->id,
                        'razon_social' => trim($razon->razon_social),
                        'cuit' => $razon->cuit ? trim($razon->cuit) : null,
                        'direccion' => $razon->direccion ? trim($razon->direccion) : null,
                        'condicion_iva' => $razon->condicion_iva ? trim($razon->condicion_iva) : null,
                        'condicion_iva_desc' => $razon->condicion_iva_desc ? trim($razon->condicion_iva_desc) : null,
                        'condicion_pago' => $razon->condicion_pago ? trim($razon->condicion_pago) : null,
                        'condicion_pago_desc' => $razon->condicion_pago_desc ? trim($razon->condicion_pago_desc) : null,
                        'tipo_factura' => $razon->tipo_factura ? trim($razon->tipo_factura) : null,
                        'es_predeterminada' => (bool) ($razon->es_predeterminada ?? false),
                    ];
                })
                ->values();

            // Si hay una razón social predeterminada, usar esos datos para la solapa Empresa
            // Si no, usar los datos por defecto del cliente
            $razonSocialEmpresa = $razonSocialPredeterminada ? trim($razonSocialPredeterminada->razon_social) : trim($cliente->cli_razonsocial);
            $direccionEmpresa = $razonSocialPredeterminada && $razonSocialPredeterminada->direccion 
                ? trim($razonSocialPredeterminada->direccion) 
                : ($cliente->cli_direccion ? trim($cliente->cli_direccion) : '');
            $cuitEmpresa = $razonSocialPredeterminada && $razonSocialPredeterminada->cuit 
                ? trim($razonSocialPredeterminada->cuit) 
                : ($cliente->cli_cuit ?: '');
            
            // Para localidad y código postal, usar los del cliente (no están en razones sociales)
            $localidadEmpresa = $cliente->cli_localidad ? trim($cliente->cli_localidad) : '';
            $codigoPostalEmpresa = $cliente->cli_codigopostal ? trim($cliente->cli_codigopostal) : '';

            // Mapear sucursales a un formato sencillo para el front
            $sucursalesData = $cliente->sucursales->map(function ($sucursal) use ($cliente) {
                return [
                    'codigo' => trim($sucursal->cli_codigo),
                    'razon_social' => $sucursal->cli_razonsocial
                        ? trim($sucursal->cli_razonsocial)
                        : trim($cliente->cli_razonsocial),
                    'fantasia' => $sucursal->cli_fantasia ? trim($sucursal->cli_fantasia) : '',
                    'direccion' => $sucursal->cli_direccion ? trim($sucursal->cli_direccion) : '',
                    'partido' => $sucursal->cli_partido ? trim($sucursal->cli_partido) : '',
                    'localidad' => $sucursal->cli_localidad ? trim($sucursal->cli_localidad) : '',
                    'provincia' => $sucursal->cli_codigoprv ? trim($sucursal->cli_codigoprv) : '',
                    'codigo_postal' => $sucursal->cli_codigopostal ? trim($sucursal->cli_codigopostal) : '',
                    'contacto' => $sucursal->cli_contacto ? trim($sucursal->cli_contacto) : '',
                    'telefono' => $sucursal->cli_telefono ? trim($sucursal->cli_telefono) : '',
                    'email' => $sucursal->cli_email ? trim($sucursal->cli_email) : '',
                ];
            })->values();

            // Mapear contactos del cliente (tabla cliente_contactos)
            $contactosData = $cliente->contactos->map(function ($contacto) {
                return [
                    'id' => $contacto->id,
                    'nombre' => $contacto->nombre ? trim($contacto->nombre) : '',
                    'telefono' => $contacto->telefono ? trim($contacto->telefono) : '',
                    'email' => $contacto->email ? trim($contacto->email) : '',
                    'tipo' => $contacto->tipo ? trim($contacto->tipo) : '',
                ];
            })->values();

            $clienteData = [
                'codigo' => trim($cliente->cli_codigo),
                'razon_social' => trim($cliente->cli_razonsocial),
                'fantasia' => $cliente->cli_fantasia ? trim($cliente->cli_fantasia) : '',
                'direccion' => $cliente->cli_direccion ? trim($cliente->cli_direccion) : '',
                'localidad' => $cliente->cli_localidad ? trim($cliente->cli_localidad) : '',
                'cuit' => $cliente->cli_cuit ?: '',
                'codigo_postal' => $cliente->cli_codigopostal ? trim($cliente->cli_codigopostal) : '',
                'telefono' => $cliente->cli_telefono ?: '',
                'email' => $cliente->cli_email ?: '',
                'contacto' => $cliente->cli_contacto ? trim($cliente->cli_contacto) : '',
                'sector' => $cliente->cli_codigocrub ? trim($cliente->cli_codigocrub) : '',
                'condicion_pago' => $cliente->cli_codigopag ? trim($cliente->cli_codigopag) : '',
                'lista_precios' => $cliente->cli_codigolp ? trim($cliente->cli_codigolp) : '',
                'nro_precio' => $cliente->cli_nroprecio ?: 1,
                'descuento_global' => (float) ($cliente->cli_descuentoglobal ?? 0),
                'descuentos_sector' => $this->obtenerDescuentosSectorCliente($cliente),
                'es_consultor' => (bool) ($cliente->es_consultor ?? false),
                // Datos de la razón social predeterminada para la solapa Empresa
                'razon_social_facturacion' => $razonSocialEmpresa,
                'direccion_facturacion' => $direccionEmpresa,
                'cuit_facturacion' => $cuitEmpresa,
                'localidad_facturacion' => $localidadEmpresa,
                'codigo_postal_facturacion' => $codigoPostalEmpresa,
                'tiene_razon_social_predeterminada' => $razonSocialPredeterminada !== null,
                'razones_sociales_facturacion' => $razonesSociales,
                // Sucursales del cliente
                'sucursales' => $sucursalesData,
                'contactos' => $contactosData,
            ];
            
            Log::info('Datos del cliente encontrado:', $clienteData);
            return response()->json($clienteData);

        } catch (\Exception $e) {
            Log::error('Error obteniendo cliente:', ['codigo' => $codigo, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * API para agregar un contacto al cliente (desde cotización)
     */
    public function agregarContactoCliente(Request $request, string $codigo)
    {
        $this->denegarVentasSiUsuarioSoloCanal();

        $request->validate([
            'nombre' => 'required|string|max:120',
            'telefono' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:120',
            'tipo' => 'nullable|string|max:30',
        ]);

        $cliente = Clientes::where('cli_codigo', trim($codigo))->first();
        if (!$cliente) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        $contacto = ClienteContacto::create([
            'cli_codigo' => $cliente->cli_codigo,
            'nombre' => $this->sanitizeNullableString($request->nombre, 120),
            'telefono' => $request->filled('telefono') ? $this->sanitizeNullableString($request->telefono, 30) : null,
            'email' => $request->filled('email') ? $this->sanitizeNullableString($request->email, 120) : null,
            'tipo' => $request->filled('tipo') ? $this->sanitizeNullableString($request->tipo, 30) : null,
        ]);

        $contactosData = $cliente->contactos()->orderBy('nombre')->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'nombre' => $c->nombre ? trim($c->nombre) : '',
                'telefono' => $c->telefono ? trim($c->telefono) : '',
                'email' => $c->email ? trim($c->email) : '',
                'tipo' => $c->tipo ? trim($c->tipo) : '',
            ];
        })->values();

        return response()->json([
            'contacto' => [
                'id' => $contacto->id,
                'nombre' => $contacto->nombre,
                'telefono' => $contacto->telefono,
                'email' => $contacto->email,
                'tipo' => $contacto->tipo,
            ],
            'contactos' => $contactosData,
        ]);
    }
    public function obtenerEnsayos(Request $request)
    {
        $termino = $request->get('q', '');
        
        $ensayos = CotioItems::muestras()
            ->when($termino, function($query, $termino) {
                return $query->buscar($termino);
            })
            ->with(['metodoAnalitico', 'metodoMuestreo', 'componentesAsociados:id,cotio_descripcion', 'matrices'])
            ->orderBy('cotio_descripcion')
            ->get();

        return response()->json($ensayos->map(function($ensayo) {
            // Intentar obtener el método desde la relación primero
            $metodoCodigo = optional($ensayo->metodoAnalitico)->metodo_codigo 
                ? trim(optional($ensayo->metodoAnalitico)->metodo_codigo) 
                : ($ensayo->metodo ? trim($ensayo->metodo) : null);
            
            $metodoDescripcion = optional($ensayo->metodoAnalitico)->metodo_descripcion;
            
            // Si tenemos el código pero no la descripción, intentar cargar el método
            if ($metodoCodigo && !$metodoDescripcion) {
                $metodo = \App\Models\Metodo::where('metodo_codigo', trim($metodoCodigo))->first();
                $metodoDescripcion = $metodo ? $metodo->metodo_descripcion : null;
            }
            
            // Obtener método de muestreo
            $metodoMuestreoCodigo = optional($ensayo->metodoMuestreo)->metodo_codigo 
                ? trim(optional($ensayo->metodoMuestreo)->metodo_codigo) 
                : ($ensayo->metodo_muestreo ? trim($ensayo->metodo_muestreo) : null);
            
            $metodoMuestreoDescripcion = optional($ensayo->metodoMuestreo)->metodo_descripcion;
            
            // Si tenemos el código pero no la descripción, intentar cargar el método de muestreo
            if ($metodoMuestreoCodigo && !$metodoMuestreoDescripcion) {
                $metodoMuestreo = \App\Models\Metodo::where('metodo_codigo', trim($metodoMuestreoCodigo))->first();
                $metodoMuestreoDescripcion = $metodoMuestreo ? $metodoMuestreo->metodo_descripcion : null;
            }
            
            // Obtener matriz desde la tabla pivote o desde matriz_codigo directo (para compatibilidad)
            $matrizCodigo = null;
            $matrizDescripcion = null;
            
            if ($ensayo->matrices->isNotEmpty()) {
                $matriz = $ensayo->matrices->first();
                $matrizCodigo = $matriz->matriz_codigo;
                $matrizDescripcion = $matriz->matriz_descripcion;
            } elseif ($ensayo->matriz_codigo) {
                // Fallback: usar matriz_codigo directo si existe
                $matrizCodigo = trim($ensayo->matriz_codigo);
                $matriz = \App\Models\Matriz::where('matriz_codigo', $matrizCodigo)->first();
                $matrizDescripcion = $matriz ? trim($matriz->matriz_descripcion) : null;
            }

            $precioDefinido = $ensayo->precio !== null && $ensayo->precio !== '';
            $precioCatalogo = $precioDefinido ? (float) $ensayo->precio : null;
            
            return [
                'id' => $ensayo->id,
                'codigo' => str_pad($ensayo->id, 15, '0', STR_PAD_LEFT), // Generar código
                'descripcion' => $ensayo->cotio_descripcion,
                'es_muestra' => $ensayo->es_muestra,
                'metodo_codigo' => $metodoCodigo,
                'metodo_descripcion' => $metodoDescripcion,
                'metodo_muestreo_codigo' => $metodoMuestreoCodigo,
                'metodo_muestreo_descripcion' => $metodoMuestreoDescripcion,
                'matriz_codigo' => $matrizCodigo,
                'matriz_descripcion' => $matrizDescripcion,
                'text' => $ensayo->cotio_descripcion, // Para select2
                // Incluye componentes del agrupador (cotio_item_component) para preselección en cotizaciones
                'componentes_default' => $ensayo->componentesAsociados->pluck('id')->values()->all(),
                'nota_imprimible' => $this->textoCatalogoCotioItem($ensayo->nota_imprimible ?? null),
                'nota_interna' => $this->textoCatalogoCotioItem($ensayo->nota_interna ?? null),
                'precio' => $precioCatalogo,
                'precio_definido' => $precioDefinido,
            ];
        }));
    }

    /**
     * Texto de nota por defecto del ítem de catálogo (cotio_items): null si vacío.
     */
    private function textoCatalogoCotioItem($valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $t = trim((string) $valor);

        return $t === '' ? null : $t;
    }

    /**
     * API para obtener componentes (análisis) disponibles
     */
    public function obtenerComponentes(Request $request)
    {
        $termino = $request->get('q', '');
        $matrizCodigo = $request->get('matriz_codigo', '');
        $incluirAgrupadores = $request->get('incluir_agrupadores', false); // Nuevo parámetro
        
        // Incluir tanto componentes como agrupadores si se solicita
        $query = CotioItems::query();
        
        if (!$incluirAgrupadores) {
            // Por defecto, solo componentes (comportamiento original)
            $query->componentes();
        }
        // Si incluirAgrupadores es true, no filtrar por es_muestra (incluir ambos)
        
        // Si hay filtro de matriz e incluirAgrupadores, los agrupadores no deben filtrarse por matriz
        if ($matrizCodigo && $incluirAgrupadores) {
            // Obtener componentes filtrados por matriz
            $componentesFiltrados = $query
                ->componentes() // Solo componentes
                ->when($termino, function($query, $termino) {
                    return $query->buscar($termino);
                })
                ->whereHas('matrices', function($q) use ($matrizCodigo) {
                    $matrizCodigoLimpio = trim($matrizCodigo);
                    $q->whereRaw('TRIM(cotio_items_matriz.matriz_codigo) = ?', [trim($matrizCodigoLimpio)]);
                })
                ->with(['metodoAnalitico', 'matrices', 'componentesAsociados'])
                ->get();
            
            // Obtener solo agrupadores con agregable_a_comps = true (sin filtro de matriz)
            $agrupadores = CotioItems::muestras()
                ->where('agregable_a_comps', true)
                ->when($termino, function($query, $termino) {
                    return $query->buscar($termino);
                })
                ->with(['metodoAnalitico', 'matrices', 'componentesAsociados'])
                ->get();
            
            // Combinar ambos
            $componentes = $componentesFiltrados->merge($agrupadores)->sortBy('cotio_descripcion')->values();
        } else {
            // Comportamiento normal: filtrar todos por matriz si se especifica
            $componentes = $query
                ->when($termino, function($query, $termino) {
                    return $query->buscar($termino);
                })
                ->when($matrizCodigo, function($query, $matrizCodigo) {
                    // Filtrar componentes que están relacionados con la matriz en la tabla pivote
                    // Limpiar espacios en blanco del código de matriz
                    $matrizCodigoLimpio = trim($matrizCodigo);
                    return $query->whereHas('matrices', function($q) use ($matrizCodigoLimpio) {
                        // Comparar con trim para manejar espacios en blanco
                        // Especificar explícitamente la tabla pivote para evitar ambigüedad
                        // La relación belongsToMany usa la tabla pivote cotio_items_matriz
                        $q->whereRaw('TRIM(cotio_items_matriz.matriz_codigo) = ?', [trim($matrizCodigoLimpio)]);
                    });
                })
                ->with(['metodoAnalitico', 'matrices', 'componentesAsociados'])
                ->orderBy('cotio_descripcion')
                ->get();
        }

        return response()->json($componentes->map(function($componente) {
            // Método de ANÁLISIS: CotioItems.metodo → cotio_codigometodo_analisis
            $metodoAnalisisCodigo = $componente->metodo ? trim($componente->metodo) : null;

            // Método de MUESTREO: CotioItems.metodo_muestreo → cotio_codigometodo
            $metodoMuestreoCodigo = $componente->metodo_muestreo ? trim($componente->metodo_muestreo) : null;

            // Descripción del método de análisis (tabla metodo)
            $metodoDescripcion = optional($componente->metodoAnalitico)->metodo_descripcion;
            if (!$metodoDescripcion && $metodoAnalisisCodigo) {
                $metodoDescripcion = Metodo::query()
                    ->whereRaw('trim(metodo_codigo) = ?', [$metodoAnalisisCodigo])
                    ->value('metodo_descripcion');
            }
            
            // Obtener matrices relacionadas desde la tabla pivote
            $matricesRelacionadas = $componente->matrices->map(function($matriz) {
                return [
                    'codigo' => $matriz->matriz_codigo,
                    'descripcion' => $matriz->matriz_descripcion
                ];
            });
            
            // Para compatibilidad, usar la primera matriz relacionada o null
            $matrizCodigo = $matricesRelacionadas->isNotEmpty() 
                ? $matricesRelacionadas->first()['codigo'] 
                : null;
            $matrizDescripcion = $matricesRelacionadas->isNotEmpty() 
                ? $matricesRelacionadas->first()['descripcion'] 
                : null;
            
            $precioDefinido = $componente->precio !== null && $componente->precio !== '';
            $precioCatalogo = $precioDefinido ? (float) $componente->precio : 0.0;
            $precioMinimoVenta = $precioDefinido ? $precioCatalogo : 5000.00;

            // Si es agrupador, incluir IDs de componentes asociados
            $componentesAsociadosIds = [];
            if ($componente->es_muestra && $componente->componentesAsociados) {
                $componentesAsociadosIds = $componente->componentesAsociados->pluck('id')->values()->all();
            }
            
            return [
                'id' => $componente->id,
                'codigo' => str_pad($componente->id, 15, '0', STR_PAD_LEFT),
                'descripcion' => $componente->cotio_descripcion,
                'es_muestra' => $componente->es_muestra,
                'metodo_analisis_id' => $metodoAnalisisCodigo,  // CotioItems.metodo → cotio_codigometodo_analisis
                'metodo_codigo' => $metodoMuestreoCodigo,         // CotioItems.metodo_muestreo → cotio_codigometodo
                'metodo_descripcion' => $metodoDescripcion,
                'unidad_medida' => $componente->unidad_medida,
                'limites_establecidos' => $componente->limites_establecidos,
                'precio' => $precioCatalogo,
                'precio_minimo_venta' => $precioMinimoVenta,
                'precio_definido' => $precioDefinido,
                'matriz_codigo' => $matrizCodigo,
                'matriz_descripcion' => $matrizDescripcion,
                'matrices' => $matricesRelacionadas->values()->all(), // Todas las matrices relacionadas
                'ley_normativa_id' => $componente->ley_normativa_id ?? null,
                'componentes_asociados' => $componentesAsociadosIds, // IDs de componentes asociados si es agrupador
                'text' => $componente->cotio_descripcion, // Para select2
                'nota_imprimible' => $this->textoCatalogoCotioItem($componente->nota_imprimible ?? null),
                'nota_interna' => $this->textoCatalogoCotioItem($componente->nota_interna ?? null),
            ];
        }));
    }

    /**
     * API para obtener métodos de muestreo
     */
    public function obtenerMetodosMuestreo(Request $request)
    {
        $termino = $request->get('q', '');
        
        $metodos = Metodo::when($termino, function($query, $termino) {
                $query->where('metodo_codigo', 'ILIKE', "%{$termino}%")
                      ->orWhere('metodo_descripcion', 'ILIKE', "%{$termino}%");
            })
            ->orderBy('metodo_codigo')
            ->get();

        return response()->json($metodos->map(function($m) {
            $codigo = trim($m->metodo_codigo);
            return [
                'id' => $codigo,
                'codigo' => $codigo,
                'descripcion' => $m->metodo_descripcion,
                'text' => $codigo . ' - ' . $m->metodo_descripcion,
            ];
        }));
    }

    /**
     * API para obtener métodos de análisis
     */
    public function obtenerMetodosAnalisis(Request $request)
    {
        $termino = $request->get('q', '');
        
        $metodos = Metodo::when($termino, function($query, $termino) {
                $query->where('metodo_codigo', 'ILIKE', "%{$termino}%")
                      ->orWhere('metodo_descripcion', 'ILIKE', "%{$termino}%");
            })
            ->orderBy('metodo_codigo')
            ->get();

        return response()->json($metodos->map(function($m) {
            $codigo = trim($m->metodo_codigo);
            return [
                'id' => $codigo,
                'codigo' => $codigo,
                'descripcion' => $m->metodo_descripcion,
                'text' => $codigo . ' - ' . $m->metodo_descripcion,
            ];
        }));
    }

    /**
     * API para obtener leyes normativas
     */
    public function obtenerLeyesNormativas(Request $request)
    {
        $termino = $request->get('q', '');
        
        $leyes = LeyNormativa::activas()
            ->when($termino, function($query, $termino) {
                return $query->buscar($termino);
            })
            ->orderBy('grupo')
            ->orderBy('codigo')
            ->get();

        return response()->json($leyes->map(function($ley) {
            return [
                'id' => $ley->id,
                'codigo' => $ley->codigo,
                'nombre' => $ley->nombre,
                'grupo' => $ley->grupo,
                'articulo' => $ley->articulo,
                'descripcion' => $ley->descripcion,
                'organismo_emisor' => $ley->organismo_emisor,
                'fecha_vigencia' => $ley->fecha_vigencia,
                'nombre_completo' => $ley->nombre_completo,
                'text' => $ley->codigo . ' - ' . $ley->nombre_completo // Para select2
            ];
        }));
    }

    /**
     * Construir array de cotio_data desde los datos del formulario
     * Esto se usa para guardar la versión histórica con los items que el usuario está editando
     */
    private function construirCotioDataDesdeFormulario(Request $request, $cotiNum)
    {
        $ensayosData = $request->ensayos_data ? json_decode($request->ensayos_data, true) : [];
        $componentesData = $request->componentes_data ? json_decode($request->componentes_data, true) : [];
        
        $cotioItems = [];
        
        // Procesar ensayos (cotio_subitem = 0)
        foreach ($ensayosData as $ensayo) {
            // Buscar el código de producto
            $prodCodigo = $this->buscarCodigoProducto($ensayo['descripcion'] ?? '', true);
            $precioExtraForm = $this->parseDecimalValue($ensayo['precio_extra_ensayo'] ?? null);

            $cotioItem = [
                'cotio_numcoti' => $cotiNum,
                'cotio_item' => $ensayo['item'] ?? 0,
                'cotio_subitem' => 0,
                'cotio_codigoprod' => $prodCodigo ?: null,
                'cotio_cantidad' => $this->parseDecimalValue($ensayo['cantidad'] ?? 1) ?? 1,
                'cotio_precio' => ($precioExtraForm !== null && $precioExtraForm > 0) ? $precioExtraForm : null,
                'cotio_descripcion' => $ensayo['descripcion'] ?? '',
                'cotio_codigoum' => null,
                'cotio_codigometodo' => null,
                'cotio_codigometodo_analisis' => null,
                'cotio_nota_tipo' => $this->normalizarNotaContenidoParaPersistencia($ensayo['nota_contenido'] ?? null) !== null
                    ? ($ensayo['nota_tipo'] ?? 'imprimible')
                    : null,
                'cotio_nota_contenido' => $this->normalizarNotaContenidoParaPersistencia($ensayo['nota_contenido'] ?? null),
                'req_cadena_custodia' => array_key_exists('req_cadena_custodia', $ensayo) ? (bool) $ensayo['req_cadena_custodia'] : null,
                'req_prot_mapba' => array_key_exists('req_prot_mapba', $ensayo) ? (bool) $ensayo['req_prot_mapba'] : null,
                'lleva_muestreo' => array_key_exists('lleva_muestreo', $ensayo) ? (bool) $ensayo['lleva_muestreo'] : true,
                'ley_aplicacion' => !empty($ensayo['ley_normativa_id'] ?? '') ? trim((string) $ensayo['ley_normativa_id']) : null,
                'es_priori' => filter_var($ensayo['es_priori'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
            
            $cotioItems[] = $cotioItem;
        }
        
        // Procesar componentes (cotio_subitem > 0)
        foreach ($componentesData as $componente) {
            $ensayoAsociadoItem = isset($componente['ensayo_asociado'])
                ? (int) $componente['ensayo_asociado']
                : 0;
            $ensayoAsociado = collect($ensayosData)->first(function ($e) use ($ensayoAsociadoItem) {
                return (int) ($e['item'] ?? 0) === $ensayoAsociadoItem;
            });

            if (!$ensayoAsociado) {
                continue;
            }
            
            // Buscar el código de producto
            $prodCodigo = $this->buscarCodigoProducto($componente['descripcion'] ?? '', false);
            
            // Determinar el subitem basado en el orden de los componentes con el mismo ensayo_asociado
            // Contar cuántos componentes anteriores tienen el mismo ensayo_asociado
            $subitem = 1;
            foreach ($componentesData as $comp) {
                if ($comp === $componente) {
                    break;
                }
                if (($comp['ensayo_asociado'] ?? 0) == ($componente['ensayo_asociado'] ?? 0)) {
                    $subitem++;
                }
            }
            
            $cotioItem = [
                'cotio_numcoti' => $cotiNum,
                'cotio_item' => $ensayoAsociado['item'] ?? 0,
                'cotio_subitem' => $subitem,
                'cotio_codigoprod' => !empty($componente['analisis_id']) 
                    ? $this->truncateAndPad($componente['analisis_id'], 15) 
                    : ($prodCodigo ?: null),
                'cotio_cantidad' => $this->parseDecimalValue($componente['cantidad'] ?? 1) ?? 1,
                'cotio_precio' => $this->parseDecimalValue($componente['precio'] ?? null),
                'cotio_descripcion' => $componente['descripcion'] ?? '',
                'cotio_codigoum' => null,
                'cotio_codigometodo' => null,
                'cotio_codigometodo_analisis' => null,
                'limite_deteccion' => $this->parseDecimalValue($componente['limite_deteccion'] ?? null),
                'limite_cuantificacion' => $this->parseDecimalValue($componente['limite_cuantificacion'] ?? null),
                'ley_aplicacion' => !empty($componente['ley_normativa_id'] ?? '') ? trim($componente['ley_normativa_id']) : null,
                'cotio_nota_tipo' => $this->normalizarNotaContenidoParaPersistencia($componente['nota_contenido'] ?? null) !== null
                    ? ($componente['nota_tipo'] ?? 'imprimible')
                    : null,
                'cotio_nota_contenido' => $this->normalizarNotaContenidoParaPersistencia($componente['nota_contenido'] ?? null),
                'req_cadena_custodia' => array_key_exists('req_cadena_custodia', $componente) ? (bool) $componente['req_cadena_custodia'] : null,
                'req_prot_mapba' => array_key_exists('req_prot_mapba', $componente) ? (bool) $componente['req_prot_mapba'] : null,
            ];
            
            // Procesar unidad de medida
            if (!empty($componente['unidad_medida'] ?? '')) {
                $unidadTrim = trim($componente['unidad_medida']);
                if ($unidadTrim !== '') {
                    $cotioItem['cotio_codigoum'] = $this->truncateAndPad($unidadTrim, 10);
                }
            }
            
            // Procesar método (cotio_codigometodo)
            if (!empty($componente['metodo_codigo'] ?? '')) {
                $metodoCodigoTrim = trim($componente['metodo_codigo']);
                if ($metodoCodigoTrim !== '') {
                    $cotioItem['cotio_codigometodo'] = $this->truncateAndPad($metodoCodigoTrim, 15);
                }
            }
            
            // Procesar método de análisis (cotio_codigometodo_analisis) — solo si existe en metodos_analisis (FK)
            if (!empty($componente['metodo_analisis_id'] ?? '')) {
                $metodoAnalisisTrim = trim($componente['metodo_analisis_id']);
                if ($metodoAnalisisTrim !== '') {
                    $cotioItem['cotio_codigometodo_analisis'] = $this->resolverCodigoMetodoAnalisisParaForeignKey($metodoAnalisisTrim);
                }
            }
            
            $cotioItems[] = $cotioItem;
        }
        
        // Ordenar por item y subitem
        usort($cotioItems, function($a, $b) {
            if ($a['cotio_item'] != $b['cotio_item']) {
                return $a['cotio_item'] <=> $b['cotio_item'];
            }
            return $a['cotio_subitem'] <=> $b['cotio_subitem'];
        });
        
        return $cotioItems;
    }

    /**
     * Procesar ensayos y componentes para crear registros en cotio
     */
    private function procesarEnsayosYComponentes(Request $request, $cotiNum, bool $reemplazarExistentes = false)
    {
        Log::info('=== PROCESANDO ENSAYOS Y COMPONENTES ===');
        
        try {
            $ensayosData = $request->ensayos_data ? json_decode($request->ensayos_data, true) : [];
            $componentesData = $request->componentes_data ? json_decode($request->componentes_data, true) : [];
            
            Log::info('ProcesarEnsayosYComponentes - Datos recibidos:', [
                'ensayos_count' => count($ensayosData),
                'componentes_count' => count($componentesData),
                'items_en_ensayos' => collect($ensayosData)->pluck('item')->toArray(),
                'componentes_raw_len' => $request->componentes_data ? strlen($request->componentes_data) : 0,
                'componentes_raw_preview' => $request->componentes_data ? substr($request->componentes_data, 0, 350) : '(vacío)',
            ]);

            if ($reemplazarExistentes) {
                Cotio::where('cotio_numcoti', $cotiNum)->delete();
                Log::info('Registros anteriores de cotio eliminados para la cotización.', ['coti_num' => $cotiNum]);
            }

            // Procesar ensayos (muestras con cotio_subitem = 0)
            foreach ($ensayosData as $ensayo) {
                Log::info('Procesando ensayo:', $ensayo);
                
                // Buscar el código de producto correcto en la tabla prod
                $prodCodigo = $this->buscarCodigoProducto($ensayo['descripcion'], true);
                
                if (!$prodCodigo) {
                    Log::warning('No se encontró código de producto para ensayo:', $ensayo);
                    continue;
                }
                
                // Obtener método de muestreo desde CotioItems
                $metodoMuestreoCodigo = null;
                
                // Primero intentar desde muestra_id si está disponible
                if (!empty($ensayo['muestra_id'])) {
                    $muestraItem = CotioItems::find($ensayo['muestra_id']);
                    if ($muestraItem && $muestraItem->metodo_muestreo) {
                        $metodoMuestreoCodigo = trim($muestraItem->metodo_muestreo);
                        // Verificar que existe en la tabla metodo
                        if (Metodo::where('metodo_codigo', $metodoMuestreoCodigo)->exists()) {
                            $metodoMuestreoCodigo = $this->truncateAndPad($metodoMuestreoCodigo, 15);
                            Log::info('Método de muestreo obtenido desde CotioItems por ID', [
                                'muestra_id' => $ensayo['muestra_id'],
                                'metodo_muestreo' => $metodoMuestreoCodigo
                            ]);
                        } else {
                            Log::warning('Método de muestreo no encontrado en tabla metodo', [
                                'metodo_codigo' => $metodoMuestreoCodigo
                            ]);
                            $metodoMuestreoCodigo = null;
                        }
                    }
                }
                
                // Si no se encontró por ID, buscar por descripción en CotioItems
                if (!$metodoMuestreoCodigo && !empty($ensayo['descripcion'])) {
                    $muestraItem = CotioItems::muestras()
                        ->where('cotio_descripcion', $ensayo['descripcion'])
                        ->first();
                    
                    if ($muestraItem && $muestraItem->metodo_muestreo) {
                        $metodoMuestreoCodigo = trim($muestraItem->metodo_muestreo);
                        // Verificar que existe en la tabla metodo
                        if (Metodo::where('metodo_codigo', $metodoMuestreoCodigo)->exists()) {
                            $metodoMuestreoCodigo = $this->truncateAndPad($metodoMuestreoCodigo, 15);
                            Log::info('Método de muestreo obtenido desde CotioItems por descripción', [
                                'descripcion' => $ensayo['descripcion'],
                                'metodo_muestreo' => $metodoMuestreoCodigo
                            ]);
                        } else {
                            Log::warning('Método de muestreo no encontrado en tabla metodo', [
                                'metodo_codigo' => $metodoMuestreoCodigo
                            ]);
                            $metodoMuestreoCodigo = null;
                        }
                    }
                }
                
                // También intentar desde el campo metodo_muestreo_codigo si viene en el request
                if (!$metodoMuestreoCodigo && !empty($ensayo['metodo_muestreo_codigo'])) {
                    $metodoMuestreoCodigoTrim = trim($ensayo['metodo_muestreo_codigo']);
                    if (Metodo::where('metodo_codigo', $metodoMuestreoCodigoTrim)->exists()) {
                        $metodoMuestreoCodigo = $this->truncateAndPad($metodoMuestreoCodigoTrim, 15);
                        Log::info('Método de muestreo obtenido desde request', [
                            'metodo_muestreo' => $metodoMuestreoCodigo
                        ]);
                    }
                }
                
                // Obtener método (análisis) desde CotioItems para ensayos también
                $metodoAnalisisCodigo = null;
                
                // Primero intentar desde muestra_id si está disponible
                if (!empty($ensayo['muestra_id'])) {
                    $muestraItem = CotioItems::find($ensayo['muestra_id']);
                    if ($muestraItem && $muestraItem->metodo) {
                        $metodoAnalisisCodigo = trim($muestraItem->metodo);
                        // Verificar que existe en la tabla metodo
                        if (Metodo::where('metodo_codigo', $metodoAnalisisCodigo)->exists()) {
                            $metodoAnalisisCodigo = $this->truncateAndPad($metodoAnalisisCodigo, 15);
                            Log::info('Método de análisis obtenido desde CotioItems por ID para ensayo', [
                                'muestra_id' => $ensayo['muestra_id'],
                                'metodo' => $metodoAnalisisCodigo
                            ]);
                        } else {
                            Log::warning('Método de análisis no encontrado en tabla metodo para ensayo', [
                                'metodo_codigo' => $metodoAnalisisCodigo
                            ]);
                            $metodoAnalisisCodigo = null;
                        }
                    }
                }
                
                // Si no se encontró por ID, buscar por descripción en CotioItems
                if (!$metodoAnalisisCodigo && !empty($ensayo['descripcion'])) {
                    $muestraItem = CotioItems::muestras()
                        ->where('cotio_descripcion', $ensayo['descripcion'])
                        ->first();
                    
                    if ($muestraItem && $muestraItem->metodo) {
                        $metodoAnalisisCodigo = trim($muestraItem->metodo);
                        // Verificar que existe en la tabla metodo
                        if (Metodo::where('metodo_codigo', $metodoAnalisisCodigo)->exists()) {
                            $metodoAnalisisCodigo = $this->truncateAndPad($metodoAnalisisCodigo, 15);
                            Log::info('Método de análisis obtenido desde CotioItems por descripción para ensayo', [
                                'descripcion' => $ensayo['descripcion'],
                                'metodo' => $metodoAnalisisCodigo
                            ]);
                        } else {
                            Log::warning('Método de análisis no encontrado en tabla metodo para ensayo', [
                                'metodo_codigo' => $metodoAnalisisCodigo
                            ]);
                            $metodoAnalisisCodigo = null;
                        }
                    }
                }
                
                $cotioEnsayo = new Cotio();
                $cotioEnsayo->cotio_numcoti = $cotiNum;
                $cotioEnsayo->cotio_item = $ensayo['item'];
                $cotioEnsayo->cotio_subitem = 0; // Las muestras siempre tienen subitem 0
                $idPad = !empty($ensayo['muestra_id']) ? $this->truncateAndPad($ensayo['muestra_id'], 15) : null;
                $cotioEnsayo->cotio_codigoprod = ($idPad && DB::table('prod')->where('prod_codigo', $idPad)->exists())
                    ? $idPad
                    : $prodCodigo;
                $cotioEnsayo->cotio_cantidad = $this->parseDecimalValue($ensayo['cantidad'] ?? 1) ?? 1;
                // En BD, cotio_precio del ensayo = solo precio adicional por unidad (no incluye analitos).
                $precioExtra = $this->parseDecimalValue($ensayo['precio_extra_ensayo'] ?? null);
                $cotioEnsayo->cotio_precio = ($precioExtra !== null && $precioExtra > 0) ? $precioExtra : null;
                $cotioEnsayo->cotio_descripcion = $this->truncateCotioDescripcion($ensayo['descripcion'] ?? null);
                $cotioEnsayo->cotio_codigoum = null;
                $cotioEnsayo->cotio_codigometodo = $metodoMuestreoCodigo; // Copiar método de muestreo desde CotioItems.metodo_muestreo
                $cotioEnsayo->cotio_codigometodo_analisis = $metodoAnalisisCodigo
                    ? $this->resolverCodigoMetodoAnalisisParaForeignKey(trim($metodoAnalisisCodigo))
                    : null;
                $cotioEnsayo->enable_muestreo = false;

                // Lleva muestreo (bandera para decidir si pasa por muestreo o va directo a laboratorio)
                if (array_key_exists('lleva_muestreo', $ensayo)) {
                    $cotioEnsayo->lleva_muestreo = (bool) $ensayo['lleva_muestreo'];
                } else {
                    $cotioEnsayo->lleva_muestreo = true;
                }

                // Requerimientos de cadena de custodia y protocolo MAPBA
                if (array_key_exists('req_cadena_custodia', $ensayo)) {
                    $cotioEnsayo->req_cadena_custodia = (bool) $ensayo['req_cadena_custodia'];
                } elseif (array_key_exists('no_requiere_custodia', $ensayo)) {
                    $cotioEnsayo->req_cadena_custodia = !((bool) $ensayo['no_requiere_custodia']);
                }

                if (array_key_exists('req_prot_mapba', $ensayo)) {
                    $cotioEnsayo->req_prot_mapba = (bool) $ensayo['req_prot_mapba'];
                }

                $leyNormativa = !empty($ensayo['ley_normativa_id'] ?? '') ? trim((string) $ensayo['ley_normativa_id']) : null;
                $cotioEnsayo->ley_aplicacion = $leyNormativa ?: null;

                $canalEnsayo = CotizacionCanalEnsayo::resolverDesdeEnsayoPayload([
                    'canal_especial' => $ensayo['canal_especial'] ?? null,
                    'matriz_descripcion' => $ensayo['matriz_descripcion'] ?? null,
                    'descripcion' => $ensayo['descripcion'] ?? null,
                ]);
                $cotioEnsayo->cotio_canal_especial = $canalEnsayo;
                if ($canalEnsayo !== null && $canalEnsayo !== 'mediciones') {
                    $cotioEnsayo->lleva_muestreo = false;
                }
                
                // Guardar datos de nota solo si hay contenido
                $notaContenido = $this->normalizarNotaContenidoParaPersistencia($ensayo['nota_contenido'] ?? null);
                if ($notaContenido !== null) {
                    $cotioEnsayo->cotio_nota_tipo = $ensayo['nota_tipo'] ?? 'imprimible';
                    $cotioEnsayo->cotio_nota_contenido = $notaContenido;
                } else {
                    $cotioEnsayo->cotio_nota_tipo = null;
                    $cotioEnsayo->cotio_nota_contenido = null;
                }

                $cotioEnsayo->es_priori = filter_var($ensayo['es_priori'] ?? false, FILTER_VALIDATE_BOOLEAN);
                
                $cotioEnsayo->save();
                Log::info('Ensayo guardado:', ['cotio_id' => $cotioEnsayo->id, 'prod_codigo' => $prodCodigo]);
            }

            // Procesar componentes (análisis con cotio_subitem > 0)
            $componentesGuardados = 0;
            foreach ($componentesData as $index => $componente) {
                Log::info('Procesando componente', ['index' => $index + 1, 'total' => count($componentesData), 'ensayo_asociado' => $componente['ensayo_asociado'] ?? null, 'descripcion' => $componente['descripcion'] ?? '']);

                // Normalizar ensayo_asociado (puede venir como int o string desde JSON)
                $ensayoAsociadoItem = isset($componente['ensayo_asociado'])
                    ? (int) $componente['ensayo_asociado']
                    : 0;

                // Encontrar el ensayo asociado comparando por entero para evitar fallos de tipo
                $ensayoAsociado = collect($ensayosData)->first(function ($e) use ($ensayoAsociadoItem) {
                    return (int) ($e['item'] ?? 0) === $ensayoAsociadoItem;
                });

                if (!$ensayoAsociado) {
                    Log::warning('COMPONENTE OMITIDO: Ensayo asociado no encontrado', [
                        'ensayo_asociado_recibido' => $componente['ensayo_asociado'] ?? 'NO ENVIADO',
                        'ensayo_asociado_normalizado' => $ensayoAsociadoItem,
                        'items_en_ensayos' => collect($ensayosData)->pluck('item')->toArray(),
                        'componente_descripcion' => $componente['descripcion'] ?? '',
                    ]);
                    continue;
                }

                // Buscar el código de producto correcto en la tabla prod
                $prodCodigo = $this->buscarCodigoProducto($componente['descripcion'], false);
                
                if (!$prodCodigo) {
                    Log::warning('COMPONENTE OMITIDO: No se encontró código de producto', [
                        'descripcion' => $componente['descripcion'] ?? '',
                        'componente' => $componente,
                    ]);
                    continue;
                }

                $ensayoItemInt = (int) ($ensayoAsociado['item'] ?? 0);

                // Contar cuántos componentes ya existen para este ensayo para asignar el subitem
                $componentesExistentes = Cotio::where('cotio_numcoti', $cotiNum)
                    ->where('cotio_item', $ensayoItemInt)
                    ->where('cotio_subitem', '>', 0)
                    ->count();

                // Validar campos mínimos (descripción puede estar vacía; usamos fallback)
                if (empty($cotiNum) || $ensayoItemInt <= 0 || empty($prodCodigo)) {
                    Log::warning('COMPONENTE OMITIDO: Campos requeridos faltantes', [
                        'cotiNum' => $cotiNum,
                        'ensayo_item' => $ensayoItemInt,
                        'prodCodigo' => $prodCodigo,
                    ]);
                    continue;
                }

                $descripcionComponente = trim($componente['descripcion'] ?? '');
                if ($descripcionComponente === '') {
                    $descripcionComponente = trim($componente['codigo'] ?? '') ?: 'Componente';
                }

                $cotioComponente = new Cotio();
                $cotioComponente->cotio_numcoti = $cotiNum;
                $cotioComponente->cotio_item = $ensayoItemInt;
                $cotioComponente->cotio_subitem = $componentesExistentes + 1; // Incrementar subitem
                $idPadComp = !empty($componente['analisis_id']) ? $this->truncateAndPad($componente['analisis_id'], 15) : null;
                $cotioComponente->cotio_codigoprod = ($idPadComp && DB::table('prod')->where('prod_codigo', $idPadComp)->exists())
                    ? $idPadComp
                    : $prodCodigo;
                $cotioComponente->cotio_cantidad = $this->parseDecimalValue($componente['cantidad'] ?? 1) ?? 1;
                $precioIngresado = $this->parseDecimalValue($componente['precio'] ?? null);

                // Precio mínimo de venta: desde catálogo (cotio_items.precio), fallback 5000.
                // Solo algunos usuarios pueden autorizar bajar por debajo del mínimo.
                $usuarioPuedeBajar = (bool) (Auth::user() && (int) (Auth::user()->usu_nivel ?? 0) >= 900);
                $precioMinimo = 5000.0;
                if (!empty($componente['analisis_id'])) {
                    $item = CotioItems::find($componente['analisis_id']);
                    if ($item && $item->precio !== null) {
                        $precioMinimo = (float) $item->precio;
                    }
                } elseif (!empty($componente['descripcion'])) {
                    $item = CotioItems::componentes()->where('cotio_descripcion', $componente['descripcion'])->first();
                    if ($item && $item->precio !== null) {
                        $precioMinimo = (float) $item->precio;
                    }
                }

                if ($precioIngresado === null) {
                    $precioIngresado = $precioMinimo;
                } elseif (!$usuarioPuedeBajar) {
                    $precioIngresado = max($precioMinimo, (float) $precioIngresado);
                }

                $cotioComponente->cotio_precio = $precioIngresado;
                $cotioComponente->cotio_descripcion = $this->truncateCotioDescripcion($descripcionComponente ?: null);

                $deAgrupador = (bool) ($componente['de_agrupador'] ?? false);
                if (!$deAgrupador && $ensayoAsociado) {
                    $precioPack = $this->parseDecimalValue($ensayoAsociado['precio_extra_ensayo'] ?? null);
                    $sugeridos = array_map('strval', $ensayoAsociado['componentes_sugeridos'] ?? []);
                    $analisisIdComp = isset($componente['analisis_id']) ? trim((string) $componente['analisis_id']) : '';
                    if ($precioPack !== null && $precioPack > 0 && $analisisIdComp !== '' && !empty($sugeridos)) {
                        $deAgrupador = in_array($analisisIdComp, $sugeridos, true);
                    }
                }
                $cotioComponente->de_agrupador = $deAgrupador;
                $unidadMedida = $componente['unidad_medida'] ?? null;
                $metodoCodigo = $componente['metodo_codigo'] ?? null;
                $metodoAnalisis = $componente['metodo_analisis_id'] ?? null;
                $metodoMuestreo = $componente['metodo_muestreo_id'] ?? null;
                $limiteDeteccion = $this->parseDecimalValue($componente['limite_deteccion'] ?? null);
                
                // Requerimientos de cadena de custodia y protocolo MAPBA
                if (array_key_exists('req_cadena_custodia', $componente)) {
                    $cotioComponente->req_cadena_custodia = (bool) $componente['req_cadena_custodia'];
                } elseif (array_key_exists('no_requiere_custodia', $componente)) {
                    $cotioComponente->req_cadena_custodia = !((bool) $componente['no_requiere_custodia']);
                }

                if (array_key_exists('req_prot_mapba', $componente)) {
                    $cotioComponente->req_prot_mapba = (bool) $componente['req_prot_mapba'];
                }

                // Obtener método de análisis desde CotioItems (se relaciona con tabla metodo)
                // Primero intentar desde analisis_id si está disponible
                if (!$metodoAnalisis && !empty($componente['analisis_id'])) {
                    $analisisItem = CotioItems::find($componente['analisis_id']);
                    if ($analisisItem && $analisisItem->metodo) {
                        $metodoAnalisisCodigo = trim($analisisItem->metodo);
                        // Verificar que existe en la tabla metodo
                        if (Metodo::where('metodo_codigo', $metodoAnalisisCodigo)->exists()) {
                            $metodoAnalisis = $metodoAnalisisCodigo;
                            Log::info('Método de análisis obtenido desde CotioItems por ID', [
                                'analisis_id' => $componente['analisis_id'],
                                'metodo' => $metodoAnalisis
                            ]);
                        } else {
                            Log::warning('Método de análisis no encontrado en tabla metodo', [
                                'metodo_codigo' => $metodoAnalisisCodigo
                            ]);
                        }
                    }
                }
                
                // Si no se encontró por ID, buscar por descripción en CotioItems
                if (!$metodoAnalisis && !empty($componente['descripcion'])) {
                    $analisisItem = CotioItems::componentes()
                        ->where('cotio_descripcion', $componente['descripcion'])
                        ->first();
                    
                    if ($analisisItem && $analisisItem->metodo) {
                        $metodoAnalisisCodigo = trim($analisisItem->metodo);
                        // Verificar que existe en la tabla metodo
                        if (Metodo::where('metodo_codigo', $metodoAnalisisCodigo)->exists()) {
                            $metodoAnalisis = $metodoAnalisisCodigo;
                            Log::info('Método de análisis obtenido desde CotioItems por descripción', [
                                'descripcion' => $componente['descripcion'],
                                'metodo' => $metodoAnalisis
                            ]);
                        } else {
                            Log::warning('Método de análisis no encontrado en tabla metodo', [
                                'metodo_codigo' => $metodoAnalisisCodigo
                            ]);
                        }
                    }
                }

                // Procesar unidad de medida
                if ($unidadMedida) {
                    $unidadTrim = trim($unidadMedida);
                    if ($unidadTrim !== '') {
                        $unidadCodigo = $this->truncateAndPad($unidadTrim, 10);
                        $unidadExiste = DB::table('um')->where('um_codigo', $unidadCodigo)->exists();

                        if (!$unidadExiste) {
                            try {
                                $columns = DB::getSchemaBuilder()->getColumnListing('um');
                                $payload = [];

                                if (in_array('um_codigo', $columns)) {
                                    $payload['um_codigo'] = $unidadCodigo;
                                }

                                if (in_array('um_descripcion', $columns)) {
                                    $payload['um_descripcion'] = Str::upper($unidadTrim);
                                }

                                if (in_array('um_factor', $columns)) {
                                    $payload['um_factor'] = 1;
                                }

                                if (in_array('um_estado', $columns)) {
                                    $payload['um_estado'] = true;
                                }

                                if (in_array('created_at', $columns)) {
                                    $payload['created_at'] = now();
                                }

                                if (in_array('updated_at', $columns)) {
                                    $payload['updated_at'] = now();
                                }

                                if (!empty($payload)) {
                                    DB::table('um')->insert($payload);
                                    Log::info('Unidad de medida creada automáticamente', [
                                        'unidad' => $unidadTrim,
                                        'unidad_codigo' => $unidadCodigo
                                    ]);
                                } else {
                                    Log::warning('No se pudo crear unidad de medida: sin columnas conocidas', [
                                        'unidad' => $unidadTrim
                                    ]);
                                }
                            } catch (\Exception $e) {
                                Log::error('Error creando unidad de medida automáticamente', [
                                    'unidad' => $unidadTrim,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }

                        if (DB::table('um')->where('um_codigo', $unidadCodigo)->exists()) {
                            $cotioComponente->cotio_codigoum = $unidadCodigo;
                            Log::info('Unidad de medida asignada al componente', [
                                'unidad_codigo' => $unidadCodigo,
                                'componente' => $componente['descripcion']
                            ]);
                        } else {
                            $cotioComponente->cotio_codigoum = null;
                        }
                    }
                }

                // Obtener método de muestreo desde CotioItems para componentes también
                $metodoMuestreoCodigoComp = null;
                
                // Primero intentar desde analisis_id si está disponible
                if (!empty($componente['analisis_id'])) {
                    $analisisItem = CotioItems::find($componente['analisis_id']);
                    if ($analisisItem && $analisisItem->metodo_muestreo) {
                        $metodoMuestreoCodigoComp = trim($analisisItem->metodo_muestreo);
                        // Verificar que existe en la tabla metodo
                        if (Metodo::where('metodo_codigo', $metodoMuestreoCodigoComp)->exists()) {
                            $metodoMuestreoCodigoComp = $this->truncateAndPad($metodoMuestreoCodigoComp, 15);
                            Log::info('Método de muestreo obtenido desde CotioItems por ID para componente', [
                                'analisis_id' => $componente['analisis_id'],
                                'metodo_muestreo' => $metodoMuestreoCodigoComp
                            ]);
                        } else {
                            Log::warning('Método de muestreo no encontrado en tabla metodo para componente', [
                                'metodo_codigo' => $metodoMuestreoCodigoComp
                            ]);
                            $metodoMuestreoCodigoComp = null;
                        }
                    }
                }
                
                // Si no se encontró por ID, buscar por descripción en CotioItems
                if (!$metodoMuestreoCodigoComp && !empty($componente['descripcion'])) {
                    $analisisItem = CotioItems::componentes()
                        ->where('cotio_descripcion', $componente['descripcion'])
                        ->first();
                    
                    if ($analisisItem && $analisisItem->metodo_muestreo) {
                        $metodoMuestreoCodigoComp = trim($analisisItem->metodo_muestreo);
                        // Verificar que existe en la tabla metodo
                        if (Metodo::where('metodo_codigo', $metodoMuestreoCodigoComp)->exists()) {
                            $metodoMuestreoCodigoComp = $this->truncateAndPad($metodoMuestreoCodigoComp, 15);
                            Log::info('Método de muestreo obtenido desde CotioItems por descripción para componente', [
                                'descripcion' => $componente['descripcion'],
                                'metodo_muestreo' => $metodoMuestreoCodigoComp
                            ]);
                        } else {
                            Log::warning('Método de muestreo no encontrado en tabla metodo para componente', [
                                'metodo_codigo' => $metodoMuestreoCodigoComp
                            ]);
                            $metodoMuestreoCodigoComp = null;
                        }
                    }
                }
                
                // Para componentes: copiar ambos métodos desde CotioItems
                // - cotio_codigometodo desde CotioItems.metodo_muestreo
                // - cotio_codigometodo_analisis desde CotioItems.metodo
                $cotioComponente->cotio_codigometodo = $metodoMuestreoCodigoComp;
                
                // Método de análisis: cotio_codigometodo_analisis → FK a metodos_analisis.codigo.
                // Si CotioItems.metodo (u origen) solo existe en `metodo` (legacy) y no en metodos_analisis,
                // se deja null para no violar la FK; las vistas usan descripción vía legado o catalogo.
                if ($metodoAnalisis) {
                    $codigoMetodoAnalisisTrim = trim($metodoAnalisis);
                    $codigoPadded = $this->truncateAndPad($codigoMetodoAnalisisTrim, 15);
                    $resueltoFk = $this->resolverCodigoMetodoAnalisisParaForeignKey($codigoMetodoAnalisisTrim);
                    if ($resueltoFk !== null) {
                        $cotioComponente->cotio_codigometodo_analisis = $resueltoFk;
                        Log::info('Método de análisis asignado al componente (metodos_analisis)', [
                            'codigo' => $resueltoFk,
                            'ingresado' => $codigoMetodoAnalisisTrim,
                            'componente' => $componente['descripcion'] ?? null,
                        ]);
                    } else {
                        $soloLegacy = $codigoPadded
                            && (Metodo::where('metodo_codigo', $codigoPadded)->exists()
                                || Metodo::whereRaw('trim(metodo_codigo) = ?', [$codigoMetodoAnalisisTrim])->exists());
                        if ($soloLegacy) {
                            Log::warning('Método de análisis solo en tabla metodo (legacy); cotio_codigometodo_analisis null (FK a metodos_analisis)', [
                                'codigo' => $codigoMetodoAnalisisTrim,
                                'componente' => $componente['descripcion'] ?? null,
                            ]);
                        } else {
                            Log::warning('Código de método de análisis no hallado en metodos_analisis; cotio_codigometodo_analisis null', [
                                'codigo' => $codigoMetodoAnalisisTrim,
                                'componente' => $componente['descripcion'] ?? null,
                            ]);
                        }
                        $cotioComponente->cotio_codigometodo_analisis = null;
                    }
                } else {
                    $cotioComponente->cotio_codigometodo_analisis = null;
                }

                if (!is_null($limiteDeteccion)) {
                    $cotioComponente->limite_deteccion = $limiteDeteccion;
                }

                $cotioComponente->enable_muestreo = false;
                
                // Guardar datos de nota solo si hay contenido
                $notaContenido = $this->normalizarNotaContenidoParaPersistencia($componente['nota_contenido'] ?? null);
                if ($notaContenido !== null) {
                    $cotioComponente->cotio_nota_tipo = $componente['nota_tipo'] ?? 'imprimible';
                    $cotioComponente->cotio_nota_contenido = $notaContenido;
                } else {
                    $cotioComponente->cotio_nota_tipo = null;
                    $cotioComponente->cotio_nota_contenido = null;
                }
                
                // Intentar guardar el componente con manejo de errores detallado
                try {
                    Log::info('Intentando guardar componente:', [
                        'cotio_numcoti' => $cotioComponente->cotio_numcoti,
                        'cotio_item' => $cotioComponente->cotio_item,
                        'cotio_subitem' => $cotioComponente->cotio_subitem,
                        'cotio_descripcion' => $cotioComponente->cotio_descripcion,
                        'cotio_codigoprod' => $cotioComponente->cotio_codigoprod,
                        'cotio_codigometodo' => $cotioComponente->cotio_codigometodo,
                        'cotio_codigometodo_analisis' => $cotioComponente->cotio_codigometodo_analisis,
                    ]);
                    
                    $cotioComponente->save();
                    $componentesGuardados++;
                    Log::info('COMPONENTE GUARDADO OK', [
                        'cotio_numcoti' => $cotioComponente->cotio_numcoti,
                        'cotio_item' => $cotioComponente->cotio_item,
                        'cotio_subitem' => $cotioComponente->cotio_subitem,
                        'descripcion' => $cotioComponente->cotio_descripcion,
                        'prod_codigo' => $prodCodigo
                    ]);
                } catch (\Exception $saveException) {
                    Log::error('Error al guardar componente:', [
                        'error' => $saveException->getMessage(),
                        'file' => $saveException->getFile(),
                        'line' => $saveException->getLine(),
                        'componente_data' => [
                            'cotio_numcoti' => $cotioComponente->cotio_numcoti,
                            'cotio_item' => $cotioComponente->cotio_item,
                            'cotio_subitem' => $cotioComponente->cotio_subitem,
                            'cotio_descripcion' => $cotioComponente->cotio_descripcion,
                            'cotio_codigoprod' => $cotioComponente->cotio_codigoprod,
                            'cotio_codigometodo' => $cotioComponente->cotio_codigometodo,
                            'cotio_codigometodo_analisis' => $cotioComponente->cotio_codigometodo_analisis,
                        ],
                        'trace' => $saveException->getTraceAsString()
                    ]);
                    // Continuar con el siguiente componente en lugar de detener todo el proceso
                    continue;
                }
            }

            Log::info('=== RESUMEN PROCESAR ENSAYOS Y COMPONENTES ===', [
                'componentes_recibidos' => count($componentesData),
                'componentes_guardados' => $componentesGuardados,
            ]);

            // Si hay ensayos configurados como "no lleva muestreo", materializar instancias en BD
            // y asignar OTN desde el inicio (para que no dependan de un paso de muestreo).
            $this->materializarInstanciasDirectoLab((int) $cotiNum);

            Log::info('Ensayos y componentes procesados exitosamente');

        } catch (\Exception $e) {
            Log::error('Error procesando ensayos y componentes:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            // No lanzar la excepción para no interrumpir la creación de la cotización
        }
    }

    /**
     * Para ensayos con lleva_muestreo=false (o null), crea las instancias (muestra + análisis)
     * y asigna número de OT (otn) a la muestra desde el inicio.
     *
     * Nota: en /ordenes se filtra por instancias de muestra con enable_ot; estas filas se crean con
     * enable_ot true (active_ot sigue false hasta coordinación en laboratorio).
     */
    private function materializarInstanciasDirectoLab(int $cotiNum): void
    {
        try {
            $ensayosDirecto = Cotio::query()
                ->where('cotio_numcoti', $cotiNum)
                ->where('cotio_subitem', 0)
                ->where(function ($q) {
                    $q->where('lleva_muestreo', false)->orWhereNull('lleva_muestreo');
                })
                ->get();

            if ($ensayosDirecto->isEmpty()) {
                return;
            }

            $componentes = Cotio::query()
                ->where('cotio_numcoti', $cotiNum)
                ->where('cotio_subitem', '>', 0)
                ->get()
                ->groupBy('cotio_item');

            $creadasMuestra = 0;
            $creadasAnalisis = 0;
            $otnAsignados = 0;

            foreach ($ensayosDirecto as $ensayo) {
                $item = (int) ($ensayo->cotio_item ?? 0);
                if ($item <= 0) {
                    continue;
                }

                $cantidad = (int) round((float) ($ensayo->cotio_cantidad ?? 1));
                $cantidad = max(1, $cantidad);

                $componentesDelItem = $componentes->get($item, collect());

                for ($instance = 1; $instance <= $cantidad; $instance++) {
                    $muestra = CotioInstancia::firstOrCreate(
                        [
                            'cotio_numcoti' => $cotiNum,
                            'cotio_item' => $item,
                            'cotio_subitem' => 0,
                            'instance_number' => $instance,
                        ],
                        [
                            'cotio_descripcion' => $ensayo->cotio_descripcion,
                            'cotio_codigometodo' => $ensayo->cotio_codigometodo,
                            'cotio_codigometodo_analisis' => $ensayo->cotio_codigometodo_analisis,
                            'cotio_codigoum' => $ensayo->cotio_codigoum,
                            // Sin muestreo: la muestra ya está en circuito laboratorio; enable_ot para /ordenes y filtros.
                            'enable_ot' => true,
                            'active_ot' => false,
                            'enable_muestreo' => false,
                            'enable_inform' => false,
                            'cotio_estado_analisis' => null,
                        ]
                    );

                    $tocoMuestra = false;
                    if (!$muestra->enable_ot) {
                        $muestra->enable_ot = true;
                        $tocoMuestra = true;
                    }
                    if (!$muestra->cotio_descripcion && $ensayo->cotio_descripcion) {
                        $muestra->cotio_descripcion = $ensayo->cotio_descripcion;
                        $tocoMuestra = true;
                    }
                    if (!$muestra->cotio_codigometodo && $ensayo->cotio_codigometodo) {
                        $muestra->cotio_codigometodo = $ensayo->cotio_codigometodo;
                        $tocoMuestra = true;
                    }
                    if (!$muestra->cotio_codigometodo_analisis && $ensayo->cotio_codigometodo_analisis) {
                        $muestra->cotio_codigometodo_analisis = $ensayo->cotio_codigometodo_analisis;
                        $tocoMuestra = true;
                    }
                    if (!$muestra->cotio_codigoum && $ensayo->cotio_codigoum) {
                        $muestra->cotio_codigoum = $ensayo->cotio_codigoum;
                        $tocoMuestra = true;
                    }

                    if (!$muestra->otn) {
                        $canalEspecial = trim((string) ($ensayo->cotio_canal_especial ?? ''));
                        if (!in_array($canalEspecial, ['asp', 'clarke_fire', 'consultoria'])) {
                            $muestra->otn = CotioInstancia::generarNumeroOT();
                            $tocoMuestra = true;
                            $otnAsignados++;
                        }
                    }

                    if ($tocoMuestra) {
                        $muestra->save();
                    }
                    if ($muestra->wasRecentlyCreated) {
                        $creadasMuestra++;
                    }

                    foreach ($componentesDelItem as $comp) {
                        $subitem = (int) ($comp->cotio_subitem ?? 0);
                        if ($subitem <= 0) {
                            continue;
                        }

                        $analisis = CotioInstancia::firstOrCreate(
                            [
                                'cotio_numcoti' => $cotiNum,
                                'cotio_item' => $item,
                                'cotio_subitem' => $subitem,
                                'instance_number' => $instance,
                            ],
                            [
                                'cotio_descripcion' => $comp->cotio_descripcion,
                                'cotio_codigometodo' => $comp->cotio_codigometodo,
                                'cotio_codigometodo_analisis' => $comp->cotio_codigometodo_analisis,
                                'cotio_codigoum' => $comp->cotio_codigoum,
                                'enable_ot' => true,
                                'active_ot' => false,
                                'enable_muestreo' => false,
                                'enable_inform' => false,
                                'cotio_estado_analisis' => null,
                            ]
                        );

                        $tocoAnalisis = false;
                        if (!$analisis->enable_ot) {
                            $analisis->enable_ot = true;
                            $tocoAnalisis = true;
                        }
                        if (!$analisis->cotio_descripcion && $comp->cotio_descripcion) {
                            $analisis->cotio_descripcion = $comp->cotio_descripcion;
                            $tocoAnalisis = true;
                        }
                        if (!$analisis->cotio_codigometodo && $comp->cotio_codigometodo) {
                            $analisis->cotio_codigometodo = $comp->cotio_codigometodo;
                            $tocoAnalisis = true;
                        }
                        if (!$analisis->cotio_codigometodo_analisis && $comp->cotio_codigometodo_analisis) {
                            $analisis->cotio_codigometodo_analisis = $comp->cotio_codigometodo_analisis;
                            $tocoAnalisis = true;
                        }
                        if (!$analisis->cotio_codigoum && $comp->cotio_codigoum) {
                            $analisis->cotio_codigoum = $comp->cotio_codigoum;
                            $tocoAnalisis = true;
                        }
                        if ($tocoAnalisis) {
                            $analisis->save();
                        }
                        if ($analisis->wasRecentlyCreated) {
                            $creadasAnalisis++;
                        }
                    }
                }
            }

            Log::info('Materialización de instancias directo a laboratorio', [
                'cotio_numcoti' => $cotiNum,
                'ensayos_directo' => $ensayosDirecto->count(),
                'muestras_creadas' => $creadasMuestra,
                'analisis_creados' => $creadasAnalisis,
                'otn_asignados' => $otnAsignados,
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudieron materializar instancias directo a laboratorio', [
                'cotio_numcoti' => $cotiNum,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Buscar código de producto en la tabla prod basándose en la descripción
     */
    private function buscarCodigoProducto($descripcion, $esMuestra = true)
    {
        try {
            Log::info('Buscando código de producto:', [
                'descripcion' => $descripcion,
                'es_muestra' => $esMuestra
            ]);
            
            // Buscar en la tabla prod por descripción exacta
            $producto = DB::table('prod')
                ->where('prod_descripcion', $descripcion)
                ->where('prod_estado', true)
                ->first();
            
            if ($producto) {
                Log::info('Producto encontrado por descripción exacta:', [
                    'prod_codigo' => $producto->prod_codigo,
                    'descripcion' => $producto->prod_descripcion
                ]);
                return $producto->prod_codigo;
            }
            
            // Si no se encuentra por descripción exacta, buscar por descripción similar
            $producto = DB::table('prod')
                ->where('prod_descripcion', 'ILIKE', "%{$descripcion}%")
                ->where('prod_estado', true)
                ->first();
            
            if ($producto) {
                Log::info('Producto encontrado por descripción similar:', [
                    'prod_codigo' => $producto->prod_codigo,
                    'descripcion' => $producto->prod_descripcion
                ]);
                return $producto->prod_codigo;
            }
            
            // Si no se encuentra, crear un código genérico basado en el tipo
            $codigoGenerico = $esMuestra ? '000010000000000' : '000010000100006'; // Códigos de ejemplo de la investigación
            
            Log::warning('No se encontró producto, usando código genérico:', [
                'descripcion' => $descripcion,
                'codigo_generico' => $codigoGenerico
            ]);
            
            return $codigoGenerico;
            
        } catch (\Exception $e) {
            Log::error('Error buscando código de producto:', [
                'error' => $e->getMessage(),
                'descripcion' => $descripcion
            ]);
            
            // Retornar código genérico en caso de error
            return $esMuestra ? '000010000000000' : '000010000100006';
        }
    }
    private function normalizarCodigoSector(?string $sector): ?string
    {
        if (is_null($sector)) {
            return null;
        }

        $valor = strtoupper(trim($sector));
        if ($valor === '') {
            return null;
        }

        $map = [
            'LABORATORIO' => 'LAB',
            'HIGIENE Y SEGURIDAD' => 'HYS',
            'MICROBIOLOGIA' => 'MIC',
            'CROMATOGRAFIA' => 'CRO',
            'LAB' => 'LAB',
            'HYS' => 'HYS',
            'MIC' => 'MIC',
            'CRO' => 'CRO',
        ];

        if (isset($map[$valor])) {
            return $map[$valor];
        }

        $primerosTres = substr($valor, 0, 3);
        $mapBasico = [
            'LAB' => 'LAB',
            'HYS' => 'HYS',
            'MIC' => 'MIC',
            'CRO' => 'CRO',
        ];

        return $mapBasico[$primerosTres] ?? null;
    }

    private function obtenerDescuentosSectorCliente(?Clientes $cliente): array
    {
        if (!$cliente) {
            return [
                'LAB' => 0.0,
                'HYS' => 0.0,
                'MIC' => 0.0,
                'CRO' => 0.0,
            ];
        }

        return [
            'LAB' => (float) ($cliente->cli_sector_laboratorio_pct ?? 0.0),
            'HYS' => (float) ($cliente->cli_sector_higiene_pct ?? 0.0),
            'MIC' => (float) ($cliente->cli_sector_microbiologia_pct ?? 0.0),
            'CRO' => (float) ($cliente->cli_sector_cromatografia_pct ?? 0.0),
        ];
    }

    private function obtenerDescuentoSector(?Clientes $cliente, ?string $sector): float
    {
        if (!$cliente) {
            return 0.0;
        }

        $codigoSector = $this->normalizarCodigoSector($sector);
        if (!$codigoSector) {
            return 0.0;
        }

        $descuentos = $this->obtenerDescuentosSectorCliente($cliente);

        return (float) ($descuentos[$codigoSector] ?? 0.0);
    }

    private function calcularDescuentoCliente(?Clientes $cliente, ?string $sector): float
    {
        if (!$cliente) {
            return 0.0;
        }

        $global = (float) ($cliente->cli_descuentoglobal ?? 0.0);
        $sectorExtra = $this->obtenerDescuentoSector($cliente, $sector);

        return $global + $sectorExtra;
    }

    /**
     * Persiste monto total e individual de cuotas según ítems y descuentos del presupuesto.
     */
    private function sincronizarMontosCuotasDesdeItems(Ventas $cotizacion): void
    {
        if (trim((string) ($cotizacion->coti_cond_pago ?? '')) !== 'CUOTAS' && !$cotizacion->coti_cuotas) {
            return;
        }

        $tareas = Cotio::where('cotio_numcoti', $cotizacion->coti_num)->get();
        $sectorCodigo = $this->normalizarCodigoSector($cotizacion->coti_sector);
        $descuentoGlobal = (float) ($cotizacion->coti_descuentoglobal ?? 0);
        $descuentoSector = 0.0;
        if ($sectorCodigo) {
            $descuentoSector = $this->obtenerDescuentoSectorCotizacion($cotizacion, $sectorCodigo);
        }

        $resumen = CotizacionResumenEconomico::calcular(
            $tareas,
            (float) ($cotizacion->coti_aumentoglobal ?? 0),
            $descuentoGlobal,
            $descuentoSector
        );

        $cant = max(1, (int) ($cotizacion->coti_cuota_cant ?? 1));
        $montos = CotizacionResumenEconomico::calcularCuotas(
            $resumen['total_final'],
            $cant,
            (float) ($cotizacion->coti_cuota_interes ?? 0)
        );

        $cotizacion->coti_cuota_monto_total = $montos['monto_total'];
        $cotizacion->coti_cuota_monto_indiv = $montos['monto_individual'];
        $cotizacion->save();
    }

    private function calcularDescuentoCotizacion(?Ventas $cotizacion): float
    {
        if (!$cotizacion) {
            return 0.0;
        }

        $cliente = $cotizacion->cliente;
        $sectorCodigoOriginal = $cotizacion->coti_sector;
        $sectorCodigo = $this->normalizarCodigoSector($sectorCodigoOriginal);

        // Solo usar descuentos configurados en la cotización (global)
        $descuentoGlobal = 0.0;
        if ($cotizacion->coti_descuentoglobal !== null) {
            $descuentoGlobal = (float) $cotizacion->coti_descuentoglobal;
        }

        // Descuento sector: solo usar el de la cotización
        $descuentoSector = 0.0;
        if ($sectorCodigo) {
            $descuentoSector = $this->obtenerDescuentoSectorCotizacion($cotizacion, $sectorCodigo);
        }

        return $descuentoGlobal + $descuentoSector;
    }

    private function obtenerDescuentosSectorCotizacion(?Ventas $cotizacion): array
    {
        if (!$cotizacion) {
            return [
                'LAB' => 0.0,
                'HYS' => 0.0,
                'MIC' => 0.0,
                'CRO' => 0.0,
            ];
        }

        return [
            'LAB' => (float) ($cotizacion->coti_sector_laboratorio_pct ?? 0.0),
            'HYS' => (float) ($cotizacion->coti_sector_higiene_pct ?? 0.0),
            'MIC' => (float) ($cotizacion->coti_sector_microbiologia_pct ?? 0.0),
            'CRO' => (float) ($cotizacion->coti_sector_cromatografia_pct ?? 0.0),
        ];
    }

    private function obtenerDescuentoSectorCotizacion(?Ventas $cotizacion, ?string $sectorCodigo): float
    {
        if (!$cotizacion || !$sectorCodigo) {
            return 0.0;
        }

        $descuentos = $this->obtenerDescuentosSectorCotizacion($cotizacion);
        return (float) ($descuentos[$sectorCodigo] ?? 0.0);
    }

    /**
     * API para obtener todas las versiones de una cotización
     */
    public function obtenerVersiones($cotiNum)
    {
        try {
            $versiones = CotiVersion::where('coti_num', $cotiNum)
                ->orderBy('version', 'desc')
                ->get()
                ->map(function($version) {
                    return [
                        'id' => $version->id,
                        'version' => $version->version,
                        'fecha_version' => $version->fecha_version->format('d/m/Y H:i'),
                        'fecha_version_raw' => $version->fecha_version->format('Y-m-d H:i:s'),
                    ];
                });

            // Agregar la versión actual
            $cotizacion = Ventas::find($cotiNum);
            if ($cotizacion) {
                $versionActual = (int)($cotizacion->coti_version ?? 1);
                $versiones->prepend([
                    'id' => null,
                    'version' => $versionActual,
                    'fecha_version' => 'Actual',
                    'fecha_version_raw' => now()->format('Y-m-d H:i:s'),
                    'es_actual' => true,
                ]);
            }

            return response()->json($versiones->values());
        } catch (\Exception $e) {
            Log::error('Error obteniendo versiones:', [
                'coti_num' => $cotiNum,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Error al obtener versiones'], 500);
        }
    }

    /**
     * API para cargar una versión específica de una cotización
     */
    public function cargarVersion($cotiNum, $version)
    {
        try {
            // Si es la versión actual, cargar desde la tabla principal
            $cotizacion = Ventas::find($cotiNum);
            if (!$cotizacion) {
                return response()->json(['error' => 'Cotización no encontrada'], 404);
            }

            $versionActual = (int)($cotizacion->coti_version ?? 1);
            
            // Normalizar version para comparación (puede venir como string o int)
            $versionSolicitada = (int)$version;
            
            Log::info('Comparando versiones en cargarVersion', [
                'coti_num' => $cotiNum,
                'version_solicitada' => $versionSolicitada,
                'version_actual' => $versionActual,
                'son_iguales' => ($versionSolicitada == $versionActual)
            ]);
            
            if ($versionSolicitada == $versionActual) {
                // Cargar versión actual desde tabla principal
                $cotiData = $cotizacion->getAttributes();
                
                // Usar DB::table para obtener TODOS los campos
                $cotioItemsRaw = DB::table('cotio')
                    ->where('cotio_numcoti', $cotiNum)
                    ->orderBy('cotio_item')
                    ->orderBy('cotio_subitem')
                    ->get();
                
                $cotioItems = $cotioItemsRaw->map(function($item) {
                    return (array) $item;
                })->toArray();
            } else {
                // Cargar versión histórica
                // Usar $versionSolicitada normalizada para la búsqueda
                $versionHistorica = CotiVersion::where('coti_num', $cotiNum)
                    ->where('version', $versionSolicitada)
                    ->first();
                
                if (!$versionHistorica) {
                    Log::warning('Versión histórica no encontrada', [
                        'coti_num' => $cotiNum,
                        'version_solicitada' => $versionSolicitada
                    ]);
                    return response()->json(['error' => 'Versión no encontrada'], 404);
                }
                
                Log::info('Versión histórica encontrada', [
                    'coti_num' => $cotiNum,
                    'version' => $versionSolicitada,
                    'version_id' => $versionHistorica->id,
                    'coti_data_tipo' => gettype($versionHistorica->coti_data),
                    'cotio_data_tipo' => gettype($versionHistorica->cotio_data)
                ]);
                
                // Obtener datos de la versión histórica
                // IMPORTANTE: coti_data y cotio_data pueden venir como string JSON o como array
                // Asegurarse de decodificarlos correctamente
                $cotiDataRaw = $versionHistorica->coti_data;
                $cotioItemsRaw = $versionHistorica->cotio_data;
                
                // Decodificar coti_data si es string
                if (is_string($cotiDataRaw)) {
                    $cotiData = json_decode($cotiDataRaw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::error('Error decodificando coti_data', [
                            'coti_num' => $cotiNum,
                            'version' => $versionSolicitada,
                            'json_error' => json_last_error_msg(),
                            'coti_data_raw_length' => strlen($cotiDataRaw)
                        ]);
                        $cotiData = [];
                    }
                } else {
                    $cotiData = $cotiDataRaw ?? [];
                }
                
                // Decodificar cotio_data si es string
                if (is_string($cotioItemsRaw)) {
                    $cotioItems = json_decode($cotioItemsRaw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::error('Error decodificando cotio_data en cargarVersion', [
                            'coti_num' => $cotiNum,
                            'version' => $versionSolicitada,
                            'json_error' => json_last_error_msg(),
                            'cotio_data_raw_length' => strlen($cotioItemsRaw)
                        ]);
                        $cotioItems = [];
                    }
                } else {
                    $cotioItems = $cotioItemsRaw ?? [];
                }
                
                // Asegurar que cotioItems sea un array
                if (!is_array($cotioItems)) {
                    Log::warning('cotioItems no es un array después de decodificar', [
                        'coti_num' => $cotiNum,
                        'version' => $versionSolicitada,
                        'tipo' => gettype($cotioItems)
                    ]);
                    $cotioItems = [];
                }
                
                Log::info('Datos decodificados de versión histórica', [
                    'coti_num' => $cotiNum,
                    'version' => $versionSolicitada,
                    'cotio_items_count' => count($cotioItems),
                    'cotio_items_sample' => array_slice($cotioItems, 0, 2)
                ]);
                
                // Normalizar el sector si existe (asegurar formato de 4 caracteres)
                // Si es null, mantenerlo como null explícitamente
                if (isset($cotiData['coti_sector']) && $cotiData['coti_sector'] !== null) {
                    $sectorTrimmed = trim($cotiData['coti_sector']);
                    if ($sectorTrimmed !== '') {
                        $cotiData['coti_sector'] = $this->truncateAndPad($sectorTrimmed, 4);
                    } else {
                        $cotiData['coti_sector'] = null;
                    }
                } else {
                    $cotiData['coti_sector'] = null;
                }
            }

            // Asegurar que cotioItems sea siempre un array, incluso si está vacío
            $cotioItemsArray = is_array($cotioItems) ? $cotioItems : [];
            
            // Log para debugging
            Log::info('Cargando versión desde API', [
                'coti_num' => $cotiNum,
                'version' => $version,
                'version_actual' => $versionActual,
                'es_version_actual' => ($version == $versionActual),
                'cotio_items_count' => count($cotioItemsArray),
                'cotio_items_sample' => array_slice($cotioItemsArray, 0, 2),
                'cotio_items_tipo' => gettype($cotioItemsArray)
            ]);
            
            return response()->json([
                'coti_data' => $cotiData,
                'cotio_data' => $cotioItemsArray, // Siempre un array
                'version' => $version,
            ]);
        } catch (\Exception $e) {
            Log::error('Error cargando versión:', [
                'coti_num' => $cotiNum,
                'version' => $version,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Error al cargar versión'], 500);
        }
    }

    /**
     * Buscar cotizaciones para clonar
     */
    public function buscarParaClonar(Request $request)
    {
        try {
            $this->denegarVentasSiUsuarioSoloCanal();

            $query = Ventas::query()
                ->leftJoin('cli', 'coti.coti_codigocli', '=', 'cli.cli_codigo')
                ->select('coti.*', 'cli.cli_razonsocial');

            // Filtro por número de cotización
            if ($request->filled('numero')) {
                $query->where('coti.coti_num', 'LIKE', '%' . $request->numero . '%');
            }

            // Filtro por descripción
            if ($request->filled('descripcion')) {
                $query->where('coti.coti_descripcion', 'LIKE', '%' . $request->descripcion . '%');
            }

            // Filtro por cliente (nombre o código)
            if ($request->filled('cliente')) {
                $query->where(function($q) use ($request) {
                    $q->where('cli.cli_razonsocial', 'LIKE', '%' . $request->cliente . '%')
                      ->orWhere('coti.coti_codigocli', 'LIKE', '%' . $request->cliente . '%');
                });
            }

            // Filtro por estado
            if ($request->filled('estado')) {
                $estadosMap = [
                    'En Espera' => 'E    ',
                    'Aprobado' => 'A    ',
                    'Rechazado' => 'R    ',
                    'En Proceso' => 'P    ',
                    'Suspendida' => 'S    ',
                ];
                $estadoBd = $estadosMap[$request->estado] ?? $request->estado;
                // Buscar exactamente el estado con espacios (la BD guarda con espacios fijos)
                $query->where('coti.coti_estado', $estadoBd);
            }

            // Filtro por fecha desde
            if ($request->filled('fecha_desde')) {
                $query->whereDate('coti.coti_fechaalta', '>=', $request->fecha_desde);
            }

            // Filtro por fecha hasta
            if ($request->filled('fecha_hasta')) {
                $query->whereDate('coti.coti_fechaalta', '<=', $request->fecha_hasta);
            }

            $cotizaciones = $query->orderBy('coti.coti_num', 'desc')
                ->limit(50)
                ->get()
                ->map(function($cotizacion) {
                    // Mapear estado de BD a formulario
                    $estadosMap = [
                        'E    ' => 'En Espera',
                        'A    ' => 'Aprobado',
                        'R    ' => 'Rechazado',
                        'P    ' => 'En Proceso',
                        'S    ' => 'Suspendida',
                    ];
                    // Buscar el estado con espacios (como está en la BD)
                    $estadoBd = $cotizacion->coti_estado;
                    $estadoFormulario = $estadosMap[$estadoBd] ?? trim($estadoBd);

                    return [
                        'coti_num' => $cotizacion->coti_num,
                        'coti_descripcion' => $cotizacion->coti_descripcion,
                        'coti_codigocli' => trim($cotizacion->coti_codigocli),
                        'cliente_nombre' => trim($cotizacion->cli_razonsocial ?? ''),
                        'coti_estado' => $estadoFormulario,
                        'coti_fechaalta' => $cotizacion->coti_fechaalta ? $cotizacion->coti_fechaalta->format('Y-m-d') : null,
                    ];
                });

            return response()->json(['cotizaciones' => $cotizaciones]);
        } catch (\Exception $e) {
            Log::error('Error buscando cotizaciones para clonar:', [
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Error al buscar cotizaciones'], 500);
        }
    }

    /**
     * Obtener datos completos de una cotización para clonar
     */
    public function obtenerParaClonar($cotiNum)
    {
        try {
            $this->denegarVentasSiUsuarioSoloCanal();

            $cotizacion = Ventas::where('coti_num', $cotiNum)->first();

            if (!$cotizacion) {
                return response()->json(['error' => 'Cotización no encontrada'], 404);
            }

            // Obtener ensayos (cotio_subitem = 0)
            $ensayos = Cotio::where('cotio_numcoti', $cotiNum)
                ->where('cotio_subitem', 0)
                ->orderBy('cotio_item')
                ->get();

            // Obtener componentes (cotio_subitem > 0)
            $componentes = Cotio::where('cotio_numcoti', $cotiNum)
                ->where('cotio_subitem', '>', 0)
                ->orderBy('cotio_item')
                ->orderBy('cotio_subitem')
                ->get();

            // Mapear estado de BD a formulario
            $estadoRaw = (string) ($cotizacion->coti_estado ?? '');
            $estadoTrim = trim($estadoRaw);
            $letter = $estadoTrim !== '' ? strtoupper($estadoTrim[0]) : 'E';
            $porLetra = [
                'E' => 'En Espera',
                'A' => 'Aprobado',
                'R' => 'Rechazado',
                'P' => 'En Proceso',
                'S' => 'Suspendida',
            ];
            $estadoFormulario = $porLetra[$letter] ?? $estadoTrim;

            // Preparar datos de la cotización
            $fechaHoy = Carbon::now()->format('Y-m-d');
            $datosCotizacion = [
                'coti_codigocli' => trim($cotizacion->coti_codigocli),
                'coti_descripcion' => $cotizacion->coti_descripcion,
                'coti_fechaalta' => $fechaHoy,
                'coti_fechafin' => $cotizacion->coti_fechafin ? $cotizacion->coti_fechafin->format('Y-m-d') : null,
                'coti_estado' => $estadoFormulario,
                'coti_codigosuc' => trim($cotizacion->coti_codigosuc ?? ''),
                'coti_para' => $cotizacion->coti_para,
                'coti_cli_empresa' => $cotizacion->coti_cli_empresa,
                'coti_empresa_rel' => $cotizacion->coti_empresa_rel,
                'coti_para_empresa_rel' => (bool) ($cotizacion->coti_para_empresa_rel ?? false),
                'coti_contacto' => $cotizacion->coti_contacto,
                'coti_mail1' => $cotizacion->coti_mail1,
                'coti_telefono' => $cotizacion->coti_telefono,
                'coti_contacto2' => $cotizacion->coti_contacto2,
                'coti_mail2' => $cotizacion->coti_mail2,
                'coti_telefono2' => $cotizacion->coti_telefono2,
                'coti_contacto3' => $cotizacion->coti_contacto3,
                'coti_mail3' => $cotizacion->coti_mail3,
                'coti_telefono3' => $cotizacion->coti_telefono3,
                'coti_contacto4' => $cotizacion->coti_contacto4,
                'coti_mail4' => $cotizacion->coti_mail4,
                'coti_telefono4' => $cotizacion->coti_telefono4,
                'coti_contacto_tipo1' => $cotizacion->coti_contacto_tipo1,
                'coti_contacto_tipo2' => $cotizacion->coti_contacto_tipo2,
                'coti_contacto_tipo3' => $cotizacion->coti_contacto_tipo3,
                'coti_contacto_tipo4' => $cotizacion->coti_contacto_tipo4,
                'coti_sector' => trim($cotizacion->coti_sector ?? ''),
                'coti_notas' => $cotizacion->coti_notas,
                'descuento' => $cotizacion->coti_descuentoglobal ?? 0.00,
                'divisa_codigo' => $cotizacion->divisa_codigo ?? 'PES',
                'coti_cond_pago' => $cotizacion->coti_cond_pago ? trim($cotizacion->coti_cond_pago) : null,
                'coti_cuotas' => $cotizacion->coti_cuotas ?? false,
                'coti_cuota_desc' => $cotizacion->coti_cuota_desc,
                'coti_cuota_cant' => $cotizacion->coti_cuota_cant,
                'coti_cuota_monto_total' => $cotizacion->coti_cuota_monto_total,
                'coti_cuota_monto_indiv' => $cotizacion->coti_cuota_monto_indiv,
                'coti_cuota_interes' => $cotizacion->coti_cuota_interes !== null
                    ? (float) $cotizacion->coti_cuota_interes
                    : 0.0,
                'coti_cuota_fact_fin_mes' => $cotizacion->coti_cuota_fact_fin_mes ?? false,
                'coti_cuota_fact_inicio_mes' => $cotizacion->coti_cuota_fact_inicio_mes ?? false,
                'coti_cadena_custodia' => $cotizacion->coti_cadena_custodia ?? false,
                'coti_muestreo' => $cotizacion->coti_muestreo ?? false,
                'coti_req_cadena_custodia_relacionada' => (bool) ($cotizacion->coti_req_cadena_custodia_relacionada ?? false),
                'coti_prioridad_global' => (bool) ($cotizacion->coti_prioridad_global ?? false),
                'coti_responsable' => $cotizacion->coti_responsable,
                'coti_fechaaprobado' => $letter === 'A' ? $fechaHoy : null,
                'coti_aprobo' => $cotizacion->coti_aprobo,
                'coti_fechaencurso' => $cotizacion->coti_fechaencurso ? $cotizacion->coti_fechaencurso->format('Y-m-d') : null,
                'coti_fechaaltatecnica' => $cotizacion->coti_fechaaltatecnica ? $cotizacion->coti_fechaaltatecnica->format('Y-m-d') : null,
                'coti_empresa' => $cotizacion->coti_empresa,
                'coti_establecimiento' => $cotizacion->coti_establecimiento,
                'coti_direccioncli' => $cotizacion->coti_direccioncli,
                'coti_localidad' => $cotizacion->coti_localidad,
                'coti_partido' => $cotizacion->coti_partido,
                'coti_cuit' => $cotizacion->coti_cuit,
                'coti_codigopostal' => $cotizacion->coti_codigopostal,
                'coti_oc_referencia' => $cotizacion->coti_oc_referencia,
                'coti_oc_requerido_factura' => (bool) ($cotizacion->coti_oc_requerido_factura ?? false),
                'coti_refs_facturacion_json' => $cotizacion->coti_refs_facturacion_json,
            ];

            $agrupadoresCatalogo = CotioItems::muestras()
                ->with(['componentesAsociados', 'matrices'])
                ->get()
                ->keyBy(function ($item) {
                    return Str::lower(trim($item->cotio_descripcion));
                });

            $payloadItems = $this->mapearEnsayosYComponentesParaFrontend($ensayos, $componentes, $agrupadoresCatalogo, $cotizacion);
            $ensayosData = $payloadItems['ensayos'];
            $componentesData = $payloadItems['componentes'];

            return response()->json([
                'cotizacion' => $datosCotizacion,
                'ensayos' => $ensayosData,
                'componentes' => $componentesData,
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo cotización para clonar:', [
                'coti_num' => $cotiNum,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Error al obtener cotización'], 500);
        }
    }

    /**
     * Mapea filas cotio a la estructura usada por el frontend (create/edit/clonar).
     *
     * @return array{ensayos: array<int, array<string, mixed>>, componentes: array<int, array<string, mixed>>}
     */
    private function mapearEnsayosYComponentesParaFrontend($ensayos, $componentes, $agrupadoresCatalogo, $cotizacion): array
    {
        $ensayosData = $ensayos->map(function ($ensayo) use ($componentes, $agrupadoresCatalogo, $cotizacion) {
            $cantidad = $ensayo->cotio_cantidad ?? 1;
            $componentesDelEnsayo = $componentes->where('cotio_item', $ensayo->cotio_item);

            $descripcionClave = Str::lower(trim($ensayo->cotio_descripcion ?? ''));
            $agrupador = $agrupadoresCatalogo->get($descripcionClave);
            $idsParametrosPack = $agrupador
                ? $agrupador->componentesAsociados->pluck('id')->map(fn ($id) => (string) $id)->values()->all()
                : [];

            $cotioPrecioEnsayo = $this->parseDecimalValue($ensayo->cotio_precio ?? null);
            $precioPackEnsayo = ($cotioPrecioEnsayo !== null && $cotioPrecioEnsayo > 0) ? (float) $cotioPrecioEnsayo : 0.0;

            $precioUnitario = $componentesDelEnsayo->sum(function ($comp) use ($idsParametrosPack, $precioPackEnsayo) {
                if ($comp->de_agrupador) {
                    return 0;
                }
                if ($precioPackEnsayo > 0 && ! empty($idsParametrosPack)) {
                    $analisisId = trim((string) ($comp->cotio_codigoprod ?? ''));
                    if ($analisisId !== '' && in_array($analisisId, $idsParametrosPack, true)) {
                        return 0;
                    }
                }
                $precio = $comp->cotio_precio ?? 0;
                $cantidadComp = $comp->cotio_cantidad ?? 1;

                return $precio * $cantidadComp;
            });

            $esNuevaLogica = $componentesDelEnsayo->contains(fn ($c) => (bool) ($c->de_agrupador ?? false))
                || ($precioPackEnsayo > 0 && ! empty($idsParametrosPack));
            $precioExtraEnsayo = $this->resolverPrecioExtraEnsayoDesdeCotioRow($cotioPrecioEnsayo, (float) $precioUnitario, $esNuevaLogica);
            $precioUnitarioTotal = (float) $precioUnitario + $precioExtraEnsayo;

            $matrizCodigo = null;
            $matrizDescripcion = null;
            if ($agrupador) {
                if ($agrupador->matrices->isNotEmpty()) {
                    $matriz = $agrupador->matrices->first();
                    $matrizCodigo = $matriz->matriz_codigo;
                    $matrizDescripcion = $matriz->matriz_descripcion;
                } elseif ($agrupador->matriz_codigo) {
                    $matrizCodigo = trim($agrupador->matriz_codigo);
                    $matriz = Matriz::where('matriz_codigo', $matrizCodigo)->first();
                    $matrizDescripcion = $matriz ? trim($matriz->matriz_descripcion) : null;
                }
            }

            return [
                'item' => (int) $ensayo->cotio_item,
                'muestra_id' => $agrupador?->id,
                'descripcion' => $ensayo->cotio_descripcion,
                'codigo' => $agrupador ? str_pad($agrupador->id, 15, '0', STR_PAD_LEFT) : ($ensayo->cotio_codigoprod ?? ''),
                'cantidad' => (float) $cantidad,
                'precio_extra_ensayo' => (float) $precioExtraEnsayo,
                'precio' => (float) $precioUnitarioTotal,
                'total' => (float) ($precioUnitarioTotal * $cantidad),
                'tipo' => 'ensayo',
                'componentes_sugeridos' => $agrupador ? $agrupador->componentesAsociados->pluck('id')->values()->all() : [],
                'nota_tipo' => $ensayo->cotio_nota_tipo ?? null,
                'nota_contenido' => $ensayo->cotio_nota_contenido ?? null,
                'matriz_codigo' => $matrizCodigo,
                'matriz_descripcion' => $matrizDescripcion,
                'canal_especial' => $this->resolverCanalCotioEnsayo($ensayo, $agrupadoresCatalogo),
                'lleva_muestreo' => $ensayo->lleva_muestreo ?? true,
                'req_cadena_custodia' => (bool) ($ensayo->req_cadena_custodia ?? false),
                'req_prot_mapba' => (bool) ($ensayo->req_prot_mapba ?? false),
                'ley_normativa_id' => ($ensayo->ley_aplicacion !== null && trim((string) $ensayo->ley_aplicacion) !== '')
                    ? trim((string) $ensayo->ley_aplicacion)
                    : null,
                'es_priori' => (bool) (($cotizacion->coti_prioridad_global ?? false) || ($ensayo->es_priori ?? false)),
            ];
        })->values()->all();

        $componentesData = [];
        $contadorComponentes = 0;
        $maxItemEnsayo = (int) ($ensayos->max('cotio_item') ?? 0);
        foreach ($componentes as $componente) {
            $contadorComponentes++;
            $metodoTexto = '-';

            if ($componente->cotio_codigometodo) {
                $metodoCodigo = trim($componente->cotio_codigometodo);
                $metodo = Metodo::where('metodo_codigo', $metodoCodigo)->first();
                $metodoTexto = $metodo
                    ? $metodo->metodo_codigo . ' - ' . ($metodo->metodo_descripcion ?? '')
                    : $metodoCodigo;
            } elseif ($componente->cotio_codigometodo_analisis) {
                $metodoCodigo = trim($componente->cotio_codigometodo_analisis);
                $metodoAnalisis = MetodoAnalisis::where('codigo', $metodoCodigo)->first();
                $metodoTexto = $metodoAnalisis
                    ? $metodoAnalisis->codigo . ' - ' . ($metodoAnalisis->nombre ?? $metodoAnalisis->descripcion ?? '')
                    : $metodoCodigo;
            }

            $codigoProducto = trim($componente->cotio_codigoprod ?? '');
            $descripcionComponente = trim($componente->cotio_descripcion ?? '');
            $componenteCatalogo = $this->buscarCotioItemComponenteCatalogo($codigoProducto, $descripcionComponente);
            $precioReferencia = $this->resolverPrecioReferenciaComponenteParaEdicion(
                $componenteCatalogo,
                $componente->cotio_precio ?? null
            );
            $analisisId = $precioReferencia['analisis_id'];
            $precioComponente = (float) $precioReferencia['precio'];
            $precioMinimoVenta = (float) $precioReferencia['precio_minimo_venta'];
            $cantidadComponente = (float) ($componente->cotio_cantidad ?? 1);
            if ($cantidadComponente <= 0) {
                $cantidadComponente = 1;
            }

            $ensayoPadre = $ensayos->firstWhere('cotio_item', $componente->cotio_item);
            $descripcionEnsayoClave = Str::lower(trim($ensayoPadre->cotio_descripcion ?? ''));
            $agrupadorPadre = $agrupadoresCatalogo->get($descripcionEnsayoClave);
            $idsParametrosPack = $agrupadorPadre
                ? $agrupadorPadre->componentesAsociados->pluck('id')->map(fn ($id) => (string) $id)->values()->all()
                : [];
            $precioPackPadre = 0.0;
            if ($ensayoPadre) {
                $cotioPrecioPadre = $this->parseDecimalValue($ensayoPadre->cotio_precio ?? null);
                $precioPackPadre = ($cotioPrecioPadre !== null && $cotioPrecioPadre > 0) ? (float) $cotioPrecioPadre : 0.0;
            }

            $deAgrupador = (bool) ($componente->de_agrupador ?? false);
            if (! $deAgrupador && $precioPackPadre > 0 && $analisisId && ! empty($idsParametrosPack)) {
                $deAgrupador = in_array((string) $analisisId, $idsParametrosPack, true);
            }

            $componentesData[] = [
                'item' => $maxItemEnsayo + $contadorComponentes,
                'analisis_id' => $analisisId,
                'descripcion' => $componente->cotio_descripcion,
                'codigo' => $componente->cotio_codigoprod ?? '',
                'cantidad' => $cantidadComponente,
                'precio' => $precioComponente,
                'precio_minimo_venta' => $precioMinimoVenta,
                'total' => $precioComponente * $cantidadComponente,
                'tipo' => 'componente',
                'ensayo_asociado' => (int) $componente->cotio_item,
                'metodo_analisis_id' => $componente->cotio_codigometodo_analisis ? trim($componente->cotio_codigometodo_analisis) : null,
                'metodo_codigo' => $componente->cotio_codigometodo ? trim($componente->cotio_codigometodo) : null,
                'metodo_descripcion' => $metodoTexto,
                'unidad_medida' => $componente->cotio_codigoum ? trim($componente->cotio_codigoum) : null,
                'limite_deteccion' => $componente->limite_deteccion ?? null,
                'ley_normativa_id' => ($componente->ley_aplicacion !== null && trim((string) $componente->ley_aplicacion) !== '')
                    ? trim((string) $componente->ley_aplicacion)
                    : null,
                'nota_tipo' => $componente->cotio_nota_tipo ?? null,
                'nota_contenido' => $componente->cotio_nota_contenido ?? null,
                'req_cadena_custodia' => (bool) ($componente->req_cadena_custodia ?? false),
                'req_prot_mapba' => (bool) ($componente->req_prot_mapba ?? false),
                'de_agrupador' => $deAgrupador,
            ];
        }

        return [
            'ensayos' => $ensayosData,
            'componentes' => $componentesData,
        ];
    }

    public function descargarAdjuntoEnsayo(CotioAdjunto $adjunto): StreamedResponse
    {
        $this->denegarVentasSiUsuarioSoloCanal();

        if ($adjunto->path === null || ! Storage::disk('public')->exists($adjunto->path)) {
            abort(404, 'Archivo no encontrado.');
        }

        return Storage::disk('public')->download($adjunto->path, $adjunto->original_name);
    }

    private function procesarAdjuntosEnsayos(Request $request, $cotiNum): void
    {
        $ensayosData = $request->ensayos_data ? json_decode($request->ensayos_data, true) : [];
        $itemsActivos = collect($ensayosData)->pluck('item')->map(fn ($item) => (int) $item)->all();

        $queryHuerfanos = CotioAdjunto::where('cotio_numcoti', $cotiNum);
        if ($itemsActivos !== []) {
            $queryHuerfanos->whereNotIn('cotio_item', $itemsActivos);
        }
        $queryHuerfanos->get()->each(fn (CotioAdjunto $adjunto) => $this->eliminarAdjuntoEnsayoArchivo($adjunto));

        $eliminarIds = json_decode($request->input('ensayos_adjuntos_eliminar', '[]'), true);
        if (is_array($eliminarIds) && $eliminarIds !== []) {
            CotioAdjunto::where('cotio_numcoti', $cotiNum)
                ->whereIn('id', $eliminarIds)
                ->get()
                ->each(fn (CotioAdjunto $adjunto) => $this->eliminarAdjuntoEnsayoArchivo($adjunto));
        }

        $adjuntosRequest = $request->file('ensayo_adjuntos', []);
        if (! is_array($adjuntosRequest) || $adjuntosRequest === []) {
            return;
        }

        $usuario = Auth::user();
        $uploadedBy = $usuario ? trim((string) $usuario->usu_codigo) : null;

        foreach ($adjuntosRequest as $item => $files) {
            $item = (int) $item;
            if (! in_array($item, $itemsActivos, true)) {
                continue;
            }

            if (! is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $file) {
                if (! $file || ! AdjuntosArchivoValidacion::esValido($file)) {
                    continue;
                }

                $filename = AdjuntosArchivoValidacion::nombreSeguro($file);
                $path = $file->storeAs("cotizaciones/{$cotiNum}/ensayos/{$item}", $filename, 'public');

                CotioAdjunto::create([
                    'cotio_numcoti' => $cotiNum,
                    'cotio_item' => $item,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => $uploadedBy,
                ]);
            }
        }
    }

    private function eliminarAdjuntoEnsayoArchivo(CotioAdjunto $adjunto): void
    {
        if ($adjunto->path && Storage::disk('public')->exists($adjunto->path)) {
            Storage::disk('public')->delete($adjunto->path);
        }

        $adjunto->delete();
    }

}