<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de tu Evolution API propia.
 * Evolution firma con ?token= (EVOLUTION_WEBHOOK_SECRET) que configuramos
 * desde EvolutionController@connect. Los entrantes se reutilizan en el
 * formato interno vía MetaWebhookController (conversaciones + reenvío al agente).
 */
class EvolutionWebhookController extends Controller
{
    public function receive(Request $request)
    {
        $expected = (string) config('services.evolution.webhook_secret');
        $given = (string) ($request->query('token') ?? $request->header('x-evolution-secret') ?? '');
        if ($expected !== '' && !hash_equals($expected, $given)) {
            Log::warning('Evolution webhook: token inválido');
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $event = strtoupper((string) ($request->input('event') ?? ''));
        $instance = (string) ($request->input('instance') ?? '');
        $data = $request->input('data') ?? [];

        try {
            match ($event) {
                'MESSAGES_UPSERT' => $this->handleMessagesUpsert($data),
                'CONNECTION_UPDATE', 'QRCODE_UPDATED' => $this->handleConnection($instance, $event, $data),
                'SEND_MESSAGE' => $this->handleSendStatus($data),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::error('Evolution webhook error: ' . $e->getMessage());
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleMessagesUpsert(array $data): void
    {
        $key = $data['key'] ?? [];
        $fromMe = (bool) ($key['fromMe'] ?? false);
        $remoteJid = (string) ($key['remoteJid'] ?? '');
        if (str_ends_with($remoteJid, '@g.us')) return; // ignorar grupos
        $number = preg_replace('/\D/', '', explode('@', $remoteJid)[0] ?? '');
        if ($number === '') return;

        $message = $data['message'] ?? [];
        $text = $message['conversation']
            ?? $message['extendedTextMessage']['text']
            ?? $message['imageMessage']['caption']
            ?? $message['videoMessage']['caption']
            ?? null;
        $pushName = $data['pushName'] ?? null;
        $messageId = $key['id'] ?? null;

        /** @var MetaWebhookController $meta */
        $meta = app(MetaWebhookController::class);
        if ($fromMe) {
            if ($messageId) $meta->handleDelivered(['messageId' => $messageId]);
            return;
        }
        $meta->handleInbound([
            'from' => '+' . $number,
            'to' => '',
            'messageId' => $messageId,
            'text' => $text,
            'content' => $text ? null : ['evolution' => true],
            'profileName' => $pushName,
            'channel' => 'whatsapp',
            'providerTimestamp' => isset($data['messageTimestamp'])
                ? ((int) $data['messageTimestamp']) * 1000
                : round(microtime(true) * 1000),
        ]);
    }

    private function handleConnection(string $instance, string $event, array $data): void
    {
        $state = strtolower((string) ($data['state'] ?? $data['status'] ?? ''));
        if ($state !== '') {
            Cache::put('evolution_state:' . $instance, $state, 600);
        }
        if ($event === 'CONNECTION_UPDATE' && $state === 'open' && $instance !== '') {
            Cache::put('evolution_last_ok:' . $instance, now()->toISOString(), 3600);
        }
        if ($event === 'QRCODE_UPDATED' && $instance !== '' && !empty($data['qrcode']['base64'])) {
            Cache::put('evolution_qr:' . $instance, $data['qrcode']['base64'], 120);
        }
    }

    private function handleSendStatus(array $data): void
    {
        $messageId = $data['key']['id'] ?? $data['id'] ?? null;
        $status = strtolower((string) ($data['status'] ?? ''));
        if (!$messageId) return;
        /** @var MetaWebhookController $meta */
        $meta = app(MetaWebhookController::class);
        if (in_array($status, ['delivered', 'read', 'sent', 'server_ack'], true)) {
            $meta->handleDelivered(['messageId' => $messageId]);
        } elseif ($status === 'failed' || !empty($data['error'])) {
            $meta->handleFailed(['messageId' => $messageId, 'error' => (string) ($data['error'] ?? 'Error de entrega')]);
        }
    }
}
