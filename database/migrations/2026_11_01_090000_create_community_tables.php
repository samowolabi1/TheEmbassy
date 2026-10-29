<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The members' community: chat rooms, messages, reactions, polls, reports, and the Bible quiz.
     */
    public function up(): void
    {
        Schema::create('community_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 70)->unique();
            $table->string('emoji', 16)->nullable();
            $table->string('description', 200)->nullable();
            $table->foreignId('unit_id')->nullable()->constrained()->cascadeOnDelete(); // only that unit's members
            $table->boolean('is_locked')->default(false); // read-only except for moderators
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('community_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reply_to_id')->nullable()->constrained('community_messages')->nullOnDelete();
            $table->text('body')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_thumb_path')->nullable();
            $table->json('poll')->nullable(); // {question, options[], closes_at}
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('removed_at')->nullable(); // deleted by the writer or a moderator
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['community_room_id', 'id']);
            $table->index(['community_room_id', 'updated_at']);
        });

        Schema::create('community_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();
            $table->unique(['community_message_id', 'user_id', 'emoji']);
        });

        Schema::create('community_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('option');
            $table->timestamps();
            $table->unique(['community_message_id', 'user_id']);
        });

        Schema::create('community_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_room_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_id')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'community_room_id']);
        });

        Schema::create('community_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // who reported it
            $table->string('reason', 300);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('outcome', 20)->nullable(); // dismissed, removed, muted
            $table->timestamps();
            $table->unique(['community_message_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('community_muted_until')->nullable();
            $table->string('community_mute_reason', 200)->nullable();
        });

        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('description', 300)->nullable();
            $table->timestamp('opens_at');
            $table->timestamp('closes_at');
            $table->unsignedSmallInteger('seconds_per_question')->default(20);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('question', 300);
            $table->json('options'); // 2 to 4 answers
            $table->unsignedTinyInteger('answer'); // index of the right one
            $table->string('reference', 60)->nullable(); // e.g. John 3:16
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('answers')->nullable();
            $table->unsignedSmallInteger('score')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('seconds')->nullable();
            $table->timestamps();
            $table->unique(['quiz_id', 'user_id']);
        });

        // Rooms to start with; staff rename, add or lock them in admin.
        $now = now();
        $rooms = [
            ['General', 'general', '👋', 'Say hello, share news and catch up with the Tribe.'],
            ['Prayer & praise', 'prayer-and-praise', '🙏', 'Ask for prayer and share what God has done.'],
            ['Word & devotion', 'word-and-devotion', '📖', 'What you read today, sermon notes and questions about the Bible.'],
            ['Campus life', 'campus-life', '🎓', 'Lectures, exams, hostels and staying on fire in school.'],
            ['Career & hustle', 'career-and-hustle', '💼', 'Jobs, business ideas, internships and money wisdom.'],
            ['Banter & fun', 'banter-and-fun', '😂', 'Memes, games, music and good vibes (keep it clean).'],
        ];
        foreach ($rooms as $i => [$name, $slug, $emoji, $description]) {
            DB::table('community_rooms')->insert([
                'name' => $name, 'slug' => $slug, 'emoji' => $emoji, 'description' => $description,
                'position' => $i, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['community_muted_until', 'community_mute_reason']));
        Schema::dropIfExists('community_reports');
        Schema::dropIfExists('community_reads');
        Schema::dropIfExists('community_votes');
        Schema::dropIfExists('community_reactions');
        Schema::dropIfExists('community_messages');
        Schema::dropIfExists('community_rooms');
    }
};
