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
        Schema::table('invoices', function (Blueprint $table) {
            // Sub-klasifikasi InvoiceType::MANUAL (ADHOC-70) — nilai
            // App\Enums\ManualInvoiceCategory. NULL untuk semua tagihan
            // non-manual (Aktivasi/Bulanan/Reaktivasi).
            $table->string('manual_category', 30)->nullable()->after('invoice_type');
            // Nama sub bebas untuk kategori Lainnya (mis. "Over Kabel").
            // NULL untuk kategori lain & tagihan non-manual.
            $table->string('manual_subtype_name', 150)->nullable()->after('manual_category');
            // Deskripsi tagihan diketik manual — cuma dipakai InvoiceType::MANUAL.
            $table->text('description')->nullable()->after('manual_subtype_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['manual_category', 'manual_subtype_name', 'description']);
        });
    }
};
