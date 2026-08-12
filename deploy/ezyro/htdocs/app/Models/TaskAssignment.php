<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAssignment extends Model
{

    protected $fillable = ['task_id', 'user_id', 'assigned_by', 'assignment_type', 'notes', 'acknowledged_at'];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime'];
    }

    public function task(): BelongsTo { return $this->belongsTo(Task::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function acknowledge(): void
    {
        $this->update(['acknowledged_at' => now()]);
    }
}