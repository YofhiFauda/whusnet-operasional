# Modul API Eksternal

Rancangan lapisan API supaya Whusnet Operasional bisa diremote dan diintegrasikan
dengan sistem lain. Modul ini dipecah **per API**, masing-masing folder sendiri —
setiap API punya arah, model keamanan, dan siklus hidup sendiri, jadi dokumennya
tidak dicampur dalam satu berkas raksasa.

| Folder | Isi | Status |
|---|---|---|
| [`api-webhook-pemasangan/`](api-webhook-pemasangan/README.md) | **Webhook Pemasangan** — Whusnet → Website B + Telegram Eksternal, event `installation.activated` | **Sudah diimplementasikan** (2026-08-20) |
| [`api-portal-pelanggan/`](api-portal-pelanggan/README.md) | **Portal Pelanggan** — REST `/api/customer-portal/*`, dikonsumsi aplikasi portal terpisah | Rancangan, belum ada kode |
| [`api-pop-distribusi/`](api-pop-distribusi/README.md) | **Topologi Jaringan & Konfirmasi Assignment** — Website B baca referensi Mini POP/Distribusi, lalu konfirmasi assignment balik ke Whusnet | Rancangan, belum ada kode |

Tiap folder API punya pola berkas yang sama:

| Berkas | Isi |
|---|---|
| `README.md` | Ringkasan API itu — arah, event/endpoint, model keamanan |
| `business-logic.md` | Kontrak lengkap: trigger, payload, aturan bisnis |
| `database-schema.md` | Tabel & kolom, atau penegasan "tidak ada tabel baru" |
| `keputusan.md` | **Kenapa jadi begini** — alternatif yang ditolak, riwayat revisi |
| `rencana-implementasi.md` | Fase, status implementasi, rencana test |

`api-webhook-pemasangan/` tambah `flowchart.md` (diagram alur) dan `panduan-konsumen.md` (satu-satunya
berkas yang **keluar organisasi**, diserahkan ke tim Website B). `api-portal-pelanggan/` juga punya
`flowchart.md`.

Baca `keputusan.md` di folder yang relevan **sebelum** mengusulkan perubahan arah —
kemungkinan besar usulan itu sudah pernah ditimbang dan ditolak dengan alasan
tercatat.

## Hubungan dengan `docs/plan/qr-code/rancangan-qr-pelanggan-final.md` §6.6

**Baca §6.6 lebih dulu kalau Anda menyentuh `api-portal-pelanggan/`.** Bagian itu (baris 804-1060)
sudah menetapkan kontrak Portal Pelanggan, dan sebagiannya **dikonfirmasi pemilik
produk** — bukan usulan terbuka. `api-portal-pelanggan/business-logic.md` merinci dan melengkapinya,
tidak menggantikannya. Kalau ada beda, §6.6 yang menang.

## Titik berangkat: fondasi API

Fakta yang berlaku lintas folder — baca sebelum menyentuh berkas mana pun:

- `routes/api.php` **tidak ada**. `bootstrap/app.php` cuma mendaftarkan `web`,
  `commands`, `channels`, dan `health`. `api-webhook-pemasangan/` tidak butuh ini (murni outbound,
  tidak ada endpoint masuk) — tapi `api-portal-pelanggan/` dan `api-pop-distribusi/` **butuh**, dan keduanya
  belum dibangun.
- Tidak ada Sanctum maupun Passport (`composer.json`).
- `config/auth.php` cuma punya guard `web` → provider `users` → `App\Models\User`.
- `App\Http\Resources` **tidak ada**. Tidak ada satu pun API Resource di repo.
- Tidak ada rate limiter — `RateLimiter::for` nol hasil di seluruh `app/`.
- Tidak ada `config/cors.php`.
- `withExceptions()` di `bootstrap/app.php` **kosong** — error di bawah `/api/*`
  masih balik sebagai halaman HTML, bukan JSON.

Route ber-prefix `/api/` yang sudah ada di `routes/web.php` (mis. pipeline FOP,
lookup tiket) adalah **AJAX internal berauth session** untuk Blade + Alpine. Bentuk
responsnya ad-hoc, tanpa versi, tanpa envelope. Bukan pondasi yang bisa dipakai ulang
untuk konsumen luar.

## Tiga API, tiga model keamanan

| | `api-webhook-pemasangan` — Webhook Pemasangan | `api-portal-pelanggan` — Portal Pelanggan | `api-pop-distribusi` — Topologi & Assignment |
|---|---|---|---|
| Prefix | — (keluar) | `/api/customer-portal/*` | `/api/v1/*` |
| Arah | **Keluar** (Whusnet mendorong) | **Masuk** (portal menarik) | **Masuk** (Website B baca + tulis) |
| Lawan bicara | Website B + Telegram Eksternal | Portal, atas nama satu pelanggan | Website B |
| Auth | HMAC-SHA256 per-request; Telegram: bot token | Client secret portal **+** bearer token pelanggan | Token bearer (baca terpisah dari tulis) |
| Identitas | Tujuan tetap, hardcode `config/webhooks.php` + `.env` | `customer_portal_accounts.login_id` | Token bearer hardcode `.env` |

