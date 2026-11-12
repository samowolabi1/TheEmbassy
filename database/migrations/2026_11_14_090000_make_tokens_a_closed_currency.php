<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tokens become a closed currency. Every token is created (minted) by the pastor in charge into
     * the church treasury; rewards are paid out of the treasury, and tokens spent on subscriptions and
     * tickets flow back into it. Every movement is a transfer from one account to another, so the
     * total in all accounts always equals what was minted less what was destroyed.
     */
    public function up(): void
    {
        Schema::create('token_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 12); // treasury, escrow, member
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('balance')->default(0);
            $table->timestamp('frozen_at')->nullable(); // a frozen account can't send tokens
            $table->string('frozen_reason', 200)->nullable();
            $table->timestamps();
            $table->index('kind');
        });

        Schema::create('token_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_account_id')->nullable()->constrained('token_accounts'); // null = minted
            $table->foreignId('to_account_id')->nullable()->constrained('token_accounts'); // null = destroyed
            $table->unsignedBigInteger('amount');
            $table->string('kind', 30); // mint, burn, reward, grant, reclaim, purchase, sale, refund, subscription, ticket, send
            $table->string('description', 200);
            $table->string('key', 120)->nullable()->unique(); // stops the same reward or payment twice
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['from_account_id', 'id']);
            $table->index(['to_account_id', 'id']);
            $table->index(['kind', 'created_at']);
        });

        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('tokens_paid')->nullable();
        });

        $now = now();
        $treasury = DB::table('token_accounts')->insertGetId(['kind' => 'treasury', 'balance' => 0, 'created_at' => $now, 'updated_at' => $now]);
        $escrow = DB::table('token_accounts')->insertGetId(['kind' => 'escrow', 'balance' => 0, 'created_at' => $now, 'updated_at' => $now]);

        // Bring the old one-sided history across as transfers, with a starting mint that covers it.
        $accounts = [];
        $account = function (int $userId) use (&$accounts, $now) {
            return $accounts[$userId] ??= DB::table('token_accounts')->insertGetId(['kind' => 'member', 'user_id' => $userId, 'balance' => 0, 'created_at' => $now, 'updated_at' => $now]);
        };
        $rows = [];
        if (Schema::hasTable('token_transactions')) {
            foreach (DB::table('token_transactions')->orderBy('id')->get() as $t) {
                $member = $account($t->user_id);
                $kind = match ($t->rule) {
                    'market_purchase' => 'purchase', 'market_sale' => 'sale', 'refund' => 'refund',
                    'subscription' => 'subscription', 'admin' => $t->amount > 0 ? 'grant' : 'reclaim', default => 'reward',
                };
                // Purchases went into holding, sales and refunds came out of it; everything else is the treasury.
                $other = in_array($kind, ['purchase', 'sale', 'refund'], true) ? $escrow : $treasury;
                $rows[] = [
                    'from_account_id' => $t->amount > 0 ? $other : $member,
                    'to_account_id' => $t->amount > 0 ? $member : $other,
                    'amount' => abs($t->amount), 'kind' => $kind, 'description' => $t->description,
                    'key' => $t->key, 'created_by' => $t->created_by, 'created_at' => $t->created_at,
                ];
            }
        }
        $outOfTreasury = collect($rows)->where('from_account_id', $treasury)->sum('amount') - collect($rows)->where('to_account_id', $treasury)->sum('amount');
        if ($outOfTreasury > 0) {
            DB::table('token_transfers')->insert([
                'from_account_id' => null, 'to_account_id' => $treasury, 'amount' => $outOfTreasury, 'kind' => 'mint',
                'description' => 'Tokens already given out before the treasury existed', 'created_at' => $rows[0]['created_at'] ?? $now,
            ]);
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('token_transfers')->insert($chunk);
        }
        // Balances from the ledger, so they agree with it exactly.
        foreach (DB::table('token_accounts')->pluck('id') as $id) {
            $in = DB::table('token_transfers')->where('to_account_id', $id)->sum('amount');
            $out = DB::table('token_transfers')->where('from_account_id', $id)->sum('amount');
            DB::table('token_accounts')->where('id', $id)->update(['balance' => max(0, $in - $out)]);
        }

        Schema::dropIfExists('token_transactions');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('token_balance'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->unsignedInteger('token_balance')->default(0));
        Schema::table('ticket_orders', fn (Blueprint $table) => $table->dropColumn('tokens_paid'));
        Schema::dropIfExists('token_transfers');
        Schema::dropIfExists('token_accounts');
        // The old one-sided history isn't rebuilt.
    }
};
