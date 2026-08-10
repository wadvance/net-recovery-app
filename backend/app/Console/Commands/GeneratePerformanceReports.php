<?php

namespace App\Console\Commands;

use App\Services\PerformanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GeneratePerformanceReports extends Command
{
    protected $signature = 'performance:reports {--period=daily} {--force}';

    protected $description = 'Genera los reportes de desempeño (PDF + Excel) por agente y período, para enviar al final del día, semana o mes.';

    public function handle(PerformanceService $perfService): int
    {
        $period = $this->option('period');
        $startDate = now()->format('Y-m-d');

        $this->info("Generando reportes de desempeño para el período: {$period}");

        $created = $perfService->generateReports($period, $startDate, null, 0, 'both');

        foreach ($created as $item) {
            $this->line(" - Usuario {$item['user_id']}: excel={$item['excel']} pdf={$item['pdf']}");
        }

        $this->info('Reportes generados: ' . count($created));

        return self::SUCCESS;
    }
}
