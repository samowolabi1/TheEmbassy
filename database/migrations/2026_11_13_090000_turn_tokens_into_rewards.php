<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tokens are no longer monthly dues. They're free rewards members earn by taking part
     * (attendance, joining online, quizzes and more) and spend in the market or on shop subscriptions.
     */
    public function up(): void
    {
        // Out with the dues.
        DB::table('payments')->where('payable_type', 'App\\Models\\TokenPayment')->delete();
        Schema::dropIfExists('token_payments');
        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('token_plan_id');
            $table->dropColumn(['tokens_from', 'token_reminded_at']);
        });
        Schema::dropIfExists('token_plans');

        // The wallet: a balance kept on the account, and every change recorded.
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('token_balance')->default(0);
        });

        Schema::create('token_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount'); // positive in, negative out
            $table->string('rule', 40); // why: attend_in_person, quiz, market_purchase, admin…
            $table->string('description', 200);
            $table->string('key', 120)->nullable()->unique(); // stops the same reward twice, e.g. attend:12:5
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'id']);
            $table->index(['rule', 'created_at']);
        });

        // Vendors choose whether a listing can be had for tokens, and for how many.
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('token_price')->nullable(); // null = not for tokens
        });

        // Buying with tokens: held from the buyer until the vendor hands it over.
        Schema::create('token_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the buyer
            $table->string('product_name', 150);
            $table->unsignedInteger('tokens');
            $table->string('note', 300)->nullable(); // from the buyer: size, colour, when to collect
            $table->string('status', 12)->default('pending'); // pending, completed, cancelled
            $table->string('cancelled_by', 10)->nullable(); // buyer, vendor, expired
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'status']);
        });

        Schema::table('vendor_subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('tokens_paid')->nullable(); // paid with tokens instead of money
        });
    }

    public function down(): void
    {
        Schema::table('vendor_subscriptions', fn (Blueprint $table) => $table->dropColumn('tokens_paid'));
        Schema::dropIfExists('token_orders');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('token_price'));
        Schema::dropIfExists('token_transactions');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('token_balance'));
        // The old dues tables are not recreated.
    }
};
