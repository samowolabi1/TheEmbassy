<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visitation: pastoral visits to members and visitors, who goes, and the report afterwards.
 * Also when a member was last sent "we missed you", so they get it once per absence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('first_timer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('follow_up_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('phone', 40)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('purpose', 20)->default('pastoral');
            $table->dateTime('scheduled_at')->index();
            $table->string('status', 10)->default('planned')->index();
            $table->text('notes')->nullable();
            $table->boolean('tell_them')->default(false);
            $table->text('report')->nullable();
            $table->string('outcome', 20)->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });

        Schema::create('visit_user', function (Blueprint $table) {
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['visit_id', 'user_id']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->timestamp('missed_message_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('missed_message_at'));
        Schema::dropIfExists('visit_user');
        Schema::dropIfExists('visits');
    }
};
