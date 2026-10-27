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
        Schema::create('market_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('code', 12)->unique();
            $table->unsignedSmallInteger('quantity');
            // Money in kobo, fixed at the time of the order.
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('commission');
            $table->unsignedBigInteger('platform_fee');
            $table->string('buyer_name');
            $table->string('buyer_email');
            $table->string('buyer_phone', 40);
            $table->text('delivery_note');
            // awaiting_payment, paid, ready, completed or cancelled.
            $table->string('status', 20)->default('awaiting_payment')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_orders');
    }
};
