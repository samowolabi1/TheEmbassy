<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // A listing is either a product (goods, may have stock) or a service (booked, no stock).
            $table->string('type', 10)->default('product')->after('shop_id')->index();
            // For services: what the price covers, e.g. "per hour" or "per session".
            $table->string('price_unit', 30)->nullable()->after('price');
        });

        Schema::table('market_orders', function (Blueprint $table) {
            // For services: when the buyer would like it done.
            $table->date('preferred_date')->nullable()->after('delivery_note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'price_unit']);
        });

        Schema::table('market_orders', function (Blueprint $table) {
            $table->dropColumn('preferred_date');
        });
    }
};
