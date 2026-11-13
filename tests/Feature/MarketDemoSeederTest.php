<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use Database\Seeders\MarketDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class MarketDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_fills_the_market_once_and_sellers_can_sign_in(): void
    {
        Storage::fake('public');
        $this->seed(MarketDemoSeeder::class);
        $this->seed(MarketDemoSeeder::class); // a second run adds nothing

        $this->assertSame(8, Shop::count());
        $this->assertSame(6, Shop::listed()->count()); // one lapsed, one waiting for approval
        $this->assertGreaterThan(20, Product::listed()->count());
        $this->assertTrue(Product::whereNotNull('token_price')->get()->contains(fn (Product $p) => $p->acceptsTokens()));
        Storage::disk('public')->assertExists(Product::first()->image_path);

        $this->get(route('market.index'))->assertOk()->assertSee('Party jollof rice')->assertDontSee('Birthday gift hamper');
        $this->post(route('vendor.login.store'), ['email' => 'seller1@demo.test', 'password' => MarketDemoSeeder::PASSWORD])->assertRedirect(route('vendor.products.index'));
    }

    public function test_it_refuses_to_run_on_a_live_site(): void
    {
        // Called directly: `db:seed` on a live site already stops to ask first.
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        $this->app->make(MarketDemoSeeder::class)->run();
    }
}
