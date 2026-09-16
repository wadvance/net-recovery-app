<?php

namespace App\Console\Commands;

use App\Models\ExcelImport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupDailyExcelImports extends Command
{
    /**
     * Zona horaria del negocio (Guatemala). La limpieza de las 8:00 pm se
     * calcula aquí, independiente del timezone del servidor (UTC en freehosting).
     */
    public const BUSINESS_TZ = 'America/Panama';

    protected $signature = 'excel:daily-cleanup {--before-today : Solo eliminar importaciones creadas antes de hoy}';

    protected $description = 'Limpia los archivos de Excel subidos (registros y archivos físicos) para dejar la lista lista para el día siguiente';

    public function handle(): int
    {
        $query = ExcelImport::query();

        if ($this->option('before-today')) {
            $startOfToday = Carbon::today(self::BUSINESS_TZ)->startOfDay();
            $query->where('created_at', '<', $startOfToday);
        }

        $imports = $query->get();
        $filesDeleted = 0;

        foreach ($imports as $import) {
            $stored = $import->stored_filename;
            if ($stored) {
                // stored_filename se guarda como "imports/<archivo>".
                $relative = str_starts_with((string) $stored, 'imports/')
                    ? substr((string) $stored, strlen('imports/'))
                    : (string) $stored;

                if (Storage::disk('local')->exists('imports/' . $relative)) {
                    Storage::disk('local')->delete('imports/' . $relative);
                    $filesDeleted++;
                }
            }
            $import->delete();
        }

        // Archivos huérfanos: quedaron en disco sin registro asociado
        // (p. ej. subidas interrumpidas). Se eliminan si tienen más de 24h.
        $orphans = 0;
        foreach (Storage::disk('local')->files('imports') as $file) {
            $modified = Storage::disk('local')->lastModified($file);
            if ($modified < Carbon::yesterday(self::BUSINESS_TZ)->getTimestamp()) {
                Storage::disk('local')->delete($file);
                $orphans++;
            }
        }

        $this->info("Importaciones eliminadas: {$imports->count()}");
        $this->info("Archivos borrados: {$filesDeleted}");
        $this->info("Archivos huérfanos borrados: {$orphans}");

        return self::SUCCESS;
    }
}
