<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workers asking permission to miss a service, or to come late, with a
 * reason. Their unit heads and the pastors see it and answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->date('on_date');
            $table->string('kind', 10); // absent or late
            $table->string('arrive_at', 5)->nullable(); // late: about when they'll arrive, "09:30"
            $table->text('reason');
            $table->string('status', 12)->default('pending'); // pending, granted, declined, cancelled
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('answer_note', 500)->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->index(['on_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_requests');
    }
};
