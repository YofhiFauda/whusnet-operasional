# Analisa & Skema: Invoice Billing, Penanganan Piutang, dan Laporan Uang Masuk (Cash Basis)

**Status:** Dokumen Analisa & Dokumentasi Fitur Existing  
**Tanggal:** 2026-09-18  
**Modul Terkait:** Billing & Pembayaran (`invoices`, `payments`, `reports/payments`, `reports/invoices`)  
**Rujukan Terkait:**
- `docs/billing-pembayaran/README.md`
- `docs/plan/analisa-tagihan-kategori-pendapatan.md`
- `docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md`

---

## 1. Studi Kasus & Prinsip Bisnis

### A. Definisi Invoice & Piutang (Accounts Receivable)
1. **Invoice Terbayar (`lunas`)**: Tagihan yang seluruh nominalnya telah dilunasi oleh pelanggan (`remaining_amount = 0`).
2. **Invoice Tidak Terbayar / Sebagian (`belum_dibayar` / `sebagian`)**: Tagihan yang masih memiliki sisa kewajiban (`remaining_amount > 0`).
3. **Piutang Berjalan**: Tagihan bulan-bulan sebelumnya yang belum lunas tidak dihapus atau di-reset saat pergantian bulan, melainkan berstatus sebagai **piutang / tunggakan aktif** yang harus dilunasi pada bulan-bulan berikutnya.

---

### B. Prinsip Rekapitulasi Laporan Keuangan (Cash Basis vs Invoice Basis)

Sistem membedakan dua jenis laporan keuangan secara tegas:

| Jenis Laporan | Controller | Dasar Pengelompokan | Pertanyaan Bisnis yang Dijawab |
| :--- | :--- | :--- | :--- |
| **Laporan Uang Masuk (Pembayaran)** | `PaymentReportController` (`/reports/payments`) | **`payment_date`** (*Cash Basis* / Tanggal Uang Diterima) | *"Berapa uang fisik/transfer riil yang masuk ke kas pada bulan ini?"* |
| **Laporan Tagihan (Piutang)** | `InvoiceReportController` (`/reports/invoices`) | **`billing_period`** / `issue_date` (*Accrual / Invoice Basis*) | *"Berapa total tagihan yang diterbitkan pada periode tertentu dan berapa sisa piutang yang belum tertagih?"* |

> [!IMPORTANT]
> **Aturan Tutup Buku Kas**:  
> Uang pembayaran piutang tagihan bulan lalu **wajib masuk ke laporan uang masuk pada bulan saat uang diterima/dibayarkan**, BUKAN bulan terbit tagihan.  
> *Contoh*: Pembayaran tagihan September yang baru dibayarkan pada bulan Oktober akan masuk ke rekap uang masuk **Bulan Oktober**, karena pembukuan kas bulan September telah ditutup.

---

### C. Skema & Prioritas Alokasi Pembayaran (Metode FIFO)

#### Kasus Simulasi:
- **Pelanggan**: Agus (Biaya paket Rp 150.000 / bulan).
- **Tagihan September**: Rp 150.000 (Jatuh tempo 10 September) → **Belum dibayar (Menjadi Piutang)**.
- **Tagihan Oktober**: Rp 150.000 (Terbit 1 Oktober).
- **Kondisi 5 Oktober**: Agus datang ke kasir hanya membawa uang **Rp 150.000**.

#### Aturan & Alur Penyelesaian:
1. **Prioritas Alokasi (FIFO)**: Uang Rp 150.000 milik Agus dialokasikan terlebih dahulu untuk melunasi **Tagihan September (Piutang Tertua)**.
2. **Hasil pada Status Tagihan**:
   - Tagihan September (`INV/2026/09/...`): Berubah menjadi **LUNAS** (`remaining_amount = 0`).
   - Tagihan Oktober (`INV/2026/10/...`): Tetap **BELUM DIBAYAR** (`remaining_amount = 150.000`).
3. **Hasil pada Laporan Uang Masuk (`/reports/payments`)**:
   - Laporan September (01/09/2026 – 30/09/2026): **Rp 0** (Tidak berubah / Buku September tetap).
   - Laporan Oktober (01/10/2026 – 31/10/2026): **Bertambah Rp 150.000** (Kwitansi diterbitkan tanggal 05/10/2026 dengan referensi invoice September).

---

## 2. Struktur Data Nyata di Aplikasi (Database Bersih / Pasca Migrasi)

Setelah pembersihan data dummy, kondisi data operasional nyata saat ini adalah:
- **Total Pelanggan**: 1.957 Pelanggan
- **Total Invoice Aktif**: 2.938 Tagihan
  - **Periode 2026-08 (Piutang Bulan Lalu)**: 1.469 Tagihan (`belum_dibayar`)
  - **Periode 2026-09 (Tagihan Berjalan Bulan Ini)**: 1.469 Tagihan (`belum_dibayar`)
