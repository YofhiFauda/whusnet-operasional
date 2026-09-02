# SUDAH DIKERJAKAN
## Analisa Saldo Kas Admin & Rancangan Modul Setoran Kas

**Tanggal:** 2026-08-14 (analisa) → **diimplementasikan 2026-08-14** (ADHOC-37)
**Status:** rancangan di bawah sudah **terpasang seluruhnya**. Perbedaan terhadap
rancangan awal dicatat di §6 & §8.
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

## 6. Test — TERPASANG (30 test, 85 assertion, hijau)

| Test | Cakupan |
|---|---|
| `AdminCashBalanceTest` (12) | rumus §2 + seluruh pengecualian: setoran belum diverifikasi, payment ditolak, non-tunai (+ rekap per metode), sumber yang sudah tersetor, `LEBIH_SETOR` yang dikembalikan, dobel hitung jalur manual, saldo kembali nol sesudah setor, transfer tanpa bank ditolak |
| `CashDepositVerificationTest` (11) | selisih dua arah, `note` wajib saat selisih, pemeriksa ≠ penyetor, gerbang POP scope seluruh sumber, sentinel ditolak dari `verify()`/`writeOff()`, penutupan selisih Owner, admin tanpa `validate` ditolak 403 |
| `CashLedgerZeroPointTest` (7) | §7: sumber lama tak muncul di saldo; setoran yang belum diverifikasi saat migrasi **muncul** sesudah diverifikasi; non-tunai tak terserap; migrasi diulang tak menggandakan apa pun; `outstandingShortfall()` kolektor tetap hidup untuk setoran `SELISIH` yang terserap; sentinel tak pernah muncul di daftar |

**Beda dari rencana:** `CashDepositPopScopeTest` **tidak dibuat sebagai file terpisah** — guard
scope-nya hidup di jalur verifikasi, jadi kasusnya ditaruh di `CashDepositVerificationTest`
bersama guard lain yang menjaga transisi yang sama. Bahan skenario ketiganya dibagi lewat
trait `Tests\Concerns\BuildsCashLedgerScenario` supaya ketiga file berangkat dari data identik.

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

## 8. Berkas yang terpasang (ADHOC-37, 2026-08-14)

| Lapis | Berkas |
|---|---|
| Enum | `app/Enums/CashDepositStatus.php`, `app/Enums/CashDepositChannel.php` |
| Migrasi | `2026_08_14_100001_create_cash_deposits_table.php`, `..._100002_add_cash_deposit_id_to_money_sources.php`, `..._100003_seed_cash_ledger_zero_point.php` |
| Model | `app/Models/CashDeposit.php` + `CollectorDeposit::cashReceivedByOffice()`/`cashDeposit()`, `Payment::cashDeposit()` |
| Service | `app/Services/AdminCashBalanceService.php`, `app/Services/CashDepositService.php` |
| Controller | `app/Http/Controllers/CashDepositController.php` |
| Route | `routes/web.php` — `cash-deposits.{index,store,verify,write-off,download}` |
| RBAC | feature `cash_deposit` di `FeatureSeeder`, `config/rbac.php` (4 action + label), `RolePermissionSeeder` (admin & pop_admin: view+create; atasan: view+validate; Owner lewat `*`) |
| View | `resources/views/cash-deposits/index.blade.php`, `resources/views/partials/admin-cash-balance-card.blade.php`, kartu di `collector-worksheet/index.blade.php`, menu sidebar |
| Test | `AdminCashBalanceTest`, `CashDepositVerificationTest`, `CashLedgerZeroPointTest`, trait `Tests\Concerns\BuildsCashLedgerScenario` |

**Pembagian permission yang dipilih (turunan D3):** admin & pop_admin *menyetor* tapi tidak
memeriksa; atasan *memeriksa* tapi tidak menyetor — atasan tak pernah punya saldo sendiri, jadi
mustahil jadi penyetor sekaligus pemeriksa. `approve` (menutup selisih) tetap Owner saja.

**Catatan scope:** modul ini di luar Sprint 8.10 (Audit Trail + Notification System), dikerjakan
sebagai **ADHOC-37** atas permintaan user.

