<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExcelImport extends Model
{

    protected $fillable = ['company_id', 'imported_by', 'original_filename', 'stored_filename', 'disk', 'total_rows', 'successful_rows', 'failed_rows', 'errors', 'column_mapping', 'status', 'completed_at'];

    protected function casts(): array
    {
        return ['errors' => 'array', 'column_mapping' => 'array', 'completed_at' => 'datetime'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function importedBy(): BelongsTo { return $this->belongsTo(User::class, 'imported_by'); }

    public function markCompleted(int $successful, int $failed, array $errors = []): void
    {
        $this->update([
            'successful_rows' => $successful, 'failed_rows' => $failed, 'errors' => $errors,
            'status' => $failed > 0 && $successful === 0 ? 'failed' : ($failed > 0 ? 'partial' : 'completed'),
            'completed_at' => now(),
        ]);
    }
}