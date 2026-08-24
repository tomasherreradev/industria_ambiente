<?php

namespace App\Console\Commands;

use App\Http\Controllers\VentasController;
use App\Models\Clientes;
use App\Models\User;
use App\Models\Ventas;
use App\Support\CotizacionClienteEtiqueta;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class DiagnosticoVentasIndex extends Command
{
    protected $signature = 'ventas:diagnostico
                            {--usu= : Código de usuario para simular login (ej. amendoza)}
                            {--render : Intentar renderizar la vista completa}';

    protected $description = 'Diagnóstico paso a paso de GET /ventas (memoria, SQL, controlador, vista)';

    public function handle(): int
    {
        $this->line('=== Diagnóstico /ventas ===');
        $this->line('PHP ' . PHP_VERSION . ' | memory_limit=' . ini_get('memory_limit')
            . ' | peak=' . $this->fmtMem(memory_get_peak_usage(true)));

        $controllerPath = app_path('Http/Controllers/VentasController.php');
        if (is_file($controllerPath)) {
            $src = file_get_contents($controllerPath) ?: '';
            $montosLigeros = str_contains($src, '->cursor() as $coti)')
                && str_contains($src, 'coti_cuota_monto_total');
            $derivadosLazy = str_contains($src, '$incluirEstadosDerivados');
            $instanciasPorClave = str_contains($src, 'fetchInstanciasVentasPorClaves');
            $this->line('VentasController: montos_ligeros=' . ($montosLigeros ? 'SI' : 'NO')
                . ' derivados_lazy=' . ($derivadosLazy ? 'SI' : 'NO')
                . ' instancias_por_clave=' . ($instanciasPorClave ? 'SI' : 'NO'));
            if (! $montosLigeros || ! $derivadosLazy || ! $instanciasPorClave) {
                $this->warn('Falta el último fix de memoria. Subí VentasController.php actualizado.');
            }
        }

        $this->paso('Conexión DB', function () {
            DB::connection()->getPdo();
            $this->line('  driver=' . DB::connection()->getDriverName());
        });

        $this->paso('Clientes::soloPrincipales()', function () {
            $this->line('  count=' . Clientes::where('cli_estado', true)->soloPrincipales()->count());
        });

        $this->paso('Ventas::count()', function () {
            $this->line('  count=' . Ventas::count());
        });

        $this->paso('Conteo instancias (cotio_instancias)', function () {
            $this->line('  total=' . DB::table('cotio_instancias')->count());
        });

        $this->paso('Conteo líneas cotio', function () {
            $this->line('  total=' . DB::table('cotio')->count());
        });

        $this->paso('cotio_cantidad sospechosas (>200)', function () {
            $filas = DB::table('cotio')
                ->where('cotio_subitem', 0)
                ->select(['cotio_numcoti', 'cotio_item', 'cotio_cantidad'])
                ->get()
                ->filter(function ($r) {
                    $n = (int) round((float) ($r->cotio_cantidad ?? 1));

                    return $n > 200;
                })
                ->take(10);
            $this->line('  filas_sospechosas=' . $filas->count());
            foreach ($filas as $r) {
                $this->line("  coti={$r->cotio_numcoti} item={$r->cotio_item} cantidad={$r->cotio_cantidad}");
            }
        });

        $usuario = $this->resolverUsuario();
        if (! $usuario) {
            $this->error('No se encontró usuario. Usá --usu=codigo');

            return self::FAILURE;
        }

        Auth::login($usuario);
        $this->line('Usuario: ' . trim((string) $usuario->usu_codigo));

        if ($this->option('render')) {
            $this->paso('VentasController::index() + render', function () {
                $request = Request::create('/ventas', 'GET');
                $controller = app(VentasController::class);
                $view = $controller->index($request);
                $html = $view->render();
                $this->line('  html_bytes=' . strlen($html)
                    . ' peak=' . $this->fmtMem(memory_get_peak_usage(true)));
            });
        } else {
            $this->paso('VentasController::index()', function () {
                $request = Request::create('/ventas', 'GET');
                $controller = app(VentasController::class);
                $response = $controller->index($request);
                $this->line('  response=' . get_class($response)
                    . ' peak=' . $this->fmtMem(memory_get_peak_usage(true)));
            });
        }

        $this->paso('CotizacionClienteEtiqueta::paraLista', function () {
            $c = Ventas::query()->with(['cliente', 'empresaRelacionada', 'sucursal'])->orderByDesc('coti_num')->first();
            if (! $c) {
                return;
            }
            CotizacionClienteEtiqueta::precargarEmpresasRelacionadas(collect([$c]));
            $this->line('  etiqueta=' . mb_substr(CotizacionClienteEtiqueta::paraLista($c), 0, 50));
        });

        $this->info('Listo. tail -80 storage/logs/laravel.log');

        return self::SUCCESS;
    }

    private function paso(string $titulo, callable $fn): void
    {
        $this->newLine();
        $this->comment("→ {$titulo}");
        $memAntes = memory_get_usage(true);
        try {
            $fn();
            $this->info('  OK | delta=' . $this->fmtMem(memory_get_usage(true) - $memAntes)
                . ' peak=' . $this->fmtMem(memory_get_peak_usage(true)));
        } catch (Throwable $e) {
            $this->error('  FALLO: ' . $e->getMessage());
            $this->line('  ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    private function resolverUsuario(): ?User
    {
        $cod = trim((string) $this->option('usu'));
        if ($cod !== '') {
            return User::whereRaw('LTRIM(RTRIM(usu_codigo)) = ?', [$cod])->first();
        }

        return User::query()->where('usu_estado', true)->orderByDesc('usu_nivel')->first();
    }

    private function fmtMem(int $bytes): string
    {
        return round($bytes / 1024 / 1024, 1) . 'MB';
    }
}
