# Dokumentasi Project WHUSNET Operasional

Dokumentasi ini menjelaskan fitur yang sudah tersedia pada aplikasi WHUSNET Operasional. Struktur dibuat per fitur agar pengembangan berikutnya lebih mudah dilacak.

## Struktur Dokumentasi

| Folder / File | Isi |
| --- | --- |
| `docs/database-schema.md` | Schema database aktual berdasarkan migration Laravel. |
| `docs/flowchart-system.md` | Flowchart sistem utama dari dashboard, master, pelanggan, dan API penunjang. |
| `docs/data-pelanggan/` | Dokumentasi fitur daftar, registrasi, edit, detail, dan import pelanggan. |
| `docs/master/` | Dokumentasi master wilayah, paket layanan, dan status langganan. |
| `docs/dashboard/` | Dokumentasi dashboard operasional. |
| `docs/penunjang/` | Dokumentasi API dependent dropdown dan import pelanggan. |

## Fitur Utama

1. Dashboard operasional.
2. Data Pelanggan:
   - Daftar pelanggan.
   - Registrasi pelanggan.
   - Detail pelanggan 12 tab operasional.
   - Edit pelanggan.
   - Import batch pelanggan.
3. Master:
   - Master wilayah.
   - Master paket layanan.
   - Master status langganan.
4. Penunjang:
   - API kota ke kecamatan.
   - API kecamatan ke desa.
   - Seeder data master.

## Komponen Kode Terkait

| Area | File utama |
| --- | --- |
| Route | `routes/web.php` |
| Dashboard | `app/Http/Controllers/DashboardController.php`, `resources/views/dashboard.blade.php` |
| Pelanggan | `app/Http/Controllers/CustomerController.php`, `app/Models/Customer.php`, `resources/views/customers/*` |
| Master Paket | `app/Http/Controllers/Master/InternetPackageController.php`, `app/Models/InternetPackage.php` |
| Master Status | `app/Http/Controllers/Master/SubscriptionStatusController.php`, `app/Models/SubscriptionStatus.php` |
| Master Wilayah | `app/Http/Controllers/Master/RegionController.php`, `app/Models/City.php`, `app/Models/District.php`, `app/Models/Village.php` |

## Catatan Scope Implementasi Saat Ini

Aplikasi sudah menyediakan fondasi operasional ISP untuk registrasi dan monitoring pelanggan. Beberapa data pada detail pelanggan seperti survey, FOP, pemasangan, aktivasi, uji layanan, dan invoice awal masih dihitung atau disusun secara simulatif dari `registration_date` dan `status`, belum disimpan sebagai tabel transaksi terpisah.

