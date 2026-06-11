# Fitur Penunjang

Fitur penunjang berisi proses yang mendukung fitur utama Data Pelanggan dan Master.

## Daftar Dokumentasi

| File | Isi |
| --- | --- |
| `import-pelanggan.md` | Alur validasi dan penyimpanan import pelanggan. |
| `api-wilayah.md` | API dependent dropdown kota, kecamatan, desa. |
| `database-schema.md` | Tabel yang dipakai fitur penunjang. |
| `flowchart.md` | Flowchart fitur penunjang. |

## Komponen Kode

| Area | File |
| --- | --- |
| Import pelanggan | `CustomerController@importForm`, `validateImport`, `confirmImport` |
| API wilayah | Closure route di `routes/web.php` |
| Model wilayah | `City`, `District`, `Village` |
| Model pelanggan | `Customer` |
| Model paket | `InternetPackage` |

