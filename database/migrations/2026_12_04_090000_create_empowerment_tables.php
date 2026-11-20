<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Youth empowerment: programmes people apply to (each with a banner, dates and the choices on
 * offer, such as catering or photography), the questions on its form, and the applications.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empowerment_programmes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 120);
            $table->string('tagline', 200)->nullable();
            $table->text('intro')->nullable();
            $table->string('banner_path')->nullable();
            $table->date('opens_on')->nullable();
            $table->date('closes_on')->nullable();
            $table->unsignedTinyInteger('max_choices')->default(1);
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->boolean('is_published')->default(false);
            $table->text('thank_you')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('empowerment_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empowerment_programme_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('slots')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('empowerment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empowerment_programme_id')->constrained()->cascadeOnDelete();
            $table->string('label', 200);
            $table->string('help', 300)->nullable();
            $table->string('type', 20)->default('text');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('empowerment_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empowerment_programme_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 16)->unique();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('phone', 40);
            $table->string('email')->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('location', 150)->nullable();
            $table->json('answers')->nullable();
            $table->string('status', 15)->default('new')->index();
            $table->foreignId('empowerment_track_id')->nullable()->constrained()->nullOnDelete(); // the choice they were accepted for
            $table->text('admin_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['empowerment_programme_id', 'status']);
        });

        Schema::create('empowerment_application_track', function (Blueprint $table) {
            $table->foreignId('empowerment_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('empowerment_track_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rank')->default(1);
            $table->primary(['empowerment_application_id', 'empowerment_track_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empowerment_application_track');
        Schema::dropIfExists('empowerment_applications');
        Schema::dropIfExists('empowerment_questions');
        Schema::dropIfExists('empowerment_tracks');
        Schema::dropIfExists('empowerment_programmes');
    }
};
