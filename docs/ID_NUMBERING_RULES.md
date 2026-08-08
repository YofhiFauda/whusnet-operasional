# ID_NUMBERING_RULES.md

# Website Billing ISP Berbasis Master Data Pelanggan

## Tujuan Dokumen

Dokumen ini menjelaskan aturan penomoran ID pelanggan berdasarkan POP/Cabang.

Aturan ini digunakan untuk:

1. ID Request / Registrasi pelanggan.
2. CID pelanggan aktif.
3. Running number per POP.
4. Pencegahan ID duplikat.
5. Konsistensi ID pelanggan dari input manual dan import.

---

# 1. Istilah

## 1.1 ID Request / Registration Number

ID Request adalah ID awal pelanggan ketika pertama kali didaftarkan ke sistem.

ID ini dibuat saat:

1. Admin input pelanggan manual.
2. Admin import pelanggan lama.
3. Data pelanggan pertama kali tersimpan di sistem baru.

Contoh:

```txt
C-SMN-000001
```

## 1.2 CID

CID adalah ID pelanggan aktif.

CID dibuat saat:

1. Data pelanggan sudah lengkap.
2. Layanan pelanggan diaktifkan.
3. Pelanggan masuk status siap billing/aktif.

Contoh:

```txt
D-SMN-000001
```

## 1.3 POP Code

POP Code adalah kode unik untuk POP/Cabang.

Contoh:

| POP           | POP Code |
| ------------- | -------- |
| Siman         | SMN      |
| Jetis         | JTS      |
| Ponorogo Kota | PNG      |

## 1.4 Prefix

Prefix adalah awalan ID.

Contoh:

| Jenis ID                | Prefix |
| ----------------------- | ------ |
| ID Request / Registrasi | C      |
| CID                     | D      |

---

# 2. Format ID

## 2.1 Format ID Request

Format:

```txt
{registration_prefix}-{pop_code}-{running_number}
```

Contoh:

```txt
C-SMN-000001
C-SMN-000002
C-JTS-000001
```

## 2.2 Format CID

Format:

```txt
{cid_prefix}-{pop_code}-{running_number}
```

Contoh:

```txt
D-SMN-000001
D-SMN-000002
D-JTS-000001
```

---

# 3. Contoh POP Siman

Konfigurasi:

```txt
POP Name: Siman
POP Code: SMN
Registration Prefix: C
CID Prefix: D
Padding: 6
```

Hasil:

| Urutan | ID Request   | CID          |
| -----: | ------------ | ------------ |
|      1 | C-SMN-000001 | D-SMN-000001 |
|      2 | C-SMN-000002 | D-SMN-000002 |
|      3 | C-SMN-000003 | D-SMN-000003 |

---

# 4. Konfigurasi di Tabel POP

Tabel `pops` harus memiliki field:

```txt
pop_code
registration_prefix
cid_prefix
```

Contoh data:

| name          | pop_code | registration_prefix | cid_prefix |
| ------------- | -------- | ------------------- | ---------- |
| Siman         | SMN      | C                   | D          |
| Jetis         | JTS      | C                   | D          |
| Ponorogo Kota | PNG      | C                   | D          |

Catatan:

Jika semua POP menggunakan prefix yang sama, tetap simpan prefix di tabel POP agar fleksibel untuk masa depan.

---

# 5. Tabel Sequence

Untuk mencegah ID duplikat, sistem perlu tabel sequence.

Nama tabel yang disarankan:

```txt
pop_number_sequences
```

## Field Minimal

```txt
id
pop_id
sequence_type
prefix
current_number
padding
created_at
updated_at
```

## Sequence Type

```txt
registration
cid
```

## Contoh Data

| pop_id | sequence_type | prefix | current_number | padding |
| -----: | ------------- | ------ | -------------: | ------: |
|      1 | registration  | C      |              0 |       6 |
|      1 | cid           | D      |              0 |       6 |
|      2 | registration  | C      |              0 |       6 |
|      2 | cid           | D      |              0 |       6 |

---

# 6. Aturan Generate ID Request

ID Request dibuat saat pelanggan pertama kali disimpan.

