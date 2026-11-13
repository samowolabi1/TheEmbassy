<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Requests to the media unit (a flyer, a video, photo coverage…) from other units and the
     * church office, and the media unit's private file library, which also keeps what was delivered.
     */
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('description', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('media_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 12)->unique(); // MR-XXXXXX
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete(); // the unit asking; null = church office
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20); // flyer, graphic, video, photography, livestream, slides, other
            $table->string('title', 150);
            $table->text('details');
            $table->date('event_on')->nullable(); // the event it's for, if any
            $table->date('needed_by');
            $table->string('status', 16)->default('new'); // new, in_progress, delivered, completed, declined, cancelled
            $table->foreignId('assigned_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('decline_reason', 300)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'needed_by']);
        });

        Schema::create('media_request_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false); // a note only the media team sees
            $table->timestamps();
        });

        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_folder_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('media_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 12)->default('library'); // library, reference (sent with a request), deliverable
            $table->string('title', 150);
            $table->string('kind', 10); // image, video, audio, document, design, archive, link
            $table->string('path')->nullable(); // on the private disk
            $table->string('thumb_path')->nullable();
            $table->string('original_name', 200)->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable(); // bytes
            $table->string('url', 500)->nullable(); // a link instead of a file (Google Drive, YouTube…)
            $table->string('tags', 300)->nullable();
            $table->string('description', 500)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['purpose', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
        Schema::dropIfExists('media_request_comments');
        Schema::dropIfExists('media_requests');
        Schema::dropIfExists('media_folders');
    }
};
