> **Arsip.** Dokumen analisa/rencana historis — sebagian rekomendasi sudah diimplementasi, sebagian belum. Lihat [../README.md](../README.md) untuk dokumentasi kondisi kode terkini.

# Analisis Sistem Pembayaran & Billing ISP Lanjutan (Advanced Billing & Payment System)

Dokumen ini menyajikan analisis mendalam dan rancangan solusi teknis untuk mengakomodasi seluruh kebutuhan operasional kasir/admin keuangan pada Website Billing ISP, khususnya untuk penanganan pembayaran awal/registrasi, bulanan rutin, reaktivasi, cicilan (prorate), piutang, tabungan kembalian (deposit), pembayaran instan (Quick Pay), setoran kolektor (pembayaran masal), serta rekapitulasi harian dan bulanan.

---

## 1. Analisis Alur Logika Bisnis & Kasus Finansial Khusus

Sistem billing internal kita menempatkan pelanggan (`customers`) sebagai pusat data. Seluruh tagihan (`invoices`) dan pembayaran (`payments`) harus mengacu pada satu pelanggan dan dikaitkan dengan POP/Cabang untuk pembatasan data yang aman.

Berikut adalah pemecahan logika bisnis untuk setiap skenario transaksi keuangan:

### A. Jenis Tagihan & Pembayaran
1. **Pembayaran Awal / Registrasi (`awal`)**:
   - **Deskripsi**: Tagihan pertama kali saat pelanggan baru aktif.
   - **Komponen**: Biaya pemasangan (PSB) + biaya non-standar (jika ada) + biaya prorate bulan pertama.
   - **Aturan**: Harus dilunasi sebelum status pelanggan dapat bertransisi ke aktif (siap billing rutin).
2. **Pembayaran Bulanan Rutin (`bulanan`)**:
   - **Deskripsi**: Tagihan bulanan rutin berdasarkan paket internet aktif pelanggan.
   - **Komponen**: Harga paket flat (`monthly_price`) - diskon + PPN.
   - **Aturan**: Biaya di luar standar (`other_fee`) tidak boleh masuk ke tagihan bulanan rutin.
3. **Pembayaran Reaktivasi (`reaktivasi`)**:
   - **Deskripsi**: Tagihan tambahan/denda reaktivasi jika status pelanggan diputus sementara (`isolir`/`nonaktif`) akibat menunggak dan ingin diaktifkan kembali.

### B. Cicilan & Pembayaran Sebagian (Prorate / Installment)
- Jika pelanggan membayar kurang dari sisa tagihan yang harus dibayar (`remaining_amount`), invoice tidak ditandai sebagai lunas, melainkan ditagih sebagian:
  - Jumlah bayar terakumulasi di `paid_amount`.
  - Sisa tagihan ter-update di `remaining_amount` (`total_amount` - `paid_amount`).
  - Status invoice diubah menjadi `'sebagian'` (Partially Paid).
  - Pelanggan tetap terhitung menunggak sisa tagihan tersebut hingga lunas.

### C. Penanganan Piutang (Tunggakan Bulan Sebelumnya)
- Sistem melacak tagihan historis yang belum dilunasi (`invoice_status` in `['belum_dibayar', 'sebagian']` dan periode billing adalah bulan lalu).
- Saat kasir merekam pembayaran, kasir dapat memilih untuk menerapkan pembayaran ke invoice tunggakan tertentu secara manual, atau sistem secara otomatis mengalokasikan pembayaran ke invoice terlama terlebih dahulu (metode FIFO).

