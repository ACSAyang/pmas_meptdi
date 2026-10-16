<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actual movements, duty/training assignments, accountability events
 * (musters / verifications) and verification cases.
 *
 * Authorization (applications) and actual movement are recorded separately (BR-008).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->restrictOnDelete();
            $table->string('assignment_type', 30); // training | schooling | official_business | temporary_duty | detail
            $table->string('title', 200);
            $table->string('location', 200)->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('reference_number', 100)->nullable();
            $table->string('responsible_office', 150)->nullable();
            $table->boolean('is_away')->default(true);
            $table->string('status', 20)->default('scheduled');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['personnel_id', 'start_at']);
        });

        Schema::create('personnel_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->restrictOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('personnel_applications')->nullOnDelete();
            $table->foreignId('duty_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 20);
            $table->string('destination', 200)->nullable();
            $table->string('authority_reference', 150)->nullable();
            $table->dateTime('expected_departure_at');
            $table->dateTime('expected_return_at');
            $table->dateTime('original_expected_return_at');
            $table->dateTime('actual_departure_at')->nullable();
            $table->dateTime('actual_return_at')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->foreignId('departure_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('departure_remarks')->nullable();
            $table->foreignId('return_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('return_remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['personnel_id', 'status']);
            $table->index(['status', 'expected_return_at']);
            $table->index(['actual_departure_at', 'actual_return_at']);
        });

        Schema::create('accountability_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->restrictOnDelete();
            $table->string('event_type', 20); // present | sick_call | absent
            $table->dateTime('occurred_at');
            $table->string('source', 20)->default('muster'); // muster | return | manual
            $table->foreignId('personnel_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('remarks')->nullable();
            // Events are never edited; an erroneous entry is voided with a reason (BR-014).
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();
            $table->index(['personnel_id', 'occurred_at']);
        });

        Schema::create('verification_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->restrictOnDelete();
            $table->foreignId('personnel_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 20); // overdue | unverified | conflict
            $table->string('status', 20)->default('open');
            $table->timestamp('opened_at');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamps();
            $table->index(['status', 'reason']);
        });

        Schema::create('verification_case_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action_type', 30); // contact_attempt | note | escalation
            $table->text('details');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_case_actions');
        Schema::dropIfExists('verification_cases');
        Schema::dropIfExists('accountability_events');
        Schema::dropIfExists('personnel_movements');
        Schema::dropIfExists('duty_assignments');
    }
};
