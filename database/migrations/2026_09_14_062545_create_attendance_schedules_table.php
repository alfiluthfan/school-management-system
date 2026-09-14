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
        Schema::create('attendance_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('name', 100);

            $table->string('attendance_type', 30)
                ->index();

            $table->unsignedTinyInteger('day_of_week')
                ->index();

            $table->time('check_in_start')
                ->nullable();

            $table->time('check_in_deadline')
                ->nullable();

            $table->time('check_in_end')
                ->nullable();

            $table->time('check_out_start')
                ->nullable();

            $table->time('check_out_end')
                ->nullable();

            $table->unsignedInteger('late_tolerance_minutes')
                ->default(0);

            $table->foreignId('school_location_id')
                ->nullable()
                ->constrained('school_locations')
                ->nullOnDelete();

            $table->foreignId('academic_year_id')
                ->nullable()
                ->constrained('academic_years')
                ->restrictOnDelete();

            $table->boolean('is_active')
                ->default(true);

            $table->date('effective_from')
                ->nullable();

            $table->date('effective_until')
                ->nullable();

            $table->timestamps();

            $table->index([
                'attendance_type',
                'day_of_week',
                'is_active'
            ]);

            $table->index([
                'academic_year_id',
                'attendance_type'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_schedules');
    }
};
