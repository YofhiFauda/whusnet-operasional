# User Flow — Modul Billing & Pembayaran

Aktor: **Admin/Kolektor** (permission `invoices.*`, `payments.*`), **FOP** (trigger invoice AWAL lewat verifikasi instalasi — lihat [docs/fop-task](../fop-task/README.md)).

## 1. Lihat daftar tagihan (`/invoices`)

1. Buka `/invoices` → tabel semua tagihan (POP-scoped), urut `issue_date` terbaru.
2. Filter: search (nomor invoice/nama pelanggan/CID/HP), POP, periode billing, status (`belum_dibayar`/`sebagian`/`lunas`/`batal`), status_group (`lunas` / `belum_lunas`), tipe invoice (`awal`/`bulanan`/`reaktivasi`).
3. Shortcut: `/invoices/lunas`, `/invoices/belum-lunas` (pre-filter status_group).
4. Ringkasan tunggakan di atas tabel: total `remaining_amount` untuk tipe AWAL+Reaktivasi vs BULANAN (independen dari filter aktif).

## 2. Bayar 1 tagihan

Dua pintu masuk, jalur belakang (route + logic) sama persis:

1. **Halaman penuh** — dari `/invoices/{id}` (Detail Tagihan), klik "Bayar"/"Bayar Cicil" (label ikut status: "Bayar Cicil" kalau tagihan sudah `sebagian`) → form `/invoices/{id}/payments/create`.
2. **Modal cepat** (paling sering dipakai sehari-hari) — tombol "Bayar"/"Bayar Cicil" langsung di baris tagihan `/invoices` (list) atau tab Tagihan di Detail Pelanggan → modal AJAX, tanpa pindah halaman.

Isian sama di kedua form: tanggal bayar, metode (cash/transfer/kolektor/lainnya — `qris` dihapus 2026-09-22, tak pernah dipakai), **Nominal Diterima dari Pelanggan** (total uang fisik — boleh lebih besar dari sisa tagihan, lihat poin 4), bukti (opsional, jpg/png/pdf ≤2MB), catatan. Metode **Lainnya** menampilkan field keterangan wajib (mis. "OVO", "Dana") — reuse field Catatan yang sama, cuma jadi wajib & relabel via JS (`qpToggleMethodFields()` / `pcToggleMethodFields()` / `hubTogglePaymentMethodFields()` / `cbToggleNote()` tergantung form), divalidasi ulang server (`required_if:payment_method,lainnya`).

3. Submit → sistem cek status invoice (tolak kalau udah lunas/batal), simpan `Payment`, update `paid_amount`/`remaining_amount`/`invoice_status` di `Invoice`.
4. **Lebih bayar (2026-08-04):** nominal yang diketik BOLEH lebih besar dari sisa tagihan — admin tak perlu hitung sendiri. Sistem otomatis menerapkan sebesar sisa tagihan ke invoice (jadi `lunas`) dan mencatat kelebihannya sebagai `overpay_amount` — kelebihan itu otomatis masuk **Saldo Pelanggan** (ADHOC-38) dan bisa dipakai manual di pembayaran berikutnya; **belum** otomatis dipakai bulan depan (rancangan ADHOC-92: [analisa-rancangan-saldo-pelanggan.md](../plan/billing/analisa-rancangan-saldo-pelanggan.md)). Kedua form kasih pratinjau hidup sebelum submit ("Rp X diterapkan ke tagihan (Lunas), Rp Y tercatat sebagai lebih bayar").
5. Kalau nominal PAS = sisa penuh → status invoice jadi `lunas`. Kalau KURANG dari sisa → `sebagian` (cicilan, masih bisa dibayar lagi) — tercatat sebagai "Cicilan Ke-N", lihat poin 2b.

### 2a. Lihat riwayat lebih bayar — Tab Khusus (`/payments/overpay`)

1. Buka `/payments`, klik tombol "Lebih Bayar" (badge amber di header) → daftar READ-ONLY semua payment yang punya `overpay_amount > 0`, filter search pelanggan & POP.
2. Info juga muncul di: Detail Pembayaran (badge "Lebih Bayar Rp X" + sub-baris nominal), kwitansi cetak, header Detail Tagihan (kalau tagihan lunas dengan sisa lebih), Riwayat Pembayaran Pelanggan (Detail Pelanggan), dan daftar `/payments` global.
3. Halaman ini read-only. Kelebihan yang tercatat di sini sudah masuk ledger Saldo Pelanggan (ADHOC-38); pemakaiannya lewat isian `use_balance_amount` di form bayar. Pemakaian otomatis saat tagihan bulanan terbit belum ada (rancangan ADHOC-92).

### 2b. Lihat riwayat cicilan

