<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 30)->index(); // camera, audio, lighting, computer, network, display, cable, other
            $table->string('tag', 30)->nullable()->unique(); // asset tag on the item
            $table->string('serial')->nullable();
            $table->string('location')->nullable();
            $table->string('condition', 10)->default('good'); // good, fair, faulty
            $table->string('status', 15)->default('available')->index(); // available, checked_out, repair, retired
            $table->date('purchased_on')->nullable();
            $table->unsignedBigInteger('value')->nullable(); // kobo
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('equipment_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->timestamp('out_at');
            $table->date('due_on')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('condition_in', 10)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('equipment_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description');
            $table->string('status', 10)->default('open'); // open, fixed
            $table->unsignedBigInteger('cost')->nullable();
            $table->string('resolution')->nullable();
            $table->timestamp('fixed_at')->nullable();
            $table->timestamps();
        });

        // The media unit's plan for a service: checklist, stream details and when it went live.
        Schema::create('service_setups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('stream_title')->nullable();
            $table->text('stream_description')->nullable();
            $table->string('youtube_video_id', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('went_live_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('setup_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_setup_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Where the church streams to. Keys are stored encrypted.
        Schema::create('stream_destinations', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20); // youtube, facebook, instagram, other
            $table->string('label');
            $table->string('server_url', 500);
            $table->text('stream_key');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->text('caption');
            $table->string('media_path')->nullable();
            $table->string('media_type', 10)->nullable(); // image, video
            $table->json('platforms');
            // draft, awaiting_approval, scheduled, publishing, published, partly_published, failed
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->json('results')->nullable(); // per platform: id, url, container, status, error
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('stream_destinations');
        Schema::dropIfExists('setup_tasks');
        Schema::dropIfExists('service_setups');
        Schema::dropIfExists('equipment_issues');
        Schema::dropIfExists('equipment_loans');
        Schema::dropIfExists('equipment');
    }
};
