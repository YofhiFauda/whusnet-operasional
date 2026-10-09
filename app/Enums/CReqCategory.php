<?php

namespace App\Enums;

/**
 * Kategori pekerjaan di dalam Laporan C-REQ (docs/plan/task-teknisi/
 * rancangan-biaya-creq-verifikasi-cs.md §2). Menentukan field kondisional
 * yang wajib diisi teknisi di form yang sama dengan Laporan MTN
 * (`TaskMaintenanceController`).
 */
enum CReqCategory: string
{
    case PINDAH_LOKASI = 'pindah_lokasi';
    case PINDAH_KABEL = 'pindah_kabel';
    case TAMBAH_MODEM = 'tambah_modem';
    case MIGRASI = 'migrasi';
    case LAINNYA = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::PINDAH_LOKASI => 'Pindah Lokasi',
            self::PINDAH_KABEL => 'Pindah Kabel',
            self::TAMBAH_MODEM => 'Tambah Modem',
            self::MIGRASI => 'Migrasi',
            self::LAINNYA => 'Lainnya',
        };
    }

    /**
     * Tikor lama & baru wajib diisi untuk kategori ini. `MIGRASI` juga
     * pindah lokasi fisik (beda dari `PINDAH_LOKASI` cuma karena lokasi
     * barunya lintas POP) — tikor tetap wajib.
     */
    public function requiresTikor(): bool
    {
        return in_array($this, [self::PINDAH_LOKASI, self::PINDAH_KABEL, self::MIGRASI], true);
    }

    /**
     * Wajib pilih POP tujuan (field creq_target_pop_id) — SATU-SATUNYA
     * kategori yang pindah pop_id pelanggan. `PINDAH_LOKASI` cuma pindah
     * alamat, POP tetap sama; `MIGRASI` pindah alamat SEKALIGUS POP.
     */
    public function requiresTargetPop(): bool
    {
        return $this === self::MIGRASI;
    }

    /** Wajib pilih SN modem dari custody tim (field selected_inventory_serial_id). */
    public function requiresModem(): bool
    {
        return $this === self::TAMBAH_MODEM;
    }

    /**
     * Pelanggan beneran NAMBAH modem (>1 modem aktif bersamaan), BUKAN
     * ganti — koreksi 2026-10-08. SATU-SATUNYA kategori yang bikin
     * `InventoryService::installSerial()` dipanggil dengan
     * `$returnExistingSerial=false`: modem lain yang masih `INSTALLED`
     * milik pelanggan ini TIDAK ikut diretur. Semua kategori/task_type
     * lain (termasuk MTN non-C-REQ) defaultnya GANTI — modem lama otomatis
     * diretur begitu SN baru diinstall.
     */
    public function addsModemWithoutReturningExisting(): bool
    {
        return $this === self::TAMBAH_MODEM;
    }

    /** Nama kategori bebas wajib diisi (category_custom_name). */
    public function requiresCustomName(): bool
    {
        return $this === self::LAINNYA;
    }

    /**
     * Prefill jenis Tagihan Manual saat CS approve verifikasi biaya (§5
     * rancangan) — Pindah Kabel & Tambah Modem dipetakan ke "Perbaikan"
     * karena `ManualInvoiceCategory` tetap 3 nilai final (ADHOC-70), tidak
     * ditambah nilai baru untuk kategori C-REQ ini. `MIGRASI` dipetakan ke
     * "Pindah Lokasi" juga — sama jenis tagihan dengan `PINDAH_LOKASI`,
     * cuma beda penanganan lapangan (lihat `requiresTargetPop()`).
     */
    public function toManualInvoiceCategory(): ManualInvoiceCategory
    {
        return match ($this) {
            self::PINDAH_LOKASI, self::MIGRASI => ManualInvoiceCategory::PINDAH_LOKASI,
            self::PINDAH_KABEL, self::TAMBAH_MODEM => ManualInvoiceCategory::PERBAIKAN,
            self::LAINNYA => ManualInvoiceCategory::LAINNYA,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn ($c) => [
            'value' => $c->value,
            'label' => $c->label(),
        ])->toArray();
    }
}
