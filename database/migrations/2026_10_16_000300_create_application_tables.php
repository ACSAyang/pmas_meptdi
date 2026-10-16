<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave / pass / other applications, configurable types, versioned document
 * checklists, versioned approval workflows and the decision history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            // Accountability category used while the member is away on this type
            $table->string('category', 20);
            // Policy values are nullable: null = "not configured / no rule"
            $table->unsignedSmallInteger('lead_time_days')->nullable();
            $table->unsignedSmallInteger('max_duration_days')->nullable();
            $table->boolean('requires_destination')->default(true);
            $table->boolean('requires_movement')->default(true);
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('allow_on_behalf')->default(true);
            $table->string('color', 20)->default('navy');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_type_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_sensitive')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamp('active_from')->nullable();
            $table->timestamp('active_to')->nullable();
            $table->timestamps();
            $table->unique(['application_type_id', 'version']);
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('name', 100);
            $table->string('action_type', 20); // review | approve
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->boolean('requires_documents_complete')->default(false);
            $table->timestamps();
            $table->unique(['approval_workflow_id', 'step_order']);
        });

        Schema::create('personnel_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 40)->nullable()->unique();
            $table->foreignId('personnel_id')->constrained('personnel')->restrictOnDelete();
            $table->foreignId('application_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('approval_workflow_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('current_step_id')->nullable()->constrained('approval_steps')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->text('purpose');
            $table->string('destination', 200)->nullable();
            $table->string('contact_while_away', 100)->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->boolean('is_late')->default(false);
            $table->boolean('is_retrospective')->default(false);
            $table->text('late_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->index(['personnel_id', 'start_at']);
            $table->index(['status', 'current_step_id']);
        });

        Schema::create('application_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('personnel_applications')->cascadeOnDelete();
            $table->foreignId('approval_step_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->index(['application_id', 'decided_at']);
        });

        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('personnel_applications')->cascadeOnDelete();
            $table->foreignId('document_requirement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name', 255);
            $table->string('storage_path', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('review_status', 20)->default('pending'); // pending | verified | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_remarks')->nullable();
            $table->boolean('is_superseded')->default(false);
            $table->timestamps();
        });

        Schema::create('reference_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['prefix', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_sequences');
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('application_decisions');
        Schema::dropIfExists('personnel_applications');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_workflows');
        Schema::dropIfExists('document_requirements');
        Schema::dropIfExists('application_types');
    }
};
