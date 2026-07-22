# Analisa: Identitas Pelanggan Permanen (Person + UUIDv7)

**Status: USULAN. Belum diimplementasi, belum disetujui.** Tidak ada kode yang
ditulis untuk ini. Dokumen ini merekam diskusi 2026-07-22 dan angka-angka yang
mendasarinya, supaya keputusan nanti tidak diambil dari ingatan.

Dipicu pertanyaan owner: REQ ID (`customer_code`) dipakai sebagai kunci permanen
yang melekat ke pelanggan seumur hidup dan jadi cikal bakal CID. Belajar dari
sistem lama, itu akan menimbulkan konflik besar saat dikembangkan. Usulan awal:
tambah `ID_PELANGGAN` berbasis UUIDv7 sebagai acuan kedua / cadangan.

Semua angka di bawah hasil query DB `whusnet_operasional` per 2026-07-22, setelah
import ulang legacy (lihat
[`analisa-duplikasi-tagihan-pembayaran-migrasi-legacy.md`](../billing-pembayaran/analisa-duplikasi-tagihan-pembayaran-migrasi-legacy.md)).

---

## 1. Diagnosa: REQ ID sudah patah sebagai identitas orang

Kekhawatiran owner benar, tapi bukan soal masa depan — **kerusakannya sudah ada di
data hari ini**, dan patah ke dua arah sekaligus.

### 1.1 Satu RQ, orang berbeda

```
indeks unik customers : (pop_id, customer_code)   ← bukan customer_code saja
```

`customer_code` hanya unik **per POP**. Saat ini ada **39 `customer_code` kembar
lintas POP**. `RQ000005` di Jetis dan `RQ000005` di Sandya adalah dua orang
berbeda. Ini kelas bug yang dulu membuat pembayaran Eva (sand_db) nyangkut ke
invoice Hanif (jetis_db).

Akarnya: tiap instalasi cabang legacy memulai penomoran dari `RQ000001`. Tiap
cabang atau akuisisi baru mengulang kelas bug yang sama.

### 1.2 Satu orang, banyak RQ

25 grup nama+POP kembar, semuanya pendaftaran ulang. Contoh: Mistiani punya
`PE000001` (rejected) dan `PE000899` (active) — satu manusia, dua RQ, satu POP.

**REQ ID adalah nomor _permintaan_, bukan nomor _orang_.** Putus langganan lalu
daftar lagi → RQ baru. Premis "melekat seumur hidup" tidak pernah benar.

---

## 2. CID bukan ID independen — dia turunan

```php
// CustomerController.php — {pop.cid_prefix}{olt_number}{dist_code}{customer_code}_{village}_{name}
$cid = $pop->generateComplexCid($customer, $distribution);
```

Contoh nyata:

```
C1X4ARQ000524
└┬┘└┬┘└───┬───┘
 │   │    customer_code
 │   olt + kode distribusi
 cid_prefix POP
```

**1488 dari 1488** CID memuat `customer_code` di dalamnya. CID mengunci tiga hal
sekaligus: identitas orang, nomor permintaan, dan topologi jaringan.

Konsekuensinya:

| Peristiwa | Akibat ke CID |
|---|---|
| Pindah ODP / ganti distribusi | `X4A` → `X4C`, CID lama bohong |
| Pindah POP | prefix berubah, CID lama bohong |
| RQ berubah (daftar ulang) | CID berubah |

Menambah UUID **tidak menyentuh masalah ini sama sekali**.

### 2.1 CID tidak punya unique index

```
indeks unik customers : PRIMARY(id), (pop_id, customer_code)
```

Tidak ada `cid` di situ. Ini lubang yang sudah ada sekarang, terlepas dari rencana
UUID.

---

## 3. Kenapa "dua acuan sebagai backup" ditolak

`CLAUDE.md` sudah punya aturannya sendiri, di konteks `fop_task.notes`:

> Jangan salin `catatan_teknis` ke sini — itu bikin dua sumber kebenaran yang
> gampang menyimpang.

Prinsip yang sama berlaku. Kalau suatu saat UUID bilang "ini orang A" dan RQ bilang
"ini orang B", mana yang menang? Cadangan hanya berguna kalau ada aturan otoritas
tertulis. Tanpa itu, dua ID = dua kebenaran, dan CID ikut goyang karena dia turunan
RQ.

**Yang benar bukan cadangan, tapi pembagian tugas.** Satu ID satu pekerjaan:

