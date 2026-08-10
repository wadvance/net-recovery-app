<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhatsAppController extends Controller
{
    protected function client(): ?\Illuminate\Http\Client\PendingRequest
    {
        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        if (!$token || !$phoneNumberId) {
            return null;
        }
        $version = config('services.whatsapp.version', 'v20.0');
        return Http::withToken($token)
            ->baseUrl("https://graph.facebook.com/{$version}/{$phoneNumberId}");
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
        $clientsQuery = Client::whereIn('id', $request->client_ids);
        if ($request->user()->role === 'agent') {
            $clientsQuery->whereHas('tasks', fn($q) => $q->where('assigned_to', $request->user()->id));
        }
        $clients = $clientsQuery->get();
        $client = $this->client();

        $created = 0;
        foreach ($clients as $clientRecord) {
            $params = $this->buildTemplateParams($company, $clientRecord);
            $message = WhatsAppMessage::create([
                'company_id' => $company->id,
                'client_id' => $clientRecord->id,
                'to_phone' => $clientRecord->formatted_phone,
                'template_name' => $request->template_name,
                'template_params' => $params,
                'status' => 'pending',
            ]);

            $created++;
            if ($client) {
                try {
                    $response = $client->post('/messages', [
                        'messaging_product' => 'whatsapp',
                        'to' => $clientRecord->formatted_phone,
                        'type' => 'template',
                        'template' => [
                            'name' => $request->template_name,
                            'language' => ['code' => 'es'],
                            'components' => $this->buildParams($company, $clientRecord),
                        ],
                    ]);

                    if ($response->ok()) {
                        $message->markSent($response->json('messages.0.id'), $response->json());
                    } else {
                        $message->markFailed($response->body());
                    }
                } catch (\Throwable $e) {
                    $message->markFailed($e->getMessage());
                }
            } else {
                $message->markFailed('WhatsApp API no configurada');
            }
        }

        return response()->json([
            'message' => "Se procesaron {$created} mensajes",
            'created' => $created,
            'configured' => (bool) $client,
        ]);
    }

    public function sendToClient(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'template_name' => 'required|string',
        ]);

        $company = $request->company_id ? Company::find($request->company_id) : null;
        $client = Client::with('company')->find($request->client_id);
        $company = $company ?: $client->company;

        $message = WhatsAppMessage::create([
            'company_id' => $company?->id,
            'client_id' => $client->id,
            'to_phone' => $client->formatted_phone,
            'template_name' => $request->template_name,
            'status' => 'pending',
        ]);

        $api = $this->client();
        if (!$api) {
            $message->markFailed('WhatsApp API no configurada');
            return response()->json(['message' => $message->error_message], 503);
        }

        try {
            $response = $api->post('/messages', [
                'messaging_product' => 'whatsapp',
                'to' => $client->formatted_phone,
                'type' => 'template',
                'template' => [
                    'name' => $request->template_name,
                    'language' => ['code' => 'es'],
                    'components' => $this->buildParams($company, $client),
                ],
            ]);

            if ($response->ok()) {
                $message->markSent($response->json('wamid.value') ?? $response->json('messages.0.id'), $response->json());
            } else {
                $message->markFailed($response->body());
            }
        } catch (\Throwable $e) {
            $message->markFailed($e->getMessage());
        }

        return response()->json($message);
    }

    public function messages(Request $request)
    {
        $user = $request->user();
        $query = WhatsAppMessage::query()->with(['client', 'task'])->latest();

        // Cada usuario ve la lista que le corresponde: el agente solo sus
        // clientes/tareas asignadas; admin y supervisor ven todo (con filtro
        // opcional por empresa).
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

    private function buildTemplateParams($company, Client $client): array
    {
        return [
            'nombre_cliente' => $client->full_name,
            'empresa' => $company?->name ?? 'nuestra empresa',
            'numero_pedido' => $client->order_number,
            'direccion' => $client->address,
            'telefono' => $client->phone,
        ];
    }

    private function buildParams($company, Client $client): array
    {
        $params = $this->buildTemplateParams($company, $client);
        return [
            [
                'type' => 'body',
                'parameters' => array_map(fn($v) => ['type' => 'text', 'text' => (string) $v], array_values($params)),
            ],
        ];
    }
}