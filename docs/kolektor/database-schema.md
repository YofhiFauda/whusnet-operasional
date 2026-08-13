# Database Schema — Modul Kolektor

## Migrasi

| Migrasi | Isi | Fase |
|---|---|---|
| `2026_08_03_090928_create_payment_batches_table.php` | Tabel `payment_batches` — wadah ringan satu sesi submit | pra-2.0 |
| `2026_08_03_120001_add_collector_id_to_customers_table.php` | `customers.collector_id` — rute penagihan permanen | pra-2.0 |
| `2026_08_03_120002_add_collector_columns_to_payments_table.php` | `payments.collected_by` + `payments.collected_date` | pra-2.0 |
| `2026_08_08_102434_create_collector_deposits_table.php` | **Tabel `collector_deposits`** — serah-terima uang fisik | Fase 2 |
| `2026_08_08_102435_add_collector_deposit_id_to_payments_table.php` | **`payments.collector_deposit_id`** — penanda sudah disetorkan | Fase 2 |
| `2026_08_08_112259_create_collector_visits_table.php` | **Tabel `collector_visits`** — catatan kunjungan | Fase 3 |
| `2026_08_08_144022_create_payment_receipts_table.php` | **Tabel `payment_receipts`** — arsip kwitansi | Fase 4 |

Fase 1 **tidak menambah migrasi** — isinya pemisahan halaman, permission baru (lahir dari `features × actions`, bukan migration), dan config `billing.collector_due_window_days`.

Perbaikan #1–#9 & R1–R2 juga **tidak menambah migrasi**. Status `lebih_setor` (#6) muat di kolom `status` yang sudah `string`.

---

## `collector_deposits`

Satu sesi serah-terima uang fisik kolektor → admin.

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint | |
| `deposit_number` | string, **unique** | `SETOR-{tahun}-{4 digit}` |
| `collector_id` | FK `users`, cascade delete | penyetor |
| `pop_id` | FK `pops`, nullable, nullOnDelete | **representatif untuk listing saja** — otorisasi TIDAK bersandar ke kolom ini |
| `status` | string, **indexed**, default `menunggu_verifikasi` | `DepositStatus` |
| `declared_amount` | decimal(15,2), nullable | uang fisik yang dihitung **admin**, terisi saat verifikasi |
| `difference` | decimal(15,2), nullable | `declared − (Σ payment + settlement)`, hasil verifikasi |
| `settlement_amount` | decimal(15,2), default 0 | pelunasan selisih yang dibawa setoran INI |
| `settled_amount` | decimal(15,2), default 0 | akumulasi pelunasan yang DITERIMA setoran ini |
| `settles_deposit_id` | FK self, nullable, nullOnDelete | setoran bermasalah yang dilunasi |
| `note` | text, nullable | wajib diisi kalau `difference ≠ 0` (ditegakkan di service) |
| `submitted_at` | timestamp | |
| `verified_by` / `verified_at` | FK `users` nullable / timestamp nullable | |
| `written_off_by` / `written_off_at` / `write_off_reason` | FK `users` nullable / timestamp / text | hapus buku Owner |
| `idempotency_key` | string, nullable, **unique** | cegah setoran dobel |
| `timestamps` | | |

Index tambahan: `(collector_id, status)`.

### Yang SENGAJA tidak ada

**Kolom saldo.** Saldo adalah angka turunan (`Σ payment belum tersetor − Σ setoran terverifikasi`), dihitung `CollectorBalanceService`. Kolom yang di-`+=`/`-=` berhenti benar begitu satu payment di-reject, dan angka uang yang bohong tak punya alarm.

**`computed_amount`.** Dihitung dari payment yang tertaut (`CollectorDeposit::computedAmount()`), tak pernah disimpan.

> Kenapa `difference` boleh disimpan tapi saldo tidak: `difference` adalah **catatan hasil verifikasi** pada satu titik waktu, dan payment tak bisa keluar dari setoran terverifikasi (guard di `PaymentController::reject()`). Saldo adalah **angka hidup** yang berubah tiap ada payment baru atau ditolak.

### Kenapa bukan memperluas `payment_batches`

