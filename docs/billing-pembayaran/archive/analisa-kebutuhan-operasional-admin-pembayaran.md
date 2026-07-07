# Analisa Kebutuhan Operasional Admin Pembayaran

Dokumen ini analisa kebutuhan admin sehari-hari dalam mengurus pembayaran (registrasi/awal, bulanan, reaktivasi, piutang, cicilan, kolektor, rekap harian/bulanan) — dicek langsung terhadap kode yang ada sekarang, bukan asumsi. Belum ada kode yang ditulis; ini murni analisa & rencana desain.

## Ringkasan Temuan

| # | Kebutuhan | Status | Keterangan |
|---|-----------|--------|------------|
| 1 | Bayar awal/bulanan/reaktivasi, metode cash/transfer | ✅ Sudah ada | `InvoiceType` enum, `payment_method` di `PaymentController.php:157` |
| 2 | Piutang (bayar tagihan bulan lalu) | ⚠️ Struktural jalan, UX belum bantu | Invoice lama tetap valid, tapi tidak ada penanda visual "ini piutang" |
| 3 | Kembalian ditabung buat bulan depan | ❌ Bolong nyata | Sistem sekarang **menolak** overpayment, tidak ada konsep saldo/kredit |
| 4 | Bayar dicicil | ✅ Sudah ada | `invoice_status = 'sebagian'`, banyak `Payment` numpuk ke 1 invoice |
| 5 | Lihat tagihan milik siapa saja | ✅ Sudah ada | `/invoices` list + search |
| 6 | Lihat pelanggan yang sudah dibayar | ✅ Sudah ada | `/invoices/lunas` |
| 7 | Bayar instan tanpa ke Detail Pelanggan | ✅ Sudah dikerjakan sesi ini | Modal Bayar Cepat + `/invoices` |
| 8 | Bayar massal berdasarkan nama kolektor | ⚠️ Setengah jalan | Bulk-pay ada, tapi tidak ada field/konsep "kolektor" sama sekali |
| 9 | Rekap hari ini | ⚠️ Mekanisme ada, UX belum instan | Report sudah support filter tanggal, tinggal butuh preset cepat |
| 10 | Rekap bulanan | ⚠️ Sama seperti #9 | Filter tanggal sudah mendukung range bulan |

## 1. Pembayaran Awal/Bulanan/Reaktivasi + Metode Cash/Transfer

Sudah tersedia penuh. `App\Enums\InvoiceType` (`awal`, `bulanan`, `reaktivasi`), dan `PaymentController::store` sudah validasi `payment_method` in `cash,transfer,qris,lainnya` (`app/Http/Controllers/PaymentController.php:157`). Tidak ada pekerjaan tambahan.

## 2. Piutang (Bayar Tagihan Bulan Sebelumnya)

Secara struktur data ini **sudah bisa** — tiap invoice berdiri sendiri dengan `remaining_amount` masing-masing; invoice Mei yang belum lunas tidak hilang begitu masuk Juli, tetap bisa dibayar kapan saja lewat tombol "Bayar" di baris invoice tersebut (modal Bayar Cepat yang sudah dibangun berlaku untuk invoice manapun, tidak dibatasi periode).

**Bolong**: tidak ada penanda visual yang membedakan "ini piutang (periode sudah lewat)" vs "tagihan berjalan bulan ini". Admin harus membandingkan `billing_period` vs bulan sekarang secara manual.

**Rekomendasi**: tambah badge "Piutang" di baris invoice mana pun yang `billing_period` < bulan berjalan DAN `invoice_status` masih `belum_dibayar`/`sebagian`. Bisa computed di query (tidak perlu kolom baru), ditampilkan di tab Tagihan customer maupun `/invoices` global.

## 3. Kembalian Ditabung untuk Bulan Depan (Saldo/Kredit Pelanggan)

**Ini gap paling nyata dan paling berisiko kalau salah desain.**

Kondisi sekarang: `PaymentController.php:158` — `'amount' => 'required|numeric|min:1|max:' . remaining_amount`. Sistem **secara aktif menolak** input pembayaran yang lebih besar dari sisa tagihan. Tidak ada tabel/kolom apa pun untuk menyimpan kelebihan bayar. Kalau pelanggan bayar Rp200.000 untuk tagihan Rp165.000, admin tidak bisa mencatat itu apa adanya — harus dipaksa pas Rp165.000 dan sisa Rp35.000 dicatat di luar sistem (buku catatan manual, dsb) — itulah sumber selisih/kebocoran yang biasa terjadi di ISP kecil.

