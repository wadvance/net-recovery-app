<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de Meta Cloud API (WhatsApp oficial directo, sin intermediario).
 *
 * - GET  /api/v1/whatsapp/meta/webhook  -> verificación hub.challenge
 * - POST /api/v1/whatsapp/meta/webhook  -> mensajes entrantes y estados
 */
class MetaWebhookController extends Controller
{
    public function verify(Request $request)
    {
        @file_put_contents(
            '/home/vol18_2/ezyro.com/ezyro_42634304/htdocs/webhook_log.txt',
            date('c') . " VERIFY mode=" . ($_GET['hub_mode'] ?? '') .
            " token=" . ($_GET['hub_verify_token'] ?? '') .
            " challenge=" . ($_GET['hub_challenge'] ?? '') .
            " UA=" . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n",
            FILE_APPEND
        );

        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $expected = config('services.whatsapp.webhook_verify_token');
        if ($mode === 'subscribe' && $token && hash_equals((string) $expected, (string) $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('Meta webhook: verificación fallida');
        return response('Forbidden', 403);
    }

    public function receive(Request $request)
    {
        // Validación de firma X-Hub-Signature-256 (solo si hay APP_SECRET configurado)
        $secret = config('services.whatsapp.app_secret');
        if ($secret) {
            $header = $request->header('X-Hub-Signature-256', '');
            $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
            if (!hash_equals($expected, (string) $header)) {
                Log::warning('Meta webhook: firma invalida');
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $payload = $request->all();

        try {
            foreach (($payload['entry'] ?? []) as $entry) {
                foreach (($entry['changes'] ?? []) as $change) {
                    $value = $change['value'] ?? [];

                    // Estados de entrega de nuestros envíos outbound
                    if (!empty($value['statuses'])) {
                        foreach ($value['statuses'] as $st) {
                            match ($st['status'] ?? '') {
                                'delivered' => $this->handleDelivered(['messageId' => $st['id'] ?? null]),
                                'failed' => $this->handleFailed([
                                    'messageId' => $st['id'] ?? null,
                                    'error' => $st['errors'][0]['title'] ?? ($st['errors'][0]['message'] ?? 'Error de entrega'),
                                ]),
                                default => null,
                            };
                        }
                    }

                    // Mensajes entrantes de clientes
                    if (!empty($value['messages'])) {
                        foreach ($value['messages'] as $msg) {
                            $this->handleInbound($this->mapInbound($msg, $value));
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Meta webhook error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Procesa mensaje entrante de Meta y lo guarda/forwards
     */
    public function handleInbound(array $data): void
    {
        $from = $data['from'] ?? null;
        if (!$from) return;

        $digits = preg_replace('/\D/', '', $from);
        $suffix = substr($digits, -8);
        $client = Client::with('company')->where('phone', 'like', "%{$suffix}")->first()
            ?? Client::where('alternate_phone', 'like', "%{$suffix}")->first();
        if (!$client && strlen($digits) > 8) {
            // fallback por últimos 7 dígitos por si formato varía
            $suffix7 = substr($digits, -7);
            $client = Client::with('company')->where('phone', 'like', "%{$suffix7}")->first()
                ?? Client::where('alternate_phone', 'like', "%{$suffix7}")->first();
        }

        // Para Meta, el destinatario es el phone_number_id de nuestra WABA
        // No hay "senderId" como en Zavu, así que usamos el agente asignado a la tarea/conversación
        $company = $client?->company;
        $task = $client?->tasks()->latest('updated_at')->first();

        $conversation = Conversation::firstOrCreate(
            ['phone' => $from],
            [
                'company_id' => $company?->id,
                'client_id' => $client?->id,
                'assigned_to' => $task?->assigned_to,
                'contact_name' => $data['profileName'] ?? $client?->full_name,
            ]
        );

        if (!$conversation->wasRecentlyCreated && $client) {
            $conversation->update([
                'company_id' => $company?->id ?? $conversation->company_id,
                'client_id' => $client->id,
                'assigned_to' => $task?->assigned_to ?? $conversation->assigned_to,
                'contact_name' => $data['profileName'] ?? $conversation->contact_name,
            ]);
        }

        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->id,
            'company_id' => $company?->id,
            'client_id' => $client?->id,
            'task_id' => $task?->id,
            'to_phone' => config('services.whatsapp.phone_number_id'),
            'from_phone' => $from,
            'body' => $data['text'] ?? $this->describeContent($data['content'] ?? null),
            'template_name' => 'inbound',
            'direction' => 'inbound',
            'message_id' => $data['messageId'] ?? null,
            'status' => 'received',
            'response_data' => $data,
        ]);

        $conversation->update([
            'last_message' => $message->body,
            'last_message_at' => now(),
            'unread_count' => ($conversation->unread_count ?? 0) + 1,
            'status' => 'open',
        ]);

        // --- Reenvío automático al WhatsApp personal del agente asignado ---
        // Horario laboral: solo reenvía entre 08:00 y 20:00 (hora de Panamá).
        // Fuera de ese rango el mensaje queda registrado en Conversaciones
        // pero NO se manda al celular del agente.
        $tz = 'America/Panama';
        $hour = (int) now($tz)->format('G');
        if ($hour < 8 || $hour >= 20) {
            Log::info("Reenvío omitido por horario ({$hour}h Panama) para cliente {$from}");
            return;
        }

        Log::info("Inbound de {$from} -> cliente ".($client?->id ?? 'null')." tarea ".($task?->id ?? 'null')." conv {$conversation->id} assigned_to ".($task?->assigned_to ?? $conversation->assigned_to ?? 'null'));
        $this->forwardToAssignedAgent($message, $client, $company, $task, $conversation, $from);
    }

    protected function forwardToAssignedAgent(
        WhatsAppMessage $message,
        ?Client $client,
        ?Company $company,
        ?Task $task,
        Conversation $conversation,
        string $from
    ): void {
        // Agente asignado a la tarea/conversación
        $agentId = $task?->assigned_to ?? $conversation->assigned_to;
        if (!$agentId) return;
        $agent = User::find($agentId);
        if (!$agent || empty($agent->phone)) return;

        $agentPhone = '+' . ltrim(preg_replace('/\D/', '', $agent->phone), '+');
        $agentPhone = preg_replace('/^\+?0+/', '+', $agentPhone);
        if (!str_starts_with($agentPhone, '+507')) {
            $digits = preg_replace('/\D/', '', $agentPhone);
            $digits = ltrim($digits, '0');
            if (!str_starts_with($digits, '507')) $digits = '507' . $digits;
            $agentPhone = '+' . $digits;
        }

        $normalizedFrom = '+' . preg_replace('/\D/', '', $from);
        if ($agentPhone === $normalizedFrom) return; // no reenviar al mismo cliente

        // Evitar bucle si el agente es el número central (WABA)
        $central = config('services.whatsapp.phone_number_id');
        if ($central && $agentPhone === $central) return;

        try {
            $service = app(WhatsAppService::class);
            $clientLabel = $client?->full_name ?? $conversation->contact_name ?? $from;
            $cuenta = $client?->order_number ?? $client?->metadata['cuenta'] ?? '—';
            $empresa = $company?->name ?? '—';
            $texto = "📩 *Nueva respuesta de cliente*\n\n"
                . "*Agente asignado:* {$agent->name}\n"
                . "*Cliente:* {$clientLabel}\n"
                . "*Cuenta:* {$cuenta} | *Empresa:* {$empresa} | *Tel:* {$from}\n"
                . "*Mensaje:* " . ($message->body ?: 'Archivo recibido') . "\n\n"
                . "— Responde directo a este cliente desde tu WhatsApp personal.";

            $service->sendTextReply($agentPhone, $texto);
            Log::info("Reenvío al agente {$agent->name} ({$agentPhone}) por cliente {$from}");
        } catch (\Throwable $e) {
            Log::warning('Fallo reenvío al agente: ' . $e->getMessage());
        }
    }

    public function handleDelivered(array $data): void
    {
        $messageId = $data['messageId'] ?? null;
        if ($messageId) {
            WhatsAppMessage::where('message_id', $messageId)->where('direction', 'outbound')->first()?->markDelivered();
        }
    }

    public function handleFailed(array $data): void
    {
        $messageId = $data['messageId'] ?? null;
        if ($messageId) {
            WhatsAppMessage::where('message_id', $messageId)->where('direction', 'outbound')->first()?->markFailed(
                $data['error'] ?? ($data['message'] ?? 'Error de entrega')
            );
        }
    }

    /**
     * Convierte un mensaje de formato Meta al formato interno
     */
    private function mapInbound(array $msg, array $value): array
    {
        $type = $msg['type'] ?? 'text';
        $content = null;
        $text = null;

        switch ($type) {
            case 'text':
                $text = $msg['text']['body'] ?? null;
                break;
            case 'image':
            case 'video':
            case 'audio':
            case 'document':
            case 'sticker':
                $content = ['mimeType' => $msg[$type]['mime_type'] ?? null,
                            'filename' => $msg[$type]['filename'] ?? null,
                            'caption' => $msg[$type]['caption'] ?? null];
                if (!empty($msg[$type]['caption'])) $text = $msg[$type]['caption'];
                break;
            case 'location':
                $content = ['latitude' => $msg['location']['latitude'] ?? null,
                            'longitude' => $msg['location']['longitude'] ?? null,
                            'name' => $msg['location']['name'] ?? null];
                break;
            case 'button':
                $text = $msg['button']['text'] ?? null;
                break;
            case 'interactive':
                $content = ['interactiveReply' => ['title' => $msg['interactive']['button_reply']['title']
                    ?? ($msg['interactive']['list_reply']['title'] ?? null)]];
                $text = $content['interactiveReply']['title'];
                break;
            case 'reaction':
                $content = ['reaction' => $msg['reaction']['emoji'] ?? null];
                $text = $content['reaction'];
                break;
        }

        $profileName = $value['contacts'][0]['profile']['name'] ?? null;

        return [
            'from' => '+' . preg_replace('/\D/', '', $msg['from'] ?? ''),
            'to' => '+' . preg_replace('/\D/', '', $value['metadata']['display_phone_number'] ?? ''),
            'messageId' => $msg['id'] ?? null,
            'text' => $text,
            'content' => $content,
            'profileName' => $profileName,
            'channel' => 'whatsapp',
            'providerTimestamp' => isset($msg['timestamp']) ? ((int) $msg['timestamp']) * 1000 : round(microtime(true) * 1000),
        ];
    }

    protected function describeContent(?array $content): string
    {
        if (!$content) return 'Mensaje recibido';
        if (!empty($content['interactiveReply']['title'])) {
            return 'Respuesta: ' . $content['interactiveReply']['title'];
        }
        if (!empty($content['filename'])) return 'Documento: ' . $content['filename'];
        if (isset($content['latitude'], $content['longitude'])) {
            return "Ubicación: {$content['latitude']}, {$content['longitude']}";
        }
        if (!empty($content['name'])) return 'Ubicación: ' . $content['name'];
        if (!empty($content['mimeType'])) return 'Archivo recibido';
        return 'Mensaje recibido';
    }
}