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
        Schema::create('spp_bills', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('bill_number', 50)
                ->unique();

            $table->foreignId('student_id')
                ->constrained('students')
                ->restrictOnDelete();

            $table->foreignId('academic_year_id')
                ->constrained('academic_years')
                ->restrictOnDelete();

            $table->unsignedTinyInteger('billing_month');
            $table->unsignedSmallInteger('billing_year');

            $table->decimal('amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)
                ->default(0);

            $table->date('due_date');

            $table->string('status', 30)
                ->default('PENDING');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique([
                'student_id',
                'academic_year_id',
                'billing_month',
                'billing_year'
            ]);

            $table->index([
                'student_id',
                'status'
            ]);

            $table->index([
                'status',
                'due_date'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spp_bills');
    }
};
