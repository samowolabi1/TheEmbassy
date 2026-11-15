<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Training a worker or minister needs (School of Disciples and Bible college join the classes
 * already kept), when their answers were last given, and: everyone serving in a unit is a worker.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->boolean('sod')->default(false)->after('workers_training');
            $table->boolean('bible_college')->default(false)->after('sod');
            $table->timestamp('training_answered_at')->nullable()->after('bible_college');
        });

        DB::table('members')->where('status', 'active')->where('rank', 'member')->whereNotNull('unit_id')
            ->update(['rank' => 'worker', 'rank_changed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['sod', 'bible_college', 'training_answered_at']);
        });
    }
};
