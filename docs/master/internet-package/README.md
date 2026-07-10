# Master Paket Internet

Master Paket Internet menyimpan daftar paket layanan ISP yang ditawarkan kepada pelanggan. Modul ini digunakan saat registrasi pelanggan baru, upgrade/downgrade layanan, serta untuk perhitungan biaya tagihan internet bulanan.

## Fungsi Utama
1. Melihat daftar paket internet beserta harganya.
2. Filter paket berdasarkan kategori (Home, Bisnis, Dedicated) dan status.
3. Menjadi rujukan *source of truth* untuk `internet_package_id` di tabel pelanggan.

## File Terkait
- **Controller**: `app/Http/Controllers/Master/InternetPackageController.php`
- **Model**: `app/Models/InternetPackage.php`
- **View**: `resources/views/master/paket/index.blade.php`
- **Route**: `routes/web.php` (Grup `/master/paket`)

## Kategori Tersedia
Sesuai rancangan aplikasi, paket dibagi menjadi beberapa grup kategori utama:
1. **Paket Home Broadband** (Contoh: Net138, Net150)
2. **Paket Bisnis Broadband** (Contoh: NetSoLite75)
3. **Paket Bisnis UKM** (Contoh: NetBLite25)
4. **Paket Bisnis Dedicated** (Contoh: Dedicated100)
