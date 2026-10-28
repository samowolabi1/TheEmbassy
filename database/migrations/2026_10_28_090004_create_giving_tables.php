<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funds', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('target')->nullable(); // kobo, for appeals like a building fund
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // Every gift, online or counted at church. Amounts in kobo.
        Schema::create('gifts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('fund_id')->constrained();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('method', 20); // online, cash, transfer, cheque
            $table->string('status', 20)->default('awaiting_payment')->index();
            $table->date('given_on')->index();
            $table->string('counted_by')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // Monthly member dues. One row covers `months` months starting at `from_month`.
        Schema::create('token_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->date('from_month');
            $table->unsignedSmallInteger('months');
            $table->unsignedBigInteger('amount');
            $table->string('method', 20);
            $table->string('status', 20)->default('awaiting_payment')->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->timestamp('token_reminded_at')->nullable()->after('tokens_from');
        });
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('token_reminded_at'));
        Schema::dropIfExists('token_payments');
        Schema::dropIfExists('gifts');
        Schema::dropIfExists('funds');
    }
};
