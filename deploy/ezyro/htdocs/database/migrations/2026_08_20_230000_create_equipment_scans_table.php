<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scanned_by')->constrained('users')->cascadeOnDelete();
            $table->string('code')->index();
            $table->string('method')->default('camera');
            $table->text('notes')->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();
            $table->index(['scanned_by', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_scans');
    }
};
