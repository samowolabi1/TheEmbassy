<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Messages from buyers. Sellers reply by phone, WhatsApp or email.
 */
class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $shop = $request->user()->shop;

        return view('vendor.enquiries', [
            'shop' => $shop,
            'enquiries' => $shop->enquiries()->with('product')->latest()->paginate(20),
        ]);
    }

    public function markRead(Request $request, Enquiry $enquiry): RedirectResponse
    {
        abort_unless($enquiry->shop_id === $request->user()->shop->id, 404);
        $enquiry->forceFill(['read_at' => $enquiry->read_at ? null : now()])->save();

        return back();
    }
}
