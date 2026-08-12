<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Support\Facades\Http;

/**
 * Envio de notificaciones al cliente a traves del proveedor configurado
 * (Zavu, con fallback a texto libre; Meta Graph API como alternativa).
 * Centraliza la logica para que tanto WhatsAppController como el
 * procesador de Excel puedan reutilizarla.
 */
class WhatsAppService
{
    public function sendToClient(Client $client, Company $company, ?string $templateName = null): array
    {
        $to = '+' . $client->formatted_phone;
        if (!$to || $to === '+') {
            return $this->result(false, null, 'Cliente sin telefono');
        }

        $provider = $this->provider();
        if (!$provider) {
            return $this->result(false, null, 'WhatsApp API no configurada');
        }

        if ($provider === 'zavu') {
            return $this->sendViaZavu($to, $client, $company, $templateName);
        }

        return $this->result(false, null, 'Proveedor Meta no implementado en el servicio');
    }

    private function sendViaZavu(string $to, Client $client, Company $company, ?string $templateName): array
    {
        $key = config('services.zavu.key');
        $baseUrl = config('services.zavu.base_url', 'https://api.zavu.dev');

        $request = Http::withToken($key)->baseUrl($baseUrl)->timeout(30);

        $headers = ['Content-Type' => 'application/json'];
        if (config('services.zavu.sender')) {
            $headers['Zavu-Sender'] = config('services.zavu.sender');
        }

        $templateId = $templateName ? config("services.whatsapp_templates.{$templateName}") : null;

        if ($templateId) {
            $params = $this->buildParams($client, $company);
            $vars = [];
            foreach (array_values($params) as $i => $v) {
                $vars[(string) ($i + 1)] = (string) $v;
            }
            $payload = [
                'to' => $to,
                'messageType' => 'template',
                'content' => [
                    'templateId' => $templateId,
                    'templateVariables' => $vars,
                ],
            ];
        } else {
            $payload = [
                'to' => $to,
                'channel' => 'whatsapp',
                'messageType' => 'text',
                'text' => $this->fallbackText($client, $company),
            ];
        }

        try {
            $response = $request->withHeaders($headers)->post('/v1/messages', $payload);
            $body = $response->json();
            if ($response->successful() && isset($body['message']['id'])) {
                return $this->result(true, $body['message']['id'], null, $body);
            }
            $error = is_array($body)
                ? ($body['message'] ?? ($body['error'] ?? json_encode($body)))
                : $response->body();
            return $this->result(false, null, $error, $body ?? []);
        } catch (\Throwable $e) {
            return $this->result(false, null, $e->getMessage(), []);
        }
    }

    private function provider(): ?string
    {
        if (config('services.zavu.key')) {
            return 'zavu';
        }
        if (config('services.whatsapp.token') && config('services.whatsapp.phone_number_id')) {
            return 'meta';
        }
        return null;
    }

    private function buildParams(Client $client, Company $company): array
    {
        return [
            'nombre_cliente' => $client->full_name,
            'empresa' => $company?->name ?? 'nuestra empresa',
            'numero_pedido' => $client->order_number,
            'direccion' => $client->address,
            'telefono' => $client->phone,
        ];
    }

    private function fallbackText(Client $client, Company $company): string
    {
        $params = $this->buildParams($client, $company);
        return sprintf(
            "Estimado %s, le informamos sobre su gestion de recuperacion de equipos en %s. Pedido: %s. Direccion: %s. Telefono: %s.",
            $params['nombre_cliente'],
            $params['empresa'],
            $params['numero_pedido'],
            $params['direccion'],
            $params['telefono']
        );
    }

    private function result(bool $ok, ?string $messageId, ?string $error, array $response = []): array
    {
        return ['ok' => $ok, 'messageId' => $messageId, 'error' => $error, 'response' => $response];
    }
}
