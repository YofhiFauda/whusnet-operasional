# WHUSNET UI Design System Redesign — Implementation Plan

> **For agentic workers:** Gunakan `superpowers:executing-plans` untuk mengerjakan task secara berurutan. Checklist di bawah adalah unit review; satu task harus selesai dan terverifikasi sebelum task berikutnya.

**Goal:** Menyamakan halaman prioritas dengan `design-system/whusnet-operasional/Design.md` tanpa mengubah perilaku billing, permission, POP scope, atau alur kerja.

**Architecture:** Edit Blade/Tailwind pada halaman yang diaudit, satu keluarga halaman per task. Type A memakai header dan filter tanpa card, summary strip datar, lalu satu panel tabel. Type B memakai header tanpa card dan satu panel detail dengan divider internal. Type C boleh memakai KPI card sesuai Design.md. Wizard form memakai satu permukaan kerja utama sambil mempertahankan state dan validasi.

**Tech Stack:** Laravel 13, Blade, Tailwind CSS v4, Alpine.js, PHPUnit 12.

**Spec:** `docs/UI_DESIGN_AUDIT_2026-10-16.md`; sumber normatif `design-system/whusnet-operasional/Design.md` v2.5.0.

## Scope dan gerbang eksekusi

- Rencana ini **belum mengaktifkan redesign**. `docs/TASKS.md` masih menandai Sprint 8.10 / `S8.10-T003` sebagai In Progress. Sebelum Task 1, catat pekerjaan UI sebagai task yang diizinkan dalam tracking proyek tanpa memindahkan task aktif secara keliru.
- UI, CSS, dan tes terkait halaman dalam daftar ini boleh diubah saat task bersangkutan dijalankan. Controller, model, migration, route, seeder, permission, dan rumus bisnis tidak termasuk scope.
- Perubahan lokal yang sudah ada pada `tasks/own.blade.php`, `tasks/partials/own-card.blade.php`, dan `technician-payments/index.blade.php` harus dipertahankan; tidak menjadi target rencana ini.
- Jangan mengganti kelas dengan pencarian global. `shadow-2xl` pada overlay modal, `p-8` pada print, serta KPI card dashboard Type C perlu dinilai menurut konteksnya.
- Pertahankan semua nama field, form action/method, query string filter, `x-data`, `x-model`, tab state, conditional `@can`, dan tautan route. Perubahan visual tidak boleh membuka data di luar POP scope.
- Gunakan `php artisan test --compact <path>` pada tes terdekat setelah tiap task. Bila tes diubah, jalankan file itu sendiri. Bila PHP diubah, jalankan `vendor/bin/pint --dirty --format agent`.
- Inspeksi browser minimum pada 375px, 768px, dan desktop; light/dark, keyboard focus, empty/error state. Ambil screenshot sebelum/sesudah dan simpan sebagai bukti review, bukan sebagai asset produk.

## Review Focus

1. Filter GET dan pagination tetap mempertahankan query parameter serta pilihan POP.
2. Tombol bayar, setor, verifikasi, dan status kritis tetap terlihat serta dibatasi permission yang sama.
3. Wizard tetap membuka step yang benar setelah validasi gagal dan file upload tetap jelas.
4. Dark mode, focus keyboard, dan mobile horizontal overflow tidak memburuk.
5. Kwitansi/detail invoice saat print tidak berubah oleh layout layar.

---

### Task 1 — Pilot Type A: Daftar Pembayaran

**Files:** Modify `resources/views/payments/index.blade.php`; Test `tests/Feature/PaymentListTest.php`.

**Result:** Header tetap polos, empat metrik menjadi satu summary strip datar, filter tetap tanpa pembungkus card, tabel/pagination satu panel. Nilai, status, tautan, dan izin sama.

- [ ] Catat screenshot dan DOM desktop/mobile; identifikasi batas panel, filter, tabel, serta kondisi kosong.
- [ ] Tambah/ubah tes `PaymentListTest` untuk membuktikan filter/status/tautan aksi yang sudah ada tetap muncul untuk pengguna berizin dan tidak muncul bagi pengguna tanpa izin.
- [ ] Jalankan `php artisan test --compact tests/Feature/PaymentListTest.php`; pastikan tes baru gagal jika perilaku yang dilindungi belum terpenuhi, atau catat baseline bila tes hanya regresi.
- [ ] Edit hanya markup/class halaman ini: summary strip memakai divider, radius panel 8px, tanpa shadow dekoratif pada metrik. Jangan ubah query atau action.
- [ ] Jalankan tes yang sama; bandingkan screenshot 375/768/desktop dan dark mode. Review sebelum melanjutkan.

### Task 2 — Pilot Type B: Detail Invoice

**Files:** Modify `resources/views/invoices/show.blade.php`; Test `tests/Feature/PaymentReceiptPrintTest.php` dan tes invoice detail terdekat yang ditemukan saat eksekusi.

**Result:** Header detail tanpa card, status pada badge/teks, satu panel informasi detail. `.print-only` dan kontrol cetak tetap sama.

