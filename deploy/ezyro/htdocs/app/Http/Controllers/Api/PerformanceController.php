<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GeneratePerformanceReport;
use App\Models\Report;
use App\Services\PerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PerformanceController extends Controller
{
    public function __construct(
        private readonly PerformanceService $perfService,
    ) {}

    /**
     * Mapea los estados internos a los que muestra el panel admin.
     */
    private function mapStatus(string $status): string
    {
        return match ($status) {
            'completed' => 'ready',
            'generating' => 'processing',
            'failed' => 'failed',
            'pending' => 'pending',
            default => 'pending',
        };
    }

    private function normalizeReport(Report $report): array
    {
        return [
            'id' => $report->id,
            'name' => $report->title,
            'date_from' => $report->period_start?->format('Y-m-d'),
            'date_to' => $report->period_end?->format('Y-m-d'),
            'format' => $report->format,
            'period' => $report->type,
            'status' => $this->mapStatus($report->status),
            'created_at' => $report->created_at?->toDateTimeString(),
        ];
    }

    /**
     * Métricas por agente para un período (daily por defecto).
     *
     * Accepta: date (Y-m-d), period (daily|weekly|monthly), start_date, end_date.
     */
    public function daily(Request $request)
    {
        if ($request->filled('start_date') || $request->filled('end_date')) {
            $startDate = $request->input('start_date') ?: now()->format('Y-m-d');
            $endDate = $request->input('end_date') ?: $startDate;
        } elseif ($request->filled('date_from') || $request->filled('date_to')) {
            $startDate = $request->input('date_from') ?: now()->format('Y-m-d');
            $endDate = $request->input('date_to') ?: $startDate;
        } else {
            $seed = $request->get('date') ?: now()->format('Y-m-d');
            [$startDate, $endDate] = $this->perfService->resolvePeriod($request->get('period', 'daily'), $seed);
        }

        $userId = $request->input('user_id');
        $metrics = $this->perfService->metrics($startDate, $endDate, $userId);

        return response()->json([
            'data' => [
                'period_start' => $startDate,
                'period_end' => $endDate,
                'periodo' => $request->get('period', 'daily'),
                'daily' => $this->perfService->dailyTrend($startDate, $endDate, $userId),
                'summary' => $this->perfService->summary($startDate, $endDate, $userId),
                'agents' => $metrics['rows'],
                'totals' => $metrics['totals'],
            ],
        ]);
    }

    /**
     * Métricas del agente autenticado (para la app móvil).
     */
    public function mine(Request $request)
    {
        $user = $request->user();
        $period = $request->get('period', 'daily');
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
        } else {
            $seed = $request->get('date') ?: now()->format('Y-m-d');
            [$startDate, $endDate] = $this->perfService->resolvePeriod($period, $seed);
        }
        $metrics = $this->perfService->metrics($startDate, $endDate, $user->id);

        return response()->json([
            'data' => [
                'period_start' => $startDate,
                'period_end' => $endDate,
                'periodo' => $period,
                'user' => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'metrics' => $metrics['rows'][0] ?? [],
                'totals' => $metrics['totals'],
            ],
        ]);
    }

    /**
     * Genera un reporte consolidado (todo el equipo o un agente) de forma
     * asíncrona: se persiste el registro con estado `generating` y la generación
     * del archivo se despacha a la cola mediante GeneratePerformanceReport.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|string|date',
            'date_to' => 'nullable|string|date',
            'start_date' => 'nullable|string|date',
            'end_date' => 'nullable|string|date',
            'period' => 'nullable|string|in:daily,weekly,monthly',
            'format' => 'required|string|in:xlsx,csv,excel,pdf,both',
            'name' => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id',
        ]);

        abort_if($request->user()->role === 'agent', 403, 'No autorizado');

        // Resolución de fechas (admite el formato del panel admin y el formato legacy).
        if ($request->filled('start_date') || $request->filled('end_date')) {
            $startDate = $request->input('start_date') ?: now()->format('Y-m-d');
            $endDate = $request->input('end_date') ?: $startDate;
        } elseif ($request->filled('date_from') || $request->filled('date_to')) {
            $startDate = $request->input('date_from') ?: now()->format('Y-m-d');
            $endDate = $request->input('date_to') ?: $startDate;
        } else {
            $seed = now()->format('Y-m-d');
            [$startDate, $endDate] = $this->perfService->resolvePeriod(
                $request->input('period', 'daily'),
                $seed
            );
        }

        // Normaliza el formato a la convención interna del modelo.
        $format = match (true) {
            in_array($request->input('format'), ['excel', 'xlsx'], true) => 'xlsx',
            $request->input('format') === 'csv' => 'csv',
            $request->input('format') === 'pdf' => 'pdf',
            $request->input('format') === 'both' => 'both',
            default => 'xlsx',
        };

        $title = $request->input('name')
            ?: "Reporte de Rendimiento $startDate - $endDate";

        $report = Report::create([
            'title' => $title,
            'type' => 'performance',
            'format' => $format,
            'company_id' => null,
            'user_id' => $request->get('user_id'), // null => reporte consolidado de todo el equipo
            'generated_by' => $request->user()->id,
            'period_start' => $startDate,
            'period_end' => $endDate,
            'filters' => [
                'period' => $request->input('period', 'custom'),
                'user_id' => $request->get('user_id'),
            ],
            'status' => 'generating',
        ]);

        GeneratePerformanceReport::dispatch($report->id);

        return response()->json([
            'message' => 'Reporte encolado. Se generará en breve.',
            'data' => $this->normalizeReport($report),
        ], 202);
    }

    /**
     * Reportes generados (cualquier estado) visibles para el usuario.
     * Admin/Supervisor ven todos los reportes del equipo; agentes solo los suyos.
     */
    public function myReports(Request $request)
    {
        $user = $request->user();

        $reports = Report::query()
            ->when(!in_array($user->role, ['admin', 'supervisor'], true), function ($q) use ($user) {
                $q->where(function ($query) use ($user) {
                    $query->where('generated_by', $user->id)
                        ->orWhere('user_id', $user->id);
                });
            })
            ->where('type', 'performance')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'reports' => $reports->map(fn (Report $r) => $this->normalizeReport($r))->all(),
        ]);
    }

    /**
     * Descarga de un reporte (PDF o Excel) del agente autenticado.
     */
    public function download(Request $request, Report $report)
    {
        $user = $request->user();

        if ($user->role === 'agent' && $report->user_id !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if (!$report->file_path) {
            return response()->json(['message' => 'Archivo no disponible'], 404);
        }

        $diskPath = Storage::disk('local')->path($report->file_path);
        if (!file_exists($diskPath)) {
            return response()->json(['message' => 'Archivo no encontrado'], 404);
        }

        $extension = $report->format === 'pdf' ? 'pdf' : 'xlsx';

        return Storage::disk('local')->download($report->file_path, $report->title . '.' . $extension);
    }
}
