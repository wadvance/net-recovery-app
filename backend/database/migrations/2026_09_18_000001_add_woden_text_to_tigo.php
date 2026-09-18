<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Company;
use App\Services\WhatsAppService;

return new class extends Migration
{
    /**
     * Texto libre de WODEN para TIGO. Cada empresa puede tener el suyo en
     * settings.whatsapp_text (editable en Empresas del panel) y su plantilla
     * en settings.whatsapp_template. Así el número de TIGO recibe el texto
     * de WODEN y el de MAS MOVIL el suyo.
     */
    public function up(): void
    {
        $tigo = Company::where('code', 'TIGO')->first();
        if ($tigo) {
            $settings = $tigo->settings ?? [];
            if (empty($settings['whatsapp_text'])) {
                $settings['whatsapp_text'] = WhatsAppService::WODEN_TEXT;
            }
            if (empty($settings['whatsapp_template'])) {
                $settings['whatsapp_template'] = 'equipment_recovery_notification';
            }
            $tigo->update(['settings' => $settings]);
        }
    }

    public function down(): void
    {
        // No se revierte el texto configurado.
    }
};
