<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Declaration;
use App\Models\NewsletterSubscriber;
use App\Models\PrayerRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create(['name' => 'Pastor Ade']);
    }

    // ---- Access ----

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/prayer-requests')->assertRedirect(route('admin.login'));
    }

    public function test_non_admin_accounts_are_refused(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admin_can_sign_in_and_out(): void
    {
        $this->admin();

        $this->post('/admin/login', ['email' => User::first()->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();

        $this->post('/admin/logout')->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_non_admin_cannot_sign_in(): void
    {
        $user = User::factory()->create();

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected_and_rate_limited(): void
    {
        $admin = $this->admin();

        foreach (range(1, 5) as $_) {
            $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }

        // Even the right password is refused while locked out.
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertStringStartsWith('Too many attempts.', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_every_admin_page_loads(): void
    {
        $this->actingAs($admin = $this->admin());
        $announcement = Announcement::factory()->create();

        foreach (['/admin', '/admin/prayer-requests', '/admin/subscribers', '/admin/content', "/admin/announcements/{$announcement->id}/edit"] as $path) {
            $this->get($path)->assertOk()->assertSee('Church admin');
        }
        $this->get('/admin')->assertSee('Pastor');
    }

    // ---- Prayer requests ----

    public function test_prayer_inbox_lists_requests_and_marks_them_read(): void
    {
        $this->actingAs($this->admin());
        $request = PrayerRequest::create(['name' => 'Tolu', 'request' => 'Pray for my exams', 'is_private' => true]);

        $this->get('/admin/prayer-requests')->assertSee('Pray for my exams')->assertSee('Private');
        $this->get('/admin')->assertSee('Unread prayer requests');

        $this->patch("/admin/prayer-requests/{$request->id}", ['read' => 1])->assertRedirect();
        $this->assertNotNull($request->fresh()->read_at);
        $this->get('/admin/prayer-requests?show=unread')->assertDontSee('Pray for my exams');

        $this->patch("/admin/prayer-requests/{$request->id}", ['read' => 0]);
        $this->assertNull($request->fresh()->read_at);
    }

    public function test_prayer_request_can_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $request = PrayerRequest::create(['request' => 'Delete me']);

        $this->delete("/admin/prayer-requests/{$request->id}")->assertRedirect();
        $this->assertModelMissing($request);
    }

    // ---- Subscribers ----

    public function test_subscribers_can_be_searched_exported_and_removed(): void
    {
        $this->actingAs($this->admin());
        $ada = NewsletterSubscriber::create(['email' => 'ada@example.com']);
        NewsletterSubscriber::create(['email' => '=cmd@example.com']);

        $this->get('/admin/subscribers?q=ada')->assertSee('ada@example.com')->assertDontSee('=cmd@example.com');

        $csv = $this->get('/admin/subscribers/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('ada@example.com', $csv);
        // Formula-like cells are prefixed so spreadsheets treat them as text.
        $this->assertStringContainsString("'=cmd@example.com", $csv);

        $this->delete("/admin/subscribers/{$ada->id}")->assertRedirect();
        $this->assertModelMissing($ada);
    }

    // ---- Announcements ----

    public function test_publishing_an_announcement_puts_it_on_the_home_page(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/announcements', [
            'title' => 'Youth picnic', 'body' => 'Saturday at noon.', 'tag' => 'This Saturday',
            'tone' => 'ice', 'url' => '/events', 'when' => 'now',
        ])->assertRedirect(route('admin.content'));

        $this->get('/')->assertSee('Youth picnic')->assertSee('href="/events"', false);
    }

    public function test_drafts_stay_off_the_home_page(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/announcements', [
            'title' => 'Secret plan', 'body' => 'Not yet.', 'tag' => 'Units', 'tone' => 'wash', 'when' => 'draft',
        ]);

        $this->assertSame('draft', Announcement::sole()->status());
        $this->get('/')->assertDontSee('Secret plan');
    }

    public function test_scheduled_announcements_go_live_at_their_time(): void
    {
        $this->actingAs($this->admin());
        $at = now()->addDays(2)->setTime(9, 0);

        $this->post('/admin/announcements', [
            'title' => 'Fasting week', 'body' => 'Starts Monday.', 'tag' => 'Prayer', 'tone' => 'wash',
            'when' => 'schedule', 'publish_at' => $at->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        $this->get('/')->assertDontSee('Fasting week');
        $this->travelTo($at->copy()->addMinute());
        $this->get('/')->assertSee('Fasting week');
    }

    public function test_announcement_links_must_be_web_addresses(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/announcements', [
            'title' => 'Bad link', 'body' => 'x', 'tag' => 'x', 'tone' => 'wash', 'when' => 'now', 'url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('url');

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_scheduling_needs_a_future_time(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/announcements', [
            'title' => 'Late', 'body' => 'x', 'tag' => 'x', 'tone' => 'wash', 'when' => 'schedule', 'publish_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('publish_at');
    }

    public function test_announcement_can_be_edited_and_deleted(): void
    {
        $this->actingAs($this->admin());
        $announcement = Announcement::factory()->create(['title' => 'Old title', 'published_at' => now()->subWeek()]);
        $liveSince = $announcement->published_at;

        $this->put("/admin/announcements/{$announcement->id}", [
            'title' => 'New title', 'body' => 'Updated.', 'tag' => 'Units', 'tone' => 'ice', 'when' => 'now',
        ])->assertRedirect(route('admin.content'));

        $announcement->refresh();
        $this->assertSame('New title', $announcement->title);
        // Keeping it live doesn't bump it to the top of the list.
        $this->assertTrue($announcement->published_at->equalTo($liveSince));

        $this->delete("/admin/announcements/{$announcement->id}")->assertRedirect(route('admin.content'));
        $this->assertModelMissing($announcement);
    }

    // ---- Declarations ----

    public function test_saving_this_weeks_declaration_updates_the_home_page(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/declarations', [
            'week_of' => now()->toDateString(),
            'lines' => ['I am loved.', 'I am called.', 'I am kept.', 'I am His.'],
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Declaration::sole()->week_of->isSameDay(now()->startOfWeek()));
        $this->get('/')->assertSee('I am called.');
    }

    public function test_next_weeks_declaration_waits_until_monday(): void
    {
        $this->actingAs($this->admin());
        Declaration::factory()->create(['week_of' => now()->startOfWeek(), 'lines' => ['Now A', 'Now B', 'Now C', 'Now D']]);

        $this->post('/admin/declarations', [
            'week_of' => now()->addWeek()->toDateString(),
            'lines' => ['Next A', 'Next B', 'Next C', 'Next D'],
        ]);

        $this->get('/')->assertSee('Now A')->assertDontSee('Next A');
        $this->travelTo(now()->addWeek()->startOfWeek()->addHour());
        $this->get('/')->assertSee('Next A');
    }

    public function test_declaration_needs_four_lines(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/declarations', ['week_of' => now()->toDateString(), 'lines' => ['One', '', 'Three', 'Four']])
            ->assertSessionHasErrors('lines.1');
    }

    public function test_content_page_ignores_a_bad_week(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/content?week=not-a-date')->assertRedirect();
    }

    // ---- Command ----

    public function test_admin_create_command_makes_an_admin(): void
    {
        $this->artisan('admin:create', ['email' => 'pastor@example.com', '--name' => 'Pastor Ade'])
            ->expectsQuestion('Password (leave empty to generate one)', 'a-very-long-password')
            ->assertSuccessful();

        $user = User::firstWhere('email', 'pastor@example.com');
        $this->assertTrue($user->is_admin);
        $this->assertSame('Pastor Ade', $user->name);

        $this->artisan('admin:revoke', ['email' => 'pastor@example.com'])->assertSuccessful();
        $this->assertFalse($user->fresh()->is_admin);
    }
}
