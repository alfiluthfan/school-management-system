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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('class_id')
                ->nullable()
                ->constrained('school_classes')
                ->nullOnDelete();

            $table->string('title', 200);

            $table->text('content');

            $table->string('target_scope', 30)
                ->default('SCHOOL');

            $table->timestamp('publish_at')
                ->nullable();

            $table->timestamp('expired_at')
                ->nullable();

            $table->string('status', 30)
                ->default('DRAFT');

            $table->timestamps();

            $table->index([
                'status',
                'publish_at'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
