<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The church's own categories for money in and money out.
        Schema::create('money_categories', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 3); // in, out
            $table->string('name', 60);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['kind', 'name']);
        });

        // Money received (offerings counted, donations, hire, sales…), recorded by any staff member
        // and confirmed by someone else.
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('category', 60);
            $table->unsignedBigInteger('amount'); // kobo
            $table->date('received_on');
            $table->string('method', 12)->default('cash');
            $table->text('description')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('status', 10)->default('pending');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decline_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'received_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomes');
        Schema::dropIfExists('money_categories');
    }
};
