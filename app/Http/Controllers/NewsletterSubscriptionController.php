<?php

namespace App\Http\Controllers;

use App\Mail\ConfirmNewsletter;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class NewsletterSubscriptionController extends Controller
{
    /**
     * Sign someone up for the weekly email from the footer form. They confirm by email first.
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
            $subscriber = NewsletterSubscriber::firstOrCreate(['email' => strtolower($validator->validated()['email'])]);

            // Coming back after unsubscribing, or never confirmed: ask (again), at most every ten minutes.
            if (! $subscriber->isReceiving() && ($subscriber->wasRecentlyCreated || $subscriber->updated_at->lt(now()->subMinutes(10)))) {
                $subscriber->touch();
                Mail::to($subscriber->email)->send(new ConfirmNewsletter($subscriber));
            }
        }

        // The same answer either way, so the form can't be used to check who is subscribed.
        return redirect()->to($back)->with('subscribed', true);
    }

    public function confirm(string $token): View
    {
        $subscriber = NewsletterSubscriber::where('token', $token)->firstOrFail();
        $subscriber->update(['confirmed_at' => now(), 'unsubscribed_at' => null]);

        return view('pages.newsletter', ['state' => 'confirmed']);
    }

    /**
     * The link in every email. Asks before unsubscribing, so link scanners can't do it by accident.
     */
    public function unsubscribeForm(string $token): View
    {
        $subscriber = NewsletterSubscriber::where('token', $token)->firstOrFail();

        return view('pages.newsletter', ['state' => $subscriber->unsubscribed_at ? 'unsubscribed' : 'confirm-unsubscribe', 'subscriber' => $subscriber]);
    }

    /**
     * Unsubscribe: the button on that page, or a mail app's one-click unsubscribe (no CSRF token).
     */
    public function unsubscribe(Request $request, string $token): View|Response
    {
        $subscriber = NewsletterSubscriber::where('token', $token)->firstOrFail();
        $subscriber->update(['unsubscribed_at' => $subscriber->unsubscribed_at ?? now()]);

        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return response('Unsubscribed', 200);
        }

        return view('pages.newsletter', ['state' => 'unsubscribed', 'subscriber' => $subscriber]);
    }
}
