<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('order_number')->index();
            $table->string('full_name');
            $table->string('phone')->index();
            $table->string('alternate_phone')->nullable();
            $table->text('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('reference')->nullable();
            $table->json('equipment_details')->nullable();
            $table->string('status')->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('recovery');
            $table->string('priority')->default('normal');
            $table->string('status')->default('pending');
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_time_start')->nullable();
            $table->time('scheduled_time_end')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('address')->nullable();
            $table->json('checklist')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['assigned_to', 'status', 'scheduled_date']);
        });

        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('assigned_by')->constrained('users')->onDelete('cascade');
            $table->string('assignment_type')->default('auto');
            $table->text('notes')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->unique(['task_id', 'user_id']);
        });

        Schema::create('excel_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('imported_by')->constrained('users')->onDelete('cascade');
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('disk')->default('local');
            $table->integer('total_rows')->default(0);
            $table->integer('successful_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->json('errors')->nullable();
            $table->json('column_mapping')->nullable();
            $table->string('status')->default('processing');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('task_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type')->default('photo');
            $table->string('file_path');
            $table->string('disk')->default('public');
            $table->string('mime_type')->nullable();
            $table->integer('file_size')->nullable();
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('comment');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to_phone');
            $table->string('template_name');
            $table->json('template_params')->nullable();
            $table->string('message_id')->nullable();
            $table->string('status')->default('pending');
            $table->json('response_data')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retries')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('route_date');
            $table->json('optimized_order')->nullable();
            $table->decimal('total_distance_km', 8, 2)->nullable();
            $table->integer('estimated_duration_minutes')->nullable();
            $table->string('status')->default('planned');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'route_date']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('generated_by')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->string('type')->default('custom');
            $table->string('format')->default('excel');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->json('filters')->nullable();
            $table->string('file_path')->nullable();
            $table->string('disk')->default('local');
            $table->string('status')->default('generating');
            $table->integer('file_size')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type')->default('weekly');
            $table->string('format')->default('excel');
            $table->json('filters')->nullable();
            $table->json('recipients')->nullable();
            $table->time('send_at')->default('08:00:00');
            $table->string('day_of_week')->nullable();
            $table->integer('day_of_month')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('next_send_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_evidence');
        Schema::dropIfExists('excel_imports');
        Schema::dropIfExists('task_assignments');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('companies');
    }
};