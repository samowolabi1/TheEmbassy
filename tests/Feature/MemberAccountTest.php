<?php

namespace Tests\Feature;

use App\Mail\NewContactMessage;
use App\Models\ContactMessage;
use App\Models\Member;
use App\Models\Shop;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class MemberAccountTest extends TestCase
{
    use RefreshDatabase;

    private function joinData(array $overrides = []): array
    {
        return [
            'name' => 'Ada Obi', 'email' => 'ada@example.com', 'phone' => '0803 000 0000', 'password' => 'a-long-password',
            'password_confirmation' => 'a-long-password', 'agree' => '1', ...$overrides,
        ];
    }

    public function test_header_log_in_goes_to_the_member_sign_in(): void
    {
        $this->get('/')->assertSee(route('login'), false);
        $this->get(route('login'))->assertOk()->assertSee('Welcome back');
    }

    public function test_someone_joins_and_waits_for_approval(): void
    {
        $choir = Unit::create(['name' => 'Choir']);

        $this->get(route('join'))->assertOk()->assertSee('Choir');
        $this->post(route('join.store'), $this->joinData(['unit_id' => $choir->id]))->assertRedirect(route('account'));

        $member = Member::sole();
        $this->assertSame(Member::PENDING, $member->status);
        $this->assertSame('ada@example.com', $member->email);
        $this->assertSame($choir->id, $member->unit_id);
        $this->assertAuthenticatedAs($member->user);
        $this->assertNull($member->user->role);

        $this->get(route('account'))->assertSee('Waiting for approval')->assertSee('Choir')->assertSee('Tokens start once your membership is confirmed');
    }

    public function test_joining_needs_consent_and_a_free_email(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->post(route('join.store'), $this->joinData(['agree' => null]))->assertSessionHasErrors(['agree', 'email']);
        $this->assertDatabaseCount('members', 0);
    }

    public function test_a_signed_in_seller_can_join_without_a_new_password(): void
    {
        $user = User::factory()->create();
        Shop::factory()->approved()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('join.store'), ['name' => $user->name, 'phone' => '0803', 'agree' => '1'])->assertRedirect(route('account'));

        $this->assertSame($user->id, Member::sole()->user_id);
        $this->get(route('account'))->assertSee('My shop');
    }

    public function test_members_sign_in_and_land_on_their_account(): void
    {
        $user = User::factory()->create();
        Member::factory()->for($user)->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('account'));
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_staff_signing_in_on_the_site_go_to_the_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_members_update_their_details(): void
    {
        $member = Member::factory()->create();

        $this->actingAs($member->user)->put(route('account.update'), ['phone' => '0812 999 0000', 'address' => 'Yaba'])->assertRedirect();

        $this->assertSame('0812 999 0000', $member->fresh()->phone);
        $this->assertSame('Yaba', $member->fresh()->address);
    }

    public function test_account_needs_sign_in(): void
    {
        $this->get(route('account'))->assertRedirect(route('login'));
    }

    public function test_password_reset_for_members(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        Member::factory()->for($user)->create();

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            return str_contains($notification->toMail($user)->actionUrl, '/reset-password/');
        });

        $token = Password::broker()->createToken($user);
        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email, 'password' => 'brand-new-password', 'password_confirmation' => 'brand-new-password',
        ])->assertRedirect(route('login'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'brand-new-password'])->assertRedirect(route('account'));
    }

    public function test_contact_messages_reach_the_office_and_the_inbox(): void
    {
        Mail::fake();
        config(['church.email' => 'office@church.test']);

        $this->get(route('contact'))->assertOk();
        $this->post(route('contact.store'), [
            'name' => 'Tolu', 'email' => 'tolu@example.com', 'subject' => 'Visiting for the first time', 'message' => 'Is there a children\'s church?',
        ])->assertRedirect(route('contact'));

        $message = ContactMessage::sole();
        Mail::assertSent(NewContactMessage::class, fn ($mail) => $mail->hasTo('office@church.test') && $mail->hasReplyTo('tolu@example.com'));

        $this->actingAs(User::factory()->staff('secretary')->create())->get(route('admin.messages.index'))->assertSee('children');
        $this->patch(route('admin.messages.update', $message), ['read' => 1]);
        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_contact_honeypot_and_subject_list(): void
    {
        $this->post(route('contact.store'), ['name' => 'Bot', 'email' => 'b@x.com', 'subject' => 'Spam', 'message' => 'x'])->assertSessionHasErrors('subject');
        $this->post(route('contact.store'), ['name' => 'Bot', 'email' => 'b@x.com', 'subject' => 'General question', 'message' => 'x', 'website' => 'x'])->assertRedirect();
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_privacy_and_terms_pages(): void
    {
        $this->get(route('privacy'))->assertOk()->assertSee('Nigeria Data Protection');
        $this->get(route('terms'))->assertOk()->assertSee('pay sellers directly');
        $this->get('/')->assertSee(route('privacy'), false)->assertSee(route('contact'), false);
    }
}
