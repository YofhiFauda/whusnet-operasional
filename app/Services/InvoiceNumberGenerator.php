<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Enums\ManualInvoiceCategory;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Penomoran tagihan `{PREFIX}-{YYYYMMDD}-{NNNNNN}` (BUG 13, rancangan
 * `docs/plan/billing/rancangan-prefix-nomor-invoice.md`).
 *
 * Diekstrak dari dua salinan identik (bekas CustomerController::storeManualInvoice,
 * dihapus ADHOC-70, dan GenerateMonthlyInvoicesCommand) waktu jalur tagihan manual dipindah ke
 * modul Tagihan (ADHOC-60). Dua salinan itu menulis ke deret yang SAMA, jadi
 * begitu keduanya menyimpang formatnya nomor tagihan langsung bentrok —
 * masalah yang persis sama sudah pernah didokumentasikan untuk `TFOP-` di
 * CLAUDE.md. Satu penghasil nomor menutup peluang itu.
 */
class InvoiceNumberGenerator
{
    /**
     * Nomor berikutnya untuk satu prefix (`resolvePrefix()`) + satu tanggal
     * terbit (`YYYYMMDD`, timezone `Asia/Jakarta` — rancangan §6 poin 3).
     * Counter-nya dikunci lewat NumberSequenceService, bukan lewat
     * `lockForUpdate()` pada baris invoice yang belum tentu ada.
     */
    public function nextFor(InvoiceType $type, ?ManualInvoiceCategory $category, string|DateTimeInterface $issueDate): string
    {
        $prefix = $this->resolvePrefix($type, $category);
        $dateCode = Carbon::parse($issueDate, 'Asia/Jakarta')->format('Ymd');

        return app(NumberSequenceService::class)->invoiceNumber($prefix, $dateCode);
    }

    /**
     * Pemetaan jenis tagihan → prefix (rancangan §2 + keputusan user
     * 2026-10-01 soal bekas `INSIDENTAL`). Sumber prefix KHUSUS penomoran —
     * bukan `InvoiceType::prefix()` yang dipakai untuk kebutuhan lain
     * (nilainya `pembayaran-awal`/`bulan`/`manual`, jangan dirombak).
     *
     * `InvoiceType::MANUAL` tanpa `ManualInvoiceCategory` (jalur lama ADHOC-60
     * — `ManualInvoiceService::resolveTypeFromLines()`, satu-satunya
     * pemanggil hidup: `InstallationFeeInvoiceService` untuk Biaya Instalasi
     * pelanggan Bisnis divalidasi BD) dulu bernama `InvoiceType::INSIDENTAL`
     * sebelum digabung ke `MANUAL`. Tetap dipetakan ke `ACT` seperti
     * keputusan semula — biaya instalasi = bagian biaya aktivasi.
     */
    private function resolvePrefix(InvoiceType $type, ?ManualInvoiceCategory $category): string
    {
        return match ($type) {
            InvoiceType::AWAL => 'ACT',
            InvoiceType::BULANAN => 'TAG',
            InvoiceType::MANUAL => match ($category) {
                ManualInvoiceCategory::PERBAIKAN => 'MTN',
                ManualInvoiceCategory::LAINNYA => 'OTH',
                ManualInvoiceCategory::PINDAH_LOKASI => 'REL',
                null => 'ACT',
            },
        };
    }
}
