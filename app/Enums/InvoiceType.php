<?php

namespace App\Enums;

enum InvoiceType: string
{
    case AWAL = 'awal';
    case BULANAN = 'bulanan';
    case REAKTIVASI = 'reaktivasi';

    /**
     * Tagihan di luar langganan — jasa perbaikan, biaya instalasi tambahan,
     * denda, dsb (ADHOC-60). SENGAJA tidak masuk `Invoice::SUBSCRIPTION_TYPES`:
     * itulah yang membebaskannya dari guard "satu tagihan langganan per
     * periode" di `InvoiceObserver`, sehingga beberapa pekerjaan berbayar boleh
     * ditagih di bulan yang sama. Konsekuensinya, jenis ini juga tidak pernah
     * dibuat `billing:generate-monthly-invoices` — selalu manual.
     *
     * Aturan pasangannya ditegakkan `ManualInvoiceService`: tagihan yang punya
     * baris berkategori `jasa_layanan_internet` TIDAK boleh bertipe ini, dan
     * sebaliknya tagihan tanpa baris langganan WAJIB bertipe ini.
     */
    case INSIDENTAL = 'insidental';

    /**
     * Tagihan Manual (ADHOC-70) — Perbaikan / Lainnya / Pindah Lokasi, diisi
     * dari `/invoices/create`. SENGAJA nilai baru, bukan reuse `INSIDENTAL`
     * (instruksi user 2026-09-19): `INSIDENTAL` terikat aturan lama
     * `ManualInvoiceService`/`invoice_items` yang tidak dipakai fitur ini.
     * Di luar `Invoice::SUBSCRIPTION_TYPES` — lihat alasannya di sana.
     */
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::AWAL => 'Aktivasi',
            self::BULANAN => 'Tagihan Bulanan Rutin',
            self::REAKTIVASI => 'Tagihan Reaktivasi',
            self::INSIDENTAL => 'Tagihan Lain-lain',
            self::MANUAL => 'Tagihan Manual',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::AWAL => 'pembayaran-awal',
            self::BULANAN => 'bulan',
            self::REAKTIVASI => 'reaktivasi',
            self::INSIDENTAL => 'insidental',
            self::MANUAL => 'manual',
        };
    }
}