| ID | Menjawab | Berubah? | Tampil ke user? |
|---|---|---|---|
| UUIDv7 (`persons`) | siapa orangnya | tidak pernah | tidak pernah |
| `customer_code` (RQ) | kontrak yang mana | tidak, selama kontrak hidup | ya |
| CID | sambungan fisik yang mana | ya, ikut topologi | ya |

UUID tidak menggantikan RQ dan bukan cadangannya. Dia menjawab pertanyaan yang
selama ini tidak ada yang menjawab.

---

## 4. UUIDv7 — memecahkan apa, tidak memecahkan apa

### Memecahkan

- **Tabrakan lintas cabang mati permanen.** Ini masalah nyata yang sudah merusak
  data (lihat `docs/ANALISA_KELENGKAPAN_MIGRASI_jetis_db.MD` & catatan migrasi).
- Bisa digenerate offline tanpa round-trip DB — aman untuk impor massal dan
  instalasi multi-cabang.
- v7 *time-ordered* → lokalitas indeks di InnoDB. v4 tidak punya ini, dan bedanya
  besar begitu tabel menembus jutaan baris.
- **Dukungan framework sudah native.** Laravel 13 di repo ini:

  ```php
  // vendor/laravel/framework/.../Eloquent/Concerns/HasUuids.php:18
  return (string) Str::uuid7();
  ```

  Tidak perlu library atau generator sendiri. `HasVersion4Uuids` tersedia sebagai
  opt-in perilaku lama — tidak dipakai.

### Tidak memecahkan

- **Kapan dua baris itu orang yang sama.** Kalau UUID dicetak saat registrasi,
  pelanggan yang daftar ulang dapat UUID baru juga → masalah identik, kolom baru.
  Yang sulit bukan format ID, tapi *identity resolution*. Lihat §5.
- CID yang menempel topologi.
- Keterbacaan manusia. CS tidak bisa mengeja `01932f8c-…` di telepon, jadi UUID
  tidak bisa menggantikan RQ/CID di UI.

---

## 5. Temuan keras: identitas tidak bisa ditentukan otomatis

Keputusan owner: **satu orang satu identitas seumur hidup**, riwayat lama harus
ikut saat pelanggan daftar ulang.

Pertanyaan lanjutannya: dari mana sistem tahu dua record itu orang yang sama?
Ketiga kandidat natural key diuji ke data nyata — **tidak ada yang bisa dipercaya.**

| Kunci | Terisi | Grup duplikat | Andal? |
|---|---|---|---|
| `identity_number` (NIK) | 1957 / 1957 | 69 | tidak |
| `primary_phone` | 1938 / 1957 | 93 | tidak |
| nama + POP | — | 25 | tidak |

### 5.1 NIK terlihat lengkap 100%, tapi semu

140 baris bukan 16 digit. Sampah nyata:

```
NIK 1234567890  dipakai 9 pelanggan
NIK 35021       dipakai 5 pelanggan
NIK 123         dipakai 4 pelanggan
NIK 0           dipakai 4 pelanggan
```

Dari **59 grup** NIK yang formatnya valid *dan* duplikat:

```
nama identik : 21   ← kandidat merge kuat
nama berbeda : 38   ← NIK salah ketik / disalin dari orang lain
```

### 5.2 Nomor HP menghasilkan false positive

```
6285642580083 : Santi Rahayu [active]   + Rachmat Andrian [terminated]
628122015855  : Luwan Prianto [active]  + Ria Anggraini [rejected]
```

Satu keluarga berbagi nomor, bukan satu orang. Merge otomatis di sini
menggabungkan dua manusia berbeda dan mencampur riwayat tagihannya — kerusakan
yang lebih parah dari masalah aslinya.

### 5.3 Nama menghasilkan false negative

```
6289633111303 : Awad El Hakam [terminated] + Awad Elhakam [active]    ← spasi
6285877761777 : Riska Nur Afrillia         + Riska Nur Afrilia        ← dobel L
```

Orang yang sama, ejaan berbeda, lolos dari deteksi nama.

### 5.4 Kesimpulan

**Merge wajib diputuskan manusia. Tidak boleh otomatis.** Tugas sistem adalah
*mengusulkan* kandidat dan membuat merge murah serta bisa dibatalkan — bukan
memutuskan.

---

## 6. Bentuk yang diusulkan

**Tabel `persons` baru + `customers.person_id` sebagai FK.** Bukan mengubah bentuk
`customers`.

```
persons        (uuid v7, immutable, tak pernah tampil)
   └── customers/subscription   (customer_code / RQ, tampil)
          └── cid               (identitas sambungan, ikut topologi, ada riwayat)
```

Alasannya pragmatis:

- ~58 tabel, semua FK menunjuk `customers.id` bigint. **Tidak ada yang perlu
  diubah.**