---

## 9. Koreksi letak: aksi setor pindah ke Worksheet Admin (2026-08-18) — **TERPASANG**

**Pemicu:** user — *"setoran dari admin ke owner sudah benar, tapi posisinya kurang benar. Seharusnya ada di halaman Worksheet Admin lewat tombol di Card."*

**Sebab salah letak:** modul ini lahir sebagai halaman sendiri, padahal padanannya di sisi kolektor
tidak begitu — kolektor menyetor dari **halaman kerjanya sendiri** (`/collector-worklist`), bukan
dari halaman setoran terpisah. Admin harus simetris: dia bekerja di `/collector-worksheet`, dan di
situ pula uangnya berpindah tangan (§1 — verifikasi setoran kolektor terjadi persis di halaman itu).

### 9.1 Pembagian baru: aksi vs arsip

| Halaman | Perannya | Audiens |
|---|---|---|
| `/collector-worksheet` | **AKSI** — lihat saldo, setorkan kas | admin, pop_admin |
| `/cash-deposits` | **ARSIP & PEMERIKSAAN** — riwayat, rincian per kolektor/pelanggan, verifikasi, tutup selisih, unduh bukti | Owner, atasan (admin membaca miliknya) |

Yang dibutuhkan admin saat memegang uang cuma satu tombol. Rincian per pelanggan dan riwayat adalah
pekerjaan PEMERIKSA, bukan penyetor — memaksa keduanya ke satu halaman membuat halaman aksi penuh
hal yang tak dipakai saat beraksi.

### 9.2 Keputusan user (2026-08-18)

| # | Pertanyaan | Keputusan |
|---|---|---|
| D5 | Bentuk form setor | **Panel lipat di bawah kartu** (Alpine `x-collapse`) — pola yang sama dengan form verifikasi setoran kolektor yang sudah inline di kartu setoran. Bukan modal/drawer: tak menambah pola baru. |
| D6 | Nasib `/cash-deposits` | **Jadi arsip & pemeriksaan.** Form setor dicabut dari sana — satu aksi, satu tempat. Dua jalur UI untuk satu aksi adalah cara tercepat keduanya menyimpang. |
| D7 | Menu sidebar | **Tetap**, digerbang `cash_deposit.view`. Owner/atasan butuh jalan langsung ke antrean pemeriksaan tanpa lewat halaman kolektor. |

### 9.3 Perubahan

1. **`partials/admin-cash-balance-card.blade.php`** — parameter baru `$dapatSetor` & `$sumberCount`.
   Bila `$dapatSetor`, kartu Tunai tumbuh: baris "N sumber" + tombol **Setorkan Kas** yang membuka
   panel. `/cash-deposits` memanggilnya dengan `dapatSetor = false`, jadi satu berkas tetap melayani
   dua halaman dan angkanya mustahil menyimpang.
2. **`partials/cash-deposit-form.blade.php`** (baru) — isi panel lipat. Dipisah supaya
   `collector-worksheet/index.blade.php` tidak membengkak.
3. **`CollectorWorksheetController::index()`** — tambah `$kasSumberCount` (dua `count()`, TIDAK
   menarik baris; halaman ini sudah berat) dan `$kasIdempotencyKey` per pemuatan halaman.
4. **`CashDepositController`** — `index()` melepas `$idempotencyKey`; `store()` menerima
   `redirect_to` dan memilih tujuan dari **daftar tertutup di server** (`worksheet` →
   `collector-worksheet.index`, selain itu → `cash-deposits.index`). Bukan URL mentah dari klien —
   itu open-redirect.
5. **`cash-deposits/index.blade.php`** — blok form setor dicabut; sisanya utuh.

**Tidak berubah:** service, enum, migrasi, model, route, permission, guard POP scope, sentinel titik
nol. Ini murni pemindahan titik masuk UI.

### 9.4 Test tambahan

- setor dengan `redirect_to=worksheet` mendarat di Worksheet Admin; tanpa parameter tetap ke
  `/cash-deposits`; nilai `redirect_to` asing TIDAK PERNAH jadi tujuan (jaring open-redirect);
