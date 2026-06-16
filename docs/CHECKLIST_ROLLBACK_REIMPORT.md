# Checklist Rollback & Reimport Data Legacy
Sistem Billing ISP Berbasis Master Data Pelanggan

Dokumen ini menyediakan panduan operasional langkah-demi-langkah bagi administrator/engineer untuk memulihkan database (rollback) dan melakukan import ulang (reimport) apabila terdapat kegagalan migrasi data legacy.

---

## 📋 Skenario A: Rollback Total Database (Rekomendasi Utama)
Gunakan skenario ini jika migrasi data nyata mengalami kegagalan struktural, merusak relasi data master existing, atau menyebabkan inkonsistensi data sistem secara masif.

### 1. Prasyarat Sebelum Migrasi
> [!IMPORTANT]
> Selalu lakukan backup database **sebelum** mengunggah file migrasi Excel/SQL.

1. **Jalankan backup database produksi:**
   - **PostgreSQL / MySQL:**
     ```bash
     pg_dump -U username -d dbname > backup_before_migration.sql
     # atau
     mysqldump -u username -p dbname > backup_before_migration.sql
     ```
   - **SQLite (development/staging):**
     Salin file `database.sqlite` ke tempat aman:
     ```bash
     cp database/database.sqlite database/database_backup_before_migration.sqlite
     ```

### 2. Prosedur Pemulihan Total (Rollback)
1. Hentikan web server / service pembaca antrean (jika ada):
   ```bash
   php artisan down
   ```
2. Restorasi database dari file backup yang diambil tepat sebelum migrasi:
   - **PostgreSQL / MySQL:**
     ```bash
     psql -U username -d dbname < backup_before_migration.sql
     # atau
     mysql -u username -p dbname < backup_before_migration.sql
     ```
   - **SQLite:**
     ```bash
     cp database/database_backup_before_migration.sqlite database/database.sqlite
     ```
3. Bersihkan cache aplikasi Laravel:
   ```bash
   php artisan cache:clear
   ```
4. Hidupkan kembali web server:
   ```bash
   php artisan up
   ```

---

## 🛠️ Skenario B: Pembersihan Parsial Berbasis Batch
Gunakan skenario ini jika import selesai tetapi terdapat data kotor atau salah format pada batch tersebut, dan Anda ingin menghapus **hanya** data yang dimasukkan oleh batch migrasi spesifik tersebut tanpa menyentuh data lainnya.

Setiap transaksi import dicatat dalam tabel `import_batches` dan dihubungkan ke data yang di-import melalui ID Relasi Legacy unik (`old_customer_id`, `old_invoice_id`, dst.).

### 1. Identifikasi Batch
Temukan ID batch yang bermasalah melalui tabel `import_batches`:
```sql
SELECT id, batch_number, file_name, created_at, status 
FROM import_batches 
ORDER BY id DESC 
LIMIT 5;
```
*Misal didapat: `id = 14`, `batch_number = 'IMP-20260616-0001'`.*

### 2. Jalankan Query Hapus Data Terkait Batch
> [!WARNING]
> Jalankan query ini dalam **database transaction** untuk menjamin keamanan dan mencegah penghapusan parsial yang menggantung.

Jalankan perintah SQL pembersihan secara berurutan sesuai arah ketergantungan relasi (payments -> invoices -> technical_details -> services -> addresses -> customers):

```sql
BEGIN;

-- 1. Hapus bukti pembayaran (payments) legacy yang terhubung ke batch
DELETE FROM payments 
WHERE old_payment_id IS NOT NULL 
  AND invoice_id IN (
      SELECT id FROM invoices WHERE old_invoice_id IS NOT NULL AND customer_id IN (
          SELECT id FROM customers WHERE old_customer_id IS NOT NULL AND pop_id = <pop_id_batch>
      )
  );

-- 2. Hapus tagihan (invoices) legacy yang di-import
DELETE FROM invoices 
WHERE old_invoice_id IS NOT NULL 
  AND pop_id = <pop_id_batch>;

-- 3. Hapus detail teknis pelanggan (customer_technical_details) yang di-import
DELETE FROM customer_technical_details 
WHERE old_report_id IS NOT NULL;

-- 4. Hapus paket/layanan aktif pelanggan (customer_services) yang di-import
DELETE FROM customer_services 
WHERE old_request_id IS NOT NULL;

-- 5. Hapus alamat pelanggan (customer_addresses) yang di-import
DELETE FROM customer_addresses 
WHERE customer_id IN (
    SELECT id FROM customers WHERE old_customer_id IS NOT NULL
);

-- 6. Hapus master pelanggan (customers) yang di-import
DELETE FROM customers 
WHERE old_customer_id IS NOT NULL;

-- 7. Perbarui status import_batch menjadi failed
UPDATE import_batches 
SET status = 'failed' 
WHERE id = <batch_id>;

COMMIT;
```

---

## 🔄 Skenario C: Koreksi Data & Reimport
Setelah database dipulihkan atau dibersihkan dari data batch yang gagal, lakukan prosedur berikut untuk mempersiapkan reimport:

1. **Analisis Log Kesalahan:**
   Buka menu **Laporan Import** pada panel admin atau kueri tabel `import_errors` untuk mengetahui baris mana yang gagal dan penyebabnya:
   ```sql
   SELECT row_number, field_name, error_message 
   FROM import_errors 
   WHERE import_batch_id = <batch_id>;
   ```
2. **Koreksi File Sumber (Excel / SQL):**
   - Perbaiki format kolom yang salah (seperti format tanggal yang bukan `YYYY-MM-DD` atau periode tagihan yang bukan `YYYY-MM`).
   - Lengkapi field wajib untuk status "Lengkap/Siap Billing".
   - Koreksi relasi ID yang terputus (seperti `old_customer_id` di sheet `services` yang tidak cocok dengan sheet `customers`).
3. **Uji Validasi:**
   Unggah kembali file koreksi di halaman `/customers/import` untuk memvalidasi ketersediaan data wilayah, status, dan keutuhan relasi.
4. **Konfirmasi Import:**
   Setelah preview menampilkan status valid (berwarna hijau), klik **Konfirmasi Import** untuk memasukkan ulang data.
