<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportSchedule;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(Report::latest()->paginate($request->get('per_page', 15)));
    }

    public function generate(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'type' => 'required|in:weekly,monthly,custom',
            'format' => 'nullable|in:excel,csv,pdf',
            'company_id' => 'nullable|exists:companies,id',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
        ]);

        $report = Report::create([
            'title' => $request->title ?? 'Reporte ' . ucfirst($request->type),
            'type' => $request->type,
            'format' => $request->format ?? 'excel',
            'company_id' => $request->company_id,
            'generated_by' => $request->user()->id,
            'period_start' => $request->period_start,
            'period_end' => $request->period_end,
            'status' => 'generating',
        ]);

        try {
            $filePath = $this->buildReportFile($report, $request);
            $fileSize = Storage::disk('local')->size($filePath);
            $report->markCompleted($filePath, $fileSize);
        } catch (\Throwable $e) {
            $report->update(['status' => 'failed']);
            return response()->json(['message' => 'Error generando reporte: ' . $e->getMessage()], 422);
        }

        return response()->json($report);
    }

    public function destroy(Request $request, Report $report)
    {
        if ($report->file_path) {
            Storage::disk('local')->delete($report->file_path);
        }
        $report->delete();
        return response()->json(['message' => 'Reporte eliminado']);
    }

    public function download(Request $request, Report $report)
    {
        if (!$report->file_path || !Storage::disk('local')->exists($report->file_path)) {
            return response()->json(['message' => 'Archivo no disponible'], 404);
        }
        return Storage::disk('local')->download($report->file_path, $report->title . '.xlsx');
    }

    public function schedules(Request $request)
    {
        return response()->json(ReportSchedule::latest()->paginate($request->get('per_page', 15)));
    }

    public function storeSchedule(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:weekly,monthly,custom',
            'format' => 'nullable|in:excel,csv,pdf',
            'company_id' => 'nullable|exists:companies,id',
            'send_at' => 'nullable|date_format:H:i',
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'recipients' => 'nullable|array',
        ]);

        $schedule = ReportSchedule::create($request->only([
            'name', 'type', 'format', 'company_id', 'filters', 'recipients',
            'send_at', 'day_of_week', 'day_of_month', 'is_active',
        ]) + ['is_active' => true]);

        return response()->json($schedule, 201);
    }

    public function updateSchedule(Request $request, ReportSchedule $schedule)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:weekly,monthly,custom',
            'format' => 'sometimes|in:excel,csv,pdf',
            'company_id' => 'nullable|exists:companies,id',
            'send_at' => 'nullable|date_format:H:i',
            'day_of_week' => 'nullable|integer|min:0|max:6',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'recipients' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $schedule->update($request->only([
            'name', 'type', 'format', 'company_id', 'filters', 'recipients',
            'send_at', 'day_of_week', 'day_of_month', 'is_active',
        ]));

        return response()->json($schedule);
    }

    public function destroySchedule(ReportSchedule $schedule)
    {
        $schedule->delete();
        return response()->json(['message' => 'Programación eliminada']);
    }

    private function buildReportFile(Report $report, Request $request): string
    {
        $rows = [
            ['Empresa', 'Cliente', 'Teléfono', 'Número de Pedido', 'Dirección', 'Estado', 'Fecha'],
        ];

        $query = Client::with('company')->withCount('tasks');
        if ($request->company_id) $query->where('company_id', $request->company_id);
        if ($request->has('status')) $query->where('status', $request->status);
        if ($request->period_start) $query->whereDate('created_at', '>=', $request->period_start);
        if ($request->period_end) $query->whereDate('created_at', '<=', $request->period_end);

        foreach ($query->get() as $client) {
            $rows[] = [
                $client->company?->name ?? '',
                $client->full_name,
                $client->phone,
                $client->order_number,
                $client->address,
                $client->status,
                $client->created_at?->format('Y-m-d') ?? '',
            ];
        }

        if ($report->format === 'csv') {
            return $this->writeCsv("reports/report_{$report->id}.csv", $rows);
        }

        return $this->writeExcel("reports/report_{$report->id}.xlsx", $rows);
    }

    private function writeCsv(string $path, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        Storage::disk('local')->put($path, stream_get_contents($stream));
        fclose($stream);
        return $path;
    }

    private function writeExcel(string $path, array $rows): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        $tempFile = tempnam(sys_get_temp_dir(), 'report_');
        $writer->save($tempFile);
        Storage::disk('local')->put($path, file_get_contents($tempFile));
        unlink($tempFile);

        return $path;
    }
}