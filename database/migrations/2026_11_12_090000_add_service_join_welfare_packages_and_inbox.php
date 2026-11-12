<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Joining services online from the member's account, welfare packages members apply for,
     * and the member's messages inbox.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('join_url', 500)->nullable(); // Zoom, Google Meet or YouTube link for this service
        });

        Schema::create('welfare_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->string('items', 500)->nullable(); // what's in the package
            $table->unsignedInteger('slots')->nullable(); // how many can be given; null = no fixed number
            $table->timestamp('opens_at');
            $table->timestamp('closes_at');
            $table->string('collection_details', 500)->nullable(); // where and when to collect
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('welfare_package_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('welfare_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 12)->unique(); // shown at collection
            $table->unsignedTinyInteger('household_size')->nullable();
            $table->text('note')->nullable();
            $table->string('status', 12)->default('pending'); // pending, approved, waitlist, declined, collected, withdrawn
            $table->string('decision_note', 300)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();
            $table->unique(['welfare_package_id', 'member_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('inbox_read_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('inbox_read_at'));
        Schema::dropIfExists('welfare_package_applications');
        Schema::dropIfExists('welfare_packages');
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn('join_url'));
    }
};