- tombol setor tidak dirender saat saldo nol maupun untuk user tanpa `cash_deposit.create`;
- `/cash-deposits` tidak lagi memuat form setor.

**Hasil:** enam test baru di `AdminCashBalanceTest` (bagian "Letak aksi setor"), total **40 test kas
hijau** (110 assertion). Regresi Worksheet/Kolektor/PostTarget/RBAC: 183 passed, 3 skipped.

---

## 10. Dua tingkat rincian: penyetor vs pemeriksa (2026-08-18) — **TERPASANG**

**Pemicu:** user — *"rincian ada dua: rincian untuk Admin dan rincian untuk orang yang punya akses
Setoran Kas, karena kalau admin mengakses halaman itu isinya data sensitif."*

**Sebab:** sesudah §9, `/cash-deposits` masih digerbang `cash_deposit.view` yang ikut dipegang
admin — padahal isinya pandangan PEMERIKSA: posisi kas admin mana pun dalam scope, antrean
pemeriksaan lintas penyetor, dan rincian sampai tingkat pelanggan. Admin yang cuma menyetor tak
berkepentingan membaca sebaran uang rekan-rekannya.

### 10.1 Pembagian

| Pandangan | Permission | Tempat | Isi |
|---|---|---|---|
| **Penyetor** | `cash_deposit.create` | Worksheet Admin | Kartu saldo + form setor + **Riwayat Setoran Kas Anda** (nomor, tanggal, channel/bank, total tercatat, uang diterima, selisih, pemeriksa, unduh bukti sendiri) |
| **Pemeriksa** | `cash_deposit.view` | `/cash-deposits` | Seluruh isi di atas untuk admin mana pun dalam scope + rincian sumber sampai **nama pelanggan** + antrean pemeriksaan + pemilih pemegang kas |

Riwayat penyetor **tidak memuat nama pelanggan maupun nama kolektor**: pertanyaan admin di sini
cuma *"setoran saya sudah diperiksa belum, hasilnya apa"*. Selisih tetap ditampilkan penuh — itu
kewajiban (atau kelebihan) yang menyangkut dirinya langsung, bukan temuan internal pemeriksa.

### 10.2 Perubahan

1. **`RolePermissionSeeder`** — `cash_deposit.view` **dicabut** dari `admin` & `pop_admin`; keduanya
   tinggal `cash_deposit.create`. Owner/atasan tetap memegang `view`.
2. **`routes/web.php`** — `/cash-deposits` tetap `cash_deposit.view`. Rute **unduh bukti** dipisah ke
   `permission:cash_deposit.view|cash_deposit.create`: penyetor harus tetap bisa mengambil kembali
   berkas yang dia unggah sendiri.
3. **`CashDepositController::download()`** — pemegang `create` tanpa `view` dibatasi ke setoran
   miliknya sendiri. Tanpa itu, rute yang sengaja dibuka untuknya berubah jadi jalan membaca bukti
   setoran admin lain, termasuk nomor rekening tujuan yang bukan urusannya.
4. **`partials/admin-cash-deposit-history.blade.php`** (baru) + `$kasRiwayat` di
   `CollectorWorksheetController::index()` — 5 baris per halaman, eager-load hanya kolom nominal
   (`declared_amount`, `difference`, `amount`) supaya total tetap terhitung tanpa N+1 dan tanpa
   menarik data pelanggan.
5. **Tautan** ke `/cash-deposits` (kartu & form) hanya dirender untuk pemegang `view` — menawarkannya
   ke admin biasa cuma melahirkan 403 sesudah diklik. Menu sidebar ikut hilang sendiri karena sudah
   digerbang `cash_deposit.view`.

**Konsekuensi D7 (§9.2) berubah:** sidebar "Setoran Kas" kini hanya muncul untuk pemeriksa. Admin
masuk lewat Worksheet Admin, dan tak ada lagi halaman yang perlu dia buka untuk urusan kasnya.

### 10.3 Test

Empat test baru di `AdminCashBalanceTest`: admin penyetor **403** di `/cash-deposits`; riwayatnya
tampil di Worksheet Admin **tanpa nama pelanggan**; riwayat hanya memuat setoran sendiri; penyetor
**tidak bisa** mengunduh bukti setoran admin lain. Total **44 test kas hijau** (122 assertion);
regresi Worksheet/Kolektor/PostTarget/RBAC 197 passed, 3 skipped.

