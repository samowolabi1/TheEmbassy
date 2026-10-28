<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The church's YouTube channel, connected with Google sign-in. One row: the church has one channel.
        Schema::create('youtube_connections', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id');
            $table->string('channel_title');
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('expires_at');
            // The reusable stream every broadcast is bound to, so the key in OBS never changes.
            $table->string('stream_id')->nullable();
            // Set when Google refuses the refresh token (revoked, or a "Testing" app's 7-day limit).
            $table->string('problem')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('service_setups', function (Blueprint $table) {
            // The broadcast the app created on YouTube (its ID is also the video ID).
            $table->boolean('youtube_managed')->default(false)->after('youtube_video_id');
            $table->string('youtube_status', 20)->nullable()->after('youtube_managed'); // created, ready, testing, live, complete, revoked
            $table->string('youtube_error')->nullable()->after('youtube_status');
        });
    }

    public function down(): void
    {
        Schema::table('service_setups', fn (Blueprint $table) => $table->dropColumn(['youtube_managed', 'youtube_status', 'youtube_error']));
        Schema::dropIfExists('youtube_connections');
    }
};
