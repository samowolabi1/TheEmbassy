<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\TokenOrder;
use App\Services\Content\Images;
use App\Support\TokenName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * The seller's products and services: several photos each (the first is the cover), a section,
 * a sale price, featured items, and hiding or deleting listings.
 */
class ProductController extends Controller
{
    /** Photos per listing. */
    public const MAX_PHOTOS = 8;

    public function index(Request $request): View
    {
        $shop = $request->user()->shop;
        $section = $request->query('section');

        return view('vendor.products', [
            'shop' => $shop,
            'collections' => $shop->collections()->get(),
            'section' => $section,
            'products' => $shop->products()->with('images', 'collection')
                ->when($section === 'none', fn ($q) => $q->whereNull('shop_collection_id'))
                ->when(is_numeric($section), fn ($q) => $q->where('shop_collection_id', (int) $section))
                ->orderByDesc('is_featured')->latest()->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, new Product(['is_active' => true, 'type' => $request->query('type') === Product::SERVICE ? Product::SERVICE : Product::PRODUCT]));
    }

    public function store(Request $request, Images $images): RedirectResponse
    {
        $shop = $request->user()->shop;
        $data = $this->validated($request);

        $product = $shop->products()->create([...$data, 'slug' => $this->uniqueSlug($shop->slug.'-'.$data['name'])]);
        if ($error = $this->savePhotos($request, $product, $images)) {
            return redirect()->route('vendor.products.edit', $product)->withErrors(['images' => $error]);
        }

        return redirect()->route('vendor.products.index')->with('status', "\"{$data['name']}\" added.");
    }

    public function edit(Request $request, Product $product): View
    {
        $this->authorizeProduct($request, $product);

        return $this->form($request, $product);
    }

    public function update(Request $request, Product $product, Images $images): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $product->update($this->validated($request, $product));
        if ($error = $this->savePhotos($request, $product, $images)) {
            return back()->withErrors(['images' => $error]);
        }

        return redirect()->route('vendor.products.index')->with('status', "\"{$product->name}\" saved.");
    }

    /**
     * Show or hide a listing from the list.
     */
    public function toggle(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('status', "\"{$product->name}\" is ".($product->is_active ? 'showing in your shop.' : 'hidden.'));
    }

    public function destroy(Request $request, Product $product, Images $images): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        if (TokenOrder::where('product_id', $product->id)->where('status', 'pending')->exists()) {
            return back()->withErrors(['product' => 'Someone is waiting for "'.$product->name.'" with '.TokenName::many().'. Hand it over or decline the order first (Crown orders), or hide it instead.']);
        }
        foreach ($product->images as $image) {
            $images->delete($image->path, $image->thumb_path);
        }
        if ($product->image_path && ! $product->images->contains('path', $product->image_path)) {
            $images->delete($product->image_path);
        }
        $name = $product->name;
        $product->delete(); // enquiries and orders about it are kept

        return redirect()->route('vendor.products.index')->with('status', "\"{$name}\" deleted.");
    }

    private function form(Request $request, Product $product): View
    {
        $shop = $request->user()->shop;

        return view('vendor.product-form', [
            'shop' => $shop,
            'product' => $product,
            'images' => $product->exists ? $product->images()->get() : collect(),
            'collections' => $shop->collections()->get(),
            'maxPhotos' => self::MAX_PHOTOS,
        ]);
    }

    /**
     * Remove ticked photos, add new ones (up to the limit), and put the chosen cover first.
     * Returns a message when some photos couldn't be saved.
     */
    private function savePhotos(Request $request, Product $product, Images $images): ?string
    {
        $remove = array_map('intval', (array) $request->input('remove_images', []));
        foreach ($product->images()->whereIn('id', $remove)->get() as $image) {
            $images->delete($image->path, $image->thumb_path);
            $image->delete();
        }
        // A photo saved before photos had their own records.
        if ($product->image_path && $product->images()->doesntExist() && $request->boolean('remove_legacy_image')) {
            $images->delete($product->image_path);
            $product->forceFill(['image_path' => null])->save();
        }

        $error = null;
        $room = self::MAX_PHOTOS - $product->images()->count();
        $next = (int) $product->images()->max('position') + 1;
        foreach (array_slice($request->file('images', []), 0, max(0, $room)) as $file) {
            try {
                $saved = $images->store($file, 'products', 1600, 480);
                $product->images()->create(['path' => $saved['path'], 'thumb_path' => $saved['thumb_path'], 'position' => $next++]);
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
        if (count($request->file('images', [])) > $room) {
            $error ??= 'A listing can have up to '.self::MAX_PHOTOS.' photos, so some weren\'t added.';
        }

        // The chosen cover goes first; the rest keep their order.
        $order = $product->images()->get();
        $cover = (int) $request->input('cover');
        if ($cover && $order->contains('id', $cover)) {
            $order = $order->sortBy(fn ($i) => $i->id === $cover ? -1 : $i->position)->values();
        }
        foreach ($order->values() as $i => $image) {
            $image->position === $i || $image->forceFill(['position' => $i])->save();
        }
        if ($product->images()->exists() || ! $product->image_path) {
            $product->syncCover();
        }

        return $error;
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless($product->shop_id === $request->user()->shop->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        $shop = $request->user()->shop;
        $data = $request->validate([
            'type' => ['required', Rule::in([Product::PRODUCT, Product::SERVICE])],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:100', 'max:10000000'],
            'price_unit' => ['nullable', 'string', 'max:30'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'remove_images' => ['nullable', 'array'],
            'cover' => ['nullable', 'integer'],
            'shop_collection_id' => ['nullable', Rule::exists('shop_collections', 'id')->where('shop_id', $shop->id)],
            'compare_price' => ['nullable', 'numeric', 'gt:price', 'max:100000000'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'accepts_tokens' => ['nullable', 'boolean'],
            'token_price' => ['nullable', 'required_if_accepted:accepts_tokens', 'integer', 'min:1', 'max:10000000'],
        ], ['price.min' => 'The lowest price is ₦100.', 'images.*.max' => 'Each photo must be under 8 MB.', 'images.*.image' => 'Photos must be JPG, PNG or WebP pictures.', 'compare_price.gt' => 'The "was" price must be higher than the price.', 'token_price.required_if_accepted' => 'Set how many '.TokenName::many().' it costs.']);

        $service = $data['type'] === Product::SERVICE;

        return [
            'type' => $data['type'],
            'name' => $data['name'],
            'description' => $data['description'],
            'price' => (int) round($data['price'] * 100),
            // Services are booked, so they have no stock; products have no price unit.
            'price_unit' => $service ? (trim((string) ($data['price_unit'] ?? '')) ?: null) : null,
            'stock' => ! $service && isset($data['stock']) && $data['stock'] !== '' ? (int) $data['stock'] : null,
            'is_active' => $request->boolean('is_active'),
            'is_featured' => $request->boolean('is_featured'),
            'shop_collection_id' => $data['shop_collection_id'] ?? null,
            'compare_price' => isset($data['compare_price']) && $data['compare_price'] !== '' ? (int) round($data['compare_price'] * 100) : null,
            // Only sellers who are church members can take tokens.
            'token_price' => $request->boolean('accepts_tokens') && $request->user()->canUseTokens() ? (int) $data['token_price'] : null,
        ];
    }

    private function uniqueSlug(string $text): string
    {
        $base = Str::slug($text) ?: 'product';
        $slug = $base;
        for ($n = 2; Product::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