1. Di `/invoices` (list), tagihan berstatus `sebagian` bisa di-expand (klik chevron di samping nomor invoice) → baris anak "Cicilan Ke-1", "Cicilan Ke-2", dst, dengan badge metode bayar & kolektor terpisah, dan sisa setelah tiap cicilan. Begitu tagihan `lunas`, tombol expand hilang — cukup ringkasan Total & Sisa.
2. Di `/invoices/{id}` (Detail Tagihan), tab Riwayat Pembayaran punya kolom "Cicilan" (Cicilan Ke-N) + badge status (Cicil / Lunas / Ditolak) per baris.
3. Di `/payments/{id}` (Detail Pembayaran) dan kwitansi cetak, badge/keterangan menunjukkan "Cicilan Ke-N" atau "Melunasi Tagihan" untuk payment itu spesifik.
4. Pembayaran yang `ditolak` TIDAK ikut dihitung nomor cicilan — mencegah nomor bolong.

## 3. Tolak Pembayaran (`/payments/{id}` → "Tolak Pembayaran")

1. Di Detail Pembayaran, kalau `payment_status = valid` dan admin punya permission `payments.reject`, tombol "Tolak Pembayaran" muncul.
2. Klik → dialog (komponen global `window.Dialog`, `components/dialog.blade.php`) minta alasan wajib diisi.
3. Submit → `payment_status` jadi `ditolak`, `Invoice::recalculateFromPayments()` jalan otomatis dalam transaksi yang sama (tagihan turun kembali ke `sebagian`/`belum_dibayar` sesuai payment valid yang tersisa) — invoice TIDAK pernah "nyangkut" `lunas` padahal payment-nya sudah ditolak.
4. Payment yang sudah `ditolak` tidak bisa direject lagi (tombol hilang, digantikan panel alasan + siapa/kapan menolak).

## 4. Bayar massal

Dua jalur terpisah, jangan tertukar:

**a. Bulk-pay biasa** (`/customers`, List Pelanggan) — centang beberapa tagihan pelanggan (yang belum lunas/batal) → bar aksi muncul di bawah layar ("N tagihan dipilih") → isi tanggal & metode sekali → submit. Sistem lunasi tiap tagihan terpilih dengan nominal = sisa tagihan masing-masing (bukan partial) — 1 transaksi DB per invoice, gagal di satu invoice gak gagalin semua. Hasil: "N tagihan berhasil dibayar, M gagal".

**b. Batch Bayar Kolektor** (`/collectors/{collector}`, tab Worklist & Bayar) — lebih fleksibel: nominal BEBAS per baris (boleh partial/cicil), bukan cuma lunas penuh. Centang invoice + isi nominal masing-masing → submit SEKALI untuk seluruh batch. **All-or-nothing**: satu baris gagal validasi (mis. invoice keburu lunas dari jalur lain) = SELURUH batch ditolak, tak ada yang tersimpan separuh — beda dari (a) yang per-invoice independen. Idempotent: submit ulang dengan sesi yang sama tidak memproses dobel.

**Kasus pakai:** kolektor setor banyak pembayaran sekaligus di satu kunjungan tanpa buka invoice satu-satu.

## 5. Buat tagihan manual

1. Dari halaman detail pelanggan, admin dengan permission `invoices.create` isi form: periode billing (`Y-m`), tanggal terbit & jatuh tempo, tipe tagihan (awal/bulanan/reaktivasi), biaya tambahan (prorate, kabel, instalasi, tiang — opsional).
2. Sistem cek: pelanggan harus `active`/`suspended` atau `siap_billing`, punya `customerService` aktif.
3. Cek dobel: tagihan tipe+periode yang sama utk pelanggan itu belum ada (AWAL & BULANAN boleh bareng di periode sama — kasus reaktivasi).
4. Submit → nomor invoice sequential (`INV-YYYYMM-NNNN`) di-lock (`lockForUpdate`) biar gak tabrakan nomor pas concurrent.

## 6. Tagihan AWAL otomatis (dari verifikasi instalasi)

1. FOP approve verifikasi instalasi terakhir pelanggan (`CustomerVerificationController::finalVerify`).
2. Sistem hitung subtotal, prorate, biaya ekstra (kabel/instalasi/tiang) berdasar input form verifikasi → buat `Invoice` tipe `awal`.
3. Bersamaan, pelanggan diaktivasi: `status=active`, CID di-generate.
4. Admin lanjut proses pembayaran tagihan AWAL ini seperti tagihan biasa (langkah 2).

## 7. Tagihan Bulanan otomatis (recurring)

1. Command `php artisan billing:generate-monthly-invoices` dijalankan tiap awal bulan (manual/scheduler).
2. Sistem loop semua pelanggan aktif/suspended yang punya `customerService` dengan `monthly_price > 0`.
3. Skip: pelanggan yang aktivasinya di bulan yang sama (udah kena tagihan AWAL), atau yang udah punya tagihan BULANAN periode ini.
4. `--dry-run` — preview siapa aja yang bakal dapet tagihan tanpa insert beneran (buat admin cek dulu sebelum eksekusi).

## 8. Lihat detail pembayaran & riwayat (`/payments`)

