<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\TokenOrder;
use App\Services\Market\ShareCards;
use App\Support\TokenName;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The seller's home: how the shop is doing, a getting-started checklist, quick actions and
 * one-tap sharing.
 */
class HomeController extends Controller
{
    public function index(Request $request, ShareCards $cards): View
    {
        $shop = $request->user()->shop;
        $products = $shop->products()->withCount('images')->get();
        $active = $products->where('is_active', true);

        $steps = [
            ['done' => (bool) $shop->logo_path, 'title' => 'Add your logo', 'about' => 'Square works best. Until then your initials show in your shop colour.', 'url' => route('vendor.storefront.edit'), 'action' => 'Add logo'],
            ['done' => (bool) $shop->banner_path, 'title' => 'Add a banner', 'about' => 'A wide picture across the top of your shop. A designed one shows until you add yours.', 'url' => route('vendor.storefront.edit'), 'action' => 'Add banner'],
            ['done' => filled($shop->tagline) && filled($shop->about), 'title' => 'Say who you are', 'about' => 'A one-line tagline and your story, so buyers trust you.', 'url' => route('vendor.storefront.edit'), 'action' => 'Write it'],
            ['done' => $products->isNotEmpty(), 'title' => 'Add your first product or service', 'about' => 'With a price and up to 5 photos.', 'url' => route('vendor.products.create'), 'action' => 'Add a listing'],
            ['done' => $active->isNotEmpty() && $active->every(fn ($p) => $p->images_count > 0), 'title' => 'Give every listing a photo', 'about' => 'Listings with photos get far more enquiries.', 'url' => route('vendor.products.index'), 'action' => 'See listings'],
            ['done' => $shop->isSubscribed(), 'title' => 'Subscribe to go live', 'about' => 'Your shop shows in the market while your subscription is active.', 'url' => route('vendor.subscription.index'), 'action' => 'Choose a plan'],
            ['done' => (bool) $shop->share_opened_at || $shop->share_visits > 0, 'title' => 'Share your shop', 'about' => 'Ready-made pictures, captions and a QR code for WhatsApp, Instagram and your stall.', 'url' => route('vendor.share.index'), 'action' => 'Share'],
        ];

        return view('vendor.home', [
            'shop' => $shop,
            'steps' => $steps,
            'done' => collect($steps)->where('done', true)->count(),
            'cards' => $cards,
            'stats' => [
                'Live listings' => $active->count(),
                'New enquiries' => $shop->enquiries()->whereNull('read_at')->count(),
                'Visits from your shares' => (int) $shop->share_visits + (int) $products->sum('share_visits'),
                ...($request->user()->canUseTokens() ? [TokenName::titleOne().' orders waiting' => TokenOrder::where('shop_id', $shop->id)->where('status', 'pending')->count()] : []),
            ],
            'enquiries' => $shop->enquiries()->with('product')->latest()->take(3)->get(),
            'until' => $shop->subscribedUntil(),
        ]);
    }
}
