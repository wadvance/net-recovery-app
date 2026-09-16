<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportSchedule extends Model
{

    protected $fillable = ['company_id', 'name', 'type', 'format', 'filters', 'recipients', 'send_at', 'day_of_week', 'day_of_month', 'is_active', 'last_sent_at', 'next_send_at'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'recipients' => 'array', 'send_at' => 'datetime:H:i:s', 'is_active' => 'boolean', 'last_sent_at' => 'datetime', 'next_send_at' => 'datetime'];
    }
}