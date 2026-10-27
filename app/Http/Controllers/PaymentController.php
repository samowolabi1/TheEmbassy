<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Payments\Payments;
use App\Services\Payments\PaystackClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymentController extends Controller
{
    /**
     * Paystack sends the payer back here. Confirm with Paystack (never trust the redirect alone),
     * then hand over to whatever was being paid for.
     */
    public function callback(Request $request, Payments $payments): RedirectResponse
    {
        $payment = Payment::where('reference', (string) $request->query('reference'))->firstOrFail();

        try {
            $payments->confirm($payment);
        } catch (RuntimeException $e) {
            // The webhook will settle it; the return page shows it as still being confirmed.
            Log::warning('Paystack verify failed on callback', ['reference' => $payment->reference, 'error' => $e->getMessage()]);
        }

        return redirect()->to($payment->payable->paymentReturnUrl($payment));
    }

    /**
     * Paystack's server-to-server notification. Signed with our secret key.
     */
    public function webhook(Request $request, PaystackClient $paystack, Payments $payments): Response
    {
        if (! $paystack->validSignature($request->getContent(), $request->header('x-paystack-signature'))) {
            return response('Invalid signature', 401);
        }

        if ($request->input('event') === 'charge.success') {
            $data = (array) $request->input('data', []);
            $payment = Payment::firstWhere('reference', (string) ($data['reference'] ?? ''));
            // Payments made outside this site share the account; ignore what isn't ours.
            if ($payment) {
                $payments->settle($payment, $data);
            }
        }

        return response('OK');
    }
}