Alur:

```txt
Admin pilih POP
→ Sistem membaca POP Code
→ Sistem membaca registration_prefix
→ Sistem membaca sequence registration POP tersebut
→ Sistem menaikkan nomor urut
→ Sistem membentuk ID Request
→ Sistem menyimpan pelanggan
```

Contoh:

```txt
POP: Siman
pop_code: SMN
registration_prefix: C
current_number: 0
next_number: 1
padding: 6

Hasil:
C-SMN-000001
```

## Aturan

1. ID Request wajib dibuat untuk setiap pelanggan baru.
2. ID Request wajib unique.
3. ID Request dibuat berdasarkan POP pelanggan.
4. Running number berjalan per POP.
5. Running number registration berbeda dari running number CID.
6. Jika pelanggan pindah POP, ID Request lama tidak otomatis berubah kecuali ada kebijakan khusus.
7. Jika gagal menyimpan pelanggan, sequence tidak boleh meloncat tanpa kontrol transaksi jika memungkinkan.

---

# 7. Aturan Generate CID

CID dibuat saat pelanggan diaktifkan.

Alur:

```txt
Admin buka detail pelanggan
→ Sistem cek kelengkapan data
→ Sistem cek status layanan
→ Admin klik Aktifkan Layanan
→ Sistem membaca POP Code
→ Sistem membaca cid_prefix
→ Sistem membaca sequence CID POP tersebut
→ Sistem menaikkan nomor urut
→ Sistem membentuk CID
→ Sistem menyimpan CID
→ Pelanggan menjadi aktif/siap billing
```

Contoh:

```txt
POP: Siman
pop_code: SMN
cid_prefix: D
current_number: 0
next_number: 1
padding: 6

Hasil:
D-SMN-000001
```

## Aturan

1. CID hanya dibuat saat pelanggan aktif/siap billing.
2. CID tidak boleh dibuat untuk pelanggan draft.
3. CID tidak boleh dibuat untuk pelanggan perlu dilengkapi.
4. CID tidak boleh dibuat untuk pelanggan yang belum memiliki paket aktif.
5. CID wajib unique.
6. CID dibuat berdasarkan POP pelanggan.
7. Running number CID berjalan per POP.
8. Jika pelanggan sudah memiliki CID, sistem tidak boleh membuat CID baru.
9. Jika pelanggan berhenti, CID lama tetap disimpan untuk histori.
10. Jika pelanggan aktif kembali, gunakan CID lama kecuali ada kebijakan baru.

---

# 8. Perbedaan ID Request dan CID

| Aspek                       | ID Request                 | CID                          |
| --------------------------- | -------------------------- | ---------------------------- |
| Dibuat saat                 | Pelanggan pertama disimpan | Pelanggan diaktifkan         |
| Wajib untuk semua pelanggan | Ya                         | Tidak, hanya pelanggan aktif |
| Contoh                      | C-SMN-000001               | D-SMN-000001                 |
| Dasar pembuatan             | POP + registration prefix  | POP + CID prefix             |
| Bisa kosong                 | Tidak                      | Bisa sebelum aktif           |
| Digunakan untuk             | Registrasi, pencarian awal | Billing, operasional aktif   |

---

# 9. Aturan Import dan ID

Saat import pelanggan lama:

1. Sistem tetap menyimpan `old_customer_id`.
2. Sistem tetap membuat `registration_number` baru.
3. Sistem tidak langsung membuat CID kecuali pelanggan diaktifkan.
4. Data hasil import harus tetap mengikuti aturan POP.
5. Jika `pop_code` tidak ditemukan, ID tidak boleh dibuat.
6. Jika import gagal, sequence tidak boleh membuat data tidak konsisten.

Rekomendasi MVP:

```txt
Import membuat registration_number.
CID dibuat saat admin melakukan aktivasi layanan.
```

**Migrasi multi-cabang (data legacy dari banyak instalasi/database berbeda):** `old_customer_id` dan `old_request_id` dari legacy TIDAK unik lintas cabang (tiap cabang punya counter sendiri mulai dari 1). Cek "sudah diimport belum" wajib di-scope per cabang (bandingkan lewat `pop_id`/relasi POP, bukan cek global ke seluruh tabel), begitu juga `customer_code` — lihat detail di 12.1.

