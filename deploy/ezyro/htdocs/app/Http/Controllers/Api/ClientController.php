<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Task;
use App\Models\TaskAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $isAgent = $request->user()->role === 'agent';

        $query = Client::with(['company', 'assignedUser']);
        if ($isAgent) {
            $query->whereHas('tasks', fn($q) => $q->where('assigned_to', $request->user()->id));
        }
        if ($request->filled('company_id')) $query->where('company_id', $request->company_id);
        if ($request->boolean('unassigned')) {
            $query->where(fn($q) => $q->whereDoesntHave('tasks', fn($q2) => $q2->whereNotNull('assigned_to'))
                ->orWhereNotIn('id', Task::whereNotNull('assigned_to')->pluck('client_id')));
        }
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('user_id')) $query->whereHas('tasks', fn($q) => $q->where('assigned_to', $request->user_id));
        if ($request->filled('date')) {
            $query->whereHas('tasks', fn($q) => $q->whereDate('scheduled_date', $request->date));
        }
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(fn($q) => $q->where('full_name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('order_number', 'like', "%{$search}%"));
        }
        $perPage = min((int) $request->get('per_page', 15), 500);
        return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    public function show(Client $client)
    {
        return response()->json($client->load(['company', 'tasks', 'assignedUser']));
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'order_number' => 'required|string',
            'address' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'reference' => 'nullable|string',
        ]);

        $client = Client::create($request->only([
            'company_id', 'full_name', 'phone', 'address', 'latitude',
            'longitude', 'order_number', 'reference',
        ]));

        Task::create([
            'company_id' => $client->company_id,
            'client_id' => $client->id,
            'title' => 'Visita a ' . $client->full_name,
            'description' => $request->reference,
            'type' => 'visit',
            'status' => 'pending',
        ]);

        return response()->json($client->load('company'), 201);
    }

    public function update(Request $request, Client $client)
    {
        $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'order_number' => 'nullable|string',
            'reference' => 'nullable|string',
            'status' => 'sometimes|string',
        ]);

        $client->update($request->only([
            'full_name', 'phone', 'address', 'latitude',
            'longitude', 'order_number', 'reference', 'status',
        ]));

        return response()->json($client->load('company'));
    }

    public function destroy(Client $client)
    {
        $client->tasks()->delete();
        $client->delete();
        return response()->json(['message' => 'Cliente eliminado']);
    }

    public function updateStatus(Request $request, Client $client)
    {
        $request->validate(['status' => 'required|in:seguimiento,por_buscar,retirado']);

        if ($client->status === $request->status) {
            return response()->json($client->load(['company', 'assignedUser']));
        }

        $client->update(['status' => $request->status]);

        $task = $client->tasks()
            ->whereIn('status', ['assigned', 'in_progress'])
            ->latest('updated_at')
            ->first();

        if ($task) {
            $map = ['seguimiento' => 'in_progress', 'por_buscar' => 'assigned', 'retirado' => 'completed'];
            $completed = $request->status === 'retirado';
            $task->update([
                'status' => $map[$request->status],
                'started_at' => $task->started_at ?? now(),
                'completed_at' => $completed ? now() : null,
            ]);
        }

        return response()->json($client->load(['company', 'assignedUser']));
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
                TaskAssignment::create([
                    'task_id' => $task->id, 'user_id' => $request->user_id,
                    'assigned_by' => $request->user()->id, 'assignment_type' => 'manual',
                ]);
                $assigned++;
            }
        });

        return response()->json(['assigned' => $assigned]);
    }
}