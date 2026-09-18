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
     * Texto libre de WODEN/TIGO (por defecto). Cada empresa puede tener el
     * suyo en companies.settings['whatsapp_text'] (editable en el panel).
     */
    public const WODEN_TEXT = 'Estimado(a) cliente: Reciba un cordial saludo de parte de WODEN PANAMA, empresa encargada de la gestion y recuperacion de equipos a nivel nacional para TIGO PANAMA. Nos permitimos contactarle debido a que hemos recibido una orden de recuperacion de equipos. Con el proposito de coordinar la visita y realizar el proceso de manera agil, segura y conveniente para usted, agradecemos su colaboracion proporcionandonos por este medio su ubicacion en tiempo actual mediante WhatsApp. Agradecemos de antemano su atencion y colaboracion. Saludos cordiales, WODEN PANAMA.';

    /** Texto libre de la empresa (settings.whatsapp_text) o el de WODEN por defecto. */
    public static function companyText(?Company $company): string
    {
        $text = trim((string) ($company?->settings['whatsapp_text'] ?? ''));
        return $text !== '' ? $text : self::WODEN_TEXT;
    }

    /**
     * Plantilla a usar para la empresa: la configurada en
     * companies.settings['whatsapp_template'], si no la solicitada,
     * si no la plantilla por defecto. Así TIGO puede usar su plantilla
     * WODEN y MAS MOVIL la suya.
     */
    public static function resolveTemplate(?Company $company, ?string $requested = null): string
    {
        $fromCompany = trim((string) ($company?->settings['whatsapp_template'] ?? ''));
        if ($fromCompany !== '') {
            return $fromCompany;
        }
        $requested = trim((string) ($requested ?? ''));
        if ($requested !== '') {
            return $requested;
        }
        return config('services.whatsapp_templates.equipment_recovery_notification', config('services.whatsapp.default_template', 'equipment_recovery_notification'));
    }

    /** Template ID de Zavu de la empresa (settings.zavu_template_id) o el global. */
    public static function resolveZavuTemplateId(?Company $company): ?string
    {
        $fromCompany = trim((string) ($company?->settings['zavu_template_id'] ?? ''));
        if ($fromCompany !== '') {
            return $fromCompany;
        }
        $global = trim((string) (config('services.zavu.template_id') ?? ''));
        return $global !== '' ? $global : null;
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

        // La plantilla de la empresa manda: cada empresa usa la suya (TIGO la
        // de WODEN, MAS MOVIL la suya). Si no hay, se usa la solicitada.
        if ($templateName) {
            $templateName = self::resolveTemplate($company, $templateName);
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
                // Si la plantilla no está disponible/aprobada, reintentar como texto libre.
                if ($templateName && str_contains(strtolower($error), 'template') && (str_contains(strtolower($error), 'pending') || str_contains(strtolower($error), 'unavailable') || str_contains(strtolower($error), 'not found'))) {
                    $fallback = ['from' => $fromNumber, 'to' => $client->formatted_phone, 'type' => 'text', 'text' => ['body' => $this->fallbackText($client, $company)]];
                    $r2 = Http::timeout(30)->withHeaders(['X-API-Key' => $apiKey, 'Content-Type' => 'application/json'])->post("{$baseUrl}/v2/whatsapp/messages", $fallback);
                    $b2 = $r2->json();
                    if ($r2->ok() && isset($b2['id'])) {
                        return $this->result(true, $b2['id'], null, $b2);
                    }
                }
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
        return self::clientHeader($client, $company) . "\n\n" . self::companyText($company);
    }

    /**
     * Encabezado con los datos del cliente que va arriba del mensaje:
     * nombre, empresa, suscriptor, dirección y teléfono.
     */
    public static function clientHeader(Client $client, ?Company $company): string
    {
        $suscriptor = $client->metadata['suscriptor'] ?? $client->order_number;
        $clientName = $client->full_name ?: 'Estimado cliente';
        $lines = [
            'Cliente: ' . $clientName,
            'Empresa: ' . ($company?->name ?? 'nuestra empresa'),
            'Suscriptor: ' . ($suscriptor ?: '-'),
            'Dirección: ' . ($client->address ?: '-'),
            'Teléfono: ' . ($client->phone ? '+' . ltrim($client->phone, '+') : '-'),
        ];
        return implode("\n", $lines);
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
        $templateId = self::resolveZavuTemplateId($company);
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