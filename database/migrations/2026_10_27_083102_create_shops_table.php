<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            // The vendor's login, created when the shop is approved.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('owner_name');
            $table->string('email');
            $table->string('phone', 40);
            $table->string('category', 40);
            $table->text('description');
            $table->string('cac_number', 40)->nullable();
            // Buyers pay sellers directly; the church only charges sellers a subscription.
            $table->string('whatsapp', 40)->nullable();
            // pending, approved, declined or suspended.
            $table->string('status', 20)->default('pending')->index();
            $table->string('decline_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            // The subscription end date we last emailed about, so each reminder goes once.
            $table->timestamp('reminded_for')->nullable();
            $table->timestamp('lapse_noticed_for')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
