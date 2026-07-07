# User Flow — Modul Billing & Pembayaran

Aktor: **Admin/Kolektor** (permission `invoices.*`, `payments.*`), **FOP** (trigger invoice AWAL lewat verifikasi instalasi — lihat [docs/fop-task](../fop-task/README.md)).

## 1. Lihat daftar tagihan (`/invoices`)

1. Buka `/invoices` → tabel semua tagihan (POP-scoped), urut `issue_date` terbaru.
2. Filter: search (nomor invoice/nama pelanggan/CID/HP), POP, periode billing, status (`belum_dibayar`/`sebagian`/`lunas`/`batal`), status_group (`lunas` / `belum_lunas`), tipe invoice (`awal`/`bulanan`/`reaktivasi`).
3. Shortcut: `/invoices/lunas`, `/invoices/belum-lunas` (pre-filter status_group).
4. Ringkasan tunggakan di atas tabel: total `remaining_amount` untuk tipe AWAL+Reaktivasi vs BULANAN (independen dari filter aktif).

## 2. Bayar 1 tagihan

1. Dari `/invoices`, klik tagihan yang belum lunas → detail (`/invoices/{id}`) menampilkan riwayat pembayaran.
2. Klik "Bayar" → form (`/invoices/{id}/payments/create`): tanggal bayar, metode (cash/transfer/qris/lainnya), nominal (maks sisa tagihan), bukti (opsional, jpg/png/pdf ≤2MB), catatan.
3. Submit → sistem cek status invoice (tolak kalau udah lunas/batal), simpan `Payment`, update `paid_amount`/`remaining_amount`/`invoice_status` di `Invoice`.
4. Kalau nominal = sisa penuh → status invoice jadi `lunas`. Kalau kurang → `sebagian` (masih bisa dibayar lagi).
5. Bisa juga lewat **quick-payment-modal** (partial `resources/views/partials/quick-payment-modal.blade.php`) — shortcut bayar tanpa pindah halaman penuh.

## 3. Bayar massal (bulk-pay)

1. Di `/invoices`, centang checkbox beberapa tagihan (yang belum lunas/batal) → bar aksi muncul di bawah layar ("N tagihan dipilih").
2. Isi tanggal & metode bayar sekali → klik submit.
3. Sistem lunasi tiap tagihan terpilih dengan nominal = sisa tagihan masing-masing (bukan partial) — 1 transaksi DB per invoice, gagal di satu invoice gak gagalin semua.
4. Hasil: notifikasi "N tagihan berhasil dibayar, M gagal" (kalau ada gagal).

**Kasus pakai:** kolektor setor banyak pembayaran bulanan flat sekaligus tanpa buka invoice satu-satu.

## 4. Buat tagihan manual

1. Dari halaman detail pelanggan, admin dengan permission `invoices.create` isi form: periode billing (`Y-m`), tanggal terbit & jatuh tempo, tipe tagihan (awal/bulanan/reaktivasi), biaya tambahan (prorate, kabel, instalasi, tiang — opsional).
2. Sistem cek: pelanggan harus `active`/`suspended` atau `siap_billing`, punya `customerService` aktif.
3. Cek dobel: tagihan tipe+periode yang sama utk pelanggan itu belum ada (AWAL & BULANAN boleh bareng di periode sama — kasus reaktivasi).
4. Submit → nomor invoice sequential (`INV-YYYYMM-NNNN`) di-lock (`lockForUpdate`) biar gak tabrakan nomor pas concurrent.

## 5. Tagihan AWAL otomatis (dari verifikasi instalasi)

1. FOP approve verifikasi instalasi terakhir pelanggan (`CustomerVerificationController::finalVerify`).
2. Sistem hitung subtotal, prorate, biaya ekstra (kabel/instalasi/tiang) berdasar input form verifikasi → buat `Invoice` tipe `awal`.
3. Bersamaan, pelanggan diaktivasi: `status=active`, CID di-generate.
4. Admin lanjut proses pembayaran tagihan AWAL ini seperti tagihan biasa (langkah 2).

## 6. Tagihan Bulanan otomatis (recurring)

1. Command `php artisan billing:generate-monthly-invoices` dijalankan tiap awal bulan (manual/scheduler).
2. Sistem loop semua pelanggan aktif/suspended yang punya `customerService` dengan `monthly_price > 0`.
3. Skip: pelanggan yang aktivasinya di bulan yang sama (udah kena tagihan AWAL), atau yang udah punya tagihan BULANAN periode ini.
4. `--dry-run` — preview siapa aja yang bakal dapet tagihan tanpa insert beneran (buat admin cek dulu sebelum eksekusi).

## 7. Lihat detail pembayaran & riwayat (`/payments`)

1. Buka `/payments` → tabel semua pembayaran (POP-scoped), filter: search, POP, rentang tanggal, metode, status, tipe invoice terkait.
2. Klik pembayaran → detail (`/payments/{id}`): info invoice terkait, customer service, internet package, penerima (`receiver`), dan (kalau permission `view_audit_logs`) riwayat audit log.

## 8. Laporan (`/reports/invoices`, `/reports/payments`)

1. Admin buka laporan tagihan/pembayaran → filter periode & POP → lihat rekap.
2. Tombol export → download CSV/stream (`InvoiceReportController@export`, `PaymentReportController@export`).

## Guard / Permission per Aksi

| Aksi | Permission |
|------|------------|
| Lihat `/invoices`, `/invoices/lunas`, `/invoices/belum-lunas`, detail invoice | `invoices.view` |
| Buat tagihan manual | `invoices.create` |
| Lihat `/payments`, detail payment | `payments.view` |
| Bayar (single/bulk) | `payments.create` |
| Lihat audit log payment | `view_audit_logs` |
| Lihat laporan invoice/payment | permission report terkait (`Route::middleware` grup `/reports/*`) |

Semua query invoice/payment discope POP lewat `applyUserScope()` — admin non-owner cuma bisa akses data POP yang di-assign ke dia; akses di luar scope → 403.
