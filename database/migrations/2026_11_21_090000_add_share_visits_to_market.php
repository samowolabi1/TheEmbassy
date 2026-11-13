<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Visits that came from links sellers shared, so they can see sharing working.
     */
    public function up(): void
    {
        Schema::table('shops', fn (Blueprint $table) => $table->unsignedInteger('share_visits')->default(0));
        Schema::table('products', fn (Blueprint $table) => $table->unsignedInteger('share_visits')->default(0));
    }

    public function down(): void
    {
        Schema::table('shops', fn (Blueprint $table) => $table->dropColumn('share_visits'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('share_visits'));
    }
};
