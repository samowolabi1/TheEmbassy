<?php

namespace App\Services\Market;

use App\Mail\VendorApproved;
use App\Models\Shop;
use App\Models\User;
use App\Services\Payments\PaystackClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Approving a shop: its Paystack payout account, the vendor's login and the welcome email.
 */
class ShopApprovals
{
    public function __construct(private PaystackClient $paystack) {}

    /**
     * Approve the shop. Returns a note for the admin if the payout account couldn't be set up yet;
     * the shop is approved either way, but can't take orders until it has one.
     */
    public function approve(Shop $shop): ?string
    {
        $note = $this->ensurePayoutAccount($shop);

        DB::transaction(function () use ($shop) {
            $user = $shop->user ?? User::firstOrNew(['email' => $shop->email]);
            if (! $user->exists) {
                $user->forceFill(['name' => $shop->owner_name, 'password' => Str::password(32)])->save();
            }

            $shop->forceFill([
                'user_id' => $user->id,
                'status' => Shop::APPROVED,
                'decline_reason' => null,
                'approved_at' => $shop->approved_at ?? now(),
            ])->save();
        });

        $this->sendWelcome($shop->fresh('user'));

        return $note;
    }

    /**
     * Create the vendor's Paystack subaccount if it doesn't have one. Returns a problem, or null.
     */
    public function ensurePayoutAccount(Shop $shop): ?string
    {
        if ($shop->subaccount_code) {
            return null;
        }
        if (! $this->paystack->enabled()) {
            return 'Online payments are off, so the payout account will be set up once Paystack is connected.';
        }
        if (! $shop->bank_code) {
            return 'The shop\'s bank wasn\'t picked from Paystack\'s list, so its payout account couldn\'t be created.';
        }

        try {
            $result = $this->paystack->createSubaccount($shop->name, $shop->bank_code, $shop->account_number, $shop->email);
        } catch (RuntimeException $e) {
            Log::warning('Could not create shop subaccount', ['shop' => $shop->id, 'error' => $e->getMessage()]);

            return 'Paystack couldn\'t create the payout account ('.$e->getMessage().'). Check the bank details and try again.';
        }

        $shop->forceFill(['subaccount_code' => $result['code'], 'account_name' => $result['account_name']])->save();

        return null;
    }

    private function sendWelcome(Shop $shop): void
    {
        try {
            $token = Password::broker()->createToken($shop->user);
            $url = route('vendor.password.edit', ['token' => $token, 'email' => $shop->user->email]);
            Mail::to($shop->email)->send(new VendorApproved($shop, $url));
        } catch (Throwable $e) {
            Log::error('Could not send vendor welcome', ['shop' => $shop->id, 'error' => $e->getMessage()]);
        }
    }
}
