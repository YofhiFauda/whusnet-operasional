<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Sengaja tidak melakukan apa-apa — dipertahankan sebagai catatan kenapa
     * unique index (customer_id, invoice_type, billing_period) TIDAK dipasang,
     * supaya percobaan yang sama tidak diulang lagi nanti.
     *
     * 4 tabrakan warisan migrasi legacy memang sudah dibereskan lewat
     * `billing:cleanup-legacy-duplicate-invoices`, jadi datanya bersih. Tapi
     * kunci polos tetap salah: tagihan berstatus 'batal' terus menempati slot
     * periode, sehingga tagihan pengganti untuk periode & jenis yang sama
     * ditolak database. Itu melanggar aturan bisnis yang sudah punya test
     * (SatuTagihanLanggananPerPeriodeTest::test_tagihan_dibatalkan_tidak_memblokir_penggantinya
     * dan AuditTagihanDobelTest::test_tagihan_batal_dan_reaktivasi_tidak_dihitung).
     * Dicoba dipasang 2026-07-21, dua test itu langsung gagal.
     *
     * MySQL tidak punya partial index, jadi "kecualikan yang batal" tidak bisa
     * ditulis langsung di index. Pencegahan tagihan dobel tetap ditegakkan di
     * layer aplikasi (InvoiceObserver + CustomerController::storeManualInvoice),
     * dan dipantau lewat `billing:audit-duplicate-invoices`.
     */
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
