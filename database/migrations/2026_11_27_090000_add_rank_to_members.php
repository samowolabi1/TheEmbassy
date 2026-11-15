<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Member → worker (in a unit, made by its head) → minister (made by a pastor).
        Schema::table('members', function (Blueprint $table) {
            $table->string('rank', 10)->default('member')->index();
            $table->timestamp('rank_changed_at')->nullable();
            $table->foreignId('rank_changed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rank_changed_by');
            $table->dropColumn(['rank', 'rank_changed_at']);
        });
    }
};
