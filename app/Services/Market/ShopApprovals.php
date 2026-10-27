<?php

namespace App\Services\Market;

use App\Mail\VendorApproved;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

/**
 * Approving a shop: the vendor's login and the welcome email.
 */
class ShopApprovals
{
    public function approve(Shop $shop): void
    {
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
