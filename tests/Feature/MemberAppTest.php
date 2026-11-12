<?php

namespace Tests\Feature;

use App\Mail\WelfarePackageDecision;
use App\Models\Announcement;
use App\Models\Broadcast;
use App\Models\Member;
use App\Models\Service;
use App\Models\Unit;
use App\Models\User;
use App\Models\WelfarePackage;
use App\Models\WelfarePackageApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberAppTest extends TestCase
{
    use RefreshDatabase;

    private function service(array $overrides = []): Service
    {
        return Service::create([
            'held_on' => today(), 'kind' => 'sunday', 'starts_at' => now()->subMinutes(10), 'ends_at' => now()->addHours(2),
            'in_person_checkin' => true, 'online_checkin' => true, ...$overrides,
        ]);
    }

    // ---- Joining online ----

    public function test_members_join_a_youtube_service_and_are_marked_present_after_watching(): void
    {
        config(['church.checkin.watch_minutes' => 3]);
        $service = $this->service(['join_url' => 'https://www.youtube.com/live/dQw4w9WgXcQ']);
        $member = Member::factory()->active()->create();
        $this->actingAs($member->user);

        $this->get(route('account'))->assertSee('Join now')->assertSee(route('services.join', $service), false);
        $this->get(route('services.join', $service))->assertOk()->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)->assertSee('Keep the stream playing for 3 minutes');

        $watch = fn () => $this->postJson(route('services.watching', $service))->assertOk();
        $watch()->assertJsonPath('present', false);
        $watch(); // a second beat straight away doesn't count twice
        $this->travel(61)->seconds();
        $watch()->assertJsonPath('present', false);
        $this->travel(61)->seconds();
        $watch()->assertJsonPath('present', false);
        $this->travel(61)->seconds();
        $watch()->assertJsonPath('present', true);

        $this->assertSame('online', $service->members()->first()->pivot->mode);
        $this->get(route('services.join', $service))->assertSee("You're marked present online", false);
    }

    public function test_stopping_for_a_while_starts_the_count_again(): void
    {
        config(['church.checkin.watch_minutes' => 2]);
        $service = $this->service();
        $member = Member::factory()->active()->create();
        $this->actingAs($member->user);

        $this->postJson(route('services.watching', $service));
        $this->travel(5)->minutes(); // tab closed
        $this->postJson(route('services.watching', $service))->assertJsonPath('present', false);
        $this->assertSame(0, $service->members()->count());
    }

    public function test_opening_a_zoom_meeting_through_the_app_marks_them_present(): void
    {
        $service = $this->service(['join_url' => 'https://us02web.zoom.us/j/123456789']);
        $member = Member::factory()->active()->create();
        $this->actingAs($member->user);

        $this->get(route('services.join', $service))->assertSee('Join on Zoom');
        $this->post(route('services.open', $service))->assertRedirect('https://us02web.zoom.us/j/123456789');
        $this->assertTrue($service->members()->whereKey($member->id)->exists());
    }

    public function test_joining_counts_only_while_online_attendance_is_open_and_for_members(): void
    {
        $later = $this->service(['starts_at' => now()->addHours(3), 'join_url' => 'https://meet.google.com/abc-defg-hij']);
        $member = Member::factory()->active()->create();
        $this->actingAs($member->user)->post(route('services.open', $later));
        $this->postJson(route('services.watching', $later))->assertJsonPath('closed', true);
        $this->assertSame(0, $later->members()->count());

        $offline = $this->service(['kind' => 'midweek', 'online_checkin' => false]);
        $this->get(route('services.join', $offline))->assertNotFound();

        $pending = Member::factory()->create();
        $this->actingAs($pending->user)->get(route('services.join', $later))->assertForbidden();
    }

    public function test_staff_set_a_join_link_on_a_service(): void
    {
        $service = $this->service();
        $this->actingAs(User::factory()->admin()->create());

        $this->put(route('admin.attendance.update', $service), [
            'held_on' => today()->toDateString(), 'kind' => 'sunday', 'start_time' => '09:00', 'online_checkin' => '1',
            'join_url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('join_url');
        $this->put(route('admin.attendance.update', $service), [
            'held_on' => today()->toDateString(), 'kind' => 'sunday', 'start_time' => '09:00', 'online_checkin' => '1',
            'join_url' => 'https://zoom.us/j/99',
        ])->assertSessionHasNoErrors();
        $this->assertSame('https://zoom.us/j/99', $service->fresh()->join_url);
    }

    // ---- Welfare packages ----

    private function welfare(): array
    {
        $unit = Unit::factory()->welfare()->create();
        $hod = Member::factory()->active()->create(['unit_id' => $unit->id]);
        $unit->update(['hod_member_id' => $hod->id]);

        return [$unit, $hod];
    }

    public function test_the_welfare_unit_runs_a_package_and_members_apply(): void
    {
        Mail::fake();
        [$unit, $hod] = $this->welfare();

        $this->actingAs($hod->user)->post(route('unit.welfare.packages.store', $unit), [
            'title' => 'Christmas food package', 'items' => 'Rice, beans, oil', 'slots' => 1,
            'opens_at' => now()->subHour()->format('Y-m-d\TH:i'), 'closes_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            'collection_details' => 'Church office, Saturday 10am', 'is_published' => '1',
        ])->assertRedirect();
        $package = WelfarePackage::first();

        $ada = Member::factory()->active()->create(['name' => 'Ada Obi']);
        $tolu = Member::factory()->active()->create(['name' => 'Tolu Ade']);
        $this->actingAs($ada->user)->get(route('account'))->assertSee('1 open');
        $this->get(route('account.welfare'))->assertSee('Christmas food package')->assertSee('Rice, beans, oil');
        $this->post(route('account.welfare.apply', $package), ['household_size' => 4, 'note' => 'Lost my job'])->assertRedirect();
        $this->post(route('account.welfare.apply', $package), ['household_size' => 4])->assertSessionHas('status', fn ($s) => str_contains($s, 'already applied'));
        $this->actingAs($tolu->user)->post(route('account.welfare.apply', $package), ['household_size' => 2]);
        [$adaApp, $toluApp] = [WelfarePackageApplication::where('member_id', $ada->id)->first(), WelfarePackageApplication::where('member_id', $tolu->id)->first()];

        // The HOD sees the applications; the one place goes to Ada, Tolu waits.
        $this->actingAs($hod->user)->get(route('unit.welfare.packages.show', [$unit, $package]))->assertSee('Ada Obi')->assertSee('Lost my job');
        $this->post(route('unit.welfare.packages.decide', [$unit, $package, $adaApp]), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->post(route('unit.welfare.packages.decide', [$unit, $package, $toluApp]), ['status' => 'approved'])->assertSessionHasErrors('status');
        $this->post(route('unit.welfare.packages.decide', [$unit, $package, $toluApp]), ['status' => 'waitlist', 'decision_note' => 'We will call you'])->assertSessionHasNoErrors();
        Mail::assertSent(WelfarePackageDecision::class, fn ($m) => $m->hasTo($ada->email) && str_contains($m->render(), $adaApp->reference));

        $this->actingAs($ada->user)->get(route('account.welfare'))->assertSee($adaApp->reference)->assertSee('Church office, Saturday 10am');

        // Handing it over by reference, once.
        $this->actingAs($hod->user)->post(route('unit.welfare.packages.collect', [$unit, $package]), ['reference' => strtolower($adaApp->reference)])->assertSessionHasNoErrors();
        $this->assertSame('collected', $adaApp->fresh()->status);
        $this->post(route('unit.welfare.packages.collect', [$unit, $package]), ['reference' => $adaApp->reference])->assertSessionHasErrors('reference');
        $this->post(route('unit.welfare.packages.collect', [$unit, $package]), ['reference' => $toluApp->reference])->assertSessionHasErrors('reference');

        $csv = $this->get(route('unit.welfare.packages.export', [$unit, $package]))->assertOk()->streamedContent();
        $this->assertStringContainsString('Ada Obi', $csv);
    }

    public function test_package_rules_withdrawing_and_privacy(): void
    {
        [$unit] = $this->welfare();
        $package = WelfarePackage::create(['unit_id' => $unit->id, 'title' => 'School supplies', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'is_published' => true]);
        $closed = WelfarePackage::create(['unit_id' => $unit->id, 'title' => 'Old', 'opens_at' => now()->subWeek(), 'closes_at' => now()->subDay(), 'is_published' => true]);
        $member = Member::factory()->active()->create();
        $this->actingAs($member->user);

        $this->post(route('account.welfare.apply', $closed), ['household_size' => 1])->assertNotFound();
        $this->post(route('account.welfare.apply', $package), ['household_size' => 0])->assertSessionHasErrors('household_size');
        $this->post(route('account.welfare.apply', $package), ['household_size' => 3]);
        $application = WelfarePackageApplication::first();
        $this->post(route('account.welfare.withdraw', $application))->assertRedirect();
        $this->assertSame('withdrawn', $application->fresh()->status);
        $this->post(route('account.welfare.apply', $package), ['household_size' => 3]); // can apply again
        $this->assertSame('pending', $application->fresh()->status);

        $other = Member::factory()->active()->create();
        $this->actingAs($other->user)->post(route('account.welfare.withdraw', $application))->assertNotFound();
        $this->get(route('unit.welfare.packages.index', $unit))->assertForbidden();
        $this->actingAs(Member::factory()->create()->user)->post(route('account.welfare.apply', $package), ['household_size' => 1])->assertForbidden();
    }

    // ---- Messages ----

    public function test_the_inbox_shows_broadcasts_announcements_and_their_units_messages(): void
    {
        $choir = Unit::factory()->create(['name' => 'Choir']);
        $drama = Unit::factory()->create(['name' => 'Drama']);
        $member = Member::factory()->active()->create(['unit_id' => $choir->id]);
        $sent = Announcement::factory()->create(['title' => 'Choir auditions', 'published_at' => now()->subDays(2)]);
        Announcement::factory()->create(['title' => 'Thanksgiving Sunday', 'published_at' => now()->subDay()]);
        Broadcast::create(['announcement_id' => $sent->id, 'title' => 'Choir auditions', 'message' => 'Auditions this Saturday at 10am.', 'channels' => ['sms'], 'status' => 'sent']);
        Broadcast::create(['title' => 'Draft', 'message' => 'Not sent yet', 'channels' => ['sms'], 'status' => 'queued']);
        $choir->messages()->forceCreate(['unit_id' => $choir->id, 'subject' => 'Rehearsal moved', 'body' => 'Friday 6pm']);
        $drama->messages()->forceCreate(['unit_id' => $drama->id, 'subject' => 'Drama only', 'body' => 'Secret']);
        $this->actingAs($member->user);

        $this->get(route('account'))->assertSee('unread: </span>3', false);
        $page = $this->get(route('account.inbox'))->assertOk()
            ->assertSee('Auditions this Saturday at 10am.')
            ->assertSee('Thanksgiving Sunday')
            ->assertSee('Rehearsal moved')
            ->assertDontSee('Drama only')
            ->assertDontSee('Not sent yet');
        $this->assertSame(1, substr_count($page->getContent(), '<strong class="inbox__subject">Choir auditions</strong>'));

        $this->get(route('account'))->assertDontSee('unread: </span>', false);
    }

    public function test_broadcast_notifications_open_the_inbox(): void
    {
        $announcement = Announcement::factory()->create(['url' => null, 'published_at' => now()->subHour()]);
        config(['services.webpush.public_key' => 'x', 'services.webpush.private_key' => 'y']);
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.announcements.send.store', $announcement), ['channels' => ['push'], 'message' => 'Hello']);

        $this->assertSame(route('account.inbox'), Broadcast::first()->url);
    }
}
