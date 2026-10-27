<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketOrder;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shop;
use App\Services\Market\ShopApprovals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketController extends Controller
{
    /**
     * Applications to review, shops, recent orders and the commission setting.
     */
    public function index(Request $request): View
    {
        $paidThisMonth = MarketOrder::whereNotIn('status', [MarketOrder::AWAITING_PAYMENT, MarketOrder::CANCELLED])
            ->where('paid_at', '>=', now()->startOfMonth());

        return view('admin.market', [
            'applications' => Shop::where('status', Shop::PENDING)->oldest()->get(),
            'shops' => Shop::whereIn('status', [Shop::APPROVED, Shop::SUSPENDED])
                ->withCount(['products', 'orders as paid_orders_count' => fn ($q) => $q->whereNotNull('paid_at')])
                ->orderBy('name')->get(),
            'orders' => MarketOrder::with(['shop', 'product'])->whereNotNull('paid_at')->latest('paid_at')->take(15)->get(),
            'stats' => [
                'sales' => (clone $paidThisMonth)->sum('total'),
                'orders' => (clone $paidThisMonth)->count(),
                'commission' => (clone $paidThisMonth)->sum('commission'),
                'products' => Product::listed()->count(),
            ],
            'commission' => Setting::marketCommission(),
        ]);
    }

    public function approve(Shop $shop, ShopApprovals $approvals): RedirectResponse
    {
        abort_unless(in_array($shop->status, [Shop::PENDING, Shop::DECLINED], true), 422);

        $note = $approvals->approve($shop);

        return back()->with('status', "{$shop->name} approved and emailed a link to set their password.".($note ? ' Note: '.$note : ''));
    }

    public function decline(Request $request, Shop $shop): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $shop->forceFill(['status' => Shop::DECLINED, 'decline_reason' => $data['reason']])->save();

        return back()->with('status', "{$shop->name} declined.");
    }

    /**
     * Take a shop off the market (its products disappear) or bring it back.
     */
    public function toggleSuspend(Shop $shop): RedirectResponse
    {
        abort_unless(in_array($shop->status, [Shop::APPROVED, Shop::SUSPENDED], true), 422);
        $shop->forceFill(['status' => $shop->isApproved() ? Shop::SUSPENDED : Shop::APPROVED])->save();

        return back()->with('status', $shop->name.($shop->isApproved() ? ' is back on the market.' : ' is suspended; its products are hidden.'));
    }

    /**
     * Try again to create a shop's Paystack payout account.
     */
    public function retryPayout(Shop $shop, ShopApprovals $approvals): RedirectResponse
    {
        $problem = $approvals->ensurePayoutAccount($shop);

        return back()->with('status', $problem ?? "{$shop->name} can now take orders.");
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['commission' => ['required', 'numeric', 'min:0', 'max:30']]);
        Setting::put('market.commission', $data['commission']);

        return back()->with('status', "Commission set to {$data['commission']}% for new orders.");
    }
}
