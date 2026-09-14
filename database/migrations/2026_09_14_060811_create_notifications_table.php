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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('recipient_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('student_id')
                ->nullable()
                ->constrained('students')
                ->nullOnDelete();

            $table->string('type', 50)->index();

            $table->string('channel', 30);

            $table->string('recipient', 150);

            $table->string('subject', 200)
                ->nullable();

            $table->text('message');

            $table->string('provider_message_id', 255)
                ->nullable();

            $table->string('status', 30)
                ->default('QUEUED');

            $table->timestamp('scheduled_at')
                ->nullable();

            $table->timestamp('sent_at')
                ->nullable();

            $table->timestamp('failed_at')
                ->nullable();

            $table->unsignedInteger('retry_count')
                ->default(0);

            $table->text('error_message')
                ->nullable();

            $table->timestamps();

            $table->index([
                'status',
                'scheduled_at'
            ]);

            $table->index([
                'recipient_user_id',
                'created_at'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
