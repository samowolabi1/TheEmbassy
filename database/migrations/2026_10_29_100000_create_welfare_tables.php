<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('welfare_cases', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            // A member, or someone outside the church (outreach) by name and phone.
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('beneficiary_name')->nullable();
            $table->string('beneficiary_phone', 40)->nullable();
            $table->string('category', 20)->index();
            $table->string('summary', 160);
            $table->text('details')->nullable();
            $table->string('urgency', 10)->default('normal');
            $table->unsignedBigInteger('amount_requested')->nullable(); // kobo
            $table->unsignedBigInteger('amount_approved')->nullable();
            // new, in_review, awaiting_pastor, approved, declined, supported, closed
            $table->string('status', 20)->default('new')->index();
            $table->string('source', 10)->default('member'); // member, leader, staff
            $table->foreignId('assigned_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('raised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->date('next_check_on')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        // The case log: calls, visits, support given and notes. Only the welfare team sees these.
        Schema::create('welfare_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('welfare_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 12); // note, call, visit, support, status
            $table->text('body');
            $table->date('happened_on');
            $table->timestamps();
        });

        Schema::create('welfare_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('welfare_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('method', 20);
            $table->date('paid_on');
            $table->string('reference')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->date('anniversary')->nullable()->after('birthday');
        });
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('anniversary'));
        Schema::dropIfExists('welfare_payments');
        Schema::dropIfExists('welfare_notes');
        Schema::dropIfExists('welfare_cases');
    }
};
