<?php

namespace Tests\Feature;

use App\Mail\TicketsConfirmed;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TicketingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['services.paystack.secret_key' => 'sk_test', 'services.paystack.subaccount' => null, 'platform.fees.tickets' => 5]);
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/t']]),
        ]);
    }

    private function event(array $types = [['name' => 'Free entry', 'price' => 0]], array $attributes = []): Event
    {
        $event = Event::factory()->create(['title' => 'Royal Youth Convention', ...$attributes]);
        foreach ($types as $type) {
            $event->ticketTypes()->create($type);
        }

        return $event->load('ticketTypes');
    }

    private function book(Event $event, array $quantities, array $overrides = [])
    {
        return $this->post(route('events.book', $event), ['name' => 'Ada Okafor', 'email' => 'ada@example.com', 'quantities' => $quantities, ...$overrides]);
    }

    // ---- Public pages ----

    public function test_events_page_lists_upcoming_published_events(): void
    {
        $this->event([['name' => 'Free entry', 'price' => 0]], ['title' => 'Worship Night', 'is_featured' => true]);
        Event::factory()->create(['title' => 'Hidden plan', 'is_published' => false]);
        Event::factory()->create(['title' => 'Last year', 'starts_at' => now()->subMonth()]);

        $this->get('/events')->assertSee('Worship Night')->assertSee('Free')->assertDontSee('Hidden plan')->assertDontSee('Last year');
        $this->get('/')->assertSee('Worship Night');
    }

    public function test_event_page_and_calendar_file(): void
    {
        $event = $this->event([['name' => 'Regular', 'price' => 200000], ['name' => 'VIP', 'price' => 500000]]);

        $this->get(route('events.show', $event))->assertOk()->assertSee('Regular')->assertSee('₦2,000')->assertSee('From ₦2,000');
        $this->get(route('events.calendar', $event))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('SUMMARY:Royal Youth Convention', false);
    }

    public function test_hidden_events_are_not_found(): void
    {
        $event = Event::factory()->create(['is_published' => false]);

        $this->get(route('events.show', $event))->assertNotFound();
    }

    // ---- Booking ----

    public function test_free_booking_is_confirmed_at_once_with_one_ticket_per_person(): void
    {
        $event = $this->event();
        $type = $event->ticketTypes->first();

        $response = $this->book($event, [$type->id => 3]);

        $order = TicketOrder::sole();
        $response->assertRedirect($order->url());
        $this->assertTrue($order->isConfirmed());
        $this->assertSame(3, $order->tickets()->count());
        $this->assertSame(3, Ticket::distinct()->count('code'));
        Mail::assertSent(TicketsConfirmed::class, fn ($mail) => $mail->hasTo('ada@example.com'));

        $this->get($order->url())->assertSee("You're booked", false)->assertSee($order->tickets->first()->code);
        Http::assertNothingSent();
    }

    public function test_paid_booking_goes_through_paystack_and_confirms_on_payment(): void
    {
        $event = $this->event([['name' => 'Regular', 'price' => 200000]]);
        $type = $event->ticketTypes->first();

        $this->book($event, [$type->id => 2])->assertRedirect('https://checkout.paystack.com/t');

        $order = TicketOrder::sole();
        $this->assertFalse($order->isConfirmed());
        $this->assertSame(400000, $order->total);
        $payment = Payment::sole();
        $this->assertSame(20000, $payment->platform_fee);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/transaction/initialize') && $r['amount'] === 400000);
        Mail::assertNothingSent();

        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => [
            'reference' => $payment->reference, 'status' => 'success', 'amount' => 400000, 'currency' => 'NGN',
        ]])]);
        $this->get('/payments/callback?reference='.$payment->reference)->assertRedirect($order->url());

        $this->assertTrue($order->fresh()->isConfirmed());
        Mail::assertSent(TicketsConfirmed::class);
    }

    public function test_limits_are_enforced(): void
    {
        $event = $this->event([['name' => 'Regular', 'price' => 0, 'quantity' => 3, 'max_per_order' => 2]]);
        $type = $event->ticketTypes->first();

        $this->book($event, [$type->id => 3])->assertSessionHasErrors('quantities'); // over max per order
        $this->book($event, [$type->id => 2])->assertSessionHasNoErrors();
        $this->book($event, [$type->id => 2], ['email' => 'b@example.com'])->assertSessionHasErrors(['quantities' => 'Only 1 Regular ticket left.']);
        $this->book($event, [$type->id => 1], ['email' => 'c@example.com'])->assertSessionHasNoErrors();

        $this->assertSame(0, $type->fresh()->remaining());
        $this->get(route('events.show', $event))->assertSee('Sold out');
    }

    public function test_unpaid_orders_hold_places_for_thirty_minutes(): void
    {
        $event = $this->event([['name' => 'Regular', 'price' => 100000, 'quantity' => 1]]);
        $type = $event->ticketTypes->first();

        $this->book($event, [$type->id => 1]); // goes to Paystack, never pays
        $this->book($event, [$type->id => 1], ['email' => 'b@example.com'])->assertSessionHasErrors('quantities');

        $this->travel(31)->minutes();
        $this->book($event, [$type->id => 1], ['email' => 'b@example.com'])->assertRedirect('https://checkout.paystack.com/t');
    }

    public function test_choosing_nothing_or_another_events_ticket_is_refused(): void
    {
        $event = $this->event();
        $other = $this->event([['name' => 'Elsewhere', 'price' => 0]], ['slug' => 'other']);

        $this->book($event, [$event->ticketTypes->first()->id => 0])->assertSessionHasErrors('quantities');
        $this->book($event, [$other->ticketTypes->first()->id => 1])->assertSessionHasErrors('quantities');
        $this->assertDatabaseCount('ticket_orders', 0);
    }

    public function test_paid_tickets_are_refused_while_payments_are_off(): void
    {
        config(['services.paystack.secret_key' => null]);
        $event = $this->event([['name' => 'Regular', 'price' => 100000]]);

        $this->book($event, [$event->ticketTypes->first()->id => 1])->assertSessionHasErrors('quantities');
        $this->assertDatabaseCount('ticket_orders', 0);
    }

    public function test_booking_closes_once_the_event_starts(): void
    {
        $event = $this->event([['name' => 'Free entry', 'price' => 0]], ['starts_at' => now()->subHour()]);

        $this->book($event, [$event->ticketTypes->first()->id => 1])->assertSessionHasErrors('quantities');
    }

    public function test_ticket_page_needs_the_signed_link(): void
    {
        $order = TicketOrder::factory()->create();

        $this->get("/tickets/{$order->code}")->assertForbidden();
    }

    // ---- Admin ----

    public function test_admin_creates_an_event_with_ticket_types(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.events.store'), [
            'title' => 'Worship Night', 'tag' => 'Worship', 'venue' => 'Main sanctuary', 'summary' => 'An evening of worship.',
            'starts_at' => now()->addWeek()->format('Y-m-d\TH:i'), 'is_published' => '1', 'is_featured' => '1',
            'types' => [
                ['name' => 'Regular', 'price' => '2000', 'quantity' => '100', 'max_per_order' => '5'],
                ['name' => '', 'price' => '0'],
            ],
        ])->assertRedirect();

        $event = Event::sole();
        $this->assertSame('worship-night', $event->slug);
        $this->assertTrue($event->is_featured);
        $this->assertSame([['Regular', 200000, 100]], $event->ticketTypes->map(fn ($t) => [$t->name, $t->price, $t->quantity])->all());
    }

    public function test_only_one_event_is_featured(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $old = Event::factory()->create(['is_featured' => true]);
        $new = Event::factory()->create();

        $this->put(route('admin.events.update', $new), [
            'title' => $new->title, 'tag' => 'Worship', 'venue' => 'Hall', 'summary' => 'x',
            'starts_at' => $new->starts_at->format('Y-m-d\TH:i'), 'is_published' => '1', 'is_featured' => '1', 'types' => [],
        ])->assertRedirect();

        $this->assertFalse($old->fresh()->is_featured);
        $this->assertTrue($new->fresh()->is_featured);
    }

    public function test_booked_ticket_types_and_events_cannot_be_removed(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $event = $this->event();
        $type = $event->ticketTypes->first();
        $this->book($event, [$type->id => 1]);

        $this->put(route('admin.events.update', $event), [
            'title' => $event->title, 'tag' => 'x', 'venue' => 'x', 'summary' => 'x',
            'starts_at' => $event->starts_at->format('Y-m-d\TH:i'), 'is_published' => '1',
            'types' => [['id' => $type->id, 'name' => '']],
        ])->assertSessionHasErrors('types');

        $this->delete(route('admin.events.destroy', $event));
        $this->assertModelExists($event);
    }

    public function test_door_check_in_works_once(): void
    {
        $admin = User::factory()->admin()->create();
        $event = $this->event();
        $this->book($event, [$event->ticketTypes->first()->id => 1]);
        $ticket = Ticket::sole();

        $this->assertStringContainsString('<svg', $ticket->qrSvg());

        $this->actingAs($admin)->get(route('admin.tickets.show', $ticket))->assertSee('Valid ticket.');
        $this->followingRedirects()->post(route('admin.tickets.check-in', $ticket))->assertSee('Checked in. Welcome, Ada Okafor!')->assertDontSee('Already checked in');
        $first = $ticket->fresh()->checked_in_at;
        $this->assertNotNull($first);

        $this->travel(5)->minutes();
        $this->post(route('admin.tickets.check-in', $ticket));
        $this->assertTrue($ticket->fresh()->checked_in_at->equalTo($first));
        $this->get(route('admin.tickets.show', $ticket))->assertSee('Already checked in');
    }

    public function test_unpaid_tickets_cannot_be_checked_in(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $ticket = Ticket::factory()->create();

        $this->get(route('admin.tickets.show', $ticket))->assertSee('Not valid');
        $this->post(route('admin.tickets.check-in', $ticket))->assertStatus(422);
    }

    public function test_attendee_list_and_export(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $event = $this->event();
        $this->book($event, [$event->ticketTypes->first()->id => 1], ['name' => '=HYPERLINK("x")']);

        $this->get(route('admin.events.show', $event))->assertSee('HYPERLINK');
        $csv = $this->get(route('admin.events.export', $event))->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_ticket_lookup_by_code(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $ticket = Ticket::factory()->create(['code' => 'ABC123XYZ0']);

        $this->get(route('admin.tickets.lookup', ['code' => 'abc123xyz0']))->assertRedirect(route('admin.tickets.show', $ticket));
    }

    public function test_guests_cannot_check_people_in(): void
    {
        $ticket = Ticket::factory()->create();

        $this->post(route('admin.tickets.check-in', $ticket))->assertRedirect(route('admin.login'));
        $this->assertNull($ticket->fresh()->checked_in_at);
    }
}
