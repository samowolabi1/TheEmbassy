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
        Schema::create('vendor_plans', function (Blueprint $table) {
            $table->id();
            // monthly, quarterly, half_yearly, yearly or three_years (see config/market.php).
            $table->string('key', 20)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('months');
            // In kobo, set by an admin.
            $table->unsignedBigInteger('price')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_plans');
    }
};
