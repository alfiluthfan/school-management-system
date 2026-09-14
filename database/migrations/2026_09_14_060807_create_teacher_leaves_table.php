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
        Schema::create('teacher_leaves', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('teacher_id')
                ->constrained('teachers')
                ->restrictOnDelete();

            $table->date('start_date');
            $table->date('end_date');

            $table->string('leave_type', 30);

            $table->text('reason');

            $table->string('attachment_path')
                ->nullable();

            $table->string('status', 30)
                ->default('PENDING');

            $table->timestamps();

            $table->index([
                'teacher_id',
                'status'
            ]);

            $table->index([
                'start_date',
                'end_date'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_leaves');
    }
};
