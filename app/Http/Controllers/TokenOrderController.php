<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\TokenOrder;
use App\Services\Tokens\TokenShop;
use App\Support\TokenName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Members buying from the market with tokens, and cancelling before it's handed over.
 */
class TokenOrderController extends Controller
{
    public function store(Request $request, Product $product, TokenShop $shop): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:300']]);
        $order = $shop->buy($request->user(), $product, $data['note'] ?? null);

        return redirect()->route('account.tokens')->with('status', "Ordered! Show the seller code {$order->code} when you collect. Your ".TokenName::many().' are held until then.');
    }

    public function cancel(Request $request, TokenOrder $order, TokenShop $shop): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $shop->cancel($order->load('buyer'), 'buyer');

        return back()->with('status', "Order cancelled. {$order->tokens} ".TokenName::many().' are back in your wallet.');
    }
}
