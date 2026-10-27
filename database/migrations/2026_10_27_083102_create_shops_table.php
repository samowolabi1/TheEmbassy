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
            // Payout details. bank_code is Paystack's code for the bank.
            $table->string('bank_name');
            $table->string('bank_code', 20)->nullable();
            $table->string('account_number', 20);
            $table->string('account_name')->nullable();
            // Paystack subaccount (ACCT_…) that receives the vendor's share of each sale.
            $table->string('subaccount_code')->nullable();
            // pending, approved, declined or suspended.
            $table->string('status', 20)->default('pending')->index();
            $table->string('decline_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
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
