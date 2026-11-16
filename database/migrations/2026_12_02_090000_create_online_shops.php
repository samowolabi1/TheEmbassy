<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each shop's own online storefront: a banner, logo and colour, an about section, opening hours,
 * delivery and returns, social links, sections (collections) to arrange products in, and several
 * photos per product, with a sale price and featured items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('tagline', 120)->nullable();
            $table->text('about')->nullable();
            $table->string('banner_path')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('accent', 12)->default('navy');
            $table->string('announcement', 160)->nullable();
            $table->string('hours', 200)->nullable();
            $table->string('address', 255)->nullable();
            $table->text('delivery_info')->nullable();
            $table->text('returns_info')->nullable();
            $table->string('instagram', 100)->nullable();
            $table->string('facebook', 255)->nullable();
            $table->string('tiktok', 100)->nullable();
            $table->string('website', 255)->nullable();
        });

        Schema::create('shop_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('slug', 80);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['shop_id', 'slug']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('shop_collection_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('compare_price')->nullable();
            $table->boolean('is_featured')->default(false);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // Each product's existing photo becomes its first (cover) photo.
        DB::table('products')->whereNotNull('image_path')->orderBy('id')->select(['id', 'image_path'])->chunk(500, fn ($rows) => DB::table('product_images')->insert(
            $rows->map(fn ($r) => ['product_id' => $r->id, 'path' => $r->image_path, 'position' => 0, 'created_at' => now(), 'updated_at' => now()])->all()
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shop_collection_id');
            $table->dropColumn(['compare_price', 'is_featured']);
        });
        Schema::dropIfExists('shop_collections');
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['tagline', 'about', 'banner_path', 'logo_path', 'accent', 'announcement', 'hours', 'address', 'delivery_info', 'returns_info', 'instagram', 'facebook', 'tiktok', 'website']);
        });
    }
};
