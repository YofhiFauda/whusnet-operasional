# AI Changelog

Catat semua perubahan yang dibuat oleh AI agent di file ini.

Format:

## YYYY-MM-DD HH:mm - Codex CLI

### Task

...

### File yang Diubah

- ...

### Ringkasan Perubahan

...

### Cara Test

...

### Catatan

...

## 2026-06-13 09:27 - Codex CLI

### Task
S6-T004 Audit Log Pembayaran — complete.

### File yang Diubah
- app/Models/Payment.php
- app/Http/Controllers/Admin/PaymentController.php
- resources/views/payments/show.blade.php
- resources/views/layouts/admin-nav.blade.php
- routes/web.php
- docs/TASKS.md

### Ringkasan Perubahan
- Menambahkan `PaymentAuditLog` model dan relasi two-way ke `Payment`.
- Mengaktifkan logging perubahan pembayaran (create, update, cancel) di `PaymentController`.
- Menambahkan link "Riwayat Audit" ke halaman detail pembayaran.
- Menampilkan tabel audit log dengan riwayat lengkap di halaman detail pembayaran (`resources/views/payments/show.blade.php`).
- Memperbarui `resources/views/layouts/admin-nav.blade.php` agar role Owner dan Admin Pusat bisa mengakses menu "Audit Log" (sebelumnya hanya Admin Pusat, sekarangOwner juga bisa).
- Menambahkan S6-T004 ke list task, set sebagai "Done", dan menambahkan catatan test.
- Tidak ada perubahan pada fitur atau scope yang sudah ada, hanya menambahkan logging dan UI untuk audit trail.

