<?php

namespace Tests\Feature;

use App\Mail\StaffInvited;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SettingsAndRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_only_open_their_own_areas(): void
    {
        $media = User::factory()->staff('media')->create();

        $this->actingAs($media)->get('/admin')->assertOk()->assertSee('Content &amp; announcements', false)->assertDontSee('Giving &amp; tokens', false);
        $this->get(route('admin.content'))->assertOk();
        $this->get(route('admin.prayer-requests.index'))->assertForbidden();
        $this->get(route('admin.market.index'))->assertForbidden();
        $this->get(route('admin.settings.edit'))->assertForbidden();

        $finance = User::factory()->staff('finance')->create();
        $this->actingAs($finance)->get(route('admin.market.index'))->assertOk();
        $this->get(route('admin.content'))->assertForbidden();
    }

    public function test_staff_of_any_role_can_sign_in_to_admin(): void
    {
        $usher = User::factory()->staff('usher')->create();

        $this->post(route('admin.login.store'), ['email' => $usher->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
        $this->get('/admin')->assertOk()->assertSee('Ushering');
    }

    public function test_admin_adds_staff_who_get_a_set_password_link(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.staff.store'), ['name' => 'Bisi Eze', 'email' => 'Bisi@Example.com', 'role' => 'finance'])->assertRedirect();

        $bisi = User::firstWhere('email', 'bisi@example.com');
        $this->assertSame('finance', $bisi->role);
        Mail::assertSent(StaffInvited::class, fn ($mail) => $mail->hasTo('bisi@example.com') && str_contains($mail->setPasswordUrl, '/reset-password/'));

        $this->patch(route('admin.staff.update', $bisi), ['role' => 'pastor'])->assertRedirect();
        $this->assertSame('pastor', $bisi->fresh()->role);

        $this->delete(route('admin.staff.destroy', $bisi))->assertRedirect();
        $this->assertNull($bisi->fresh()->role);
    }

    public function test_the_last_admin_cannot_be_removed_or_demoted(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->patch(route('admin.staff.update', $admin), ['role' => 'media'])->assertSessionHasErrors('staff');
        $this->delete(route('admin.staff.destroy', $admin))->assertSessionHasErrors('staff');
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_unknown_roles_are_refused(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.staff.store'), ['name' => 'X', 'email' => 'x@example.com', 'role' => 'superuser'])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_saved_settings_replace_placeholders_across_the_site(): void
    {
        $this->get('/')->assertSee('[Venue address]');
        $this->actingAs(User::factory()->admin()->create());

        $this->put(route('admin.settings.update'), [
            'church__address' => '12 Crown Avenue, Lekki',
            'church__sunday_time' => '9:00 AM',
            'church__bank__name' => 'Zenith Bank',
            'church__bank__account_number' => '1012345678',
            'church__links__instagram' => 'https://instagram.com/royaltribe',
            'church__live__video_id' => 'dQw4w9WgXcQ',
            'church__live__is_live' => '1',
            'church__parking' => 'Free parking in the school compound',
        ])->assertRedirect(route('admin.settings.edit'));

        $this->assertSame('12 Crown Avenue, Lekki', Setting::firstWhere('key', 'church.address')->value);
        Setting::applyToConfig(); // what every request does at boot
        $this->get('/')->assertSee('12 Crown Avenue, Lekki')->assertSee('9:00 AM')->assertSee('https://instagram.com/royaltribe', false)->assertDontSee('[Venue address]');
        $this->get('/give')->assertSee('Zenith Bank')->assertSee('1012345678');
        $this->get('/watch')->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)->assertSee('LIVE ON YOUTUBE');
        $this->get('/about')->assertSee('Free parking in the school compound');
    }

    public function test_settings_are_validated(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->put(route('admin.settings.update'), [
            'church__links__instagram' => 'javascript:alert(1)',
            'church__map_embed_url' => 'https://evil.example/embed',
            'church__live__video_id' => '"><script>',
        ])->assertSessionHasErrors(['church__links__instagram', 'church__map_embed_url', 'church__live__video_id']);
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_settings_page_lists_staff_and_role_areas(): void
    {
        $this->actingAs(User::factory()->admin()->create(['name' => 'Pastor Ade']));
        User::factory()->staff('media')->create(['name' => 'Kunle Media']);

        $this->get(route('admin.settings.edit'))->assertOk()->assertSee('Kunle Media')->assertSee('What each role can open')->assertSee('Venue address');
    }

    public function test_the_dashboard_shows_each_role_its_own_panels(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk()
            ->assertSee('Members waiting for approval')->assertSee('Tokens up to date')->assertSee('Sunday attendance')->assertSee('Giving by fund');

        $this->actingAs(User::factory()->staff('usher')->create())->get('/admin')->assertOk()
            ->assertSee('Sunday attendance')->assertSee('First-timers not yet called')
            ->assertDontSee('Giving by fund')->assertDontSee('Unread prayer requests')->assertDontSee('Members waiting for approval');
    }
}
