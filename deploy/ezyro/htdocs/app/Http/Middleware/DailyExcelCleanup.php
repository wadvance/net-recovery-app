<?php

namespace App\Http\Middleware;

use App\Console\Commands\CleanupDailyExcelImports;
use App\Models\ExcelImport;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Disparador perezoso de la limpieza diaria de Excel.
 *
 * El hosting gratuito no ejecuta `schedule:run` por cron, así que la
 * limpieza se garantiza evaluando en cada petición API:
 *
 *  1. Son las 8:00 pm o más (hora Guatemala) y hoy aún no se ha limpiado
 *     → limpieza total y se marca el día como ya limpiado.
 *  2. Red de seguridad: si hay importaciones de días anteriores
 *     (p. ej. el servidor no recibió tráfico después de las 8 pm),
 *     se eliminan para que el agente empiece el día con la lista vacía.
 */
class DailyExcelCleanup
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $tz = CleanupDailyExcelImports::BUSINESS_TZ;
            $now = now($tz);
            $cacheKey = 'excel_cleanup_last_run_date';

            if ($now->hour >= 20 && Cache::get($cacheKey) !== $now->toDateString()) {
                Artisan::call('excel:daily-cleanup');
                Cache::put($cacheKey, $now->toDateString(), Carbon::tomorrow($tz)->endOfDay());
                return $next($request);
            }

            // Red de seguridad: restos de días anteriores fuera de horario.
            if (ExcelImport::where('created_at', '<', Carbon::today($tz)->startOfDay())->exists()) {
                Artisan::call('excel:daily-cleanup', ['--before-today' => true]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $next($request);
    }
}
