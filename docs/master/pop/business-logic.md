# Business Logic — Master POP

## 1. Hierarki 3 Level

| Level (`type`) | Contoh | Aturan |
|-----------------|--------|--------|
| `pusat` | Nama ISP | Level tertinggi, representasi perusahaan itu sendiri |
| `cabang` | Jetis | Anak dari `pusat`, punya `cid_prefix`+`registration_prefix` sendiri |
| `mini_pop` | C1, C2, C3 | Anak dari `cabang`, `pop_code`-nya nempel prefix cabang + segmen sendiri |

Self-referencing via `parent_id`. **Semua kode ditentukan manual oleh admin** (bukan auto-generate) — keputusan eksplisit dari spesifikasi awal (lihat [archive/spesifikasi-pop-distribusi-cid.md](archive/spesifikasi-pop-distribusi-cid.md) §1).

## 2. Kode-Kode yang Wajib Unik

| Kolom | Unik | Format | Fungsi |
|-------|------|--------|--------|
| `code` | Global (`unique:pops,code`) | Bebas | Identitas umum |
| `pop_code` | Global | `[A-Z0-9]+(-[A-Z0-9]+)*`, di-uppercase paksa (`normalizeIdentifierInput()`) | Resolve segmen Mini POP di CID |
| `registration_prefix` | Tidak divalidasi unik secara eksplisit, tapi konvensinya beda per Cabang | `[A-Z0-9]+`, uppercase | Prefix REQ ID pelanggan |
| `cid_prefix` | Sama, gak divalidasi unik eksplisit | `[A-Z0-9]+`, uppercase | Huruf kode Cabang di CID final |

**Cegah circular parent:** `PopController::edit()`/`update()` exclude semua descendant (rekursif turun) dari daftar pilihan `parent_id` — POP gak bisa jadi induk dari leluhurnya sendiri.

## 3. Generate REQ ID (`Pop::generateRegistrationNumber()`)

Dipanggil saat registrasi pelanggan baru (`CustomerController::store()`).

- Format: `{registration_prefix}{6 digit}` — e.g. `RQ000001`.
- **Permanen** — REQ ID ini nempel ke pelanggan seumur hidup, jadi basis CID setelah aktivasi, dan **muncul lagi apa adanya** kalau pelanggan di-terminate (lihat §5).
- Counter (`PopSequence`, `sequence_type=registration`) di-lock (`lockForUpdate()`) dalam transaction — race-condition safe untuk registrasi concurrent.
- **Self-healing terhadap data import:** sebelum increment, sistem cek angka REQ ID tertinggi yang sudah ada di `customers` untuk POP itu (`MAX(SUBSTRING(customer_code...))`) — kalau counter di `pop_sequences` ternyata lebih rendah dari data riil (misal abis migrasi data lama), counter di-sync naik dulu. Ini mencegah collision kalau data lama pernah insert kode lebih tinggi dari counter yang tercatat.
- Loop `do...while` cek `Customer::where('customer_code', $candidate)->exists()` — extra safety net di luar lock, walau practically jarang kepakai karena lock udah cukup.

## 4. Generate CID — satu rumus, satu pintu (ADHOC-107, 2026-09-29)

**Rumus:** `Pop::generateComplexCid()` — `{cid_prefix Cabang}{segmen Mini POP}{kode Distribusi}{REQ ID}`, e.g. `D2X6CRQ000021`.

- **Segmen Mini POP** — cuma dari Mini POP yang di-assign (`customers.mini_pop_id`): `pop_code` Mini POP dikurangi `cid_prefix`-nya; **`0` kalau belum ada**. Dibaca lewat query by id, bukan relasi (dipanggil dari hook `updating` saat nilainya baru di-set).
- **Kode Distribusi** — `Distribution.code`; **`0` kalau belum ada**.

| Mini POP | Distribusi | CID |
|---|---|---|
| — | — | `C00RQ000631` |
| C1 | — | `C10RQ000631` |
| C1 | 4A | `C14ARQ000631` |

