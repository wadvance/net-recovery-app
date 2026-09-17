<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Envio de notificaciones al cliente a traves del proveedor configurado
 * (Meta Graph API con fallback a Twilio).
 * Centraliza la logica para que tanto WhatsAppController como el
 * procesador de Excel puedan reutilizarla.
 */
class WhatsAppService
{
    public function sendToClient(Client $client, Company $company, ?string $templateName = null, ?string $senderId = null, ?User $user = null): array
    {
        $to = '+' . $client->formatted_phone;
        if (!$to || $to === '+') {
            return $this->result(false, null, 'Cliente sin telefono');
        }

        $provider = $this->provider($user);
        if (!$provider) {
            return $this->result(false, null, 'WhatsApp API no configurada');
        }

        if ($provider === 'zavu') {
            return $this->sendViaZavu($to, $client, $company, $templateName, $user);
        }

        if ($provider === 'ycloud') {
            return $this->sendViaYCloud($to, $client, $company, $templateName, $user);
        }

        if ($provider === 'meta') {
            return $this->sendViaMeta($to, $client, $company, $templateName);
        }

        if ($provider === 'twilio') {
            return $this->sendViaTwilio($to, $client, $company, $templateName);
        }

        return $this->result(false, null, 'WhatsApp API no configurada');
    }

    private function sendViaYCloud(string $to, Client $client, Company $company, ?string $templateName, ?User $user = null): array
    {
        $apiKey = $user?->settings['whatsapp_api_key'] ?? config('services.ycloud.api_key');
        $fromNumber = $user?->settings['whatsapp_phone_number'] ?? config('services.ycloud.phone_number');
        if (!$apiKey || !$fromNumber) {
            return $this->result(false, null, 'YCloud no configurado');
        }
        $baseUrl = rtrim(config('services.ycloud.base_url', 'https://api.ycloud.com'), '/');
        $params = $this->buildParams($client, $company);
        $payload = [
            'from' => $fromNumber,
            'to' => $to,
            'type' => $templateName ? 'template' : 'text',
        ];
        if ($templateName) {
            $payload['template'] = [
                'name' => $templateName,
                'language' => ['code' => 'es'],
                'components' => [[
                    'type' => 'body',
                    'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], array_values($params)),
                ]],
            ];
        } else {
            $payload['text'] = ['body' => $this->fallbackText($client, $company)];
        }
        try {
            $response = Http::timeout(30)
                ->withHeaders(['X-API-Key' => $apiKey, 'Content-Type' => 'application/json'])
                ->post("{$baseUrl}/v2/whatsapp/messages", $payload);
            $body = $response->json();
            if ($response->ok() && isset($body['id'])) {
                return $this->result(true, $body['id'], null, $body);
            }
            $rawError = is_array($body) ? ($body['error']['message'] ?? ($body['message'] ?? $body)) : $response->body();
            $error = is_string($rawError) ? $rawError : json_encode($rawError, JSON_UNESCAPED_UNICODE);
            if ($templateName && str_contains(strtolower($error), 'template') && (str_contains(strtolower($error), 'pending') || str_contains(strtolower($error), 'unavailable') || str_contains(strtolower($error), 'not found'))) {
                $fallback = ['from' => $fromNumber, 'to' => $to, 'type' => 'text', 'text' => ['body' => $this->fallbackText($client, $company)]];
                $r2 = Http::timeout(30)->withHeaders(['X-API-Key' => $apiKey, 'Content-Type' => 'application/json'])->post("{$baseUrl}/v2/whatsapp/messages", $fallback);
                $b2 = $r2->json();
                if ($r2->ok() && isset($b2['id'])) return $this->result(true, $b2['id'], null, $b2);
            }
            return $this->result(false, null, $error, is_array($body) ? $body : []);
        } catch (\Throwable $e) {
            return $this->result(false, null, $e->getMessage(), []);
        }
    }

    private function sendViaMeta(string $to, Client $client, Company $company, ?string $templateName): array
    {
        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        if (!$token || !$phoneNumberId) {
            return $this->result(false, null, 'Meta Cloud API no configurada');
        }

        $version = config('services.whatsapp.version', 'v21.0');
        $baseUrl = config('services.whatsapp.base_url', 'https://graph.facebook.com');

        $params = $this->buildParams($client, $company);
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $client->formatted_phone,
            'type' => $templateName ? 'template' : 'text',
        ];

