<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category', 60)->index();
            $table->unsignedBigInteger('amount');
            $table->date('spent_on');
            $table->text('description')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decline_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('category', 60);
            $table->date('month');
            $table->unsignedBigInteger('amount');
            $table->timestamps();
            $table->unique(['category', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('expenses');
    }
};
