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

    /*
    |--------------------------------------------------------------------------
    | Jendela Mundur Pembebasan Tagihan (ADHOC-87)
    |--------------------------------------------------------------------------
    |
    | Berapa bulan ke BELAKANG dari bulan berjalan yang boleh dibebaskan lewat
    | Request Putus Langganan / Cuti Berlangganan (`BillingPeriodWaiverService`).
    | Default 1 = bulan berjalan + 1 bulan sebelumnya boleh, lebih lama dari
    | itu ditolak (piutang yang sudah "tutup buku" tidak boleh dihapus diam-diam
    | lewat jalur ini — kalau jendelanya kurang, admin membatalkan manual bulan
    | berikutnya, keputusan user 2026-09-21).
    |
    | Periode KE DEPAN (khusus Cuti, tagihan yang belum terbit) tidak dibatasi
    | angka ini — lihat BillingPeriodWaiverService::eligiblePeriods().
    |
    | docs/plan/billing/analisa-rancangan-request-deaktivasi-bebas-tagihan-periode.md §4.3 G3.
    */
    'waiver_backdate_window_months' => (int) env('BILLING_WAIVER_BACKDATE_WINDOW_MONTHS', 1),
];
