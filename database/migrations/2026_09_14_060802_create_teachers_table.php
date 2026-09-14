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
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('nip', 50)->nullable()->unique();
            $table->string('employee_number', 50)->nullable()->unique();

            $table->string('gender', 20);

            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();

            $table->text('address')->nullable();

            $table->string('employment_status', 30);
            $table->date('join_date')->nullable();

            $table->string('status', 30)->default('ACTIVE');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
