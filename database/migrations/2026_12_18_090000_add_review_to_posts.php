<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog writers send articles for approval instead of publishing them; an editor
 * approves (it goes live) or sends it back with a note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('review', 10)->nullable()->after('published_at'); // pending or changes
            $table->string('review_note', 500)->nullable()->after('review');
            $table->timestamp('submitted_at')->nullable()->after('review_note');
            $table->timestamp('wants_live_at')->nullable()->after('submitted_at'); // the writer's chosen time, if any
            $table->foreignId('reviewed_by')->nullable()->after('wants_live_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review', 'review_note', 'submitted_at', 'wants_live_at', 'reviewed_at']);
        });
    }
};