1. Buka `/payments` → tabel semua pembayaran (POP-scoped), filter: search, POP, rentang tanggal, metode, status (`valid`/`ditolak` — `pending` sudah tidak ada), tipe invoice terkait.
2. Klik pembayaran → detail (`/payments/{id}`): info invoice terkait, customer service, internet package, penerima (`receiver`), kolektor lapangan (`collector`, kalau ada), badge cicilan/lebih bayar (lihat 2a/2b), dan (kalau permission `audit_logs.view`) riwayat audit log.
3. Cetak kwitansi thermal (`/payments/{id}/kwitansi`) — sama-sama menampilkan keterangan cicilan/lebih bayar.

## 9. Kolektor & Pelanggan Ter-assign

1. `/collectors` — daftar kolektor (permission `customers.update` ATAU `payments.create`), tiap baris menunjukkan jumlah pelanggan ter-assign & total tunggakan.
2. `/collectors/{collector}` — detail per kolektor, 2 tab: **Worklist & Bayar** (lihat §4b) dan **Atur Pelanggan** (assign/release pelanggan ke/dari kolektor ini, permission `customers.update`).
3. Assign pelanggan ke kolektor tercatat di `customers.collector_id` — rute permanen, reassignable. Payment yang lahir dari batch kolektor punya `collected_by` snapshot beku (tidak berubah walau `collector_id` pelanggan direassign kemudian).
4. Kolektor sendiri (role `kolektor`) hanya bisa lihat worklist read-only pelanggannya sendiri (`kolektor.view`, terpisah dari `customers.view`) — tak bisa input pembayaran (itu tetap wewenang admin/kasir kantor).

## 10. Laporan (`/reports/invoices`, `/reports/payments`)

1. Admin buka laporan tagihan/pembayaran → filter periode & POP → lihat rekap.
2. Tombol export → CSV (`@export`) atau XLSX (`@exportXlsx`, `reports.invoices.export-xlsx` & `reports.payments.export-xlsx`) — dua-duanya sekarang, konsisten (2026-09-22, sebelumnya Laporan Tagihan cuma CSV).

## 11. Laporan Bulanan Admin Collector & tutup periode (`/reports/collector-monthly`)

1. Admin buka laporan → pilih bulan (& POP) → lihat 4 blok (Tagihan, Piutang Bulan Lalu, Pelanggan, Uang Diterima). Tombol Export Excel.
2. Setelah bulan berganti: tombol **Tutup Periode** membekukan angka (semua POP dalam scope, atau satu POP). Bulan berjalan belum bisa ditutup. Periode berikutnya otomatis terbuka.
3. Salah hitung → owner **Buka Ulang** (alasan wajib) per POP.
4. Piutang yang sudah mustahil ditagih: di detail tagihan (piutang) → **Hapus Buku** + alasan → status Tak Tertagih; bisa **Batalkan Hapus Buku** selama periodenya belum ditutup.

## 12. Laporan Bayar Kolektor (`/reports/collector-payments`)

1. Admin buka laporan → pilih kolektor (atau semua), rentang tanggal, metode → tabel bayar per sesi input dengan Total Sub per kelompok; header **Kas Terkumpul** = jumlah semua baris pada filter.
2. Cari pelanggan/akun/alamat/keterangan lewat kotak cari; tombol Export → Excel.

## Guard / Permission per Aksi

| Aksi | Permission |
|------|------------|
| Lihat `/invoices`, `/invoices/lunas`, `/invoices/belum-lunas`, detail invoice | `invoices.view` |
| Buat tagihan manual | `invoices.create` |
| Hapus buku piutang / batalkan hapus buku | `invoices.approve` |
| Lihat / export Laporan Bulanan Admin Collector | `collector_report.view` / `collector_report.export` |
| Tutup periode | `collector_report.approve` (admin, pop_admin — hanya POP dalam scope) |
| Buka ulang periode | `collector_report.cancel` (owner saja) |
| Lihat / export Laporan Bayar Kolektor | `collector_payment_report.view` / `collector_payment_report.export` |
| Lihat `/payments`, `/payments/overpay`, detail payment, kwitansi | `payments.view` |
| Bayar (single/bulk/batch kolektor) | `payments.create` |
| Tolak pembayaran | `payments.reject` |
| Lihat `/collectors`, `/collectors/{id}` | `customers.update` ATAU `payments.create` |
| Assign/release pelanggan ke kolektor | `customers.update` |
| Worklist read-only kolektor (`/kolektor/worklist`) | `kolektor.view` (permission sendiri, bukan `customers.view`) |
| Lihat audit log payment | `audit_logs.view` |
| Lihat laporan invoice/payment | permission report terkait (`Route::middleware` grup `/reports/*`) |

Semua query invoice/payment discope POP lewat `applyUserScope()` — admin non-owner cuma bisa akses data POP yang di-assign ke dia; akses di luar scope → 403.
