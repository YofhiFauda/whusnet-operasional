# Rancangan: Fase 4 (Bersihkan Skema) + Layer Identitas `persons`

**Status: RANCANGAN. Belum ada kode. Menunggu keputusan produk di §7.**
Tanggal: 2026-07-22.

Menggabungkan dua pekerjaan yang sama-sama membongkar tabel `customers` supaya
tabel itu dibongkar **sekali**, bukan dua kali:

1. **Fase 4** dari [`ANALISA_INDEX_DATABASE.md`](./ANALISA_INDEX_DATABASE.md) —
   bersihkan skema (drop kolom zombie, satukan telepon, persempit tipe).
2. **Layer `persons`** dari
   [`analisa-identitas-pelanggan-person-uuid.md`](./analisa-identitas-pelanggan-person-uuid.md)
   — identitas pelanggan permanen (UUIDv7 + `person_id`).

Prasyarat: Fase 0–3 sudah selesai & terverifikasi (index terpasang, N+1 beres).
Pekerjaan ini **destruktif** — wajib `migrate:fresh` + import ulang legacy.
Karena DB dev 100% hasil migrasi, ini bukan jalur baru (lihat §14 dokumen index).

---

## 1. Prinsip

- **Sekali bongkar.** Semua perubahan skema `customers` masuk di satu gelombang
  migration + satu import ulang. Tidak ada bongkar susulan.
- **Tulis ke migration `create_*` asalnya** kalau memungkinkan (repo masih dev,
  lihat §14 dokumen index) — hasilnya skema yang terbaca sekali jalan, bukan
  tumpukan migration tambal.
- **Data uang tidak pernah disentuh.** Invoice/payment tidak dipindah, tidak
  di-remap. Merge person hanya mengarahkan `person_id`.
- **Backfill selamat dari import ulang.** Ini titik paling rawan — lihat §3.2.

---

## 2. Bagian A — Bersihkan Skema (Fase 4)

### 2.1 Drop `customer_status` (kolom zombie)

`customers.customer_status` cuma dipakai sebagai variabel antara untuk mengisi
`customer_services.service_status`. Nilainya duplikat `status` dan bisa
menyimpang → dua sumber kebenaran.

Titik yang harus diarahkan ulang ke `customer_services.service_status`
(hasil grep, ~15 lokasi):
`CustomerController` (423, 520, 521, 672, 809, 810, 2124, 2284, 3082, 3091, 3106),
`CustomerVerificationController` (253, 263, 297), `Customer.php` fillable (33).

**Rencana:** hitung `service_status` langsung dari `status` saat menulis
`customer_services` (mapping yang sudah ada di controller), lalu drop kolom.

### 2.2 Drop `old_account_status`

Hanya ditulis (`MigrateLegacyDataCommand:561`, `CustomerController:1561/2115`),
**tidak pernah dibaca** untuk logika. Jejak status akun legacy. Nilai rendah.
**Kandidat drop** — perlu konfirmasi apakah masih dipakai audit/laporan manual.

### 2.3 Satukan telepon `phone` → `primary_phone`

`customers` punya tiga: `phone`, `primary_phone`, `alternative_phone`.
- `phone` & `primary_phone` = duplikat konsep. Pencarian & cek duplikat harus
  menyapu dua kolom dengan `OR` → gandakan biaya, index tidak efektif.
- `alternative_phone` = kontak sekunder yang sah. **Dipertahankan.**

**Rencana:** jadikan `primary_phone` satu-satunya kolom nomor utama. Backfill
`primary_phone = COALESCE(primary_phone, phone)` saat import, drop `phone`.
Sebar ke 15 file/48 occurrence (grep) — terbanyak `CustomerController` (16),
`UserController` (9, tapi itu `User.phone`, JANGAN ikut diubah).

### 2.4 Persempit tipe kolom (sebelum index lahir ulang)

| Kolom | Sekarang | Jadi | Alasan |
|---|---|---|---|
| `customers.cid` | `varchar(150)` | `varchar(50)` | pernah di-enlarge buat legacy; CID nyata jauh lebih pendek |
| `customers.status` | `varchar(50)` | `varchar(30)` | enum terpanjang `installation_in_progress` = 24 char |
| `audit_logs.auditable_type` | `varchar(255)` | `varchar(100)` | isinya FQCN model |

Di utf8mb4, `varchar(255)` = 1020 byte/entri index. Persempit **sebelum** index
P0/P1 lahir supaya langsung berukuran benar (Fase 3 index dipasang ulang setelah
`migrate:fresh`).

### 2.5 `ip_address` duplikat

`ip_address` ada di `customers` DAN `customer_technical_details`. Tetapkan
`customer_technical_details` sebagai sumber kebenaran, drop dari `customers`.
**Perlu konfirmasi** — cek dulu apakah ada view/laporan yang baca
`customers.ip_address` langsung.

