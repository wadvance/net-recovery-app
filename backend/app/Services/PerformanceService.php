<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

class PerformanceService
{
    /**
     * Calcula las métricas de recuperación por agente para un periodo dado.
     *
     * Mapeo:
     *  - total_asignado: tareas asignadas al agente en el periodo.
     *  - pendientes: tareas en estado pending o assigned.
     *  - total_gestion: tareas iniciadas, completadas o fallidas.
     *  - porcentaje_visitas: total_gestion / total_asignado * 100.
     *  - equipos: cantidad de tareas (una fila del Excel = un equipo).
     *  - total_recuperados: tareas completadas.
     *  - faltante: equipos - total_recuperados.
     *  - porcentaje_equipos: total_recuperados / equipos * 100.
     *
     * @param string      $startDate Fecha inicial (Y-m-d)
     * @param string|null $endDate   Fecha final (Y-m-d); si es null se usa startDate.
     * @param int|null    $userId    Filtrar por un agente específico.
     */
    public function metrics(string $startDate, ?string $endDate = null, ?int $userId = null): array
    {
        $endDate = $endDate ?: $startDate;

        $users = User::where('role', 'agent')
            ->where('is_active', true)
            ->when($userId, fn ($q) => $q->whereKey($userId))
            ->get();

        $rows = [];
        foreach ($users as $user) {
            $query = Task::where('assigned_to', $user->id)
                ->whereDate('scheduled_date', '>=', $startDate)
                ->whereDate('scheduled_date', '<=', $endDate);

            $totalAsignado = (clone $query)->count();
            $pendientes = (clone $query)->whereIn('status', ['pending', 'assigned'])->count();
            $inProgress = (clone $query)->where('status', 'in_progress')->count();
            $completadas = (clone $query)->where('status', 'completed')->count();
            $fallidas = (clone $query)->where('status', 'failed')->count();

            $totalGestion = $inProgress + $completadas + $fallidas;
            $faltante = $totalAsignado - $completadas;

            $rows[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'email' => $user->email,
                'total_asignado' => $totalAsignado,
                'pendientes' => $pendientes,
                'en_progreso' => $inProgress,
                'completadas' => $completadas,
                'fallidas' => $fallidas,
                'total_gestion' => $totalGestion,
                'porcentaje_visitas' => $totalAsignado > 0 ? round(($totalGestion / $totalAsignado) * 100, 1) : 0,
                'equipos' => $totalAsignado,
                'total_recuperados' => $completadas,
                'faltante' => $faltante,
                'porcentaje_equipos' => $totalAsignado > 0 ? round(($completadas / $totalAsignado) * 100, 1) : 0,
            ];
        }

        return [
            'period_start' => $startDate,
            'period_end' => $endDate,
            'rows' => $rows,
            'totals' => $this->totals($rows),
        ];
    }

    public function totals(array $rows): array
    {
        $sum = fn (string $key) => array_sum(array_column($rows, $key));

        $totalAsignado = $sum('total_asignado');
        $totalGestion = $sum('total_gestion');
        $totalRecuperados = $sum('total_recuperados');

        return [
            'total_asignado' => $totalAsignado,
            'pendientes' => $sum('pendientes'),
            'en_progreso' => $sum('en_progreso'),
            'completadas' => $sum('completadas'),
            'fallidas' => $sum('fallidas'),
            'total_gestion' => $totalGestion,
            'porcentaje_visitas' => $totalAsignado > 0 ? round(($totalGestion / $totalAsignado) * 100, 1) : 0,
            'equipos' => $totalAsignado,
            'total_recuperados' => $totalRecuperados,
            'faltante' => $totalAsignado - $totalRecuperados,
            'porcentaje_equipos' => $totalAsignado > 0 ? round(($totalRecuperados / $totalAsignado) * 100, 1) : 0,
        ];
    }

