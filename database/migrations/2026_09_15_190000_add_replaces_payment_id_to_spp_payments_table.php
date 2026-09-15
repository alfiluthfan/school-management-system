<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spp_payments', function (Blueprint $table): void {
            $table->foreignId('replaces_payment_id')
                ->nullable()
                ->unique()
                ->after('spp_bill_id')
                ->constrained('spp_payments')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('spp_payments', function (Blueprint $table): void {
            $table->dropUnique(
                'spp_payments_replaces_payment_id_unique'
            );
        });

        Schema::table('spp_payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId(
                'replaces_payment_id'
            );
        });
    }
};
