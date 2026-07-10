> **Arsip.** Dokumen analisa/rencana historis — sebagian rekomendasi sudah diimplementasi, sebagian belum. Lihat [../README.md](../README.md) untuk dokumentasi kondisi kode terkini.

# Analisa UI/UX: Memaksimalkan Admin Menganalisa & Melakukan Pembayaran

Lanjutan dari `ANALISA_BILLING_PEMBAYARAN_FINAL.md` — fokus ke desain antarmuka per layar supaya admin bisa menganalisa kondisi tagihan/saldo pelanggan dan memproses pembayaran secepat mungkin, berdasarkan studi kasus yang sama (awal/bulanan/reaktivasi, piutang, kembalian ditabung, cicilan, bayar instan, bayar massal per kolektor, rekap harian/bulanan).

## Prinsip Desain

1. **Satu warna = satu makna, konsisten di semua layar.** Sudah ada oranye (Awal/Reaktivasi) dan biru (Bulanan). Tambah warna baru: **merah/amber pekat** = Piutang (beda dari amber biasa "belum dibayar", supaya "telat" terlihat beda dari "baru terbit"), **hijau tua** = Saldo tersedia.
2. **Info penting harus terlihat tanpa klik** — baru butuh detail baru klik. Prinsip yang sama dipakai untuk stat tile dan badge yang sudah dibangun sebelumnya.
3. **Konfirmasi eksplisit untuk aksi finansial tidak lazim** (kelebihan bayar, pakai saldo) — jangan otomatis, tapi jangan juga berlapis-lapis klik. Satu modal, satu keputusan jelas.

## Per Layar

### 1. `/invoices` — Pusat Kerja Harian Admin

**Sudah ada**: stat tile (nunggak Awal vs Bulanan), kolom invoice_type, checkbox bulk-pay, tombol Bayar per baris.

**Tambahan dari gap `ANALISA_BILLING_PEMBAYARAN_FINAL.md`**:
- **Badge "Piutang"** — badge merah kecil di baris invoice yang `billing_period` < bulan berjalan. Ditempel di sebelah kolom Periode (bukan kolom baru, biar tabel tidak makin lebar) — teks singkat "TELAT Xd" (X = hari sejak due_date lewat).
- **Stat tile ke-3**: "Total Piutang (Lewat Tempo)" — beda dari tile "nunggak" yang sudah ada (itu total belum-bayar apa adanya; ini spesifik yang sudah lewat due_date). Ditaruh sejajar dengan 2 tile yang sudah ada.
- **Floating bar bulk-pay** — di-extend: tiap baris tercentang punya input nominal kecil di sampingnya (default = sisa tagihan, bisa diubah kalau kolektor setor kurang/lebih), plus dropdown **Kolektor** di floating bar (satu pilihan berlaku untuk seluruh batch tercentang).

### 2. Customer Detail — Tab Tagihan (Sudah Ada Split Awal/Bulanan)

**Tambahan**:
- **Kartu "Saldo Tersedia"** di atas tabel invoice (di bawah Rincian Biaya, sebelum tabel) — hanya muncul kalau saldo > 0. Isi: nominal saldo + tombol kecil "Riwayat Saldo" (buka ledger, lihat §6).
- **Badge Piutang** sama seperti di `/invoices`, tapi di sini boleh lebih menonjol ("TERLAMBAT 12 HARI") karena admin sedang fokus ke satu pelanggan.
- Tombol "Bayar" — kalau invoice yang diklik adalah Piutang DAN pelanggan punya saldo, modal Bayar Cepat langsung menawarkan opsi "Pakai Saldo Rp X" sebelum minta input manual (lihat §3).

### 3. Modal Bayar Cepat — Extend Sadar-Saldo

Modal yang sudah ada (nominal, tanggal, metode, catatan) ditambah 2 elemen kondisional:

- **Kalau customer punya saldo** → baris info di atas form: "Saldo tersedia: Rp35.000" + checkbox "Pakai saldo ini duluan" — kalau dicentang, nominal tunai yang perlu dibayar otomatis berkurang, tapi tetap ditampilkan dua angka terpisah (dari saldo, dari tunai) — jangan digabung jadi satu angka, supaya rekonsiliasi tetap jelas.
- **Kalau admin input nominal > sisa tagihan** → sistem tidak menolak seperti sekarang, muncul baris baru: *"Kelebihan Rp35.000 — [ ] Tabung sebagai saldo pelanggan"*. Checkbox wajib dicentang eksplisit; kalau tidak dicentang, submit ditolak dengan pesan jelas ("nominal melebihi tagihan, centang opsi tabung atau kurangi nominal") — jangan silent-clamp ke nilai maksimum.

### 4. Floating Bar Bulk-Pay — Extend Kolektor + Custom Amount

Sudah ada: checkbox + tombol "Bayar Massal (Lunas Penuh)". Perubahan:
- Nama tombol jadi netral: **"Proses Pembayaran (X Data)"** — karena sekarang tidak selalu "lunas penuh" (nominal bisa dikustom per baris).
- Tambah dropdown **Kolektor** di floating bar (sekali pilih, berlaku ke seluruh batch tercentang) — jawaban langsung untuk kebutuhan "bayar massal berdasarkan nama kolektor".
- Baris invoice yang tercentang mendapat input nominal kecil di sebelah checkbox (default sisa tagihan, bisa diedit). Kalau dikosongkan/diisi 0, baris tersebut otomatis dilewati (skip, bukan error).

### 5. Halaman Report (`reports/invoices`, `reports/payments`)

- Tombol preset **"Hari Ini" | "Minggu Ini" | "Bulan Ini"** di atas filter tanggal manual (melengkapi, bukan menggantikan).
- Kalau kolektor sudah ada (Gap 2 di dokumen FINAL): tambah filter dropdown Kolektor + kolom Kolektor di tabel hasil, supaya bisa direkap "berapa yang disetor kolektor X bulan ini".
- Tambah kartu ringkasan di atas tabel report (bukan cuma tabel mentah): total nominal, jumlah transaksi, breakdown cash vs transfer — supaya admin tidak perlu scroll dan menjumlah manual dari tabel panjang.

### 6. Baru: Riwayat Saldo Pelanggan (Ledger View)

Belum ada layarnya sama sekali (fitur saldo sendiri belum dibangun — lihat Gap 1 di `ANALISA_BILLING_PEMBAYARAN_FINAL.md`). Dibuka dari kartu "Saldo Tersedia" di Detail Pelanggan (§2) — tabel sederhana: tanggal, tipe (Masuk/Dipakai/Refund), nominal, keterangan (invoice/payment terkait), saldo berjalan setelah baris tersebut. Read-only, tidak ada aksi edit/hapus (sesuai prinsip ledger append-only) — murni transparansi, supaya admin/pelanggan bisa mengecek kapan saja kenapa saldonya sekian.

## Ringkasan Prioritas

| Prioritas | Komponen | Effort |
|---|---|---|
| 1 | Badge Piutang (list + detail) | Kecil |
| 2 | Kartu Saldo + Riwayat Saldo (baru, menunggu backend Gap 1) | Sedang-Besar |
| 3 | Extend modal Bayar Cepat (checkbox tabung/pakai saldo) | Sedang |
| 4 | Dropdown Kolektor + nominal custom di Bulk Pay | Sedang |
| 5 | Preset tanggal + kartu ringkasan di report | Kecil |
