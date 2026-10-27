<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class NoticeBoardTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paystack.secret_key' => self::SECRET,
            'services.paystack.subaccount' => 'ACCT_church',
            'platform.fees.notices' => 10,
            'notices.packages.week.price' => 5000,
            'notices.featured_price' => 5000,
        ]);

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc'],
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    private function form(array $overrides = []): array
    {
        return [
            'advertiser_type' => 'business',
            'organisation' => "Kemi's Kitchen",
            'contact_name' => 'Kemi Bello',
            'email' => 'kemi@example.com',
            'phone' => '0803 000 0000',
            'category' => 'Business',
            'title' => 'Party trays for your events',
            'body' => 'Jollof, small chops and more.',
            'url' => 'https://kemiskitchen.example.com',
            'package' => 'week',
            'featured' => '1',
            'agree' => '1',
            ...$overrides,
        ];
    }

    private function fakeVerify(Payment $payment, array $overrides = []): void
    {
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => [
                'reference' => $payment->reference, 'status' => 'success', 'amount' => $payment->amount,
                'currency' => 'NGN', 'channel' => 'card', ...$overrides,
            ]]),
        ]);
    }

    private function webhook(array $data, ?string $signature = null)
    {
        $body = json_encode(['event' => 'charge.success', 'data' => $data]);

        return $this->call('POST', '/webhooks/paystack', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature ?? hash_hmac('sha512', $body, self::SECRET),
        ], $body);
    }

    // ---- Placing a notice ----

    public function test_placing_a_notice_sends_the_advertiser_to_paystack_with_the_right_split(): void
    {
        $this->post('/notices', $this->form())->assertRedirect('https://checkout.paystack.com/abc');

        $notice = Notice::sole();
        $this->assertSame(Notice::AWAITING_PAYMENT, $notice->status);
        $this->assertSame(1_000_000, $notice->amount); // ₦5,000 week + ₦5,000 featured, in kobo
        $this->assertTrue($notice->featured);

        $payment = Payment::sole();
        $this->assertSame(100_000, $payment->platform_fee); // 10%

        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.paystack.co/transaction/initialize'
            && $r['amount'] === 1_000_000
            && $r['reference'] === $payment->reference
            && $r['subaccount'] === 'ACCT_church'
            && $r['transaction_charge'] === 100_000
            && $r->hasHeader('Authorization', 'Bearer '.self::SECRET));
    }

    public function test_image_is_stored_and_links_must_be_web_addresses(): void
    {
        Storage::fake('public');

        $this->post('/notices', $this->form(['image' => UploadedFile::fake()->image('flyer.jpg', 800, 450)]));
        Storage::disk('public')->assertExists(Notice::sole()->image_path);

        $this->post('/notices', $this->form(['url' => 'javascript:alert(1)']))->assertSessionHasErrors('url');
        $this->post('/notices', $this->form(['image' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php')]))->assertSessionHasErrors('image');
    }

    public function test_notices_need_the_guidelines_accepted(): void
    {
        $this->post('/notices', $this->form(['agree' => null]))->assertSessionHasErrors('agree');
        $this->assertDatabaseCount('notices', 0);
    }

    public function test_board_says_so_when_payments_are_off(): void
    {
        config(['services.paystack.secret_key' => null]);

        $this->get('/notices/advertise')->assertSee("Online payments aren't switched on yet", false);
        $this->post('/notices', $this->form())->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('notices', 0);
    }

    // ---- Paying ----

    public function test_returning_from_paystack_confirms_payment_and_shows_status(): void
    {
        $this->post('/notices', $this->form());
        $payment = Payment::sole();
        $this->fakeVerify($payment);

        $this->get('/payments/callback?reference='.$payment->reference)
            ->assertRedirectContains('/notices/'.$payment->payable_id.'/status');

        $this->assertTrue($payment->fresh()->isPaid());
        $notice = Notice::sole();
        $this->assertSame(Notice::IN_REVIEW, $notice->status);
        $this->get($notice->statusUrl())->assertSee('Paid: in review');
        // Paid isn't live: it still needs an admin.
        $this->get('/notices')->assertDontSee('Party trays for your events');
    }

    public function test_a_wrong_amount_is_not_treated_as_paid(): void
    {
        $this->post('/notices', $this->form());
        $payment = Payment::sole();
        $this->fakeVerify($payment, ['amount' => 100]);

        $this->get('/payments/callback?reference='.$payment->reference);

        $this->assertSame(Payment::FAILED, $payment->fresh()->status);
        $this->assertSame(Notice::AWAITING_PAYMENT, Notice::sole()->status);
    }

    public function test_signed_webhook_settles_the_payment_once(): void
    {
        $this->post('/notices', $this->form());
        $payment = Payment::sole();
        $data = ['reference' => $payment->reference, 'status' => 'success', 'amount' => $payment->amount, 'currency' => 'NGN'];

        $this->webhook($data)->assertOk();
        $paidAt = Notice::sole()->paid_at;
        $this->travel(5)->minutes();
        $this->webhook($data)->assertOk(); // Paystack retries; nothing changes.

        $this->assertTrue($payment->fresh()->isPaid());
        $this->assertTrue(Notice::sole()->paid_at->equalTo($paidAt));
    }

    public function test_unsigned_webhooks_are_refused(): void
    {
        $this->post('/notices', $this->form());
        $payment = Payment::sole();

        $this->webhook(['reference' => $payment->reference, 'status' => 'success', 'amount' => $payment->amount, 'currency' => 'NGN'], 'forged')
            ->assertUnauthorized();

        $this->assertFalse($payment->fresh()->isPaid());
    }

    public function test_status_page_needs_the_signed_link(): void
    {
        $notice = Notice::factory()->create();

        $this->get("/notices/{$notice->id}/status")->assertForbidden();
        $this->get($notice->statusUrl())->assertOk();
    }

    public function test_unpaid_notice_can_be_paid_again(): void
    {
        $notice = Notice::factory()->create(['amount' => 500000]);

        $this->post(URL::signedRoute('notices.pay', $notice))->assertRedirect('https://checkout.paystack.com/abc');
        $this->assertSame(1, $notice->payments()->count());
    }

    // ---- Review and going live ----

    public function test_admin_approves_and_notice_runs_for_its_days(): void
    {
        $notice = Notice::factory()->inReview()->create(['title' => 'Career fair', 'days' => 7, 'featured' => true]);
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/notices')->assertSee('Career fair');
        $this->post("/admin/notices/{$notice->id}/approve")->assertRedirect();

        $this->get('/notices')->assertSee('Career fair');
        $this->get('/')->assertSee('Career fair'); // featured notices show on the home page

        $this->travel(8)->days();
        $this->get('/notices')->assertDontSee('Career fair');
        $this->get('/admin/notices?show=expired')->assertSee('Career fair');
    }

    public function test_admin_rejects_with_a_reason_the_advertiser_sees(): void
    {
        $notice = Notice::factory()->inReview()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->post("/admin/notices/{$notice->id}/reject", ['reason' => 'Not suitable for a church audience'])->assertRedirect();

        $this->get($notice->statusUrl())->assertSee('Not suitable for a church audience');
        $this->get('/notices')->assertDontSee($notice->title);
    }

    public function test_unpaid_notices_cannot_be_approved(): void
    {
        $notice = Notice::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->post("/admin/notices/{$notice->id}/approve")->assertStatus(422);
        $this->assertSame(Notice::AWAITING_PAYMENT, $notice->fresh()->status);
    }

    public function test_guests_cannot_review_notices(): void
    {
        $notice = Notice::factory()->inReview()->create();

        $this->post("/admin/notices/{$notice->id}/approve")->assertRedirect(route('admin.login'));
        $this->assertSame(Notice::IN_REVIEW, $notice->fresh()->status);
    }
}
