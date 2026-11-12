<?php

namespace App\Services\Tokens;

use App\Mail\TokenOrderPlaced;
use App\Models\Product;
use App\Models\TokenOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Buying from the market with tokens. The buyer's tokens go into escrow when they order; the seller
 * gets them when they hand it over, or the buyer gets them back if it's cancelled.
 */
class TokenShop
{
    public function __construct(private Ledger $ledger) {}

    public function buy(User $buyer, Product $product, ?string $note = null): TokenOrder
    {
        $product->loadMissing('shop');
        if (! $product->acceptsTokens() || ! $product->is_active || ! $product->shop?->isApproved() || ! $product->shop->isSubscribed()) {
            throw ValidationException::withMessages(['tokens' => 'This listing can\'t be bought with tokens.']);
        }
        if (! $buyer->canUseTokens()) {
            throw ValidationException::withMessages(['tokens' => 'Tokens are for church members only.']);
        }
        if ($product->shop->user_id === $buyer->id) {
            throw ValidationException::withMessages(['tokens' => 'That\'s your own listing.']);
        }

        $order = DB::transaction(function () use ($buyer, $product, $note) {
            // One left means one order: stock is taken under a lock.
            $fresh = Product::whereKey($product->id)->lockForUpdate()->first();
            if ($fresh->stock !== null) {
                if ($fresh->stock < 1) {
                    throw ValidationException::withMessages(['tokens' => 'Sorry, this is out of stock.']);
                }
                $fresh->decrement('stock');
            }

            $order = TokenOrder::create([
                'product_id' => $product->id,
                'shop_id' => $product->shop_id,
                'user_id' => $buyer->id,
                'product_name' => $product->name,
                'tokens' => $product->token_price,
                'note' => $note ? mb_substr(trim($note), 0, 300) : null,
                'status' => TokenOrder::PENDING,
            ]);
            // Held in escrow until the seller hands it over.
            $this->ledger->move($this->ledger->account($buyer), $this->ledger->escrow(), $product->token_price, 'purchase', 'Bought '.$product->name.' from '.$product->shop->name.' ('.$order->code.')', "order:{$order->id}");

            return $order;
        });

        if ($product->shop->email) {
            try {
                Mail::to($product->shop->email)->send(new TokenOrderPlaced($order->load('buyer.member', 'shop')));
            } catch (Throwable $e) {
                Log::warning('Token order email not sent', ['order' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        return $order;
    }

    /**
     * The seller has handed it over: the tokens go to them.
     */
    public function complete(TokenOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = TokenOrder::whereKey($order->id)->lockForUpdate()->first();
            if ($locked->status !== TokenOrder::PENDING) {
                throw ValidationException::withMessages(['code' => "Order {$order->code} is already ".($locked->status === TokenOrder::COMPLETED ? 'handed over' : 'cancelled').'.']);
            }
            $locked->update(['status' => TokenOrder::COMPLETED, 'completed_at' => now()]);
            $this->ledger->move($this->ledger->escrow(), $this->ledger->account($order->shop->user), $order->tokens, 'sale', "Sold {$order->product_name} ({$order->code})", "sale:{$order->id}");
        });
        $order->refresh();
    }

    /**
     * Cancelled by the buyer, declined by the seller, or expired: the buyer gets the tokens back.
     */
    public function cancel(TokenOrder $order, string $by): void
    {
        DB::transaction(function () use ($order, $by) {
            $locked = TokenOrder::whereKey($order->id)->lockForUpdate()->first();
            if ($locked->status !== TokenOrder::PENDING) {
                throw ValidationException::withMessages(['code' => "Order {$order->code} can't be cancelled now."]);
            }
            $locked->update(['status' => TokenOrder::CANCELLED, 'cancelled_by' => $by, 'cancelled_at' => now()]);
            if ($order->product_id && ($product = Product::whereKey($order->product_id)->lockForUpdate()->first()) && $product->stock !== null) {
                $product->increment('stock');
            }
            $this->ledger->move($this->ledger->escrow(), $this->ledger->account($order->buyer, 'code', settling: true), $order->tokens, 'refund', "Returned: {$order->product_name} ({$order->code})", "refund:{$order->id}");
        });
        $order->refresh();
    }

    /**
     * Orders a seller never handed over go back to the buyer.
     */
    public function expireOld(): int
    {
        $count = 0;
        TokenOrder::with('buyer')->where('status', TokenOrder::PENDING)
            ->where('created_at', '<', now()->subDays(TokenOrder::EXPIRES_AFTER_DAYS))
            ->each(function (TokenOrder $order) use (&$count) {
                $this->cancel($order, 'expired');
                $count++;
            });

        return $count;
    }
}
