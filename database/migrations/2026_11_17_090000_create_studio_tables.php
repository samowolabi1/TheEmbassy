<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The media unit's live studio: what's on screen over the stream (a full-screen scene, a name
     * caption, a scripture, a scrolling ticker, the logo), shown in OBS through a secret overlay
     * link; the captions and scriptures saved for quick use; and links for remote guests.
     */
    public function up(): void
    {
        Schema::create('studio_screens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique(); // the overlay link for OBS
            $table->string('scene', 20)->nullable(); // starting, brb, ending, message
            $table->string('scene_text', 200)->nullable();
            $table->timestamp('countdown_to')->nullable();
            $table->json('lower_third')->nullable(); // {name, title}
            $table->json('scripture')->nullable(); // {reference, text}
            $table->string('ticker', 500)->nullable();
            $table->boolean('show_logo')->default(false);
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });

        Schema::create('studio_presets', function (Blueprint $table) {
            $table->id();
            $table->string('type', 12); // caption, scripture
            $table->string('line1', 120); // a name, or a reference
            $table->string('line2', 600)->nullable(); // their title, or the verse
            $table->timestamps();
        });

        Schema::create('studio_guests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('stream_id', 40)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_guests');
        Schema::dropIfExists('studio_presets');
        Schema::dropIfExists('studio_screens');
    }
};