Beda kardinalitas. `payment_batches` = satu sesi **submit**; `collector_deposits` = satu sesi **serah-terima uang**. Satu setoran bisa memuat pembayaran dari banyak batch, jadi menempelkan `declared`/`variance` ke `payment_batches` salah bentuk.

Komentar di migration `create_payment_batches` sudah diperbarui menjelaskan ini — dulu isinya "Setoran di-drop dari scope", yang tidak berlaku lagi.

### Status (`DepositStatus`)

| Nilai | Terminal? | Arti |
|---|---|---|
| `menunggu_verifikasi` | tidak | uang sudah diserahkan, belum dihitung admin |
| `terverifikasi` | ya | uang fisik cocok |
| `selisih` | **tidak** | kurang setor — kewajiban kolektor, wajib punya jalan pulang |
| `selisih_lunas` | ya | kurang setor sudah ditutup lewat setoran berikutnya |
| `lebih_setor` | ya | uang fisik melebihi catatan, dikembalikan fisik |
| `dihapus_buku` | ya | kerugian diakui Owner |

Disimpan sebagai `string` + PHP enum, bukan native DB enum — menambah nilai baru tak butuh `ALTER TABLE` (itulah kenapa `lebih_setor` bisa lahir tanpa migrasi).

---

## `collector_visits`

Satu kunjungan ke satu pintu pada satu hari.

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint | |
| `collector_id` | FK `users`, cascade delete | |
| `customer_id` | FK `customers`, cascade delete | |
| `pop_id` | FK `pops`, nullable, nullOnDelete | **disalin saat kunjungan dicatat** — laporan aging per POP tak boleh berubah kalau pelanggan dipindah POP belakangan; scope admin dibaca dari kolom ini |
| `visited_at` | date | |
| `result` | string, **indexed** | `VisitResult` |
| `promised_date` | date, nullable | hanya untuk `janji_bayar`; dinolkan untuk hasil lain |
| `note` | text, nullable | dinolkan saat baris menjadi `bayar` |
| `payment_id` | FK `payments`, nullable, nullOnDelete | hanya untuk `bayar`; dinolkan saat hasil berubah |
| `timestamps` | | |

Index:
- **unique** `(collector_id, customer_id, visited_at)` — `collector_visits_unique_per_day`
- `(collector_id, visited_at)`

### Kenapa unique per hari

Pelanggan yang melunasi 3 tagihan sekaligus harus tercatat **satu** kunjungan. Tanpa kunci ini, "total kunjungan" di laporan aging membengkak dan pola aslinya tertutup.

> **Jebakan yang pernah terjadi:** `updateOrCreate()` dengan `visited_at` di kunci pencarian **tak pernah** menemukan baris yang ada — kolomnya `DATE` tapi atributnya di-cast `date`, sehingga Eloquent membandingkannya sebagai datetime penuh (`… 00:00:00`). Insert kedua menabrak unique index dan **seluruh transaksi pembayaran rollback**. Sekarang memakai `whereDate()` + `fill/save` (`CollectorVisitService::findVisit()`).

### Hasil (`VisitResult`)

| Nilai | Sumber |
|---|---|
| `bayar` | **turunan payment**, tak bisa diketik maupun ditimpa input manual |
| `tidak_ada_orang` | input kolektor |
| `menolak` | input kolektor |
| `janji_bayar` | input kolektor, wajib `promised_date` |

---

## `payment_receipts`

