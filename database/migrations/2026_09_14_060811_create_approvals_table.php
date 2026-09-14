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
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('module', 50)->index();

            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id');

            $table->string('action', 50);

            $table->text('reason');

            $table->json('request_payload')
                ->nullable();

            $table->string('status', 30)
                ->default('PENDING');

            $table->text('review_notes')
                ->nullable();

            $table->timestamp('reviewed_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'entity_type',
                'entity_id'
            ]);

            $table->index([
                'status',
                'module'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
