# Database Schema — API 1: Webhook Pemasangan

## Ringkasan

| Objek | Jenis | Untuk |
|---|---|---|
| `webhook_outbox` | tabel, **sudah dibuat** | Antrean + jejak pengiriman. **Dipakai bareng `api-portal-pelanggan`** (event `invoice.updated`) — lihat §2 |
| `installation_provisioning_callbacks` | tabel, belum dibuat | Log hasil provisioning dari Website B (Fase 6, belum masuk implementasi) |

**Tabel `customers` tidak diubah sama sekali.**

## 1. Tujuan webhook — config, bukan tabel

**Tidak ada tabel `webhook_endpoints`.** Website B dan Telegram Eksternal
**hardcode** — satu tujuan tetap per transport, disimpan di `config/webhooks.php` +
`.env`. Alasan: cuma ada 1 konsumen tiap transport sekarang, dan tabel dinamis untuk
melayani konsumen yang belum ada adalah abstraksi sebelum dibutuhkan.

```php
// config/webhooks.php
'website_b' => [
    'url'    => env('WEBHOOK_WEBSITE_B_URL'),
    'secret' => env('WEBHOOK_WEBSITE_B_SECRET'),
],
'telegram_external' => [
    'bot_token' => env('TELEGRAM_EXTERNAL_BOT_TOKEN'),
    'chat_id'   => env('TELEGRAM_EXTERNAL_CHAT_ID'),
],
```

**`secret` tetap wajib bisa dibaca ulang (plaintext di `.env`, bukan hash).** HMAC
menuntut kedua pihak memegang rahasia yang sama, jadi kita harus bisa membacanya
kembali setiap kali menandatangani. `.env` tidak pernah masuk git.

**Kredensial Telegram Eksternal tetap terpisah dari Telegram Internal**:
`config/services.php` punya `TELEGRAM_BOT_TOKEN`/`TELEGRAM_CHAT_ID` global untuk
enam pemanggilan internal; `telegram_external` di atas adalah env var **berbeda**
(`TELEGRAM_EXTERNAL_BOT_TOKEN`/`TELEGRAM_EXTERNAL_CHAT_ID`).

**Yang sengaja tidak ada:**
- Tidak ada `pop_id` per tujuan — konsumen tunggal menerima seluruh cabang.
- Tidak ada `is_active`/`consecutive_failures` di DB — kegagalan beruntun cukup
  terlihat dari `webhook_outbox` (banyak baris `failed`) dan dialertkan manual.
- Rotasi URL/secret butuh ubah `.env` + deploy, bukan form + klik.

## 2. `webhook_outbox`

**Sudah dibuat** —
`database/migrations/2026_08_20_100000_create_webhook_outbox_table.php`,
`app/Models/WebhookOutbox.php`.

Satu tabel untuk **dua keluarga event**: `installation.*` (API 1, ke sistem
provisioning) dan `invoice.updated` (`api-portal-pelanggan/`, ke portal). Digabung karena
mekanismenya identik sampai ke angka backoff-nya; dua tabel berarti dua worker, dua
kebijakan retry, dua tempat mencari saat ada yang tidak sampai. Kalau nama
`portal_outbox` lebih disukai untuk keluarga kedua saat `api-portal-pelanggan` diimplementasikan,
itu keputusan penamaan, bukan perbedaan desain.

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | id | |
| `destination` | string(30) | Identifier tetap: `website_b` / `telegram_external` / `customer_portal` (`api-portal-pelanggan`, belum dipakai). **Bukan FK** |
| `event` | string(50) | `installation.activated` / `invoice.updated` |
| `event_id` | uuid, index | **Satu baris = satu event.** Tetap sama di semua percobaan |
| `idempotency_key` | string(100) nullable, index | Mengelompokkan event yang saling menggantikan |
| `customer_id` | FK nullable, `nullOnDelete` | Penelusuran |
| `payload` | json | Isi yang dikirim, apa adanya |
| `status` | string(15) | `pending` / `delivered` / `failed` / `skipped` |
| `attempts` | unsigned tinyint | Dinaikkan di tempat, maks 8 |
| `next_attempt_at` | timestamp nullable, index | |
| `response_status` | unsigned smallint nullable | Percobaan terakhir |
| `last_error` | text nullable | Dipotong, mis. 1 KB |
| `delivered_at` | timestamp nullable | |
| `timestamps` | | |

