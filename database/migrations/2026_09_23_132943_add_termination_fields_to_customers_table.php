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
        Schema::table('customers', function (Blueprint $table) {
            // restrictOnDelete() (bukan nullOnDelete/cascadeOnDelete) — alasan
            // yang masih dipakai minimal 1 pelanggan tidak boleh terhapus
            // (ADHOC-69 §3.4). Pengecekan ramah ada di controller; ini jaring
            // pengaman terakhir untuk jalur lain (tinker, SQL langsung).
            $table->foreignId('termination_reason_id')->nullable()
                ->after('terminated_at')
                ->constrained('customer_termination_reasons')
                ->restrictOnDelete();
            $table->text('termination_note')->nullable()->after('termination_reason_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('termination_reason_id');
            $table->dropColumn('termination_note');
        });
    }
};
