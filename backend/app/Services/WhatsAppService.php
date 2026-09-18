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
    /**
     * Mensaje estándar cuando el usuario no tiene sesión de YCloud.
     * Cada usuario necesita su propia sesión en ycloud.com para que los
     * mensajes masivos salgan desde su número y lleguen a los clientes.
     */
    public const MISSING_SESSION_MESSAGE = 'Tu usuario no tiene sesión de YCloud configurada. Crea tu sesión en https://ycloud.com, conecta tu número de WhatsApp Business y pega tu API Key + número remitente en Usuarios > Editar. Cada usuario debe tener su propia sesión para que los mensajes masivos lleguen a los clientes.';

    /**
     * Mensaje cuando el usuario no tiene ninguna sesión válida para masivos
     * (ni YCloud ni Zavu). Cada usuario necesita su propia cuenta.
     */
    public const BULK_NO_SESSION_MESSAGE = 'Tu usuario no tiene sesión de WhatsApp configurada para envíos masivos. Registra tu API Key en Usuarios > Editar: si usas Zavu basta tu API Key de tu cuenta Zavu; si usas YCloud agrega además tu número remitente. Cada usuario debe tener su propia cuenta para que los mensajes lleguen a los clientes.';

    /**
     * ¿El usuario tiene sesión propia de YCloud completa?
     * Requiere API Key + (número remitente o Phone Number ID).
     */
    public static function userHasYCloudSession(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        $settings = $user->settings ?? [];
        $apiKey = trim((string) ($settings['whatsapp_api_key'] ?? ''));
        $from = trim((string) ($settings['whatsapp_phone_number'] ?? ''));
        $phoneId = trim((string) ($settings['whatsapp_phone_number_id'] ?? ''));
        return $apiKey !== '' && ($from !== '' || $phoneId !== '');
    }

    /**
     * Proveedor efectivo del usuario: 'ycloud', 'zavu' o null.
     * Respeta la selección explícita (settings.whatsapp_provider); si no hay,
     * auto: YCloud si la sesión está completa, Zavu si solo hay API Key
     * (en Zavu el número remitente va ligado a la cuenta, no se configura aquí).
     */
    public static function userWhatsAppProvider(?User $user): ?string
    {
        if (!$user) {
            return null;
        }
        $settings = $user->settings ?? [];
        $explicit = $settings['whatsapp_provider'] ?? null;
        if (in_array($explicit, ['ycloud', 'zavu'], true)) {
            return $explicit;
        }
        if (self::userHasYCloudSession($user)) {
            return 'ycloud';
        }
        if (trim((string) ($settings['whatsapp_api_key'] ?? '')) !== '') {
            return 'zavu';
        }
        return null;
    }

    /** ¿El usuario tiene sesión propia de Zavu (su API Key de su cuenta Zavu)? */
    public static function userHasZavuSession(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        $settings = $user->settings ?? [];
        return self::userWhatsAppProvider($user) === 'zavu'
            && trim((string) ($settings['whatsapp_api_key'] ?? '')) !== '';
    }

    /** ¿El usuario puede enviar masivos? (sesión YCloud o Zavu propia). */
    public static function userHasBulkSession(?User $user): bool
    {
        return self::userHasYCloudSession($user) || self::userHasZavuSession($user);
    }

    /**
     * Credenciales YCloud efectivas: primero las del usuario (su sesión),
     * luego el fallback global de config/services.ycloud.
     *
     * @return array{apiKey: ?string, fromNumber: ?string, phoneNumberId: ?string, source: string}
     */
    public static function ycloudCredentials(?User $user): array
    {
        $settings = $user?->settings ?? [];
        $userKey = trim((string) ($settings['whatsapp_api_key'] ?? ''));
        $userFrom = trim((string) ($settings['whatsapp_phone_number'] ?? ''));
        $userPhoneId = trim((string) ($settings['whatsapp_phone_number_id'] ?? ''));

        // Si el usuario empezó a configurar su sesión (tiene API key),
        // NO mezclar con el número global: su sesión debe estar completa
        // o falla con mensaje claro. Evita enviar con key de uno + número de otro.
        if ($userKey !== '') {
            return [
                'apiKey' => $userKey,
                'fromNumber' => $userFrom !== '' ? $userFrom : null,
                'phoneNumberId' => $userPhoneId !== '' ? $userPhoneId : null,
                'source' => 'user',
            ];
        }

        return [
            'apiKey' => config('services.ycloud.api_key') ?: null,
            'fromNumber' => config('services.ycloud.phone_number') ?: null,
            'phoneNumberId' => config('services.ycloud.phone_number_id') ?: null,
            'source' => 'global',
        ];
    }

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
        $creds = self::ycloudCredentials($user);
        $apiKey = $creds['apiKey'];
        $fromNumber = $creds['fromNumber'];
        $phoneNumberId = $creds['phoneNumberId'];
        if (!$apiKey || (!$fromNumber && !$phoneNumberId)) {
            return $this->result(false, null, self::MISSING_SESSION_MESSAGE);
        }
        $baseUrl = rtrim(config('services.ycloud.base_url', 'https://api.ycloud.com'), '/');
        $params = $this->buildParams($client, $company);

        try {
            // Vía nativa YCloud v2 (requiere número remitente "from" = sesión del usuario).
            // Es la vía que usa la plantilla del masivo.
            if ($fromNumber) {
                $payload = [
                    'from' => $fromNumber,
                    'to' => $client->formatted_phone,
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
                $response = Http::timeout(30)
                    ->withHeaders(['X-API-Key' => $apiKey, 'Content-Type' => 'application/json'])
                    ->post("{$baseUrl}/v2/whatsapp/messages", $payload);
                $body = $response->json();
                if ($response->ok() && isset($body['id'])) {
                    return $this->result(true, $body['id'], null, $body);
                }
                $rawError = is_array($body) ? ($body['error']['message'] ?? ($body['message'] ?? $body)) : $response->body();
                $error = is_string($rawError) ? $rawError : json_encode($rawError, JSON_UNESCAPED_UNICODE);
                return $this->result(false, null, $error, is_array($body) ? $body : []);
            }

            // Fallback compatible Meta (solo con Phone Number ID, sin "from").
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => ltrim($client->formatted_phone, '+'),
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
            $response = Http::timeout(30)
                ->withHeaders(['X-API-Key' => $apiKey, 'Content-Type' => 'application/json'])
                ->post("{$baseUrl}/v1/{$phoneNumberId}/messages", $payload);
            $body = $response->json();
            if ($response->ok() && isset($body['messages'][0]['id'])) {
                return $this->result(true, $body['messages'][0]['id'], null, $body);
            }
            $rawError = is_array($body) ? ($body['error']['message'] ?? ($body['message'] ?? $body)) : $response->body();
            $error = is_string($rawError) ? $rawError : json_encode($rawError, JSON_UNESCAPED_UNICODE);
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
        $creds = self::ycloudCredentials($user);
        $apiKey = $creds['apiKey'];
        $fromNumber = $creds['fromNumber'];
        $phoneNumberId = $creds['phoneNumberId'];
        if (!$apiKey || (!$fromNumber && !$phoneNumberId)) {
            return $this->result(false, null, self::MISSING_SESSION_MESSAGE);
        }
        $baseUrl = rtrim(config('services.ycloud.base_url', 'https://api.ycloud.com'), '/');
        try {
            // Vía nativa v2 con número remitente (sesión del usuario).
            if ($fromNumber) {
                $response = Http::timeout(30)
                    ->withHeaders(['X-API-Key' => $apiKey, 'Content-Type' => 'application/json'])
                    ->post("{$baseUrl}/v2/whatsapp/messages", [
                        'from' => $fromNumber,
                        'to' => ltrim($to, '+'),
                        'type' => 'text',
                        'text' => ['body' => $text],
                    ]);
                $body = $response->json();
                if ($response->ok() && isset($body['id'])) {
                    return $this->result(true, $body['id'], null, $body);
                }
                // Si v2 falla, se intenta la vía compatible Meta abajo solo si hay phoneNumberId.
                if (!$phoneNumberId) {
                    $rawError = is_array($body) ? ($body['error']['message'] ?? ($body['message'] ?? $body)) : $response->body();
                    $error = is_string($rawError) ? $rawError : json_encode($rawError, JSON_UNESCAPED_UNICODE);
                    return $this->result(false, null, $error, is_array($body) ? $body : []);
                }
            }
            $response = Http::timeout(30)
                ->withHeaders(['X-API-Key' => $apiKey])
                ->post("{$baseUrl}/v1/{$phoneNumberId}/messages", [
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
        // Sesión propia del usuario primero: YCloud o Zavu (1 cuenta por usuario)
        $userProvider = self::userWhatsAppProvider($user);
        if ($userProvider) {
            return $userProvider;
        }
        // Fallbacks globales
        if (config('services.ycloud.api_key') && (config('services.ycloud.phone_number') || config('services.ycloud.phone_number_id'))) {
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
        return 'Estimado(a) cliente: Reciba un cordial saludo de parte de WODEN PANAMA, empresa encargada de la gestion y recuperacion de equipos a nivel nacional para TIGO PANAMA. Nos permitimos contactarle debido a que hemos recibido una orden de recuperacion de equipos. Con el proposito de coordinar la visita y realizar el proceso de manera agil, segura y conveniente para usted, agradecemos su colaboracion proporcionandonos por este medio su ubicacion en tiempo actual mediante WhatsApp. Agradecemos de antemano su atencion y colaboracion. Saludos cordiales, WODEN PANAMA.';
    }

    private function sendViaZavu(string $to, Client $client, Company $company, ?string $templateName, ?User $user = null): array
    {
        $apiKey = trim((string) ($user?->settings['whatsapp_api_key'] ?? '')) ?: config('services.zavu.api_key');
        $baseUrl = config('services.zavu.base_url', 'https://api.zavu.dev');
        if (!$apiKey) {
            return $this->result(false, null, self::BULK_NO_SESSION_MESSAGE);
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
        $apiKey = trim((string) ($user?->settings['whatsapp_api_key'] ?? '')) ?: config('services.zavu.api_key');
        $baseUrl = config('services.zavu.base_url', 'https://api.zavu.dev');
        if (!$apiKey) {
            return $this->result(false, null, self::BULK_NO_SESSION_MESSAGE);
        }
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
        $provider = self::userWhatsAppProvider($user);
        $hasSession = self::userHasBulkSession($user);
        if ($user && $provider) {
            $settings = $user->settings ?? [];
            $missing = [];
            if (empty($settings['whatsapp_api_key'] ?? null)) {
                $missing[] = 'whatsapp_api_key';
            }
            if ($provider === 'ycloud'
                && empty($settings['whatsapp_phone_number'] ?? null)
                && empty($settings['whatsapp_phone_number_id'] ?? null)) {
                $missing[] = 'whatsapp_phone_number';
            }
            return [
                'provider' => $provider,
                'api_key' => !empty($settings['whatsapp_api_key'] ?? null) ? 'configured' : null,
                'phone_number' => $settings['whatsapp_phone_number'] ?? null,
                'phone_number_id' => $settings['whatsapp_phone_number_id'] ?? null,
                'source' => 'user',
                'has_session' => $hasSession,
                'missing' => $missing,
            ];
        }
        $provider = $provider ?? $this->provider();
        return [
            'provider' => $provider,
            'api_key' => $provider ? 'configured' : null,
            'phone_number' => config('services.ycloud.phone_number'),
            'phone_number_id' => config('services.ycloud.phone_number_id'),
            'source' => 'global',
            'has_session' => $hasSession,
        ];
    }
}