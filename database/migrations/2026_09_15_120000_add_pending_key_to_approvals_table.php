<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approvals', function (Blueprint $table): void {
            $table->string('pending_key', 191)
                ->nullable()
                ->unique();
        });
    }

    public function down(): void
    {
        Schema::table('approvals', function (Blueprint $table): void {
            $table->dropUnique(['pending_key']);
            $table->dropColumn('pending_key');
        });
    }
};
