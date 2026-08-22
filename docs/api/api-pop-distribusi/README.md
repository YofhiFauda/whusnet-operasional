# API Baru — Topologi Jaringan & Konfirmasi Assignment

**Status: rancangan, belum ada kode.** Belum masuk fase implementasi — beberapa
keputusan pemilik produk masih diperlukan sebelum ditulis (lihat
`rencana-implementasi.md`).

## Ringkasan

Dua endpoint baru, arah **masuk** (Website B → Whusnet) — kebalikan dari `api-webhook-pemasangan`.
Muncul dari kebutuhan nyata: setelah Website B menerima `installation.activated`
(`api-webhook-pemasangan`), mereka perlu **mengonfirmasi balik** Mini POP dan Distribusi pelanggan ke
Whusnet — dan di momen yang sama, mengisi ulang kredensial jaringan (username/password
PPPoE, IP address) hasil provisioning mereka. Untuk konfirmasi Mini POP/Distribusi,
mereka perlu tahu lebih dulu kode apa saja yang valid.

| | |
|---|---|
| Arah | **Masuk** — Website B yang memulai koneksi ke Whusnet |
| Endpoint #1 | `GET /api/v1/pop-distribusi` — baca referensi Mini POP + Distribusi |
| Endpoint #2 | `POST /api/v1/installations/network-assignment` — konfirmasi assignment (`mini_pop_id`/`distribution_id`) **+ opsional** kredensial jaringan (`pppoe_username`/`pppoe_password`/`ip_address`) |
| Auth | Token bearer, **terpisah** untuk baca vs tulis |
| Data disentuh | `pops` (baca), `distributions` (baca), `customers.mini_pop_id`/`distribution_id`/`cid` (tulis), `customer_devices.pppoe_username`/`pppoe_password`/`ip_address` (tulis, opsional) — **tidak ada tabel baru** |

**Kenapa ini bukan bagian dari `api-webhook-pemasangan`.** Arahnya kebalik total (masuk, bukan
keluar), dan efeknya beda kelas risiko — endpoint #2 menulis `cid` pelanggan,
identitas yang dipakai di seluruh sistem (billing, dokumen legal). Menyatukannya ke
`api-webhook-pemasangan` akan mencampur dua model keamanan yang seharusnya terpisah.

## Berkas di folder ini

| Berkas | Isi |
|---|---|
| `business-logic.md` | Kedua endpoint: kontrak, validasi, reuse dari `CustomerNetworkAssignmentController` |
| `database-schema.md` | Kenapa tidak ada tabel baru, tabel yang dibaca/ditulis |
| `keputusan.md` | Kenapa dua endpoint (bukan digabung jadi satu), alternatif yang ditolak (polling, endpoint tunggal, dst) |
| `rencana-implementasi.md` | Pertanyaan yang masih terbuka sebelum bisa masuk fase resmi |
