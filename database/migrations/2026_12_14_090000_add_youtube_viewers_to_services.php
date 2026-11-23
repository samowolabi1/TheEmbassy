<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The most people YouTube showed watching a service live (people watching in
 * the app count too, since the app plays YouTube's own player). It fills the
 * Online headcount until staff type a number of their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedInteger('youtube_peak')->nullable()->after('online');
            $table->timestamp('youtube_peak_at')->nullable()->after('youtube_peak');
            $table->boolean('online_typed')->default(false)->after('youtube_peak_at');
        });
    }

    public function down(): void
    {
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn(['youtube_peak', 'youtube_peak_at', 'online_typed']));
    }
};
