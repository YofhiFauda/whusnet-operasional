<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Jendela Tagih Kolektor (hari)
    |--------------------------------------------------------------------------
    |
    | Berapa hari SEBELUM jatuh tempo sebuah tagihan sudah boleh muncul di
    | Worklist Kolektor. Bukan `due_date <= hari ini` mentah: kolektor keliling
    | sebulan sekali, jadi kalau jatuh temponya tanggal 20 dan kolektor lewat
    | tanggal 18, tagihan itu harus sudah kelihatan — kalau tidak, pelanggan
    | yang siap bayar terlewat dan kolektor harus datang dua kali.
    |
    | Disimpan di config, BUKAN literal di query: tiap POP bisa beda ritme
    | keliling dan penyetelannya tak boleh butuh deploy.
    |
    | docs/plan/kolektor/analisa-alur-kolektor-2.0.md §10.
    |
    */
    'collector_due_window_days' => (int) env('COLLECTOR_DUE_WINDOW_DAYS', 7),
];
