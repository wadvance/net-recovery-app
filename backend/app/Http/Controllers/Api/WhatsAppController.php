<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\Task;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    /**
     * Envía (o reintenta) el mensaje creado en la base de datos a través del
     * proveedor de WhatsApp configurado (Twilio > Zavu > Meta). Reutilizado por
     * sendToClient, sendForTask y sendBulk.
     */
    protected function dispatchMessage(WhatsAppMessage $message, Client $client, Company $company, string $templateName, ?\App\Models\User $user = null): WhatsAppMessage
    {
        $result = (new WhatsAppService())->sendToClient($client, $company, $templateName, null, $user);
        if ($result['ok']) {
            $message->markSent($result['messageId'] ?? '', $result['response'] ?? []);
        } else {
            $message->markFailed($result['error']);
        }
        return $message;
    }

    public function sendBulk(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'client_ids' => 'required|array|min:1',
            'client_ids.*' => 'exists:clients,id',
            'template_name' => 'required|string',
        ]);

        $company = Company::find($request->company_id);
        $authUser = $request->user();
        $clientsQuery = Client::with('company')->whereIn('id', $request->client_ids);
        if ($authUser->role === 'agent') {
            $clientsQuery->whereHas('tasks', fn ($q) => $q->where('assigned_to', $authUser->id));
        }
        $clients = $clientsQuery->get();

        $created = 0;
        $skipped = 0;
        $seenPhones = [];
        foreach ($clients as $clientRecord) {
            // Solo 1 por cliente/teléfono — dedup en lote y contra BD
            $suffix = substr(preg_replace('/\D/', '', $clientRecord->formatted_phone), -8);
            if (isset($seenPhones[$suffix])) {
                $skipped++;
                continue;
            }
            $seenPhones[$suffix] = true;
            if ($this->alreadyNotified($clientRecord)) {
                $skipped++;
                continue;
            }
            $clientCompany = $clientRecord->company ?: $company;
            $params = $this->buildTemplateParams($clientCompany, $clientRecord);
            $message = WhatsAppMessage::create([
                'company_id' => $clientCompany?->id ?? $company?->id,
                'client_id' => $clientRecord->id,
                'to_phone' => $clientRecord->formatted_phone,
                'template_name' => $request->template_name,
                'template_params' => $params,
                'status' => 'pending',
            ]);

            $created++;

            $result = (new WhatsAppService())->sendToClient($clientRecord, $clientCompany, $request->template_name, null, $authUser);
            if ($result['ok']) {
                $message->markSent($result['messageId'] ?? '', $result['response'] ?? []);
            } else {
                $message->markFailed($result['error']);
            }
        }

        return response()->json([
            'message' => "Se procesaron {$created} mensajes" . ($skipped ? " ({$skipped} omitidos por ya notificados - 1 por cliente)" : ""),
            'created' => $created,
            'skipped' => $skipped,
            'configured' => (bool) $authUser->settings['whatsapp_api_key'] ?? ((bool) config('services.whatsapp.token') && (bool) config('services.whatsapp.phone_number_id') || (bool) config('services.twilio.account_sid')),
        ]);
    }

    public function sendToClient(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'template_name' => 'required|string',
            'task_id' => 'nullable|exists:tasks,id',
        ]);

        $company = $request->company_id ? Company::find($request->company_id) : null;
        $client = Client::with('company')->find($request->client_id);
        $company = $company ?: $client->company;

        if ($this->alreadyNotified($client)) {
            return response()->json(['message' => 'Cliente ya notificado por WhatsApp - solo 1 por cliente'], 422);
        }

        $params = $this->buildTemplateParams($company, $client);
        $message = WhatsAppMessage::create([
            'company_id' => $company?->id,
            'client_id' => $client->id,
            'task_id' => $request->task_id,
            'to_phone' => $client->formatted_phone,
            'template_name' => $request->template_name,
            'template_params' => $params,
            'status' => 'pending',
        ]);

        $message = $this->dispatchMessage($message, $client, $company, $request->template_name, $request->user());

        if ($message->status === 'failed') {
            return response()->json(['message' => $message->error_message], 503);
        }

        return response()->json($message);
    }

    /**
     * Envía un mensaje de WhatsApp al cliente asociado a la tarea, vinculando
     * el mensaje a la tarea. Agente debe ser el asignado; admin/supervisor
     * pueden enviar para cualquier tarea.
     */
    public function sendForTask(Request $request, Task $task)
    {
        $user = $request->user();
        $role = $user->role;

        if ($role === 'agent' && $task->assigned_to !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $templateName = $request->input('template_name') ?: config('services.whatsapp.default_template', 'equipment_recovery_notification');
        $request->validate([
            'template_name' => 'nullable|string',
        ]);

        $client = $task->client;
        if (!$client || !$client->formatted_phone || $client->formatted_phone === '+') {
            return response()->json(['message' => 'El cliente no tiene teléfono de WhatsApp'], 422);
        }

        $company = $task->company;

        if ($this->alreadyNotified($client)) {
            return response()->json(['message' => 'Cliente ya notificado por WhatsApp - solo 1 por cliente', 'status' => 'skipped'], 422);
        }

        $params = $this->buildTemplateParams($company, $client);
        $message = WhatsAppMessage::create([
            'company_id' => $company?->id,
            'task_id' => $task->id,
            'client_id' => $client->id,
            'to_phone' => $client->formatted_phone,
            'template_name' => $templateName,
            'template_params' => $params,
            'status' => 'pending',
        ]);

        $message = $this->dispatchMessage($message, $client, $company, $templateName, $user);

        return response()->json([
            'message' => 'Mensaje procesado',
            'status' => $message->status,
            'message_id' => $message->message_id,
            'error' => $message->error_message,
            'whatsapp_message' => $message,
        ]);
    }

    public function sendBulkForUser(Request $request, \App\Models\User $user)
    {
        $request->validate(['template_name' => 'nullable|string']);
        $templateName = $request->input('template_name', 'equipment_recovery_notification');
        $authUser = $request->user();
        if ($authUser->role === 'agent' && $authUser->id !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }
        $clientIds = Task::where('assigned_to', $user->id)->with('client')->get()
            ->pluck('client_id')->filter()->unique()->values();
        if ($clientIds->isEmpty()) {
            return response()->json(['message' => 'El usuario no tiene clientes asignados', 'created' => 0, 'skipped' => 0], 422);
        }
        $firstTask = Task::where('assigned_to', $user->id)->with('company')->first();
        $company = $firstTask?->company;
        if (!$company) $company = Company::first();
        $clients = Client::with('company')->whereIn('id', $clientIds)->get();
        $created = 0; $skipped = 0; $seenPhones = [];
        foreach ($clients as $client) {
            $suffix = substr(preg_replace('/\D/', '', $client->formatted_phone), -8);
            if ($suffix && isset($seenPhones[$suffix])) { $skipped++; continue; }
            if ($suffix) $seenPhones[$suffix] = true;
            if ($this->alreadyNotified($client)) { $skipped++; continue; }
            $clientCompany = $client->company ?: $company;
            $params = $this->buildTemplateParams($clientCompany, $client);
            $msg = WhatsAppMessage::create(['company_id' => $clientCompany?->id ?? $company?->id, 'client_id' => $client->id, 'to_phone' => $client->formatted_phone, 'template_name' => $templateName, 'template_params' => $params, 'status' => 'pending']);
            $created++;
            $result = (new WhatsAppService())->sendToClient($client, $clientCompany, $templateName, null, $user);
            if ($result['ok']) $msg->markSent($result['messageId'] ?? '', $result['response'] ?? []);
            else $msg->markFailed($result['error']);
        }
        return response()->json(['message' => "Se procesaron {$created} mensajes" . ($skipped ? " ({$skipped} omitidos)" : ""), 'created' => $created, 'skipped' => $skipped]);
    }

    public function messages(Request $request)
    {
        $user = $request->user();
        $query = WhatsAppMessage::query()->with(['client', 'task'])->latest();

        if ($user->role === 'agent') {
            $query->where(function ($q) use ($user) {
                $q->whereHas('task', fn ($tq) => $tq->where('assigned_to', $user->id))
                    ->orWhereHas('client', fn ($cq) => $cq->whereHas('tasks', fn ($tq) => $tq->where('assigned_to', $user->id)));
            });
        } else {
            if ($request->has('company_id')) $query->where('company_id', $request->company_id);
        }

        if ($request->has('status')) $query->where('status', $request->status);

        return response()->json($query->paginate($request->get('per_page', 15)));
    }

    private function alreadyNotified(Client $client): bool
    {
        $suffix = substr(preg_replace('/\D/', '', $client->formatted_phone), -8);
        if (!$suffix) return false;
        return WhatsAppMessage::where('direction', 'outbound')
            ->where(function ($q) use ($client, $suffix) {
                $q->where('client_id', $client->id)
                  ->orWhere('to_phone', 'like', '%' . $suffix);
            })
            ->where('status', '!=', 'failed')
            ->exists();
    }

    private function buildTemplateParams($company, Client $client): array
    {
        $suscriptor = $client->metadata['suscriptor'] ?? $client->order_number;
        $clientName = $client->full_name ?: 'Estimado cliente';

        return [
            'nombre_cliente' => $clientName,
            'empresa' => $company?->name ?? 'nuestra empresa',
            'numero_pedido' => $suscriptor,
            'direccion' => $client->address,
            'telefono' => $client->phone,
        ];
    }
}
