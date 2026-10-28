<?php

namespace Tests\Feature;

use App\Mail\GiftReceipt;
use App\Mail\TokenReminder;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\Fund;
use App\Models\Gift;
use App\Models\Member;
use App\Models\Payment;
use App\Models\TokenPayment;
use App\Models\TokenPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GivingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['services.paystack.secret_key' => 'sk_test', 'platform.fees.giving' => 1, 'platform.fees.tokens' => 1]);
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/g']])]);
    }

    private function confirm(Payment $payment)
    {
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => [
            'reference' => $payment->reference, 'status' => 'success', 'amount' => $payment->amount, 'currency' => 'NGN',
        ]])]);

        return $this->get('/payments/callback?reference='.$payment->reference);
    }

    private function finance(): User
    {
        return User::factory()->staff('finance')->create();
    }

    // ---- Online giving ----

    public function test_give_page_offers_the_church_funds(): void
    {
        $this->get(route('give'))->assertOk()->assertSee('Tithe')->assertSee('Building fund')->assertDontSee('One time');
        $this->assertSame(4, Fund::count());
    }

    public function test_a_guest_gives_online_and_gets_a_receipt(): void
    {
        $this->get(route('give'));
        $tithe = Fund::firstWhere('name', 'Tithe');

        $this->post(route('give.store'), ['amount' => '25,000', 'fund' => $tithe->id, 'email' => 'tolu@example.com', 'name' => 'Tolu Adeyemi'])
            ->assertRedirect('https://checkout.paystack.com/g');

        $gift = Gift::sole();
        $this->assertSame(2_500_000, $gift->amount);
        $this->assertSame(Gift::AWAITING_PAYMENT, $gift->status);
        $payment = Payment::sole();
        $this->assertSame(25_000, $payment->platform_fee);

        $this->confirm($payment)->assertRedirect($gift->url());
        $this->assertSame(Gift::PAID, $gift->fresh()->status);
        Mail::assertSent(GiftReceipt::class, fn ($mail) => $mail->hasTo('tolu@example.com'));
        $this->get($gift->url())->assertSee('God bless you, Tolu')->assertSee('₦25,000');
    }

    public function test_a_signed_in_members_gift_is_linked_to_them(): void
    {
        $this->get(route('give'));
        $member = Member::factory()->active()->create();

        $this->actingAs($member->user)->post(route('give.store'), ['amount' => '5000', 'fund' => Fund::first()->id, 'email' => $member->user->email]);

        $this->assertSame($member->id, Gift::sole()->member_id);
        $this->assertSame($member->name, Gift::sole()->name);
    }

    public function test_giving_is_validated_and_needs_payments_on(): void
    {
        $this->get(route('give'));
        $fund = Fund::first();

        $this->post(route('give.store'), ['amount' => '50', 'fund' => $fund->id, 'email' => 'a@b.com'])->assertSessionHasErrors('amount');
        $fund->update(['is_active' => false]);
        $this->post(route('give.store'), ['amount' => '5000', 'fund' => $fund->id, 'email' => 'a@b.com'])->assertSessionHasErrors('fund');

        config(['services.paystack.secret_key' => null]);
        $this->post(route('give.store'), ['amount' => '5000', 'fund' => Fund::where('is_active', true)->first()->id, 'email' => 'a@b.com'])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('gifts', 0);
    }

    public function test_the_thanks_page_needs_its_signed_link(): void
    {
        $this->get(route('give'));
        $gift = Gift::create(['fund_id' => Fund::first()->id, 'name' => 'A', 'amount' => 100, 'method' => 'online', 'given_on' => today()]);

        $this->get(route('give.thanks', $gift))->assertForbidden();
    }

    public function test_appeal_progress_shows_on_the_give_page(): void
    {
        $this->get(route('give'));
        $building = Fund::firstWhere('name', 'Building fund');
        $building->update(['target' => 1_000_000_00]);
        $gift = new Gift(['fund_id' => $building->id, 'name' => 'Jide', 'email' => 'j@x.com', 'amount' => 250_000_00, 'method' => 'cash', 'given_on' => today()]);
        $gift->status = Gift::PAID;
        $gift->save();

        $this->get(route('give'))->assertSee('₦250,000')->assertSee('of ₦1,000,000')->assertSee('1 giver so far')->assertDontSee('[raised]');
    }

    // ---- Admin: giving ----

    public function test_finance_records_a_cash_offering_and_sees_the_totals(): void
    {
        $this->actingAs($this->finance())->get(route('admin.finance.index'))->assertOk();
        $offering = Fund::firstWhere('name', 'Offering');

        $this->post(route('admin.gifts.store'), [
            'fund_id' => $offering->id, 'amount' => '342500', 'method' => 'cash', 'given_on' => today()->toDateString(), 'counted_by' => 'Bro. Femi & Sis. Ada',
        ])->assertRedirect();

        $gift = Gift::sole();
        $this->assertSame(Gift::PAID, $gift->status);
        $this->assertSame('Sunday offering', $gift->name);
        $this->assertStringStartsWith('CASH-', $gift->code);

        $this->get(route('admin.finance.index'))->assertSee('₦342,500')->assertSee('Bro. Femi &amp; Sis. Ada', false);
        $csv = $this->get(route('admin.finance.export'))->streamedContent();
        $this->assertStringContainsString('342500.00', $csv);
    }

    public function test_gifts_on_the_first_of_the_month_count(): void
    {
        $this->travelTo(now()->startOfMonth()->setTime(10, 0));
        $this->actingAs($this->finance())->get(route('admin.finance.index'));
        $this->post(route('admin.gifts.store'), ['fund_id' => Fund::first()->id, 'amount' => '1000', 'method' => 'cash', 'given_on' => today()->toDateString()]);

        $this->get(route('admin.finance.index'))->assertSee('₦1,000');
    }

    public function test_online_gifts_cannot_be_deleted(): void
    {
        $this->actingAs($this->finance())->get(route('admin.finance.index'));
        $gift = Gift::create(['fund_id' => Fund::first()->id, 'name' => 'A', 'amount' => 100, 'method' => 'online', 'given_on' => today()]);

        $this->delete(route('admin.gifts.destroy', $gift))->assertStatus(422);
    }

    public function test_media_staff_cannot_see_finances(): void
    {
        $this->actingAs(User::factory()->staff('media')->create())->get(route('admin.finance.index'))->assertForbidden();
    }

    // ---- Tokens ----

    public function test_tokens_owed_build_up_monthly_and_payments_clear_them(): void
    {
        $plan = TokenPlan::create(['name' => 'Standard', 'amount' => 200_000]);
        $member = Member::factory()->active($plan->id)->create(['tokens_from' => now()->subMonthsNoOverflow(2)->startOfMonth()]);

        $this->assertSame(3, $member->tokenMonthsOwed());
        $this->assertSame(600_000, $member->tokenAmountOwed());

        $this->actingAs($this->finance())->post(route('admin.tokens.store'), ['member_id' => $member->id, 'months' => 2, 'method' => 'cash'])->assertRedirect();
        $member->refresh()->load('tokenPayments');
        $this->assertSame(1, $member->tokenMonthsOwed());
        $this->assertSame('1 month behind', $member->tokenStatus());
        $this->assertTrue($member->nextTokenMonth()->isSameMonth(now()));
    }

    public function test_exempt_members_owe_nothing(): void
    {
        $exempt = TokenPlan::create(['name' => 'Exempt', 'amount' => 0]);
        $member = Member::factory()->active($exempt->id)->create(['tokens_from' => now()->subYear()]);

        $this->assertSame(0, $member->tokenMonthsOwed());
        $this->assertSame('Exempt', $member->tokenStatus());
        $this->actingAs($this->finance())->post(route('admin.tokens.store'), ['member_id' => $member->id, 'months' => 1, 'method' => 'cash'])->assertSessionHasErrors('member_id', null, 'tokens');
    }

    public function test_members_pay_tokens_online(): void
    {
        $plan = TokenPlan::create(['name' => 'Student', 'amount' => 100_000]);
        $member = Member::factory()->active($plan->id)->create();

        $this->actingAs($member->user)->get(route('account.tokens'))->assertSee('1 month behind');
        $this->post(route('account.tokens.pay'), ['months' => 3])->assertRedirect('https://checkout.paystack.com/g');

        $tokens = TokenPayment::sole();
        $this->assertSame(300_000, $tokens->amount);
        $this->assertTrue($tokens->from_month->isSameMonth(now()));
        $this->confirm(Payment::sole())->assertRedirect(route('account.tokens', ['paid' => $tokens->id]));

        $this->assertSame(TokenPayment::PAID, $tokens->fresh()->status);
        $this->assertSame('Up to date', $member->fresh()->tokenStatus());
        $this->get(route('account.tokens', ['paid' => $tokens->id]))->assertSee('Thank you!');
    }

    public function test_pending_members_cannot_pay_tokens_yet(): void
    {
        $member = Member::factory()->create(['token_plan_id' => TokenPlan::create(['name' => 'S', 'amount' => 100])->id]);

        $this->actingAs($member->user)->post(route('account.tokens.pay'), ['months' => 1])->assertSessionHasErrors('months');
    }

    public function test_arrears_list_and_reminders(): void
    {
        $plan = TokenPlan::create(['name' => 'Standard', 'amount' => 200_000]);
        $behind = Member::factory()->active($plan->id)->create(['name' => 'Ada Behind', 'tokens_from' => now()->subMonthsNoOverflow(3)->startOfMonth()]);
        Member::factory()->active($plan->id)->withoutLogin()->create(['name' => 'No Email', 'email' => null, 'phone' => '08030000000']);
        $this->actingAs($this->finance());

        $this->get(route('admin.finance.index'))->assertSee('Ada Behind')->assertSee('4 months')->assertSee('wa.me/2348030000000', false);

        $this->post(route('admin.tokens.remind'))->assertSessionHas('status', 'Sent 1 reminder by email.');
        Mail::assertSent(TokenReminder::class, fn ($mail) => $mail->hasTo($behind->email));
        $this->assertNotNull($behind->fresh()->token_reminded_at);

        // Not again within the week.
        $this->post(route('admin.tokens.remind'))->assertSessionHas('status', 'No reminders sent: nobody due a reminder has an email address.');
    }

    public function test_finance_manages_token_plans_with_one_default(): void
    {
        $this->actingAs($this->finance())->get(route('admin.finance.index'));
        $student = TokenPlan::firstWhere('name', 'Student');

        $this->put(route('admin.token-plans.update', $student), ['name' => 'Student', 'amount' => '1500', 'is_default' => '1'])->assertRedirect();

        $this->assertSame(150_000, $student->fresh()->amount);
        $this->assertSame(['Student'], TokenPlan::where('is_default', true)->pluck('name')->all());
    }

    // ---- Expenses ----

    public function test_expenses_are_logged_with_receipts_and_approved_by_someone_else(): void
    {
        Storage::fake('local');
        $logger = User::factory()->staff('finance')->create();
        $pastor = User::factory()->staff('pastor')->create();

        $this->actingAs($logger)->post(route('admin.expenses.store'), [
            'title' => 'Generator diesel', 'category' => 'Venue & utilities', 'amount' => '45000', 'spent_on' => today()->toDateString(),
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertRedirect(route('admin.expenses.index'));

        $expense = Expense::sole();
        $this->assertSame(Expense::PENDING, $expense->status);
        Storage::disk('local')->assertExists($expense->receipt_path);

        $this->post(route('admin.expenses.approve', $expense))->assertForbidden(); // not your own
        $this->get(route('admin.expenses.receipt', $expense))->assertOk();

        $this->actingAs($pastor)->post(route('admin.expenses.approve', $expense))->assertRedirect();
        $this->assertSame(Expense::APPROVED, $expense->fresh()->status);
        $this->assertSame($pastor->id, $expense->fresh()->decided_by);
        $this->post(route('admin.expenses.approve', $expense))->assertStatus(422);
    }

    public function test_declining_needs_a_reason(): void
    {
        $expense = new Expense(['title' => 'Snacks', 'category' => 'Hospitality', 'amount' => 100, 'spent_on' => today()]);
        $expense->save();

        $this->actingAs($this->finance())->post(route('admin.expenses.decline', $expense))->assertSessionHasErrors('reason');
        $this->post(route('admin.expenses.decline', $expense), ['reason' => 'Not budgeted'])->assertRedirect();
        $this->assertSame(Expense::DECLINED, $expense->fresh()->status);
    }

    public function test_receipts_must_be_images_or_pdfs(): void
    {
        $this->actingAs($this->finance())->post(route('admin.expenses.store'), [
            'title' => 'X', 'category' => 'Other', 'amount' => '10', 'spent_on' => today()->toDateString(),
            'receipt' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('receipt');
    }

    public function test_budgets_show_spending_against_them(): void
    {
        $this->actingAs($this->finance());
        $month = now()->format('Y-m');
        $categories = config('finance.expense_categories');

        $this->put(route('admin.budgets.update', ['month' => $month]), ['budgets' => [0 => '100000']])->assertRedirect();
        $this->assertSame(10_000_000, Budget::sole()->amount);
        $this->put(route('admin.budgets.update', ['month' => $month]), ['budgets' => [0 => '100000', 1 => '20000']])->assertRedirect();
        $this->assertSame(2, Budget::count());

        $expense = new Expense(['title' => 'Light bill', 'category' => $categories[0], 'amount' => 12_000_000, 'spent_on' => today()]);
        $expense->status = Expense::APPROVED;
        $expense->save();

        $this->get(route('admin.expenses.index', ['month' => $month]))->assertSee('₦120,000')->assertSee('of ₦100,000')->assertSee('budget-line__over');
    }
}
