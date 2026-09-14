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
        Schema::create('student_class_enrollments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained('students')
                ->restrictOnDelete();

            $table->foreignId('class_id')
                ->constrained('school_classes')
                ->restrictOnDelete();

            $table->date('joined_at');
            $table->date('left_at')->nullable();

            $table->string('status', 30)
                ->default('ACTIVE');

            $table->timestamps();

            $table->unique([
                'student_id',
                'class_id'
            ]);

            $table->index([
                'class_id',
                'status'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_class_enrollments');
    }
};
