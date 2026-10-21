<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Jalur tulis representasi infrastruktur lama
    |--------------------------------------------------------------------------
    |
    | Rancangan hierarki §16.4: `olt_slots`, node `olt_card`/`pon_port`, dan
    | form laporan berbasis node tunggal adalah representasi lama. Untuk data
    | NYATA (audit Task 1: topologi memegang assignment pelanggan), jalur tulis
    | lama HANYA boleh ditutup setelah ketujuh bukti cutover tertulis —
    | termasuk selisih rekonsiliasi nol selama satu siklus tutup periode
    | penuh (`php artisan infrastructure:reconcile`).
    |
    | Penutupan normal: infrastructure:close-legacy-writes dengan tujuh bukti.
    | false di env hanya fallback darurat; alasan dan status bukan-cutover
    | dicatat saat saklar dibaca. Baris & histori lama tetap terbaca.
    |
    */

    'legacy_emergency_reason' => env('INFRA_LEGACY_EMERGENCY_REASON', 'Alasan darurat belum dicatat di konfigurasi'),

    'legacy_writes_enabled' => (bool) env('INFRA_LEGACY_WRITES', true),
];
