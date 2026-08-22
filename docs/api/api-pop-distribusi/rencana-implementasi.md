# Rencana Implementasi — API Baru: Topologi Jaringan & Konfirmasi Assignment

**Status: belum resmi.** Tidak dikerjakan sebelum pertanyaan di bawah dijawab
pemilik produk — beda dari `api-webhook-pemasangan` Fase 1 yang jelas sejak awal, modul ini baru
muncul dari diskusi dan belum dites arahnya lewat implementasi nyata.

## Prasyarat teknis

Sama dengan `api-portal-pelanggan` Fase 0 — belum ada satupun di repo:

- `routes/api.php` — belum ada sama sekali (`api-webhook-pemasangan` tidak pernah butuh, murni
  outbound).
- Middleware autentikasi token bearer (beda dari middleware staf/session yang ada
  sekarang, dan beda lagi dari HMAC signature `api-webhook-pemasangan`).
- `withExceptions()` diisi biar error `/api/*` balas JSON.

Kalau `api-portal-pelanggan` Fase 0 sudah dikerjakan lebih dulu, sebagian pondasi ini bisa dipakai
bareng — tapi jangan berasumsi keduanya identik: `api-portal-pelanggan` pakai client secret +
bearer token pelanggan (dua lapis, atas nama satu pelanggan), modul ini pakai bearer
token tetap per-arah (baca/tulis), tidak ada konsep "pelanggan yang login".

## Pertanyaan yang harus dijawab sebelum masuk fase resmi

| # | Pertanyaan | Kenapa tidak ditebak |
|---|---|---|
| 1 | Validasi gagal (Mini POP/Distribusi tidak cocok) → langsung ditolak (default rancangan), atau tetap butuh approval staf sebelum tersimpan? | Nyentuh proses kerja NOC/admin yang mengelola Mini POP/Distribusi sekarang — mengubahnya jadi otomatis penuh adalah keputusan operasional, bukan cuma teknis |
| 2 | Endpoint #2 dipanggil otomatis oleh Website B segera setelah mereka terima `installation.activated`, atau proses terpisah (staf mereka assign manual, baru API dipanggil belakangan)? | Menentukan apakah perlu SLA/timeout expektasi di sisi Whusnet, atau baris `customers.mini_pop_id` boleh kosong lama tanpa dianggap anomali |
| 3 | Kalau Website B kirim kode Mini POP/Distribusi yang salah berkali-kali (422 berulang), perlu alert ke staf Whusnet, atau cukup tercatat di `audit_logs`/log aplikasi? | Endpoint ini permukaan masuk baru — pola kegagalan berulang bisa berarti kesalahan integrasi atau percobaan yang tidak sah |
| 4 | Endpoint #1 (baca topologi) perlu rate limit berapa? Ini referensi jarang berubah, jadi limiter longgar cukup — tapi belum ada angka disepakati | Beda karakter dari endpoint kredensial `api-portal-pelanggan` yang butuh limiter ketat |
| 5 | `customer_devices.pppoe_password` dienkripsi (`encrypted` cast) sekarang, sebagai bagian pekerjaan ini — atau tetap plaintext konsisten dengan jalur staf yang sudah ada? | Perubahan ini berlaku ke **seluruh** penulis kolom itu (termasuk wizard teknisi), bukan cuma endpoint baru — di luar cakupan kalau tidak diputuskan eksplisit. Lihat `keputusan.md` §9 |
| 6 | Kredensial PPPoE/IP selalu datang **bareng** konfirmasi Mini POP/Distribusi (satu request), atau kadang menyusul terpisah? | Menentukan apakah endpoint #2 cukup satu, atau perlu dipecah jadi endpoint ketiga nanti. Lihat `keputusan.md` §8 |

## Setelah pertanyaan di atas dijawab

Urutan kerja yang disarankan:

1. `routes/api.php` + pondasi (`withExceptions`, middleware token) — kalau belum
   dikerjakan lewat `api-portal-pelanggan` Fase 0.
2. Endpoint #1 (`GET /api/v1/pop-distribusi`) — baca-saja, risiko rendah, bisa
   dikerjakan lebih dulu dan independen dari jawaban pertanyaan #1 di atas.
3. Endpoint #2 (`POST /api/v1/installations/network-assignment`) — setelah
   pertanyaan #1-3 terjawab, karena bentuk responsnya (langsung eksekusi vs
   antre approval) menentukan struktur kode, bukan detail yang bisa ditambal
   belakangan.

## Rencana test (kerangka awal, disesuaikan setelah pertanyaan terjawab)

| Nama | Menguji |
|---|---|
| `NetworkTopologyReadReturnsFullHierarchyTest` | Endpoint #1 balikin semua POP/Mini POP/Distribusi, bukan cuma yang relevan ke satu pelanggan |
| `NetworkTopologyReadRequiresTokenTest` | Tanpa/salah token → 401 |
| `NetworkAssignmentValidatesMiniPopBelongsToCustomerPopTest` | Mini POP yang bukan anak Cabang POP pelanggan → 422, tidak ada yang tersimpan |
| `NetworkAssignmentValidatesDistributionBelongsToMiniPopTest` | Distribusi yang bukan anak Mini POP yang dipilih → 422 |
| `NetworkAssignmentRejectedForBlockedStatusTest` | Pelanggan masih di `BLOCKED_STATUSES` → ditolak |
| `NetworkAssignmentRegeneratesCidWhenActiveTest` | Pelanggan `active`/`suspended` → `cid` diregenerate ikut Mini POP/Distribusi baru |
| `NetworkAssignmentDoesNotRegenerateCidWhenNotActiveTest` | Pelanggan belum `active` → `cid` tidak disentuh |
| `NetworkAssignmentWritesAuditLogWithNullUserTest` | `audit_logs.user_id = null`, `user_agent` menandai sumber API, `ip_address` tercatat |
| `NetworkAssignmentUsesIdempotencyKeyNotCustomerIdTest` | `customer_id` yang disuntikkan di body diabaikan total — larangan keras lintas-API |
| `NetworkAssignmentReadTokenCannotWriteTest` | Token baca topologi ditolak kalau dipakai memanggil endpoint tulis, dan sebaliknya |
| `NetworkAssignmentUpsertsCustomerDeviceCredentialsTest` | `perangkat.pppoe_username`/`pppoe_password`/`ip_address` terisi ke `customer_devices` |
| `NetworkAssignmentPartialPerangkatDoesNotClearOtherFieldsTest` | Kirim cuma `pppoe_username` → `ip_address` yang sudah tersimpan sebelumnya (dari input teknisi) tidak ikut terhapus |
| `NetworkAssignmentOverwritesTechnicianEnteredCredentialsTest` | Teknisi sudah isi `ip_address` manual di wizard → Website B kirim nilai beda → nilai Website B yang menang |
| `NetworkAssignmentPppoePasswordNeverInAuditLogTest` | `audit_logs.new_values`/`old_values` tidak memuat `pppoe_password` mentah — penjaga langsung atas aturan §"Keamanan kredensial jaringan" |
| `NetworkAssignmentPppoePasswordNotInResponseTest` | Respons sukses tidak mengembalikan `pppoe_password` yang baru saja dikirim |
| `NetworkAssignmentPerangkatIsOptionalTest` | Body tanpa `perangkat` sama sekali → `mini_pop_id`/`distribution_id` tetap tersimpan, `customer_devices` tidak disentuh |
