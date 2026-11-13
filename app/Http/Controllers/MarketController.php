<?php

namespace App\Http\Controllers;

use App\Mail\NewEnquiry;
use App\Models\Product;
use App\Models\Shop;
use App\Models\VendorPlan;
use App\Services\Market\ShareCards;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class MarketController extends Controller
{
    /**
     * Everything listed, filterable by products or services.
     */
    public function index(): View
    {
        return view('market.index', [
            'products' => Product::listed()->with('shop.user.member')->get(),
            'shops' => Shop::listed()->withCount(['products' => fn ($q) => $q->where('is_active', true)])->orderBy('name')->get(),
        ]);
    }

    /**
     * Shops only show while subscribed. The owner can still see theirs, to check how it looks.
     */
    public function shop(Request $request, Shop $shop): View
    {
        $this->ensureVisible($request, $shop);
        $this->countShareVisit($request, $shop);

        return view('market.shop', [
            'shop' => $shop,
            'products' => $shop->products()->where('is_active', true)->latest()->get(),
        ]);
    }

    public function product(Request $request, Product $product): View
    {
        $this->ensureVisible($request, $product->shop);
        abort_unless($product->is_active, 404);
        $this->countShareVisit($request, $product);

        return view('market.product', [
            'product' => $product->load('shop.user.member'),
            'more' => $product->shop->products()->where('is_active', true)->whereKeyNot($product->id)->latest()->take(3)->get(),
        ]);
    }

    /**
     * A picture for sharing the listing (square, story or wide), or its QR code.
     */
    public function productImage(Request $request, Product $product, string $format, ShareCards $cards): Response
    {
        $this->ensureVisible($request, $product->shop);
        abort_unless($product->is_active, 404);
        $bytes = $format === 'qr' ? $cards->qr($cards->url($product, 'qr')) : $cards->product($product, $format);

        return $this->png($request, $bytes, $product->slug.'-'.$format.'.png');
    }

    public function shopImage(Request $request, Shop $shop, string $format, ShareCards $cards): Response
    {
        $this->ensureVisible($request, $shop);
        $bytes = $format === 'qr' ? $cards->qr($cards->url($shop, 'qr')) : $cards->shop($shop, $format);

        return $this->png($request, $bytes, $shop->slug.'-'.$format.'.png');
    }

    private function png(Request $request, string $bytes, string $name): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=600',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$name.'"',
        ]);
    }

    /**
     * Someone arrived from a link the seller shared (?s=wa, fb, x…). Counted once per visitor,
     * and never the seller looking at their own page.
     */
    private function countShareVisit(Request $request, Product|Shop $item): void
    {
        $via = $request->query('s');
        $shop = $item instanceof Product ? $item->shop : $item;
        if (! in_array($via, ['wa', 'fb', 'x', 'tg', 'qr', 'link', 'share', 'status'], true) || $request->user()?->id === $shop->user_id) {
            return;
        }
        $key = 'shared.'.class_basename($item).'.'.$item->id;
        if (! $request->session()->has($key)) {
            $request->session()->put($key, true);
            $item->newQuery()->whereKey($item->id)->increment('share_visits');
        }
    }

    /**
     * A buyer's message to the seller. The sale itself, and payment, happen between them.
     */
    public function enquire(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active && $product->shop->isListed(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:1000'],
            'preferred_date' => [$product->isService() ? 'required' : 'nullable', 'date', 'after_or_equal:today'],
        ], [
            'preferred_date.after_or_equal' => 'Pick today or a later date.',
        ]);

        // A hidden "website" field that people never see; bots that fill it in are quietly ignored.
        if (! $request->filled('website')) {
            $enquiry = $product->shop->enquiries()->create([...$data, 'product_id' => $product->id]);

            try {
                Mail::to($product->shop->email)->send(new NewEnquiry($enquiry->load('product', 'shop')));
            } catch (Throwable $e) {
                Log::error('Could not email seller about an enquiry', ['enquiry' => $enquiry->id, 'error' => $e->getMessage()]);
            }
        }

        return redirect()->to(route('market.product', $product).'#contact')->with('enquired', $product->shop->name);
    }

    /**
     * The form for members to open a shop.
     */
    public function apply(): View
    {
        return view('market.apply', [
            'categories' => config('market.categories'),
            'plans' => VendorPlan::available()->get(),
        ]);
    }

    public function storeApplication(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('shops', 'name')],
            'owner_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('shops', 'email')->whereNot('status', Shop::DECLINED)],
            'phone' => ['required', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'category' => ['required', Rule::in(config('market.categories'))],
            'description' => ['required', 'string', 'max:600'],
            'cac_number' => ['nullable', 'string', 'max:40'],
            'agree' => ['accepted'],
        ], [
            'name.unique' => 'There is already a shop with that name.',
            'email.unique' => 'There is already a shop application with that email.',
            'agree.accepted' => 'Please accept the market rules.',
        ]);

        Shop::create([
            ...collect($data)->except('agree')->all(),
            'slug' => $this->uniqueSlug($data['name']),
        ]);

        return redirect()->route('market.apply')->with('applied', $data['name']);
    }

    private function ensureVisible(Request $request, Shop $shop): void
    {
        $isOwner = $shop->user_id && $request->user()?->id === $shop->user_id;
        abort_unless($shop->isListed() || ($isOwner && $shop->isApproved()), 404);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'shop';
        $slug = $base;
        for ($n = 2; Shop::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
