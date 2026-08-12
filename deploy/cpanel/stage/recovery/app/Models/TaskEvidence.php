<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskEvidence extends Model
{

    protected $fillable = ['task_id', 'user_id', 'type', 'file_path', 'disk', 'mime_type', 'file_size', 'description', 'latitude', 'longitude', 'metadata'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'metadata' => 'array'];
    }

    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function getFullUrlAttribute(): string
    {
        return \Storage::disk($this->disk)->url($this->file_path);
    }
}