---

# 10. Aturan Pindah POP

Untuk MVP, pelanggan tidak disarankan pindah POP secara bebas.

Jika pelanggan pindah POP:

1. `registration_number` lama tetap disimpan.
2. CID lama tetap disimpan jika sudah aktif.
3. Perubahan POP harus masuk audit log.
4. Jika bisnis ingin ID berubah mengikuti POP baru, harus dibuat aturan migrasi khusus.
5. Default MVP: ID tidak berubah saat pindah POP.

---

# 11. Aturan Sequence dan Race Condition

Masalah yang harus dicegah:

```txt
Dua admin membuat pelanggan bersamaan
→ dua proses membaca nomor terakhir yang sama
→ ID duplikat
```

Solusi teknis:

1. Gunakan database transaction.
2. Lock row sequence saat generate ID.
3. Tambahkan unique constraint pada `registration_number`.
4. Tambahkan unique constraint pada `cid`.
5. Jika terjadi duplicate, sistem retry generate ID.
6. Jangan generate ID hanya dengan menghitung jumlah customer.

Larangan:

```txt
Jangan membuat ID dengan cara count(customers) + 1
```

Karena rawan duplikat.

---

# 12. Unique Constraint

Wajib unique:

```txt
customers.registration_number  (kolom aktual: customer_code) — unique PER POP, bukan global. Lihat 12.1.
customers.cid
pops.pop_code
pop_number_sequences.pop_id + pop_number_sequences.sequence_type
```

Jika CID nullable, validasi unique hanya saat CID terisi.

## 12.1 `customer_code` unique per POP, bukan global (sejak 2026-07-20)

**Implementasi aktual berbeda dari desain awal dokumen ini.** Kolom `customers.customer_code` (nama kolom real untuk "registration_number" di atas) awalnya diberi `UNIQUE` constraint global satu kolom. Ini keliru dan sudah diperbaiki lewat migration `scope_customer_code_unique_to_pop`.

**Kenapa salah:** ID request legacy (RQ dari `old_request_id`) sering dijadikan `customer_code` apa adanya saat migrasi. Tiap instalasi software cabang lama punya counter sendiri mulai dari 1 — jadi nomor yang sama (mis. `RQ000042`) bisa dipakai oleh 2 pelanggan berbeda di 2 cabang berbeda secara sah. Constraint unique GLOBAL pada `customer_code` memaksa salah satu pelanggan dapat nomor pengganti secara acak (tergantung urutan import), padahal `cid_prefix` sudah beda per cabang sehingga CID akhir (`{cid_prefix}{olt}{dist}{customer_code}...`) otomatis tetap unik walau `customer_code`-nya sama.

**Kasus nyata**: pelanggan `Winda Ari Sulfia` (POP Jetis, `old_request_id=RQ000042`) dan `Endah Puji Rahayu` (POP Sandya, `old_request_id=RQ000042`) — dua pelanggan berbeda, kebetulan nomor request legacy sama. Sebelum fix, `customer_code` Winda dipaksa jadi `RQ002058` (bukan `RQ000042` aslinya) karena Endah "menang" duluan pas migrasi. Ini mempersulit audit — CID sistem baru gak cocok sama CID di sistem lama tanpa alasan yang jelas di permukaan.

**Fix**: constraint diganti jadi composite `UNIQUE(pop_id, customer_code)`. Dua konsekuensi:

1. `customer_code` sekarang boleh sama ANTAR cabang berbeda (`pop_id` beda) — RQ number legacy bisa dipertahankan apa adanya tanpa perlu diganti, sepanjang gak dobel DALAM cabang yang sama.
2. `Pop::generateRegistrationNumber()` dan proses dedup di `MigrateLegacyDataCommand.php` (`candidateCode`) di-scope ke seluruh subtree cabang (cabang + semua mini-POP anaknya) — bukan cuma exact `pop_id` — karena mini-POP dalam 1 cabang mewarisi `cid_prefix` cabangnya, jadi harus dianggap 1 "namespace" yang sama untuk keperluan cek duplikat ini.
3. **Syarat wajib**: `cid_prefix` HARUS unik antar POP bertipe `cabang` (divalidasi di `PopController::store()`/`update()`). Kalau 2 cabang punya `cid_prefix` sama, poin 1 di atas jadi gak aman lagi (CID akhir bisa collide beneran). Mini-POP boleh — memang harus — mewarisi `cid_prefix` cabang induknya.

