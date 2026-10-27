<?php

namespace App\Http\Controllers;

use App\Models\MarketOrder;
use App\Models\Product;
use App\Services\Payments\Payments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class MarketOrderController extends Controller
{
    /**
     * Buy a product: record the order with its money split, then pay on Paystack.
     */
    public function store(Request $request, Product $product, Payments $payments): RedirectResponse
    {
        abort_unless($product->is_active && $product->shop->isApproved(), 404);

        $max = min(config('market.max_quantity'), $product->stock ?? PHP_INT_MAX);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.max(1, $max)],
            'buyer_name' => ['required', 'string', 'max:100'],
            'buyer_email' => ['required', 'email', 'max:255'],
            'buyer_phone' => ['required', 'string', 'max:40'],
            'delivery_note' => ['required', 'string', 'max:500'],
        ], [
            'quantity.max' => $product->stock !== null ? "Only {$product->stock} left." : 'That is more than one order allows.',
            'delivery_note.required' => 'Tell the seller where to deliver, or that you will collect at church.',
        ]);

        if (! $payments->enabled() || ! $product->canBuy()) {
            throw ValidationException::withMessages(['quantity' => 'This item can\'t be bought online right now.']);
        }

        $order = MarketOrder::start($product, (int) $data['quantity']);
        $order->fill(collect($data)->except('quantity')->all())->save();

        return $this->checkout($order, $payments);
    }

    /**
     * The buyer's private order page (signed link).
     */
    public function show(MarketOrder $order, Payments $payments): View
    {
        return view('market.order', [
            'order' => $order->load('shop', 'product'),
            'payment' => $order->payments()->latest('id')->first(),
            'paymentsEnabled' => $payments->enabled(),
        ]);
    }

    public function pay(MarketOrder $order, Payments $payments): RedirectResponse
    {
        abort_if($order->isPaid() || $order->status === MarketOrder::CANCELLED, 404);
        abort_unless($order->product->canBuy(), 410, 'This item is no longer available.');

        return $this->checkout($order, $payments);
    }

    private function checkout(MarketOrder $order, Payments $payments): RedirectResponse
    {
        try {
            return redirect()->away($payments->checkout($order, $order->total, $order->buyer_email));
        } catch (RuntimeException $e) {
            Log::error('Could not start market checkout', ['order' => $order->code, 'error' => $e->getMessage()]);

            return redirect()->to($order->url())->with('payment_error', 'We couldn\'t reach the payment page. Please try again in a moment.');
        }
    }
}