    /**
     * Genera reportes (PDF + Excel) por cada agente para un periodo y los
     * registra en la tabla `reports` marcados con `generated_by`.
     *
     * Se encuentra separado del controlador para poder reutilizarlo desde el
     * comando programado.
     */
    public function generateReports(string $period, string $startDate, string $endDate, int $generatedBy, string $format = 'both'): array
    {
        [$startDate, $endDate] = $this->resolvePeriod($period, $startDate);

        $users = \App\Models\User::where('role', 'agent')->where('is_active', true)->get();
        $created = [];

        foreach ($users as $user) {
            $metrics = $this->metrics($startDate, $endDate, $user->id);

            $excelPath = $this->buildExcel($metrics, $user->id, $startDate, $endDate);
            $pdfPath = $this->buildPdf($metrics, $user->id, $startDate, $endDate);

            \App\Models\Report::create([
                'title' => "Reporte de Desempeño $startDate - $endDate - {$user->name}",
                'type' => $period,
                'format' => 'excel',
                'company_id' => null,
                'user_id' => $user->id,
                'generated_by' => $generatedBy,
                'period_start' => $startDate,
                'period_end' => $endDate,
                'status' => 'completed',
                'file_path' => $excelPath,
                'file_size' => \Illuminate\Support\Facades\Storage::disk('local')->size($excelPath),
                'completed_at' => now(),
            ]);

            \App\Models\Report::create([
                'title' => "Reporte de Desempeño PDF $startDate - $endDate - {$user->name}",
                'type' => $period,
                'format' => 'pdf',
                'company_id' => null,
                'user_id' => $user->id,
                'generated_by' => $generatedBy,
                'period_start' => $startDate,
                'period_end' => $endDate,
                'status' => 'completed',
                'file_path' => $pdfPath,
                'file_size' => \Illuminate\Support\Facades\Storage::disk('local')->size($pdfPath),
                'completed_at' => now(),
            ]);

            $created[] = ['user_id' => $user->id, 'excel' => $excelPath, 'pdf' => $pdfPath];
        }

        return $created;
    }

    /**
     * Genera el archivo Excel para un conjunto de métricas.
     */
    public function buildExcel(array $metrics, int $userId, string $startDate, string $endDate): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("Desempeño $startDate");

        $sheet->setCellValue('A1', 'Usuario')
            ->setCellValue('B1', 'Total Asignado')
            ->setCellValue('C1', 'Pendientes')
            ->setCellValue('D1', 'En Progreso')
            ->setCellValue('E1', 'Completadas')
            ->setCellValue('F1', 'Fallidas')
            ->setCellValue('G1', 'Total Gestión')
            ->setCellValue('H1', '% Visitas')
            ->setCellValue('I1', 'Equipos')
            ->setCellValue('J1', 'Faltante')
            ->setCellValue('K1', '% Equipos')
            ->setCellValue('L1', 'Recuperados');

        $row = 2;
        foreach ($metrics['rows'] as $u) {
            $sheet->setCellValue('A' . $row, $u['user_name'])
                ->setCellValue('B' . $row, $u['total_asignado'])
                ->setCellValue('C' . $row, $u['pendientes'])
                ->setCellValue('D' . $row, $u['en_progreso'])
                ->setCellValue('E' . $row, $u['completadas'])
                ->setCellValue('F' . $row, $u['fallidas'])
                ->setCellValue('G' . $row, $u['total_gestion'])
                ->setCellValue('H' . $row, $u['porcentaje_visitas'])
                ->setCellValue('I' . $row, $u['equipos'])
                ->setCellValue('J' . $row, $u['faltante'])
                ->setCellValue('K' . $row, $u['porcentaje_equipos'])
                ->setCellValue('L' . $row, $u['total_recuperados']);
            $row++;
        }

        $sheet->setCellValue('A' . $row, 'TOTALES')
            ->setCellValue('B' . $row, $metrics['totals']['total_asignado'])
            ->setCellValue('C' . $row, $metrics['totals']['pendientes'])
            ->setCellValue('D' . $row, $metrics['totals']['en_progreso'])
            ->setCellValue('E' . $row, $metrics['totals']['completadas'])
            ->setCellValue('F' . $row, $metrics['totals']['fallidas'])
            ->setCellValue('G' . $row, $metrics['totals']['total_gestion'])
            ->setCellValue('H' . $row, $metrics['totals']['porcentaje_visitas'])
            ->setCellValue('I' . $row, $metrics['totals']['equipos'])
            ->setCellValue('J' . $row, $metrics['totals']['faltante'])
            ->setCellValue('K' . $row, $metrics['totals']['porcentaje_equipos'])
            ->setCellValue('L' . $row, $metrics['totals']['total_recuperados']);