### D. Kelebihan Bayar & Tabungan Kembalian (Deposit Pelanggan)
- **Masalah**: Pelanggan membayar dengan uang bulat (misal Rp 200.000 untuk tagihan Rp 198.000) dan kembaliannya ingin "ditabung" untuk tagihan bulan depan, atau pelanggan membayar di muka untuk beberapa bulan sekaligus.
- **Solusi Teknis Ledger Deposit**:
  1. Tambahkan kolom saldo deposit (`deposit_balance`) pada profil pelanggan.
  2. Buat tabel riwayat mutasi saldo (`customer_deposits`) untuk mencatat keluar-masuk deposit.
  3. **Alur Deposit Masuk (Kelebihan Bayar)**:
     - Kasir menginput pembayaran instan nominal Rp 200.000 pada invoice Rp 198.000.
     - Invoice ditandai `lunas` (`paid_amount = 198.000`, `remaining_amount = 0`).
     - Kelebihannya (Rp 2.000) disimpan ke saldo deposit: `customers.deposit_balance += 2.000`.
     - Catat riwayat di `customer_deposits` (Mutasi IN, nilai +2.000, referensi `payment_id`, deskripsi "Kembalian bayar invoice INV-xxx ditabung").
  4. **Alur Deposit Keluar (Pemotongan Otomatis / Manual)**:
     - Saat invoice baru terbit (misal Rp 198.000), sistem mendeteksi pelanggan memiliki saldo deposit Rp 2.000.
     - Saldo deposit langsung digunakan untuk memotong tagihan baru tersebut secara otomatis:
       - `invoices.paid_amount += 2.000`
       - `invoices.remaining_amount = 196.000`
       - `invoices.invoice_status = 'sebagian'`
       - Saldo pelanggan berkurang: `customers.deposit_balance -= 2.000`.
       - Catat riwayat di `customer_deposits` (Mutasi OUT, nilai -2.000, referensi `invoice_id`, deskripsi "Pemotongan otomatis tagihan").

---

## 2. Desain Solusi UI/UX Kasir/Admin

Kasir memerlukan antarmuka yang gesit dan minim navigasi (Zero-Reload & One-Click Actions).

### A. Modal "Bayar Instan" (Quick Pay)
Alih-alih memaksa kasir masuk ke halaman pelanggan -> klik invoice -> klik input pembayaran -> isi form halaman penuh (3x load halaman), kasir dapat melunasi tagihan langsung dari baris tabel di `/invoices` atau `/customers`.

```
+--------------------------------------------------------------+
| BAYAR INSTAN - [INV-202607-0105]                             |
+--------------------------------------------------------------+
| Pelanggan : Abdul Wahab (CID: C1X4BRQ001114)                 |
| Tagihan   : Rp 198.000,00 (Bulanan Rutin - Juli 2026)        |
| Saldo Dep. : Rp 2.000,00                                     |
+--------------------------------------------------------------+
| * Jumlah Bayar   : [ 198.000,00 ]                            |
|                     [ ] Gunakan Deposit (Potong Rp 2.000)    |
| * Metode Bayar   : (o) CASH     ( ) TRANSFER                 |
| * Tanggal Bayar  : [ 2026-07-04 ]                            |
|   Catatan        : [ Sisa ditabung                          ] |
+--------------------------------------------------------------+
| [ Batal ]                                   [ Simpan Bayar ] |
+--------------------------------------------------------------+
```

**Mekanisme UX**:
1. Tombol **"Bayar Instan"** (ikon petir atau dolar) diletakkan di setiap baris tabel pada halaman `/invoices`.
2. Saat diklik, modal Alpine.js terbuka seketika tanpa refresh halaman.
3. Form otomatis terisi sisa tagihan (`remaining_amount`).
4. Jika nominal yang diketik kasir melebihi tagihan, modal akan menampilkan info: *"Kembalian Rp X.XXX akan ditabung ke saldo deposit pelanggan."*
5. Submit diproses via AJAX (Fetch API) ke endpoint pembayaran. Setelah sukses, baris tabel ter-update secara reaktif dan status tagihan langsung berubah (misal: badge abu-abu "Belum Dibayar" langsung berganti hijau "Lunas").

### B. Pembayaran Masal Berdasarkan Kolektor (Bulk Field Payment)
Dalam model operasional ISP, staf kolektor lapangan sering mengumpulkan tunai dari puluhan pelanggan di daerah tertentu, kemudian menyetorkan uang terkumpul secara kolektif kepada kasir di kantor pusat/cabang.

