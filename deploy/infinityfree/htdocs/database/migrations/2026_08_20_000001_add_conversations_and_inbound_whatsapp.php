<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone')->index();
            $table->string('contact_name')->nullable();
            $table->string('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->integer('unread_count')->default(0);
            $table->string('status')->default('open');
            $table->timestamps();
            $table->index(['assigned_to', 'status', 'last_message_at']);
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('direction')->default('outbound')->after('status');
            $table->string('from_phone')->nullable()->after('to_phone');
            $table->text('body')->nullable()->after('template_name');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropColumn(['conversation_id', 'direction', 'from_phone', 'body']);
        });

        Schema::dropIfExists('conversations');
    }
};