### Satu baris per event, bukan satu baris per percobaan

**Satu baris mewakili satu event**; `attempts` dinaikkan di tempat dan `last_error`
ditimpa. Percobaan ulang mengirim **payload yang tersimpan di baris itu**, tidak
merakit ulang dari model — kalau dirakit ulang, percobaan ke-3 bisa mengirim data
yang sudah berubah, dibuang penerima sebagai duplikat `event_id`, perubahan hilang
tanpa jejak.

`skipped` khusus transport `telegram`: penekanan Aktivasi yang tidak mengubah teks
pesan tidak dikirim, tapi barisnya tetap ditulis.

Indeks: `(status, next_attempt_at)` untuk worker mengambil pekerjaan,
`(destination, created_at)` untuk halaman riwayat, `event_id` dan
`idempotency_key` untuk penelusuran.

### Retensi

Baris `delivered` dipruning 90 hari (`app/Console/Commands/PruneWebhookOutbox.php`,
`webhook-outbox:prune`). Baris `failed` **tidak** ikut dipruning otomatis — ia
daftar rekonsiliasi "event mana yang belum sampai".

Payload `installation.*` memuat nama, desa, paket, dan perangkat pelanggan —
dibiarkan tumbuh, tabel ini jadi salinan data pelanggan kedua yang tidak pernah
diaudit siapa pun. Payload `invoice.updated` (`api-portal-pelanggan`) tidak memuat PII sama sekali,
jadi retensinya bisa lebih longgar, tapi kebijakan tunggal 90 hari dipilih supaya
tidak ada dua aturan purge di satu tabel.

## 3. `installation_provisioning_callbacks` — belum masuk fase

Untuk Callback Hasil Provisioning (`business-logic.md` bagian "Callback Hasil
Provisioning"), dicatat di sini supaya tidak dirancang ulang dari nol.

### Auth callback — juga hardcode

Tidak ada tabel token. Token bearer arah-balik cukup satu nilai tetap di `.env`
(`WEBHOOK_WEBSITE_B_CALLBACK_TOKEN`), diverifikasi `hash_equals` terhadap hash yang
dihitung di tempat. Tetap **terpisah** dari `WEBHOOK_WEBSITE_B_SECRET` (secret HMAC
arah keluar): satu membuktikan pesan **dari** kita, satu lagi membuktikan permintaan
**ke** kita berasal dari Website B yang sah.

### Kolom

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | id | |
| `destination` | string(30) | Identifier tetap, selalu `website_b` untuk saat ini |
| `webhook_outbox_id` | FK nullable | Baris `installation.activated` asal, untuk lacak balik |
| `event_id` | uuid | Dari body |
| `idempotency_key` | string(100), index | Satu aktivasi = satu hasil final |
| `status` | string(15) | `succeeded` / `failed` / `rejected` |
| `reason` | text nullable | Wajib kalau `failed` atau `rejected` |
| `provider_reference` | string nullable | ID internal Website B |
| `source_ip` | string(45) | Audit — permukaan masuk dari luar organisasi |
| `received_at` | timestamp | |
| `timestamps` | | |

**Kenapa `rejected` ikut jadi baris.** Endpoint ini permukaan masuk baru dari luar
organisasi. Percobaan yang ditolak di gerbang tetap harus bisa dijawab "kapan dan
siapa yang mencoba" — bukan cuma menghilang ke log aplikasi generik.

**Idempotensi terminal.** `idempotency_key` yang sudah punya baris `succeeded`/`failed`
tidak bisa ditimpa. Callback kedua dengan isi identik diterima diam-diam sebagai
duplikat; isi berbeda ditolak sebagai `rejected` alasan `already_finalized`.

## Tabel yang dibaca tanpa diubah

| Tabel | Dipakai untuk |
|---|---|
| `customers` | identitas, profil, payload webhook — tidak ditambah kolom apa pun |
| `pops` | payload |
| `tasks` | `task.number`/`started_at` di payload |
| `customer_devices`, `customer_technical_details` | SN & ODP webhook — rantai fallback, lihat `business-logic.md` |
| `villages`, `internet_packages` | payload webhook |