        $last = $row;
        for ($col = 'B'; $col <= 'L'; $col++) {
            $sheet->getStyle($col . '2:' . $col . $last)->getNumberFormat()->setFormatCode('0');
        }
        $sheet->getStyle('H2:H' . $last)->getNumberFormat()->setFormatCode('0.0');
        $sheet->getStyle('K2:K' . $last)->getNumberFormat()->setFormatCode('0.0');
        $sheet->getStyle('A' . $last)->getFont()->setBold(true);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tmp = tempnam(sys_get_temp_dir(), 'perf_') . '.xlsx';
        $writer->save($tmp);

        $path = "reports/performance_{$startDate}_{$userId}_{$endDate}.xlsx";
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, file_get_contents($tmp));
        unlink($tmp);

        return $path;
    }

    /**
     * Genera el archivo PDF para un conjunto de métricas.
     */
    public function buildPdf(array $metrics, int $userId, string $startDate, string $endDate): string
    {
        $html = $this->buildPdfHtml($metrics, $startDate, $endDate);
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $path = "reports/performance_{$startDate}_{$userId}_{$endDate}.pdf";
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, $dompdf->output());

        return $path;
    }

    private function buildPdfHtml(array $metrics, string $startDate, string $endDate): string
    {
        $t = $metrics['totals'];
        $html = '<html><head><meta charset="utf-8"><style>
            body { font-family: Arial; font-size: 12px; margin: 40px; }
            h1 { font-size: 18px; margin-bottom: 4px; }
            h2 { font-size: 14px; margin-top: 24px; }
            table { width: 100%; border-collapse: collapse; margin-top: 8px; }
            th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
            th { background: #f5f5f5; font-size: 11px; }
            .number { text-align: right; }
            .metric { font-weight: bold; }
        </style></head><body>';
        $html .= "<h1>Reporte de Desempeño de Equipos</h1><p>Período: {$startDate} al {$endDate}</p>";
        $html .= '<table>
            <tr><th>Métrica</th><th>Cantidad</th><th>Descripción</th></tr>
            <tr><td class="metric">Total Asignado</td><td class="number">' . $t['total_asignado'] . '</td><td>Tareas asignadas al agente</td></tr>
            <tr><td class="metric">Pendientes</td><td class="number">' . $t['pendientes'] . '</td><td>Tareas no gestionadas</td></tr>
            <tr><td class="metric">Total Gestión</td><td class="number">' . $t['total_gestion'] . '</td><td>In progress, completadas y fallidas</td></tr>
            <tr><td class="metric">% de Visitas</td><td class="number">' . $t['porcentaje_visitas'] . '%</td><td>Gestión / Asignado</td></tr>
            <tr><td class="metric">Equipos</td><td class="number">' . $t['equipos'] . '</td><td>Total de equipos asignados</td></tr>
            <tr><td class="metric">Faltante</td><td class="number">' . $t['faltante'] . '</td><td>Equipos no recuperados</td></tr>
            <tr><td class="metric">% de Equipos</td><td class="number">' . $t['porcentaje_equipos'] . '%</td><td>Recuperados / Equipos</td></tr>
            <tr><td class="metric">Total Recuperados</td><td class="number">' . $t['total_recuperados'] . '</td><td>Tareas completadas</td></tr></table>';
        $html .= '<h2>Desglose por usuario</h2><table>
            <tr><th>Usuario</th><th>Total</th><th>Pendientes</th><th>Gestión</th><th>%Visitas</th><th>Equipos</th><th>Recuperados</th><th>Faltante</th><th>%Equipos</th></tr>';
        foreach ($metrics['rows'] as $r) {
            $html .= sprintf(
                '<tr><td>%s</td><td class="number">%d</td><td class="number">%d</td><td class="number">%d</td><td class="number">%s%%</td><td class="number">%d</td><td class="number">%d</td><td class="number">%d</td><td class="number">%s%%</td></tr>',
                $r['user_name'], $r['total_asignado'], $r['pendientes'], $r['total_gestion'],
                $r['porcentaje_visitas'], $r['equipos'], $r['total_recuperados'],
                $r['faltante'], $r['porcentaje_equipos']
            );
        }
        $html .= '</table></body></html>';
        return $html;
    }

    /**
     * Resuelve el rango de fechas según el periodo.
     */
    public function resolvePeriod(string $period, ?string $date = null): array
    {
        $now = Carbon::parse($date ?? now());

        return match ($period) {
            'daily' => [$now->format('Y-m-d'), $now->format('Y-m-d')],
            'weekly' => [$now->startOfWeek()->format('Y-m-d'), $now->endOfWeek()->format('Y-m-d')],
            'monthly' => [$now->startOfMonth()->format('Y-m-d'), $now->endOfMonth()->format('Y-m-d')],
            default => [$now->format('Y-m-d'), $now->format('Y-m-d')],
        };
    }

    /**
     * Tendencia diaria (buckets por fecha) para un período.
     * Usado por el dashboard admin para renderizar el gráfico de líneas.
     */
    public function dailyTrend(string $startDate, string $endDate, ?int $userId = null): array
    {
        return Task::query()
            ->when($userId, fn ($q) => $q->where('assigned_to', $userId))
            ->whereDate('scheduled_date', '>=', $startDate)
            ->whereDate('scheduled_date', '<=', $endDate)
            ->selectRaw("DATE(scheduled_date) as date")
            ->selectRaw("COUNT(*) as total_asigned")
            ->selectRaw("SUM(CASE WHEN status IN ('pending','assigned') THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => [
                'date' => $r->date,
                'total_assigned' => (int) $r->total_asigned,
                'pending' => (int) $r->pending,
                'in_progress' => (int) $r->in_progress,
                'completed' => (int) $r->completed,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Resumen consolidado (claves alineadas con el frontend).
     */
    public function summary(string $startDate, string $endDate, ?int $userId = null): array
    {
        $t = $this->totals($this->metrics($startDate, $endDate, $userId)['rows']);
        $totalAsignado = $t['total_asignado'] ?: 0;

        return [
            'total_assigned' => $t['total_asignado'],
            'completed' => $t['total_recuperados'],
            'pending' => $t['pendientes'],
            'in_progress' => $t['en_progreso'],
            'success_rate' => $totalAsignado > 0
                ? round(($t['total_recuperados'] / $totalAsignado) * 100, 1)
                : 0,
        ];
    }

    /**
     * Archivo CSV con las mismas columnas que buildExcel.
     */
    public function buildCsv(array $metrics, int $userId, string $startDate, string $endDate): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, [
            'Usuario', 'Total Asignado', 'Pendientes', 'En Progreso', 'Completadas',
            'Fallidas', 'Total Gestión', '% Visitas', 'Equipos', 'Faltante', '% Equipos', 'Recuperados',
        ]);

        foreach ($metrics['rows'] as $u) {
            fputcsv($stream, [
                $u['user_name'], $u['total_asignado'], $u['pendientes'], $u['en_progreso'],
                $u['completadas'], $u['fallidas'], $u['total_gestion'], $u['porcentaje_visitas'],
                $u['equipos'], $u['faltante'], $u['porcentaje_equipos'], $u['total_recuperados'],
            ]);
        }

        $t = $metrics['totals'];
        fputcsv($stream, [
            'TOTALES', $t['total_asignado'], $t['pendientes'], $t['en_progreso'],
            $t['completadas'], $t['fallidas'], $t['total_gestion'], $t['porcentaje_visitas'],
            $t['equipos'], $t['faltante'], $t['porcentaje_equipos'], $t['total_recuperados'],
        ]);

        rewind($stream);
        $path = "reports/performance_{$startDate}_{$userId}_{$endDate}.csv";
        Storage::disk('local')->put($path, stream_get_contents($stream));
        fclose($stream);

        return $path;
    }
}