<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Support\Facades\Http;

/**
 * Envio de notificaciones al cliente a traves del proveedor configurado
 * (Meta Graph API con fallback a Twilio).
 * Centraliza la logica para que tanto WhatsAppController como el
 * procesador de Excel puedan reutilizarla.
 */
class WhatsAppService
{
    public function sendToClient(Client $client, Company $company, ?string $templateName = null, ?string $senderId = null): array
    {
        $to = '+' . $client->formatted_phone;
        if (!$to || $to === '+') {
            return $this->result(false, null, 'Cliente sin telefono');
        }

        $provider = $this->provider();
        if (!$provider) {
            return $this->result(false, null, 'WhatsApp API no configurada');
        }

        if ($provider === 'meta') {
            return $this->sendViaMeta($to, $client, $company, $templateName);
        }

        if ($provider === 'twilio') {
            return $this->sendViaTwilio($to, $client, $company, $templateName);
        }

        return $this->result(false, null, 'WhatsApp API no configurada');
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

    public function sendTextReply(string $to, string $text, ?string $templateName = null): array
    {
        $to = '+' . ltrim($to, '+');
        if (!$to || $to === '+') {
            return $this->result(false, null, 'Destino sin telefono');
        }

        $provider = $this->provider();
        if (!$provider) {
            return $this->result(false, null, 'WhatsApp API no configurada');
        }

        if ($provider === 'twilio') {
            return $this->sendTextViaTwilio($to, $text);
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

    private function provider(): ?string
    {
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

    private function result(bool $ok, ?string $messageId, ?string $error, array $response = []): array
    {
        return ['ok' => $ok, 'messageId' => $messageId, 'error' => $error, 'response' => $response];
    }
}