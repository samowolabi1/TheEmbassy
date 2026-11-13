<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\Market\ShareCards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketShareTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Shop $shop;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seller = User::factory()->create();
        $this->shop = Shop::factory()->listed()->create(['user_id' => $this->seller->id, 'name' => 'Kemi Cakes', 'category' => 'Food']);
        $photo = UploadedFile::fake()->image('cake.jpg', 900, 700)->store('products', 'public');
        $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Red velvet cake', 'price' => 2_500_000, 'image_path' => $photo]);
    }

    public function test_share_pictures_in_every_size_and_a_qr_code(): void
    {
        foreach (['square' => [1080, 1080], 'story' => [1080, 1920], 'wide' => [1200, 630]] as $format => $size) {
            $png = $this->get(route('market.product.image', [$this->product, $format]))->assertOk()->assertHeader('Content-Type', 'image/png')->getContent();
            $this->assertSame($size, array_slice(getimagesizefromstring($png), 0, 2), $format);
            $this->get(route('market.shop.image', [$this->shop, $format]))->assertOk();
        }
        $this->get(route('market.product.image', [$this->product, 'qr', 'download' => 1]))->assertOk()->assertDownload();
        $this->get(route('market.product.image', [$this->product, 'huge']))->assertNotFound();
    }

    public function test_pages_show_a_link_preview_and_share_buttons(): void
    {
        $this->get(route('market.product', $this->product))->assertOk()
            ->assertSee('<meta property="og:image" content="'.route('market.product.image', [$this->product, 'wide']).'">', false)
            ->assertSee('₦25,000 · ', false)
            ->assertSee('https://wa.me/?text=', false)
            ->assertSee('facebook.com/sharer', false);
        $this->get(route('market.shop', $this->shop))->assertOk()->assertSee(route('market.shop.image', [$this->shop, 'wide']), false);

        $caption = app(ShareCards::class)->caption($this->product, 'wa');
        $this->assertStringContainsString('Red velvet cake · ₦25,000', $caption);
        $this->assertStringContainsString(route('market.product', $this->product).'?s=wa', $caption);
        $this->assertStringContainsString('#TribeMarket', $caption);
    }

    public function test_visits_from_shared_links_are_counted_once_and_not_for_the_seller(): void
    {
        $this->get(route('market.product', [$this->product, 's' => 'wa']));
        $this->get(route('market.product', [$this->product, 's' => 'wa']));
        $this->get(route('market.product', [$this->product, 's' => 'bogus']));
        $this->assertSame(1, $this->product->fresh()->share_visits);

        $this->actingAs($this->seller)->get(route('market.shop', [$this->shop, 's' => 'fb']));
        $this->assertSame(0, $this->shop->fresh()->share_visits);

        // The seller's share page: captions, buttons, downloads and the visit count.
        $this->get(route('vendor.share.index'))->assertOk()
            ->assertSee('Share your shop')->assertSee('Red velvet cake')
            ->assertSee(route('market.product.image', [$this->product, 'story', 'download' => 1]), false)
            ->assertSee('Visits from your shared links so far: 1.');
        $this->get(route('vendor.products.index'))->assertSee('#product-'.$this->product->slug, false);
    }

    public function test_pictures_of_shops_not_in_the_market_are_only_for_their_owner(): void
    {
        $hidden = Shop::factory()->approved()->create(['user_id' => ($owner = User::factory()->create())->id]);
        $this->get(route('market.shop.image', [$hidden, 'square']))->assertNotFound();
        $this->actingAs($owner)->get(route('market.shop.image', [$hidden, 'square']))->assertOk();
        $this->product->update(['is_active' => false]);
        $this->get(route('market.product.image', [$this->product, 'square']))->assertNotFound();
    }
}
