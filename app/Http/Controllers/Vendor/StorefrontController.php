<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopCollection;
use App\Services\Content\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * The seller's online shop: banner, logo and colour, what it says, opening hours, delivery and
 * returns, social links, and the sections products are arranged in.
 */
class StorefrontController extends Controller
{
    public function edit(Request $request): View
    {
        $shop = $request->user()->shop;

        return view('vendor.storefront', [
            'shop' => $shop,
            'collections' => $shop->collections()->withCount('products')->get(),
            'accents' => Shop::ACCENTS,
        ]);
    }

    public function update(Request $request, Images $images): RedirectResponse
    {
        $shop = $request->user()->shop;
        $data = $request->validate([
            'tagline' => ['nullable', 'string', 'max:120'],
            'announcement' => ['nullable', 'string', 'max:160'],
            'about' => ['nullable', 'string', 'max:3000'],
            'accent' => ['required', Rule::in(array_keys(Shop::ACCENTS))],
            'hours' => ['nullable', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:255'],
            'delivery_info' => ['nullable', 'string', 'max:1500'],
            'returns_info' => ['nullable', 'string', 'max:1500'],
            'instagram' => ['nullable', 'string', 'max:100', 'regex:/^[@\w.\-\/:]+$/'],
            'facebook' => ['nullable', 'string', 'max:255', 'regex:/^[@\w.\-\/:?=&]+$/'],
            'tiktok' => ['nullable', 'string', 'max:100', 'regex:/^[@\w.\-\/:]+$/'],
            'website' => ['nullable', 'string', 'max:255', 'regex:/^(https?:\/\/)?[\w.\-]+\.[a-z]{2,}(\/\S*)?$/i'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'banner.max' => 'Choose a banner under 8 MB.',
            'logo.max' => 'Choose a logo under 4 MB.',
            'instagram.regex' => 'Enter your Instagram name, e.g. @mykitchen.',
            'tiktok.regex' => 'Enter your TikTok name, e.g. @mykitchen.',
            'facebook.regex' => 'Enter your Facebook page name or link.',
            'website.regex' => 'Enter a web address, e.g. mykitchen.com.',
        ]);

        try {
            foreach (['banner' => [2000, 'shops/banners'], 'logo' => [400, 'shops/logos']] as $field => [$width, $folder]) {
                $column = $field.'_path';
                if ($request->boolean('remove_'.$field) || $request->hasFile($field)) {
                    $images->delete($shop->{$column});
                    $shop->{$column} = null;
                }
                if ($file = $request->file($field)) {
                    $saved = $images->store($file, $folder, $width, 160);
                    $images->delete($saved['thumb_path']);
                    $shop->{$column} = $saved['path'];
                }
            }
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['banner' => $e->getMessage()]);
        }

        $shop->fill(collect($data)->except(['banner', 'logo'])->map(fn ($v) => is_string($v) ? (trim($v) ?: null) : $v)->all())->save();

        return back()->with('status', 'Your online shop is saved.');
    }

    /**
     * Add, rename, reorder and remove sections in one go.
     */
    public function collections(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;
        $data = $request->validateWithBag('collections', [
            'collections' => ['array', 'max:30'],
            'collections.*.name' => ['nullable', 'string', 'max:60'],
            'collections.*.position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'collections.*.remove' => ['nullable', 'boolean'],
            'new' => ['nullable', 'string', 'max:60'],
        ]);

        foreach ($data['collections'] ?? [] as $id => $row) {
            $collection = $shop->collections()->whereKey($id)->first();
            if (! $collection) {
                continue;
            }
            if (! empty($row['remove']) || blank($row['name'] ?? null)) {
                $collection->delete(); // its products stay, with no section

                continue;
            }
            $collection->update(['name' => trim($row['name']), 'position' => (int) ($row['position'] ?? 0)]);
        }
        if (filled($data['new'] ?? null)) {
            if ($shop->collections()->count() >= 30) {
                return back()->withErrors(['new' => 'A shop can have up to 30 sections.'], 'collections');
            }
            $shop->collections()->create([
                'name' => trim($data['new']),
                'slug' => $this->slug($shop, $data['new']),
                'position' => (int) $shop->collections()->max('position') + 1,
            ]);
        }

        return redirect()->to(route('vendor.storefront.edit').'#sections')->with('status', 'Sections saved.');
    }

    private function slug(Shop $shop, string $name): string
    {
        $base = Str::slug($name) ?: 'section';
        $slug = $base;
        for ($n = 2; ShopCollection::where('shop_id', $shop->id)->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