### Rancangan (belum dikode, untuk didiskusikan)

**Tabel baru**: `customer_credit_entries` (ledger, **append-only** — tidak boleh UPDATE/DELETE, prinsip akuntansi double-entry supaya selalu bisa diaudit dari histori, bukan cuma angka saldo akhir).

Kolom kasar:
- `customer_id`
- `type`: `deposit` (kelebihan bayar masuk) / `applied` (dipakai untuk melunasi invoice) / `refund` (kalau suatu saat perlu dikembalikan tunai)
- `amount`
- `source_payment_id` (payment mana yang menghasilkan kelebihan ini)
- `applied_to_invoice_id` (nullable, invoice mana yang memakai saldo ini kalau `type=applied`)
- `created_by`, `note`

**Saldo berjalan** = `SUM(deposit) - SUM(applied) - SUM(refund)` per customer — dihitung on-the-fly dari ledger, bukan kolom `balance` yang bisa basi/gak sinkron.

### Alur yang Diusulkan

1. Admin input pembayaran Rp200.000 untuk invoice Rp165.000.
2. Sistem deteksi `amount > remaining_amount` → alih-alih ditolak seperti sekarang, muncul konfirmasi: *"Kelebihan Rp35.000 — mau ditabung sebagai saldo pelanggan?"*
3. Kalau ya: invoice dilunasi dengan `paid_amount = total_amount` (bukan 200rb — invoice tidak boleh mencatat amount lebih dari total_amount miliknya sendiri, biar laporan per-invoice tetap jujur), dan entry `deposit` Rp35.000 masuk ke ledger customer.
4. Bulan depan, saat admin buka modal Bayar Cepat untuk invoice baru customer itu, tampilkan *"Saldo tersedia: Rp35.000"* + tombol **"Pakai Saldo"** yang otomatis isi/potong nominal yang perlu dibayar tunai.
5. Kalau dipakai: entry `applied` Rp35.000 dicatat, `applied_to_invoice_id` diisi invoice yang bersangkutan.

### Yang Perlu Dipikirkan Sebelum Kode

- **Manual dulu, jangan auto-apply diam-diam.** Saldo sebaiknya butuh klik eksplisit "Pakai Saldo" oleh admin, bukan otomatis kepotong sendiri — supaya kolektor/admin selalu sadar & bisa dikonfirmasi ke pelanggan, dan gampang direkonsiliasi kalau ada yang komplain.
- **Interaksi dengan piutang (poin 2)**: kalau customer punya saldo DAN piutang lama sekaligus, sistem sebaiknya menyarankan (bukan memaksa) memakai saldo ke invoice piutang paling lama dulu (FIFO), tapi keputusan akhir tetap di admin.
- **Validasi terpusat**: perubahan ke `PaymentController::store` ini harus dibarengi update ke `PaymentObserver` (yang sudah dibuat sesi sebelumnya) supaya batas atas `amount` tetap konsisten di semua jalur insert, bukan cuma di controller.
- **Audit ketat**: setiap masuk/keluar saldo wajib tercatat `audit_logs` — ini uang yang "melayang" tanpa nempel ke invoice tertentu, kalau tidak diaudit ketat gampang jadi celah.
- **Refund tunai**: kalau pelanggan berhenti langganan sementara masih ada saldo, perlu alur refund manual (dicatat, bukan cuma dihapus).

## 4. Bayar Dicicil

Sudah berjalan penuh, tidak perlu kerjaan tambahan. `invoice_status` sudah punya state `sebagian` (`PaymentController.php:190`: `$remainingAmount <= 0 ? 'lunas' : 'sebagian'`), dan banyak `Payment` row bisa menumpuk ke `invoice_id` yang sama — `paid_amount` terakumulasi tiap kali ada pembayaran baru. Cicilan 2x, 3x, berapa kali pun, invoice menunggu sampai `remaining_amount` mencapai 0.

## 5-6. Melihat Tagihan Siapa Saja & Pelanggan yang Sudah Dibayar