### 10.4 Susulan: fitur ini harus dikenali Role Matrix (2026-08-18)

Temuan lapangan: di DB produksi user, `FEATURE ADA? false` dan `PERMISSION ROW []` — feature
`cash_deposit` belum pernah di-seed, jadi barisnya tak ada di Matrix dan admin mustahil diberi hak
apa pun. Saldonya sendiri sudah benar (Rp 12.501.304 atas nama admin yang memverifikasi
`SETOR-2026-0010`). Owner tetap melihat semuanya karena `getPermissions()` mengembalikan `['*']`
untuk role owner — dia lolos tanpa butuh satu baris permission pun.

Perintah pemasangan di lingkungan berjalan: `php artisan db:seed --class=FeatureSeeder` lalu
`php artisan rbac:generate-permissions`. **Jangan** `RolePermissionSeeder` — baris terakhirnya
`$role->permissions()->sync()` yang menimpa seluruh centang Matrix yang sudah diatur manual.

Dua perbaikan kode ikut dipasang:

1. **`resources/views/roles/matrix.blade.php`** — `cash_deposit` didaftarkan ke grup
   *Tagihan & Keuangan* (sebelumnya jatuh ke "Modul Tambahan Lainnya"), plus nama ramah
   *"Setoran Kas Admin ke Owner / Bank"* dan deskripsi per-permission.
2. **Auto-grant `view` dikecualikan** — `config/rbac.php > view_autogrant_exempt` +
   `RoleManagementService::syncPermissions()`. Pada hampir semua fitur, mencentang aksi anak wajar
   ikut memberi hak membuka halamannya; di sini TIDAK, karena `cash_deposit.view` adalah pandangan
   PEMERIKSA. Tanpa pengecualian ini, satu centang "Setor" diam-diam membatalkan pemisahan dua
   tingkat rincian yang justru jadi tujuan §10. Dijaga
   `test_mencentang_hak_setor_tidak_ikut_memberi_pandangan_pemeriksa`.

---

## 11. Koreksi alur: `/cash-deposits` adalah worksheet PENERIMA (2026-08-18) — **TERPASANG**

**Pemicu:** user — *"Setoran Kas itu Worksheet dari Owner yang menerima uang setoran dari Admin.
Admin tidak boleh akses halaman ini, dan kalau saldo admin disetorkan ke Owner maka saldo itu
pindah ke Owner."*

**Kesalahan yang dikoreksi:** modul ini dibangun seolah `/cash-deposits` milik penyetor, padahal ia
milik PENERIMA. Akibatnya rantai uang putus untuk **kedua kalinya** — persis penyakit §1, satu
tingkat lebih tinggi: sesudah Owner memeriksa setoran, saldo admin turun (benar, uangnya sudah
diserahkan) tapi tak ada siapa pun yang menerimanya di sistem.

```
pelanggan → kolektor → admin → owner/bank
                ^         ^        ^
              §11 lama   §1      §11 baru
             (sudah OK) (sudah OK)  ← yang ditutup sekarang
```

### 11.1 `OwnerCashBalanceService` — tiga angka, tak pernah dijumlahkan

| Angka | Sumber | Kenapa terpisah |
|---|---|---|
| **Brankas (tunai)** | `cash_deposits` diperiksa oleh viewer, `channel = tunai_brankas` → `declared_amount − max(difference, 0)` | uang fisik yang benar-benar dipegang |
| **Masuk Bank** | idem, `channel = transfer_bank`, dibatasi periode | uangnya di rekening, tak pernah lewat tangan Owner; menjumlahkannya melahirkan "tunai" yang mustahil dihitung ulang di meja |
| **Dalam Perjalanan** | setoran `menunggu_verifikasi` (POP-scoped), pakai `computedAmount()` | klaim satu pihak, bukan kas — dicatat terpisah supaya selisih ketahuan saat dihitung, bukan tertelan lebih dulu |

