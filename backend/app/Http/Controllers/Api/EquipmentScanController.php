<?php

namespace App\Http\Controllers\Api;

use App\Exports\EquipmentScansExport;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\EquipmentScan;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EquipmentScanController extends Controller
{
    /**
     * Lista de escaneos. Los agentes solo ven los suyos; supervisor y
     * administrador ven todos. Filtro opcional ?date=YYYY-MM-DD.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EquipmentScan::with(['client', 'company', 'scanner', 'task'])
            ->orderBy('scanned_at', 'desc');

        if ($request->user()->role === 'agent') {
            $query->where('scanned_by', $request->user()->id);
        }

        if ($date = $request->query('date')) {
            $query->whereDate('scanned_at', $date);
        } else {
            // Por defecto solo el día actual (operación diaria).
            $query->whereDate('scanned_at', today());
        }

        return response()->json([
            'data' => $query->limit(500)->get(),
        ]);
    }

    /**
     * Registrar un escaneo de equipo (cámara o teclado manual).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:255',
            'task_id' => 'nullable|exists:tasks,id',
            'method' => 'nullable|in:camera,manual',
            'notes' => 'nullable|string|max:1000',
            'scanned_at' => 'nullable|date',
        ]);

        $code = trim($data['code']);
        $taskId = $data['task_id'] ?? null;
        $clientId = null;
        $companyId = null;
        $task = null;

        if ($taskId) {
            $task = Task::find($taskId);
            if ($request->user()->role === 'agent'
                && $task
                && (int) $task->assigned_to !== (int) $request->user()->id) {
                return response()->json([
                    'message' => 'Esta tarea no está asignada a ti.',
                ], 403);
            }
            $clientId = $task?->client_id;
            $companyId = $task?->company_id;
        }

        // Si no hay tarea asociada, intenta vincular el escaneo a un
        // cliente/tarea de la hoja Excel subida (por CUENTA, CLIENTE,
        // suscriptor, teléfono u order_number).
        if (!$clientId && !$companyId) {
            [$clientId, $companyId, $taskId] = $this->findClientFromExcel($code, $request->user());
            if ($clientId && !$taskId) {
                // Asocia también la tarea del día si existe para ese cliente
                $task = Task::where('client_id', $clientId)
                    ->whereDate('scheduled_date', today())
                    ->latest('id')->first()
                    ?? Task::where('client_id', $clientId)->latest('id')->first();
                $taskId = $task?->id;
            }
        }

        $scan = EquipmentScan::create([
            'task_id' => $taskId,
            'client_id' => $clientId,
            'company_id' => $companyId,
            'scanned_by' => $request->user()->id,
            'code' => $code,
            'method' => $data['method'] ?? 'camera',
            'notes' => $data['notes'] ?? null,
            'scanned_at' => $data['scanned_at'] ?? now(),
        ]);

        return response()->json([
            'message' => 'Escaneo registrado correctamente',
            'scan' => $scan->load(['client', 'company', 'task']),
        ], 201);
    }

    public function destroy(Request $request, EquipmentScan $scan): JsonResponse
    {
        $isOwner = (int) $scan->scanned_by === (int) $request->user()->id;
        $isAdmin = in_array($request->user()->role, ['admin', 'supervisor'], true);

        if (!$isOwner && !$isAdmin) {
            return response()->json(['message' => 'No tienes permiso para eliminar este escaneo'], 403);
        }

        $scan->delete();

        return response()->json(['message' => 'Escaneo eliminado']);
    }

    /**
     * Exportar escaneos a Excel (.xlsx). Solo supervisor y administrador.
     * Parámetro opcional ?date=YYYY-MM-DD (por defecto hoy).
     */
    public function export(Request $request): BinaryFileResponse|JsonResponse
    {
        if (!in_array($request->user()->role, ['admin', 'supervisor'], true)) {
            return response()->json([
                'message' => 'Solo el supervisor o administrador pueden descargar el reporte de escaneos',
            ], 403);
        }

        $date = $request->query('date', today()->toDateString());
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
            return response()->json(['message' => 'Formato de fecha inválido (use YYYY-MM-DD)'], 422);
        }

        $query = EquipmentScan::query()
            ->with(['client', 'company', 'scanner', 'task'])
            ->whereDate('scanned_at', $date)
            ->orderBy('scanned_at');

        $filename = 'escaneos_equipos_' . $date . '.xlsx';

        return Excel::download(
            new EquipmentScansExport($query, "Escaneos {$date}"),
            $filename
        );
    }

    /**
     * Busca un cliente de la hoja Excel actual que coincida con el código
     * escaneado (CUENTA, CLIENTE, suscriptor, teléfono). Prioriza datos
     * del día en curso.
     */
    private function findClientFromExcel(string $code, $user): array
    {
        $code = trim($code);
        if ($code === '') return [null, null, null];

        // Normaliza: solo dígitos para comparación telefónica
        $digits = preg_replace('/\D+/', '', $code);

        $base = Client::query();
        // Agentes solo ven sus clientes; escaneos libres priorizan
        // igualmente el día en curso sin filtrar por agente para no
        // perder el vínculo del WhatsApp.
        $todayTaskIds = Task::whereDate('scheduled_date', today())->pluck('client_id')->all();

        $client = null;

        // 1. order_number exacto (CUENTA)
        $client = Client::where('order_number', $code)->latest('id')->first();
        if (!$client && $digits !== '') {
            $client = Client::where('order_number', $digits)->latest('id')->first();
        }
        // 2. metadata->cliente / suscriptor / cedula
        if (!$client) {
            $client = Client::whereRaw("json_extract(metadata, '\$.cliente') = ?", [$code])->latest('id')->first();
        }
        if (!$client) {
            $client = Client::whereRaw("json_extract(metadata, '\$.cuenta') = ?", [$code])->latest('id')->first();
        }
        if (!$client) {
            $client = Client::whereRaw("json_extract(metadata, '\$.suscriptor') = ?", [$code])->latest('id')->first();
        }
        // 3. teléfono
        if (!$client && $digits !== '') {
            $client = Client::where('phone', 'like', '%' . $digits)->latest('id')->first();
        }
        // 4. Si no hay match y hay datos del día, vincula al primer
        //    cliente del día para que el escaneo quede contemplado en
        //    Escaneos con al menos empresa/fecha de la hoja actual.
        if (!$client && !empty($todayTaskIds)) {
            $task = Task::whereIn('client_id', $todayTaskIds)->latest('id')->first();
            if ($task) {
                return [$task->client_id, $task->company_id, $task->id];
            }
        }

        if (!$client) return [null, null, null];

        $taskId = null;
        $task = Task::where('client_id', $client->id)->whereDate('scheduled_date', today())->latest('id')->first();
        if (!$task) {
            $task = Task::where('client_id', $client->id)->latest('id')->first();
        }

        return [$client->id, $client->company_id, $task?->id];
    }
}
