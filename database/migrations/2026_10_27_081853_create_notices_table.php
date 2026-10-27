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
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            // Who is advertising: another church or a business.
            $table->string('advertiser_type', 20);
            $table->string('organisation');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->string('category', 40);
            $table->string('title');
            $table->text('body');
            $table->string('url')->nullable();
            $table->string('image_path')->nullable();
            // The package bought: how many days it runs, the price in kobo, and whether it's featured.
            $table->string('package', 40);
            $table->unsignedSmallInteger('days');
            $table->boolean('featured')->default(false);
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('awaiting_payment')->index();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notices');
    }
};
