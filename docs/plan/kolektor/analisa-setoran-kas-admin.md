# BELUM DIKERJAKAN (PENDING)
## Analisa Saldo Kas Admin & Rancangan Modul Setoran Kas

**Tanggal:** 2026-08-14
**Status:** dicatat atas permintaan user. **Belum ada kode yang diubah.**
**Pemicu:** pertanyaan user — *"ketika setoran dari kolektor sukses, seharusnya saldo itu masuk ke Saldo Admin. Terus ada modul baru Setoran Kas, biar atasan/Owner tahu jelas dari mana sumber uangnya."*

Konteks modul: [`docs/kolektor/business-logic.md`](../../kolektor/business-logic.md), [`analisa-alur-kolektor-2.0 .md`](<analisa-alur-kolektor-2.0 .md>) §11.

> Modul ini **satu tingkat di atas** Setoran Kolektor. Rantai uangnya:
> **pelanggan → kolektor → admin → owner/bank.** Hari ini rantai itu putus di anak panah ketiga.

---

## 1. Temuan: jejak uang putus di verifikasi setoran

`CollectorDepositService::verify()` hanya mengubah status, menulis `declared_amount`/`difference`,
dan mencatat audit. **Tidak ada apa pun yang mencatat uang itu masuk kas kantor.**
Pencarian `Saldo Admin` / `saldo_admin` / `admin_balance` di seluruh repo → **nol hasil**.

Jadi keluhan user tepat: begitu setoran kolektor ditutup, uangnya lenyap dari sistem.

### 1.1 Dua sumber uang di tangan admin, saat ini tak pernah bertemu

| Sumber | Jejak data | Kondisi sekarang |
|---|---|---|
| Setoran kolektor | `collector_deposits.declared_amount` (uang fisik yang dihitung admin) | berhenti di sini, tak berlanjut ke mana-mana |
| Bayar manual di kantor | `payments` dengan `collected_by = NULL`, `received_by` = admin, `collector_deposit_id = NULL` | tak pernah masuk setoran apa pun |

### 1.2 Perangkap: `payment_method` bukan cuma tunai

Nilai yang dipakai: `cash | transfer | qris | lainnya` (`PaymentController::store`,
`RecordsCollectorBatch`). Uang **transfer & QRIS sudah ada di bank** — tak pernah lewat tangan
admin. Kalau saldo admin dijumlah tanpa memisahkan metode, admin diminta menyetorkan uang yang
tidak pernah dia pegang. Ini **dua angka berbeda**, bukan satu.

### 1.3 Prinsip yang wajib diikuti

Dari docblock `CollectorBalanceService` (§11.2): **saldo DITURUNKAN dari data, tak boleh ada kolom
saldo yang di-increment.** Kolom seperti itu berhenti benar begitu satu payment di-reject, dan
angka uang yang bohong tak punya alarm. Modul kas admin mengikuti aturan yang sama — tak ada
`users.saldo_kas`.

---

## 2. Rumus Saldo Kas Admin (turunan)

```
tunaiBelumDisetor(admin) =
    Σ collector_deposits { verified_by = admin,
                           status ∈ {terverifikasi, selisih_*},
                           cash_deposit_id IS NULL }
        → declared_amount − max(difference, 0)

  + Σ payments { received_by = admin,
                 collected_by IS NULL,
                 payment_method = 'cash',
                 payment_status = 'valid',
                 collector_deposit_id IS NULL,
                 cash_deposit_id IS NULL }
```

**Kenapa `declared_amount`, bukan `computedAmount()`:** yang berpindah ke brankas kantor adalah
uang fisik yang benar-benar dihitung di meja, bukan angka yang tercatat sistem. Kurang setor
otomatis terpantul di sini tanpa penyesuaian tambahan.

**Kenapa dikurangi `max(difference, 0)`:** `LEBIH_SETOR` berarti kelebihannya dikembalikan fisik
ke kolektor saat itu juga (§B-8 no. 6) — uang itu tak pernah jadi kas kantor.

**Yang WAJIB dikecualikan:**
- setoran `MENUNGGU_VERIFIKASI` — uangnya masih di tas kolektor;
- payment berstatus `ditolak` — uangnya memang tak pernah sah;
- payment non-tunai — lihat §1.2;
- sumber yang `cash_deposit_id`-nya sudah terisi — sudah disetor.

