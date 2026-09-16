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

    /** Busca el usuario dueño de una línea WhatsApp (por settings.whatsapp_sender_id). */
    public static function ownerOfSender(?string $senderId): ?self
    {
        if (!$senderId) return null;
        return self::where('is_active', true)
            ->get()
            ->first(fn (self $u) => $u->whatsappSenderId() === $senderId);
    }
}