### 2.6 `clean_address` lepas dari relasi (Fase 4.4 / pilihan B)

Sekarang accessor `getCleanAddressAttribute()` membaca relasi `village`,
`district`, `city` → sumber N+1 paling menyebar (sudah ditambal eager-load di
Fase 1, tapi jebakannya masih ada untuk pemanggil baru). `customer_addresses`
sudah menyimpan `village`/`district`/`city` sebagai string. Ubah accessor supaya
pakai string itu; relasi hanya cadangan bila string kosong. Jebakan hilang
permanen.

### 2.7 JANGAN disentuh

- `old_customer_id`, `old_request_id` — baru diindex (Fase 3), dipakai
  idempotensi import DAN jadi anchor backfill persons (§3.2). **Wajib tetap ada.**
- `alternative_phone` — kontak sekunder sah.

### 2.8 Koreksi terhadap dokumen index §10

Klaim §10 bahwa `ont_sn`, `olt_code`, `odp_code`, `vlan_id` duplikat di
`customers` **SALAH untuk skema sekarang** — kolom-kolom itu tidak ada di
`customers`. Jadi tidak ada yang perlu di-drop di sana. Satu-satunya duplikat
teknis nyata = `ip_address` (§2.5).

---

## 3. Bagian B — Layer `persons`

### 3.1 Bentuk

Tabel `persons` baru + `customers.person_id` FK. **Bukan** mengubah PK
`customers` (58 tabel menunjuk `customers.id` bigint — tidak ada yang berubah).

```
persons
  id           bigint PK
  uuid         char(36) UNIQUE   ← UUIDv7 (Str::uuid7()), immutable, tak tampil UI
  legacy_key   varchar(60) UNIQUE NULL  ← anchor import ulang, lihat §3.2
  merged_into  bigint NULL FK persons.id  ← merge reversibel (§3.3)
  created_at / updated_at

customers
  + person_id  bigint NULL FK persons.id
```

**Keputusan tipe: `char(36)`, BUKAN `binary(16)`** (menyimpang dari §6.1 doc
persons). Alasan: 1.957 baris — penghematan 20 byte × index tidak terasa,
sementara `binary(16)` merusak keterbacaan di `database-query`/tinker/log/export.
Ganti ke `binary(16)` nanti kalau tembus jutaan baris.

### 3.2 Backfill — WAJIB di dalam `MigrateLegacyDataCommand`

**Ini titik paling rawan.** DB dev = hasil import legacy, dan runbook-nya
"fix migrasi → import ulang". Kalau `persons` di-backfill di command TERPISAH
setelah import:

```
import #1  → 1957 customers → backfill → 1957 persons
merge manual admin → 1830 persons
import ulang #2 → customers ID baru → person_id nunjuk ke ruang kosong
```

→ **semua kerja merge manual hilang tiap import ulang.**

**Solusi:** backfill jalan DI DALAM `MigrateLegacyDataCommand`, `firstOrCreate`
atas `persons.legacy_key = "{cabang}:{IDPENGGUNA}"`. `IDPENGGUNA` = `old_customer_id`
(sudah ada, sudah diindex). Key ke `IDPENGGUNA`, **bukan** `customer_code` —
`customer_code` di-auto-generate saat bentrok (`MigrateLegacyDataCommand:539`),
jadi tidak deterministik antar-run.

Backfill awal 1:1 — tiap customer satu person. Nol perubahan perilaku hari
pertama. (Sadari: duplikat legacy seperti Mistiani `PE000001`+`PE000899` dapat
DUA person sampai halaman merge jalan — itu memang niatnya, jangan berhenti di
sini lalu anggap selesai.)

### 3.3 Merge

Merge = arahkan `person_id` dua baris ke person yang sama + set
`persons.merged_into`. Satu kolom, **reversibel** (relasi, bukan penghapusan).
Data uang tidak disentuh. Riwayat mengikuti person lewat join.

**Merge WAJIB manual** — sudah dibuktikan di §5 doc persons bahwa NIK/HP/nama
tidak bisa dipercaya untuk merge otomatis (NIK sampah, HP satu keluarga, nama
beda ejaan). Sistem hanya *mengusulkan* kandidat.

Halaman merge (bertahap, tiap langkah berdiri sendiri):
1. `persons` + `person_id` + backfill 1:1 (nol perubahan perilaku)
2. Unique index `cid` + tabel riwayat CID — **tidak bergantung UUID, bisa duluan**
3. Halaman kandidat merge: mulai 21 grup NIK-nama-identik → 93 grup HP "perlu tinjau"
4. Pencarian person saat registrasi ("mungkin orang yang sama?")
5. Pindah laporan riwayat dari level `customer` ke `person`

---

## 4. Urutan Eksekusi (dependency)

