<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bible verses (KJV) for the members' verse scroll and the daily verse, the verses
 * each member saved, and the member's daily-verse switch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verses', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 60);
            $table->text('text');
            $table->string('theme', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('is_active');
        });

        Schema::create('member_verse', function (Blueprint $table) {
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verse_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['member_id', 'verse_id']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->boolean('daily_verse')->default(true);
            $table->date('daily_verse_on')->nullable(); // the last day they were shown one
        });

        $now = now();
        $rows = array_map(fn ($v) => ['reference' => $v[0], 'text' => $v[1], 'theme' => $v[2], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            require database_path('data/verses.php'));
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('verses')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn(['daily_verse', 'daily_verse_on']));
        Schema::dropIfExists('member_verse');
        Schema::dropIfExists('verses');
    }
};