**Aturan lintas-API yang sama, satu kalimat: REST kalau ada yang bertanya, webhook
kalau ada yang terjadi.** `api-webhook-pemasangan` berat ke webhook (kejadian: tombol Aktivasi
ditekan). `api-portal-pelanggan` berat ke REST (pelanggan yang bertanya, kapan saja). `api-pop-distribusi`
campuran: baca topologi = REST (Website B bertanya "apa saja pilihannya"), konfirmasi
assignment = juga REST tapi dipicu keputusan Website B sendiri, bukan kejadian di
Whusnet.

Model keamanan berbeda ini disengaja. Untuk integrasi mesin-ke-mesin murni satu arah
(`api-webhook-pemasangan`), signature per-request tidak menaruh rahasia statis di dalam permintaan.
Portal (`api-portal-pelanggan`) butuh dua lapis karena mewakili satu pelanggan spesifik. `api-pop-distribusi`
butuh dua kredensial terpisah (baca vs tulis) karena efeknya beda kelas risiko — baca
topologi cuma expose struktur internal, tulis assignment mengubah CID pelanggan.

## Prinsip lintas-API (berlaku untuk ketiganya)

**1. Versioning.** `api-webhook-pemasangan` dan `api-pop-distribusi` memakai `/api/v1/`. `api-portal-pelanggan` memakai
`/api/customer-portal/*` sesuai §6.6.4 — prefix itu sudah jadi kontrak yang dipegang
tim portal, jangan diseragamkan demi kerapian. Sekali sebuah path dipublikasikan, ia
tidak berubah bentuk tanpa naik versi.

**2. Satu envelope, bukan tiga.** Sukses `{"data": ..., "meta": {...}}` lewat
`JsonResource`. Galat `{"message": "...", "errors": {...}}` — bentuk bawaan Laravel.
Karena `App\Http\Resources` masih kosong, pola ini dibangun dari nol; jangan menyalin
gaya ad-hoc dari controller web yang ada.

**3. Exception harus dirender JSON.** `withExceptions()` masih kosong — perlu diisi
sebelum `api-portal-pelanggan`/`api-pop-distribusi` mulai dikerjakan.

**4. Semua nominal adalah string desimal, bukan angka JSON.** `"150000.00"`, bukan
`150000`. Galat pembulatan pernah mengubah **cabang** lunas/sebagian di sistem ini,
bukan cuma tampilan. Berlaku di semua API, termasuk `paket.harga_bulanan` di payload
`api-webhook-pemasangan`.

**5. Rate limit wajib, endpoint kredensial punya limiter sendiri.** Detail angka ada
di `api-portal-pelanggan/business-logic.md` (satu-satunya yang sudah punya kredensial pelanggan
sekarang).

**6. CORS.** Cuma relevan buat `api-portal-pelanggan` (portal di domain berbeda, diakses browser).
`api-webhook-pemasangan` dan `api-pop-distribusi` server-to-server, tidak lewat browser, tidak butuh CORS.

**7. Urutan route statis dulu.** Aturan yang sudah ditandai eksplisit di
`routes/web.php` berlaku sama di `routes/api.php` begitu dibuat.

## Larangan keras lintas-API

1. **Jangan pernah menerima `customer_id` dari request di jalur portal (`api-portal-pelanggan`).**
   Pemilik data ditentukan oleh token, titik.
2. **Jangan campur POP scope dengan kepemilikan portal.**
   `EffectiveAccessService::getAllowedPopIds()` tidak dipanggil di jalur portal sama
   sekali.
3. **Dokumen/data milik pihak lain dijawab 404, bukan 403** — di seluruh API yang
   punya konsep kepemilikan (`api-portal-pelanggan`, `api-pop-distribusi`).
4. **Jangan menaruh kredensial pelanggan di tabel `customers`** (`api-portal-pelanggan`).
5. **Jangan mencampur token pelanggan dengan token staf di satu tabel** (`api-portal-pelanggan`).
6. **Payload webhook pemasangan (`api-webhook-pemasangan`) memuat PII pelanggan** — URL tujuan wajib
   HTTPS, log-nya punya kebijakan purge. Payload webhook **portal** (`api-portal-pelanggan`) justru
   tidak boleh memuat PII sama sekali.
7. **Jangan bocorkan catatan internal** ke pihak luar mana pun — `catatan_teknis`,
   `reject_reason`, `note`, nama pegawai. Daftar putih, bukan daftar hitam.
8. **`api-pop-distribusi` menulis data sensitif (CID pelanggan)** — validasi Mini POP/Distribusi
   harus reuse logic yang sudah ada di `CustomerNetworkAssignmentController`, jangan
   menulis ulang aturan validasi yang berbeda.
