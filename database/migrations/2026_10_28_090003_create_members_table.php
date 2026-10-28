<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable()->index();
            $table->string('gender', 10)->nullable();
            $table->date('birthday')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fellowship_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('token_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->date('tokens_from')->nullable(); // first month tokens are due
            $table->string('status', 20)->default('pending')->index();
            $table->date('joined_on')->nullable();
            $table->boolean('baptised')->default(false);
            $table->boolean('believers_class')->default(false);
            $table->boolean('workers_training')->default(false);
            $table->text('notes')->nullable();
            $table->date('last_attended_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