**Mekanisme UX**:
1. Buat menu/halaman baru `/payments/bulk-collector`.
2. Admin memilih **Nama Kolektor** (dropdown berisi daftar sales/teknisi/kolektor) dan **Periode Tagihan**.
3. Sistem memunculkan tabel berisi seluruh tagihan aktif (unpaid) dari pelanggan yang ditugaskan ke kolektor tersebut (POP/Wilayah yang sama).
4. Setiap baris memiliki checkbox dan input field **"Nominal Diterima"** (default auto-fill sisa tagihan berjalan).
5. Admin dapat mencentang seluruh pelanggan yang pembayarannya disetorkan oleh kolektor, melakukan penyesuaian nominal jika ada yang mencicil atau membayar lebih, lalu mengklik **"Proses Pembayaran Masal (X Data)"**.
6. Sistem memproses transaksi bulk ini secara aman di database dalam satu database transaction block.

### C. Dashboard Rekapitulasi Keuangan (Daily & Monthly Recap Tiles)
Di bagian atas halaman utama keuangan / invoices, dipasang panel statistik ringkasan yang selalu up-to-date untuk memantau arus kas:

*   **Rekap Hari Ini (Daily)**:
    - **Total Tagihan Baru**: Menampilkan rupiah tagihan yang terbit hari ini.
    - **Total Setoran Masuk**: Total uang riil terkumpul hari ini.
    - **Pemisahan Metode**:
        - *Tunai (Cash)*: Rp XX.XXX
        - *Transfer*: Rp YY.YYY
    - **Piutang Hari Ini**: Selisih tagihan hari ini yang belum terbayar.
*   **Rekap Bulan Ini (Monthly)**:
    - **Total Tagihan Periode**: Jumlah tagihan yang beredar di bulan berjalan.
    - **Total Penerimaan**: Akumulasi setoran masuk sepanjang bulan ini.
    - **Total Piutang Berjalan (Outstanding)**: Sisa tagihan bulan ini yang belum lunas.

---

## 3. Usulan Perubahan Skema Database (Database Concept & Schema Design)

Untuk mendukung alur keuangan baru di atas, berikut usulan modifikasi skema database. Kita tetap menggunakan tabel-tabel MVP yang ada, dengan penambahan field pendukung serta satu tabel log mutasi saldo deposit pelanggan.

### A. Tabel `customers` (Penambahan Field)
```sql
ALTER TABLE `customers` 
ADD COLUMN `deposit_balance` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 AFTER `registration_date`,
ADD COLUMN `collector_id` BIGINT UNSIGNED NULL AFTER `pop_id`,
ADD CONSTRAINT `fk_customers_collector` FOREIGN KEY (`collector_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;
```
- `deposit_balance`: Menyimpan saldo berjalan milik pelanggan.
- `collector_id`: Menghubungkan pelanggan dengan kolektor lapangan (user internal) penanggung jawab penagihan wilayah tersebut.

### B. Tabel Baru `customer_deposits` (Ledger Transaksi Deposit)
Tabel ini berfungsi sebagai jurnal mutasi saldo tabungan pelanggan demi kepatuhan audit keuangan (audit trail).
```sql
CREATE TABLE `customer_deposits` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(12, 2) NOT NULL, -- Positif untuk deposit masuk (in), negatif untuk deposit keluar/digunakan (out)
  `type` ENUM('in', 'out') NOT NULL,
  `invoice_id` BIGINT UNSIGNED NULL, -- Terisi jika tipe 'out' untuk membayar invoice tertentu
  `payment_id` BIGINT UNSIGNED NULL, -- Terisi jika tipe 'in' yang bersumber dari kelebihan bayar transaksi
  `note` VARCHAR(255) NULL,          -- Keterangan transaksi (misal: "Sisa kembalian bayar tagihan", "Potong tagihan bulanan")
  `created_by` BIGINT UNSIGNED NOT NULL, -- Admin yang memproses transaksi
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_deposits_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_deposits_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_deposits_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_deposits_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### C. Tabel `invoices` (Penambahan Field)
```sql
ALTER TABLE `invoices`
ADD COLUMN `invoice_type` ENUM('awal', 'bulanan', 'reaktivasi') NOT NULL DEFAULT 'bulanan' AFTER `invoice_number`,
ADD COLUMN `deposit_applied` DECIMAL(12, 2) NULL DEFAULT 0.00 AFTER `remaining_amount`;
```
- `invoice_type`: Menegaskan jenis invoice secara eksplisit (awal/bulanan/reaktivasi) agar tidak nebak otomatis berdasarkan nominal lagi.
- `deposit_applied`: Menyimpan jumlah nominal saldo deposit yang dipotong untuk membayar sebagian/seluruh invoice ini.