- Backfill awal 1:1 — tiap customer dapat satu person. Nol perubahan perilaku di
  hari pertama.
- Merge = arahkan `person_id` dua baris ke person yang sama. Satu kolom,
  reversibel.
- Riwayat mengikuti person lewat join, bukan dengan memindahkan invoice/payment.
  **Data uang tidak pernah disentuh saat merge** — ini yang membuat operasinya
  aman.

UUIDv7 tinggal di `persons`, bukan di `customers`. Kalau nanti terbukti butuh
surrogate anti-tabrakan di level registrasi juga (mis. sinkronisasi antar
instalasi cabang), baru ditambah — dan saat itu pekerjaannya sudah jelas berbeda,
jadi tetap bukan dua kebenaran.

### 6.1 Yang ditolak, beserta alasannya

| Usulan | Alasan ditolak |
|---|---|
| UUID sebagai primary key | Migrasi masif 58 tabel tanpa manfaat. Bigint PK tetap untuk join; UUID kolom unik sekunder. |
| Simpan sebagai `char(36)` | 36 vs 16 byte dikali setiap index yang memuatnya. Pakai `binary(16)` + cast/accessor supaya tetap terbaca di tinker & log. |
| UUID tampil di UI | Tidak bisa dieja di telepon. Ini identitas mesin, bukan referensi bisnis. |
| Merge otomatis dari NIK/HP | Sudah dibuktikan salah di §5. |
| UUID sebagai "cadangan" RQ | Dua sumber kebenaran, §3. |

---

## 7. Urutan kerja yang diusulkan

Bertahap; tiap langkah berdiri sendiri dan bisa dihentikan tanpa menyisakan
setengah jadi.

1. **`persons` + `person_id` + backfill 1:1.** Nol perubahan perilaku, murni
   menyiapkan tempat.
2. **Unique index CID + tabel riwayat CID** (`cid`, `valid_from`, `valid_to`,
   alasan). **Tidak bergantung pada keputusan UUID — bisa jalan duluan.** Karena
   CID diputuskan ikut jaringan, tanpa riwayat setiap invoice PDF lama, catatan
   teknisi, profil Mikrotik/OLT, dan pesan WA yang menyebut CID lama akan menunjuk
   ke ruang kosong.
3. **Halaman kandidat merge.** Mulai dari 21 grup NIK-nama-identik (sinyal
   terkuat), lalu 93 grup HP sebagai "perlu ditinjau". Admin memutuskan; sistem
   mencatat siapa dan kapan.
4. **Pencarian person saat registrasi.** Sebelum membuat record baru, cari
   NIK/HP/nama mirip dan tampilkan "mungkin ini orang yang sama?". Mencegah
   duplikat baru lahir, bukan hanya membereskan yang lama.
5. Setelah 1–4 stabil: pindahkan laporan riwayat pelanggan dari level `customer`
   ke level `person`.

---

## 8. Belum diputuskan

1. **Siapa yang mengonsumsi ID ini di luar sistem?** Profil Mikrotik/OLT,
   notifikasi WhatsApp, PDF invoice, payment gateway. Kalau ada sistem luar yang
   menyimpan CID sebagai kunci, "CID boleh berubah" punya konsekuensi ke sana —
   dan itu menentukan apakah riwayat CID cukup, atau perlu alias yang tetap bisa
   di-resolve.
2. **Merge final atau bisa dibatalkan?** Kalau admin salah menggabungkan dua orang,
   apa yang terjadi? Rekomendasi: merge disimpan sebagai relasi, bukan penghapusan,
   supaya bisa dipisah lagi. Konsekuensinya menambah kompleksitas query di semua
   laporan.

---

## Keputusan yang sudah diambil (2026-07-22)

| Pertanyaan | Jawaban owner |
|---|---|
| Pelanggan daftar ulang — riwayat lama ikut? | **Ya, satu orang satu identitas seumur hidup** |
| Pindah ODP/POP — CID ikut berubah? | **Ya, CID mengikuti jaringan** |

---

## Referensi

- `app/Http/Controllers/CustomerController.php` — `generateComplexCid`, `PopSequence`
- `app/Models/Pop.php` — `cid_prefix`, generator CID
- `docs/ID_NUMBERING_RULES.md` — aturan penomoran yang berlaku sekarang
- `docs/master/pop/business-logic.md` — prefix & sequence per POP
- `docs/billing-pembayaran/analisa-duplikasi-tagihan-pembayaran-migrasi-legacy.md` — sumber angka & kasus tabrakan lintas cabang
