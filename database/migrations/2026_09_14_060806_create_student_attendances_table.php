<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('student_id')
                ->constrained('students')
                ->restrictOnDelete();

            $table->foreignId('class_id')
                ->constrained('school_classes')
                ->restrictOnDelete();

            $table->foreignId('attendance_schedule_id')
                ->nullable()
                ->constrained('attendance_schedules')
                ->nullOnDelete();

            $table->foreignId('school_location_id')
                ->nullable()
                ->constrained('school_locations')
                ->nullOnDelete();

            $table->date('attendance_date');

            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();

            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();

            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();

            $table->decimal('location_accuracy', 10, 2)->nullable();
            $table->decimal('distance_from_school', 10, 2)->nullable();

            $table->string('status', 30);
            $table->unsignedInteger('late_minutes')->default(0);

            $table->string('source', 30)
                ->default('GEOLOCATION');

            $table->text('notes')->nullable();

            $table->foreignId('corrected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('correction_reason')->nullable();

            $table->timestamps();

            $table->unique([
                'student_id',
                'attendance_date'
            ]);

            $table->index([
                'class_id',
                'attendance_date'
            ]);

            $table->index([
                'status',
                'attendance_date'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_attendances');
    }
};
