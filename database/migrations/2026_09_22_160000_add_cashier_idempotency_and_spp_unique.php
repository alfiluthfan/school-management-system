<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Fail loudly if historical duplicates exist: they must be reconciled, never auto-deleted.
        if (DB::table('spp_bills')->select('student_id', 'billing_year', 'billing_month')
            ->groupBy('student_id', 'billing_year', 'billing_month')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Tagihan SPP duplikat ditemukan. Rekonsiliasi dahulu sebelum migrasi.');
        }
        if (DB::table('saving_accounts')->select('student_id')->groupBy('student_id')
            ->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Rekening tabungan siswa duplikat ditemukan. Rekonsiliasi dahulu sebelum migrasi.');
        }
        Schema::table('spp_bills', function (Blueprint $table): void {
            $table->unique(['student_id', 'billing_year', 'billing_month'], 'cashier_spp_student_period_unique');
        });
        Schema::table('saving_accounts', function (Blueprint $table): void {
            $table->unique('student_id', 'cashier_saving_student_unique');
        });
        Schema::create('cashier_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('operation', 24);
            $table->uuid('request_key');
            $table->string('student_uuid', 36);
            $table->uuid('result_uuid');
            $table->char('payload_hash', 64);
            $table->timestamps();
            $table->unique(['actor_id', 'operation', 'request_key'], 'cashier_request_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_requests');
        Schema::table('saving_accounts', fn (Blueprint $table) => $table->dropUnique('cashier_saving_student_unique'));
        Schema::table('spp_bills', fn (Blueprint $table) => $table->dropUnique('cashier_spp_student_period_unique'));
    }
};
