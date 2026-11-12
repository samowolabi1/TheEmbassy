<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tithes and offerings come in by bank transfer only; donations can also be paid through Paystack.
     * The church's bank accounts, in each currency it receives, are listed on the Give page.
     */
    public function up(): void
    {
        Schema::table('funds', function (Blueprint $table) {
            $table->boolean('online')->default(true); // can be given through Paystack
        });
        // Tithes and offerings: bank transfer only.
        DB::table('funds')->where(fn ($q) => $q->whereRaw('lower(name) like ?', ['%tithe%'])->orWhereRaw('lower(name) like ?', ['%offering%']))
            ->update(['online' => false]);

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('currency', 3); // NGN, USD, GBP, EUR…
            $table->string('bank_name', 100);
            $table->string('account_name', 150);
            $table->string('account_number', 40);
            $table->string('sort_code', 20)->nullable(); // UK
            $table->string('routing_number', 20)->nullable(); // US
            $table->string('swift', 20)->nullable(); // international transfers
            $table->string('iban', 40)->nullable();
            $table->string('note', 200)->nullable(); // e.g. "Domiciliary account" or what to put as the reference
            $table->string('use_for', 12)->default('all'); // all, tithe (tithes & offerings), donations
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // The single account saved in Settings becomes the first naira account.
        $saved = DB::table('settings')->whereIn('key', ['church.bank.name', 'church.bank.account_number', 'church.bank.account_name'])->pluck('value', 'key');
        if (filled($saved['church.bank.account_number'] ?? null) && filled($saved['church.bank.name'] ?? null)) {
            DB::table('bank_accounts')->insert([
                'currency' => 'NGN',
                'bank_name' => $saved['church.bank.name'],
                'account_name' => $saved['church.bank.account_name'] ?? config('church.name'),
                'account_number' => $saved['church.bank.account_number'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
        Schema::table('funds', fn (Blueprint $table) => $table->dropColumn('online'));
    }
};
