<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * The weekly newsletter, app push notifications, and announcements sent by SMS and push.
     */
    public function up(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->string('token', 40)->nullable()->unique(); // in confirm and unsubscribe links
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
        });
        // People who signed up before confirmation existed stay subscribed.
        foreach (DB::table('newsletter_subscribers')->pluck('id') as $id) {
            DB::table('newsletter_subscribers')->where('id', $id)->update(['token' => Str::random(40)]);
        }
        DB::table('newsletter_subscribers')->update(['confirmed_at' => DB::raw('created_at')]);

        Schema::create('newsletter_issues', function (Blueprint $table) {
            $table->id();
            $table->date('week_of')->unique();
            $table->string('subject');
            $table->string('status', 12)->default('queued'); // queued, sending, sent
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->boolean('notify_sms')->default(true); // church announcements by text message
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->unique();
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->unsignedSmallInteger('failures')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 120);
            $table->string('message', 480);
            $table->string('url')->nullable();
            $table->json('channels'); // sms, push
            $table->string('status', 12)->default('queued'); // queued, sending, sent
            $table->unsignedInteger('sms_cursor')->default(0); // last member id texted
            $table->unsignedInteger('sms_sent')->default(0);
            $table->unsignedInteger('sms_failed')->default(0);
            $table->text('sms_error')->nullable();
            $table->unsignedInteger('push_cursor')->default(0); // last subscription id notified
            $table->unsignedInteger('push_sent')->default(0);
            $table->unsignedInteger('push_failed')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
        Schema::dropIfExists('push_subscriptions');
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('notify_sms'));
        Schema::dropIfExists('newsletter_issues');
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropColumn(['token', 'confirmed_at', 'unsubscribed_at', 'last_sent_at']);
        });
    }
};
