<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable()->after('held_on');
            $table->dateTime('ends_at')->nullable()->after('starts_at');
            // Members check themselves in: at the venue (inside the geofence), online, or both.
            $table->boolean('in_person_checkin')->default(true)->after('ends_at');
            $table->boolean('online_checkin')->default(false)->after('in_person_checkin');
            // Seeds the code on the check-in screen, which changes every minute.
            $table->string('secret', 64)->nullable()->after('online_checkin');
        });

        Schema::table('member_service', function (Blueprint $table) {
            // in_person or online (the member checked in), or marked (staff ticked them).
            $table->string('mode', 12)->default('marked');
            $table->timestamp('checked_in_at')->nullable();
            // How far from the venue they were, in metres. Their coordinates aren't kept.
            $table->unsignedInteger('distance_m')->nullable();
        });

        // Pastoral care tasks: calling a first-timer, checking on someone who's been away, welcoming a new member.
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('first_timer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('reason', 20)->index(); // first_timer, absent, new_member, other
            $table->string('note')->nullable();
            $table->date('reference_on')->nullable(); // e.g. when an absent member was last seen, so each absence is raised once
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->index();
            $table->timestamp('done_at')->nullable()->index();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('outcome', 20)->nullable(); // reached, no_answer, visited, prayed, other
            $table->string('outcome_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
        Schema::table('member_service', function (Blueprint $table) {
            $table->dropColumn(['mode', 'checked_in_at', 'distance_m']);
        });
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'ends_at', 'in_person_checkin', 'online_checkin', 'secret']);
        });
    }
};
