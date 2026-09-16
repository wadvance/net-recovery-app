<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{

    protected $fillable = ['company_id', 'user_id', 'generated_by', 'title', 'type', 'format', 'period_start', 'period_end', 'filters', 'file_path', 'disk', 'status', 'file_size', 'completed_at'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'filters' => 'array', 'completed_at' => 'datetime'];
    }

    public function markCompleted(string $filePath, int $fileSize): void
    {
        $this->update(['status' => 'completed', 'file_path' => $filePath, 'file_size' => $fileSize, 'completed_at' => now()]);
    }

    public function getDownloadUrlAttribute(): ?string
    {
        return $this->file_path ? \Storage::disk($this->disk)->url($this->file_path) : null;
    }
}