Arsip kwitansi. **Dokumen, bukan uang** — tak satu pun kolom di sini memengaruhi status setoran atau nilai pembayaran.

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | bigint | |
| `payment_id` | FK `payments`, **nullable**, nullOnDelete | null selama belum tercocokkan; berkas tetap ada supaya pekerjaan yang tertinggal tidak hilang dari layar admin |
| `pop_id` | FK `pops`, nullable, nullOnDelete | disalin dari payment saat cocok — inilah yang membuat berkas ikut POP scope. **Tidak dinolkan saat `detach()`**: POP-nya sudah pernah diketahui, dan menolkannya justru melebarkan akses karena gerbang melewatkan berkas ber-`pop_id` null |
| `uploaded_by` | FK `users`, cascade delete | |
| `original_filename`, `path`, `mime_type`, `size_bytes` | | `path` di disk **`local`** (privat) — menunjuk LEMBAR yang diunggah. Satu lembar bisa memuat 8 kwitansi, jadi berkas ini arsip kertasnya, bukan "kwitansi pelanggan X". Kwitansi satuan dirender ulang dari data, tidak disimpan |
| `checksum` | string(64) | SHA-256 isi berkas yang diunggah. Dipakai `store()` mengenali unggahan ulang. **Unique-nya komposit `(checksum, payment_id)`**, bukan `checksum` sendirian — lihat di bawah |
| `status` | string, **indexed** | `ReceiptStatus` |
| `match_method` | string, nullable | `ReceiptMatchMethod` — `teks` / `qr` / `ocr` / `manual` |
| `detected_number` | string, nullable | nomor yang terbaca, disimpan juga saat `MISMATCH` untuk penelusuran |
| `attempts` | tinyint | percobaan baca otomatis; batasnya `PaymentReceiptService::MAX_ATTEMPTS`, dan `MatchPaymentReceipt::$tries` mengambil angka dari konstanta yang sama |
| `last_error` | text, nullable | alasan gagal — dibedakan antara "tak terbaca" dan kegagalan teknis |
| `matched_by` / `matched_at` | FK `users` nullable / timestamp | terisi untuk pencocokan manual |
| `timestamps` | | |

Index tambahan: `(status, created_at)`.

### Kenapa unique-nya `(checksum, payment_id)`, bukan `checksum`

Migration `allow_many_receipt_rows_per_bundle_file` (2026-08-11).

Satu **lembar** cetak memuat 8 kwitansi untuk digunting, jadi satu berkas mewakili banyak pembayaran. `checksum` unique global menolak bentuk itu mentah-mentah: baris kedua untuk lembar yang sama langsung ditolak database, sehingga tujuh kwitansi lain tak pernah bisa tercatat.

Kuncinya digeser jadi **satu baris per (lembar, pembayaran)**. Maksud indeks lama tetap dijaga di tempat lain: `PaymentReceiptService::store()` mencari `checksum` lebih dulu dan mengembalikan baris yang sudah ada, sehingga unggah ulang berkas identik tidak melahirkan baris menganggur kedua. Itu **tidak** bisa diserahkan ke indeks, karena `payment_id` yang masih NULL dianggap berbeda satu sama lain oleh MySQL maupun SQLite.

### Status (`ReceiptStatus`)

| Nilai | Arti | Butuh manusia? |
|---|---|---|
| `pending` | baru diunggah, antre dibaca | tidak |
| `processing` | sedang dibaca queue | tidak |
| `matched` | menempel ke pembayaran | tidak |
| `mismatch` | nomor terbaca tapi tak menunjuk pembayaran sah | **ya** |
| `failed` | tak terbaca sama sekali / kegagalan teknis | **ya** |

`mismatch` dan `failed` dibedakan karena tindak lanjutnya beda: yang pertama biasanya salah cetak atau salah berkas, yang kedua biasanya kualitas gambar.

### Gerbang akses berkas

| Keadaan | Siapa boleh membuka |
|---|---|
| `pop_id` terisi | siapa pun dalam POP scope-nya |
| `pop_id` null (belum pernah tercocokkan) | **hanya pengunggahnya**, atau pemegang akses seluruh POP |

"Belum bisa di-scope" bukan berarti "boleh dilihat semua orang" — tanpa pembatas kedua, tiap admin melihat seluruh berkas yatim di sistem lintas cabang.

### Kenapa `payment_id` nullable

Berkas bisa mendarat sebelum diketahui milik siapa. Menolak menyimpan baris tanpa pemilik berarti kwitansi yang QR-nya rusak hilang begitu saja dari daftar kerja — persis dokumen yang paling butuh perhatian.

---

## Kolom tambahan di tabel lain

### `payments`

