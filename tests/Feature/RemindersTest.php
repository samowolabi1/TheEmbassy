<?php

namespace Tests\Feature;

use App\Mail\ReminderMail;
use App\Mail\SubscriptionEnding;
use App\Models\Member;
use App\Models\ReminderLog;
use App\Models\Service;
use App\Models\ServiceFeedback;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorSubscription;
use App\Services\Messaging\Reminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(now()->next('Sunday')->setTime(6, 30));
    }

    private function service(array $overrides = []): Service
    {
        return Service::create(['held_on' => today(), 'kind' => 'sunday', 'starts_at' => today()->setTime(9, 0), 'ends_at' => today()->setTime(11, 0), 'in_person_checkin' => true, 'online_checkin' => true, ...$overrides]);
    }

    public function test_members_are_reminded_before_a_service_once(): void
    {
        $ada = Member::factory()->active()->create(['name' => 'Ada Obi']);
        Member::factory()->create(); // waiting for approval: not reminded
        $service = $this->service();

        $this->artisan('reminders:send')->assertSuccessful();
        $this->assertSame(0, ReminderLog::count()); // 9:00 is more than 2 hours away

        $this->travelTo(today()->setTime(8, 5));
        $this->artisan('reminders:send')->expectsOutputToContain('Service reminders reached 1');
        $this->artisan('reminders:send')->expectsOutputToContain('Service reminders reached 0');
        $log = ReminderLog::sole();
        $this->assertSame(['service', $ada->user_id, $service->id], [$log->kind, $log->user_id, $log->service_id]);
        $this->assertStringContainsString('Hi Ada! Sunday service is today at 9:00 AM.', $log->body);

        // It shows in their Messages.
        $this->actingAs($ada->user)->get(route('account.inbox'))->assertOk()->assertSee('Hi Ada! Sunday service is today at 9:00 AM.');
    }

    public function test_people_who_came_are_thanked_and_asked_for_feedback(): void
    {
        $came = Member::factory()->active()->create(['name' => 'Tolu Ade']);
        $answered = Member::factory()->active()->create(['name' => 'Emeka Obi']);
        $away = Member::factory()->active()->create();
        $service = $this->service(['reminder_sent_at' => now()]);
        $service->members()->attach([$came->id => ['mode' => 'in_person'], $answered->id => ['mode' => 'online']]);
        ServiceFeedback::forceCreate(['service_id' => $service->id, 'member_id' => $answered->id, 'rating' => 5]);

        $this->travelTo(today()->setTime(11, 20));
        $this->artisan('reminders:send')->expectsOutputToContain('thank-you greetings 0');
        $this->travelTo(today()->setTime(11, 31));
        $this->artisan('reminders:send')->expectsOutputToContain('thank-you greetings 2, feedback requests 0');
        $this->travelTo(today()->setTime(14, 1));
        $this->artisan('reminders:send')->expectsOutputToContain('thank-you greetings 0, feedback requests 1');
        $this->artisan('reminders:send')->expectsOutputToContain('feedback requests 0');

        $this->assertSame(0, ReminderLog::where('user_id', $away->user_id)->count());
        $this->assertSame(['greeting', 'feedback'], ReminderLog::where('user_id', $came->user_id)->orderBy('id')->pluck('kind')->all());
        $this->assertSame(['greeting'], ReminderLog::where('user_id', $answered->user_id)->pluck('kind')->all());
        $this->assertStringContainsString('How was Sunday service today, Tolu?', ReminderLog::where('kind', 'feedback')->value('body'));
    }

    public function test_switched_off_reminders_and_old_services_send_nothing(): void
    {
        Member::factory()->active()->create();
        config(['church.reminders.service.enabled' => '0']);
        $this->service(['starts_at' => now()->addHour()]);
        $this->service(['held_on' => today()->subDays(3), 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHours(2)]);
        $this->artisan('reminders:send')->expectsOutput('Service reminders reached 0, thank-you greetings 0, feedback requests 0.');
        $this->assertSame(0, ReminderLog::count());
    }

    public function test_reminders_can_also_go_by_email(): void
    {
        $ada = Member::factory()->active()->create();
        config(['church.reminders.service.email' => '1']);
        $this->service(['starts_at' => now()->addHour()]);
        $this->artisan('reminders:send');
        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo($ada->user->email) && str_contains($m->body, 'We can\'t wait to worship with you'));
    }

    public function test_staff_manage_every_reminder(): void
    {
        $office = User::factory()->create(['role' => 'secretary']);
        $this->actingAs($office)->get(route('admin.reminders.index'))->assertOk()
            ->assertSee('Service reminder')->assertSee('Thank-you greeting')->assertSee('Feedback request')->assertSee('Market renewal reminder')
            ->assertSee('Hi Ada! Sunday service is today at 9:00 AM.');

        $this->put(route('admin.reminders.update', 'service'), ['timing' => 12, 'push' => '1', 'email' => '1', 'text' => 'Good evening {first_name}! See you {day} at {time}.'])->assertSessionHasNoErrors();
        $s = Reminders::settings('service');
        $this->assertSame([false, 12, true, false, true, 'Good evening {first_name}! See you {day} at {time}.'], [$s['enabled'], $s['timing'], $s['push'], $s['sms'], $s['email'], $s['text']]);
        $this->put(route('admin.reminders.update', 'greeting'), ['enabled' => '1', 'timing' => 5000, 'text' => ''])->assertSessionHasErrors(['timing', 'text'], null, 'greeting');
        $this->put(route('admin.reminders.update', 'nonsense'), ['timing' => 1, 'text' => 'x'])->assertNotFound();

        // A test goes to the person asking only.
        $this->put(route('admin.reminders.update', 'feedback'), ['enabled' => '1', 'timing' => 3, 'email' => '1', 'text' => 'How was it, {first_name}?']);
        $this->post(route('admin.reminders.test', 'feedback'))->assertSessionHas('status', 'Test sent to you by email.');
        Mail::assertSent(ReminderMail::class, fn ($m) => $m->hasTo($office->email) && str_starts_with($m->headline, '[Test]'));

        // Not for roles without the area.
        $this->actingAs(User::factory()->create(['role' => 'media']))->get(route('admin.reminders.index'))->assertForbidden();
    }

    public function test_sellers_get_a_custom_reminder_a_final_one_and_a_notice_when_it_ends(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->approved()->create(['user_id' => $user->id, 'email' => $user->email, 'name' => 'Tolu\'s Kitchen', 'owner_name' => 'Tolu']);
        VendorSubscription::factory()->for($shop)->create(['status' => VendorSubscription::ACTIVE, 'starts_at' => now()->subMonth(), 'ends_at' => now()->addDays(10)]);
        config(['church.reminders.market.days_before' => '10']);

        $this->artisan('market:remind')->expectsOutput('Sent 1 renewal reminders and 0 lapsed notices.');
        Mail::assertSent(SubscriptionEnding::class, fn ($m) => str_contains((string) $m->text, 'the '.config('church.brand.market_name').' subscription for Tolu\'s Kitchen ends on'));
        $this->assertSame('market', ReminderLog::where('shop_id', $shop->id)->value('kind'));

        $this->travel(9)->days();
        $this->travel(1)->hours();
        $this->artisan('market:remind')->expectsOutput('Sent 1 renewal reminders and 0 lapsed notices.');
        $this->artisan('market:remind')->expectsOutput('Sent 0 renewal reminders and 0 lapsed notices.');
        Mail::assertSent(SubscriptionEnding::class, 2);

        $this->travel(2)->days();
        $this->artisan('market:remind')->expectsOutput('Sent 0 renewal reminders and 1 lapsed notices.');

        // Switched off: nothing.
        config(['church.reminders.market.enabled' => '0']);
        $shop->forceFill(['reminded_for' => null, 'final_reminded_for' => null, 'lapse_noticed_for' => null])->save();
        $this->artisan('market:remind')->expectsOutput('Sent 0 renewal reminders and 0 lapsed notices.');
    }
}
