<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->date('held_on')->index();
            $table->string('kind', 20); // sunday, midweek, special
            $table->string('title')->nullable();
            $table->unsignedInteger('in_person')->default(0);
            $table->unsignedInteger('online')->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['held_on', 'kind']);
        });

        Schema::create('member_service', function (Blueprint $table) {
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->primary(['member_id', 'service_id']);
        });

        Schema::create('first_timers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('invited_by')->nullable();
            $table->date('visited_on')->index();
            $table->boolean('wants_to_join')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('followed_up_at')->nullable();
            $table->foreignId('followed_up_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('follow_up_note')->nullable();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('first_timers');
        Schema::dropIfExists('member_service');
        Schema::dropIfExists('services');
    }
};
