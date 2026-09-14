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
        Schema::create('saving_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('transaction_number', 50)
                ->unique();

            $table->foreignId('saving_account_id')
                ->constrained('saving_accounts')
                ->restrictOnDelete();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('reference_transaction_id')
                ->nullable()
                ->constrained('saving_transactions')
                ->nullOnDelete();

            $table->string('transaction_type', 30);

            $table->decimal('amount', 15, 2);

            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);

            $table->text('description')->nullable();

            $table->string('status', 30)
                ->default('POSTED');

            $table->timestamp('transaction_date');

            $table->timestamps();

            $table->index([
                'saving_account_id',
                'transaction_date'
            ]);

            $table->index([
                'status',
                'transaction_date'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saving_transactions');
    }
};
