# Rencana Implementasi — API Baru: Topologi Jaringan & Konfirmasi Assignment

**Status: resmi, sudah diimplementasikan** (2026-08-22, terakhir diperbarui setelah
pemecahan endpoint #2/#3 di rev 12 — keputusan.md §19). Seluruh pertanyaan di
bawah sudah dijawab pemilik produk — lihat "Keputusan resmi" untuk jawaban tiap
poin.

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

## Keputusan resmi (2026-08-22)

| # | Pertanyaan | Keputusan |
|---|---|---|
| 1 | Validasi gagal → langsung ditolak, atau butuh approval staf? | **Langsung ditolak (422), tanpa approval manual.** Validasi Mini POP/Distribusi cocok Cabang POP pelanggan adalah pemeriksaan otomatis yang setara dengan yang staf lakukan manual — gak ada nilai tambah nunggu approval buat pemeriksaan yang sudah pasti benar/salah. |
| 2 | Endpoint #2 dipanggil otomatis segera, atau proses terpisah/manual? | **Terpisah/manual.** Assignment Mini POP/Distribusi **selalu** dikonfirmasi manual oleh petugas Website B setelah mereka terima `installation.activated` — bukan otomatis oleh sistem mereka. Konsekuensi: `customers.mini_pop_id` kosong berjam-jam/berhari **normal**, bukan anomali — **tidak ada SLA/timeout alert** di sisi Whusnet untuk ini. |
| 3 | 422 berulang perlu alert ke staf Whusnet? | **Ya** — ≥5 gagal 422 beruntun dalam 10 menit → alert via `TelegramBotService` (reuse channel notif teknisi yang sudah ada). |
| 4 | Rate limit endpoint #1 & #2? | Endpoint #1 (baca): limiter longgar, **120/menit per token**. Endpoint #2 (tulis): **20/menit per token+IP** — lebih ketat karena tiap request berpotensi menulis identitas pelanggan. |
| 5 | `pppoe_password` dienkripsi sekarang? | **Tidak — tetap plaintext**, konsisten dengan jalur staf (`storePemasangan()`). Keputusan enkripsi kolom ini di luar cakupan pekerjaan endpoint baru (lihat `keputusan.md` §9), ditunda ke pekerjaan terpisah kalau nanti diputuskan. |
| 6 | Kredensial PPPoE/IP selalu bareng assignment, atau kadang menyusul? | **Independen timing-nya** dari assignment. Assignment Mini POP/Distribusi selalu manual (lihat #2); kredensial jaringan boleh ikut di request yang sama atau menyusul lewat request terpisah — **manual atau otomatis, tergantung integrasi Website B sendiri**, Whusnet tidak menentukan caranya. Endpoint #2 tetap **satu** (bukan dipecah jadi endpoint ketiga) — body minimal salah satu dari pasangan `mini_pop_code`+`distribution_code` atau `perangkat` harus ada; `perangkat`-saja valid kalau assignment sudah tersimpan sebelumnya. Lihat `business-logic.md` §"Alur nyata". |
| 7 | Retensi `webhook_outbox`? | **Minimal 90 hari** sebelum baris sumber `idempotency_key` boleh di-purge/diarsip — perlu menutup rentang assignment manual yang bisa berhari-hari (lihat #2). Kalau job retensi sekarang lebih pendek, penyesuaian ini bagian dari pekerjaan sebelum endpoint #2 live. |

## Urutan kerja

1. `routes/api.php` + pondasi (`withExceptions`, middleware token) — kalau belum
   dikerjakan lewat `api-portal-pelanggan` Fase 0.
2. Endpoint #1 (`GET /api/v1/pop-distribusi`) — baca-saja, risiko rendah, bisa
   dikerjakan lebih dulu.
3. Endpoint #2 (`POST /api/v1/installations/network-assignment`) — termasuk kolom
   `audit_logs.idempotency_key`/`request_hash`, transaction+row lock, validasi
   `mini_pop_code`+`distribution_code` wajib bareng, rate limiter, dan alert 422
   beruntun.
4. Endpoint #3 (`POST /api/v1/installations/network-device`, rev 12 — keputusan.md
   §19) — upsert `perangkat` ke `customer_devices`+`customer_technical_details`,
   berbagi token tulis dengan endpoint #2 tapi rate limiter & counter alert 422
   terpisah.

## Rencana test

**Realisasi (rev 12): tiga file test, satu per endpoint** — bukan satu file besar
seperti rencana awal, mengikuti pemecahan endpoint #2/#3 (keputusan.md §19). Nama
di bawah adalah nama method aktual di repo, bukan lagi nama class rencana awal.

### `tests/Feature/Api/PopDistribusiReadTest.php` — endpoint #1

| Test | Menguji |
|---|---|
| (baca daftar Mini POP + Distribusi) | Balikin seluruh hierarki, bukan cuma yang relevan ke satu pelanggan; tanpa/salah token → 401 |

### `tests/Feature/Api/NetworkAssignmentTest.php` — endpoint #2

| Test | Menguji |
|---|---|
| `test_tanpa_token_ditolak_401` | Tanpa/salah token → 401 |
| `test_idempotency_key_tidak_dikenal_404` | Key gak nunjuk baris `webhook_outbox` manapun → 404 |
| `test_field_wajib_tidak_dikirim_ditolak_422` | Body tanpa `mini_pop_code`/`distribution_code` → 422 (keduanya sekarang `required`, beda dari rencana awal yang opsional-bareng) |
| `test_mini_pop_code_tanpa_distribution_code_ditolak_422` | Kirim salah satu doang → 422 |
| `test_mini_pop_bukan_anak_cabang_pop_pelanggan_422` | Mini POP bukan anak Cabang POP pelanggan → 422, tidak ada yang tersimpan |
| `test_distribution_bukan_anak_mini_pop_yang_dipilih_422` | Distribusi bukan anak Mini POP yang dipilih → 422 |
| `test_status_blocked_ditolak_422` | Pelanggan masih di `BLOCKED_STATUSES` → ditolak |
| `test_assignment_sukses_menyimpan_mini_pop_dan_distribusi` | `customers.mini_pop_id`/`distribution_id` tersimpan |
| `test_response_menyertakan_mini_pop_code_dan_distribution_code` | Respons balikin kode, bukan cuma ID internal |
| `test_key_cid_tidak_ada_di_response_saat_belum_active` | Pelanggan belum `active` → key `cid` TIDAK ADA di respons (bukan `null`) |
| `test_cid_diregenerate_kalau_pelanggan_active` | Pelanggan `active`/`suspended` → `customers.cid` diregenerate, key `cid` muncul berisi nilai sama |
| `test_cid_tidak_disentuh_kalau_belum_active` | Pelanggan belum `active` → `customers.cid` tidak disentuh |
| `test_audit_log_user_id_null_dan_sumber_ditandai` | `audit_logs.user_id = null`, `user_agent` menandai sumber API |
| `test_customer_id_di_body_diabaikan` | `customer_id` yang disuntikkan di body diabaikan total — larangan keras lintas-API |
| `test_retry_key_dan_body_identik_tidak_menulis_audit_log_dobel` | `idempotency_key`+body identik dikirim dua kali → `audit_logs` cuma nambah satu baris |
| `test_rate_limit_20_per_menit` | >20x/menit dari token+IP sama → 429 |

### `tests/Feature/Api/NetworkAssignmentAlertTest.php` — endpoint #2

| Test | Menguji |
|---|---|
| `test_lima_gagal_422_beruntun_memicu_alert_telegram_sekali` | ≥5 gagal 422 beruntun dalam 10 menit → `TelegramBotService` dipanggil sekali |
| `test_sukses_memutus_rentetan_gagal_counter_reset` | Satu sukses di tengah rentetan gagal → counter reset, gak ikut kebawa ke rentetan berikutnya |

### `tests/Feature/Api/NetworkDeviceTest.php` — endpoint #3 (baru, rev 12)

| Test | Menguji |
|---|---|
| `test_tanpa_token_ditolak_401` | Tanpa/salah token → 401 |
| `test_idempotency_key_tidak_dikenal_404` | Key gak nunjuk baris `webhook_outbox` manapun → 404 |
| `test_perangkat_kosong_ditolak_422` | `perangkat` dikirim tapi semua sub-field null/kosong → 422 |
| `test_perangkat_tidak_dikirim_ditolak_422` | Body tanpa `perangkat` sama sekali → 422 (beda dari rencana awal — di endpoint #3, `perangkat` **wajib**, bukan lagi opsional seperti dulu di endpoint gabungan) |
| `test_tanpa_assignment_tersimpan_ditolak_422` | Pelanggan belum punya `mini_pop_id`/`distribution_id` (belum lewat endpoint #2) → 422 |
| `test_perangkat_upsert_ke_customer_devices` | `perangkat.pppoe_username`/`pppoe_password` terisi ke `customer_devices` |
| `test_field_olt_vlan_masuk_customer_technical_details_bukan_customer_devices` | `perangkat.olt_number`/`olt_slot`/`olt_port`/`vlan` terisi ke `customer_technical_details`, bukan `customer_devices` — termasuk penjaga `vlan` vs `customer_devices.vlan_id` gak ketuker |
| `test_perangkat_parsial_tidak_menghapus_field_lain_yang_sudah_tersimpan` | Kirim cuma `pppoe_username` → field lain yang sudah tersimpan sebelumnya tidak ikut terhapus |
| `test_perangkat_menimpa_nilai_yang_diisi_teknisi` | Teknisi sudah isi manual di wizard → Website B kirim nilai beda → nilai Website B yang menang |
| `test_pppoe_password_tidak_pernah_masuk_audit_log_mentah` | `audit_logs.new_values`/`old_values` tidak memuat `pppoe_password` mentah |
| `test_pppoe_password_tidak_dikembalikan_di_response` | Respons sukses tidak mengembalikan `pppoe_password` yang baru saja dikirim |
| `test_audit_log_user_id_null_dan_sumber_ditandai` | `audit_logs.user_id = null`, `user_agent` menandai sumber API, `action = network_device_update` (beda dari `network_assignment` di endpoint #2) |
| `test_retry_key_dan_body_identik_tidak_menulis_audit_log_dobel` | `idempotency_key`+body identik dikirim dua kali → `audit_logs` cuma nambah satu baris |
| `test_key_sama_dipakai_lintas_endpoint_diproses_sebagai_kejadian_terpisah` | `idempotency_key` sama dipakai di endpoint #2 lalu endpoint #3 → **bukan** dianggap duplikat, keduanya diproses (bukti langsung kenapa dedup di-scope ke `request_hash`, bukan cuma key — database-schema.md) |
| `test_rate_limit_20_per_menit` | >20x/menit dari token+IP sama → 429, counter **terpisah** dari endpoint #2 |

### `tests/Feature/Api/NetworkDeviceAlertTest.php` — endpoint #3 (baru, rev 12)

| Test | Menguji |
|---|---|
| `test_lima_gagal_422_beruntun_memicu_alert_telegram_sekali` | ≥5 gagal 422 beruntun dalam 10 menit → `TelegramBotService` dipanggil |
| `test_counter_network_device_terpisah_dari_network_assignment` | 4 gagal di endpoint #2 + 4 gagal di endpoint #3 (total 8, tapi masing-masing di bawah ambang 5) → alert **tidak** terpicu di kedua sisi — buktiin namespace counter terpisah |