Lihat juga: `docs/PLAN_MIGRASI_PELANGGAN_BILLING.md` dan `docs/ANALISA_KELENGKAPAN_MIGRASI_jetis_db.MD` untuk konteks migrasi legacy multi-cabang.

## 12.2 Konsekuensi ke tampilan REQ ID murni (List Pelanggan Putus/Gagal)

`Pop::resolveDisplayId()` sengaja menampilkan REQ ID murni tanpa `cid_prefix` untuk status `terminated`/`failed`/`rejected`/`putus`/`gagal` (lihat `docs/master/pop/archive/spesifikasi-pop-distribusi-cid.md`). Setelah 12.1, `customer_code` (basis REQ ID) boleh sama antar cabang — jadi 2 pelanggan beda cabang, sama-sama status Putus/Gagal, bisa tampil dengan REQ ID identik (mis. `RQ000042`) di layar yang sama.

**Fix**: `resources/views/customers/index.blade.php` — tabel "Pelanggan Gagal" dan "Pelanggan Putus" ditambah kolom **POP** (sama seperti tabel pelanggan aktif) supaya admin tetap bisa bedain walau REQ ID kebetulan sama. Format REQ ID sendiri TIDAK diubah — tetap sesuai spek asli.

---

# 13. Format Running Number

Default padding:

```txt
6 digit
```

Contoh:

```txt
1      → 000001
25     → 000025
999    → 000999
12000  → 012000
```

Jika sudah melewati padding:

```txt
1000000 → 1000000
```

Sistem tidak boleh error hanya karena nomor lebih panjang dari padding.

---

# 14. Contoh Skenario

## 14.1 Pelanggan Baru POP Siman

Input:

```txt
POP: Siman
Prefix Request: C
POP Code: SMN
Current registration sequence: 0
```

Output:

```txt
registration_number = C-SMN-000001
cid = null
status = calon_pelanggan
data_completeness_status = perlu_dilengkapi
```

## 14.2 Pelanggan Diaktifkan

Input:

```txt
Customer: C-SMN-000001
POP: Siman
CID Prefix: D
Current CID sequence: 0
```

Output:

```txt
registration_number = C-SMN-000001
cid = D-SMN-000001
status = aktif
data_completeness_status = siap_billing
```

## 14.3 Import Pelanggan Lama

Input:

```txt
old_customer_id = OLD9981
POP = SMN
```

Output:

```txt
old_customer_id = OLD9981
registration_number = C-SMN-000002
cid = null
```

CID dibuat nanti saat aktivasi.

---

# 15. Acceptance Criteria ID Numbering

Fitur ID numbering dianggap selesai jika:

* [ ] Setiap POP memiliki `pop_code`.
* [ ] Setiap POP memiliki `registration_prefix`.
* [ ] Setiap POP memiliki `cid_prefix`.
* [ ] Sistem memiliki sequence registration per POP.
* [ ] Sistem memiliki sequence CID per POP.
* [ ] ID Request dibuat otomatis saat pelanggan disimpan.
* [ ] CID dibuat otomatis saat pelanggan diaktifkan.
* [ ] ID Request tidak duplikat.
* [ ] CID tidak duplikat.
* [ ] Nomor urut berjalan per POP.
* [ ] Nomor urut registration dan CID terpisah.
* [ ] Import pelanggan menghasilkan ID Request.
* [ ] CID tidak dibuat sebelum pelanggan siap billing/aktif.
* [ ] Sistem aman dari duplikasi saat dua admin input bersamaan.
* [ ] Perubahan POP/CID masuk audit log jika relevan.
