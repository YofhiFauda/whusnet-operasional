# Rancangan Perubahan Kwitansi

**Status:** Terbuka — analisa & keputusan selesai dikunci 2026-09-22 (§Keputusan Terbuka Sebelum Eksekusi + §Kertas NCR 2-Ply), implementasi belum mulai. Di luar sprint aktif, dicatat sebagai **ADHOC-94** di `docs/TASKS.md` (~~ADHOC-93~~ placeholder awal sudah kepakai fitur lain, Hapus QRIS).

**Referensi visual (bukan dokumen keputusan):** `docs/plan/billing/kwitansi.md`, `docs/plan/billing/kwitansi.html` — template cetak WHUSNET existing yang jadi acuan desain. Dua file itu cuma contoh tampilan, semua keputusan pemetaan field & scope ada di dokumen ini.

## Latar Belakang

Kwitansi yang beredar sekarang punya **dua desain berbeda** yang isinya sama (sumber tunggal `ReceiptPresenter`) tapi tata letaknya beda-beda tergantung dari mana dicetak:

1. **Struk thermal 80mm** (`.page` di `payments/receipt.blade.php`, dan `collector-worksheet/receipt-print.blade.php` untuk kwitansi borongan kolektor) — didesain buat printer kasir/roll paper.
2. **Invoice A4 gaya "Stripe/Anthropic"** (`.a4` di `payments/receipt.blade.php` untuk PDF Portal dompdf, dan blok `print-only` di `payments/show.blade.php` untuk cetak langsung dari Detail Pembayaran) — kartu bersih modern, beda total dari struk thermal.

Permintaan: ganti SEMUA titik cetak jadi satu desain yang sesuai kop resmi WHUSNET (`kwitansi.md`/`kwitansi.html`), dan **hilangkan struk thermal sepenuhnya** (bukan disembunyikan/dinonaktifkan — dihapus).

## Prinsip

Satu desain kwitansi dipakai di **semua** tempat: staf & pelanggan melihat bentuk **identik**. Tidak ada lagi cabang thermal vs A4-Stripe vs A4-dompdf yang isinya sama tapi rupa beda.

## Layout & Pemetaan Field (final, hasil klarifikasi)

Mengikuti struktur `kwitansi.html`: kop kiri, tanggal & jatuh tempo kanan atas, No. Dokumen, identitas pelanggan, tabel Rincian Pembayaran, tabel Rincian Total, Terima kasih, Perhatian.

**Layout header (2026-09-23, feedback):** identitas pelanggan (kiri) & tanggal/jatuh tempo (kanan) dirender lewat satu baris tabel (bukan float), supaya keduanya sejajar mulai baris yang sama — float sebelumnya bikin blok tanggal gak sejajar rapi dengan blok identitas.

**Tabel Rincian Pembayaran + Rincian Total (2026-09-23, feedback):** disatukan jadi SATU `<table>` (bukan dua tabel terpisah bersebelahan) — border 1px jadi konsisten di semua baris, tanpa border ganda yang numpuk kelihatan tebal di antara dua tabel. Baris Sub Total/DP/Lebih Bayar/Total cuma 2 kolom yang sejajar dengan Harga & Total; kolom No/Keterangan/Paket di baris itu kosong TANPA border sama sekali (`colspan="3"`, `border: none`) — bukan digabung jadi label lebar (percobaan sebelumnya) dan bukan pula kotak kosong ber-border (percobaan sebelum itu lagi).

| Bagian template | Sumber data |
|---|---|
| Header perusahaan | Statis: "WHUSNET by CONNEXA DIGITAL NETWORK" + alamat + telepon (sama seperti sekarang, hardcoded di view) |
| Tanggal | `ReceiptPresenter::tanggal_bayar` |
| Tgl. Jatuh Tempo | **Field baru** — `invoice->due_date`; "-" kalau tak ada invoice |
| No. Dokumen | `pelanggan.cid` (diubah dari `payment_number` 2026-09-23 — feedback layout, posisi yang sebelumnya "No. Kwitansi") |
| Nama Pelanggan | `pelanggan.nama` |
| Alamat | `pelanggan.alamat` — **satu baris utuh**, bukan `alamat_baris` yang dipecah dua baris (pecahan itu peninggalan struk sempit 80mm, tidak relevan lagi setelah thermal dihapus) |

### Tabel Rincian Pembayaran

Satu baris item + **2 baris kosong filler** (persis `kwitansi.html` acuan) — keputusan dibalik 2026-09-23: awalnya dibuang (dianggap peninggalan struk manual), tapi tabel 1 baris tanpa filler bikin free space gak selaras dengan tinggi tabel Rincian Total di bawahnya. Baris kosong dikembalikan murni buat kerapian visual.

