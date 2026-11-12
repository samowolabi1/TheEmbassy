<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\TokenOrder;
use App\Services\Tokens\TokenShop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Orders members placed with tokens: hand them over (the tokens become the seller's) or decline.
 */
class TokenOrderController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canUseTokens(), 404);
        $shop = $request->user()->shop;
        $show = $request->query('show') === 'done' ? 'done' : 'pending';

        return view('vendor.token-orders', [
            'shop' => $shop,
            'show' => $show,
            'orders' => TokenOrder::with('buyer.member')->where('shop_id', $shop->id)
                ->when($show === 'pending', fn ($q) => $q->where('status', TokenOrder::PENDING))
                ->when($show === 'done', fn ($q) => $q->where('status', '!=', TokenOrder::PENDING))
                ->latest()->paginate(30)->withQueryString(),
            'pending' => TokenOrder::where('shop_id', $shop->id)->where('status', TokenOrder::PENDING)->count(),
            'balance' => (int) $request->user()->tokenBalance(),
        ]);
    }

    /**
     * Hand over: by the order's button, or by typing the code the buyer shows.
     */
    public function complete(Request $request, TokenShop $tokens): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $order = TokenOrder::with('shop.user')->where('shop_id', $request->user()->shop->id)->where('code', strtoupper(trim($data['code'])))->first();
        if (! $order) {
            throw ValidationException::withMessages(['code' => 'No token order with that code for your shop.']);
        }
        $tokens->complete($order);

        return back()->with('status', "Handed over: {$order->product_name}. {$order->tokens} tokens are now yours.");
    }

    public function decline(Request $request, TokenOrder $order, TokenShop $tokens): RedirectResponse
    {
        abort_unless($order->shop_id === $request->user()->shop->id, 404);
        $tokens->cancel($order->load('buyer'), 'vendor');

        return back()->with('status', "Declined. The buyer's {$order->tokens} tokens went back to them.");
    }
}
