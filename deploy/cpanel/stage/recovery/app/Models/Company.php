<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'code', 'logo', 'description', 'settings', 'is_active'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'is_active' => 'boolean'];
    }

    public function clients(): HasMany { return $this->hasMany(Client::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
}