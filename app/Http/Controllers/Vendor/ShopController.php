<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The seller's own shop details: how buyers reach them and what they sell.
 */
class ShopController extends Controller
{
    public function edit(Request $request): View
    {
        return view('vendor.shop', ['shop' => $request->user()->shop, 'categories' => config('market.categories')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('shops', 'name')->ignore($shop->id)],
            'owner_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'category' => ['required', Rule::in(config('market.categories'))],
            'description' => ['required', 'string', 'max:600'],
            'cac_number' => ['nullable', 'string', 'max:40'],
        ], ['name.unique' => 'There is already a shop with that name.']);

        $shop->update($data); // the web address (slug) stays the same so shared links keep working

        return back()->with('status', 'Shop details saved.');
    }
}
