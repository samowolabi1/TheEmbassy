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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Our reference, sent to Paystack and used to match its confirmations.
            $table->string('reference')->unique();
            // What is being paid for: a notice, ticket order or market order.
            $table->morphs('payable');
            $table->string('email');
            // All money in kobo, as integers.
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('platform_fee')->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->string('status', 20)->default('pending')->index();
            $table->string('channel')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
