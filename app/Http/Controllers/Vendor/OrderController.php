<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\MarketOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * The vendor's paid orders and takings.
     */
    public function index(Request $request): View
    {
        $shop = $request->user()->shop;
        $open = $request->query('show') !== 'done';
        $paid = $shop->orders()->whereNotNull('paid_at');

        return view('vendor.orders', [
            'shop' => $shop,
            'open' => $open,
            'orders' => (clone $paid)->with('product')
                ->whereIn('status', $open ? [MarketOrder::PAID, MarketOrder::READY] : [MarketOrder::COMPLETED, MarketOrder::CANCELLED])
                ->latest('paid_at')->paginate(20)->withQueryString(),
            'earnedThisMonth' => (clone $paid)->where('paid_at', '>=', now()->startOfMonth())->get()->sum(fn ($o) => $o->vendorShare()),
            'openCount' => (clone $paid)->whereIn('status', [MarketOrder::PAID, MarketOrder::READY])->count(),
        ]);
    }

    /**
     * Move an order along: ready or on its way, then delivered.
     */
    public function update(Request $request, MarketOrder $order): RedirectResponse
    {
        abort_unless($order->shop_id === $request->user()->shop->id && $order->isPaid(), 404);

        $data = $request->validate(['status' => ['required', Rule::in(array_keys(MarketOrder::VENDOR_STEPS))]]);
        $order->forceFill(['status' => $data['status']])->save();

        return back()->with('status', "Order {$order->code}: ".MarketOrder::VENDOR_STEPS[$data['status']].'.');
    }
}
