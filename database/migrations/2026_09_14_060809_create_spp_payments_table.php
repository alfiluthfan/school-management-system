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
        Schema::create('spp_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('payment_number', 50)
                ->unique();

            $table->string('receipt_number', 50)
                ->unique();

            $table->foreignId('spp_bill_id')
                ->constrained('spp_bills')
                ->restrictOnDelete();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->decimal('amount', 15, 2);

            $table->string('payment_method', 30);

            $table->string('reference_number', 100)
                ->nullable();

            $table->timestamp('payment_date');

            $table->string('status', 30)
                ->default('POSTED');

            $table->text('notes')->nullable();

            $table->foreignId('voided_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('voided_at')->nullable();

            $table->text('void_reason')->nullable();

            $table->timestamps();

            $table->index([
                'spp_bill_id',
                'payment_date'
            ]);

            $table->index([
                'status',
                'payment_date'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spp_payments');
    }
};
