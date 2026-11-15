<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Announcements now go by email and WhatsApp (and app notification) instead of text message.
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->unsignedBigInteger('email_cursor')->default(0);
            $table->unsignedInteger('email_sent')->default(0);
            $table->unsignedInteger('email_failed')->default(0);
            $table->unsignedBigInteger('whatsapp_cursor')->default(0);
            $table->unsignedInteger('whatsapp_sent')->default(0);
            $table->unsignedInteger('whatsapp_failed')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', fn (Blueprint $table) => $table->dropColumn(['email_cursor', 'email_sent', 'email_failed', 'whatsapp_cursor', 'whatsapp_sent', 'whatsapp_failed']));
    }
};
