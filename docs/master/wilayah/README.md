# Master Wilayah

Master Wilayah mengelola data terstruktur terkait lokasi geografis yang dilayani ISP. Wilayah dibagi secara hierarki menjadi **Kabupaten/Kota** (City), **Kecamatan** (District), dan **Desa/Kelurahan** (Village).

## Fungsi Utama
1. Menjadi *source of truth* referensi lokasi saat registrasi pelanggan baru.
2. Memfasilitasi filter lokasi di fitur antrean, data pelanggan, dan dashboard operasional.
3. Menyediakan API dependent dropdown (berbasis AJAX) untuk UI form di sistem (misal: Pilih Kota -> otomatis memuat data Kecamatan terkait).

## File Terkait
- **Controller**: `app/Http/Controllers/Master/RegionController.php`
- **Model**: `app/Models/City.php`, `app/Models/District.php`, `app/Models/Village.php`
- **View**: `resources/views/master/wilayah/index.blade.php`
- **Route**: `routes/web.php` (Grup `/master/wilayah` dan API wilayah lokal)
