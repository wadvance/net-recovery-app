<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Task;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZavuWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $rawBody = $request->getContent();

        if (!$this->verifySignature($request->header('X-Zavu-Signature', ''), $rawBody)) {
            Log::warning('Zavu webhook: firma invalida');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawBody, true);
        $eventType = $payload['type'] ?? null;

        try {
            switch ($eventType) {
                case 'message.inbound':
                    $this->handleInbound($payload['data'] ?? []);
                    break;
                case 'message.delivered':
                    $this->handleDelivered($payload['data'] ?? []);
                    break;
                case 'message.failed':
                    $this->handleFailed($payload['data'] ?? []);
                    break;
            }
        } catch (\Throwable $e) {
            Log::error('Zavu webhook error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleInbound(array $data): void
    {
        $from = $data['from'] ?? null;
        if (!$from) return;

        $normalized = preg_replace('/\D/', '', $from);
        if (strlen($normalized) > 10) {
            $normalized = substr($normalized, -10);
        }

        $client = Client::with('company')->where('phone', 'like', "%{$normalized}")->first()
            ?? Client::where('alternate_phone', 'like', "%{$normalized}")->first();

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
            'to_phone' => $data['to'] ?? config('services.zavu.sender'),
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
    }

    protected function handleDelivered(array $data): void
    {
        $messageId = $data['messageId'] ?? null;
        if ($messageId) {
            WhatsAppMessage::where('message_id', $messageId)->where('direction', 'outbound')->first()?->markDelivered();
        }
    }

    protected function handleFailed(array $data): void
    {
        $messageId = $data['messageId'] ?? null;
        if ($messageId) {
            WhatsAppMessage::where('message_id', $messageId)->where('direction', 'outbound')->first()?->markFailed(
                $data['error'] ?? ($data['message'] ?? 'Error de entrega')
            );
        }
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

    protected function verifySignature(string $header, string $rawBody): bool
    {
        $secret = config('services.zavu.webhook_secret');
        if (!$secret) {
            Log::warning('Zavu webhook: no hay ZAVU_WEBHOOK_SECRET configurado');
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $piece) {
            $i = strpos($piece, '=');
            if ($i !== false) {
                $parts[substr($piece, 0, $i)] = substr($piece, $i + 1);
            }
        }

        $timestamp = $parts['t'] ?? null;
        $received = $parts['v2'] ?? ($parts['v1'] ?? null);
        if (!$timestamp || !$received) return false;

        if (abs(time() - (int) $timestamp) > 300) return false;

        $signedPayload = isset($parts['v2'])
            ? $timestamp . '.' . $rawBody
            : $rawBody;

        $expected = hash_hmac('sha256', $signedPayload, $secret);
        return hash_equals($expected, $received);
    }
}