- [x] Catat screenshot layar dan preview cetak; petakan tombol Bayar/WA/Cetak serta status invoice.
- [x] Tambah tes regresi pada file tes detail/print yang relevan untuk conditional aksi dan tampilan cetak.
- [x] Jalankan tes terpilih untuk baseline; ubah markup layar saja dan jaga pemisahan `print-only`/`no-print`.
- [x] Jalankan `php artisan test --compact tests/Feature/PaymentReceiptPrintTest.php` dan tes detail yang diperbarui; periksa preview cetak, mobile, dark mode.

### Task 3 — Master list: POP, Barang, Node

**Files:** Modify `resources/views/master/pop/index.blade.php`, `resources/views/master/items/index.blade.php`, `resources/views/network/nodes/index.blade.php`; Test `tests/Feature/PopForUserScopeTreeTest.php`, `tests/Feature/WarehouseStockSearchTest.php`, `tests/Feature/OdpNetworkManagementTest.php`.

**Result:** Ketiga halaman Type A memakai header/filter polos, summary strip bila ada metrik, dan satu panel data; tidak ada gradien hero Node.

- [x] Rekam tampilan dan state filter masing-masing halaman; periksa pengaruh peran dan POP.
- [x] Tambah assertion relevan pada tes terdekat untuk form filter, hasil berbasis scope, dan action yang boleh terlihat.
- [x] Kerjakan POP terlebih dulu, lalu Barang, lalu Node; review diff dan jalankan tes terkait setelah tiap halaman.
- [x] Jalankan tiga tes di atas dengan `php artisan test --compact <file>`; periksa 375/768/desktop dan dark mode.

### Task 4 — Detail Worksheet Kolektor/Teknisi

**Files:** Modify `resources/views/collector-worksheet/show.blade.php`; Test `tests/Feature/CollectorWorksheetTest.php` serta `tests/Feature/TechnicianPaymentTest.php` jika tersedia.

**Result:** Ringkasan dan tab berada dalam satu panel detail; aksi Setor Seluruh Saldo tetap terlihat pada posisi yang baru disepakati di ADHOC-191, termasuk saldo nol dan tanpa izin.

- [x] Rekam state kolektor, teknisi, saldo positif/nol, serta pengguna tanpa permission setor.
- [x] Tambah/ubah tes view untuk keberadaan/ketiadaan aksi Setor sesuai izin dan saldo, serta tab yang dipertahankan.
- [x] Satukan permukaan visual dengan divider; jangan ubah form action, angka saldo, maupun guard POP.
- [x] Jalankan `php artisan test --compact tests/Feature/CollectorWorksheetTest.php` dan `tests/Feature/TechnicianPaymentTest.php` jika ada; cek mobile dan dark mode.

### Task 5 — Detail Tugas dan Verifikasi Admin

**Files:** Modify `resources/views/tasks/show.blade.php`, `resources/views/verifications/admin.blade.php`; Test `tests/Feature/TaskShowMaintenanceReportTest.php`, `tests/Feature/VerificationAdminInputDataVisibilityTest.php`.

**Result:** Kedua detail memiliki satu panel utama; subbagian menjadi baris/divider. Tab verifikasi dan data sensitif tetap sesuai izin.

- [ ] Catat semua action, tab, status, dan pesan validasi sebelum edit.
- [ ] Tambah assertion tes untuk data/action yang bersyarat, lalu jalankan dua tes sebagai baseline.
- [ ] Ubah panel bertingkat dan padding tab berlebih, tanpa mengubah `x-data` atau urutan form.
- [ ] Jalankan dua tes terpilih; cek detail tugas dan setiap tab verifikasi di mobile/dark mode.

### Task 6 — Custody Gudang

**Files:** Modify `resources/views/warehouse/custody/index.blade.php`; Test `tests/Feature/WarehouseCustodySortTest.php`.

**Result:** Filter di background halaman, tab dan daftar dalam satu panel data; sort/search, indikator stok, dan pembatasan POP sama.

- [ ] Rekam tab, filter, sort, dan empty state.
- [ ] Tambah/ubah tes `WarehouseCustodySortTest` untuk query filter/sort dan state daftar penting.
- [ ] Ratakan panel filter dan radius 8px pada permukaan utama; pertahankan kontrol interaktif.
- [ ] Jalankan `php artisan test --compact tests/Feature/WarehouseCustodySortTest.php`; cek overflow tabel pada 375px.

### Task 7 — Wizard Survey

**Files:** Modify `resources/views/surveys/report.blade.php`; Test `tests/Feature/SurveyReportPhotoPersistsAcrossValidationErrorTest.php` dan `tests/Feature/SurveyIdentityEditTest.php`.

**Result:** Satu permukaan form utama; stepper tetap menunjukkan tahap aktif dan error, unggahan foto tetap dapat dipakai.

