# Postman — Koleksi, Environment, OpenAPI Spec

## Berkas di folder ini

| Berkas | Isi | Cara pakai di Postman |
|---|---|---|
| `Whusnet Operasional API.postman_collection.json` | Koleksi request siap-pakai (endpoint `/api/v1/*`, `/api/customer-portal/*`, + folder `Webhook Keluar (Outbound)` buat contoh payload) | **Import → File** → jadi **Collection** biasa |
| `Whusnet — Dev.postman_environment.json` | Variable environment Dev (`baseUrl`, token-token, dll) | **Import → File** → jadi **Environment**, pilih di dropdown kanan atas |
| `openapi.json` | Spec OpenAPI 3.1 lengkap, digenerate otomatis dari kode (`dedoc/scramble`) | **Import → File**, pilih **"Create a new API"** (bukan "Create a new Collection") — baru tab **API Specs** (mock server, schema validation, dll) muncul |

**Kenapa `openapi.json` dan koleksi `.postman_collection.json` dua berkas terpisah**
(bukan satu): fitur "API Specs" Postman cuma aktif kalau yang diimpor berupa
dokumen OpenAPI lewat alur **Import as API**, bukan koleksi biasa. Koleksi
yang sudah ada dipertahankan terpisah karena isinya ada yang di-custom manual
(contoh payload webhook keluar, deskripsi per-request bahasa Indonesia) —
meng-generate ulang dari OpenAPI akan menimpa itu.

## `openapi.json` itu SNAPSHOT, bukan live

Digenerate sekali via:

```bash
php artisan scramble:export --path=docs/api/postman/openapi.json
```

Cakupannya ikut `config/scramble.php` (`api_path` → `api/v1` + `api/customer-portal`
saja — bukan seluruh `routes/web.php`). **Tidak auto-update** kalau ada endpoint
baru/berubah di kode — regenerate manual tiap kali ada perubahan API yang berarti,
lalu commit ulang. Buat lihat versi paling baru tanpa commit dulu, buka langsung
`/docs/api` di aplikasi (login sebagai `owner` — di-gate `Gate::define('viewApiDocs', ...)`
di `AppServiceProvider`).
