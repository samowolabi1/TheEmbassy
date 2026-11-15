<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each reminder, greeting or feedback request sent to someone: their inbox, and the
        // admin's record of what went out.
        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->string('url')->nullable();
            $table->string('channels', 40)->default('');
            $table->timestamp('created_at')->nullable();
            $table->index(['kind', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('greeting_sent_at')->nullable();
            $table->timestamp('feedback_asked_at')->nullable();
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->timestamp('final_reminded_for')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shops', fn (Blueprint $table) => $table->dropColumn('final_reminded_for'));
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn(['reminder_sent_at', 'greeting_sent_at', 'feedback_asked_at']));
        Schema::dropIfExists('reminder_logs');
    }
};
