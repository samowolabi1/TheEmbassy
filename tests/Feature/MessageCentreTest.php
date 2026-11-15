<?php

namespace Tests\Feature;

use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Member;
use App\Models\Shop;
use App\Models\Unit;
use App\Models\User;
use App\Models\VendorSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MessageCentreTest extends TestCase
{
    use RefreshDatabase;

    private User $office;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->office = User::factory()->create(['role' => 'secretary', 'name' => 'Ronke Balogun']);
    }

    private function message(array $overrides = []): array
    {
        return ['subject' => 'Thanksgiving this Sunday', 'body' => "Hi {first_name},\n\nJoin us for thanksgiving at {church}.", 'channels' => ['email'], 'audience' => ['type' => 'members'], 'action' => 'send', ...$overrides];
    }

    public function test_a_message_goes_to_approved_members_by_email_and_into_their_messages(): void
    {
        $ada = Member::factory()->active()->create(['name' => 'Ada Obi']);
        $tolu = Member::factory()->active()->create(['name' => 'Tolu Ade']);
        Member::factory()->create(); // waiting for approval
        Member::factory()->active()->withoutLogin()->create(['email' => null, 'phone' => null]); // can't be reached

        $this->actingAs($this->office)->get(route('admin.outreach.create'))->assertOk()->assertSee('Who it');
        $this->getJson(route('admin.outreach.reach', ['type' => 'members']))->assertJson(['people' => 2, 'email' => 2]);
        $this->post(route('admin.outreach.store'), $this->message())->assertRedirect();
        $campaign = Campaign::sole();
        $this->assertSame('sending', $campaign->status);

        $this->artisan('messages:send')->assertSuccessful();
        $campaign->refresh();
        $this->assertSame(['sent', 2, 2, 0], [$campaign->status, $campaign->recipients_count, $campaign->email_sent, $campaign->failed]);
        Mail::assertSent(CampaignMail::class, fn ($m) => $m->hasTo($ada->user->email) && str_starts_with($m->body, 'Hi Ada,') && str_contains($m->body, config('church.short_name')));
        Mail::assertSent(CampaignMail::class, fn ($m) => $m->hasTo($tolu->user->email) && str_starts_with($m->body, 'Hi Tolu,'));

        $this->actingAs($ada->user)->get(route('account.inbox'))->assertOk()->assertSee('Thanksgiving this Sunday')->assertSee('Hi Ada,');
        $this->actingAs($this->office)->get(route('admin.outreach.show', $campaign))->assertOk()->assertSee('Ada Obi')->assertSee('Sent');
        $this->get(route('admin.outreach.contacts'))->assertOk()->assertSee('Ada Obi')->assertSee('1 · last');
    }

    public function test_narrowing_by_unit_and_messaging_sellers_once_each(): void
    {
        $choir = Unit::factory()->create(['name' => 'Choir']);
        Member::factory()->active()->create(['unit_id' => $choir->id]);
        Member::factory()->active()->create();
        $this->actingAs($this->office);
        $this->getJson(route('admin.outreach.reach', ['type' => 'members', 'unit_id' => $choir->id]))->assertJson(['people' => 1]);

        // A seller who is also a member gets "everyone" messages once.
        $both = Member::factory()->active()->create();
        $shop = Shop::factory()->approved()->create(['user_id' => $both->user_id, 'email' => $both->user->email]);
        VendorSubscription::factory()->for($shop)->create(['status' => VendorSubscription::ACTIVE, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]);
        $lapsed = Shop::factory()->approved()->create();
        $this->getJson(route('admin.outreach.reach', ['type' => 'sellers', 'seller_status' => 'lapsed']))->assertJson(['people' => 1]);
        $this->getJson(route('admin.outreach.reach', ['type' => 'everyone']))->assertJson(['people' => 4]); // 3 members + the office (staff); the listed seller is a member
        $this->post(route('admin.outreach.store'), $this->message(['audience' => ['type' => 'sellers']]))->assertRedirect();
        $this->artisan('messages:send');
        Mail::assertSent(CampaignMail::class, 2);
        Mail::assertSent(CampaignMail::class, fn ($m) => $m->hasTo($lapsed->email) && $m->kind === 'seller');
    }

    public function test_drafts_tests_scheduling_stopping_and_whatsapp(): void
    {
        $member = Member::factory()->active()->create(['phone' => '0803 111 2222']);
        $this->actingAs($this->office);

        $this->post(route('admin.outreach.store'), $this->message(['action' => 'draft', 'channels' => []]))->assertRedirect();
        $draft = Campaign::sole();
        $this->assertSame('draft', $draft->status);
        $this->put(route('admin.outreach.update', $draft), $this->message(['action' => 'test']))->assertSessionHas('status', 'Test sent to you by email.');
        Mail::assertSent(CampaignMail::class, fn ($m) => $m->hasTo($this->office->email) && str_starts_with($m->headline, '[Test]'));
        $this->artisan('messages:send');
        $this->assertSame(0, CampaignRecipient::count()); // drafts and tests don't go out

        // Scheduled: waits until its time; can be cancelled.
        $this->put(route('admin.outreach.update', $draft), $this->message(['action' => 'schedule', 'scheduled_at' => now()->addHour()->format('Y-m-d H:i')]))->assertRedirect();
        $this->assertSame('scheduled', $draft->fresh()->status);
        $this->artisan('messages:send');
        $this->assertNull($draft->fresh()->recipients_count);
        $this->post(route('admin.outreach.cancel', $draft))->assertRedirect();
        $this->travel(2)->hours();
        $this->artisan('messages:send');
        $this->assertSame(['cancelled', null], [$draft->fresh()->status, $draft->fresh()->recipients_count]);

        // WhatsApp only when it's switched on and set up.
        $this->post(route('admin.outreach.store'), $this->message(['channels' => ['whatsapp']]))->assertSessionHasErrors('channels.0');
        config(['church.notify.whatsapp' => '1', 'services.whatsapp.token' => 't', 'services.whatsapp.phone_number_id' => '123456', 'services.whatsapp.template' => 'church_notice']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'w']]])]);
        $this->post(route('admin.outreach.store'), $this->message(['channels' => ['whatsapp'], 'subject' => 'By WhatsApp']))->assertSessionHasNoErrors();
        $this->artisan('messages:send');
        Http::assertSent(fn ($r) => $r['to'] === '2348031112222' && str_contains($r['template']['components'][0]['parameters'][0]['text'], 'Hi '.Member::firstNameOf($member->name)));
        $this->assertSame(1, Campaign::where('subject', 'By WhatsApp')->value('whatsapp_sent'));

        // Nothing to send to: refused.
        $this->post(route('admin.outreach.store'), $this->message(['audience' => ['type' => 'members', 'birthday_month' => 13, 'member_status' => 'inactive']]))->assertSessionHasErrors('audience');
    }

    public function test_only_staff_with_the_message_centre_can_use_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'media']))->get(route('admin.outreach.index'))->assertForbidden();
        $this->post(route('admin.outreach.store'), $this->message())->assertForbidden();
        $this->actingAs(Member::factory()->active()->create()->user)->get(route('admin.outreach.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'pastor']))->get(route('admin.outreach.index'))->assertOk()->assertSee('Message centre');
    }
}
