<?php

namespace Tests\Feature;

use App\Mail\GiftReceipt;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\Fund;
use App\Models\Gift;
use App\Models\Member;
use App\Models\Payment;
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

    /**
     * The pastor in charge: the only one who sees giving.
     */
    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    private function donation(): Fund
    {
        Fund::ensureDefaults();

        return Fund::firstWhere('name', 'Building fund');
    }

    // ---- Online giving ----

    public function test_give_page_offers_the_church_funds(): void
    {
        $this->get(route('give'))->assertOk()->assertSee('Tithe')->assertSee('Building fund')->assertDontSee('One time');
        $this->assertSame(4, Fund::count());
    }

    public function test_a_guest_donates_online_and_gets_a_receipt(): void
    {
        $this->post(route('give.store'), ['amount' => '25,000', 'fund' => $this->donation()->id, 'email' => 'tolu@example.com', 'name' => 'Tolu Adeyemi'])
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

        $this->actingAs($member->user)->post(route('give.store'), ['amount' => '5000', 'fund' => $this->donation()->id, 'email' => $member->user->email]);

        $this->assertSame($member->id, Gift::sole()->member_id);
        $this->assertSame($member->name, Gift::sole()->name);
    }

    public function test_giving_is_validated_and_needs_payments_on(): void
    {
        $fund = $this->donation();

        $this->post(route('give.store'), ['amount' => '50', 'fund' => $fund->id, 'email' => 'a@b.com'])->assertSessionHasErrors('amount');
        $fund->update(['is_active' => false]);
        $this->post(route('give.store'), ['amount' => '5000', 'fund' => $fund->id, 'email' => 'a@b.com'])->assertSessionHasErrors('fund');

        config(['services.paystack.secret_key' => null]);
        $this->post(route('give.store'), ['amount' => '5000', 'fund' => Fund::firstWhere('name', 'Missions & welfare')->id, 'email' => 'a@b.com'])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('gifts', 0);
    }

    public function test_tithes_and_offerings_are_never_taken_through_paystack(): void
    {
        Fund::ensureDefaults();
        BankAccount::create(['currency' => 'NGN', 'bank_name' => 'Zenith Bank', 'account_name' => 'RCCG Royal Tribe', 'account_number' => '1012345678', 'use_for' => 'all']);
        BankAccount::create(['currency' => 'USD', 'bank_name' => 'GTBank Domiciliary', 'account_name' => 'RCCG Royal Tribe', 'account_number' => '0099887766', 'swift' => 'GTBINGLA', 'use_for' => 'tithe']);
        BankAccount::create(['currency' => 'GBP', 'bank_name' => 'Barclays', 'account_name' => 'RCCG Royal Tribe UK', 'account_number' => '41234567', 'sort_code' => '20-00-00', 'use_for' => 'donations']);
        BankAccount::create(['currency' => 'EUR', 'bank_name' => 'Hidden Bank', 'account_name' => 'X', 'account_number' => '1', 'is_active' => false]);

        foreach (['Tithe', 'Offering'] as $name) {
            $this->post(route('give.store'), ['amount' => '5000', 'fund' => Fund::firstWhere('name', $name)->id, 'email' => 'a@b.com'])->assertSessionHasErrors('fund');
        }
        $this->assertDatabaseCount('gifts', 0);

        $page = $this->get(route('give'))->assertOk()
            ->assertSee('We receive tithes and offerings by bank transfer only.')
            ->assertSee('1012345678')->assertSee('GTBINGLA')->assertSee('USD · US dollar')
            ->assertSee('20-00-00')
            ->assertDontSee('Hidden Bank');
        // The online form only offers donation funds.
        $form = substr($page->getContent(), strpos($page->getContent(), 'id="donate"'), 4000);
        $this->assertStringContainsString('Building fund', $form);
        $this->assertStringNotContainsString('>Tithe<', $form);
        $this->assertStringNotContainsString('>Offering<', $form);
    }

    public function test_staff_manage_bank_accounts_in_each_currency(): void
    {
        $this->actingAs($this->finance());

        $this->post(route('admin.accounts.store'), ['currency' => 'USD', 'bank_name' => 'Access Bank', 'account_name' => 'RCCG', 'account_number' => '123', 'swift' => 'bad', 'use_for' => 'all', 'is_active' => '1'])
            ->assertSessionHasErrors('swift', null, 'account');
        $this->post(route('admin.accounts.store'), ['currency' => 'USD', 'bank_name' => 'Access Bank', 'account_name' => 'RCCG', 'account_number' => '123', 'swift' => 'abngngla', 'use_for' => 'tithe', 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $account = BankAccount::sole();
        $this->assertSame('ABNGNGLA', $account->swift);

        $this->put(route('admin.accounts.update', $account), [...$account->only(['currency', 'bank_name', 'account_name', 'account_number', 'use_for'])]);
        $this->assertFalse($account->fresh()->is_active);
        $this->get(route('give'))->assertDontSee('Access Bank');

        $this->delete(route('admin.accounts.destroy', $account))->assertRedirect();
        $this->assertModelMissing($account);

        // A fund can be switched to transfer-only.
        $fund = $this->donation();
        $this->put(route('admin.funds.update', $fund), ['name' => $fund->name, 'is_active' => '1']);
        $this->assertFalse($fund->fresh()->online);
    }

    public function test_giving_history_is_only_for_the_administrator(): void
    {
        $member = Member::factory()->active()->create(['name' => 'Ada Obi']);
        $gift = new Gift(['fund_id' => $this->donation()->id, 'member_id' => $member->id, 'name' => 'Ada Obi', 'amount' => 777_700, 'method' => 'transfer', 'given_on' => today()]);
        $gift->status = Gift::PAID;
        $gift->save();

        // Not the member themselves.
        $this->actingAs($member->user)->get(route('account'))->assertOk()->assertDontSee('₦7,777');
        // Not finance staff, pastors, or even the administrator.
        foreach (['finance', 'pastor', 'admin'] as $role) {
            $staff = User::factory()->staff($role)->create();
            $this->actingAs($staff)->get(route('admin.finance.index'))->assertOk()->assertDontSee('₦7,777')->assertDontSee('Ada Obi');
            $this->get(route('admin.dashboard'))->assertDontSee('₦7,777')->assertDontSee('Latest gifts');
            $this->get(route('admin.finance.export'))->assertForbidden();
            $this->delete(route('admin.gifts.destroy', $gift))->assertForbidden();
        }
        $this->actingAs(User::factory()->staff('pastor')->create())->get(route('admin.members.show', $member))->assertOk()->assertDontSee('₦7,777');

        // The administrator sees it all.
        $this->actingAs($this->admin())->get(route('admin.finance.index'))->assertSee('₦7,777')->assertSee('Ada Obi');
        $this->get(route('admin.dashboard'))->assertSee('Latest gifts');
        $this->get(route('admin.members.show', $member))->assertSee('₦7,777');
        $this->assertStringContainsString('Ada Obi', $this->get(route('admin.finance.export'))->streamedContent());
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

    public function test_finance_records_a_cash_offering_and_the_administrator_sees_the_totals(): void
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

        $this->get(route('admin.finance.index'))->assertSee('recorded to Offering'); // their own confirmation, once
        $this->get(route('admin.finance.index'))->assertDontSee('₦342,500')->assertDontSee($gift->code);
        $this->actingAs($this->admin());
        $this->get(route('admin.finance.index'))->assertSee('₦342,500')->assertSee('Bro. Femi &amp; Sis. Ada', false);
        $csv = $this->get(route('admin.finance.export'))->streamedContent();
        $this->assertStringContainsString('342500.00', $csv);
    }

    public function test_gifts_on_the_first_of_the_month_count(): void
    {
        $this->travelTo(now()->startOfMonth()->setTime(10, 0));
        $this->actingAs($this->admin())->get(route('admin.finance.index'));
        $this->post(route('admin.gifts.store'), ['fund_id' => Fund::first()->id, 'amount' => '1000', 'method' => 'cash', 'given_on' => today()->toDateString()]);

        $this->get(route('admin.finance.index'))->assertSee('₦1,000');
    }

    public function test_online_gifts_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin())->get(route('admin.finance.index'));
        $gift = Gift::create(['fund_id' => Fund::first()->id, 'name' => 'A', 'amount' => 100, 'method' => 'online', 'given_on' => today()]);

        $this->delete(route('admin.gifts.destroy', $gift))->assertStatus(422);
    }

    public function test_media_staff_cannot_see_finances(): void
    {
        $this->actingAs(User::factory()->staff('media')->create())->get(route('admin.finance.index'))->assertForbidden();
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
