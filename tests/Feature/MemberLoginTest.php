<?php

namespace Tests\Feature;

use App\Mail\ReminderMail;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberLoginTest extends TestCase
{
    use RefreshDatabase;

    private User $office;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->office = User::factory()->create(['role' => 'secretary']);
    }

    private function inviteUrl(string $email): string
    {
        $mail = Mail::sent(ReminderMail::class, fn ($m) => $m->hasTo($email))->last();
        $this->assertNotNull($mail, 'No invitation was emailed to '.$email);

        return $mail->url;
    }

    public function test_the_office_adds_a_member_and_sends_a_link_that_works_for_a_week(): void
    {
        $this->actingAs($this->office)->post(route('admin.members.store'), [
            'first_name' => 'Ada', 'last_name' => 'Obi', 'gender' => 'female', 'phone' => '0803 123 4567', 'email' => 'ada@example.com', 'login' => 'link',
        ])->assertSessionHas('status', fn ($s) => str_contains($s, 'sent a link to choose a password'));
        $member = Member::where('email', 'ada@example.com')->sole();
        $this->assertNotNull($member->user);
        $url = $this->inviteUrl('ada@example.com');
        $this->assertStringContainsString('invite=1', $url);
        $this->get(route('admin.members.show', $member))->assertSee('Sent a link');

        // Three days later the link still works.
        auth()->logout();
        $this->travel(3)->days();
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->get($url)->assertOk()->assertSee('name="invite"', false);
        $this->post(route('password.update'), ['token' => $token, 'email' => 'ada@example.com', 'invite' => '1', 'password' => 'a-good-password', 'password_confirmation' => 'a-good-password'])
            ->assertRedirect(route('login'));

        // She signs in with her phone number, written any way.
        $this->post(route('login.store'), ['email' => '+234 803 123 4567', 'password' => 'a-good-password', 'remember' => '1'])->assertRedirect();
        $this->assertAuthenticatedAs($member->user);
        $this->assertNotNull($member->user->fresh()->last_login_at);
    }

    public function test_a_temporary_password_must_be_changed_at_first_sign_in(): void
    {
        $member = Member::factory()->active()->create(['name' => 'Tunde Bello', 'email' => null, 'phone' => '0805 999 0000', 'user_id' => null]);
        $this->actingAs($this->office)->get(route('admin.members.show', $member))->assertOk()->assertSee('No login yet')->assertSee('Give a temporary password')->assertDontSee('Send a link to set a password</button>', false);
        $this->post(route('admin.members.login.temporary', $member))->assertRedirect(route('admin.members.show', $member).'#login')->assertSessionHas('tempPassword');
        $temp = session('tempPassword');
        $this->get(route('admin.members.show', $member))->assertSee($temp)->assertSee('Send on WhatsApp');
        $user = $member->fresh()->user;
        $this->assertNull($user->email);
        $this->assertTrue($user->must_change_password);

        auth()->logout();
        $this->post(route('login.store'), ['email' => '08059990000', 'password' => $temp])->assertRedirect(route('account.password.edit'));
        // Everything else waits until they choose a password.
        $this->get(route('account'))->assertRedirect(route('account.password.edit'));
        $this->get(route('account.password.edit'))->assertOk()->assertSee('temporary password from the church office');
        $this->put(route('account.password.update'), ['current_password' => 'wrong', 'password' => 'my-own-password', 'password_confirmation' => 'my-own-password'])->assertSessionHasErrors('current_password');
        $this->put(route('account.password.update'), ['current_password' => $temp, 'password' => 'my-own-password', 'password_confirmation' => 'my-own-password'])->assertRedirect();
        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('my-own-password', $user->password));
        $this->get(route('account'))->assertOk();
    }

    public function test_keep_me_signed_in_lasts_ninety_days_and_is_ticked(): void
    {
        $member = Member::factory()->active()->create();
        $member->user->forceFill(['password' => Hash::make('secret-password')])->save();
        $this->get(route('login'))->assertOk()->assertSee('name="remember" value="1" checked', false)->assertSee('90 days')->assertSee('Email or phone number');

        $response = $this->post(route('login.store'), ['email' => $member->user->email, 'password' => 'secret-password', 'remember' => '1']);
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
        $this->assertNotNull($cookie, 'No "keep me signed in" cookie was set.');
        $this->assertEqualsWithDelta(now()->addDays(90)->timestamp, $cookie->getExpiresTime(), 120);
    }

    public function test_links_for_everyone_without_a_login_and_clashing_emails(): void
    {
        Member::factory()->active()->count(2)->sequence(['email' => 'a@example.com'], ['email' => 'b@example.com'])->create(['user_id' => null]);
        Member::factory()->active()->create(['email' => null, 'user_id' => null]);
        $this->actingAs($this->office)->get(route('admin.members.index'))->assertOk()->assertSee('2 members')->assertSee('Send 2 links');
        $this->post(route('admin.members.login.invite-all'))->assertSessionHas('status', 'Sent set-password links to 2 members.');
        Mail::assertSent(ReminderMail::class, 2);
        $this->get(route('admin.members.index', ['login' => 'none']))->assertOk();

        // Someone else already signs in with that email.
        $other = Member::factory()->active()->create();
        $clash = Member::factory()->active()->create(['email' => $other->user->email, 'user_id' => null]);
        $this->post(route('admin.members.login.invite', $clash))->assertSessionHasErrors('login');

        // Members change their own password from their account.
        $other->user->forceFill(['password' => Hash::make('old-password-1')])->save();
        $this->actingAs($other->user->fresh())->get(route('account'))->assertSee('Change password');
        $this->put(route('account.password.update'), ['current_password' => 'old-password-1', 'password' => 'new-password-2', 'password_confirmation' => 'new-password-2'])->assertSessionHas('status', 'Password changed.');
    }
}
