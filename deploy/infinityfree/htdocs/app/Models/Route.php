<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Route extends Model
{

    protected $fillable = ['user_id', 'route_date', 'optimized_order', 'total_distance_km', 'estimated_duration_minutes', 'status', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['route_date' => 'date', 'optimized_order' => 'array', 'total_distance_km' => 'decimal:2', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}