<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        return view('vendor.products', [
            'shop' => $request->user()->shop,
            'products' => $request->user()->shop->products()->latest()->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('vendor.product-form', ['shop' => $request->user()->shop, 'product' => new Product(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;
        $data = $this->validated($request);

        $shop->products()->create([
            ...$data,
            'slug' => $this->uniqueSlug($shop->slug.'-'.$data['name']),
            'image_path' => $request->file('image')?->store('products', 'public'),
        ]);

        return redirect()->route('vendor.products.index')->with('status', "\"{$data['name']}\" added.");
    }

    public function edit(Request $request, Product $product): View
    {
        $this->authorizeProduct($request, $product);

        return view('vendor.product-form', ['shop' => $request->user()->shop, 'product' => $product]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }
        $product->update($data);

        return redirect()->route('vendor.products.index')->with('status', "\"{$product->name}\" saved.");
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless($product->shop_id === $request->user()->shop->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:100', 'max:10000000'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ], ['price.min' => 'The lowest price is ₦100.']);

        return [
            'name' => $data['name'],
            'description' => $data['description'],
            'price' => (int) round($data['price'] * 100),
            'stock' => isset($data['stock']) && $data['stock'] !== '' ? (int) $data['stock'] : null,
            'is_active' => $request->boolean('is_active'),
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
