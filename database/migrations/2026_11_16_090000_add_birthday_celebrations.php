<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Birthday cards and celebrations: a member's photo for their card, whether they want their
     * birthday celebrated publicly, and a record of each year's celebration (the card, and the
     * community post that carries it).
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('photo_path')->nullable(); // for the birthday card
            $table->boolean('celebrate_birthday')->default(true);
        });

        Schema::table('community_messages', function (Blueprint $table) {
            $table->string('kind', 20)->nullable(); // birthday: posted by the church on someone's birthday
        });

        Schema::create('birthday_celebrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('card_path');
            $table->foreignId('community_message_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['member_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('birthday_celebrations');
        Schema::table('community_messages', fn (Blueprint $table) => $table->dropColumn('kind'));
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn(['photo_path', 'celebrate_birthday']));
    }
};
