<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes on a follow-up that's still open (a call with no answer, a unit head checking in).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->text('log')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('follow_ups', fn (Blueprint $table) => $table->dropColumn('log'));
    }
};
