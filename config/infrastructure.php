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
    | penuh. CATATAN Task 14: perintah `infrastructure:reconcile` dan
    | `infrastructure:close-legacy-writes` beserta tabel posisi lama sudah
    | dipensiunkan (Slot/PON kini node), jadi penutupan lewat perintah itu tak
    | tersedia lagi; saklar di bawah tetap dibaca guard penulis lama.
    | false di env hanya fallback darurat; alasan dan status bukan-cutover
    | dicatat saat saklar dibaca. Baris & histori lama tetap terbaca.
    |
    */

    'legacy_emergency_reason' => env('INFRA_LEGACY_EMERGENCY_REASON', 'Alasan darurat belum dicatat di konfigurasi'),

    'legacy_writes_enabled' => (bool) env('INFRA_LEGACY_WRITES', true),

    /*
    |--------------------------------------------------------------------------
    | Konfirmasi reset data posisi lama (ADHOC-213 Task 5)
    |--------------------------------------------------------------------------
    |
    | Dibaca dua migrasi destruktif ADHOC-213:
    | - `2026_10_24_000002_retarget_location_foreign_keys_to_network_nodes`:
    |   memindahkan lokasi serial/ledger/saldo di Slot/Port lama ke perangkat
    |   induknya, me-NULL kolom slot/port, MENGHAPUS isi
    |   infrastructure_slots/ports/connections/legacy_mappings, lalu men-drop
    |   tabel lama.
    | - `2026_10_25_000001_drop_olt_slots_table`: men-drop `olt_slots` beserta
    |   isinya (tidak dipetakan ke node Slot).
    | Di luar env `testing` keduanya menolak jalan bila ada baris yang akan
    | dibuang, kecuali saklar ini true — operator wajib menyatakan sendiri
    | bahwa database target dummy (spec §10). Dibaca lewat config(), bukan
    | env(), supaya tetap benar saat config di-cache (`config:cache` → ubah
    | env lalu cache ulang).
    |
    */

    'confirm_dummy_reset' => (bool) env('INFRA_CONFIRM_DUMMY_RESET', false),
];
