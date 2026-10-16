<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ranks, organizational units, the personnel masterlist and assignment history.
 *
 * Assignment history is kept in personnel_assignments so historical strength
 * reports use the assignment that was effective at the selected time (BR-015).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('abbreviation', 20)->unique();
            $table->string('category', 20)->default('enlisted'); // officer | enlisted
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('personnel', function (Blueprint $table) {
            $table->id();
            $table->string('service_number', 30)->unique();
            $table->foreignId('rank_id')->constrained()->restrictOnDelete();
            $table->string('first_name', 80);
            $table->string('middle_name', 80)->nullable();
            $table->string('last_name', 80)->index();
            $table->string('suffix', 10)->nullable();
            $table->string('sex', 1)->nullable(); // M | F
            $table->string('designation', 150)->nullable();
            $table->string('email', 150)->nullable();
            // Restricted contact information (personnel.view_sensitive)
            $table->string('contact_number', 30)->nullable();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_number', 30)->nullable();
            $table->string('service_status', 20)->default('active')->index();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('personnel_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_id')->constrained('personnel')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('assignment_type', 20)->default('regular'); // regular | attached | temporary
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('order_reference', 100)->nullable();
            $table->string('end_reason', 150)->nullable();
            $table->string('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['personnel_id', 'start_date']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('personnel_id')->references('id')->on('personnel')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['personnel_id']);
        });
        Schema::dropIfExists('personnel_assignments');
        Schema::dropIfExists('personnel');
        Schema::dropIfExists('units');
        Schema::dropIfExists('ranks');
    }
};
