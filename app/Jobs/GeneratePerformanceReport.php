<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\PerformanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GeneratePerformanceReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de intentos antes de marcar el reporte como fallido.
     */
    public int $tries = 3;

    public function __construct(
        public readonly int $reportId,
    ) {}

    public function handle(): void
    {
        $report = Report::find($this->reportId);

        if ($report === null) {
            return;
        }

        try {
            $service = app(PerformanceService::class);

            // PhpSpreadsheet rechaza títulos con ':' o >31 chars (e.g.
            // "Desempeño 2026-08-03 00:00:00"); forzamos formato Y-m-d.
            $startDate = $report->period_start?->format('Y-m-d') ?? now()->format('Y-m-d');
            $endDate = $report->period_end?->format('Y-m-d') ?? now()->format('Y-m-d');

            $metrics = $service->metrics($startDate, $endDate, $report->user_id);

            // buildExcel/buildPdf usan $userId solo para nombrar el archivo;
            // pasamos el id del reporte para evitar colisiones.
            $filePath = match ($report->format) {
                'csv' => $service->buildCsv($metrics, $report->id, $startDate, $endDate),
                'pdf' => $service->buildPdf($metrics, $report->id, $startDate, $endDate),
                default => $service->buildExcel($metrics, $report->id, $startDate, $endDate),
            };

            $report->markCompleted(
                $filePath,
                Storage::disk('local')->size($filePath)
            );
        } catch (Throwable $e) {
            $report->update(['status' => 'failed']);

            throw $e;
        }
    }
}