---

## 3. Keputusan user (2026-08-14)

| # | Pertanyaan | Keputusan |
|---|---|---|
| D1 | Saldo kas milik siapa | **Per admin (per user).** Yang memverifikasi setoran kolektor = yang memegang uangnya. Cermin persis kolektor; jelas siapa yang nombok kalau selisih. |
| D2 | Perlakuan transfer/QRIS | **Dipisah, tampil sebagai rekap saja.** Hanya tunai yang jadi kewajiban setor. Non-tunai muncul di card sebagai angka informasi + rincian pelanggan. |
| D3 | Verifikator setoran kas | **Owner + atasan**, lewat Role Matrix. Guard "bukan penyetor sendiri" tetap berlaku untuk semua, termasuk Owner. |
| D4 | Penanganan selisih fase 1 | **Catat selisih + catatan wajib, tanpa pelunasan lintas setoran.** Penutupan hanya lewat hapus buku Owner. |

---

## 4. Rancangan

### 4.1 Migrasi

**Tabel baru `cash_deposits`** — nomor `SETKAS-{tahun}-{4 digit}` (pola TKT/TFOP/TASK/SETOR, unique index):

```
deposit_number, depositor_id (admin), pop_id, status,
declared_amount, difference, note,
channel, bank_name, account_number, reference_no, proof_path,
submitted_at, verified_by, verified_at,
written_off_by, written_off_at, write_off_reason,
idempotency_key
```

**Penautan sumber = dua kolom FK, BUKAN tabel pivot** (repo: "tabel baru kalau kolom cukup"):
- `collector_deposits.cash_deposit_id` (nullable)
- `payments.cash_deposit_id` (nullable) — untuk pembayaran manual di kantor

Dari dua kolom itu seluruh rincian yang diminta Owner lahir sendiri, tanpa menyimpan satu angka
tambahan pun:
- **dari kolektor mana** → `collector_deposits.collector_id`
- **pelanggan siapa & berapa** → `collector_deposits.payments.customer` (relasi & eager-load sudah
  ada di `CollectorWorksheetController::show()`) + `payments.customer` untuk jalur manual
- **total** → dihitung, tak disimpan

### 4.2 Enum `CashDepositStatus`

`menunggu_verifikasi`, `terverifikasi`, `selisih_kurang`, `selisih_lebih`, `dihapus_buku`,
`saldo_awal` (§7.2 — sentinel titik nol, terminal keras).

Dua arah selisih **sengaja dipisah**, mengikuti argumen `DepositStatus::LEBIH_SETOR`: status yang
artinya berbeda tidak boleh berbagi nama. Kurang = kewajiban admin; lebih = uang di brankas yang
belum jelas asalnya.

**Beda dari kolektor:** di sini `selisih_lebih` **BUKAN terminal.** Pada kolektor, lebih setor
dikembalikan fisik saat itu juga sehingga selesai. Pada kas admin tak ada siapa pun yang menerima
pengembalian — uang lebih tetap ada di brankas dan tetap harus dijelaskan, jadi statusnya terbuka
sampai Owner menutupnya.

### 4.3 `AdminCashBalanceService`

```
tunaiBelumDisetor(User $admin): float
nonTunaiRekap(User $admin, periode)        // informasi, bukan kewajiban setor
selisihTerbuka(User $admin): float
isVisibleTo(User $admin, User $viewer): bool   // cermin popFootprint(), all-or-nothing
```

`isVisibleTo()` mengikuti alasan yang sama dengan `CollectorBalanceService`: halaman ini menyajikan
**angka total**, dan total yang diam-diam disaring bukan menyembunyikan baris — ia berbohong.

### 4.4 `CashDepositService`

- `submit()` — setor **seluruh** saldo tunai (bukan sebagian, sama seperti kolektor: tak boleh ada
  saldo mengendap), + pilih channel/bank/no. referensi/bukti. `lockForUpdate`, `idempotency_key`,
  notifikasi **sesudah commit** lewat pola `safelyNotify()`.
- `verify()` — guard: (1) verifikator ≠ penyetor, (2) verifikator wajib bisa melihat **seluruh**
  sumber (`assertVerifierCanSeeAllSources()`, cermin `assertVerifierCanSeeAllPayments()`),
  (3) `note` wajib kalau `difference ≠ 0`. Hitung dengan `Money`, **bukan** float mentah, dan
  **tanpa epsilon karangan**.
