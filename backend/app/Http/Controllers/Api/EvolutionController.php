<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Gestión de tu Evolution API propia (Baileys self-hosted):
 * crear instancia, obtener QR, ver estado y apuntar el webhook a este backend.
 * Todo queda en tu infraestructura, ningún tercero ve tus mensajes.
 */
class EvolutionController extends Controller
{
    private function instance(Request $request): string
    {
        return trim((string) ($request->input('instance') ?: config('services.evolution.instance', 'net-recovery')));
    }

    /** Crea la instancia si no existe, configura el webhook y devuelve el QR para parear. */
    public function connect(Request $request)
    {
        $instance = $this->instance($request);
        if (!WhatsAppService::globalEvolutionConfigured()) {
            return response()->json(['message' => 'Evolution no configurada: define EVOLUTION_BASE_URL y EVOLUTION_API_KEY.'], 422);
        }

        // 1. Crear instancia (si ya existe, Evolution responde 403/409 y seguimos).
        WhatsAppService::evolutionRequest('POST', '/instance/create', [
            'instanceName' => $instance,
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS',
        ]);

        // 2. Apuntar el webhook de Evolution a este backend.
        $this->configureWebhook($request, $instance);

        // 3. Pedir QR / estado de conexión.
        return $this->qr($request);
    }

    /** Devuelve el QR (base64) para escanear con el WhatsApp del negocio + estado. */
    public function qr(Request $request)
    {
        $instance = $this->instance($request);
        if (!WhatsAppService::globalEvolutionConfigured()) {
            return response()->json(['message' => 'Evolution no configurada.'], 422);
        }

        $state = WhatsAppService::evolutionRequest('GET', "/instance/connectionState/{$instance}");
        $stateName = strtolower((string) ($state['body']['instance']['state'] ?? $state['body']['state'] ?? ''));
        if ($stateName === 'open') {
            Cache::put('evolution_last_ok:' . $instance, now()->toISOString(), 3600);
            return response()->json(['connected' => true, 'state' => 'open', 'instance' => $instance]);
        }

        $conn = WhatsAppService::evolutionRequest('GET', "/instance/connect/{$instance}");
        $body = $conn['body'] ?? [];
        $base64 = $body['base64'] ?? $body['qrcode']['base64'] ?? null;
        if ($base64) {
            Cache::put('evolution_qr:' . $instance, $base64, 120);
        }

        return response()->json([
            'connected' => false,
            'state' => $stateName ?: 'connecting',
            'instance' => $instance,
            'qr_base64' => $base64, // data:image/png;base64,... listo para <img src="...">
            'qr_expires_in' => 120,
            'hint' => 'Escanea el QR con WhatsApp > Dispositivos vinculados > Vincular dispositivo.',
        ]);
    }

    /** Estado de conexión de la instancia. */
    public function connectionState(Request $request)
    {
        $instance = $this->instance($request);
        if (!WhatsAppService::globalEvolutionConfigured()) {
            return response()->json(['message' => 'Evolution no configurada.'], 422);
        }
        $state = WhatsAppService::evolutionRequest('GET', "/instance/connectionState/{$instance}");
        $stateName = strtolower((string) ($state['body']['instance']['state'] ?? $state['body']['state'] ?? 'unknown'));
        return response()->json(['instance' => $instance, 'state' => $stateName, 'connected' => $stateName === 'open']);
    }

    /** Cierra la sesión del teléfono (requiere re-escanear QR después). */
    public function logout(Request $request)
    {
        $instance = $this->instance($request);
        $res = WhatsAppService::evolutionRequest('DELETE', "/instance/logout/{$instance}");
        return response()->json(['instance' => $instance, 'ok' => $res['ok'], 'response' => $res['body']]);
    }

    private function configureWebhook(Request $request, string $instance): void
    {
        $secret = config('services.evolution.webhook_secret');
        $url = rtrim((string) ($request->input('webhook_url') ?: config('app.url')), '/')
            . '/api/v1/whatsapp/evolution/webhook?token=' . urlencode((string) $secret);
        WhatsAppService::evolutionRequest('POST', "/webhook/set/{$instance}", [
            'url' => $url,
            'enabled' => true,
            'events' => ['MESSAGES_UPSERT', 'CONNECTION_UPDATE', 'QRCODE_UPDATED', 'SEND_MESSAGE'],
        ]);
    }
}
