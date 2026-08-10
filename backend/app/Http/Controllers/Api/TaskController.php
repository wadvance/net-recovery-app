<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskEvidence;
use App\Models\TaskComment;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::with(['client', 'assignee', 'company']);
        if ($request->user()->role === 'agent') {
            $query->where('assigned_to', $request->user()->id);
        }
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('assigned_to')) $query->where('assigned_to', $request->assigned_to);
        if ($request->filled('date')) $query->whereDate('scheduled_date', $request->date);
        if ($request->filled('date_from')) $query->whereDate('scheduled_date', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('scheduled_date', '<=', $request->date_to);
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('client', fn($q) => $q
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('order_number', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }
        $perPage = min((int) $request->get('per_page', 15), 10000);

        $paginated = $query->orderBy('scheduled_date', 'asc')->paginate($perPage);
        $paginated->through(function ($task) {
            $client = $task->client;
            $meta = $client ? (is_array($client->metadata) ? $client->metadata : []) : [];
            return [
                'id' => $task->id,
                'status' => $task->status,
                'scheduled_date' => $task->scheduled_date,
                'title' => $task->title,
                'type' => $task->type,
                'priority' => $task->priority,
                'latitude' => $task->latitude,
                'longitude' => $task->longitude,
                'client' => $client ? [
                    'id' => $client->id,
                    'full_name' => $client->full_name,
                    'phone' => $client->phone,
                    'alternate_phone' => $client->alternate_phone,
                    'order_number' => $client->order_number,
                    'address' => $client->address,
                    'metadata' => [
                        'suscriptor' => $meta['suscriptor'] ?? null,
                        'cedula' => $meta['cedula'] ?? null,
                        'provincia' => $meta['provincia'] ?? null,
                        'distrito' => $meta['distrito'] ?? null,
                        'corregimiento' => $meta['corregimiento'] ?? null,
                        'barrio' => $meta['barrio'] ?? null,
                    ],
                ] : null,
                'assignee' => $task->assignee ? ['id' => $task->assignee->id, 'name' => $task->assignee->name] : null,
                'company' => $task->company ? ['id' => $task->company->id, 'name' => $task->company->name, 'code' => $task->company->code] : null,
            ];
        });

        return response()->json($paginated);
    }

    public function show(Request $request, Task $task)
    {
        $task->load(['client.company', 'assignee', 'company', 'evidence', 'comments.user', 'assignments']);
        return response()->json($task);
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'client_id' => 'required|exists:clients,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string',
            'priority' => 'nullable|string',
            'scheduled_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $task = Task::create($request->only([
            'company_id', 'client_id', 'assigned_to', 'title', 'description', 'type',
            'priority', 'scheduled_date', 'latitude', 'longitude', 'address',
        ]));

        if ($task->assigned_to && $task->status === 'pending') {
            $task->update(['status' => 'assigned']);
            $task->client->update(['status' => 'assigned']);
            \App\Models\TaskAssignment::create([
                'task_id' => $task->id,
                'user_id' => $task->assigned_to,
                'assigned_by' => $request->user()->id,
                'assignment_type' => 'manual',
            ]);
        }

        return response()->json($task->load('client'), 201);
    }

    public function update(Request $request, Task $task)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string',
            'priority' => 'nullable|string',
            'status' => 'nullable|string',
            'scheduled_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $task->update($request->only([
            'title', 'description', 'type', 'priority', 'status',
            'scheduled_date', 'scheduled_time_start', 'scheduled_time_end',
            'latitude', 'longitude', 'address', 'assigned_to',
        ]));

        return response()->json($task->load('client'));
    }

    public function destroy(Request $request, Task $task)
    {
        $task->delete();
        return response()->json(['message' => 'Tarea eliminada']);
    }

    public function myTasks(Request $request)
    {
        $tasks = Task::with(['client.company', 'evidence'])
            ->where('assigned_to', $request->user()->id)
            ->when($request->has('status'), fn($q) => $q->where('status', $request->status))
            ->orderBy('scheduled_date', 'asc')
            ->paginate($request->get('per_page', 20));
        return response()->json($tasks);
    }

    public function myTasksByDate(Request $request, $date)
    {
        $tasks = Task::with(['client.company', 'evidence'])
            ->where('assigned_to', $request->user()->id)
            ->whereDate('scheduled_date', $date)
            ->orderBy('scheduled_time_start', 'asc')
            ->get();
        return response()->json($tasks);
    }

    public function start(Request $request, Task $task)
    {
        if ($task->assigned_to !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }
        $task->start();
        return response()->json($task->load('client'));
    }

    public function complete(Request $request, Task $task)
    {
        $request->validate(['notes' => 'nullable|string', 'signature' => 'nullable|string']);

        if ($task->assigned_to !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        if ($request->signature) {
            $sigData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->signature));
            $filename = "signatures/task_{$task->id}_" . time() . '.png';
            Storage::disk('public')->put($filename, $sigData);
            TaskEvidence::create([
                'task_id' => $task->id, 'user_id' => $request->user()->id,
                'type' => 'signature', 'file_path' => $filename, 'disk' => 'public',
            ]);
        }

        if ($request->notes) {
            TaskComment::create([
                'task_id' => $task->id, 'user_id' => $request->user()->id,
                'comment' => "Completada: " . $request->notes,
            ]);
        }

        $task->complete();
        return response()->json($task->load(['client', 'evidence']));
    }

    public function fail(Request $request, Task $task)
    {
        $request->validate(['reason' => 'required|string|max:500']);
        if ($task->assigned_to !== $request->user()->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }
        $task->fail($request->reason);
        TaskComment::create([
            'task_id' => $task->id, 'user_id' => $request->user()->id,
            'comment' => "Fallida: " . $request->reason,
        ]);
        return response()->json($task->load('client'));
    }

    public function acknowledge(Request $request, Task $task)
    {
        $assignment = $task->assignments()->where('user_id', $request->user()->id)->first();
        if ($assignment) $assignment->acknowledge();
        return response()->json(['message' => 'Confirmada']);
    }

    public function addEvidence(Request $request, Task $task)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf,mp4|max:51200',
            'type' => 'required|in:photo,signature,document,audio',
            'description' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $path = $file->store("evidence/task_{$task->id}", 'public');

        $evidence = TaskEvidence::create([
            'task_id' => $task->id, 'user_id' => $request->user()->id,
            'type' => $request->type, 'file_path' => $path, 'disk' => 'public',
            'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(),
            'description' => $request->description,
        ]);

        return response()->json($evidence, 201);
    }

    public function comments(Request $request, Task $task)
    {
        return response()->json($task->comments()->with('user')->orderBy('created_at', 'desc')->get());
    }

    public function addComment(Request $request, Task $task)
    {
        $request->validate(['comment' => 'required|string']);
        $comment = TaskComment::create([
            'task_id' => $task->id, 'user_id' => $request->user()->id, 'comment' => $request->comment,
        ]);
        return response()->json($comment->load('user'), 201);
    }

    public function autoAssign(Request $request)
    {
        if (!in_array($request->user()->role, ['admin', 'supervisor'], true)) {
            return response()->json(['message' => 'Solo el administrador puede asignar tareas'], 403);
        }

        $request->validate([
            'company_id' => 'nullable|array',
            'company_id.*' => 'exists:companies,id',
            'company_ids' => 'nullable|array',
            'company_ids.*' => 'exists:companies,id',
            'scheduled_date' => 'required|date',
            'tasks_per_agent' => 'nullable|integer|min:1|max:10000',
            'user_id' => 'nullable|exists:users,id',
            'redistribute' => 'boolean',
        ]);

        $agents = \App\Models\User::where('role', 'agent')->where('is_active', true)->get();
        if ($agents->isEmpty()) {
            return response()->json(['message' => 'No hay agentes activos'], 422);
        }
        $specificAgent = null;
        if ($request->filled('user_id')) {
            $specificAgent = $agents->firstWhere('id', $request->user_id);
            if ($specificAgent) {
                $agents = collect([$specificAgent]);
            }
        }

        $tasksPerAgent = $specificAgent ? PHP_INT_MAX : (int) ($request->get('tasks_per_agent', 10));
        $redistribute = $request->boolean('redistribute');

        $companies = array_filter((array) $request->get('company_id', $request->get('company_ids', [])));
        $companyIds = !empty($companies) ? array_values($companies)
            : \App\Models\Company::where('is_active', true)->pluck('id')->all();

        $taskQuery = Task::query()
            ->whereIn('company_id', $companyIds)
            ->when($request->filled('scheduled_date'), fn ($q) => $q->whereDate('scheduled_date', $request->scheduled_date));

        if ($redistribute) {
            $taskQuery->whereIn('status', ['pending', 'assigned']);
        } else {
            $taskQuery->where('status', 'pending')->whereNull('assigned_to');
        }

        $candidates = $taskQuery->get();

        $shuffled = $candidates->groupBy('company_id')
            ->map(fn ($group) => $group->shuffle())
            ->values()
            ->flatMap(fn ($group) => $group)
            ->all();

        $workloads = [];
        foreach ($agents as $agent) {
            $workloads[$agent->id] = Task::where('assigned_to', $agent->id)
                ->where('scheduled_date', $request->scheduled_date)
                ->whereIn('status', ['assigned', 'in_progress'])->count();
        }

        $assigned = 0;
        $reassigned = 0;
        DB::transaction(function () use ($shuffled, $agents, &$workloads, $tasksPerAgent, $request, &$assigned, &$reassigned) {
            foreach ($shuffled as $task) {
                if ($tasksPerAgent === PHP_INT_MAX) {
                    $selectedAgent = $agents->sortBy(fn($a) => $workloads[$a->id] ?? 0)->first();
                } else {
                    $selectedAgent = $agents
                        ->filter(fn($a) => ($workloads[$a->id] ?? 0) < $tasksPerAgent)
                        ->sortBy(fn($a) => $workloads[$a->id] ?? 0)
                        ->first();
                }
                if (!$selectedAgent) {
                    break;
                }

                $previous = $task->assigned_to;

                $task->update([
                    'assigned_to' => $selectedAgent->id,
                    'status' => 'assigned',
                    'scheduled_date' => $request->scheduled_date,
                ]);
                $task->client->update(['status' => 'assigned']);

                $changed = $previous !== null && $previous !== $selectedAgent->id;
                \App\Models\TaskAssignment::create([
                    'task_id' => $task->id, 'user_id' => $selectedAgent->id,
                    'assigned_by' => $request->user()->id,
                    'assignment_type' => $changed ? 'reassign' : 'auto',
                ]);
                if ($changed) {
                    $workloads[$previous] = max(($workloads[$previous] ?? 1) - 1, 0);
                    $reassigned++;
                }

                $workloads[$selectedAgent->id] = ($workloads[$selectedAgent->id] ?? 0) + 1;
                $assigned++;
            }
        });

        return response()->json([
            'assigned' => $assigned,
            'reassigned' => $reassigned,
            'total' => $candidates->count(),
            'company_ids' => array_values($companyIds),
            'by_company' => $candidates->groupBy('company_id')
                ->map(fn ($g) => $g->count())
                ->toArray(),
        ]);
    }

    public function bulkAssign(Request $request)
    {
        $request->validate([
            'client_ids' => 'required|array|min:1',
            'client_ids.*' => 'exists:clients,id',
            'user_id' => 'required|exists:users,id',
            'scheduled_date' => 'required|date',
        ]);

        $assigned = 0;
        DB::transaction(function () use ($request, &$assigned) {
            foreach ($request->client_ids as $clientId) {
                $client = Client::find($clientId);
                if (!$client) continue;
                $task = $client->tasks()->where('status', 'pending')->first();
                if (!$task) continue;

                $task->update(['assigned_to' => $request->user_id, 'status' => 'assigned', 'scheduled_date' => $request->scheduled_date]);
                $client->update(['status' => 'assigned']);
                \App\Models\TaskAssignment::create([
                    'task_id' => $task->id, 'user_id' => $request->user_id,
                    'assigned_by' => $request->user()->id, 'assignment_type' => 'manual',
                ]);
                $assigned++;
            }
        });

        return response()->json(['assigned' => $assigned]);
    }

    public function assign(Request $request, Task $task)
    {
        if (!in_array($request->user()->role, ['admin', 'supervisor'], true)) {
            return response()->json(['message' => 'Solo el administrador puede asignar tareas'], 403);
        }

        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'scheduled_date' => 'nullable|date',
        ]);

        $user = $request->user_id ? \App\Models\User::find($request->user_id) : null;
        if ($request->user_id && (!$user || $user->role !== 'agent')) {
            return response()->json(['message' => 'Solo se puede asignar a un agente activo'], 422);
        }

        $previous = $task->assigned_to;
        $task->update([
            'assigned_to' => $request->user_id,
            'status' => $request->user_id ? 'assigned' : 'pending',
            'scheduled_date' => $request->filled('scheduled_date') ? $request->scheduled_date : $task->scheduled_date,
        ]);

        $task->client->update(['status' => $request->user_id ? 'assigned' : 'pending']);

        if ($request->user_id) {
            $changed = $previous !== null && $previous !== $request->user_id;
            \App\Models\TaskAssignment::create([
                'task_id' => $task->id,
                'user_id' => $request->user_id,
                'assigned_by' => $request->user()->id,
                'assignment_type' => $changed ? 'reassign' : 'manual',
                'notes' => $changed
                    ? "Reasignada desde " . (\App\Models\User::find($previous)?->name ?? '')
                    : null,
            ]);
        }

        return response()->json($task->load(['client', 'assignee', 'company']));
    }

    public function myRoute(Request $request)
    {
        $date = $request->get('date', now()->format('Y-m-d'));
        $tasks = Task::with('client')
            ->where('assigned_to', $request->user()->id)
            ->whereDate('scheduled_date', $date)
            ->orderBy('scheduled_time_start', 'asc')
            ->get();

        return response()->json([
            'date' => $date,
            'tasks' => $tasks->map(fn($t) => [
                'task_id' => $t->id,
                'client_name' => $t->client?->full_name,
                'address' => $t->client?->address,
                'latitude' => (float) ($t->latitude ?? $t->client?->latitude),
                'longitude' => (float) ($t->longitude ?? $t->client?->longitude),
                'status' => $t->status,
            ]),
        ]);
    }

    public function optimizeRoute(Request $request)
    {
        $request->validate(['date' => 'nullable|date']);
        $date = $request->get('date', now()->format('Y-m-d'));

        $tasks = Task::with('client')
            ->where('assigned_to', $request->user()->id)
            ->whereDate('scheduled_date', $date)
            ->get()
            ->filter(fn($t) => ($t->latitude ?? $t->client?->latitude) && ($t->longitude ?? $t->client?->longitude))
            ->values();

        $ordered = $this->nearestNeighborOrder($tasks);

        \App\Models\Route::updateOrCreate(
            ['user_id' => $request->user()->id, 'route_date' => $date],
            ['optimized_order' => $ordered->pluck('id')->toArray(), 'status' => 'planned']
        );

        return response()->json([
            'route_date' => $date,
            'order' => $ordered->map(fn($t) => ['task_id' => $t->id, 'client_name' => $t->client?->full_name]),
        ]);
    }

    private function nearestNeighborOrder($tasks): \Illuminate\Support\Collection
    {
        if ($tasks->isEmpty()) return collect();

        $remaining = $tasks->values();
        $first = $remaining->shift();
        $ordered = collect([$first]);

        while ($remaining->isNotEmpty()) {
            $last = $ordered->last();
            $lastLat = (float) ($last->latitude ?? $last->client?->latitude);
            $lastLng = (float) ($last->longitude ?? $last->client?->longitude);

            $nearestIdx = null;
            $nearestDist = PHP_FLOAT_MAX;
            foreach ($remaining as $idx => $candidate) {
                $lat = (float) ($candidate->latitude ?? $candidate->client?->latitude);
                $lng = (float) ($candidate->longitude ?? $candidate->client?->longitude);
                $dist = ($lat - $lastLat) ** 2 + ($lng - $lastLng) ** 2;
                if ($dist < $nearestDist) {
                    $nearestDist = $dist;
                    $nearestIdx = $idx;
                }
            }

            $ordered->push($remaining->splice($nearestIdx, 1)->first());
        }

        return $ordered;
    }
}