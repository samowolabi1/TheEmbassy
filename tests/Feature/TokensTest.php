<?php

namespace Tests\Feature;

use App\Mail\TokenOrderPlaced;
use App\Models\Declaration;
use App\Models\Duty;
use App\Models\Fellowship;
use App\Models\Member;
use App\Models\Product;
use App\Models\Quiz;
use App\Models\Service;
use App\Models\Shop;
use App\Models\TokenOrder;
use App\Models\TokenTransaction;
use App\Models\Unit;
use App\Models\User;
use App\Models\VendorPlan;
use App\Services\Tokens\Rewards;
use App\Services\Tokens\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TokensTest extends TestCase
{
    use RefreshDatabase;

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
        $shop = Shop::factory()->listed()->create(['user_id' => User::factory()->create()->id]);

        return Product::factory()->create(['shop_id' => $shop->id, ...$attributes]);
    }

    private function give(User $user, int $tokens): void
    {
        app(Wallet::class)->credit($user, $tokens, 'admin', 'Test tokens');
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
        $this->assertSame(5, $member->user->fresh()->token_balance);
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

        $this->assertSame(15, $ada->user->fresh()->token_balance); // came + served
        $this->assertSame(10, $tolu->user->fresh()->token_balance);

        // Marking again doesn't add more.
        $this->post(route('admin.attendance.mark', $service), ['shown' => [$ada->id], 'present' => [$ada->id]]);
        $this->assertSame(15, $ada->user->fresh()->token_balance);
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

        $this->assertSame(4 * 10 + 20, $member->user->fresh()->token_balance);
        $this->assertTrue(TokenTransaction::where('rule', 'sunday_streak')->exists());
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
        $this->assertSame(1, TokenTransaction::where('rule', 'community_day')->count());

        Declaration::create(['week_of' => Declaration::weekStarting(today()), 'lines' => ['I am chosen.', '', '', '']]);
        $this->get(route('account.tokens'))->assertSee('I declare it');
        $this->post(route('account.declare'))->assertSessionHas('status', 'Amen! +2 tokens.');
        $this->post(route('account.declare'))->assertSessionHas('status', fn ($s) => str_contains($s, 'declared it this week'));

        $this->put(route('account.update'), ['phone' => '0803', 'birthday' => $member->birthday->toDateString(), 'address' => 'Yaba', 'unit_id' => Unit::factory()->create()->id, 'fellowship_id' => Fellowship::create(['name' => 'Yaba'])->id])
            ->assertSessionHas('status', fn ($s) => str_contains($s, '+10 tokens'));

        $this->artisan('tokens:daily')->assertSuccessful();
        $this->artisan('tokens:daily')->assertSuccessful(); // once a year
        $this->assertSame(1, TokenTransaction::where('rule', 'birthday')->count());

        $pending = Member::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.members.approve', $pending));
        $this->assertSame(20, $pending->user->fresh()->token_balance);
    }

    public function test_amounts_can_be_changed_or_switched_off(): void
    {
        $member = $this->member();
        config(['tokens.rules.attend_online' => 0]);
        $this->actingAs($member->user)->post(route('services.open', $this->service(['join_url' => 'https://zoom.us/j/1'])));
        $this->assertSame(0, $member->user->fresh()->token_balance);

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
        $this->assertSame(100, $buyer->fresh()->token_balance);

        $this->give($buyer, 100);
        $this->get(route('market.index'))->assertSee('For tokens')->assertSee('150 tokens');
        $this->post(route('market.tokens', $product), ['note' => 'Size L'])->assertRedirect(route('account.tokens'));
        $order = TokenOrder::sole();
        $this->assertSame([50, 0, 'pending'], [$buyer->fresh()->token_balance, $product->fresh()->stock, $order->status]);
        Mail::assertSent(TokenOrderPlaced::class, fn ($m) => $m->hasTo($product->shop->email));

        // Sold out now.
        $other = $this->member()->user;
        $this->give($other, 500);
        $this->actingAs($other)->post(route('market.tokens', $product))->assertSessionHasErrors('tokens');

        // The seller hands it over with the code: the tokens are theirs.
        $seller = $product->shop->user;
        $this->actingAs($seller)->get(route('vendor.token-orders.index'))->assertSee('Royal hoodie')->assertSee('Size L');
        $this->post(route('vendor.token-orders.complete'), ['code' => strtolower($order->code)])->assertSessionHasNoErrors();
        $this->assertSame(150, $seller->fresh()->token_balance);
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
        $this->assertSame([200, 3], [$buyer->fresh()->token_balance, $product->fresh()->stock]);

        $this->post(route('market.tokens', $product));
        $this->actingAs($product->shop->user)->post(route('vendor.token-orders.decline', TokenOrder::latest('id')->first()));
        $this->assertSame(200, $buyer->fresh()->token_balance);

        $this->actingAs($buyer)->post(route('market.tokens', $product));
        $this->travel(15)->days();
        $this->artisan('tokens:daily')->expectsOutputToContain('1 token orders expired');
        $this->assertSame(200, $buyer->fresh()->token_balance);
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
        $seller = User::factory()->create();
        $shop = Shop::factory()->approved()->create(['user_id' => $seller->id]);
        $this->actingAs($seller);

        $this->post(route('vendor.products.store'), ['type' => 'product', 'name' => 'Bible cover', 'description' => 'Leather', 'price' => 5000, 'accepts_tokens' => '1'])
            ->assertSessionHasErrors('token_price');
        $this->post(route('vendor.products.store'), ['type' => 'product', 'name' => 'Bible cover', 'description' => 'Leather', 'price' => 5000, 'accepts_tokens' => '1', 'token_price' => 400, 'is_active' => '1']);
        $this->assertSame(400, Product::firstWhere('name', 'Bible cover')->token_price);

        // ₦5,000 at ₦10 a token = 500 tokens.
        $this->give($seller, 499);
        $this->post(route('vendor.subscription.store'), ['plan' => $plan->id, 'pay_with' => 'tokens'])->assertSessionHasErrors('pay_with');
        $this->give($seller, 1);
        $this->post(route('vendor.subscription.store'), ['plan' => $plan->id, 'pay_with' => 'tokens'])->assertSessionHasNoErrors();

        $subscription = $shop->subscriptions()->sole();
        $this->assertSame([500, 0, 'active'], [$subscription->tokens_paid, $subscription->amount, $subscription->status]);
        $this->assertSame(0, $seller->fresh()->token_balance);
        $this->assertTrue($shop->fresh()->isSubscribed());
    }

    public function test_staff_give_and_take_tokens_and_see_activity(): void
    {
        $member = $this->member(['name' => 'Ada Obi']);
        $this->actingAs(User::factory()->staff('secretary')->create());

        $this->post(route('admin.tokens.award'), ['member_id' => $member->id, 'amount' => 50, 'reason' => 'Outreach volunteer'])->assertSessionHasNoErrors();
        $this->post(route('admin.tokens.award'), ['member_id' => $member->id, 'amount' => -80, 'reason' => 'Correction'])->assertSessionHasErrors('amount');
        $this->post(route('admin.tokens.award'), ['member_id' => $member->id, 'amount' => -20, 'reason' => 'Correction'])->assertSessionHasNoErrors();
        $this->assertSame(30, $member->user->fresh()->token_balance);

        $this->get(route('admin.tokens.index'))->assertOk()->assertSee('Outreach volunteer')->assertSee('Ada Obi');
        $this->get(route('admin.members.show', $member))->assertSee('🪙 30');

        $this->actingAs(User::factory()->staff('finance')->create())->get(route('admin.tokens.index'))->assertForbidden();
    }

    public function test_balances_never_go_below_zero(): void
    {
        $user = $this->member()->user;
        $this->give($user, 10);
        $wallet = app(Wallet::class);

        $wallet->debit($user, 10, 'admin', 'All of it');
        $this->expectException(ValidationException::class);
        $wallet->debit($user, 1, 'admin', 'One more');
    }
}
