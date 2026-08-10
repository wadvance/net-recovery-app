<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{

    protected $table = "whatsapp_messages";

    protected $fillable = ["company_id", "client_id", "task_id", "to_phone", "template_name", "template_params", "message_id", "status", "response_data", "error_message", "retries", "sent_at", "delivered_at", "read_at"];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    protected function casts(): array
    {
        return ["template_params" => "array", "response_data" => "array", "sent_at" => "datetime", "delivered_at" => "datetime", "read_at" => "datetime"];
    }

    public function markSent(string $messageId, array $response = []): void
    {
        $this->update(["status" => "sent", "message_id" => $messageId, "response_data" => $response, "sent_at" => now()]);
    }

    public function markDelivered(): void
    {
        $this->update(["status" => "delivered", "delivered_at" => now()]);
    }

    public function markRead(): void
    {
        $this->update(["status" => "read", "read_at" => now()]);
    }

    public function markFailed(string $error): void
    {
        $this->update(["status" => "failed", "error_message" => $error]);
    }
}
