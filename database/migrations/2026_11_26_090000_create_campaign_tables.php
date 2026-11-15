<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Messages staff send to a group: members, sellers, staff or everyone.
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 160);
            $table->text('body');
            $table->json('audience');
            $table->json('channels');
            $table->string('status', 12)->default('draft'); // draft, scheduled, sending, sent, cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('recipients_count')->nullable();
            $table->unsignedInteger('email_sent')->default(0);
            $table->unsignedInteger('whatsapp_sent')->default(0);
            $table->unsignedInteger('push_sent')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });

        // Who a message went to, kept as it was when it was sent.
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10); // member, seller, staff
            $table->unsignedBigInteger('contact_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('whatsapp_ok')->default(true);
            $table->string('status', 10)->default('pending'); // pending, sent, failed
            $table->string('channels', 40)->default('');
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->index(['campaign_id', 'status']);
            $table->index(['kind', 'contact_id']);
            $table->index(['user_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
    }
};
