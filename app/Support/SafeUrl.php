<?php

namespace App\Support;

class SafeUrl
{
    /**
     * Validasi nilai `return_to` yang datang dari query string/form input.
     *
     * Halaman Laporan Survey & Pemasangan diakses dari beberapa entry point
     * berbeda (Detail Task teknisi, Dashboard Task Saya, Antrean Survey,
     * Verifikasi Queue, Detail Pelanggan) — sebelumnya tombol "Kembali" dan
     * redirect sukses submit HARDCODED ke satu tujuan tetap, jadi teknisi
     * yang masuk dari Detail Task malah dilempar ke halaman antrean/verifikasi
     * yang gak relevan buat dia. `return_to` dikirim eksplisit oleh pemanggil
     * (bukan `url()->previous()` — itu ke-overwrite jadi URL halaman Laporan
     * itu sendiri begitu form di-load, jadi gak bisa dipakai balik ke 2 hop
     * sebelumnya).
     *
     * Cuma nerima URL yang menuju host aplikasi sendiri — cegah open redirect
     * kalau ada yang nyelipin `return_to` ke domain luar.
     */
    public static function resolveReturnTo(?string $value, string $fallbackRouteName): string
    {
        if ($value && str_starts_with($value, url('/'))) {
            return $value;
        }

        return route($fallbackRouteName);
    }
}
