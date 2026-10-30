# Sisa Pekerjaan — Papan Kelola Stok

Handoff untuk agent berikutnya. Papan Kelola Stok (`warehouse.stock.index`) sudah
diimplementasikan di branch `dev` (commit `ed9ab49` … `08a8aa5`). Dokumen ini
mencatat temuan awal dan hasil tindak lanjut. Prioritas 1 dan 2 telah ditangani pada 2026-10-30; daftar di bawah dipertahankan sebagai catatan handoff awal.

Baca dulu: spec `docs/superpowers/specs/2026-10-29-kelola-stok-papan-gudang-design.md`
dan plan `docs/superpowers/plans/2026-10-29-kelola-stok-papan-gudang.md`.

## Aturan kerja

- Bahasa kerja Indonesia (kode, komentar, commit). Ikuti `CLAUDE.md`.
- Kerja di `dev`. Jangan merge ke `master` atau push kecuali diminta.
- JANGAN jalankan full test suite. Jalankan `php artisan test --compact tests/Feature/Warehouse`
  atau filter yang terdampak.
- Pint hanya pada file milik sendiri (`vendor/bin/pint <file>`), BUKAN `--dirty`.
- Working tree berisi perubahan user yang tidak terkait (pembayaran, kolektor,
  docs). Jangan di-stash, di-reset, atau ikut di-commit. `git add` per file.
- `resources/views/warehouse/stock/board/_toolbar.blade.php` dan
  `docs/plan/warehouse/design-preview.html` punya edit user yang belum
  di-commit. Baca diff-nya dulu (`git diff <file>`) sebelum menyentuhnya.
- Perubahan Blade/JS wajib `npm run build`.
- Tes memakai PHPUnit 12 (`#[Test]`, nama snake_case). Pint suka mengubah nama
  tes jadi camelCase; kembalikan ke snake_case.

## Prioritas 1 — Verifikasi browser (belum pernah dilakukan)

Tidak ada agent yang punya akses browser. Cek manual setelah `npm run build`:

- `/warehouse/stock` (papan), `?view=matrix`, `?view=rincian`, `?view=ringkasan`.
- Alpine: lipat/buka band gudang dan kategori, buka drawer dari baris tabel
  dan dari sel matriks, fokus terkunci (`x-trap`) dan Esc menutup drawer.
- Menu aksi baris (di-teleport) tidak terpotong dan menutup saat klik di luar.
- Matriks: header dan kolom pertama sticky saat scroll.
- Mobile (lebar sempit) dan dark mode.
- Letak tombol pilih tampilan Papan/Rincian/Ringkasan di tampilan lama.

Hasil temuan dicatat di bawah bagian "Temuan browser" di file ini.

## Prioritas 2 — Kekurangan tampilan

1. **Kolom "spesifikasi singkat" belum dirender.** Ada di spec; tidak ada di
   `board/_table.blade.php`. Cek spec untuk sumber datanya, tambahkan ke
   `makeRow()` di `app/Services/WarehouseStockBoardService.php` bila perlu.
2. **Band kategori di matriks mungkin tidak sticky.** `board/_matrix.blade.php`.
3. **Tombol "Bentang/Ciutkan" hampir tidak berefek di matriks** (collapse-all
   tidak punya target yang berarti). Hubungkan ke band kategori atau sembunyikan
   tombolnya di mode matriks.
4. **Notifikasi "hasil pencarian terpotong" bisa hilang.** Logikanya ada di
   `board/_toolbar.blade.php` (variabel `$searchTruncated`). File ini punya edit
   user yang belum di-commit; periksa dulu.
5. **JS lama masih ikut dimuat di halaman papan** (modal SN/roll lama). Muat
   hanya di `?view=rincian`/`ringkasan`.

## Prioritas 3 — Pembersihan setelah papan stabil (spec §11.7)

Lakukan HANYA setelah Prioritas 1 lolos dan user setuju:

- Hapus tampilan Per Lot (`?view=rincian`) dan Ringkasan beserta modal SN/roll lama.
- Perbaiki sekitar 11 file tes lama yang diberi `'view' => 'rincian'` agar
  mengarah ke papan atau dihapus bersama fiturnya. Jangan menghapus tes tanpa
  persetujuan user.
- Label tombol "Ringkasan per Item" dipertahankan karena ada tes yang menegaskan
  teksnya; sesuaikan tes bila tombolnya dihapus.

## Catatan perilaku (disengaja, jangan "diperbaiki" tanpa tanya)

- Atur Ambang di papan TIDAK membawa `lot_no`; ambang selalu di lot kosong.
  Ambang lama di lot selain kosong tidak terbaca papan (tidak ada fallback).
  Cek dampak data: `SELECT count(*) FROM inventory_balances WHERE lot_no <> '' AND minimum_stock IS NOT NULL`.
