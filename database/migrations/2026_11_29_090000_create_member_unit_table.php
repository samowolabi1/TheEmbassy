<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workers can serve in more than one unit. members.unit_id stays as their main unit (the
 * first they joined), shown where one unit is named; this table holds every unit they're in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_unit', function (Blueprint $table) {
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['member_id', 'unit_id']);
            $table->index('unit_id');
        });

        DB::table('members')->whereNotNull('unit_id')->orderBy('id')->select(['id', 'unit_id'])
            ->chunk(500, fn ($rows) => DB::table('member_unit')->insert($rows->map(fn ($r) => [
                'member_id' => $r->id, 'unit_id' => $r->unit_id, 'created_at' => now(), 'updated_at' => now(),
            ])->all()));

        // Someone can be head of one unit only. If anyone heads two, keep the first.
        $seen = [];
        foreach (DB::table('units')->whereNotNull('hod_member_id')->orderBy('id')->get(['id', 'hod_member_id']) as $u) {
            if (isset($seen[$u->hod_member_id])) {
                DB::table('units')->where('id', $u->id)->update(['hod_member_id' => null]);
            }
            $seen[$u->hod_member_id] = true;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_unit');
    }
};
