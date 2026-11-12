<?php

namespace Tests\Feature;

use App\Mail\TokenOrderPlaced;
use App\Models\Declaration;
use App\Models\Duty;
use App\Models\Event;
use App\Models\Fellowship;
use App\Models\Member;
use App\Models\Product;
use App\Models\Quiz;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\TicketOrder;
use App\Models\TokenOrder;
use App\Models\TokenTransfer;
use App\Models\Unit;
use App\Models\User;
use App\Models\VendorPlan;
use App\Services\Tokens\Ledger;
use App\Services\Tokens\Rewards;
use App\Services\Tokens\TokenValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TokensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fundTreasury(1_000_000);
    }

    private function member(array $attributes = []): Member
    {
        return Member::factory()->active()->create($attributes);
    }

    private function service(array $overrides = []): Service
    {
        return Service::create([
            'held_on' => today(), 'kind' => 'sunday', 'starts_at' => now()->subMinutes(10), 'ends_at' => now()->addHours(2),
            'in_person_checkin' => true, 'online_checkin' => true, ...$overrides,
        ]);
    }

    /**
     * A listing in a subscribed shop that has an owner's login.
     */
    private function product(array $attributes = []): Product
    {
        $shop = Shop::factory()->listed()->create(['user_id' => $this->member()->user_id]); // the seller is a member, so can take tokens

        return Product::factory()->create(['shop_id' => $shop->id, ...$attributes]);
    }

    private function give(User $user, int $tokens): void
    {
        app(Ledger::class)->payOut($user, $tokens, 'grant', 'Test tokens');
    }

    private function balance(User $user): int
    {
        return app(Ledger::class)->balance($user);
    }

    public function test_nobody_pays_dues_any_more(): void
    {
        $member = $this->member();
        $this->actingAs($member->user)->get(route('account.tokens'))->assertOk()->assertSee('MY TOKENS')->assertDontSee('Pay tokens');
        $this->post('/account/tokens')->assertStatus(405);
        $this->assertFalse(\Schema::hasTable('token_plans'));
    }

    public function test_attendance_earns_tokens_once_per_service(): void
    {
        $member = $this->member();
        $service = $this->service(['join_url' => 'https://zoom.us/j/1']);
        $this->actingAs($member->user);

        $this->post(route('services.open', $service));
        $this->post(route('services.open', $service)); // twice: still once
        $this->assertSame(5, $this->balance($member->user));
        $this->get(route('account.tokens'))->assertSee('Joined online');
    }

    public function test_in_person_attendance_serving_and_ushers_marking_present(): void
    {
        $unit = Unit::factory()->create();
        $ada = $this->member(['unit_id' => $unit->id]);
        $tolu = $this->member();
        $service = $this->service();
        Duty::forceCreate(['unit_id' => $unit->id, 'service_id' => $service->id, 'member_id' => $ada->id, 'role' => 'Camera 1']);

        $this->actingAs(User::factory()->staff('usher')->create())
            ->post(route('admin.attendance.mark', $service), ['shown' => [$ada->id, $tolu->id], 'present' => [$ada->id, $tolu->id]]);

        $this->assertSame(15, $this->balance($ada->user)); // came + served
        $this->assertSame(10, $this->balance($tolu->user));

        // Marking again doesn't add more.
        $this->post(route('admin.attendance.mark', $service), ['shown' => [$ada->id], 'present' => [$ada->id]]);
        $this->assertSame(15, $this->balance($ada->user));
    }

    public function test_four_sundays_in_a_row_earn_a_bonus(): void
    {
        $member = $this->member();
        $rewards = app(Rewards::class);
        foreach ([21, 14, 7, 0] as $daysAgo) {
            $sunday = Service::create(['held_on' => today()->subDays($daysAgo), 'kind' => 'sunday']);
            $sunday->members()->attach($member->id, ['mode' => 'in_person', 'checked_in_at' => now()]);
            $rewards->attended($member, $sunday, 'in_person');
        }

        $this->assertSame(4 * 10 + 20, $this->balance($member->user));
        $this->assertTrue(TokenTransfer::where('key', 'like', 'sunday_streak:%')->exists());
    }

    public function test_quiz_community_declaration_profile_welcome_and_birthday(): void
    {
        $member = $this->member(['birthday' => today()->subYears(25)]);
        $user = $member->user;
        $rewards = app(Rewards::class);

        $quiz = Quiz::create(['title' => 'Q', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'is_published' => true]);
        $this->assertSame(5 + 3, $rewards->quiz($user, $quiz, 3));
        $this->assertSame(0, $rewards->quiz($user, $quiz, 3));

        $this->actingAs($user)->postJson(route('community.messages.store', 'general'), ['body' => 'Hello'])->assertCreated();
        $this->postJson(route('community.messages.store', 'general'), ['body' => 'Again'])->assertCreated();
        $this->assertSame(1, TokenTransfer::where('key', 'like', 'community_day:%')->count());

        Declaration::create(['week_of' => Declaration::weekStarting(today()), 'lines' => ['I am chosen.', '', '', '']]);
        $this->get(route('account.tokens'))->assertSee('I declare it');
        $this->post(route('account.declare'))->assertSessionHas('status', 'Amen! +2 tokens.');
        $this->post(route('account.declare'))->assertSessionHas('status', fn ($s) => str_contains($s, 'declared it this week'));

        $this->put(route('account.update'), ['phone' => '0803', 'birthday' => $member->birthday->toDateString(), 'address' => 'Yaba', 'unit_id' => Unit::factory()->create()->id, 'fellowship_id' => Fellowship::create(['name' => 'Yaba'])->id])
            ->assertSessionHas('status', fn ($s) => str_contains($s, '+10 tokens'));

        $this->artisan('tokens:daily')->assertSuccessful();
        $this->artisan('tokens:daily')->assertSuccessful(); // once a year
        $this->assertSame(1, TokenTransfer::where('key', 'like', 'birthday:%')->count());

        $pending = Member::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.members.approve', $pending));
        $this->assertSame(20, $this->balance($pending->user));
    }

    public function test_amounts_can_be_changed_or_switched_off(): void
    {
        $member = $this->member();
        config(['tokens.rules.attend_online' => 0]);
        $this->actingAs($member->user)->post(route('services.open', $this->service(['join_url' => 'https://zoom.us/j/1'])));
        $this->assertSame(0, $this->balance($member->user));

        config(['tokens.enabled' => false]);
        $this->assertSame(0, app(Rewards::class)->welcome($member->user));
    }

    public function test_buying_from_the_market_with_tokens(): void
    {
        Mail::fake();
        $product = $this->product(['name' => 'Royal hoodie', 'token_price' => 150, 'stock' => 1]);
        $buyer = $this->member()->user;
        $this->actingAs($buyer);

        // Not enough yet.
        $this->give($buyer, 100);
        $this->get(route('market.product', $product))->assertSee('this needs 150');
        $this->post(route('market.tokens', $product))->assertSessionHasErrors('tokens');
        $this->assertSame(100, $this->balance($buyer));

        $this->give($buyer, 100);
        $this->get(route('market.index'))->assertSee('For tokens')->assertSee('150 tokens');
        $this->post(route('market.tokens', $product), ['note' => 'Size L'])->assertRedirect(route('account.tokens'));
        $order = TokenOrder::sole();
        $this->assertSame([50, 0, 'pending'], [$this->balance($buyer), $product->fresh()->stock, $order->status]);
        Mail::assertSent(TokenOrderPlaced::class, fn ($m) => $m->hasTo($product->shop->email));

        // Sold out now.
        $other = $this->member()->user;
        $this->give($other, 500);
        $this->actingAs($other)->post(route('market.tokens', $product))->assertSessionHasErrors('tokens');

        // The seller hands it over with the code: the tokens are theirs.
        $seller = $product->shop->user;
        $this->actingAs($seller)->get(route('vendor.token-orders.index'))->assertSee('Royal hoodie')->assertSee('Size L');
        $this->post(route('vendor.token-orders.complete'), ['code' => strtolower($order->code)])->assertSessionHasNoErrors();
        $this->assertSame(150, $this->balance($seller));
        $this->post(route('vendor.token-orders.complete'), ['code' => $order->code])->assertSessionHasErrors('code');
    }

    public function test_cancelling_declining_and_expiry_return_the_tokens(): void
    {
        $product = $this->product(['token_price' => 40, 'stock' => 3]);
        $buyer = $this->member()->user;
        $this->give($buyer, 200);
        $this->actingAs($buyer);

        $this->post(route('market.tokens', $product));
        $this->post(route('token-orders.cancel', TokenOrder::latest('id')->first()))->assertSessionHasNoErrors();
        $this->assertSame([200, 3], [$this->balance($buyer), $product->fresh()->stock]);

        $this->post(route('market.tokens', $product));
        $this->actingAs($product->shop->user)->post(route('vendor.token-orders.decline', TokenOrder::latest('id')->first()));
        $this->assertSame(200, $this->balance($buyer));

        $this->actingAs($buyer)->post(route('market.tokens', $product));
        $this->travel(15)->days();
        $this->artisan('tokens:daily')->expectsOutputToContain('1 token orders expired');
        $this->assertSame(200, $this->balance($buyer));
        $this->assertSame('expired', TokenOrder::latest('id')->first()->cancelled_by);

        // Someone else's order can't be cancelled.
        $this->post(route('market.tokens', $product));
        $this->actingAs($this->member()->user)->post(route('token-orders.cancel', TokenOrder::latest('id')->first()))->assertNotFound();
    }

    public function test_only_listings_the_seller_opted_in_and_never_their_own(): void
    {
        $product = $this->product(['token_price' => null]);
        $buyer = $this->member()->user;
        $this->give($buyer, 1000);
        $this->actingAs($buyer)->post(route('market.tokens', $product))->assertSessionHasErrors('tokens');

        $own = $this->product(['token_price' => 10]);
        $this->give($own->shop->user, 100);
        $this->actingAs($own->shop->user)->post(route('market.tokens', $own))->assertSessionHasErrors('tokens');
    }

    public function test_sellers_set_a_token_price_and_pay_their_subscription_with_tokens(): void
    {
        VendorPlan::catalogue();
        $plan = tap(VendorPlan::firstWhere('key', 'monthly'))->update(['price' => 500_000, 'is_active' => true]); // ₦5,000
        $seller = $this->member()->user;
        $shop = Shop::factory()->approved()->create(['user_id' => $seller->id]);
        $this->actingAs($seller);

        $this->post(route('vendor.products.store'), ['type' => 'product', 'name' => 'Bible cover', 'description' => 'Leather', 'price' => 5000, 'accepts_tokens' => '1'])
            ->assertSessionHasErrors('token_price');
        $this->post(route('vendor.products.store'), ['type' => 'product', 'name' => 'Bible cover', 'description' => 'Leather', 'price' => 5000, 'accepts_tokens' => '1', 'token_price' => 400, 'is_active' => '1']);
        $this->assertSame(400, Product::firstWhere('name', 'Bible cover')->token_price);

        // ₦5,000 at ₦2 a token = 2,500 tokens, which go back to the treasury.
        $treasury = app(Ledger::class)->treasury()->balance;
        $this->give($seller, 2499);
        $this->post(route('vendor.subscription.store'), ['plan' => $plan->id, 'pay_with' => 'tokens'])->assertSessionHasErrors('pay_with');
        $this->give($seller, 1);
        $this->post(route('vendor.subscription.store'), ['plan' => $plan->id, 'pay_with' => 'tokens'])->assertSessionHasNoErrors();

        $subscription = $shop->subscriptions()->sole();
        $this->assertSame([2500, 0, 'active'], [$subscription->tokens_paid, $subscription->amount, $subscription->status]);
        $this->assertSame($treasury, app(Ledger::class)->treasury()->balance); // 2,500 out as grants, 2,500 back
        $this->assertSame(0, $this->balance($seller));
        $this->assertTrue($shop->fresh()->isSubscribed());
    }

    public function test_only_the_pastor_in_charge_creates_and_moves_tokens(): void
    {
        $member = $this->member(['name' => 'Ada Obi']);
        $ledger = app(Ledger::class);
        $treasury = $ledger->treasury()->balance;

        // Other staff, even administrators and pastors, can look but not touch.
        foreach (['admin', 'pastor', 'secretary'] as $role) {
            $this->actingAs(User::factory()->staff($role)->create());
            $this->get(route('admin.tokens.index'))->assertOk()->assertDontSee('Create tokens');
            $this->post(route('admin.tokens.mint'), ['amount' => 500, 'note' => 'x'])->assertForbidden();
            $this->post(route('admin.tokens.award'), ['member_id' => $member->id, 'direction' => 'give', 'amount' => 50, 'reason' => 'x'])->assertForbidden();
            $this->put(route('admin.tokens.settings'), [])->assertForbidden();
        }
        $this->actingAs(User::factory()->staff('finance')->create())->get(route('admin.tokens.index'))->assertForbidden();

        $this->actingAs(User::factory()->superAdmin()->create());
        $this->get(route('admin.tokens.index'))->assertOk()->assertSee('Create tokens')->assertSee('books balance');
        $this->post(route('admin.tokens.mint'), ['amount' => 5000, 'note' => 'December rewards'])->assertSessionHasNoErrors();
        $this->post(route('admin.tokens.award'), ['member_id' => $member->id, 'direction' => 'give', 'amount' => 50, 'reason' => 'Outreach volunteer'])->assertSessionHasNoErrors();
        $this->post(route('admin.tokens.award'), ['member_id' => $member->id, 'direction' => 'take', 'amount' => 80, 'reason' => 'Correction'])->assertSessionHasErrorsIn('award', 'amount');
        $this->post(route('admin.tokens.award'), ['member_id' => $member->id, 'direction' => 'take', 'amount' => 20, 'reason' => 'Correction'])->assertSessionHasNoErrors();
        $this->post(route('admin.tokens.burn'), ['amount' => 1000, 'note' => 'Too many'])->assertSessionHasNoErrors();

        $this->assertSame(30, $this->balance($member->user));
        $this->assertSame($treasury + 5000 - 50 + 20 - 1000, $ledger->treasury()->balance);
        $audit = $ledger->audit();
        $this->assertTrue($audit['ok'], implode(' ', $audit['problems']));
        $this->assertSame($audit['supply'], $audit['held']);
        $this->get(route('admin.tokens.index', ['member' => $member->id]))->assertSee('Outreach volunteer')->assertSee('Ada Obi');
    }

    public function test_only_the_pastor_in_charge_sets_amounts_and_value(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.settings.edit'))->assertOk()->assertDontSee('Coming to church (per service)');
        // The Settings page can't change token settings either.
        $this->put(route('admin.settings.update'), ['tokens__rules__attend_in_person' => 999, 'tokens__naira_per_token' => 50]);
        $this->assertNull(Setting::firstWhere('key', 'tokens.rules.attend_in_person'));

        $this->actingAs(User::factory()->superAdmin()->create());
        $rules = collect(Rewards::RULES)->map(fn () => 3)->all();
        $this->put(route('admin.tokens.settings'), ['naira_per_token' => 5, 'daily_limit' => 200, 'enabled' => '1', 'transfers_enabled' => '1', 'rules' => [...$rules, 'attend_in_person' => 25]])->assertSessionHasNoErrors();
        Setting::applyToConfig();

        $this->assertSame(25, app(Rewards::class)->amount('attend_in_person'));
        $this->assertSame(1000, TokenValue::tokensFor(500_000)); // ₦5,000 at ₦5 a token
    }

    public function test_rewards_pause_when_the_treasury_is_empty(): void
    {
        $ledger = app(Ledger::class);
        $ledger->burn($ledger->treasury()->balance, User::factory()->superAdmin()->create(), 'Empty it');
        $member = $this->member();

        $this->assertSame(0, app(Rewards::class)->welcome($member->user));
        $this->assertSame(0, $this->balance($member->user));
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.tokens.index'))->assertSee('The treasury ran short');
    }

    public function test_members_send_tokens_to_each_other_within_limits(): void
    {
        config(['tokens.transfers.daily_limit' => 100]);
        $ada = $this->member(['phone' => '0803 111 2222']);
        $tolu = $this->member(['phone' => '08055556666', 'email' => 'tolu@example.com']);
        $this->give($ada->user, 300);
        $this->actingAs($ada->user);

        $this->post(route('account.tokens.send'), ['to' => '+234 805 555 6666', 'amount' => 60, 'note' => 'Happy birthday'])->assertSessionHasNoErrors();
        $this->post(route('account.tokens.send'), ['to' => 'tolu@example.com', 'amount' => 50])->assertSessionHasErrors('amount'); // over 100 today
        $this->post(route('account.tokens.send'), ['to' => '0803 111 2222', 'amount' => 1])->assertSessionHasErrors('to'); // yourself
        $this->post(route('account.tokens.send'), ['to' => 'nobody@example.com', 'amount' => 1])->assertSessionHasErrors('to');
        $this->assertSame([240, 60], [$this->balance($ada->user), $this->balance($tolu->user)]);
        $this->actingAs($tolu->user)->get(route('account.tokens'))->assertSee('Happy birthday');

        $this->travel(1)->days();
        $this->actingAs($ada->user)->post(route('account.tokens.send'), ['to' => 'tolu@example.com', 'amount' => 50])->assertSessionHasNoErrors();

        config(['tokens.transfers.enabled' => false]);
        $this->post(route('account.tokens.send'), ['to' => 'tolu@example.com', 'amount' => 1])->assertForbidden();
    }

    public function test_a_wallet_on_hold_can_receive_but_not_spend(): void
    {
        $member = $this->member();
        $this->give($member->user, 100);
        $product = $this->product(['token_price' => 10]);
        $this->actingAs(User::factory()->superAdmin()->create())->post(route('admin.tokens.freeze', $member), ['reason' => 'Checking something']);

        $this->give($member->user, 5);
        $this->assertSame(105, $this->balance($member->user));
        $this->actingAs($member->user)->post(route('market.tokens', $product))->assertSessionHasErrors('tokens');
        $this->get(route('account.tokens'))->assertSee('Your wallet is on hold');
    }

    public function test_event_tickets_paid_with_tokens_go_back_to_the_treasury(): void
    {
        $event = Event::factory()->create(['is_published' => true, 'starts_at' => now()->addWeek()]);
        $type = $event->ticketTypes()->create(['name' => 'Regular', 'price' => 200_000, 'quantity' => 10, 'max_per_order' => 4]); // ₦2,000
        $member = $this->member();
        $this->give($member->user, 2000);
        $ledger = app(Ledger::class);
        $before = $ledger->treasury()->balance;
        config(['services.paystack.secret_key' => 'sk_test']);
        Http::fake(['*' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/x']])]);

        $this->actingAs($member->user)->post(route('events.book', $event), ['name' => 'Ada', 'email' => 'a@example.com', 'quantities' => [$type->id => 1]]);
        $order = TicketOrder::sole();
        $this->get(URL::signedRoute('tickets.show', $order))->assertSee('Pay 🪙 1,000 tokens');
        $this->post(URL::signedRoute('tickets.tokens', $order))->assertRedirect($order->url());

        $this->assertTrue($order->fresh()->isConfirmed());
        $this->assertSame(1000, $order->fresh()->tokens_paid);
        $this->assertSame(1000, $this->balance($member->user));
        $this->assertSame($before + 1000, $ledger->treasury()->balance);
    }

    public function test_only_the_pastor_in_charge_makes_another(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.staff.store'), ['name' => 'X', 'email' => 'x@example.com', 'role' => 'super'])->assertForbidden();
        $super = User::factory()->superAdmin()->create();
        $this->patch(route('admin.staff.update', $super), ['role' => 'admin'])->assertForbidden();

        $this->actingAs($super)->post(route('admin.staff.store'), ['name' => 'X', 'email' => 'x@example.com', 'role' => 'super'])->assertSessionHasNoErrors();
        $this->assertTrue(User::firstWhere('email', 'x@example.com')->isSuperAdmin());
    }

    public function test_balances_never_go_below_zero(): void
    {
        $user = $this->member()->user;
        $this->give($user, 10);
        $ledger = app(Ledger::class);

        $ledger->move($ledger->account($user), $ledger->treasury(), 10, 'reclaim', 'All of it');
        $this->expectException(ValidationException::class);
        $ledger->move($ledger->account($user), $ledger->treasury(), 1, 'reclaim', 'One more');
    }

    public function test_only_members_use_tokens(): void
    {
        VendorPlan::catalogue();
        $plan = tap(VendorPlan::firstWhere('key', 'monthly'))->update(['price' => 500_000, 'is_active' => true]);
        $outsider = User::factory()->create(); // a seller who isn't a church member
        $shop = Shop::factory()->approved()->create(['user_id' => $outsider->id]);
        $this->actingAs($outsider);

        // No token option for their listings or subscription; money is offered as before.
        $this->get(route('vendor.products.create'))->assertOk()->assertDontSee('accepts_tokens');
        $this->get(route('vendor.subscription.index'))->assertOk()->assertDontSee('value="tokens"', false);
        $this->post(route('vendor.products.store'), ['type' => 'product', 'name' => 'Mug', 'description' => 'Ceramic', 'price' => 3000, 'accepts_tokens' => '1', 'token_price' => 100, 'is_active' => '1']);
        $this->assertNull(Product::firstWhere('name', 'Mug')->token_price);
        $this->post(route('vendor.subscription.store'), ['plan' => $plan->id, 'pay_with' => 'tokens'])->assertSessionHasErrors('pay_with');
        $this->get(route('vendor.token-orders.index'))->assertNotFound();

        // They can't earn, hold, buy with or be sent tokens.
        $this->assertSame(0, app(Rewards::class)->welcome($outsider));
        $this->assertSame(0, $this->balance($outsider));
        $this->post(route('market.tokens', $this->product(['token_price' => 10])))->assertSessionHasErrors('tokens');
        $this->get(route('account.tokens'))->assertOk()->assertSee('Tokens are for church members.');
        $this->assertThrows(fn () => app(Ledger::class)->payOut($outsider, 5, 'grant', 'Gift'), ValidationException::class);

        // A member seller's listing stops taking tokens if they're no longer a member.
        $product = $this->product(['token_price' => 10]);
        $this->assertTrue($product->acceptsTokens());
        $product->shop->user->member->forceFill(['status' => Member::INACTIVE])->save();
        $this->assertFalse($product->fresh()->acceptsTokens());
    }
}
