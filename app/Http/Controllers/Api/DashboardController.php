<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $period = $request->get('period', 'today');
        [$startDate, $endDate] = $this->getPeriodDates($period);

        $taskQuery = Task::query();
        if ($startDate) $taskQuery->whereBetween('scheduled_date', [$startDate, $endDate]);

        return response()->json([
            'overview' => [
                'total_clients' => Client::count(),
                'total_tasks' => (clone $taskQuery)->count(),
                'completed_tasks' => (clone $taskQuery)->where('status', 'completed')->count(),
                'pending_tasks' => (clone $taskQuery)->whereIn('status', ['pending', 'assigned'])->count(),
                'in_progress_tasks' => (clone $taskQuery)->where('status', 'in_progress')->count(),
                'failed_tasks' => (clone $taskQuery)->where('status', 'failed')->count(),
            ],
            'by_company' => \App\Models\Company::withCount(['clients', 'tasks'])->get()->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'code' => $c->code, 'tasks_count' => $c->tasks_count]),
            'whatsapp_stats' => [
                'sent_today' => \App\Models\WhatsAppMessage::whereDate('sent_at', today())->count(),
                'delivered_today' => \App\Models\WhatsAppMessage::whereDate('delivered_at', today())->count(),
                'failed_today' => \App\Models\WhatsAppMessage::whereDate('created_at', today())->where('status', 'failed')->count(),
            ],
            'success_rate' => $this->calculateSuccessRate($taskQuery),
        ]);
    }

    public function mapData(Request $request)
    {
        $date = $request->get('date', now()->format('Y-m-d'));
        $clients = Client::with(['tasks' => fn($q) => $q->where('scheduled_date', $date), 'company'])
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->get()->map(fn($c) => [
                'id' => $c->id, 'name' => $c->full_name, 'phone' => $c->phone,
                'address' => $c->address, 'lat' => (float) $c->latitude, 'lng' => (float) $c->longitude,
                'status' => $c->status, 'company' => $c->company?->name,
            ]);
        return response()->json($clients);
    }

    public function agentPerformance(Request $request)
    {
        [$startDate, $endDate] = $this->getPeriodDates($request->get('period', 'week'));

        $agents = User::where('role', 'agent')->where('is_active', true)->get()->map(function ($agent) use ($startDate, $endDate) {
            $tasks = Task::where('assigned_to', $agent->id);
            if ($startDate) $tasks->whereBetween('scheduled_date', [$startDate, $endDate]);
            $taskData = $tasks->get();

            return [
                'id' => $agent->id, 'name' => $agent->name,
                'total_tasks' => $taskData->count(),
                'completed' => $taskData->where('status', 'completed')->count(),
                'failed' => $taskData->where('status', 'failed')->count(),
                'success_rate' => $taskData->count() > 0 ? round(($taskData->where('status', 'completed')->count() / $taskData->count()) * 100, 1) : 0,
            ];
        })->sortByDesc('success_rate')->values();

        return response()->json($agents);
    }

    public function myStats(Request $request)
    {
        $user = $request->user();
        $today = now()->format('Y-m-d');
        return response()->json([
            'today' => [
                'total' => Task::where('assigned_to', $user->id)->where('scheduled_date', $today)->count(),
                'completed' => Task::where('assigned_to', $user->id)->where('scheduled_date', $today)->where('status', 'completed')->count(),
            ],
        ]);
    }

    private function getPeriodDates(string $period): array
    {
        return match ($period) {
            'today' => [now()->format('Y-m-d'), now()->format('Y-m-d')],
            'week' => [now()->startOfWeek()->format('Y-m-d'), now()->endOfWeek()->format('Y-m-d')],
            'month' => [now()->startOfMonth()->format('Y-m-d'), now()->endOfMonth()->format('Y-m-d')],
            default => [null, null],
        };
    }

    private function calculateSuccessRate($query): float
    {
        $total = (clone $query)->count();
        $completed = (clone $query)->where('status', 'completed')->count();
        return $total > 0 ? round(($completed / $total) * 100, 1) : 0;
    }
}