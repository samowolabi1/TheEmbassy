<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The church library: its books, members borrowing them (reserved, waiting, on loan,
     * returned), and members' reviews, which also show in the community.
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title', 160);
            $table->string('author', 120);
            $table->string('category', 30);
            $table->text('description')->nullable();
            $table->string('isbn', 20)->nullable();
            $table->string('cover_path')->nullable();
            $table->string('cover_thumb_path')->nullable();
            $table->unsignedSmallInteger('copies')->default(1);
            $table->string('shelf', 40)->nullable(); // where it's kept, e.g. "Shelf B2"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'category']);
        });

        Schema::create('book_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            // requested: a copy is free, waiting to be handed over; waiting: in the queue for a copy;
            // on_loan, returned, cancelled.
            $table->string('status', 12);
            $table->timestamp('lent_at')->nullable();
            $table->date('due_on')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->unsignedTinyInteger('renewals')->default(0);
            $table->timestamp('reminded_at')->nullable(); // due soon
            $table->timestamp('overdue_reminded_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 300)->nullable();
            $table->timestamps();
            $table->index(['book_id', 'status']);
            $table->index(['member_id', 'status']);
            $table->index(['status', 'due_on']);
        });

        Schema::create('book_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1 to 5
            $table->text('body');
            $table->timestamp('removed_at')->nullable(); // by a moderator
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['book_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_reviews');
        Schema::dropIfExists('book_loans');
        Schema::dropIfExists('books');
    }
};
