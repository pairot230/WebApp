<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('dorms', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dorm_id')->constrained('dorms')->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unique(['dorm_id', 'code']);
            $table->timestamps();
        });

        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained('buildings')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('name')->nullable();
            $table->unique(['building_id', 'number']);
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('floor_id')->constrained('floors')->restrictOnDelete();
            $table->string('number');
            $table->unsignedSmallInteger('capacity')->default(2);
            $table->boolean('is_active')->default(true);
            $table->unique(['floor_id', 'number']);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('google_id')->nullable()->unique();
            $table->string('student_id')->nullable()->unique();
            $table->string('faculty')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('role')->default('user');
            $table->foreignId('dorm_id')->nullable()->constrained('dorms')->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->restrictOnDelete();
            $table->uuid('qr_token')->nullable()->unique();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dorm_id')->constrained('dorms')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('location');
            $table->unsignedInteger('score');
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('activity_id')->constrained('activities')->restrictOnDelete();
            $table->foreignId('checked_in_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('checked_in_at');
            $table->unique(['user_id', 'activity_id']);
            $table->timestamps();
        });

        Schema::create('score_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('activities')->restrictOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained('attendances')->restrictOnDelete();
            $table->integer('score');
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unique('attendance_id');
            $table->index(['user_id', 'academic_year_id']);
            $table->timestamps();
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('dorm_id')->constrained('dorms')->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('status')->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->index(['dorm_id', 'status']);
            $table->timestamps();
        });

        Schema::create('repair_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('dorm_id')->constrained('dorms')->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->restrictOnDelete();
            $table->string('reporter_name');
            $table->string('reporter_type');
            $table->string('room_label');
            $table->string('category');
            $table->text('description');
            $table->string('phone', 30);
            $table->string('email');
            $table->date('appointment_date');
            $table->time('appointment_time');
            $table->string('status')->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->index(['dorm_id', 'status']);
            $table->timestamps();
        });

        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dorm_id')->constrained('dorms')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('type');
            $table->string('category');
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->date('transaction_date');
            $table->text('description')->nullable();
            $table->string('status')->default('posted');
            $table->boolean('is_public')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->index(['dorm_id', 'academic_year_id', 'type', 'status'], 'finance_scope_index');
            $table->timestamps();
        });

        Schema::create('membership_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('dorm_id')->constrained('dorms')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('financial_transaction_id')->nullable()->constrained('financial_transactions')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->dateTime('paid_at')->nullable();
            $table->string('status')->default('pending');
            $table->text('note')->nullable();
            $table->unique(['user_id', 'academic_year_id']);
            $table->unique('financial_transaction_id');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_type', 'target_id']);
            $table->index(['actor_id', 'created_at']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('membership_payments');
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('repair_requests');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('score_histories');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('activities');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_id');
            $table->dropConstrainedForeignId('dorm_id');
            $table->dropUnique(['google_id']);
            $table->dropUnique(['student_id']);
            $table->dropUnique(['qr_token']);
            $table->dropColumn(['first_name', 'last_name', 'google_id', 'student_id', 'faculty', 'phone', 'role', 'qr_token', 'is_active']);
        });

        Schema::dropIfExists('rooms');
        Schema::dropIfExists('floors');
        Schema::dropIfExists('buildings');
        Schema::dropIfExists('dorms');
        Schema::dropIfExists('academic_years');
    }
};
