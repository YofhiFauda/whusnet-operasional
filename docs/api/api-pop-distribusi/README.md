# API Baru — Topologi Jaringan & Konfirmasi Assignment

**Status: resmi, sudah diimplementasikan.** Tiga endpoint, terdokumentasi via
Scramble (`/docs/api`). Riwayat keputusan pemilik produk ada di
`rencana-implementasi.md` §"Keputusan resmi" dan `keputusan.md`.

## Ringkasan

Tiga endpoint, arah **masuk** (Website B → Whusnet) — kebalikan dari `api-webhook-pemasangan`.
Muncul dari kebutuhan nyata: setelah Website B menerima `installation.activated`
(`api-webhook-pemasangan`), mereka perlu **mengonfirmasi balik** Mini POP dan Distribusi pelanggan ke
Whusnet — dan, di momen yang sama atau terpisah, mengisi ulang kredensial jaringan
(PPPoE, titik sambung OLT) hasil provisioning mereka. Untuk konfirmasi Mini
POP/Distribusi, mereka perlu tahu lebih dulu kode apa saja yang valid.

| | |
|---|---|
| Arah | **Masuk** — Website B yang memulai koneksi ke Whusnet |
| Endpoint #1 | `GET /api/v1/pop-distribusi` — baca referensi Mini POP + Distribusi |
| Endpoint #2 | `POST /api/v1/installations/network-assignment` — konfirmasi `mini_pop_code`+`distribution_code` untuk satu pelanggan |
| Endpoint #3 | `POST /api/v1/installations/network-device` — perbarui kredensial PPPoE & topologi fisik OLT (`pppoe_username`/`pppoe_password`/`olt_number`/`olt_slot`/`olt_port`/`vlan`), butuh assignment dari endpoint #2 sudah ada duluan |
| Auth | Token bearer baca (endpoint #1) TERPISAH dari token tulis (endpoint #2 & #3 BERBAGI token tulis yang sama — satu kelas risiko, keputusan.md §5) |
| Data disentuh | `pops` (baca), `distributions` (baca), `customers.mini_pop_id`/`distribution_id`/`cid` (tulis, endpoint #2), `customer_devices.pppoe_username`/`pppoe_password` (tulis, endpoint #3), `customer_technical_details.olt_number`/`olt_slot`/`olt_port`/`vlan` (tulis, endpoint #3) — **tidak ada tabel baru**, cuma 2 kolom baru di `audit_logs` (lihat `database-schema.md`) |

**Kenapa endpoint #2 & #3 dipecah (bukan satu endpoint gabungan seperti rancangan
awal).** Awalnya satu endpoint (rev 1-10) — dipecah (rev 12, keputusan.md §19) atas
keputusan pemilik produk supaya keduanya bisa berkembang independen ke depannya.
Detail lengkap analisis (termasuk kenapa versi gabungan sebenarnya sudah benar
secara teknis) ada di keputusan.md §19.

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
