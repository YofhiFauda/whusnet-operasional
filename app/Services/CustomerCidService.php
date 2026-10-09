<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Distribution;
use App\Models\Pop;

/**
 * Satu pintu penghitungan CID pelanggan (ADHOC-107 R3).
 *
 * Dulu CID ditulis enam jalur dengan rumus berbeda — Edit Pelanggan memakai
 * `sprintf('%s00%s')` kalau distribusi kosong (Mini POP diabaikan), modal staf
 * & API memakai Pop::generateComplexCid() (`C10RQ…`) — sehingga CID pelanggan
 * yang sama bolak-balik tiap kali disimpan dari jalur berbeda. Pindah POP
 * lewat import/tinker bahkan tidak pernah membuat ulang CID sama sekali.
 *
 * Sekarang:
 * - Rumusnya satu: Pop::generateComplexCid() (K3: segmen kosong = '0').
 * - Kapan CID dibuat ulang ditentukan CustomerObserver::updating(): setiap
 *   POP/Mini POP/Distribusi berubah, atau CID masih kosong, pada pelanggan
 *   yang statusnya boleh punya CID. Controller/service tidak menghitung CID
 *   sendiri untuk perubahan jaringan.
 * - Aktivasi & import tetap menulis CID eksplisit (status berubah ke active
 *   di sana), tapi lewat resolve() di sini.
 *
 * CID boleh berubah; REQ ID (`customer_code`) yang permanen — keputusan user
 * 2026-09-26, docs/ID_NUMBERING_RULES.md §10.
 */
class CustomerCidService
{
    /**
     * Status yang punya CID aktif. Status lain menampilkan REQ ID (lihat
     * Pop::resolveDisplayId()); pelanggan terminated menyimpan CID lamanya
     * sebagai histori, tidak dihitung ulang.
     */
    public const CID_STATUSES = ['active', 'suspended'];

    public static function shouldHaveCid(Customer $customer): bool
    {
        return in_array((string) $customer->status, self::CID_STATUSES, true);
    }

    /**
     * Hitung CID dari nilai atribut SAAT INI (termasuk nilai dirty yang belum
     * disimpan). POP & Distribusi dibaca lewat query by id, bukan relasi:
     * resolve() dipanggil dari hook `updating`, tepat saat pop_id/
     * distribution_id baru di-set, sehingga relasi yang sudah ter-load bisa
     * basi; dan relasi lazy melempar LazyLoadingViolation kalau pelanggannya
     * bagian dari koleksi (preventLazyLoading aktif di dev/test).
     *
     * Null kalau Cabang tidak ditemukan atau `cid_prefix`-nya belum diisi —
     * pemanggil membiarkan CID lama, bukan menulis CID rusak.
     */
    public static function resolve(Customer $customer): ?string
    {
        $pop = $customer->pop_id ? Pop::query()->find($customer->pop_id) : null;
        if (! $pop || trim((string) $pop->cid_prefix) === '') {
            return null;
        }

        $distribution = $customer->distribution_id
            ? Distribution::query()->find($customer->distribution_id)
            : null;

        return $pop->generateComplexCid($customer, $distribution);
    }

    /**
     * Isi `cid` dengan hasil resolve() kalau statusnya boleh punya CID — belum
     * disimpan, pemanggil yang save(). Dipakai CustomerObserver::updating()
     * dan aksi eksplisit "atur jaringan" (modal staf & endpoint API).
     *
     * Aksi eksplisit tetap menyinkronkan CID walau Mini POP/Distribusi yang
     * dipilih SAMA dengan sebelumnya: menyimpan ulang modal adalah jalur
     * perbaikan manual CID yang sudah terlanjur campuran/basi (keputusan user
     * 2026-09-26: data lama diperbaiki manual). Tanpa ini, tidak ada kolom
     * yang berubah → observer diam → CID rusak tidak bisa dibetulkan dari UI.
     */
    public static function sync(Customer $customer): void
    {
        if (! self::shouldHaveCid($customer)) {
            return;
        }

        $cid = self::resolve($customer);
        if ($cid !== null) {
            $customer->cid = $cid;
        }
    }

    /**
     * Apakah username PPPoE masih sesuai CID pelanggan (format
     * `{CID}_{DESA}_{NAMA}`). Null = tidak ada yang dibandingkan (PPPoE/CID
     * kosong, atau status tanpa CID aktif).
     *
     * Sistem SENGAJA tidak mengubah PPPoE otomatis saat CID berubah (ADHOC-107
     * K2, keputusan user 2026-09-28): username harus sama persis dengan akun di
     * Mikrotik, dan belum ada integrasi hardware — mengubahnya sepihak bikin
     * pelanggan tidak bisa login PPPoE. Hasil false cuma dipakai untuk
     * peringatan di tampilan supaya NOC menyesuaikannya manual.
     */
    public static function pppoeMatchesCid(Customer $customer, ?string $pppoeUsername): ?bool
    {
        $pppoeUsername = trim((string) $pppoeUsername);
        $cid = trim((string) $customer->cid);

        if ($pppoeUsername === '' || $pppoeUsername === '-' || $cid === '' || ! self::shouldHaveCid($customer)) {
            return null;
        }

        return str_starts_with($pppoeUsername, $cid.'_');
    }

    public static function pppoeMismatchWarning(Customer $customer, ?string $pppoeUsername): ?string
    {
        return self::pppoeMatchesCid($customer, $pppoeUsername) === false
            ? "PPPoE belum disesuaikan dengan CID {$customer->cid} — ubah di Mikrotik lalu di Edit Pelanggan."
            : null;
    }
}