- **Keterangan**: `"Pembayaran Layanan Internet Bulan {NamaBulan}"` — bulan diambil dari `billing_period` invoice kalau ada, fallback ke `payment_date`. Ditambah suffix supaya info cicilan lama tidak hilang dari kwitansi:
  - `" (Cicilan Ke-N)"` kalau pembayaran belum melunasi tagihan.
  - `" (Pelunasan)"` kalau pembayaran ini yang melunasi cicilan sebelumnya.
  - Tanpa suffix kalau lunas sekali bayar / tanpa invoice.
- **Paket**: `invoice.paket`, fallback "-" tanpa invoice.
- **Harga & Total**: nominal `dibayar` — 1 baris, qty implisit 1 (mengikuti pola template, bukan `invoice.total` per unit).

### Tabel Rincian Total

- **Sub Total** = `dibayar`.
- **DP** = **sisa tagihan** (`invoice.sisa`), keputusan yang dikonfirmasi: DP dipetakan ke sisa tagihan cicilan, bukan dibuang dan bukan ke lebih-bayar. "-" kalau lunas/tanpa invoice.
- **Baris tambahan "Lebih Bayar"** — di luar 3 baris template asli, disisipkan di atas Total **hanya** kalau `overpay_amount > 0`. Data ini tidak boleh hilang dari kwitansi meskipun template acuan tak punya baris ini.
- **Total** = `dibayar` (tebal, sama seperti sekarang).

### Footer

"Terima kasih" + 4 poin "Perhatian" — statis, persis `kwitansi.md`.

### Yang DIBUANG dari kwitansi lama

Keputusan final: **staf dan pelanggan melihat bentuk identik**, tidak ada lagi info khusus staf di lembar kwitansi:

- Diterima oleh
- Kolektor/Penagih
- Catatan
- Status badge berwarna (valid/ditolak)
- Riwayat pembayaran (tabel history)
- Breakdown per-pihak ala invoice (`a4-parties`, `a4-history`, dst.)

Info staf itu (siapa kasir yang terima, catatan petugas) tetap tersedia di halaman Detail Pembayaran biasa — cuma tidak lagi ikut ke lembar kwitansi cetak.

## Titik yang Kena

1. **`resources/views/payments/receipt.blade.php`** — tulis ulang total, buang blok `.page` (thermal) dan `.a4` (Stripe-style) lama, ganti markup baru sesuai layout di atas. Dipakai oleh:
   - `PaymentController::receipt()` → route `payments.receipt` (`/payments/{id}/kwitansi`, browser, toolbar Cetak + Kembali).
   - `PortalPaymentController::receiptPdf()` → dompdf, `setPaper('a4')`.
   - `PortalPaymentController::receiptView()` → iframe modal "Lihat Kwitansi" di Portal.
2. **`resources/views/payments/show.blade.php`** — blok `print-only` (cetak langsung dari Detail Pembayaran) diganti pakai partial yang sama lewat `@include`, hilangkan duplikasi markup yang sebelumnya nyaris identik tapi manual disalin.
3. **Dropdown "Cetak Struk"** di `payments/show.blade.php` — opsi "Struk Thermal (80mm)" + fungsi JS `openThermalPreview()` dihapus. Sisa satu tombol "Cetak Kwitansi" (dropdown dibubarkan kalau cuma tinggal 1 opsi).
4. **`resources/views/collector-worksheet/receipt-print.blade.php`** — kwitansi borongan kolektor, sekarang murni struk thermal 80mm. Diganti ke desain baru, tetap 1 lembar per payment dengan `page-break-after` antar kwitansi (kertas biasa, bukan roll). Kolom QR untuk pencocokan otomatis (`docs/plan/kolektor/analisa-kwitansi-otomatis-portal.md`) **tetap dipertahankan** — fitur itu di luar cakupan perubahan desain kwitansi ini.
5. **`ReceiptPresenter`** — tambah field `jatuh_tempo` (invoice `due_date`) dan field turunan keterangan item (bulan + suffix cicilan/pelunasan), supaya semua view tetap baca dari satu sumber, sesuai prinsip kelas ini yang sudah tertulis di docblock-nya.
6. **`$isCustomerCopy` / `$isPdf`** di `receipt.blade.php` dan controller pemanggilnya — kemungkinan **tidak diperlukan lagi** karena staf & pelanggan sekarang identik. Perlu ditelusuri ulang semua pemanggil sebelum keduanya dihapus (jangan dihapus setengah — kalau ternyata masih ada perbedaan teknis murni render seperti toolbar/paper size, boleh dipertahankan sekadar untuk itu, bukan untuk beda isi).

