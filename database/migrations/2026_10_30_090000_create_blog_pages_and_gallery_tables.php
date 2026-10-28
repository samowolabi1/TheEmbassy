<?php

use App\Services\Content\LegacyContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Blog posts, editable page content (About and home), and the photo gallery.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->json('previous_slugs')->nullable(); // old addresses that redirect here
            $table->string('topic', 60);
            $table->string('dek', 300)->nullable();
            $table->longText('body')->nullable(); // Markdown
            $table->longText('body_html')->nullable(); // rendered and cleaned on save
            $table->string('cover_path')->nullable();
            $table->string('cover_thumb_path')->nullable();
            $table->string('cover_alt', 200)->nullable();
            $table->string('swatch', 20)->default('navy');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name', 100)->nullable();
            $table->string('author_bio', 300)->nullable();
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->boolean('is_featured')->default(false);
            $table->string('meta_description', 300)->nullable();
            $table->timestamp('published_at')->nullable()->index(); // null = draft, future = scheduled
            $table->timestamps();
        });

        Schema::create('page_contents', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->json('content');
            $table->timestamps();
        });

        Schema::create('gallery_albums', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->date('taken_on')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('cover_item_id')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_album_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10)->default('photo'); // photo or video
            $table->string('path')->nullable();
            $table->string('thumb_path')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('youtube_id', 20)->nullable();
            $table->string('caption', 200)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // Bring the starter articles and page text across from config/content so nothing disappears.
        LegacyContent::import();
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_items');
        Schema::dropIfExists('gallery_albums');
        Schema::dropIfExists('page_contents');
        Schema::dropIfExists('posts');
    }
};
