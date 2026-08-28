<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'version' => env('WHATSAPP_VERSION', 'v21.0'),
        'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
        'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),
        // Meta directo (Opción C)
        'app_secret' => env('META_APP_SECRET'),
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN', 'netrecovery2026'),
        // Resumen diario de tareas por agente (true/false)
        'agent_summary' => env('WHATSAPP_AGENT_SUMMARY', true),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_WHATSAPP_FROM'),
    ],

    // template_name -> Content Sid aprobado en Twilio (HX...)
    'twilio_content_templates' => [
        'equipment_recovery_notification' => env('TWILIO_WHATSAPP_CONTENT_SID'),
    ],

    // template_name (Meta) -> nombre de plantilla aprobada en Meta Business Manager
    'whatsapp_templates' => [
        'equipment_recovery_notification' => env('WHATSAPP_TEMPLATE_NAME', 'equipment_recovery_notification'),
    ],

];
