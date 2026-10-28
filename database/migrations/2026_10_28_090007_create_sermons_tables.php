<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sermon_series', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('accent')->nullable(); // second line in gold, e.g. "for More"
            $table->string('style', 10)->default('navy');
            $table->string('url')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('sermons', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('kind', 20)->index(); // sunday, midweek, podcast, special
            $table->foreignId('sermon_series_id')->nullable()->constrained()->nullOnDelete();
            $table->string('preacher')->nullable();
            $table->date('preached_on')->index();
            $table->string('length', 20)->nullable();
            $table->string('url')->nullable();
            $table->string('swatch', 10)->default('navy');
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sermons');
        Schema::dropIfExists('sermon_series');
    }
};