- Dashboard menghitung "menipis" per lot; papan membandingkan total dengan
  ambang di lot kosong. Angkanya bisa berbeda.
- Barang qty 0 tidak jadi baris papan (sama dengan layar lama).
- Custody Teknisi sengaja tidak ditampilkan.
- Istilah "lot" tidak boleh muncul di UI papan (ada tes yang menjaganya).

## Di luar scope papan (ditemukan saat kerja)

Tiga tes gagal sebelum pekerjaan papan; teksnya sudah tidak ada di view:

- `tests/Feature/DashboardWarehouseAlertTest.php`: "low stock dihitung…" mencari
  teks `Alert Stok Kritis POP`; "user tanpa warehouse…" dapat 302, bukan 200.
- `tests/Feature/WarehouseDashboardPageTest.php`: `owner_can_render…` mencari
  teks `Dasbor & Riwayat Gudang`.

Tanya user: sesuaikan tesnya, atau kembalikan teks/tampilannya.

## Pencatatan

- docs/TASKS.md telah diperbarui melalui ADHOC-241. ADHOC-202 tetap aktif.
  Tampilan lama belum dihapus (spec §11.7) karena perlu stabil dalam pemakaian
  nyata dan penghapusan tes lama memerlukan persetujuan khusus.
## Temuan browser

- 2026-10-30: diuji dengan Playwright Chromium pada database SQLite uji terisolasi. /warehouse/stock serta mode papan, matrix, rincian, dan ringkasan merender HTTP 200 tanpa error JavaScript.
- Band gudang/kategori di papan dapat dilipat dan dibuka. Drawer dari baris tabel maupun sel matriks terbuka; fokus tetap di drawer, Esc menutup, lalu fokus kembali ke pemicu.
- Menu aksi yang di-teleport ke body berada dalam viewport dan menutup saat klik di luar. Header, kolom pertama, dan band kategori matriks berposisi sticky. Bentang/Ciutkan matriks kini membuka/menutup baris kategori.
- Tampilan mobile 390 px dan dark mode diperiksa di browser. Tabel memiliki gulir horizontal; tab tampilan lama tetap terlihat sebagai pilihan di toolbar.
- Browser awal menemukan menu aksi rusak setelah skrip modal lama dipisah: helper calcDropdownPos masih dibutuhkan papan. Helper dipasang pada semua mode; skrip modal SN/roll lama kini hanya ada pada Rincian/Ringkasan. Pemeriksaan browser setelah perbaikan lulus.
- Kolom spesifikasi singkat ditampilkan dari meter_per_roll pada barang ROLL bila datanya ada. Master barang saat ini tidak memiliki kolom spesifikasi umum; barang lain tidak diberi spesifikasi rekaan. Notifikasi hasil pencarian terpotong sudah ada dan dipastikan muncul di Papan dan Matriks dengan tes.
- Tes tests/Feature/Warehouse: 110 lulus, 711 assertion. Build Vite melalui docker compose run --rm assets lulus. Pembersihan tampilan lama (§11.7) dilanjutkan pada ADHOC-242 setelah user menyetujui penghapusan tes UI lama.

## Lanjutan implementasi ADHOC-242 (2026-10-30)

- Rincian dan Ringkasan lama tidak dirender lagi. Tautan `?view=rincian` dan `?view=ringkasan` dialihkan ke Papan, dengan filter relevan dipertahankan. Tab dan modal SN/roll lama dihapus; drawer Papan dan Matriks tetap memakai endpoint SN/roll yang dibatasi POP.
- Master Barang kini mempunyai `short_specification` opsional (maksimal 255 karakter). Form tambah/edit dan Papan menampilkannya. Barang roll juga menampilkan panjang per roll dari master; migrasi tambahan bersifat nullable, tidak mengubah data lama, dan telah diterapkan khusus pada database aplikasi lokal.
- Tes yang khusus memeriksa antarmuka Rincian/Ringkasan dihapus dengan persetujuan user. Tes stok, pencarian, ambang, dan POP scope diperbarui untuk Papan; tes service Posisi Stok tetap dipertahankan.
- Audit baca-saja pada database aplikasi: 39 baris `inventory_balances` mempunyai ambang di `lot_no` tidak kosong; 0 baris berambang di `lot_no` kosong; 0 baris berada di bawah ambangnya saat audit. Papan membaca ambang dari `lot_no` kosong, sehingga ambang lama itu belum terlihat di Papan. Data ambang tidak dipindahkan karena aturan handoff menyebut perilaku ini disengaja dan memerlukan keputusan tersendiri. Dashboard tetap dapat menghitung dengan dasar per lot yang berbeda.
- Browser Chromium pada database SQLite uji: Papan dan Matriks terbuka tanpa error JavaScript, tautan Rincian dialihkan ke Papan, tab lama tidak ada. Build Vite lulus.