| Kolom | Ditambah | Catatan |
|---|---|---|
| `payment_batch_id` | pra-2.0 | sesi submit |
| `collected_by` | pra-2.0 | **snapshot beku** siapa yang faktanya menagih. Tidak disalin otomatis dari `customers.collector_id` — kalau disalin buta, laporan kolektor mencatat uang yang tak pernah dia tagih |
| `collected_date` | pra-2.0 | tanggal uang diterima **di lapangan**, terpisah dari `payment_date` (posting kantor). Divalidasi `≤ hari ini` |
| `collector_deposit_id` | **Fase 2** | `null` = belum disetor = **masih jadi saldo di tangan kolektor**. Itulah satu-satunya definisi saldo di sistem ini |

### `customers`

| Kolom | Catatan |
|---|---|
| `collector_id` | rute permanen tapi **reassignable**. Tiga guard ditegakkan di aplikasi, bukan di migration: target ber-role `kolektor`, POP pelanggan ⊆ scope kolektor, kolektor bermuatan/bersaldo tak boleh dinonaktifkan |

---

## Relasi

```
users (kolektor)
  ├─ hasMany customers          (customers.collector_id)
  ├─ hasMany payments           (payments.collected_by)
  ├─ hasMany collector_deposits (collector_deposits.collector_id)
  └─ hasMany collector_visits   (collector_visits.collector_id)

collector_deposits
  ├─ hasMany payments           (payments.collector_deposit_id)
  ├─ belongsTo users            (collector_id / verified_by / written_off_by)
  ├─ belongsTo pops             (pop_id, representatif)
  └─ belongsTo collector_deposits (settles_deposit_id, self-reference)

collector_visits
  ├─ belongsTo users            (collector_id)
  ├─ belongsTo customers
  ├─ belongsTo pops             (pop_id, snapshot)
  └─ belongsTo payments         (payment_id, hanya untuk result=bayar)
```

Model `CollectorDeposit` dan `CollectorVisit` dua-duanya `use HasPopScope` — query listing/riwayat/aging memakainya. Angka **total** di halaman kas sengaja tidak disaring scope; yang menjaga adalah gerbang all-or-nothing di controller (lihat [business-logic.md § 7](business-logic.md#7-rbac--pop-scope)).

---

## Config

| Kunci | Default | Berkas |
|---|---|---|
| `billing.collector_due_window_days` | `7` (env `COLLECTOR_DUE_WINDOW_DAYS`) | `config/billing.php` |
| `services.gemini.key` | **kosong** (env `GEMINI_API_KEY`) | `config/services.php` — OCR cadangan; kosong = mati, dan itu normal |
| `services.gemini.model` | `gemini-2.0-flash` | idem |

### Dependency yang ditambahkan Fase 4

| Paket | Untuk apa |
|---|---|
| `endroid/qr-code:^5.1` | **membuat** QR (SvgWriter — tanpa GD/imagick) |
| `khanamiryan/qrcode-detector-decoder:^2.0` | **membaca** QR dari berkas yang diunggah (pakai `ext-gd`) |
| `bacon/bacon-qr-code` | dependensi endroid |

Dipilih endroid `^5.1`, bukan `^6`, karena `^6` mensyaratkan PHP `^8.4` sementara `composer.json` menyatakan `"php": "^8.3"` — menaikkannya akan membuat pernyataan itu tidak jujur.

---

## Catatan Deploy

Setelah migrasi, jalankan seeder RBAC lalu **`EffectiveAccessService::clearCache()`** — permission & scope di-cache, permission baru tak kelihatan sampai cache kedaluwarsa.

Permission yang lahir di modul ini: `kolektor.pay`, `kolektor.deposit`, `kolektor.visit`, `collector_worksheet.view`, `.assign`, `.validate`, `.approve` — dari `ActionCode::PAY`, `DEPOSIT`, `VISIT` + feature `collector_worksheet` di `config/rbac.php` & `FeatureSeeder`.

> **Hati-hati:** `RolePermissionSeeder` memakai `sync()` — menjalankannya di DB yang matrix-nya sudah pernah diatur manual akan **menimpa** seluruh permission tiap role. Untuk menambah permission baru saja, pakai `syncWithoutDetaching` pada role yang dituju lalu bersihkan cache.
