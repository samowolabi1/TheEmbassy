<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Birthday designs the media unit uploads: a finished picture, and where on it the member's
     * photo, name and day go. The single background picture from before becomes one of these.
     */
    public function up(): void
    {
        Schema::create('birthday_designs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('background_path');
            $table->json('layout')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $background = DB::table('settings')->where('key', 'church.birthday.background')->value('value');
        if (filled($background)) {
            $id = DB::table('birthday_designs')->insertGetId(['name' => 'Our background', 'background_path' => $background, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('settings')->updateOrInsert(['key' => 'church.birthday.design'], ['value' => 'upload-'.$id, 'updated_at' => now()]);
        }
        DB::table('settings')->where('key', 'church.birthday.background')->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('birthday_designs');
    }
};
