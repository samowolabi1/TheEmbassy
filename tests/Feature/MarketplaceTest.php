<?php

namespace Tests\Feature;

use App\Mail\MarketOrderPlaced;
use App\Mail\VendorApproved;
use App\Models\MarketOrder;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
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
            'market.commission' => 5,
            'platform.fees.market' => 2,
        ]);
        Http::fake([
            'api.paystack.co/bank*' => Http::response(['status' => true, 'data' => [
                ['name' => 'Access Bank', 'code' => '044'], ['name' => 'GTBank', 'code' => '058'],
            ]]),
            'api.paystack.co/subaccount' => Http::response(['status' => true, 'data' => ['subaccount_code' => 'ACCT_new', 'account_name' => 'KEMI BELLO']]),
            'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/m']]),
        ]);
    }

    private function application(array $overrides = []): array
    {
        return [
            'name' => "Kemi's Kitchen", 'owner_name' => 'Kemi Bello', 'email' => 'kemi@example.com', 'phone' => '0803 000 0000',
            'category' => 'Food', 'description' => 'Party trays and small chops.', 'bank_code' => '058',
            'account_number' => '0123456789', 'agree' => '1', ...$overrides,
        ];
    }

    private function buy(Product $product, array $overrides = [])
    {
        return $this->post(route('market.buy', $product), [
            'quantity' => 2, 'buyer_name' => 'Ada', 'buyer_email' => 'ada@example.com', 'buyer_phone' => '0812 000 0000',
            'delivery_note' => 'Collect at church on Sunday', ...$overrides,
        ]);
    }

    // ---- Applying and approval ----

    public function test_member_applies_and_admin_approves_with_a_payout_account_and_login(): void
    {
        $this->get(route('market.apply'))->assertSee('GTBank');
        $this->post(route('market.apply.store'), $this->application())->assertRedirect(route('market.apply'));

        $shop = Shop::sole();
        $this->assertSame(Shop::PENDING, $shop->status);
        $this->assertSame('GTBank', $shop->bank_name);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.market.index'))->assertSee("Kemi's Kitchen");
        $this->post(route('admin.market.approve', $shop))->assertRedirect();

        $shop->refresh();
        $this->assertTrue($shop->canSell());
        $this->assertSame('ACCT_new', $shop->subaccount_code);
        $this->assertSame('kemi@example.com', $shop->user->email);
        $this->assertFalse($shop->user->is_admin);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/subaccount') && $r['settlement_bank'] === '058' && $r['account_number'] === '0123456789');
        Mail::assertSent(VendorApproved::class, fn ($mail) => $mail->hasTo('kemi@example.com'));
    }

    public function test_application_needs_a_valid_account_number_and_the_rules_accepted(): void
    {
        $this->post(route('market.apply.store'), $this->application(['account_number' => '123', 'agree' => null]))
            ->assertSessionHasErrors(['account_number', 'agree']);
        $this->assertDatabaseCount('shops', 0);
    }

    public function test_vendor_sets_password_from_the_welcome_link_and_signs_in(): void
    {
        $shop = Shop::factory()->selling()->create();
        $user = User::factory()->create(['email' => $shop->email]);
        $shop->user()->associate($user)->save();
        $token = Password::broker()->createToken($user);

        $this->post(route('vendor.password.update'), [
            'token' => $token, 'email' => $user->email, 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password',
        ])->assertRedirect(route('vendor.login'));

        $this->post(route('vendor.login.store'), ['email' => $user->email, 'password' => 'a-long-password'])
            ->assertRedirect(route('vendor.orders.index'));
        $this->get(route('vendor.orders.index'))->assertOk()->assertSee($shop->name);
    }

    public function test_people_without_an_approved_shop_cannot_use_the_vendor_area(): void
    {
        $this->get(route('vendor.orders.index'))->assertRedirect(route('vendor.login'));
        $this->actingAs(User::factory()->create())->get(route('vendor.orders.index'))->assertForbidden();

        $pending = Shop::factory()->create(['user_id' => ($user = User::factory()->create())->id]);
        $this->actingAs($user)->get(route('vendor.products.index'))->assertForbidden();
        $this->assertSame(Shop::PENDING, $pending->fresh()->status);
    }

    // ---- Vendor products and orders ----

    public function test_vendor_adds_a_product_that_appears_in_the_market(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->selling()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('vendor.products.store'), [
            'type' => 'product', 'name' => 'Party jollof tray', 'description' => 'Serves 10.', 'price' => '25000', 'stock' => '', 'is_active' => '1',
        ])->assertRedirect(route('vendor.products.index'));

        $product = Product::sole();
        $this->assertSame(2_500_000, $product->price);
        $this->assertNull($product->stock);
        $this->get(route('market.index'))->assertSee('Party jollof tray')->assertSee('₦25,000');
        $this->get('/')->assertSee('Party jollof tray');
    }

    public function test_vendor_lists_a_service_with_a_price_unit_and_no_stock(): void
    {
        $user = User::factory()->create();
        Shop::factory()->selling()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('vendor.products.store'), [
            'type' => 'service', 'name' => 'Birthday photoshoot', 'description' => 'Two hours, edited photos.',
            'price' => '40000', 'price_unit' => 'per session', 'stock' => '5', 'is_active' => '1',
        ])->assertRedirect(route('vendor.products.index'));

        $service = Product::sole();
        $this->assertTrue($service->isService());
        $this->assertNull($service->stock); // services have no stock
        $this->assertSame('₦40,000 per session', $service->priceLabel());
        $this->get(route('market.index'))->assertSee('Birthday photoshoot')->assertSee('₦40,000 per session');
    }

    public function test_booking_a_service_asks_for_a_date_and_details(): void
    {
        $service = Product::factory()->service()->create(['price' => 4_000_000]);

        $this->get(route('market.product', $service))->assertSee('Book this service')->assertSee('When do you need it?')->assertDontSee('Quantity');

        // A fixed-price service is always booked once, whatever is posted.
        $this->buy($service, ['quantity' => 3, 'preferred_date' => now()->addWeek()->toDateString()])->assertSessionHasErrors('quantity');
        $this->buy($service, ['quantity' => 1])->assertSessionHasErrors(['preferred_date' => 'Pick the date you need it.']);
        $this->buy($service, ['quantity' => 1, 'preferred_date' => now()->subDay()->toDateString()])->assertSessionHasErrors('preferred_date');

        $this->buy($service, ['quantity' => 1, 'preferred_date' => now()->addWeek()->toDateString(), 'delivery_note' => 'Home shoot in Lekki'])
            ->assertRedirect('https://checkout.paystack.com/m');

        $order = MarketOrder::sole();
        $this->assertTrue($order->preferred_date->isSameDay(now()->addWeek()));
        $this->assertSame(4_000_000, $order->total);
        $this->assertSame('Awaiting payment', $order->statusLabel());
    }

    public function test_hourly_services_can_be_booked_for_several_hours(): void
    {
        $service = Product::factory()->service('per hour')->create(['price' => 500_000]);

        $this->get(route('market.product', $service))->assertSee('How many hours?');
        $this->buy($service, ['quantity' => 3, 'preferred_date' => now()->addDay()->toDateString()]);

        $this->assertSame(1_500_000, MarketOrder::sole()->total);
    }

    public function test_products_do_not_take_a_date(): void
    {
        $product = Product::factory()->create();

        $this->buy($product, ['preferred_date' => now()->addDay()->toDateString()])->assertSessionHasErrors('preferred_date');
    }

    public function test_service_orders_use_booking_steps(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->selling()->create(['user_id' => $user->id]);
        $service = Product::factory()->for($shop)->service()->create();
        $order = MarketOrder::factory()->create([
            'shop_id' => $shop->id, 'product_id' => $service->id, 'status' => MarketOrder::PAID, 'paid_at' => now(), 'preferred_date' => now()->addDays(3),
        ]);

        $this->actingAs($user)->get(route('vendor.orders.index'))
            ->assertSee('Booked: waiting for the seller to confirm')
            ->assertSee('Wanted on')
            ->assertSee('Mark as confirmed and scheduled');

        $this->patch(route('vendor.orders.update', $order), ['status' => MarketOrder::READY])->assertRedirect();
        $this->assertSame('Confirmed and scheduled', $order->fresh()->statusLabel());
        $this->get($order->url())->assertSee('Your booking is confirmed.');
    }

    public function test_vendors_cannot_touch_other_shops(): void
    {
        $user = User::factory()->create();
        Shop::factory()->selling()->create(['user_id' => $user->id]);
        $theirs = Product::factory()->create();
        $order = MarketOrder::factory()->create(['shop_id' => $theirs->shop_id, 'product_id' => $theirs->id, 'status' => MarketOrder::PAID, 'paid_at' => now()]);

        $this->actingAs($user)->get(route('vendor.products.edit', $theirs))->assertNotFound();
        $this->put(route('vendor.products.update', $theirs), ['name' => 'Hijacked', 'description' => 'x', 'price' => '100'])->assertNotFound();
        $this->patch(route('vendor.orders.update', $order), ['status' => MarketOrder::COMPLETED])->assertNotFound();
        $this->assertSame(MarketOrder::PAID, $order->fresh()->status);
    }

    // ---- Buying ----

    public function test_buying_splits_the_payment_between_vendor_church_and_platform(): void
    {
        $product = Product::factory()->create(['price' => 1_000_000, 'stock' => 5]); // ₦10,000

        $this->buy($product)->assertRedirect('https://checkout.paystack.com/m');

        $order = MarketOrder::sole();
        $this->assertSame(2_000_000, $order->total);
        $this->assertSame(100_000, $order->commission); // 5%
        $this->assertSame(40_000, $order->platform_fee); // 2%
        $this->assertSame(1_860_000, $order->vendorShare());

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/transaction/initialize')
            && $r['amount'] === 2_000_000
            && $r['split']['type'] === 'flat'
            && $r['split']['bearer_subaccount'] === 'ACCT_vendor'
            && $r['split']['subaccounts'] === [
                ['subaccount' => 'ACCT_vendor', 'share' => 1_860_000],
                ['subaccount' => 'ACCT_church', 'share' => 100_000],
            ]);
    }

    public function test_paid_order_reduces_stock_and_emails_buyer_and_vendor(): void
    {
        $product = Product::factory()->create(['price' => 1_000_000, 'stock' => 5]);
        $this->buy($product);
        $payment = Payment::sole();
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => [
            'reference' => $payment->reference, 'status' => 'success', 'amount' => $payment->amount, 'currency' => 'NGN',
        ]])]);

        $order = MarketOrder::sole();
        $this->get('/payments/callback?reference='.$payment->reference)->assertRedirect($order->url());

        $this->assertSame(MarketOrder::PAID, $order->fresh()->status);
        $this->assertSame(3, $product->fresh()->stock);
        Mail::assertSent(MarketOrderPlaced::class, 2);
        $this->get($order->url())->assertSee('Paid: being prepared');
    }

    public function test_cannot_buy_more_than_in_stock_or_from_a_shop_without_payouts(): void
    {
        $product = Product::factory()->create(['stock' => 1]);
        $this->buy($product, ['quantity' => 2])->assertSessionHasErrors(['quantity' => 'Only 1 left.']);

        $noPayouts = Product::factory()->for(Shop::factory()->state(['status' => Shop::APPROVED]))->create();
        $this->buy($noPayouts, ['quantity' => 1])->assertSessionHasErrors('quantity');
        $this->get(route('market.product', $noPayouts))->assertSee("Online orders aren't open", false);

        $this->assertDatabaseCount('market_orders', 0);
    }

    public function test_suspended_shops_and_hidden_products_disappear(): void
    {
        $product = Product::factory()->create(['name' => 'Shea butter']);
        $hidden = Product::factory()->create(['name' => 'Old stock', 'is_active' => false]);

        $this->get(route('market.index'))->assertSee('Shea butter')->assertDontSee('Old stock');
        $this->get(route('market.product', $hidden))->assertNotFound();

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.market.suspend', $product->shop));
        $this->get(route('market.index'))->assertDontSee('Shea butter');
        $this->get(route('market.product', $product))->assertNotFound();
    }

    public function test_vendor_moves_an_order_to_delivered(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->selling()->create(['user_id' => $user->id]);
        $product = Product::factory()->for($shop)->create();
        $order = MarketOrder::factory()->create(['shop_id' => $shop->id, 'product_id' => $product->id, 'status' => MarketOrder::PAID, 'paid_at' => now()]);

        $this->actingAs($user)->patch(route('vendor.orders.update', $order), ['status' => MarketOrder::COMPLETED])->assertRedirect();
        $this->assertSame(MarketOrder::COMPLETED, $order->fresh()->status);
    }

    public function test_admin_changes_the_commission_for_new_orders(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.market.settings'), ['commission' => 10])->assertRedirect();
        $this->assertSame(10.0, Setting::marketCommission());

        $this->post(route('admin.market.settings'), ['commission' => 80])->assertSessionHasErrors('commission');

        $product = Product::factory()->create(['price' => 1_000_000]);
        $this->buy($product, ['quantity' => 1]);
        $this->assertSame(100_000, MarketOrder::sole()->commission);
    }

    public function test_order_page_needs_the_signed_link(): void
    {
        $order = MarketOrder::factory()->create();

        $this->get("/market/orders/{$order->code}")->assertForbidden();
        $this->get($order->url())->assertOk();
    }
}
