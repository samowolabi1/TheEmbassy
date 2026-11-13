<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Members' feedback on services they attended, and suggestions on anything else, which the
     * church office and pastors read and answer.
     */
    public function up(): void
    {
        Schema::create('service_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1 to 5
            $table->json('went_well')->nullable(); // worship, word, welcome…
            $table->json('could_improve')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('anonymous')->default(false); // staff don't see who sent it
            $table->timestamps();
            $table->unique(['service_id', 'member_id']);
        });

        Schema::create('suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->string('subject', 120);
            $table->text('body');
            $table->boolean('anonymous')->default(false);
            $table->string('status', 12)->default('new'); // new, reviewing, planned, done, declined
            $table->text('response')->nullable(); // to the member
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestions');
        Schema::dropIfExists('service_feedback');
    }
};