Yang masuk brankas adalah **yang dihitung Owner** (`declared_amount`), bukan yang diklaim admin —
kurang setor terpantul sendiri. Kelebihan dikurangkan lagi karena dikembalikan fisik saat itu juga,
aturan yang sama dengan dua tingkat sebelumnya.

**`DIHAPUS_BUKU` IKUT dihitung di sini**, berbeda dari perlakuan setoran kolektor di sisi admin.
Beda arti: di sana hapus buku berarti uangnya tak pernah sampai; di sini yang ditutup adalah
SELISIHNYA, sedangkan uang fisik yang sudah dihitung Owner tetap ada di brankasnya.

### 11.2 Halaman

`/cash-deposits` kini berjudul **"Setoran Kas — Penerimaan dari Admin"**, dibuka dengan kartu
**Kas Diterima** milik pembacanya, baru disusul posisi kas admin yang sedang diperiksa. Admin
penyetor tetap 403 (§10) — dan sekarang alasannya utuh: halaman itu memang bukan halamannya.

### 11.3 Test

`OwnerCashBalanceTest` (9): saldo benar-benar **pindah** admin → Owner sesudah diperiksa (dan tidak
lebih cepat dari itu); brankas mengikuti uang yang dihitung, bukan yang diklaim; kelebihan tak
mengendap; transfer masuk bank bukan brankas; kas melekat ke pemeriksa (atasan ≠ Owner); halaman
tertutup untuk admin; kartu tampil untuk penerima; "dalam perjalanan" ikut POP scope pembaca;
sentinel titik nol tak pernah terhitung.

---

## 12. `/cash-deposits` dibersihkan jadi murni lembar kerja penerima (2026-08-18) — **TERPASANG**

**Pemicu:** user — *"Setoran Kas murni lembar kerja Owner untuk mengelola saldo yang dapat
diverifikasi dan dianalisa, sehingga admin yang setor tidak mempunyai tampilan apa pun di sini."*

**Yang masih salah sesudah §11:** halaman itu tetap menampilkan **saldo admin yang BELUM
disetor** — kartu posisi kas admin, rincian sumber dari `unsettledCollectorDepositsQuery()`, dan
pemilih "buka kas admin siapa". Itu isi Worksheet Admin yang dipindahkan ke halaman yang salah:
uang yang belum diserahkan bukan urusan penerima, dan tak ada satu pun keputusan di halaman ini
yang bergantung padanya.

Yang seharusnya ditampilkan bukan saldo admin, melainkan **isi setoran yang masuk**.

### 12.1 Isi halaman sekarang

1. **Card analisa penerimaan** (§11.1) — Brankas tunai · Masuk Bank per rekening · Dalam Perjalanan.
2. **Setoran masuk** dari admin mana pun dalam scope POP pembaca, yang `menunggu_verifikasi`
   selalu di atas (satu-satunya baris yang menuntut tindakan), + filter Semua/Menunggu/Selesai.
3. **Rincian sumber per setoran**, bisa dibuka: tiap setoran kolektor → kolektornya siapa →
   pelanggan yang bayar & nominalnya; plus blok pembayaran tunai di loket. Inilah permintaan asli
   user — *"atasan atau Owner tahu jelas dari mana sumber uangnya"*.
4. **Aksi**: verifikasi (pemeriksa ≠ penyetor), tutup selisih (Owner), unduh bukti.

### 12.2 Yang dicabut

| Dicabut | Alasan |
|---|---|
| Kartu posisi kas admin + rincian saldo belum disetor | isi Worksheet Admin, bukan halaman penerima |
| Pemilih `admin_id` di header | tak ada lagi "kas siapa yang dibuka" — halaman ini menampilkan semua setoran masuk sekaligus |
| `AdminCashBalanceService::cashHolderIds()` | jadi kode mati begitu pemilih hilang; dibuang bersama tiga test yang menjaganya |

### 12.3 Test

`OwnerCashBalanceTest` naik jadi 11: ditambah *rincian sumber tampil sampai nama pelanggan* (dari
kolektor mana, pelanggan siapa, total berapa) dan *setoran seluruh admin dalam scope tampil di satu
halaman*. Total kas **54 test hijau** (151 assertion).
