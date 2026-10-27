# Audit UI terhadap Design.md — 16 Oktober 2026

## Scope
Sprint aktif menurut docs/TASKS.md: Sprint 8.10, S8.10-T003 (FOP Notification Dashboard), In Progress. Permintaan ini adalah audit dan rencana lintas halaman. Tidak ada perubahan UI, perilaku, database, atau status task. Acuan utama: design-system/whusnet-operasional/Design.md v2.5.0 §1.3–1.5.

## Metode
Audit statis pada 190 halaman Blade utama (tanpa components, partials, tabs, layouts, dan berkas berawalan _). 70 halaman memakai rounded-xl/2xl/3xl; 151 memuat shadow-*; 163 memuat kelas padding 6 ke atas. Ini indikator untuk inspeksi, bukan jumlah pelanggaran: modal, dashboard agregat, dan halaman cetak dapat menjadi pengecualian. Browser dan screenshot belum diperiksa.

## Temuan

| Prioritas | Halaman | Bukti | Perbaikan yang direncanakan |
|---|---|---|---|
| P0 | Daftar Pembayaran | resources/views/payments/index.blade.php:64-100 — 4 KPI card pada halaman daftar | Summary strip datar dengan divider; satu panel tabel. |
| P0 | Master POP/Cabang | resources/views/master/pop/index.blade.php:8-47 — header/stat dan filter dibungkus dua kartu | Header dan filter tanpa kartu; summary strip datar. |
| P0 | Master Barang | resources/views/master/items/index.blade.php:9-14 — filter panel rounded-2xl bershadow | Lepas pembungkus filter; radius 8px. |
| P0 | Daftar Node Jaringan | resources/views/network/nodes/index.blade.php:25,65-106 — header gradien dan kartu ringkasan | Header polos, summary strip, warna solid. |
| P0 | Detail Invoice | resources/views/invoices/show.blade.php:196,326 — header kartu dan blok status berwarna | Header polos; satu panel detail; status hanya badge/teks. Area cetak dikecualikan. |
| P0 | Detail Worksheet Kolektor/Teknisi | resources/views/collector-worksheet/show.blade.php:51-120 — banyak panel rounded-2xl, gradien, shadow | Satu panel detail; ringkasan sebagai baris/divider; posisi aksi setor tetap jelas. |
| P1 | Detail Tugas | resources/views/tasks/show.blade.php:100,128,388 — panel dan subpanel berulang | Satu panel tugas; subbagian memakai divider. |
| P1 | Verifikasi Admin | resources/views/verifications/admin.blade.php:59,90,141-160 — panel bertumpuk dan padding p-6/md:p-8 | Header polos, satu panel bertab, ruang lebih padat. |
| P1 | Custody Gudang | resources/views/warehouse/custody/index.blade.php:51,157-193 — panel filter dan data terpisah | Filter tanpa kartu; panel tunggal untuk tab/data. |
| P1 | Form Survey | resources/views/surveys/report.blade.php:40,66,99,174 — wizard dalam beberapa kartu bershadow | Satu permukaan form; navigasi langkah ringan. |
| P1 | Form Pelanggan create/edit | resources/views/customers/create.blade.php:38,80,120; edit.blade.php:42,93,132 — pola wizard berkartu | Samakan kedua form ke satu panel utama; validasi/urutan langkah tetap. |
| P2 | Laporan Import | resources/views/reports/imports/index.blade.php:8-50 — 4 KPI card pada daftar | Summary strip jika halaman utamanya daftar. |
| P2 | Laporan Pembayaran/Tagihan | resources/views/reports/payments/index.blade.php:27-65; reports/invoices/index.blade.php:28-67 | Pastikan dulu apakah dashboard agregat Type C; jika ya KPI card diperbolehkan, tetapi radius/shadow/filter perlu diseragamkan. |

## Komponen bersama
- resources/views/components/ui/card.blade.php:12 memakai rounded-md (6px), berbeda dari aturan panel 8px.
- resources/views/components/ui/input.blade.php:7 memakai rounded-md dan shadow-sm.
- resources/views/components/ui/modal.blade.php:43-57 memakai rounded-xl/2xl; modal memang boleh menjadi kartu bershadow, tetapi radius/padding perlu audit tersendiri.
- resources/views/layouts/app.blade.php:84 memakai gradien dan shadow pada logo mark, sedangkan Design.md mensyaratkan warna solid.

## Rencana
1. Jadikan Type A: header/filter polos, summary strip datar, satu panel tabel. Type B: header polos, satu panel detail bertab dan divider. Type C: kartu KPI untuk dashboard agregat. Wizard memakai satu permukaan form utama; catat pengecualiannya.
2. Pilot pada Daftar Pembayaran dan Detail Invoice. Bandingkan screenshot sebelum/sesudah untuk desktop, tablet, mobile, light/dark. Uji seluruh aksi dan state.
3. Lanjutkan per keluarga: master/list, detail/worksheet, wizard/form, lalu laporan. Satu keluarga per task.
4. Ubah komponen bersama hanya setelah seluruh pemakaiannya diperiksa. Jangan ganti kelas secara massal.

## Acceptance criteria tahap redesign
- Type A/B mematuhi card budget, dengan header dan filter tanpa kartu.
- Radius panel, tombol, select, input 8px sesuai Design.md; pill hanya untuk elemen pengecualian.
- Tidak ada shadow tebal atau gradien dekoratif pada konten; modal tetap memiliki batas visual.
- Padding tidak membuat data operasional terpencar; layout berfungsi pada 375px, 768px, desktop.
- Light/dark, keyboard focus, empty/error/loading, dan print tetap berfungsi.
- Permission, POP scope, perhitungan billing, dan alur simpan tidak berubah.

## Risiko dan status
Audit masih berbasis markup, belum validasi visual. Redesign belum dimulai. docs/TASKS.md tidak dipindah karena task aktif belum selesai. Berkas kerja pengguna yang sudah berubah tidak disentuh.