### Cara Test
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/PaymentAuditLogTest.php tests/Feature/PaymentInputTest.php tests/Feature/PaymentListTest.php tests/Feature/PaymentModelTest.php` lulus: 11 tests, 68 assertions.
- `npm run build` lulus.
- Full test suite dengan `VIEW_COMPILED_PATH` temp: 106 passed, 2 failed pada `CustomerEditTest` lama terkait cleanup file dokumen pelanggan, bukan modul pembayaran.
- Cek manual di aplikasi:
  - Login sebagai Admin Pusat atau Owner.
  - Buat pembayaran baru → cek audit log.
  - Edit pembayaran (rubah nilai atau status) → cek audit log.
  - Batalkan pembayaran → cek audit log.
  - Pastikan admin cabang tidak bisa lihat audit log.

### Catatan
- Audit log hanya mencatat perubahan pada tabel `payments`, tidak pada tabel lain.
- Hanya user dengan `view_audit_logs` permission yang dapat melihat riwayat audit.
- UI audit log sederhana (tabel, tidak ada filter) sesuai batasan sprint.


## 2026-06-13 09:07 - Codex CLI

### Task
Fixer review notes S6-T003 menuju S6-T004.

### File yang Diubah
- .ai/ACTIVE_TASK.md
- .ai/HANDOFF.md
- .ai/CHANGELOG_AI.md
- .ai/SESSION_STATE.md

### Ringkasan Perubahan
Menangani perbaikan wajib di `.ai/REVIEW_NOTES.md`: context `.ai/ACTIVE_TASK.md` dan `.ai/HANDOFF.md` disinkronkan dengan `docs/TASKS.md` agar menunjuk ke S6-T004 — Audit Log Pembayaran. Tidak ada perubahan kode aplikasi dan tidak ada perluasan scope.

### Cara Test
- Tidak menjalankan test aplikasi karena perubahan hanya pada context `.ai`.
- Verifikasi dilakukan dengan membaca ulang `.ai/ACTIVE_TASK.md`, `.ai/HANDOFF.md`, `.ai/SESSION_STATE.md`, dan `.ai/CHANGELOG_AI.md`.

### Catatan
Fix dibatasi hanya pada issue wajib di `.ai/REVIEW_NOTES.md`. Perbaikan opsional review notes tidak dikerjakan.

## 2026-06-13 08:46 - Codex CLI

### Task
Fixer review notes S6-T002.

### File yang Diubah
- .ai/CHANGELOG_AI.md
- .ai/SESSION_STATE.md

### Ringkasan Perubahan
Memverifikasi issue wajib di `.ai/REVIEW_NOTES.md`: context `.ai/ACTIVE_TASK.md` dan `.ai/HANDOFF.md` sudah sinkron dengan `docs/TASKS.md` dan menunjuk ke S6-T003 — Daftar dan Detail Pembayaran. Tidak ada perubahan kode aplikasi dan tidak ada perluasan scope.

### Cara Test
- Tidak menjalankan test aplikasi karena perubahan hanya pada context `.ai`.
- Verifikasi dilakukan dengan membaca `.ai/REVIEW_NOTES.md`, `.ai/ACTIVE_TASK.md`, `.ai/HANDOFF.md`, dan `docs/TASKS.md`.

### Catatan
Fix dibatasi pada issue review notes. S6-T003 tetap menjadi task aktif.

## 2026-06-13 08:20 - Codex CLI

### Task
Fix review notes setelah review S6-T001.

### File yang Diubah
- .ai/CHANGELOG_AI.md
- .ai/SESSION_STATE.md

### Ringkasan Perubahan
Review notes menyatakan tidak ada perbaikan wajib untuk implementasi S6-T001. Tidak ada perubahan kode dilakukan agar tidak memperluas scope. Context session diperbarui untuk mencatat bahwa review selesai dan source of truth task aktif tetap `docs/TASKS.md` dengan `S6-T002 — Input Pembayaran`.

### Cara Test
- Tidak menjalankan test karena tidak ada perubahan kode, migration, view, route, atau test.

### Catatan
Item di bagian perbaikan opsional review notes tidak dikerjakan karena bersifat rekomendasi untuk task berikutnya dan bukan issue wajib.

## 2026-06-13 08:42 - Codex CLI

### Task
Fix review notes setelah review S6-T002.

### File yang Diubah
- .ai/ACTIVE_TASK.md
- .ai/HANDOFF.md
- .ai/CHANGELOG_AI.md
- .ai/SESSION_STATE.md

### Ringkasan Perubahan
Review notes menemukan context `.ai/ACTIVE_TASK.md` masih menunjuk ke S6-T002 meskipun `docs/TASKS.md` sudah mencatat S6-T002 Done dan S6-T003 In Progress. Context diperbarui agar task aktif mengikuti source of truth `docs/TASKS.md`: S6-T003 — Daftar dan Detail Pembayaran.

### Cara Test
- Tidak menjalankan test aplikasi karena tidak ada perubahan kode, route, view, migration, model, atau test.
- Verifikasi dilakukan dengan membaca ulang file context yang diperbarui.

### Catatan
Fix dibatasi hanya pada issue wajib di `.ai/REVIEW_NOTES.md`. Tidak ada fitur baru dan tidak ada perluasan scope.

## 2026-06-12 16:45 - Codex CLI

### Task
Fix review note S5-T004 terkait kejelasan tampilan PPN di detail tagihan.

### File yang Diubah
- resources/views/invoices/show.blade.php
- tests/Feature/InvoiceListTest.php
- .ai/CHANGELOG_AI.md
- .ai/SESSION_STATE.md

### Ringkasan Perubahan
Label PPN pada detail tagihan diperjelas menjadi `PPN (%)` karena nilai `ppn` invoice disimpan dan diuji sebagai persentase, bukan nominal rupiah. Test detail invoice ditambah assertion label PPN agar tidak kembali ambigu.

### Cara Test
- `$compiled = Join-Path $env:TEMP ('whusnet-views-' + [guid]::NewGuid().ToString()); New-Item -ItemType Directory -Force $compiled | Out-Null; $env:VIEW_COMPILED_PATH=$compiled; php artisan test tests/Feature/InvoiceListTest.php`
- Hasil: 3 passed, 18 assertions.

### Catatan
Tidak ada perubahan kalkulasi invoice, struktur database, atau fitur pembayaran. Run awal tanpa `VIEW_COMPILED_PATH` temp gagal karena Laravel tidak bisa rename compiled Blade di folder `storage/framework/testing/views` pada Windows (`Access is denied`), bukan karena assertion fix.
