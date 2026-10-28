<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Declaration;
use App\Models\Event;
use App\Models\Fellowship;
use App\Models\Fund;
use App\Models\TokenPlan;
use App\Models\Unit;
use App\Models\VendorPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the starting content from the design. Safe to run more than once.
     *
     * Staff accounts are not seeded: create the first administrator with `php artisan admin:create`.
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

        $this->seedEvents();

        // Units and house fellowships from the design; the church renames or adds to them in admin.
        foreach (['Choir' => 'general', 'Media & tech' => 'media', 'Ushering' => 'general', 'Drama' => 'general', 'Prayer' => 'general', 'Welfare' => 'welfare'] as $unit => $kind) {
            Unit::firstOrCreate(['name' => $unit], ['kind' => $kind]);
        }
        foreach (['House 1', 'House 4', 'Campus'] as $fellowship) {
            Fellowship::firstOrCreate(['name' => $fellowship]);
        }
        Fund::ensureDefaults();
        TokenPlan::ensureDefaults();
        VendorPlan::catalogue();

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

    /**
     * The events from the design, each with a free RSVP ticket. Prices can be set in the admin.
     */
    private function seedEvents(): void
    {
        if (Event::exists()) {
            return;
        }

        $events = [
            ['worship-night', 'Worship Night', 'Worship', '2026-11-06 18:00', 'A night of worship and prayer with the Tribe choir.', 'Main sanctuary', false],
            ['royal-youth-convention', 'Royal Youth Convention', 'Convention', '2026-11-14 16:00', 'Worship, honest conversations and the biggest Tribe hangout of the year. Bring a friend.', 'Main sanctuary', true],
            ['career-and-purpose-clinic', 'Career & Purpose Clinic', 'Career', '2026-11-21 11:00', 'CV reviews, mentoring and panels with professionals from the church.', 'Hall B', false],
            ['thanksgiving-sunday', 'Thanksgiving Sunday', 'Thanksgiving', '2026-11-29 09:00', 'Wear white and gold. Family lunch after service.', 'Main sanctuary', false],
            ['community-outreach', 'Community Outreach', 'Outreach', '2026-12-05 09:00', 'Taking food, care packs and the gospel to [Location]. Volunteers welcome.', '[Location]', false],
            ['carols-by-candlelight', 'Carols by Candlelight', 'Carols', '2026-12-19 18:00', 'Carols, candles and the Christmas story.', 'Main sanctuary', false],
        ];

        foreach ($events as [$slug, $title, $tag, $starts, $summary, $venue, $featured]) {
            $event = Event::create([
                'slug' => $slug, 'title' => $title, 'tag' => $tag, 'summary' => $summary, 'venue' => $venue,
                'starts_at' => Carbon::parse($starts, config('app.timezone')),
                'is_featured' => $featured, 'is_published' => true,
            ]);
            $event->ticketTypes()->create(['name' => 'Free entry', 'price' => 0]);
        }
    }
}
