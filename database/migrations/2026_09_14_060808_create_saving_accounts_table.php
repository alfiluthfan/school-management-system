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
        Schema::create('saving_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('student_id')
                ->unique()
                ->constrained('students')
                ->restrictOnDelete();

            $table->string('account_number', 50)
                ->unique();

            $table->decimal('current_balance', 15, 2)
                ->default(0);

            $table->string('status', 30)
                ->default('ACTIVE');

            $table->timestamp('opened_at');

            $table->timestamp('closed_at')
                ->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saving_accounts');
    }
};
