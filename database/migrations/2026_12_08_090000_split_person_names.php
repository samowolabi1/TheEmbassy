<?php

use App\Models\Concerns\HasFirstAndLastName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Members and first-timers get a first name and a last name. The full
 * `name` stays, kept up to date from the two, because the rest of the app
 * (lists, search, messages) reads it. First-timers also get a gender.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['members', 'first_timers'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->string('first_name', 60)->nullable()->after('name');
                $t->string('last_name', 60)->nullable()->after('first_name');
                if ($table === 'first_timers') {
                    $t->string('gender', 10)->nullable()->after('last_name');
                }
            });

            // Existing people: the last word is the last name, the rest the first name.
            DB::table($table)->select(['id', 'name'])->orderBy('id')->each(function ($row) use ($table) {
                [$first, $last] = HasFirstAndLastName::split((string) $row->name);
                DB::table($table)->where('id', $row->id)->update(['first_name' => $first, 'last_name' => $last]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $t) => $t->dropColumn(['first_name', 'last_name']));
        Schema::table('first_timers', fn (Blueprint $t) => $t->dropColumn(['first_name', 'last_name', 'gender']));
    }
};
