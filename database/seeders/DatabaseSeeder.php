<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Declaration;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the starting content from the design. Safe to run more than once.
     *
     * Admin accounts are not seeded: create them with `php artisan admin:create`.
     */
    public function run(): void
    {
        Declaration::firstOrCreate(
            ['week_of' => Declaration::weekStarting(today())],
            ['lines' => [
                'I am chosen by God and called for a purpose.',
                'I walk in wisdom, favour and excellence this week.',
                'No weapon formed against me shall prosper.',
                'I am a royal priesthood, and I will shine for Christ.',
            ]],
        );

        if (Announcement::exists()) {
            return;
        }

        $announcements = [
            ['tag' => 'Prayer', 'tone' => 'wash', 'title' => 'Midweek prayer moves online', 'body' => 'Join on YouTube Live every [Day] at [Time].'],
            ['tag' => 'Units', 'tone' => 'ice', 'title' => 'Choir and media auditions are open', 'body' => 'Sign up in the app before 20 November.'],
            ['tag' => 'This Sunday', 'tone' => 'wash', 'title' => 'Thanksgiving service and family lunch', 'body' => 'Wear white and gold. Lunch is served after service.'],
        ];

        // Oldest first, so the first one in the design ends up newest and shows at the top.
        foreach ($announcements as $i => $announcement) {
            Announcement::create([...$announcement, 'published_at' => now()->subMinutes(count($announcements) - $i)]);
        }
    }
}
