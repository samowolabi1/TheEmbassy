<?php

namespace Tests\Feature;

use App\Mail\NewEnquiry;
use App\Mail\SubscriptionActivated;
use App\Mail\VendorApproved;
use App\Models\Coupon;
use App\Models\Enquiry;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\VendorPlan;
use App\Models\VendorSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config([
            'services.paystack.secret_key' => 'sk_test',
            'services.paystack.subaccount' => 'ACCT_church',
            'platform.fees.market' => 2,
        ]);
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/m']]),
        ]);
    }

    private function application(array $overrides = []): array
    {
        return [
            'name' => "Kemi's Kitchen", 'owner_name' => 'Kemi Bello', 'email' => 'kemi@example.com', 'phone' => '0803 000 0000',
            'category' => 'Food', 'description' => 'Party trays and small chops.', 'agree' => '1', ...$overrides,
        ];
    }

    /**
     * An approved shop with a login, not yet subscribed.
     *
     * @return array{0: User, 1: Shop}
     */
    private function vendor(): array
    {
        $user = User::factory()->create();

        return [$user, Shop::factory()->approved()->create(['user_id' => $user->id, 'email' => $user->email])];
    }

    private function plan(string $key = 'quarterly', int $price = 1_200_000): VendorPlan
    {
        VendorPlan::catalogue();

        return tap(VendorPlan::firstWhere('key', $key))->update(['price' => $price, 'is_active' => true]);
    }

    private function confirmPayment(Payment $payment)
    {
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => [
            'reference' => $payment->reference, 'status' => 'success', 'amount' => $payment->amount, 'currency' => 'NGN',
        ]])]);

        return $this->get('/payments/callback?reference='.$payment->reference);
    }

    // ---- Applying and approval ----

    public function test_member_applies_and_admin_approves_with_a_login(): void
    {
        $this->plan('monthly', 500_000);
        $this->get(route('market.apply'))->assertSee('the church never handles your sales')->assertSee('₦5,000')->assertDontSee('Account number');
        $this->post(route('market.apply.store'), $this->application(['whatsapp' => '0809 111 2222']))->assertRedirect(route('market.apply'));

        $shop = Shop::sole();
        $this->assertSame(Shop::PENDING, $shop->status);
        $this->assertSame('0809 111 2222', $shop->whatsapp);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.market.index'))->assertSee("Kemi's Kitchen");
        $this->post(route('admin.market.approve', $shop))->assertRedirect();

        $shop->refresh();
        $this->assertTrue($shop->isApproved());
        $this->assertFalse($shop->isListed()); // not until they subscribe
        $this->assertSame('kemi@example.com', $shop->user->email);
        $this->assertFalse($shop->user->is_admin);
        Mail::assertSent(VendorApproved::class, fn ($mail) => $mail->hasTo('kemi@example.com'));
    }

    public function test_application_needs_the_rules_accepted(): void
    {
        $this->post(route('market.apply.store'), $this->application(['agree' => null]))->assertSessionHasErrors('agree');
        $this->assertDatabaseCount('shops', 0);
    }

    public function test_vendor_sets_password_from_the_welcome_link_and_signs_in(): void
    {
        [$user, $shop] = $this->vendor();
        $token = Password::broker()->createToken($user);

        $this->post(route('vendor.password.update'), [
            'token' => $token, 'email' => $user->email, 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password',
        ])->assertRedirect(route('vendor.login'));

        $this->post(route('vendor.login.store'), ['email' => $user->email, 'password' => 'a-long-password'])
            ->assertRedirect(route('vendor.products.index'));
        $this->get(route('vendor.products.index'))->assertOk()->assertSee($shop->name)->assertSee('Your listings are hidden');
    }

    public function test_people_without_an_approved_shop_cannot_use_the_vendor_area(): void
    {
        $this->get(route('vendor.subscription.index'))->assertRedirect(route('vendor.login'));
        $this->actingAs(User::factory()->create())->get(route('vendor.subscription.index'))->assertForbidden();

        $pending = Shop::factory()->create(['user_id' => ($user = User::factory()->create())->id]);
        $this->actingAs($user)->get(route('vendor.products.index'))->assertForbidden();
        $this->post(route('vendor.subscription.store'), ['plan' => $this->plan()->id])->assertForbidden();
        $this->assertSame(0, $pending->subscriptions()->count());
    }

    // ---- Listings ----

    public function test_vendor_adds_a_product_that_appears_once_subscribed(): void
    {
        [$user, $shop] = $this->vendor();

        $this->actingAs($user)->post(route('vendor.products.store'), [
            'type' => 'product', 'name' => 'Party jollof tray', 'description' => 'Serves 10.', 'price' => '25000', 'stock' => '', 'is_active' => '1',
        ])->assertRedirect(route('vendor.products.index'));

        $product = Product::sole();
        $this->assertSame(2_500_000, $product->price);
        $this->get(route('market.index'))->assertDontSee('Party jollof tray');
        // The owner can preview it; nobody else can.
        $this->get(route('market.product', $product))->assertOk()->assertSee('Only you can see this listing');
        auth()->logout();
        $this->get(route('market.product', $product))->assertNotFound();

        VendorSubscription::factory()->active()->for($shop)->create();
        $this->get(route('market.index'))->assertSee('Party jollof tray')->assertSee('₦25,000');
        $this->get('/')->assertSee('Party jollof tray');
    }

    public function test_vendor_lists_a_service_with_a_price_unit_and_no_stock(): void
    {
        [$user, $shop] = $this->vendor();
        VendorSubscription::factory()->active()->for($shop)->create();

        $this->actingAs($user)->post(route('vendor.products.store'), [
            'type' => 'service', 'name' => 'Birthday photoshoot', 'description' => 'Two hours, edited photos.',
            'price' => '40000', 'price_unit' => 'per session', 'stock' => '5', 'is_active' => '1',
        ])->assertRedirect(route('vendor.products.index'));

        $service = Product::sole();
        $this->assertTrue($service->isService());
        $this->assertNull($service->stock);
        $this->assertSame('₦40,000 per session', $service->priceLabel());
        $this->get(route('market.index'))->assertSee('Birthday photoshoot')->assertSee('₦40,000 per session');
    }

    public function test_lapsed_suspended_and_hidden_listings_disappear(): void
    {
        $product = Product::factory()->create(['name' => 'Shea butter']);
        $hidden = Product::factory()->create(['name' => 'Old stock', 'is_active' => false]);
        $lapsedShop = Shop::factory()->approved()->has(VendorSubscription::factory()->expired(), 'subscriptions')->create();
        Product::factory()->for($lapsedShop)->create(['name' => 'Lapsed lamp']);

        $this->get(route('market.index'))->assertSee('Shea butter')->assertDontSee('Old stock')->assertDontSee('Lapsed lamp');
        $this->get(route('market.product', $hidden))->assertNotFound();
        $this->get(route('market.shop', $lapsedShop))->assertNotFound();

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.market.suspend', $product->shop));
        auth()->logout();
        $this->get(route('market.index'))->assertDontSee('Shea butter');
        $this->get(route('market.product', $product))->assertNotFound();
    }

    public function test_vendors_cannot_touch_other_shops(): void
    {
        [$user] = $this->vendor();
        $theirs = Product::factory()->create();
        $enquiry = Enquiry::factory()->for($theirs)->create();

        $this->actingAs($user)->get(route('vendor.products.edit', $theirs))->assertNotFound();
        $this->put(route('vendor.products.update', $theirs), ['name' => 'Hijacked', 'description' => 'x', 'price' => '100'])->assertNotFound();
        $this->patch(route('vendor.enquiries.read', $enquiry))->assertNotFound();
        $this->get(route('vendor.enquiries.index'))->assertDontSee($enquiry->name);
        $this->assertNull($enquiry->fresh()->read_at);
    }

    // ---- Buyers contact sellers directly ----

    public function test_product_page_offers_call_whatsapp_and_email_not_checkout(): void
    {
        $product = Product::factory()->create();
        $product->shop->update(['phone' => '0803 000 0000', 'whatsapp' => '0809 111 2222']);

        $this->get(route('market.product', $product))
            ->assertSee('tel:08030000000')
            ->assertSee('https://wa.me/2348091112222', false)
            ->assertSee('mailto:'.$product->shop->email, false)
            ->assertSee('You pay '.$product->shop->name.' directly', false)
            ->assertDontSee('Paystack');
    }

    public function test_buyer_sends_an_enquiry_that_reaches_the_seller(): void
    {
        $product = Product::factory()->create();

        $this->post(route('market.enquire', $product), ['name' => 'Ada', 'phone' => '0812 000 0000', 'email' => 'ada@example.com', 'message' => 'Do you deliver to Ikeja?'])
            ->assertRedirect(route('market.product', $product).'#contact');

        $enquiry = Enquiry::sole();
        $this->assertSame($product->shop_id, $enquiry->shop_id);
        $this->assertSame($product->id, $enquiry->product_id);
        Mail::assertSent(NewEnquiry::class, fn ($mail) => $mail->hasTo($product->shop->email) && $mail->hasReplyTo('ada@example.com'));
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_service_enquiries_need_a_date(): void
    {
        $service = Product::factory()->service()->create();
        $data = ['name' => 'Ada', 'phone' => '0812 000 0000', 'message' => 'Shoot for 20 guests in Lekki'];

        $this->get(route('market.product', $service))->assertSee('When do you need it?');
        $this->post(route('market.enquire', $service), $data)->assertSessionHasErrors('preferred_date');
        $this->post(route('market.enquire', $service), [...$data, 'preferred_date' => now()->subDay()->toDateString()])->assertSessionHasErrors('preferred_date');
        $this->post(route('market.enquire', $service), [...$data, 'preferred_date' => now()->addWeek()->toDateString()])->assertSessionHasNoErrors();

        $this->assertTrue(Enquiry::sole()->preferred_date->isSameDay(now()->addWeek()));
    }

    public function test_bots_filling_the_honeypot_are_ignored(): void
    {
        $product = Product::factory()->create();

        $this->post(route('market.enquire', $product), ['name' => 'Bot', 'phone' => '1', 'message' => 'spam', 'website' => 'http://spam.test'])->assertRedirect();

        $this->assertDatabaseCount('enquiries', 0);
        Mail::assertNothingSent();
    }

    public function test_unlisted_shops_cannot_be_enquired(): void
    {
        $shop = Shop::factory()->approved()->create();
        $product = Product::factory()->for($shop)->create();

        $this->post(route('market.enquire', $product), ['name' => 'Ada', 'phone' => '0812', 'message' => 'Hi'])->assertNotFound();
    }

    public function test_vendor_sees_and_handles_enquiries(): void
    {
        [$user, $shop] = $this->vendor();
        $product = Product::factory()->for($shop)->create();
        $enquiry = Enquiry::factory()->for($product)->create(['name' => 'Ada Obi', 'message' => 'Is it available Saturday?']);

        $this->actingAs($user)->get(route('vendor.enquiries.index'))->assertSee('Ada Obi')->assertSee('Is it available Saturday?')->assertSee('Mark as handled');
        $this->patch(route('vendor.enquiries.read', $enquiry))->assertRedirect();
        $this->assertNotNull($enquiry->fresh()->read_at);
    }

    // ---- Subscriptions ----

    public function test_plans_are_hidden_until_priced_and_switched_on(): void
    {
        [$user] = $this->vendor();
        VendorPlan::catalogue();
        $this->assertSame(5, VendorPlan::count());

        $this->actingAs($user)->get(route('vendor.subscription.index'))->assertSee('Plans aren\'t open yet', false);
        $this->post(route('vendor.subscription.store'), ['plan' => VendorPlan::first()->id])->assertSessionHasErrors('plan');

        $this->plan('yearly', 9_000_000);
        $this->get(route('vendor.subscription.index'))->assertSee('1 year')->assertSee('₦90,000')->assertSee('₦7,500 a month')->assertDontSee('Quarterly');
    }

    public function test_vendor_pays_for_a_plan_and_is_listed_for_its_length(): void
    {
        [$user, $shop] = $this->vendor();
        $plan = $this->plan('quarterly', 1_200_000);

        $this->actingAs($user)->post(route('vendor.subscription.store'), ['plan' => $plan->id])
            ->assertRedirect('https://checkout.paystack.com/m');

        $subscription = VendorSubscription::sole();
        $payment = Payment::sole();
        $this->assertSame(1_200_000, $payment->amount);
        $this->assertSame(24_000, $payment->platform_fee); // 2% to the platform; the rest settles to the church
        $this->assertFalse($shop->isSubscribed());
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/initialize') && $r['subaccount'] === 'ACCT_church' && ! isset($r['split']));

        $this->confirmPayment($payment)->assertRedirect(route('vendor.subscription.index', ['paid' => $subscription->id]));

        $subscription->refresh();
        $this->assertSame(VendorSubscription::ACTIVE, $subscription->status);
        $this->assertTrue($subscription->ends_at->isSameDay(now()->addMonthsNoOverflow(3)));
        $this->assertTrue($shop->isListed());
        Mail::assertSent(SubscriptionActivated::class, fn ($mail) => $mail->hasTo($shop->email));
        $this->get(route('vendor.subscription.index', ['paid' => $subscription->id]))->assertSee('Payment received')->assertSee('Listed');
    }

    public function test_renewing_early_adds_on_after_the_current_period(): void
    {
        [$user, $shop] = $this->vendor();
        $current = VendorSubscription::factory()->active()->for($shop)->create(['ends_at' => now()->addDays(10)]);
        $plan = $this->plan('monthly', 500_000);

        $this->actingAs($user)->post(route('vendor.subscription.store'), ['plan' => $plan->id]);
        $this->confirmPayment(Payment::sole());

        $renewal = VendorSubscription::whereKeyNot($current->id)->sole();
        $this->assertTrue($renewal->starts_at->equalTo($current->ends_at));
        $this->assertTrue($shop->subscribedUntil()->isSameDay(now()->addDays(10)->addMonthNoOverflow()));
    }

    public function test_coupons_take_ten_twenty_or_fifty_percent_off(): void
    {
        [$user] = $this->vendor();
        $plan = $this->plan('yearly', 10_000_000);

        foreach ([10 => 9_000_000, 20 => 8_000_000, 50 => 5_000_000] as $percent => $expected) {
            Coupon::factory()->percent($percent)->create(['code' => "SAVE{$percent}"]);
            $this->actingAs($user)->post(route('vendor.subscription.store'), ['plan' => $plan->id, 'coupon' => "save{$percent}"])
                ->assertRedirect('https://checkout.paystack.com/m');

            $subscription = VendorSubscription::latest('id')->first();
            $this->assertSame(10_000_000 - $expected, $subscription->discount);
            $this->assertSame($expected, $subscription->payments()->sole()->amount);
        }
    }

    public function test_a_hundred_percent_coupon_activates_without_payment(): void
    {
        [$user, $shop] = $this->vendor();
        $plan = $this->plan('three_years', 20_000_000);
        $coupon = Coupon::factory()->percent(100)->create(['code' => 'FOUNDERS', 'max_uses' => 1]);
        config(['services.paystack.secret_key' => null]); // works even with payments off

        $this->actingAs($user)->post(route('vendor.subscription.store'), ['plan' => $plan->id, 'coupon' => 'founders'])
            ->assertRedirect(route('vendor.subscription.index'))
            ->assertSessionHas('status');

        $subscription = VendorSubscription::sole();
        $this->assertSame(0, $subscription->amount);
        $this->assertSame($coupon->id, $subscription->coupon_id);
        $this->assertTrue($subscription->ends_at->isSameDay(now()->addMonthsNoOverflow(36)));
        $this->assertTrue($shop->isListed());
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();

        // It was single-use.
        $this->post(route('vendor.subscription.store'), ['plan' => $plan->id, 'coupon' => 'FOUNDERS'])->assertSessionHasErrors(['coupon' => 'That coupon has been used up.']);
        $this->assertSame(1, VendorSubscription::count());
    }

    public function test_switched_off_expired_and_unknown_coupons_are_refused(): void
    {
        [$user] = $this->vendor();
        $plan = $this->plan();
        Coupon::factory()->create(['code' => 'OFF', 'is_active' => false]);
        Coupon::factory()->create(['code' => 'OLD', 'expires_at' => now()->subDay()]);

        $this->actingAs($user);
        $this->post(route('vendor.subscription.store'), ['plan' => $plan->id, 'coupon' => 'OFF'])->assertSessionHasErrors(['coupon' => 'That coupon has been switched off.']);
        $this->post(route('vendor.subscription.store'), ['plan' => $plan->id, 'coupon' => 'OLD'])->assertSessionHasErrors(['coupon' => 'That coupon has expired.']);
        $this->post(route('vendor.subscription.store'), ['plan' => $plan->id, 'coupon' => 'NOPE'])->assertSessionHasErrors('coupon');
        $this->assertDatabaseCount('vendor_subscriptions', 0);
    }

    public function test_paid_plans_need_payments_switched_on(): void
    {
        [$user] = $this->vendor();
        $plan = $this->plan();
        config(['services.paystack.secret_key' => null]);

        $this->actingAs($user)->post(route('vendor.subscription.store'), ['plan' => $plan->id])->assertSessionHasErrors('plan');
        $this->assertDatabaseCount('vendor_subscriptions', 0);
    }

    // ---- Admin ----

    public function test_admin_prices_plans_and_switches_them_on(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.market.index'))
            ->assertSee('Monthly')->assertSee('Quarterly')->assertSee('6 months')->assertSee('1 year')->assertSee('3 years');

        $monthly = VendorPlan::firstWhere('key', 'monthly');
        $yearly = VendorPlan::firstWhere('key', 'yearly');
        $this->post(route('admin.market.plans'), ['plans' => [
            $monthly->id => ['price' => '5000', 'is_active' => '1'],
            $yearly->id => ['price' => '', 'is_active' => '1'], // no price: can't be offered
        ]])->assertRedirect();

        $this->assertSame(500_000, $monthly->fresh()->price);
        $this->assertTrue($monthly->fresh()->is_active);
        $this->assertFalse($yearly->fresh()->is_active);
        $this->assertSame(['monthly'], VendorPlan::available()->pluck('key')->all());
    }

    public function test_admin_creates_coupons_only_at_the_allowed_percents(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.market.coupons.store'), ['code' => 'welcome50', 'percent_off' => 50, 'max_uses' => 20, 'expires_at' => now()->addMonth()->toDateString()])
            ->assertSessionHasNoErrors();
        $coupon = Coupon::sole();
        $this->assertSame('WELCOME50', $coupon->code);
        $this->assertTrue($coupon->expires_at->isSameDay(now()->addMonth()));

        $this->post(route('admin.market.coupons.store'), ['code' => 'ODD', 'percent_off' => 35])->assertSessionHasErrors('percent_off');
        $this->post(route('admin.market.coupons.store'), ['code' => 'Welcome50', 'percent_off' => 10])->assertSessionHasErrors('code');
        $this->post(route('admin.market.coupons.store'), ['code' => 'bad code!', 'percent_off' => 10])->assertSessionHasErrors('code');

        $this->post(route('admin.market.coupons.toggle', $coupon))->assertRedirect();
        $this->assertFalse($coupon->fresh()->is_active);
        $this->get(route('admin.market.index'))->assertSee('WELCOME50')->assertSee('50% off');
    }

    public function test_admin_sees_subscription_income_and_who_is_paid_up(): void
    {
        $shop = Shop::factory()->approved()->create(['name' => 'Ade Prints']);
        VendorSubscription::factory()->active()->for($shop)->create(['amount' => 1_200_000, 'ends_at' => now()->addMonths(3)]);
        Shop::factory()->approved()->create(['name' => 'Bisi Bakes']);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.market.index'))
            ->assertSee('₦12,000')
            ->assertSee('Paid to '.now()->addMonths(3)->format('j M Y'))
            ->assertSee('Not subscribed');
    }

    public function test_only_admins_manage_plans_and_coupons(): void
    {
        [$user] = $this->vendor();

        $this->actingAs($user)->post(route('admin.market.coupons.store'), ['code' => 'FREE', 'percent_off' => 100])->assertForbidden();
        $this->assertDatabaseCount('coupons', 0);
    }
}