- `writeOff()` — Owner, wajib beralasan.

### 4.5 Permission

Feature **baru** `cash_deposit` di `FeatureSeeder`: `view / create / validate / approve`.

**Jangan menumpang `collector_worksheet.*`** — halaman ini mengaudit admin, dan admin tak boleh
menjadi verifikator setorannya sendiri. Menumpang permission lama berarti setiap admin yang bisa
memverifikasi kolektor otomatis bisa menutup setoran kasnya sendiri.

### 4.6 View

- Halaman baru `/cash-deposits`: **Card Saldo** (3 angka terpisah, tak pernah dijumlahkan),
  **Card Setoran** (channel tunai brankas / transfer bank + bank + no. referensi + unggah bukti),
  daftar setoran dengan rincian yang bisa dibuka: per kolektor → per pelanggan → nominal, plus
  baris pembayaran manual.
- **Card Saldo Admin juga tampil di `/collector-worksheet` (index)** — permintaan eksplisit user.
- Bukti transfer disimpan di disk **`local` (privat)**, sama seperti lampiran tiket; unduh hanya
  lewat controller ber-guard permission + POP scope. Jangan ke disk `public`.
- Target POST dirender server-side (`route()`), tidak dirakit di klien (ADHOC-20).
- Semua handler POST/PUT/DELETE redirect (PRG).

---

## 5. Risiko yang wajib ditutup

1. **Dobel hitung.** Filter `collected_by IS NULL` saja tidak cukup — pakai
   `collector_deposit_id IS NULL AND cash_deposit_id IS NULL` juga, supaya payment yang sudah ikut
   setoran kolektor tak terhitung ulang sebagai pembayaran manual.
2. **Reject mundur.** Payment ditolak setelah setoran kas terverifikasi. Modul kolektor menutup ini
   dengan aturan "payment tak bisa keluar dari setoran terverifikasi". Kas butuh aturan setara,
   atau saldo berbohong tanpa alarm.
3. **Setoran kolektor `MENUNGGU_VERIFIKASI` ikut terhitung** → admin diminta menyetorkan uang yang
   masih ada di tas kolektor.
4. **Scope kosong = akses penuh.** `getAllowedPopIds()` mengembalikan array kosong untuk `ALL_POP`.
   Periksa `hasAllPopAccess()` **lebih dulu**, sesuai aturan POP scope di CLAUDE.md.
5. **Admin merangkap kolektor.** §B-8 no. 4 mengizinkannya. Guard "verifikator ≠ penyetor" harus
   ada di sisi kas juga, kalau tidak satu orang bisa menagih → menyetor → memverifikasi → menyetor
   ke kas → memverifikasi lagi.

---

## 6. Rencana test

| Test | Cakupan |
|---|---|
| `AdminCashBalanceTest` | rumus §2 + seluruh pengecualian: setoran belum diverifikasi, payment ditolak, non-tunai, sumber yang sudah tersetor, `LEBIH_SETOR` yang dikembalikan |
| `CashDepositVerificationTest` | selisih dua arah, `note` wajib saat selisih, verifikator ≠ penyetor, hapus buku Owner |
| `CashDepositPopScopeTest` | verifikator yang tak melihat seluruh sumber ditolak; `isVisibleTo()` all-or-nothing |
| `CashLedgerZeroPointTest` | §7: sumber lama tak muncul di saldo; setoran yang belum diverifikasi saat migrasi **muncul** sesudah diverifikasi; sentinel ditolak dari `verify()`/`writeOff()`; migrasi diulang tak menggandakan apa pun; `outstandingShortfall()` kolektor tetap hidup untuk setoran `SELISIH` yang terserap |

---

## 7. Data historis: titik nol pencatatan kas

**Masalah.** Setoran kolektor yang sudah `terverifikasi` sebelum modul ini ada punya
`cash_deposit_id` NULL — persis sama dengan "belum disetor". Begitu modul hidup, saldo kas admin
hari pertama menampilkan **seluruh uang sejak sistem berjalan** sebagai kewajiban setor yang belum
ditunaikan. Angka itu palsu; uangnya sudah lama masuk bank di dunia nyata.

### 7.1 Dua pendekatan yang DITOLAK

