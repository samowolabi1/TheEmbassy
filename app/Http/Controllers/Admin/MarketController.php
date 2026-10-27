<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Shop;
use App\Models\VendorPlan;
use App\Models\VendorSubscription;
use App\Services\Market\ShopApprovals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The market: shop applications, shops and their subscriptions, plan prices and coupons.
 * Sales happen between buyers and sellers; the church's income is the subscriptions.
 */
class MarketController extends Controller
{
    public function index(): View
    {
        $paidThisMonth = VendorSubscription::where('status', VendorSubscription::ACTIVE)
            ->where('created_at', '>=', now()->startOfMonth());

        $shops = Shop::whereIn('status', [Shop::APPROVED, Shop::SUSPENDED])
            ->withCount('products')
            ->withMax(['subscriptions as subscribed_until' => fn ($q) => $q->where('status', VendorSubscription::ACTIVE)], 'ends_at')
            ->orderBy('name')->get();

        return view('admin.market', [
            'applications' => Shop::where('status', Shop::PENDING)->oldest()->get(),
            'shops' => $shops,
            'plans' => VendorPlan::catalogue(),
            'coupons' => Coupon::withCount(['subscriptions as uses_count' => fn ($q) => $q->where('status', VendorSubscription::ACTIVE)])->latest()->get(),
            'stats' => [
                'income' => (clone $paidThisMonth)->sum('amount'),
                'subscriptions' => (clone $paidThisMonth)->count(),
                'listed' => Shop::listed()->count(),
                'products' => Product::listed()->count(),
            ],
        ]);
    }

    public function approve(Shop $shop, ShopApprovals $approvals): RedirectResponse
    {
        abort_unless(in_array($shop->status, [Shop::PENDING, Shop::DECLINED], true), 422);

        $approvals->approve($shop);

        return back()->with('status', "{$shop->name} approved and emailed a link to set their password.");
    }

    public function decline(Request $request, Shop $shop): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $shop->forceFill(['status' => Shop::DECLINED, 'decline_reason' => $data['reason']])->save();

        return back()->with('status', "{$shop->name} declined.");
    }

    /**
     * Take a shop off the market (its listings disappear) or bring it back.
     */
    public function toggleSuspend(Shop $shop): RedirectResponse
    {
        abort_unless(in_array($shop->status, [Shop::APPROVED, Shop::SUSPENDED], true), 422);
        $shop->forceFill(['status' => $shop->isApproved() ? Shop::SUSPENDED : Shop::APPROVED])->save();

        return back()->with('status', $shop->name.($shop->isApproved() ? ' is back on the market.' : ' is suspended; its listings are hidden.'));
    }

    /**
     * Prices for each subscription period. A plan is only offered when it's switched on and priced.
     */
    public function plans(Request $request): RedirectResponse
    {
        $plans = VendorPlan::catalogue();
        $data = $request->validate([
            'plans' => ['required', 'array'],
            'plans.*.price' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'plans.*.is_active' => ['nullable', 'boolean'],
        ]);

        foreach ($plans as $plan) {
            $input = $data['plans'][$plan->id] ?? null;
            if ($input === null) {
                continue;
            }
            $price = (int) round(((float) ($input['price'] ?? 0)) * 100);
            $plan->update(['price' => $price, 'is_active' => ! empty($input['is_active']) && $price > 0]);
        }

        return back()->with('status', 'Subscription prices saved.');
    }

    public function storeCoupon(Request $request): RedirectResponse
    {
        $request->merge(['code' => Str::upper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')],
            'percent_off' => ['required', 'integer', Rule::in(config('market.coupon_percents'))],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            'code.regex' => 'Use letters, numbers, dashes and underscores only.',
            'code.unique' => 'There is already a coupon with that code.',
        ]);

        Coupon::create([
            ...$data,
            'expires_at' => isset($data['expires_at']) ? now()->parse($data['expires_at'])->endOfDay() : null,
            'is_active' => true,
        ]);

        return back()->with('status', "Coupon {$data['code']} created: {$data['percent_off']}% off.");
    }

    public function toggleCoupon(Coupon $coupon): RedirectResponse
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return back()->with('status', "Coupon {$coupon->code} ".($coupon->is_active ? 'switched on.' : 'switched off.'));
    }
}
