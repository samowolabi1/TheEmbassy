<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Members booking a visit with a pastor (from the pastors' weekly hours, less their time off),
     * and members sharing testimonies, which the church publishes once a pastor or the office has
     * read them.
     */
    public function up(): void
    {
        Schema::create('pastor_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the pastor
            $table->unsignedTinyInteger('weekday'); // 0 Sunday … 6 Saturday
            $table->string('starts', 5); // 10:00
            $table->string('ends', 5); // 13:00
            $table->unsignedSmallInteger('slot_minutes')->default(30);
            $table->string('mode', 12)->default('both'); // in_person, call, both
            $table->string('location', 150)->nullable(); // e.g. Pastor's office, church building
            $table->timestamps();
        });

        Schema::create('pastor_time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('note', 150)->nullable();
            $table->timestamps();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 12)->unique(); // AP-XXXXXX
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pastor_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('mode', 12); // in_person, call
            $table->string('topic', 20);
            $table->text('note')->nullable(); // only the pastor (and the pastor in charge) sees this
            $table->string('status', 12)->default('requested'); // requested, confirmed, declined, cancelled, completed, missed
            $table->string('staff_note', 300)->nullable(); // to the member: where to come, or why not
            $table->string('call_link', 500)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
            $table->index(['pastor_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });

        Schema::create('testimonies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('body');
            $table->string('category', 20);
            $table->string('name_display', 10)->default('full'); // full, first, anonymous
            $table->boolean('share_publicly')->default(true); // on the website
            $table->boolean('share_in_church')->default(true); // may be read out in a service
            $table->string('status', 12)->default('pending'); // pending, published, declined, withdrawn
            $table->string('review_note', 300)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('praises')->default(0);
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        Schema::create('testimony_praises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('testimony_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['testimony_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimony_praises');
        Schema::dropIfExists('testimonies');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('pastor_time_off');
        Schema::dropIfExists('pastor_hours');
    }
};
