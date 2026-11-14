<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\User;
use Database\Seeders\EventDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class EventDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_fills_events_once_with_bookings_and_check_ins(): void
    {
        $this->seed(EventDemoSeeder::class);
        $this->seed(EventDemoSeeder::class); // a second run adds nothing

        $this->assertSame(10, Event::count());
        $this->assertSame(9, Event::where('is_published', true)->count());
        $this->assertGreaterThan(500, Ticket::count());
        $this->assertGreaterThan(0, Ticket::whereNotNull('checked_in_at')->count());
        $this->assertGreaterThan(0, TicketOrder::where('status', TicketOrder::AWAITING_PAYMENT)->count());

        $fest = Event::firstWhere('slug', 'demo-tribe-fest');
        $this->assertSame(0, $fest->ticketTypes->firstWhere('name', 'Early bird')->remaining());
        $this->get(route('events'))->assertOk()->assertSee('Tribe Fest: Rise &amp; Reign', false)->assertDontSee('Easter Drama');
        $this->get(route('events.show', $fest))->assertOk()->assertSee('Sold out');

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.events.show', Event::firstWhere('slug', 'demo-youth-sports-day')))->assertOk()->assertSee('checked in');
    }

    public function test_it_refuses_to_run_on_a_live_site(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        $this->app->make(EventDemoSeeder::class)->run();
    }
}
