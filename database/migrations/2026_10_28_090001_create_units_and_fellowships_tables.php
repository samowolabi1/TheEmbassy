<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('leader_name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('fellowships', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('host_name')->nullable();
            $table->string('area')->nullable();
            $table->string('meets')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fellowships');
        Schema::dropIfExists('units');
    }
};