**Cutoff berbasis tanggal** (`cash_tracking_start_at` di config atau kolom) — ditolak karena
aturannya jadi **dua**: `cash_deposit_id IS NULL` **DAN** `verified_at >= :start`. Aturan kedua itu
harus diulang di setiap query yang menyentuh kas — saldo, daftar sumber, rekap, laporan Owner.
Cepat atau lambat ada satu yang lupa, dan yang muncul adalah uang lama yang hidup lagi sebagai
kewajiban setor. Repo ini sudah punya preseden persis itu (`TFOP-` digenerate di dua tempat,
dicatat di CLAUDE.md sebagai titik rawan). Ditambah: nilai di `.env`/config tidak ikut backup,
tidak tahan pindah environment, dan bisa berbeda antara dev & produksi.

**Backfill retroaktif** (membuat `cash_deposit` "terverifikasi" per admin per periode) — ditolak
karena mengarang riwayat setoran ke bank yang tak pernah terjadi. Lima tahun lagi tak ada yang bisa
membedakannya dari setoran asli.

### 7.2 Yang DIPAKAI: satu baris sentinel (keputusan user, 2026-08-14)

Migrasi modul membuat **satu** baris di `cash_deposits`:

```
deposit_number  : SETKAS-0000-0000        -- sengaja di luar deret normal
depositor_id    : NULL                    -- tak ada yang mengklaim menyetor
pop_id          : NULL
status          : saldo_awal              -- case enum sendiri, TERMINAL KERAS
declared_amount : 0
note            : "Titik nol pencatatan kas. Transaksi sebelum
                   {tanggal go-live} tidak pernah tercatat di sistem."
```

Lalu mengisi `cash_deposit_id` → baris sentinel pada:
- `collector_deposits` yang **sudah diverifikasi** saat itu (`verified_at IS NOT NULL`);
- `payments` manual tunai valid yang ada saat itu.

**Kenapa ini yang benar:**

1. **Aturan query tetap SATU** — `cash_deposit_id IS NULL`. Tak ada cabang tanggal yang bisa lupa
   dipasang di query berikutnya. Setiap baris lama membawa sendiri alasan kenapa ia tak dihitung,
   eksplisit, di kolomnya sendiri.
2. **Tidak mengarang apa pun.** `declared_amount = 0`, `depositor_id` NULL, status bernama
   `saldo_awal`. Barisnya menyatakan *"sebelum ini tak tercatat"* — bukan *"sudah disetor Rp sekian"*.
3. **Ikut data**, bukan `.env`. Tahan restore, tahan pindah environment, tahan `config:cache`.

`CashDepositStatus::SALDO_AWAL` **terminal keras**: tak bisa diverifikasi, tak bisa dihapus buku,
tak masuk daftar setoran maupun laporan Owner. Guard-nya di `CashDepositService`, bukan cuma di view.

### 7.3 Yang sengaja TIDAK diserap

| Baris | Alasan |
|---|---|
| Setoran kolektor `MENUNGGU_VERIFIKASI` | Uangnya masih di tas kolektor. Sesudah go-live diverifikasi → masuk saldo admin lewat jalur normal. Benar apa adanya. |
| Payment non-tunai | Tak pernah masuk saldo tunai (D2). Menyerapnya justru merusak rekap non-tunai historis. |
| Setoran kolektor `SELISIH` terbuka | `cash_deposit_id` **diisi** (agar tak masuk saldo kas), tapi `outstandingShortfall()` **tetap hidup** — kewajiban kolektor jalur terpisah, tak boleh ikut terhapus. |

### 7.4 Sifat operasional

- **Idempoten** — hanya menyentuh baris yang `cash_deposit_id`-nya NULL. Aman dijalankan ulang.
- **Reversibel** — `down()` mengosongkan pointer + menghapus sentinel.
- **Terlihat** — banner tetap di `/cash-deposits`: *"Pencatatan kas dimulai {tanggal}. Transaksi
  sebelum tanggal ini tidak masuk hitungan."* Owner tak akan salah membaca saldo hari pertama.

---

## 8. Catatan scope

Modul ini **di luar Sprint 8.10** (Audit Trail + Notification System). Dicatat sebagai **ADHOC-37**,
belum dikerjakan, menunggu persetujuan user untuk mulai koding.
