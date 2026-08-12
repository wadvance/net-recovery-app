<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'order_number', 'full_name', 'phone', 'alternate_phone',
        'address', 'latitude', 'longitude', 'reference', 'equipment_details', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'equipment_details' => 'array', 'metadata' => 'array'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
    public function assignedUser()
    {
        return $this->hasOneThrough(
            \App\Models\User::class,
            Task::class,
            'client_id',
            'id',
            'id',
            'assigned_to'
        )->whereNotNull('tasks.assigned_to')->latest('tasks.updated_at');
    }

    public function getFormattedPhoneAttribute(): string
    {
        $phone = preg_replace('/\D/', '', $this->phone);
        return str_starts_with($phone, '507') ? $phone : '507' . ltrim($phone, '0');
    }

    public function getGoogleMapsUrlAttribute(): string
    {
        if ($this->latitude && $this->longitude) {
            return "https://www.google.com/maps/dir/?api=1&destination={$this->latitude},{$this->longitude}";
        }
        return "https://www.google.com/maps/search/?api=1&query=" . urlencode($this->address);
    }
}