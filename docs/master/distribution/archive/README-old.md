# Master Distribusi

Master Distribusi mengelola data jaringan titik distribusi seperti ODC (Optical Distribution Cabinet) atau ODP (Optical Distribution Point) hingga OLT (Optical Line Terminal). Data ini sangat penting bagi tim teknisi saat proses Instalasi & Pemasangan FOP untuk mencatat port mana yang digunakan pelanggan.

## Fungsi Utama
1. Pencatatan nama dan detail infrastruktur distribusi (Router, OLT, ODC/ODP).
2. Referensi bagi form Verifikasi Pemasangan & pengisian `CustomerTechnicalDetail`.
3. Mengetahui beban dan jumlah koneksi per titik distribusi jaringan.

## File Terkait
- **Controller**: `app/Http/Controllers/Master/DistributionController.php`
- **Model**: `app/Models/Distribution.php`
- **View**: `resources/views/master/distribution/index.blade.php` (Jika tersedia UI CRUD-nya)
- **Route**: `routes/web.php` (Grup `/master/distribution`)
