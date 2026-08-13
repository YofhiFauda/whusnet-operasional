<?php

namespace App\Enums;

/**
 * Status Setoran Kolektor.
 *
 * `SELISIH` SENGAJA bukan status terminal — selisih adalah uang perusahaan
 * yang sedang dipegang (atau kurang dipegang) kolektor, jadi wajib punya jalan
 * pulang: dilunasi di setoran berikutnya (`SELISIH_LUNAS`) atau diakui sebagai
 * kerugian lewat hapus buku Owner (`DIHAPUS_BUKU`). Kalau dibiarkan berhenti
 * di `SELISIH`, laporan "selisih per kolektor" jadi akumulasi sampah yang tak
 * pernah nol dan tak ada yang bisa menutupnya.
 *
 * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §11.4.
 */
enum DepositStatus: string
{
    case MENUNGGU_VERIFIKASI = 'menunggu_verifikasi';
    case TERVERIFIKASI = 'terverifikasi';
    case SELISIH = 'selisih';
    case SELISIH_LUNAS = 'selisih_lunas';
    case DIHAPUS_BUKU = 'dihapus_buku';

    /**
     * Uang fisik MELEBIHI catatan. Sengaja status sendiri, bukan menumpang
     * `SELISIH`, dan sengaja TERMINAL.
     *
     * Alasannya beda arah uang, jadi beda konsekuensi: kurang setor adalah
     * kewajiban kolektor yang harus ditagih pulang; lebih setor adalah uang
     * yang dikembalikan fisik saat itu juga (konsisten dengan aturan kembalian
     * pelanggan, §B-8 no. 6). Waktu keduanya berbagi status `SELISIH`,
     * lebih setor nyangkut: worklist kolektor menampilkan badge merah permanen
     * "Kurang setor Rp0", setoran itu tak bisa dipilih untuk pelunasan, dan
     * satu-satunya jalan keluar adalah hapus buku bernilai nol — yang secara
     * akuntansi omong kosong.
     *
     * Status yang artinya berbeda tidak boleh berbagi nama.
     */
    case LEBIH_SETOR = 'lebih_setor';

    public function label(): string
    {
        return match ($this) {
            self::MENUNGGU_VERIFIKASI => 'Menunggu Verifikasi Admin',
            self::TERVERIFIKASI => 'Terverifikasi',
            self::SELISIH => 'Kurang Setor',
            self::SELISIH_LUNAS => 'Selisih Lunas',
            self::LEBIH_SETOR => 'Lebih Setor (dikembalikan)',
            self::DIHAPUS_BUKU => 'Dihapus Buku',
        };
    }

    /**
     * Setoran yang uangnya sudah dihitung & disepakati dua pihak. Sesudah
     * titik ini pembayaran di dalamnya tak boleh diutak-atik lagi — koreksi
     * lewat pembayaran pembalik, bukan mengubah setoran lama.
     */
    public function isVerified(): bool
    {
        return $this !== self::MENUNGGU_VERIFIKASI;
    }
}
