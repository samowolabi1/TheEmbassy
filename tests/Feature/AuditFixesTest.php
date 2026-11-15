<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_carry_browser_protections(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy');
    }

    public function test_error_pages_are_friendly_and_branded(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee('We can’t find that page')->assertSee('Go to the home page');
        $member = Member::factory()->create(); // waiting for approval
        $this->actingAs($member->user)->post(route('library.reserve', 'any-book'))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'media']))->get(route('admin.server'))->assertForbidden()->assertSee('You can’t open this page');
    }

    public function test_staff_without_a_member_profile_get_no_dead_testimony_link(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('account'))->assertOk()->assertDontSee(route('account.testimonies'));
        $member = Member::factory()->active()->create();
        $this->actingAs($member->user)->get(route('account'))->assertOk()->assertSee(route('account.testimonies'));
    }

    public function test_an_event_page_counts_tickets_in_one_go(): void
    {
        $event = Event::factory()->create(['starts_at' => now()->addWeek()]);
        foreach (range(1, 6) as $i) {
            $event->ticketTypes()->create(['name' => "Type {$i}", 'price' => 1000 * $i, 'quantity' => 50, 'max_per_order' => 5, 'sort' => $i]);
        }
        DB::enableQueryLog();
        $this->get(route('events.show', $event))->assertOk()->assertSee('Type 6');
        $ticketCounts = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "tickets"'))->count();
        $this->assertLessThanOrEqual(1, $ticketCounts);
    }
}
