# Master Wilayah

Master Wilayah mengelola data terstruktur terkait lokasi geografis yang dilayani ISP. Wilayah dibagi secara hierarki menjadi **Kabupaten/Kota** (City), **Kecamatan** (District), dan **Desa/Kelurahan** (Village).

## Fungsi Utama
1. Menjadi *source of truth* referensi lokasi saat registrasi pelanggan baru.
2. Memfasilitasi filter lokasi di fitur antrean, data pelanggan, dan dashboard operasional.
3. Menyediakan API dependent dropdown (berbasis AJAX) untuk UI form di sistem (misal: Pilih Kota -> otomatis memuat data Kecamatan terkait).

## CRUD (sejak 2026-10-05)

| Aksi | Route | Permission |
|---|---|---|
| Peta wilayah (tree) | `GET /master/wilayah` | `master_wilayah.view` |
| Kelola (tabel per level, cari) | `GET /master/wilayah/kelola?level=kota\|kecamatan\|desa` | `master_wilayah.view` |
| Tambah | `GET /master/wilayah/create?level=…`, `POST /master/wilayah` | `master_wilayah.create` |
| Ubah | `GET /master/wilayah/{level}/{id}/edit`, `PUT /master/wilayah/{level}/{id}` | `master_wilayah.update` |
| Hapus | `DELETE /master/wilayah/{level}/{id}` | `master_wilayah.delete` |

**Aturan hapus (keputusan user 2026-10-05):** wilayah **tidak punya status**, jadi tidak ada jalur nonaktif. Hapus **ditolak** selama wilayah masih dipakai:
- ada pelanggan atau alamat yang merujuknya (`customers`, `customer_addresses`, `fop_tasks`, tiket, dll.), atau
- masih punya wilayah anak (kecamatan di bawah kota, desa di bawah kecamatan).

Cek-nya lewat `MasterRecordRemovalService::hasDependents()` (FK introspection, sama dengan master lain). Logika di `RegionMasterService`.

**Aturan lain:**
- Nama unik di dalam induknya (kota: global; kecamatan: per kota; desa: per kecamatan).
- Induk (kota/kecamatan) **dikunci saat edit**. Memindahkannya mengubah arti alamat pelanggan yang sudah tercatat.

**Provinsi belum jadi level tersendiri.** Keputusan 2026-10-05 (menunggu konfirmasi user): provinsi tetap kolom string bebas `customer_addresses.province` (nilai default `'Jawa Timur'`, di-hardcode di `CustomerController`). Alasan: seluruh operasi ada di Jawa Timur; level provinsi butuh tabel baru, FK, migrasi kolom, dan perubahan import legacy. Kalau nanti ekspansi lintas provinsi, tambahkan `province_id` di `cities`.

## File Terkait
- **Controller**: `app/Http/Controllers/Master/RegionController.php` (peta tree), `app/Http/Controllers/Master/RegionMasterController.php` (CRUD)
- **Service**: `app/Services/RegionMasterService.php`
- **Model**: `app/Models/City.php`, `app/Models/District.php`, `app/Models/Village.php`
- **View**: `resources/views/master/wilayah.blade.php` (peta), `resources/views/master/wilayah-kelola.blade.php`, `resources/views/master/wilayah-form.blade.php`
- **Route**: `routes/web.php` (grup `/master/wilayah`, dan API wilayah lokal)
- **Test**: `tests/Feature/RegionMasterCrudTest.php`
