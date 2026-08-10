<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'client_id', 'assigned_to', 'title', 'description', 'type', 'priority', 'status',
        'scheduled_date', 'scheduled_time_start', 'scheduled_time_end', 'latitude', 'longitude', 'address',
        'checklist', 'metadata', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'scheduled_date' => 'date',
            'checklist' => 'array', 'metadata' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function assignments(): HasMany { return $this->hasMany(TaskAssignment::class); }
    public function evidence(): HasMany { return $this->hasMany(TaskEvidence::class); }
    public function comments(): HasMany { return $this->hasMany(TaskComment::class); }

    public function scopeAssignedTo($q, $userId) { return $q->where('assigned_to', $userId); }
    public function scopeForDate($q, $date) { return $q->where('scheduled_date', $date); }

    public function start(): void
    {
        $this->update(['status' => 'in_progress', 'started_at' => now()]);
    }

    public function complete(): void
    {
        $this->update(['status' => 'completed', 'completed_at' => now()]);
        $this->client->update(['status' => 'completed']);
    }

    public function fail(string $reason = ''): void
    {
        $this->update(['status' => 'failed', 'metadata' => array_merge($this->metadata ?? [], ['failure_reason' => $reason])]);
        $this->client->update(['status' => 'failed']);
    }
}