Fallback lama **dihapus** (keputusan user 2026-09-28, K3): `pop_code` Cabang, lalu `customer_technical_details.olt_number` (teks bebas teknisi). `olt_number` tidak ikut dilepas saat pindah POP dan jadi sumber CID campuran (prefix cabang baru + OLT cabang lama). Simulasi DB dev: hanya 3 dari 1.492 CID aktif yang hasil hitung ulangnya berbeda, dan CID itu cuma berubah kalau jaringannya disentuh.

**Pintu:** `App\Services\CustomerCidService` — `resolve()` (hitung), `sync()` (isi `cid` kalau status `active`/`suspended`), `shouldHaveCid()`. **Jangan panggil `generateComplexCid()` langsung dari controller.**

| Penulis CID | Kapan |
|---|---|
| `CustomerObserver::updating()` | Setiap `pop_id`/`mini_pop_id`/`distribution_id` berubah, atau CID masih kosong, dari jalur mana pun (Edit, modal, API, tinker). Tidak dihitung ulang di setiap simpan — CID legacy yang jaringannya tidak disentuh tetap stabil |
| Modal "Atur Mini POP & Distribusi" & API `network-assignment` | `sync()` eksplisit — simpan ulang tanpa perubahan pilihan tetap membetulkan CID yang terlanjur campuran (jalur perbaikan manual) |
| Aktivasi (`CustomerVerificationController::finalVerify()`, `CustomerController::activate()`) & import (`updateQuietly`, melewati observer) | `resolve()` eksplisit — status berubah ke active di sana |

Dulu Edit menghitung `sprintf('%s00%s')` kalau distribusi kosong (Mini POP diabaikan) sementara modal memakai `generateComplexCid()` → CID pelanggan yang sama bolak-balik tiap disimpan dari jalur berbeda (43 pelanggan dev "Mini POP tanpa distribusi").

## 5. Resolve Display ID per Status (`Pop::resolveDisplayId()`)

Aturan tampilan ID pelanggan berbeda tergantung status — ini **bukan** kolom tersimpan, dihitung on-the-fly tiap kali ditampilkan:

| Status Pelanggan | ID yang Ditampilkan | Contoh |
|-------------------|----------------------|--------|
| `terminated`/`failed`/`rejected`/`putus`/`gagal` | REQ ID murni | `RQ001296` |
| `active`/`suspended` + **punya** `distribution_id` & `cid` | CID lengkap | `D2X6CRQ001296_MANGKUJAYAN_DYAHGALUH` |
| `active`/`suspended` + **belum** punya distribusi | Format default | `C00RQ001296` |
| Status lain (registrasi, survey, pemasangan, dst) | Format default | `C00RQ001296` |

**Prinsip kunci:** REQ ID **tidak pernah berubah/hilang** — cuma "dibungkus" beda tergantung status. Saat terminate, sistem gak generate ID baru, cuma balik nampilin REQ ID murni yang dari awal udah ada (`extractBareRegistrationId()` strip prefix `cid_prefix+"00"` dari `customer_code`/CID).

## 6. Peran di RBAC Scope

