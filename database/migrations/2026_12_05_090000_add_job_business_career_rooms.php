<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Three community groups in place of "Career & hustle": Job vacancies, Business and Career.
 * The old room becomes Career, keeping its conversations. Rooms an admin has already made with
 * these addresses are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $career = DB::table('community_rooms')->where('slug', 'career-and-hustle')->first();
        if ($career && ! DB::table('community_rooms')->where('slug', 'career')->exists()) {
            DB::table('community_rooms')->where('id', $career->id)->update([
                'name' => 'Career', 'slug' => 'career', 'emoji' => '🎯', 'updated_at' => $now,
                'description' => 'Growing at work: CVs, interviews, internships, courses and advice from people ahead of you.',
            ]);
        }
        $position = (int) DB::table('community_rooms')->whereNull('unit_id')->max('position');
        foreach ([
            ['Career', 'career', '🎯', 'Growing at work: CVs, interviews, internships, courses and advice from people ahead of you.'],
            ['Job vacancies', 'job-vacancies', '💼', 'Jobs and internships people know about. Use "Post a job" so every vacancy has the role, place, pay and how to apply.'],
            ['Business', 'business', '🏪', 'Ideas, questions and support for people running a business or side hustle. Share yours with "Share my business".'],
        ] as [$name, $slug, $emoji, $description]) {
            if (DB::table('community_rooms')->where('slug', $slug)->exists()) {
                continue;
            }
            DB::table('community_rooms')->insert([
                'name' => $name, 'slug' => $slug, 'emoji' => $emoji, 'description' => $description,
                'position' => ++$position, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        // Keep the work groups together: banter goes to the end.
        DB::table('community_rooms')->where('slug', 'banter-and-fun')->update(['position' => $position + 1]);
    }

    public function down(): void
    {
        DB::table('community_rooms')->whereIn('slug', ['job-vacancies', 'business'])->delete();
        DB::table('community_rooms')->where('slug', 'career')->update([
            'name' => 'Career & hustle', 'slug' => 'career-and-hustle', 'emoji' => '💼',
            'description' => 'Jobs, business ideas, internships and money wisdom.',
        ]);
    }
};
