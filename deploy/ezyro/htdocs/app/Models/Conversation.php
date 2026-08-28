<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'company_id', 'client_id', 'assigned_to', 'phone', 'contact_name',
        'last_message', 'last_message_at', 'unread_count', 'status',
    ];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function messages(): HasMany { return $this->hasMany(WhatsAppMessage::class); }
}