Sudah tersedia lewat halaman global `/invoices` (search nama/CID/no invoice — `InvoiceController.php:33-40`) dan `/invoices/lunas` (filter status lunas). Tidak ada gap struktural, paling tambahan quality-of-life (lihat poin 9-10).

## 7. Pembayaran Instan Tanpa ke Detail Pelanggan

Sudah dikerjakan pada sesi sebelumnya: modal "Bayar Cepat" di tab Tagihan customer dan di `/invoices` global, submit AJAX ke `PaymentController::store`, update in-place tanpa reload.

## 8. Bayar Massal Berdasarkan Nama Kolektor

**Setengah jalan.** Endpoint `PaymentController::bulkStore` sudah bisa membayar banyak invoice sekaligus (checkbox multi-select + floating bar di `/invoices`). Tapi dicek di kode: **tidak ada konsep "kolektor" sama sekali**.

- `Payment.received_by` adalah FK ke `users` — itu **admin yang menginput data** di kantor, bukan orang lapangan yang benar-benar mengumpulkan uang dari pelanggan.
- Role yang ada di sistem (`Admin, Atasan, FOP, Helpdesk, NOC, Owner, POP Admin, Sales, Teknisi`) tidak ada "Kolektor".
- Field legacy `received_by_old`/`deposited_by_old` (dari migrasi data lama) memang berupa label teks bebas, bukan akun sistem — mengindikasikan kolektor di lapangan memang tidak login ke sistem, mereka hanya setor fisik/hasil ke admin.

### Rekomendasi

Tidak perlu membuat kolektor jadi user sistem (kemungkinan besar mereka tidak pernah login). Cukup:
- Tabel referensi ringan `collectors` (id, name, phone, pop_id, aktif/tidak) — supaya nama konsisten dan bisa direkap rapi per kolektor, tidak seperti free-text yang rawan typo/variasi penulisan.
- Tambah kolom `collector_id` (nullable) di `payments` — siapa yang secara fisik mengumpulkan uang untuk transaksi ini, terpisah dari `received_by` (siapa yang input).
- Di floating bar Bayar Massal yang sudah ada, tambah 1 dropdown "Kolektor" — dipilih sekali, berlaku untuk semua invoice dalam batch itu. Tidak perlu sistem pre-assignment kolektor↔pelanggan; cukup dicatat siapa yang menyetor pada saat transaksi terjadi.
- Laporan (`PaymentReportController`) tinggal ditambah filter/group-by `collector_id` untuk rekap per kolektor.

## 9-10. Rekap Harian & Bulanan

Mekanisme dasar **sudah ada** — `InvoiceReportController` dan `PaymentReportController` sudah mendukung filter rentang tanggal (`whereDate('issue_date'/'payment_date', ...)`). Admin secara teknis bisa mengisi `date_from=date_to=hari ini` atau rentang 1 bulan penuh sekarang juga.

**Bolong**: tidak ada tombol pintas — admin harus mengetik/memilih tanggal manual setiap kali buka halaman report.

**Rekomendasi**: tambah tombol preset "Hari Ini" dan "Bulan Ini" di halaman report (`reports/invoices`, `reports/payments`) yang otomatis mengisi filter tanggal. Opsional: widget kecil di dashboard menampilkan total pembayaran & tagihan hari ini tanpa perlu buka halaman report sama sekali. Kalau kolektor (poin 8) sudah ada, breakdown rekap per kolektor bisa ditambahkan di laporan yang sama.

## Prioritas Pengerjaan (Disarankan)

1. **Saldo/kredit pelanggan** (poin 3) — paling besar dampaknya, paling berisiko kalau desainnya asal, harus dipikirkan matang (ledger append-only, validasi terpusat, audit ketat) sebelum ada satu baris kode pun.
2. **Field kolektor di Bayar Massal** (poin 8) — kecil, cepat dikerjakan, langsung terasa manfaatnya.
3. **Badge "Piutang"** pada invoice periode lalu (poin 2) — kecil, murni tampilan, tidak butuh migrasi besar.
4. **Preset tanggal "Hari Ini"/"Bulan Ini"** di halaman report (poin 9-10) — kecil, quality-of-life, tidak butuh perubahan skema.