        if ($templateName) {
            $payload['template'] = [
                'name' => $templateName,
                'language' => ['code' => 'es'],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], array_values($params)),
                    ],
                ],
            ];
        } else {
            $payload['text'] = ['body' => $this->fallbackText($client, $company)];
        }

        try {
            $response = Http::timeout(30)
                ->baseUrl($baseUrl)
                ->withToken($token)
                ->post("/{$version}/{$phoneNumberId}/messages", $payload);
            $body = $response->json();
            if ($response->ok() && isset($body['messages'][0]['id'])) {
                return $this->result(true, $body['messages'][0]['id'], null, $body);
            }
            $rawError = is_array($body)
                ? ($body['error']['message'] ?? ($body['message'] ?? $body))
                : $response->body();
            $error = is_string($rawError) ? $rawError : json_encode($rawError, JSON_UNESCAPED_UNICODE);
            return $this->result(false, null, $error, is_array($body) ? $body : []);
        } catch (\Throwable $e) {
            return $this->result(false, null, $e->getMessage(), []);
        }
    }

    private function sendViaTwilio(string $to, Client $client, Company $company, ?string $templateName): array
    {
        $sid = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');
        $from = config('services.twilio.from');
        if (!$sid || !$token || !$from) {
            return $this->result(false, null, 'Twilio no configurado');
        }
        if (!str_starts_with($from, 'whatsapp:')) {
            $from = 'whatsapp:' . $from;
        }

        $data = [
            'From' => $from,
            'To' => 'whatsapp:' . $to,
        ];

        $contentSid = $templateName ? config("services.twilio_content_templates.{$templateName}") : null;
        if ($contentSid) {
            $params = $this->buildParams($client, $company);
            $vars = [];
            foreach (array_values($params) as $i => $v) {
                $vars[(string) ($i + 1)] = (string) $v;
            }
            $data['ContentSid'] = $contentSid;
            $data['ContentVariables'] = json_encode($vars);
        } else {
            $data['Body'] = $this->fallbackText($client, $company);
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($sid, $token)
                ->timeout(30)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", $data);
            $body = $response->json();
            if ($response->successful() && isset($body['sid'])) {
                return $this->result(true, $body['sid'], null, $body);
            }
            $rawError = is_array($body)
                ? ($body['message'] ?? ($body['error_message'] ?? $body))
                : $response->body();
            $error = is_string($rawError) ? $rawError : json_encode($rawError, JSON_UNESCAPED_UNICODE);
            return $this->result(false, null, $error, is_array($body) ? $body : []);
        } catch (\Throwable $e) {
            return $this->result(false, null, $e->getMessage(), []);
        }
    }

    public function sendTextReply(string $to, string $text, ?string $templateName = null, ?User $user = null): array
    {
        $to = '+' . ltrim($to, '+');
        if (!$to || $to === '+') {
            return $this->result(false, null, 'Destino sin telefono');
        }

        $provider = $this->provider($user);
        if (!$provider) {
            return $this->result(false, null, 'WhatsApp API no configurada');
        }

        if ($provider === 'zavu') {
            return $this->sendTextViaZavu($to, $text, $user);
        }

        if ($provider === 'twilio') {
            return $this->sendTextViaTwilio($to, $text);
        }

        if ($provider === 'ycloud') {
            return $this->sendTextViaYCloud($to, $text, $user);
        }

        if ($provider === 'meta') {
            return $this->sendTextViaMeta($to, $text);
        }

        return $this->result(false, null, 'WhatsApp API no configurada');
    }

    private function sendTextViaTwilio(string $to, string $text): array
    {
        $sid = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');
        $from = config('services.twilio.from');
        if (!$sid || !$token || !$from) {
            return $this->result(false, null, 'Twilio no configurado');
        }
        if (!str_starts_with($from, 'whatsapp:')) {
            $from = 'whatsapp:' . $from;
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($sid, $token)
                ->timeout(30)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $from,
                    'To' => 'whatsapp:' . $to,
                    'Body' => $text,
                ]);
            $body = $response->json();
            if ($response->successful() && isset($body['sid'])) {
                return $this->result(true, $body['sid'], null, $body);
            }
            $rawError = is_array($body)
                ? ($body['message'] ?? ($body['error_message'] ?? $body))
                : $response->body();
            $error = is_string($rawError) ? $rawError : json_encode($rawError, JSON_UNESCAPED_UNICODE);
            return $this->result(false, null, $error, is_array($body) ? $body : []);
        } catch (\Throwable $e) {
            return $this->result(false, null, $e->getMessage(), []);
        }
    }

    private function sendTextViaYCloud(string $to, string $text, ?User $user = null): array
    {
        $apiKey = $user?->settings['whatsapp_api_key'] ?? config('services.ycloud.api_key');
        $fromNumber = $user?->settings['whatsapp_phone_number'] ?? config('services.ycloud.phone_number');
        if (!$apiKey || !$fromNumber) {
            return $this->result(false, null, 'YCloud no configurado');
        }
        $baseUrl = rtrim(config('services.ycloud.base_url', 'https://api.ycloud.com'), '/');
        try {
            $response = Http::timeout(30)
                ->withHeaders(['X-API-Key' => $apiKey, 'Content-Type' => 'application/json'])
                ->post("{$baseUrl}/v2/whatsapp/messages", [
                    'from' => $fromNumber,
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['body' => $text],
                ]);
            $body = $response->json();
            if ($response->ok() && isset($body['id'])) {
                return $this->result(true, $body['id'], null, $body);
            }
            $rawError = is_array($body) ? ($body['error']['message'] ?? ($body['message'] ?? $body)) : $response->body();
            $error = is_string($rawError) ? $rawError : json_encode($rawError, JSON_UNESCAPED_UNICODE);
            return $this->result(false, null, $error, is_array($body) ? $body : []);
        } catch (\Throwable $e) {
            return $this->result(false, null, $e->getMessage(), []);
        }
    }

    private function sendTextViaMeta(string $to, string $text): array
    {
        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        if (!$token || !$phoneNumberId) {
            return $this->result(false, null, 'Meta Cloud API no configurada');
        }

        $version = config('services.whatsapp.version', 'v21.0');
        $baseUrl = config('services.whatsapp.base_url', 'https://graph.facebook.com');

        try {
            $response = Http::timeout(30)
                ->baseUrl($baseUrl)
                ->withToken($token)
                ->post("/{$version}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => ltrim($to, '+'),
                    'type' => 'text',
                    'text' => ['body' => $text],
                ]);
            $body = $response->json();
            if ($response->ok() && isset($body['messages'][0]['id'])) {
                return $this->result(true, $body['messages'][0]['id'], null, $body);
            }
            $rawError = is_array($body)
                ? ($body['error']['message'] ?? ($body['message'] ?? $body))
                : $response->body();
            $error = is_string($rawError) ? $rawError : json_encode($rawError, JSON_UNESCAPED_UNICODE);
            return $this->result(false, null, $error, is_array($body) ? $body : []);
        } catch (\Throwable $e) {
            return $this->result(false, null, $e->getMessage(), []);
        }
    }

    private function provider(?User $user = null): ?string
    {
        // Si el usuario tiene credenciales propias, usarlas
        if ($user && $user->settings['whatsapp_api_key'] ?? null) {
            return 'ycloud';
        }
        // YCloud tiene prioridad global si está configurado
        if (config('services.ycloud.api_key') && config('services.ycloud.phone_number_id')) {
            return 'ycloud';
        }
        if (config('services.zavu.api_key')) {
            return 'zavu';
        }
        if (config('services.whatsapp.token') && config('services.whatsapp.phone_number_id')) {
            return 'meta';
        }
        if (config('services.twilio.account_sid') && config('services.twilio.auth_token')) {
            return 'twilio';
        }
        return null;
    }

    private function buildParams(Client $client, Company $company): array
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

    private function fallbackText(Client $client, Company $company): string
    {
        $params = $this->buildParams($client, $company);
        return sprintf(
            "Estimado(a) cliente: Le informamos que el Departamento de Recuperación de Equipos de %s se comunicara con usted respecto al pedido #%s. Nos puede proporcionar por este medio su ubicación en tiempo actual por WhatsApp para retirar los equipos. Un agente se acercará a la dirección registrada. Por favor manténgase atento/a a su teléfono. Gracias.",
            $params['empresa'],
            $params['numero_pedido']
        );
    }

    private function sendViaZavu(string $to, Client $client, Company $company, ?string $templateName, ?User $user = null): array
    {
        $apiKey = $user?->settings['whatsapp_api_key'] ?? config('services.zavu.api_key');
        $senderId = $user?->phone ?? $user?->settings['whatsapp_sender_id'] ?? config('services.zavu.sender_id');
        $baseUrl = config('services.zavu.base_url', 'https://api.zavu.dev');
        if (!$apiKey) {
            return $this->result(false, null, 'Zavu no configurado');
        }
        $text = $this->fallbackText($client, $company);
        $phone = ltrim($to, '+');
        $params = $this->buildParams($client, $company);
        $phone = '+'.ltrim($to, '+');
        $templateId = config('services.zavu.template_id');
        try {
            $headers = ['Authorization' => 'Bearer '.$apiKey, 'Content-Type' => 'application/json'];
            $payload = ['to' => $phone, 'channel' => 'whatsapp'];
            if ($templateId) {
                $payload['messageType'] = 'template';
                $payload['content'] = ['templateId' => $templateId, 'templateVariables' => [
                    '1' => $params['nombre_cliente'], '2' => $params['empresa'], '3' => $params['numero_pedido'], '4' => $params['direccion'], '5' => $params['telefono'],
                ]];
            } else {
                $payload['text'] = $this->fallbackText($client, $company);
            }
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->withHeaders($headers)
                ->post(rtrim($baseUrl, '/').'/v1/messages', $payload);
            $body = $response->json();
            if ($response->successful() && isset($body['message']['id'])) {
                return $this->result(true, $body['message']['id'], null, is_array($body) ? $body : []);
            }
            if ($response->successful() && isset($body['id'])) {
                return $this->result(true, $body['id'], null, is_array($body) ? $body : []);
            }
            $rawError = is_array($body) ? ($body['message'] ?? $body['error'] ?? json_encode($body)) : $response->body();
            return $this->result(false, null, is_string($rawError) ? $rawError : json_encode($rawError), is_array($body) ? $body : []);
        } catch (\Throwable $e) {
            return $this->result(false, null, $e->getMessage(), []);
        }
    }

    private function sendTextViaZavu(string $to, string $text, ?User $user = null): array
    {
        $apiKey = $user?->settings['whatsapp_api_key'] ?? config('services.zavu.api_key');
        $baseUrl = config('services.zavu.base_url', 'https://api.zavu.dev');
        $phone = '+'.ltrim($to, '+');
        try {
            $headers = ['Authorization' => 'Bearer '.$apiKey, 'Content-Type' => 'application/json'];
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->withHeaders($headers)
                ->post(rtrim($baseUrl, '/').'/v1/messages', [
                    'to' => $phone,
                    'text' => $text,
                    'channel' => 'whatsapp',
                ]);
            $body = $response->json();
            if ($response->successful() && isset($body['message']['id'])) {
                return $this->result(true, $body['message']['id'], null, is_array($body) ? $body : []);
            }
            if ($response->successful() && isset($body['id'])) {
                return $this->result(true, $body['id'], null, is_array($body) ? $body : []);
            }
            $rawError = is_array($body) ? ($body['message'] ?? $body['error'] ?? json_encode($body)) : $response->body();
            return $this->result(false, null, is_string($rawError) ? $rawError : json_encode($rawError), is_array($body) ? $body : []);
        } catch (\Throwable $e) {
            return $this->result(false, null, $e->getMessage(), []);
        }
    }

    private function result(bool $ok, ?string $messageId, ?string $error, array $response = []): array
    {
        return ['ok' => $ok, 'messageId' => $messageId, 'error' => $error, 'response' => $response];
    }

    /**
     * Obtiene la configuración de WhatsApp que se usaría para un usuario dado.
     * Útil para mostrar en el panel qué número se usará para enviar.
     */
    public function getUserWhatsAppConfig(?User $user): array
    {
        if ($user && ($user->settings['whatsapp_api_key'] ?? null)) {
            return [
                'provider' => 'ycloud',
                'api_key' => $user->settings['whatsapp_api_key'],
                'phone_number_id' => $user->settings['whatsapp_phone_number_id'] ?? null,
                'source' => 'user',
            ];
        }
        $provider = $this->provider();
        return [
            'provider' => $provider,
            'api_key' => $provider ? 'configured' : null,
            'phone_number_id' => null,
            'source' => 'global',
        ];
    }
}