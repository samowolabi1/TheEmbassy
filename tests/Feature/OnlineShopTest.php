<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OnlineShopTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->user = User::factory()->create();
        $this->shop = Shop::factory()->listed()->create(['user_id' => $this->user->id, 'email' => $this->user->email, 'name' => "Kemi's Kitchen"]);
    }

    private function listing(array $overrides = []): array
    {
        return ['type' => 'product', 'name' => 'Chocolate cake', 'description' => 'Rich and moist.', 'price' => '15000', 'is_active' => '1', ...$overrides];
    }

    public function test_the_seller_designs_their_online_shop(): void
    {
        $this->actingAs($this->user)->get(route('vendor.storefront.edit'))->assertOk()->assertSee('My online shop')->assertSee('Shop colour');

        $this->post(route('vendor.storefront.update'), [
            'tagline' => 'Cakes for every celebration', 'announcement' => '10% off birthday cakes', 'about' => 'Started in my mum\'s kitchen in 2015.',
            'accent' => 'rose', 'hours' => 'Mon–Sat, 9am–6pm', 'address' => '12 Allen Avenue, Ikeja', 'delivery_info' => 'Lagos only, 1–2 days.',
            'returns_info' => 'Cakes can\'t be returned.', 'instagram' => '@kemiskitchen', 'website' => 'kemiskitchen.ng',
            'banner' => UploadedFile::fake()->image('banner.jpg', 1800, 600), 'logo' => UploadedFile::fake()->image('logo.png', 400, 400),
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'Your online shop is saved.');

        $shop = $this->shop->fresh();
        $this->assertSame(['rose', 'Cakes for every celebration'], [$shop->accent, $shop->tagline]);
        Storage::disk('public')->assertExists([$shop->banner_path, $shop->logo_path]);
        $this->assertSame(['Instagram' => 'https://instagram.com/kemiskitchen', 'Website' => 'https://kemiskitchen.ng'], $shop->socialLinks());

        $this->get(route('market.shop', $shop))->assertOk()
            ->assertSee('10% off birthday cakes')->assertSee('Cakes for every celebration')->assertSee($shop->bannerUrl())->assertSee($shop->logoUrl())
            ->assertSee('Started in my mum')->assertSee('Lagos only')->assertSee('https://instagram.com/kemiskitchen')->assertSee('--shop: #b0284f', false);

        // Bad links are caught; removing the banner deletes it.
        $this->post(route('vendor.storefront.update'), ['accent' => 'rose', 'instagram' => 'not a handle!'])->assertSessionHasErrors('instagram');
        $banner = $shop->banner_path;
        $this->post(route('vendor.storefront.update'), ['accent' => 'navy', 'remove_banner' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($shop->fresh()->banner_path);
        Storage::disk('public')->assertMissing($banner);
        $this->post(route('vendor.storefront.update'), ['accent' => 'purple'])->assertSessionHasErrors('accent');
    }

    public function test_sections_are_added_renamed_reordered_and_removed(): void
    {
        $this->actingAs($this->user);
        $this->put(route('vendor.storefront.collections'), ['new' => 'Cakes'])->assertSessionHas('status');
        $this->put(route('vendor.storefront.collections'), ['new' => 'Small chops']);
        [$cakes, $chops] = $this->shop->collections()->get()->all();
        $this->assertSame(['cakes', 'small-chops'], [$cakes->slug, $chops->slug]);

        $this->put(route('vendor.storefront.collections'), ['collections' => [
            $cakes->id => ['name' => 'Celebration cakes', 'position' => 5],
            $chops->id => ['name' => 'Small chops', 'position' => 1],
        ]]);
        $this->assertSame(['Small chops', 'Celebration cakes'], $this->shop->collections()->pluck('name')->all());

        $product = $this->shop->products()->create([...$this->listing(), 'price' => 1500000, 'slug' => 'cake', 'shop_collection_id' => $cakes->id]);
        $this->put(route('vendor.storefront.collections'), ['collections' => [$cakes->id => ['name' => 'Celebration cakes', 'remove' => '1'], $chops->id => ['name' => 'Small chops']]]);
        $this->assertNull($product->fresh()->shop_collection_id); // the product stays, with no section

        // Another seller's sections can't be used.
        $other = Shop::factory()->approved()->create();
        $theirs = $other->collections()->create(['name' => 'Theirs', 'slug' => 'theirs']);
        $this->post(route('vendor.products.store'), $this->listing(['shop_collection_id' => $theirs->id]))->assertSessionHasErrors('shop_collection_id');
    }

    public function test_listings_have_several_photos_with_a_cover(): void
    {
        $this->actingAs($this->user);
        $this->get(route('vendor.products.create'))->assertOk()->assertSee('Photos')->assertSee('images[]', false);

        $this->post(route('vendor.products.store'), $this->listing([
            'compare_price' => '20000', 'is_featured' => '1',
            'images' => [UploadedFile::fake()->image('a.jpg', 1200, 900), UploadedFile::fake()->image('b.jpg', 800, 800), UploadedFile::fake()->image('c.png', 600, 600)],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('vendor.products.index'));

        $product = Product::sole();
        $images = $product->images()->get();
        $this->assertCount(3, $images);
        $this->assertSame($images[0]->path, $product->image_path);
        Storage::disk('public')->assertExists([...$images->pluck('path')->all(), ...$images->pluck('thumb_path')->all()]);
        $this->assertSame([1500000, 2000000, 25, true], [$product->price, $product->compare_price, $product->salePercent(), $product->is_featured]);

        // Remove the first, make the last the cover, add two more.
        $this->put(route('vendor.products.update', $product), $this->listing([
            'remove_images' => [$images[0]->id], 'cover' => $images[2]->id,
            'images' => [UploadedFile::fake()->image('d.jpg'), UploadedFile::fake()->image('e.jpg')],
        ]))->assertSessionHasNoErrors();
        $order = $product->images()->get();
        $this->assertCount(4, $order);
        $this->assertSame([$images[2]->id, $images[1]->id], $order->take(2)->pluck('id')->all());
        $this->assertSame($images[2]->path, $product->fresh()->image_path);
        Storage::disk('public')->assertMissing($images[0]->path);

        // Up to five photos: four plus three keeps the first one.
        $this->put(route('vendor.products.update', $product), $this->listing([
            'images' => array_map(fn ($i) => UploadedFile::fake()->image("x{$i}.jpg"), range(1, 3)),
        ]))->assertSessionHasErrors(['images' => 'A listing can have up to 5 photos, so some weren\'t added.']);
        $this->assertSame(5, $product->images()->count());
        $this->get(route('vendor.products.edit', $product))->assertOk()->assertSee('That\'s the most photos a listing can have.', false);

        // The "was" price must be higher.
        $this->put(route('vendor.products.update', $product), $this->listing(['compare_price' => '100']))->assertSessionHasErrors('compare_price');
        // Not a picture.
        $this->post(route('vendor.products.store'), $this->listing(['images' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')]]))->assertSessionHasErrors('images.0');
    }

    public function test_buyers_browse_the_shop_by_section_search_and_price(): void
    {
        $cakes = $this->shop->collections()->create(['name' => 'Cakes', 'slug' => 'cakes', 'position' => 1]);
        $drinks = $this->shop->collections()->create(['name' => 'Drinks', 'slug' => 'drinks', 'position' => 2]);
        $make = fn ($name, $price, $section, $extra = []) => $this->shop->products()->create(['type' => 'product', 'name' => $name, 'description' => 'Lovely.', 'price' => $price, 'slug' => Str::slug($name), 'is_active' => true, 'shop_collection_id' => $section?->id, ...$extra]);
        $make('Red velvet cake', 2000000, $cakes, ['is_featured' => true]);
        $make('Sponge cake', 1200000, $cakes);
        $make('Zobo drink', 150000, $drinks, ['compare_price' => 200000]);
        $make('Gift card', 500000, null);
        $make('Hidden thing', 100000, null, ['is_active' => false]);

        $page = $this->get(route('market.shop', $this->shop))->assertOk();
        $page->assertSeeInOrder(['Featured', 'Red velvet cake', 'Cakes', 'Sponge cake', 'Drinks', 'Zobo drink', 'More from', 'Gift card'])
            ->assertSee('25% off')->assertDontSee('Hidden thing');

        $this->get(route('market.shop', [$this->shop, 'section' => 'drinks']))->assertOk()->assertSee('Zobo drink')->assertDontSee('Sponge cake');
        $this->get(route('market.shop', [$this->shop, 'q' => 'velvet']))->assertOk()->assertSee('Results for')->assertSee('Red velvet cake')->assertDontSee('Zobo drink');
        $this->get(route('market.shop', [$this->shop, 'sort' => 'price-low']))->assertOk()->assertSeeInOrder(['Zobo drink', 'Gift card', 'Sponge cake', 'Red velvet cake']);
    }

    public function test_the_product_page_shows_every_photo_and_listings_can_be_hidden_or_deleted(): void
    {
        $product = $this->shop->products()->create(['type' => 'product', 'name' => 'Ankara dress', 'description' => 'Made to measure.', 'price' => 2500000, 'compare_price' => 3000000, 'slug' => 'ankara-dress', 'is_active' => true]);
        foreach (['one', 'two', 'three'] as $i => $name) {
            UploadedFile::fake()->image("{$name}.jpg")->storeAs('products', "{$name}.jpg", 'public');
            $product->images()->create(['path' => "products/{$name}.jpg", 'position' => $i]);
        }
        $product->syncCover();

        $this->get(route('market.product', $product))->assertOk()
            ->assertSee('data-gallery', false)->assertSee('photo 3 of 3')->assertSee('Show photo 2')->assertSee('Save 17%')->assertSee('Visit shop');

        $this->actingAs($this->user)->post(route('vendor.products.toggle', $product))->assertSessionHas('status');
        $this->assertFalse($product->fresh()->is_active);

        // Other sellers can't touch it.
        $intruder = User::factory()->create();
        Shop::factory()->approved()->create(['user_id' => $intruder->id]);
        $this->actingAs($intruder)->delete(route('vendor.products.destroy', $product))->assertNotFound();

        $this->actingAs($this->user)->delete(route('vendor.products.destroy', $product))->assertRedirect(route('vendor.products.index'));
        $this->assertModelMissing($product);
        $this->assertSame(0, ProductImage::count());
        Storage::disk('public')->assertMissing('products/one.jpg');
    }

    public function test_the_seller_home_has_a_checklist_help_and_sharing(): void
    {
        $this->actingAs($this->user)->get(route('vendor.home'))->assertOk()
            ->assertSee('Getting started')->assertSee('1 of 7 done') // subscribed already
            ->assertSee('Add your logo')->assertSee('Share your shop')->assertSee('wa.me', false)
            ->assertSee('data-help-open', false)->assertSee('id="page-tour"', false)->assertSee('Questions sellers ask');

        // Default logo and banner until the seller adds their own.
        $this->get(route('market.shop', $this->shop))->assertOk()->assertSee('shop-banner--default', false)->assertSee('shop-logo--default', false);

        // Opening the share kit ticks that step; adding a listing offers to share it.
        $this->get(route('vendor.share.index'))->assertOk();
        $this->assertNotNull($this->shop->fresh()->share_opened_at);
        $this->post(route('vendor.products.store'), $this->listing(['images' => [UploadedFile::fake()->image('a.jpg')]]))->assertRedirect(route('vendor.products.index'));
        $this->get(route('vendor.products.index'))->assertOk()->assertSee('Share "Chocolate cake"', false);
        $this->get(route('vendor.home'))->assertSee('4 of 7 done');
        $this->get(route('vendor.storefront.edit'))->assertOk()->assertSee('id="page-tour"', false);
        $this->get(route('vendor.products.create'))->assertOk()->assertSee('Up to 5', false);
    }
}
