<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Shop;
use App\Services\Payments\Payments;
use App\Services\Payments\PaystackClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class MarketController extends Controller
{
    /**
     * Everything for sale, filterable by shop category.
     */
    public function index(): View
    {
        return view('market.index', [
            'products' => Product::listed()->with('shop')->get(),
            'shops' => Shop::approved()->withCount(['products' => fn ($q) => $q->where('is_active', true)])->orderBy('name')->get(),
            'categories' => config('market.categories'),
        ]);
    }

    public function shop(Shop $shop): View
    {
        abort_unless($shop->isApproved(), 404);

        return view('market.shop', [
            'shop' => $shop,
            'products' => $shop->products()->where('is_active', true)->latest()->get(),
        ]);
    }

    public function product(Product $product, Payments $payments): View
    {
        abort_unless($product->is_active && $product->shop->isApproved(), 404);

        return view('market.product', [
            'product' => $product->load('shop'),
            'paymentsEnabled' => $payments->enabled(),
            'more' => $product->shop->products()->where('is_active', true)->whereKeyNot($product->id)->latest()->take(3)->get(),
        ]);
    }

    /**
     * The form for members to open a shop.
     */
    public function apply(PaystackClient $paystack): View
    {
        try {
            $banks = $paystack->banks();
        } catch (RuntimeException) {
            $banks = [];
        }

        return view('market.apply', ['banks' => $banks, 'categories' => config('market.categories')]);
    }

    public function storeApplication(Request $request, PaystackClient $paystack): RedirectResponse
    {
        try {
            $banks = $paystack->banks();
        } catch (RuntimeException) {
            $banks = [];
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('shops', 'name')],
            'owner_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('shops', 'email')->whereNot('status', Shop::DECLINED)],
            'phone' => ['required', 'string', 'max:40'],
            'category' => ['required', Rule::in(config('market.categories'))],
            'description' => ['required', 'string', 'max:600'],
            'cac_number' => ['nullable', 'string', 'max:40'],
            // With Paystack on, the bank is picked from its list so payouts can be set up; otherwise typed in.
            'bank_code' => $banks ? ['required', Rule::in(array_keys($banks))] : ['nullable'],
            'bank_name' => $banks ? ['nullable'] : ['required', 'string', 'max:80'],
            'account_number' => ['required', 'digits:10'],
            'agree' => ['accepted'],
        ], [
            'name.unique' => 'There is already a shop with that name.',
            'email.unique' => 'There is already a shop application with that email.',
            'account_number.digits' => 'Enter your 10-digit NUBAN account number.',
            'agree.accepted' => 'Please accept the market rules.',
        ]);

        Shop::create([
            ...collect($data)->except('agree')->all(),
            'bank_name' => $banks[$data['bank_code'] ?? ''] ?? $data['bank_name'],
            'slug' => $this->uniqueSlug($data['name']),
        ]);

        return redirect()->route('market.apply')->with('applied', $data['name']);
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