- **Total Pembayaran**: 0 (Siap menerima pencatatan transaksi nyata)

### Contoh Nyata pada Pelanggan "Siti Juariyah":
- **Customer ID**: `4` | Kode: `RQ000006` | Nama: **Siti Juariyah**
- **Tagihan Piutang (Agustus 2026)**:
  - **Invoice ID**: `1472`
  - Nomor Invoice: `INV-202608-0003`
  - Periode: `2026-08` | Total: `Rp 150.000` | Sisa: `Rp 150.000` | Status: `belum_dibayar`
- **Tagihan Berjalan (September 2026)**:
  - **Invoice ID**: `3`
  - Nomor Invoice: `INV-202609-0003`
  - Periode: `2026-09` | Total: `Rp 150.000` | Sisa: `Rp 150.000` | Status: `belum_dibayar`

### Simulasi Eksekusi Nyata:
Jika kasir mencatat pembayaran Siti Juariyah untuk Invoice ID `1472` (Agustus) sebesar `Rp 150.000` pada hari ini di bulan September:
1. **Invoice ID `1472`** berubah menjadi `LUNAS` (`remaining_amount = 0`).
2. **Invoice ID `3`** tetap `BELUM DIBAYAR` (`remaining_amount = 150.000`).
3. **Laporan Uang Masuk (`/reports/payments`)** Bulan September mencatat uang masuk `Rp 150.000` (Cash Basis).
4. **Laporan Uang Masuk** Bulan Agustus tetap `Rp 0` (Tutup buku tidak terganggu).

---

## 3. Alur Operasional Kasir vs Kolektor

### A. Alur Kasir / Admin Meja (`/invoices` & `/customers/{id}`)
1. Kasir membuka profil pelanggan atau daftar tagihan.
2. Kasir melihat daftar seluruh tagihan pelanggan (termasuk tagihan bulan lalu yang masih menunggak).
3. Kasir memilih tombol **"Bayar"** pada baris invoice periode tertua.
4. Kasir memasukkan nominal pembayaran dan tanggal bayar (hari ini).
5. Sistem menerbitkan record pembayaran pada tanggal hari ini dan memperbarui status invoice terkait.

### B. Alur Kolektor Lapangan (`/collectors/{id}`)
1. Sistem menyajikan daftar tunggakan pelanggan yang di-assign ke kolektor.
2. Pemrosesan pembayaran massal/batch menyusun tagihan secara otomatis dengan urutan **FIFO** (dari invoice tertua).
3. Transaksi batch disimpan dalam satu transaksi atomik (`DB::transaction`) dengan kunci idempotency.

---

## 4. Status Implementasi & Keputusan FIFO (2026-09-19)

**Koreksi status:** dokumen ini bukan murni "dokumentasi fitur existing". Yang benar-benar berjalan di kode: laporan uang masuk berbasis `payment_date`, laporan tagihan berbasis `billing_period`, invoice lama tidak di-reset saat ganti bulan, `CollectorPaymentService` atomik + idempotent. Yang **belum** ada: prioritas FIFO. Di jalur kasir (§3.A) admin mengklik "Bayar" per baris invoice — tidak ada FIFO. Klaim FIFO di §3.B **belum terverifikasi** di `CollectorPaymentService` (tidak ada logika urut-tertua yang ditemukan saat dicek 2026-09-19) — jadi FIFO Kolektor adalah pekerjaan baru, bukan fitur yang sudah ada.

**Keputusan user (2026-09-19):**
- **FIFO diterapkan di Kolektor saja.** Cara kerja: uang dialokasikan ke invoice belum lunas milik pelanggan dari periode tertua (`billing_period` naik) sampai uang habis; sisa setelah semua lunas jadi saldo. Contoh 200k, tagihan Sept 150k + Okt 150k → Sept lunas, Okt sebagian 50k.
- **Admin/kasir TIDAK memakai FIFO.** Admin memilih invoice sendiri; alokasi (bulanan/piutang/cicilan/lebih bayar) dihitung server, bukan diketik (lihat `analisa-skema-alokasi-pembayaran-dan-saldo.md` §2.1–§2.5).
- Di Kolektor, FIFO berperan sebagai **isian awal** (bukan pemaksaan) — kolektor bisa mengubahnya kalau pelanggan minta lain.
- Aturan bisnis "piutang dibayar dulu" (contoh Agus, §1.C) tetap berlaku. Penegakan di sisi admin = **peringatan non-blokir** (diputuskan 2026-09-19): kalau pelanggan masih punya invoice lebih lama yang belum lunas dan admin membayar invoice yang lebih baru, tampil warning tapi pembayaran tetap boleh dilanjutkan. Detail: dokumen alokasi §2.4.

Pekerjaan ini digabung dengan ADHOC-84 (alokasi pembayaran), karena keduanya mengubah jalur pencatatan pembayaran.
