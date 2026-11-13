<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prayer requests from members' accounts: they follow them up, hear back from the prayer team,
     * mark them answered, and can let the church pray with them on the members' prayer wall.
     */
    public function up(): void
    {
        Schema::table('prayer_requests', function (Blueprint $table) {
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 20)->nullable();
            $table->boolean('on_wall')->default(false); // other members can see it and pray
            $table->boolean('anonymous')->default(false); // on the wall without a name
            $table->timestamp('answered_at')->nullable();
            $table->string('answer_note', 500)->nullable(); // what God did, in the member's words
            $table->unsignedInteger('prayed_count')->default(0);
            $table->index(['on_wall', 'created_at']);
        });

        Schema::create('prayer_request_prayers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prayer_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['prayer_request_id', 'user_id']);
        });

        Schema::create('prayer_request_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prayer_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_request_replies');
        Schema::dropIfExists('prayer_request_prayers');
        Schema::table('prayer_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_id');
            $table->dropIndex(['on_wall', 'created_at']);
            $table->dropColumn(['category', 'on_wall', 'anonymous', 'answered_at', 'answer_note', 'prayed_count']);
        });
    }
};
