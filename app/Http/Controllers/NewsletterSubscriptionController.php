<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsletterSubscriptionController extends Controller
{
    /**
     * Sign someone up for the weekly declaration email from the footer form.
     */
    public function store(Request $request): RedirectResponse
    {
        // The footer is on every page, so return to the page they were on (path only, so it stays on this site).
        $back = url(url()->previousPath()).'#newsletter';

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email:rfc', 'max:255'],
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        if ($validator->fails()) {
            return redirect()->to($back)->withErrors($validator, 'newsletter')->withInput();
        }

        // Hidden honeypot field: bots that fill it in are quietly ignored.
        if (! $request->filled('website')) {
            NewsletterSubscriber::firstOrCreate(['email' => strtolower($validator->validated()['email'])]);
        }

        return redirect()->to($back)->with('subscribed', true);
    }
}
