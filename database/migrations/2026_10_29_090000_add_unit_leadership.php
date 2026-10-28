<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            // general, welfare or media: welfare and media units get their own tools.
            $table->string('kind', 20)->default('general')->after('name');
            // The head of department and an assistant, both members, who run the unit's area.
            $table->foreignId('hod_member_id')->nullable()->after('leader_name')->constrained('members')->nullOnDelete();
            $table->foreignId('assistant_member_id')->nullable()->after('hod_member_id')->constrained('members')->nullOnDelete();
            // The media unit's setup checklist, one item per line.
            $table->text('checklist')->nullable();
        });

        Schema::create('unit_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->string('online_url', 500)->nullable();
            $table->text('agenda')->nullable();
            $table->text('minutes')->nullable();
            $table->string('secret', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('member_unit_meeting', function (Blueprint $table) {
            $table->foreignId('unit_meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 12)->default('marked'); // code (checked in themselves) or marked
            $table->timestamp('checked_in_at')->nullable();
            $table->primary(['unit_meeting_id', 'member_id']);
        });

        Schema::create('unit_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('subject', 120);
            $table->text('body');
            $table->unsignedInteger('emailed')->default(0);
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // The unit's rota: who serves at which service, doing what.
        Schema::create('duties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('role', 60);
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
            $table->unique(['unit_id', 'service_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duties');
        Schema::dropIfExists('unit_messages');
        Schema::dropIfExists('member_unit_meeting');
        Schema::dropIfExists('unit_meetings');
        Schema::table('units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hod_member_id');
            $table->dropConstrainedForeignId('assistant_member_id');
            $table->dropColumn(['kind', 'checklist']);
        });
    }
};