---

## 4. Peluang Risiko & Langkah Mitigasi

1. **Risiko Selisih Saldo Deposit**:
   - *Penyebab*: Kesalahan hitung pembulatan desimal atau double-submit payment yang memicu mutasi deposit ganda.
   - *Mitigasi*: Seluruh mutasi saldo deposit wajib dilakukan dalam satu `DB::transaction`. Nilai saldo berjalan `customers.deposit_balance` harus selalu diverifikasi ulang dengan melakukan `sum('amount')` dari tabel `customer_deposits` secara terjadwal / berkala.
2. **Risiko Overlapping Pembayaran Masal (Bulk)**:
   - *Penyebab*: Admin menekan tombol proses massal berkali-kali karena koneksi lambat (double-submit).
   - *Mitigasi*: Implementasikan penonaktifan tombol submit (loading state) pada UI/UX, dan tambahkan pengaman tingkat DB berupa pengecekan status invoice ter-lock (`lockForUpdate()`) sebelum memproses record pembayaran.
3. **Risiko Kebocoran Data Scope Cabang**:
   - *Penyebab*: Kasir cabang tertentu memproses bulk payment kolektor dari wilayah cabang lain.
   - *Mitigasi*: Terapkan filter scope POP ketat di level query controller. Kasir dengan scope POP terbatas hanya diizinkan melihat tagihan pelanggan di wilayah POP yang di-assign padanya (`user_pops`).

---

## 5. Rencana Tahapan Eksekusi (Implementation Plan)

Jika rancangan analisis ini disetujui, berikut adalah langkah-langkah implementasi terstruktur:

1. **Database Migration**: Membuat migrasi penambahan kolom deposit/kolektor pada `customers`, `invoices`, dan pembuatan tabel `customer_deposits`.
2. **Back-end Logic Layer**:
   - Membuat `DepositService` untuk mengelola penambahan (`credit`) dan pengurangan (`debit`) saldo deposit pelanggan.
   - Menyesuaikan `PaymentController@store` agar mendeteksi overpayment dan memindahkan kelebihan bayar ke `DepositService`.
   - Mengembangkan scheduled job / command untuk generate tagihan bulanan otomatis yang memotong saldo deposit secara otomatis jika pelanggan memiliki saldo deposit berjalan.
3. **Quick Pay Endpoint**: Membuat endpoint khusus AJAX untuk merekam pembayaran instan yang memvalidasi sisa tagihan, metode bayar, dan kelebihan uang kembalian.
4. **UI/UX Development**:
   - Mengembangkan Modal "Bayar Instan" menggunakan Alpine.js di halaman `/invoices` dan `/customers`.
   - Memodifikasi view tab tagihan di detail pelanggan agar menampilkan jenis tagihan (`Awal` / `Bulanan` / `Reaktivasi`) dengan visual yang bersih.
   - Membuat halaman `/payments/bulk-collector` untuk penagihan kolektif penanggung jawab kolektor.
5. **Dashboard Reporting**: Menambahkan rekap harian & bulanan reaktif di dasbor keuangan `/invoices` untuk mempermudah monitoring harian.