### Yang TIDAK berubah

- Route `payments.receipt` tetap ada — isi beda, path & nama route sama.
- `PaymentReceiptResource` (JSON API Portal) — belum ditelusuri di rancangan ini apakah field internal yang dibuang dari HTML juga perlu dibuang dari JSON, atau JSON tetap kaya data untuk kebutuhan lain Portal. **Perlu dicek sebelum eksekusi**, di luar scope keputusan yang sudah difinalkan di atas.

## Test yang Wajib Diupdate

Assertion isi/markup lama (struk thermal, "Diterima oleh", dst.) akan gagal begitu template diganti:

- `PaymentReceiptPrintTest`
- `PaymentReceiptTest`
- `KwitansiIsiSeragamAntarHalamanTest`
- `KwitansiLembarBoronganTest`
- `CustomerHubModalReceiptAndDocumentTest`
- `Api/CustomerPortal/PortalPaymentReceiptTest`

## Keputusan Terbuka Sebelum Eksekusi

> **Diputuskan user (2026-09-22):**

1. **`$isCustomerCopy` dihapus, `$isPdf` dipertahankan** (sebatas alasan render teknis, bukan alasan konten). Satu template kwitansi dipakai identik untuk staf & pelanggan (Portal dan `/kwitansi`) — alasan awal `$isCustomerCopy` (menyembunyikan data staf khusus dari pelanggan) sudah tidak relevan karena data itu memang dibuang dari SEMUA kwitansi, bukan cuma disembunyikan dari pelanggan. `$isPdf` tetap perlu murni untuk urusan render (toolbar "Cetak"/"Kembali" tidak boleh ikut masuk ke file PDF yang didownload dari Portal) — bukan untuk beda isi.
2. **`PaymentReceiptResource` (JSON Portal) TIDAK dipangkas lebih jauh** — tetap ikut satu sumber `ReceiptPresenter` yang sama dengan template kwitansi cetak (prinsip "satu template kwitansi yang sudah ditetapkan", bukan bikin kontrak data kedua yang menyimpang). `status_valid` dan `keterangan_cicilan` **tetap dipertahankan** di JSON walau tidak lagi tampil sebagai badge berwarna di kertas — dipakai logika aplikasi Portal, bukan ditulis di kertas kwitansi. 4 field internal staf (`penerima`/`penagih`/`catatan`/`dicetak`) tetap dibuang seperti sekarang.

## Kertas NCR 2-Ply (ditambahkan 2026-09-22, dikunci 2026-09-22)

Kwitansi dicetak di atas **kertas NCR 2-ply blangko polos** (kertas rangkap dua tanpa karbon, tanpa kop/garis pre-printed — template Blade ini satu-satunya sumber tata letak, dicetak penuh termasuk kop WHUSNET & garis tabel).

**Ukuran fisik (dikonfirmasi user):** continuous form 1/2 part, **9,5 × 5,5 inci** (lebar × tinggi — separuh dari lembar 9,5"×11" utuh).

**Konsekuensi ke CSS `@page`:**
- CSS `@page` yang ada sekarang di `payments/receipt.blade.php` (`size: auto; margin: 0;`) itu warisan struk thermal (printer thermal otomatis pas ke lebar rol-nya) — **harus diganti** untuk jalur print fisik ke NCR: `@page { size: 9.5in 5.5in; margin: 0; }` (atau satuan mm yang setara, cek presisi dompdf/browser saat implementasi).
- Ukuran ini **cuma berlaku untuk jalur cetak fisik staf** (`PaymentController::receipt()`, browser print ke printer NCR). **Tidak berlaku** untuk PDF yang didownload pelanggan dari Portal (`PortalPaymentController::receiptPdf()`, tetap `setPaper('a4')` — pelanggan lihat/simpan digital, bukan nyetak ke kertas NCR kantor) atau tampilan iframe modal Portal (layar, bukan kertas).
- Ini **bukan pengecualian dari prinsip "satu template identik"** — isi/layout HTML-nya tetap sama persis di semua tempat (§Layout & Pemetaan Field tidak berubah). Yang beda cuma **ukuran kertas fisik** per konteks render, dan itu memang alasan `$isPdf` tetap dipertahankan (§Pertanyaan 1 di atas) — flag ini yang nentuin `@page` mana yang dipakai, bukan flag konten.

**Tidak ada lagi yang terbuka di bagian ini.**