`Pop.parent_id` juga jadi basis `EffectiveAccessService::resolvePopTree()` — user dengan scope `selected_pop` yang di-assign ke 1 Cabang otomatis dapat akses ke **semua Mini POP di bawahnya** (BFS turun lewat `parent_id`). Lihat [docs/rbac/business-logic.md §6](../../rbac/business-logic.md#6-scope-pop--3-tipe).

## 7. Mini POP — Assignment ke Pelanggan (✅ Fixed 2026-07-07)

**Registrasi cuma pilih Cabang POP** (`Pop::where('type','cabang')`) — Mini POP sengaja **gak** ditawarkan di sini, biar REQ ID/CID gak berantakan sebelum pemasangan kelar (keputusan produk, bukan keterbatasan teknis).

**Jalur utama** Mini POP + Distribusi: **pasca pemasangan/aktivasi**, lewat modal "Atur Mini POP & Distribusi" (klik CID/REQ ID di halaman detail pelanggan → `CustomerNetworkAssignmentController@update`, route `PUT /customers/{customer}/network-assignment`, permission `customers.detail.installation.validate`):

- Dropdown Mini POP di-scope ke anak (`parent_id`) Cabang POP pelanggan.
- Dropdown Distribusi di-scope ke anak Mini POP yang dipilih (`Distribution.pop_id = mini_pop.id`, sesuai struktur data seeder — lihat [docs/master/distribution/business-logic.md](../distribution/business-logic.md)).
- Guard status: ditolak kalau pelanggan masih pra-pemasangan (`registered`…`waiting_installation`) atau `rejected`.
- Bisa diganti-ganti berkali-kali pasca aktivasi (nyusul konfigurasi Mikrotik manual, belum ada integrasi hardware). CID pelanggan `active`/`suspended` disinkronkan tiap simpan (§4) — termasuk simpan ulang tanpa perubahan, yang jadi jalur perbaikan manual CID campuran.

**Jalur kedua — Edit Pelanggan** (ADHOC-104/109/107): dropdown berantai POP → Mini POP → Distribusi, tapi Mini POP & Distribusi **cuma ditulis kalau Cabang ikut dipindah** (selain itu rule `exclude`, nilainya tidak disentuh — nilai legacy di luar hierarki tetap utuh). Dropdown dikunci (`disabled` + ditolak server) kalau:
- pelanggan **pra-pemasangan** — cuma POP Cabang yang boleh diatur; atau
- user **tidak** punya `customers.detail.installation.validate` — permission yang sama dengan modal. Tanpa gerbang ini Edit (cukup `customers.update`) jadi pintu belakang untuk mengubah OLT/ODP & CID. User tanpa izin tetap boleh pindah Cabang; Mini POP & Distribusi cabang lama dilepas, yang baru diatur pemegang izin lewat modal.

Alasan kunci dihitung satu method (`CustomerController::networkLockReason()`) untuk tampilan & validasi.

**Satu sumber aturan** (ADHOC-107 R6) di `NetworkAssignmentService`: daftar status pra-pemasangan `BLOCKED_STATUSES`, serta `miniPopBelongsToPop()` & `distributionBelongsToMiniPop()` — dipakai Edit, modal, endpoint API, dan `CustomerObserver`. Jangan menulis ulang query hierarki di tempat lain.

Riwayat gap sebelum fix ini (Mini POP gak pernah nyambung ke pelanggan sama sekali): [bug.md](bug.md).

## 7a. Pindah POP (ADHOC-104 → ADHOC-107, final 2026-09-29)

Kasus pemicu: pelanggan dipindah JETIS → SANDYA lewat Edit, CID jadi campuran `D1X6…` (prefix SANDYA + segmen OLT JETIS) karena cuma `pop_id` yang berganti. Rancangan & semua keputusan user: [`../../plan/rancangan-pindah-pop-lanjutan.md`](../../plan/rancangan-pindah-pop-lanjutan.md).

**`CustomerObserver::updating()`** — berlaku dari **semua** jalur (Edit, modal, API, tinker), urutannya:
1. **Guard piutang** — kalau `pop_id` berubah dan masih ada tagihan penghalang (lihat tabel), lempar `CustomerRelocationBlockedException`; tidak ada yang berubah. Lapis kedua di belakang validasi Edit.
2. **Hierarki** — `mini_pop.type = mini_pop` & `parent_id = pop_id`, `distribution.pop_id = mini_pop_id`; yang tidak cocok **dilepas, tidak ditebak**. Hanya dicek kalau salah satu kolom jaringan berubah.
3. **Kolektor dilepas** kalau `pop_id` berubah.
4. **CID dibuat ulang** (§4).

**`CustomerObserver::updated()`** — kalau `pop_id` berubah: cabut token QR, pindahkan tagihan bulan berjalan yang belum dibayar.

**Aturan tagihan** — satu sumber: `App\Services\CustomerRelocationService`. Garis batasnya **bulan berjalan**, sama dengan garis kunci buku (`BookPeriod::isLocked()`; bulan berjalan tidak pernah terkunci).

| Data | Perlakuan saat pindah POP |
|---|---|
| REQ ID (`customer_code`) | **Permanen.** Pindah ditolak kalau REQ ID sama sudah dipakai di POP tujuan (unique `pop_id, customer_code`) |
| CID | **Boleh berubah** — dibuat ulang dari POP + Mini POP + Distribusi (pelanggan `active`/`suspended`). CID lama tercatat di `audit_logs` |
| Piutang (`belum_dibayar`/`sebagian`, periode < bulan berjalan = `Invoice::scopePiutang()`) | **Penghalang** — wajib lunas dulu |
| Tagihan bulan berjalan/sesudahnya berstatus `sebagian`, atau yang sudah punya pembayaran `valid` | **Penghalang** — kalau ikut pindah, cicilannya tercatat di cabang lama sementara tagihannya di cabang baru |
| Tagihan bulan berjalan/sesudahnya `belum_dibayar` tanpa pembayaran valid | **Ikut pindah** (per model, tercatat di audit) — pembayarannya masuk cabang baru |
| Tagihan lunas/batal/write-off, baris `payments` | **Tidak pernah disentuh** — laporan pembayaran & piutang tetap milik cabang lama |
| Tagihan bulan berikutnya | Terbit di cabang baru (`GenerateMonthlyInvoicesCommand` memakai `pop_id` pelanggan) |
| Saldo lebih bayar | Terbawa; dipakai untuk tagihan cabang baru (ledger mencatat POP per baris) |
| Mini POP / Distribusi | Dilepas kalau milik cabang lama; dipilih ulang di Edit (dengan izin) / modal |
| Kolektor | **Selalu dilepas** — termasuk yang punya akses ke cabang baru (lihat [kolektor](../../kolektor/business-logic.md)) |
| Username PPPoE | **Tidak diubah otomatis** — peringatan tampil di Detail & Quick Hub kalau tidak diawali CID baru (`CustomerCidService::pppoeMismatchWarning()`) |
| Token QR | Dicabut |
| Tiket/Task/FopTask terbuka | **Belum ditangani** — tetap di POP lama (usulan task terpisah) |

**Validasi Edit (`CustomerController::update()`, rule `pop_id`):** POP tujuan wajib dalam scope user (`Pop::forUser()`), REQ ID tidak bentrok, dan tidak ada tagihan penghalang (pesan menyebut jumlah & total). Form Edit menampilkan keterangan sebelum submit: jumlah tagihan penghalang, atau tagihan bulan berjalan yang akan ikut pindah. Exception observer yang lolos validasi (race) diterjemahkan jadi error `pop_id`, bukan 500.

Aturan penomoran lengkap: [ID_NUMBERING_RULES.md §10](../../ID_NUMBERING_RULES.md). Test: `CustomerPindahPopResetMiniPopTest`, `PindahPopDitolakSelamaAdaPiutangTest`, `CustomerEditJaringanButuhIzinValidasiTest`, `CidSatuRumusEditModalApiTest`, `CustomerEditDistribusiLegacyTidakHilangTest`, `KolektorSelaluDilepasSaatPindahPopTest`, `PppoeTidakCocokCidDiberiPeringatanTest`.

## 8. Hal yang Belum/Sengaja Tidak Divalidasi

- `registration_prefix` dan `cid_prefix` **tidak** ada unique constraint di level DB maupun validasi form — 2 Cabang POP secara teknis bisa punya prefix yang sama, yang akan bikin REQ ID/CID pelanggan dari 2 cabang berbeda kelihatan identik. Ini bukan bug yang ditemukan aktif, tapi celah desain yang perlu disiplin operasional (isi manual dengan hati-hati) sampai divalidasi eksplisit.
- `generateCid()` (bukan `generateComplexCid()`) ditandai `@deprecated`, dipertahankan cuma untuk kompatibilitas panggilan lama — jangan pakai di kode baru.
