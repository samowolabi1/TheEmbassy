<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\VendorPlan;
use App\Models\VendorSubscription;
use App\Services\Payments\Payments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Sellers pay to be listed: monthly up to three years, with optional coupons from the church.
 */
class SubscriptionController extends Controller
{
    public function index(Request $request, Payments $payments): View
    {
        $shop = $request->user()->shop;
        $returned = $request->filled('paid') ? $shop->subscriptions()->find($request->integer('paid')) : null;

        return view('vendor.subscription', [
            'shop' => $shop,
            'until' => $shop->subscribedUntil(),
            'subscribed' => $shop->isSubscribed(),
            'plans' => VendorPlan::available()->get(),
            'history' => $shop->subscriptions()->with('plan', 'coupon')->where('status', VendorSubscription::ACTIVE)->latest('starts_at')->get(),
            'returned' => $returned,
            'paymentsEnabled' => $payments->enabled(),
        ]);
    }

    public function store(Request $request, Payments $payments): RedirectResponse
    {
        $shop = $request->user()->shop;
        $data = $request->validate([
            'plan' => ['required', 'integer'],
            'coupon' => ['nullable', 'string', 'max:40'],
        ]);

        $plan = VendorPlan::available()->find($data['plan']);
        if (! $plan) {
            throw ValidationException::withMessages(['plan' => 'Choose one of the plans shown.']);
        }

        $coupon = null;
        if (filled($data['coupon'] ?? null)) {
            $coupon = Coupon::findByCode($data['coupon']);
            $problem = $coupon ? $coupon->problem() : 'We don\'t recognise that coupon code.';
            if ($problem) {
                throw ValidationException::withMessages(['coupon' => $problem]);
            }
        }

        $discount = $coupon ? min($plan->price, $coupon->discountOn($plan->price)) : 0;
        $amount = $plan->price - $discount;

        if ($amount > 0 && ! $payments->enabled()) {
            throw ValidationException::withMessages(['plan' => 'Online payments aren\'t switched on yet. Please contact the church office.']);
        }

        $terms = [
            'vendor_plan_id' => $plan->id,
            'coupon_id' => $coupon?->id,
            'months' => $plan->months,
            'list_price' => $plan->price,
            'discount' => $discount,
            'amount' => $amount,
        ];

        // A 100% coupon: nothing to pay, so it starts now. The coupon is re-checked under a lock
        // so its last use can't be taken twice.
        if ($amount === 0) {
            $subscription = DB::transaction(function () use ($coupon, $shop, $terms) {
                if ($problem = Coupon::whereKey($coupon->id)->lockForUpdate()->first()->problem()) {
                    throw ValidationException::withMessages(['coupon' => $problem]);
                }
                $subscription = $shop->subscriptions()->create($terms);
                $subscription->activate();

                return $subscription;
            });

            return redirect()->route('vendor.subscription.index')
                ->with('status', "Subscribed until {$subscription->ends_at->format('j F Y')}. Your listings are live.");
        }

        $subscription = $shop->subscriptions()->create($terms);

        try {
            return redirect()->away($payments->checkout($subscription, $amount, $shop->email));
        } catch (RuntimeException $e) {
            Log::error('Could not start subscription checkout', ['subscription' => $subscription->id, 'error' => $e->getMessage()]);

            return back()->withErrors(['plan' => 'We couldn\'t reach the payment page. Please try again in a moment.']);
        }
    }
}
