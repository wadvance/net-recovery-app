<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'avatar', 'password', 'role', 'is_active', 'settings'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function assignedTasks() { return $this->hasMany(Task::class, 'assigned_to'); }
    public function routes() { return $this->hasMany(\App\Models\Route::class, 'user_id'); }
    public function scopeAgents($q) { return $q->where('role', 'agent')->where('is_active', true); }

    /** Sender ID de WhatsApp propia del agente (Meta/Twilio) o null si no tiene. */
    public function whatsappSenderId(): ?string
    {
        $id = $this->settings['whatsapp_sender_id'] ?? null;
        return ($id && is_string($id)) ? $id : null;
    }

    /**
     * ¿El usuario tiene sesión propia de YCloud? (API Key + número remitente o Phone Number ID).
     * Requerido para que los mensajes masivos salgan desde su número en ycloud.com.
     */
    public function hasYCloudSession(): bool
    {
        return \App\Services\WhatsAppService::userHasYCloudSession($this);
    }

    /** Número remitente YCloud del usuario (sesión propia en ycloud.com). */
    public function ycloudFromNumber(): ?string
    {
        $from = trim((string) ($this->settings['whatsapp_phone_number'] ?? ''));
        return $from !== '' ? $from : null;
    }

    /** Proveedor WhatsApp efectivo del usuario: 'ycloud', 'zavu' o null. */
    public function whatsappProvider(): ?string
    {
        return \App\Services\WhatsAppService::userWhatsAppProvider($this);
    }

    /** ¿El usuario tiene sesión propia de Zavu (su cuenta Zavu)? */
    public function hasZavuSession(): bool
    {
        return \App\Services\WhatsAppService::userHasZavuSession($this);
    }

    /** ¿El usuario puede enviar masivos? (sesión YCloud o Zavu propia). */
    public function hasBulkSession(): bool
    {
        return \App\Services\WhatsAppService::userHasBulkSession($this);
    }

    /** Busca el usuario dueño de una línea WhatsApp (por settings.whatsapp_sender_id). */
    public static function ownerOfSender(?string $senderId): ?self
    {
        if (!$senderId) return null;
        return self::where('is_active', true)
            ->get()
            ->first(fn (self $u) => $u->whatsappSenderId() === $senderId);
    }
}