```
A. Skema (satu migration wave, migrate:fresh)
   A1 persempit tipe (cid, status, auditable_type)   ← sebelum index lahir ulang
   A2 drop customer_status  (arahkan ke service_status dulu)
   A3 satukan phone → primary_phone
   A4 drop old_account_status, ip_address (setelah konfirmasi §7)
   A5 tambah persons + customers.person_id
   A6 clean_address pilihan B
B. MigrateLegacyDataCommand
   B1 hook backfill persons (firstOrCreate legacy_key)
   B2 sesuaikan mapping phone/customer_status yang di-drop
C. Import ulang legacy → verifikasi jumlah baris & person 1:1
D. Fase 3 index dipasang ulang otomatis (sudah di migration) — verifikasi 34 idx
E. Halaman merge (langkah 2-5 §3.3) — iteratif, setelah A–D stabil
F. Test + benchmark ulang (SeedVolumeForBenchmark tetap valid)
```

---

## 5. Test Plan

- Regresi guard tagihan WAJIB hijau: `SatuTagihanLanggananPerPeriodeTest`,
  `AuditTagihanDobelTest`, `AktivasiTertagihDobelKarenaActivationDateStaleTest`,
  `InitialInvoiceProrateIgnoresClientAmountTest`.
- Test baru: backfill person 1:1 setelah import; import ulang TIDAK menghasilkan
  person ganda untuk `legacy_key` sama (regresi §3.2).
- Test: drop `customer_status` tidak mengubah `service_status` yang dihasilkan.
- Test: pencarian pelanggan by phone tetap jalan setelah `phone` dihapus.
- `preventLazyLoading` tetap aktif — `clean_address` pilihan B tidak boleh
  memicu lazy load.

---

## 6. Risiko

| Risiko | Mitigasi |
|---|---|
| Import ulang menghapus kerja merge | Backfill by `legacy_key` di dalam command (§3.2) |
| Drop `customer_status` mengubah `service_status` | Test regresi + mapping eksplisit sebelum drop |
| Satukan phone memutus pencarian | Backfill COALESCE + test pencarian |
| Persempit `cid` truncation | Cek `MAX(LENGTH(cid))` sebelum ALTER (dev data) |
| `char(36)` vs `binary(16)` salah pilih | char(36) dulu; reversibel nanti |

---

## 7. Keputusan Produk — DIPUTUSKAN (2026-07-22)

| # | Pertanyaan | Keputusan |
|---|---|---|
| 1 | Scope gelombang pertama | **A (skema) + B/C (persons backfill + import ulang) saja.** Halaman merge & pencarian person = gelombang berikutnya. |
| 2 | Drop `old_account_status` & `customers.ip_address`? | **Drop keduanya.** `ip_address` tetap di `customer_technical_details` (sumber kebenaran). |
| 3 | Konsumen CID di luar sistem? | **Ada sistem luar pakai CID** (Mikrotik/OLT/WA/gateway simpan CID sebagai kunci). |
| 4 | `char(36)` (bukan `binary(16)`)? | Rekomendasi tetap `char(36)` — belum dikonfirmasi eksplisit, dipakai kecuali dibantah. |
| 5 | Merge final/reversibel? | Belum diputus — tidak memblokir gel.1 (merge = gel.2). Rekomendasi reversibel. |

### Konsekuensi keputusan #3 (penting untuk gel.2)

Karena ada sistem luar yang menyimpan CID sebagai kunci, prinsip "CID boleh
berubah mengikuti jaringan" **tidak aman kalau hanya berbekal riwayat CID**.
Saat CID berubah (pindah ODP/POP), profil Mikrotik/OLT/WA/gateway yang menyimpan
CID lama akan menunjuk ke entitas yang salah. Maka gel.2 wajib menyediakan
**alias CID tetap yang bisa di-resolve** (bukan sekadar tabel riwayat
`valid_from`/`valid_to`) — mis. kolom `cid_stable`/`cid_alias` yang tidak pernah
berubah dan memetakan ke CID aktif. Ini menambah desain di gel.2; **tidak
mengubah gel.1**.

### Batas gelombang 1 (yang dikerjakan SEKARANG)

TERMASUK: §2 (semua pembersihan skema, drop `old_account_status` + `ip_address`),
§3.1 (`persons` + `person_id`, `char(36)`), §3.2 (backfill di
`MigrateLegacyDataCommand`), import ulang, Fase 3 index lahir ulang.

TIDAK TERMASUK (gel.2): halaman merge, pencarian person saat registrasi,
riwayat/alias CID, pindah laporan ke level person.

---

## Rujukan
- [`ANALISA_INDEX_DATABASE.md`](./ANALISA_INDEX_DATABASE.md) §10, §13 Fase 4, §14
- [`analisa-identitas-pelanggan-person-uuid.md`](./analisa-identitas-pelanggan-person-uuid.md) §5, §6, §7, §8
- `app/Console/Commands/MigrateLegacyDataCommand.php` — titik backfill
- `docs/RUNBOOK_COMMANDS.md` — alur import ulang
