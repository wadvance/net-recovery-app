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
}