- [ ] Rekam setiap step, error validasi, preview upload, dan navigasi mobile.
- [ ] Pastikan tes kedua alur di atas melindungi state pasca error dan nilai field; tambah assertion jika ada celah.
- [ ] Ubah wrapper intro/stepper/isi menjadi satu hierarki tanpa kartu bersarang; jangan ubah JavaScript wizard atau payload.
- [ ] Jalankan dua tes; uji manual keyboard dan 375px untuk seluruh step.

### Task 8 — Wizard Pelanggan Create/Edit

**Files:** Modify `resources/views/customers/create.blade.php`, `resources/views/customers/edit.blade.php`; Test `tests/Feature/CustomerCreateTest.php`, `tests/Feature/CustomerRegistrationTest.php`.

**Result:** Create/edit memakai permukaan form dan navigasi step yang konsisten; nilai lama, error, dokumen, dan status kelengkapan tetap terbaca.

- [ ] Rekam semua step dan state validasi kedua form.
- [ ] Tambah assertion pada dua tes untuk field wajib, nilai lama sesudah gagal, dan aksi edit yang hanya tampil jika diizinkan.
- [ ] Rapikan satu form per kali, lalu samakan pola keduanya; hindari perubahan nama input/urutan langkah.
- [ ] Jalankan dua tes di atas; periksa tiap step pada 375/768/desktop dan dark mode.

### Task 9 — Laporan: Import, Pembayaran, Tagihan

**Files:** Modify `resources/views/reports/imports/index.blade.php`, `resources/views/reports/payments/index.blade.php`, `resources/views/reports/invoices/index.blade.php`; Test `tests/Feature/ReportInvoiceTest.php` dan tes laporan pembayaran/import yang tersedia.

**Result:** Laporan Import yang berfungsi sebagai daftar memakai Type A. Laporan Pembayaran/Tagihan diklasifikasikan berdasarkan fungsi yang terlihat: jika dashboard agregat, KPI card Type C dipertahankan; jika daftar, gunakan summary strip. Semua filter tetap berfungsi.

- [ ] Rekam screenshot dan tentukan tipe masing-masing laporan dari susunan data/aksi aktual; catat keputusan di review task.
- [ ] Tambah/ubah tes filter rentang tanggal, export link, dan keterkaitan data sesuai tes terdekat.
- [ ] Edit halaman satu per satu; hindari konversi KPI card otomatis pada Type C.
- [ ] Jalankan `php artisan test --compact tests/Feature/ReportInvoiceTest.php` dan tes laporan yang diubah; cek empty state dan mobile.

### Task 10 — Komponen bersama dan shell

**Files:** Modify hanya setelah inventaris pemakaian: `resources/views/components/ui/card.blade.php`, `input.blade.php`, `modal.blade.php`, `resources/views/layouts/app.blade.php`; Test: tes feature pemakai komponen yang terdampak, dipilih dari task sebelumnya.

**Result:** Radius dasar 8px, input tanpa shadow dekoratif, logo solid. Modal tetap memiliki batas visual dan fokus yang jelas. Tidak ada perubahan global sebelum semua pemakaian diketahui.

- [ ] Inventaris semua pemanggil `x-ui.card`, `x-ui.input`, `x-ui.modal` dan layout shell; identifikasi pengecualian.
- [ ] Perbarui tes halaman pemakai untuk interaksi modal/form yang berisiko; jalankan baseline.
- [ ] Ubah satu komponen per kali; review efeknya pada halaman yang sudah dibenahi dan halaman yang belum.
- [ ] Jalankan tes pemakai terpilih, lalu cek screenshot lintas halaman dan fokus modal. Jika efek samping luas, batalkan perubahan global dan lakukan override lokal yang terukur.

### Task 11 — Review akhir dan tracking

**Files:** Update `docs/UI_DESIGN_AUDIT_2026-10-16.md` dengan status per temuan; update `docs/TASKS.md` hanya sesuai protokol setelah task terkait benar-benar selesai. Tidak ada perubahan produk pada task ini.

- [ ] Bandingkan hasil tiap task dengan Design.md §1.3–1.5; tandai temuan yang sudah selesai dan yang masih perlu audit visual.
- [ ] Jalankan tes terfokus yang pernah diubah dan pemeriksaan browser terakhir; perluas ke suite penuh hanya bila ditemukan risiko lintas komponen.
- [ ] Periksa `git diff` agar tidak ada perubahan pada data, permission, controller, atau berkas kerja pengguna.
- [ ] Catat sisa risiko dan next task yang benar di `docs/TASKS.md` setelah status proyek mengizinkan.

## Out of scope dan ketergantungan

Dashboard utama/NOC adalah Type C, sehingga banyak KPI card di sana bukan pelanggaran card budget. Halaman cetak, modal, toast, serta perubahan lokal pengguna di task/teknisi dibahas terpisah bila hasil visual nyata menunjukkan masalah. Tidak ada dependensi baru. Rencana ini tidak mengubah task aktif Sprint 8.10 dan tidak menetapkan tanggal implementasi.
