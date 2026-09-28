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
    case LAINNYA = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::PINDAH_LOKASI => 'Pindah Lokasi',
            self::PINDAH_KABEL => 'Pindah Kabel',
            self::TAMBAH_MODEM => 'Tambah Modem',
            self::LAINNYA => 'Lainnya',
        };
    }

    /** Tikor lama & baru wajib diisi untuk kategori ini. */
    public function requiresTikor(): bool
    {
        return in_array($this, [self::PINDAH_LOKASI, self::PINDAH_KABEL], true);
    }

    /** Wajib pilih SN modem dari custody tim (field selected_inventory_serial_id). */
    public function requiresModem(): bool
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
     * ditambah nilai baru untuk kategori C-REQ ini.
     */
    public function toManualInvoiceCategory(): ManualInvoiceCategory
    {
        return match ($this) {
            self::PINDAH_LOKASI => ManualInvoiceCategory::PINDAH_LOKASI,
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
