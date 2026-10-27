<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Services\Payments\Payments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class NoticeController extends Controller
{
    /**
     * The public notice board: live notices from churches and businesses.
     */
    public function index(): View
    {
        return view('notices.index', [
            'notices' => Notice::live()->get(),
            'categories' => config('notices.categories'),
        ]);
    }

    /**
     * The form to place a notice, with packages and prices.
     */
    public function create(Payments $payments): View
    {
        return view('notices.create', [
            'packages' => config('notices.packages'),
            'featuredPrice' => config('notices.featured_price'),
            'categories' => config('notices.categories'),
            'paymentsEnabled' => $payments->enabled(),
        ]);
    }

    /**
     * Save the notice and send the advertiser to Paystack. It goes live only after payment and review.
     */
    public function store(Request $request, Payments $payments): RedirectResponse
    {
        if (! $payments->enabled()) {
            return back()->withInput()->withErrors(['payment' => 'Online payments are not switched on yet, so notices can\'t be placed right now.']);
        }

        $data = $request->validate([
            'advertiser_type' => ['required', Rule::in(['church', 'business'])],
            'organisation' => ['required', 'string', 'max:120'],
            'contact_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'category' => ['required', Rule::in(config('notices.categories'))],
            'title' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:600'],
            'url' => ['nullable', 'url:http,https', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'package' => ['required', Rule::in(array_keys(config('notices.packages')))],
            'featured' => ['nullable', 'boolean'],
            'agree' => ['accepted'],
        ], [
            'agree.accepted' => 'Please confirm the notice follows the guidelines.',
            'url.url' => 'The link must be a full web address starting with https://.',
        ]);

        $package = config('notices.packages.'.$data['package']);
        $featured = $request->boolean('featured');
        $naira = $package['price'] + ($featured ? config('notices.featured_price') : 0);

        $notice = Notice::create([
            ...collect($data)->except(['image', 'featured', 'agree'])->all(),
            'image_path' => $request->file('image')?->store('notices', 'public'),
            'days' => $package['days'],
            'featured' => $featured,
            'amount' => $naira * 100,
        ]);

        try {
            return redirect()->away($payments->checkout($notice, $notice->amount, $notice->email));
        } catch (RuntimeException $e) {
            Log::error('Could not start notice checkout', ['notice' => $notice->id, 'error' => $e->getMessage()]);

            return redirect()->to($notice->statusUrl())
                ->with('payment_error', 'We couldn\'t reach the payment page. Please try again in a moment.');
        }
    }

    /**
     * The advertiser's private status page (signed link), where they land after paying.
     */
    public function status(Notice $notice, Payments $payments): View
    {
        return view('notices.status', [
            'notice' => $notice,
            'payment' => $notice->payments()->latest('id')->first(),
            'paymentsEnabled' => $payments->enabled(),
        ]);
    }

    /**
     * Try paying again for a notice whose payment didn't go through.
     */
    public function pay(Notice $notice, Payments $payments): RedirectResponse
    {
        abort_unless($notice->status === Notice::AWAITING_PAYMENT, 404);

        try {
            return redirect()->away($payments->checkout($notice, $notice->amount, $notice->email));
        } catch (RuntimeException $e) {
            Log::error('Could not restart notice checkout', ['notice' => $notice->id, 'error' => $e->getMessage()]);

            return back()->with('payment_error', 'We couldn\'t reach the payment page. Please try again in a moment.');
        }
    }
}
