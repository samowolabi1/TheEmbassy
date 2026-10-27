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
        Schema::create('vendor_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('months');
            // Money in kobo, fixed when the seller subscribes.
            $table->unsignedBigInteger('list_price');
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('amount');
            // awaiting_payment or active. The period is set when it becomes active.
            $table->string('status', 20)->default('awaiting_payment')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_subscriptions');
    }
};
