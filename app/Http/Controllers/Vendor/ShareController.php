<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Services\Market\ShareCards;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Everything a seller needs to share their shop and listings: ready-made pictures, captions,
 * one-tap sharing, QR codes, and how many visits their shared links have brought.
 */
class ShareController extends Controller
{
    public function index(Request $request, ShareCards $cards): View
    {
        $shop = $request->user()->shop;

        return view('vendor.share', [
            'shop' => $shop,
            'products' => $shop->products()->where('is_active', true)->latest()->get()->each->setRelation('shop', $shop),
            'cards' => $cards,
            'focus' => $request->query('product'),
            'visits' => (int) $shop->share_visits + (int) $shop->products()->sum('share_visits'),
        ]);
    }
}
