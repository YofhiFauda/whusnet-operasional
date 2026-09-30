# Rancangan: Pindah POP — Perbaikan Lanjutan ADHOC-104 (ADHOC-107)

**Status:** ✅ DIIMPLEMENTASI 2026-09-29 (ADHOC-107). Di luar sprint aktif (Sprint 8.10), dibuat atas permintaan eksplisit user. Semua keputusan (K2–K8) dijawab user per 2026-09-28. Penyimpangan dari rancangan saat implementasi dicatat di §11.

**Alur kerja (instruksi user 2026-09-28):** analisa → dokumentasi → implementasi **hanya setelah diinstruksikan user**.

**Riwayat dokumen:**

| Tanggal | Perubahan |
|---|---|
| 2026-09-28 | Dibuat dari code review (high) atas pekerjaan ADHOC-104 di working tree (belum di-commit). |
| 2026-09-28 | K2–K4 diputuskan user. |
| 2026-09-28 | **Revisi keputusan keuangan** setelah user konfirmasi ke pihak terkait: pindah POP wajib lunas dulu, tagihan & laporan lama tetap di cabang lama, tagihan bulanan berikutnya di cabang baru, kolektor selalu dilepas (§3). Keputusan K1 & K5 sebelumnya **dibatalkan** (jejak di §4.4). |
| 2026-09-28 | K6 & K7 diputuskan user: yang wajib lunas = **piutang** (`scopePiutang`, tunggakan bulan-bulan sebelumnya); **tagihan bulan ini ikut pindah** dan dibayar ke cabang baru; saldo lebih bayar terbawa. Muncul keputusan turunan **K8** (tagihan bulan ini yang sudah dicicil), §4.6. |
| 2026-09-28 | K8 diputuskan user (A: tagihan bulan ini yang sudah dicicil wajib lunas dulu). Rancangan final. |
| 2026-09-29 | Diimplementasi. Kode ADHOC-104 + ADHOC-109 (commit `bb15742`) jadi titik awal; lihat §11. |

**Terkait:**
- [`../ID_NUMBERING_RULES.md`](../ID_NUMBERING_RULES.md) §10 — aturan pindah POP (REQ ID permanen, CID boleh berubah)
- [`../master/pop/business-logic.md`](../master/pop/business-logic.md) §4 (Generate CID), §7 (Mini POP), §7a (Pindah POP)
- [`../master/distribution/business-logic.md`](../master/distribution/business-logic.md) §3 — Distribusi seharusnya di Mini POP, tapi tidak dipaksa
- [`../kolektor/business-logic.md`](../kolektor/business-logic.md) §8 — kolektor saat pindah POP
- [`../billing-pembayaran/README.md`](../billing-pembayaran/README.md) §Pelanggan Pindah POP
- `docs/TASKS.md` ADHOC-104, ADHOC-107

---

## 1. Latar Belakang

ADHOC-104 memperbaiki bug pindah POP lewat Edit Pelanggan (kasus JETIS → SANDYA, CID `C1X4…` jadi `D1X6…`). Yang sudah ada di working tree:

| Bagian | Lokasi | Nasib di ADHOC-107 |
|---|---|---|
| Invariant hierarki Cabang → Mini POP → Distribusi dari semua jalur | `CustomerObserver::updating()` | Dipertahankan, aturannya dipusatkan (R6) |
| Dropdown berantai POP → Mini POP → Distribusi di Edit, di-scope `Pop::forUser()` | `CustomerController::edit()`, `customers/edit.blade.php` | Dipertahankan + opsi legacy (R1) + gerbang izin (R2) |
| POP tujuan wajib dalam scope user, bentrok REQ ID ditolak (bukan 500) | `CustomerController::update()` | Dipertahankan |
| Pra-pemasangan cuma POP yang boleh diatur | `update()` + `NetworkAssignmentService::BLOCKED_STATUSES` | Dipertahankan, digabung ke R2 |
| CID baru dicatat di audit log (`update()` ganti `updateQuietly()`) | `update()` langkah 1b | Dipindah ke observer (R3) |
| Tagihan outstanding ikut pindah POP | `CustomerObserver::moveOutstandingInvoicesToNewPop()` | **Diganti** — hanya tagihan bulan berjalan yang pindah, ditambah guard piutang lunas dulu (R4) |
| Kolektor tanpa akses POP baru dilepas | `CustomerObserver::releaseCollectorOutsideNewPop()` | **Diubah** — kolektor selalu dilepas (R8) |
| Test | `tests/Feature/CustomerPindahPopResetMiniPopTest.php` (18) | Disesuaikan (§8) |

Review menemukan arah perbaikannya benar, tapi ada **satu regresi berat** dan beberapa celah yang harus ditutup sebelum ADHOC-104 layak di-commit.

## 2. Temuan Review

| # | Tingkat | Temuan | Bukti di kode |
|---|---|---|---|
| T1 | **Berat (regresi)** | Edit menghapus distribusi pelanggan legacy tanpa pesan apa pun, dan CID ikut berubah | Import membuat distribusi yang menempel ke **Cabang** (`CustomerController` ±2752: `Distribution::firstOrCreate(['code'], ['pop_id' => $pop->id])`). Dropdown Edit cuma memuat distribusi anak Mini POP → select terkirim `''` → `distribution_id` NULL → CID jadi `C00RQ…` |
| T2 | Tinggi | Celah permission: Edit bisa mengubah Mini POP/Distribusi & CID tanpa `customers.detail.installation.validate` | Modal digerbang permission itu (`routes/web.php` ±934–943); Edit cuma `customers.update` |
| T3 | Tinggi | CID bolak-balik antara Edit dan modal | Edit: `sprintf('%s00%s')` kalau distribusi kosong (Mini POP diabaikan). Modal/API: `generateComplexCid($c, null)` → `{prefix}{segMini}0{REQ}` |
| T4 | Tinggi | Pindah POP lewat import/tinker meninggalkan CID basi | Observer melepas Mini POP/Distribusi, tapi CID cuma dibuat ulang di controller Edit |
| T5 | Sedang | Tagihan outstanding (termasuk periode tutup buku) ikut pindah POP → laporan & piutang dua cabang tidak sinambung | `moveOutstandingInvoicesToNewPop()`. Laporan bulanan menghitung live per `invoices.pop_id` → *drift* di periode tertutup, piutang pembuka loncat di kedua cabang. **Diselesaikan oleh keputusan §3 no. 3–4 (R4)** |
| T6 | Sedang | PPPoE username (`{CID}_{DESA}_{NAMA}`) tidak ikut berubah saat CID berubah | `customer_devices.pppoe_username`; form Edit mengirim ulang nilai lama |
| T7 | Sedang | Tiket/Task/FopTask terbuka tetap di POP lama; kolektor yang dilepas tidak diberi notifikasi | `pop_id` ada di `$fillable` Ticket/FopTask/Task |
| T8 | Rendah | Validasi distribusi membaca `mini_pop_id` dari request, bukan nilai efektif | Request parsial tanpa `mini_pop_id` ditolak walau DB sudah konsisten |
| T9 | Rendah | Aturan tersebar: `BLOCKED_STATUSES` masih punya salinan private di modal; aturan hierarki ditulis di 4 tempat (closure Edit, modal, `NetworkAssignmentService`, observer) | `CustomerNetworkAssignmentController::BLOCKED_STATUSES` |
| T10 | Arsitektur | Penulis CID tersebar dengan aturan berbeda | Edit langkah 1b, modal, `NetworkAssignmentService`, `CustomerVerificationController::finalVerify()`, `CustomerController::activate()`, import (±2960), `BackfillCidDefaultDistributionSegmentCommand` |

## 3. Keputusan Terkunci (dari user, jangan diubah tanpa user)

| # | Keputusan | Tanggal |
|---|---|---|
| 1 | **REQ ID (`customer_code`) permanen.** CID = POP + Mini POP + Kode Distribusi, boleh berubah. | 2026-09-26 |
| 2 | **Pra-pemasangan cuma POP Cabang yang boleh diatur.** Mini POP & Distribusi baru boleh setelah pemasangan dimulai. | 2026-09-26 |
| 3 | **Pelanggan yang punya piutang wajib melunasi dulu sebelum bisa pindah POP.** "Piutang" = istilah sistem saat ini (`Invoice::scopePiutang()`): tagihan `belum_dibayar`/`sebagian` dari periode **sebelum** bulan berjalan (K6). | 2026-09-28 (revisi, K6) |
| 4 | **Laporan pembayaran & piutang tetap di cabang lama; tagihan bulan ini dan seterusnya di cabang baru.** Tagihan periode lalu (sudah lunas, karena guard no. 3) tidak pernah dipindah `pop_id`-nya. Tagihan **bulan berjalan** yang belum lunas **ikut pindah** ke cabang baru, sehingga pembayarannya masuk cabang baru (K6). | 2026-09-28 (revisi, K6) |
| 5 | **Kolektor selalu dilepas saat pindah POP.** Contoh: pelanggan A di JETIS dengan kolektor Wahyu → pindah ke SANDYA → lepas dari Wahyu, tidak terikat kolektor siapa pun sampai admin SANDYA meng-assign kolektor baru. | 2026-09-28 (revisi) |
| 6 | **PPPoE username tidak diubah otomatis**; tampilkan peringatan kalau tidak cocok dengan CID (K2). | 2026-09-28 |
| 7 | **Satu rumus CID**: segmen Mini POP/Distribusi kosong = `'0'`, fallback `pop_code` Cabang & `olt_number` dihapus (K3). | 2026-09-28 |
| 8 | **Distribusi legacy dipertahankan apa adanya**, tidak dimigrasi otomatis ke Mini POP (K4). | 2026-09-28 |
| 9 | Data lama yang sudah terlanjur CID campuran diperbaiki **manual** oleh user. | 2026-09-26 |
| 10 | **Saldo lebih bayar terbawa** ke cabang baru dan dipakai membayar tagihan di sana, tanpa guard (K7 = A). | 2026-09-28 |

### Kenapa keputusan 3–5 menyelesaikan masalah keuangan sekaligus

Garis batasnya **bulan berjalan**, dan garis itu sama dengan garis kunci buku sistem: periode dikunci menurut kalender (`BookPeriod::isLocked()`), dan **bulan berjalan tidak pernah terkunci** (`closePeriod()` menolak "periode yang masih berjalan"). Akibatnya:

- **Periode lalu (sudah/boleh terkunci):** piutang wajib lunas dulu (no. 3), jadi semua tagihan periode lalu sudah lunas saat pindah dan **tetap** ber-`pop_id` cabang lama → laporan bulanan, snapshot tutup buku, dan saldo piutang cabang lama **tidak berubah**, tanpa *drift*.
- **Periode berjalan (belum terkunci):** tagihan yang belum lunas ikut pindah ke cabang baru (no. 4). Tidak ada snapshot yang terusik karena periode ini belum bisa ditutup. Pembayarannya otomatis masuk cabang baru (`payments.pop_id = invoice.pop_id` di `CollectorPaymentService`).
- **Pembayaran lama** tetap ber-`pop_id` cabang lama (tidak pernah disentuh).
- **Tagihan bulan berikutnya** terbit di cabang baru dengan sendirinya: `GenerateMonthlyInvoicesCommand` sudah memakai `$customer->pop_id` (baris ±147).
- **Tidak ada tagihan yatim**: piutang lama sudah lunas; tagihan bulan ini ikut ke cabang baru, tempat kolektor barunya nanti di-assign. Masalah yang dulu memicu K5 hilang.
- **Kolektor lama dilepas** tanpa meninggalkan tagihan yang belum tertagih di cabangnya.

## 4. Keputusan Teknis Pendukung

### 4.1 K2 — PPPoE username saat CID berubah (✅ opsi A)

| Opsi | Perilaku | Konsekuensi |
|---|---|---|
| **A (dipilih)** | Tidak diubah otomatis. Tampilkan peringatan kalau prefiks username ≠ CID. | Aman terhadap Mikrotik (belum ada integrasi hardware). NOC mengubah manual di router lalu di sistem. |
| B | Dibuat ulang otomatis bersama CID. | Akun di Mikrotik jadi tidak cocok sampai diubah manual → risiko pelanggan tidak bisa login PPPoE. |

### 4.2 K3 — Format CID tunggal (✅ disetujui)

Satu rumus, `Pop::generateComplexCid()`:
- **Segmen Mini POP** = dari `mini_pop.pop_code`; kalau Mini POP kosong → `'0'`.
- **Segmen Distribusi** = `distribution.code`; kalau kosong → `'0'`.

| Mini POP | Distribusi | CID |
|---|---|---|
| — | — | `C00RQ000631` (sama dengan format default sekarang) |
| C1 | — | `C10RQ000631` |
| C1 | 4A | `C14ARQ000631` |

Fallback lama `pop.pop_code` Cabang dan `customerTechnicalDetail.olt_number` **dihapus dari rumus**. `olt_number` adalah sumber CID campuran T4: isinya teks bebas dari cabang lama dan tidak ikut dilepas saat pindah POP. CID legacy yang bergantung pada fallback itu tidak berubah selama jaringannya tidak disentuh (R3). Mengubah §4 `master/pop/business-logic.md`.

### 4.3 K4 — Distribusi legacy (✅ disetujui)

Dipertahankan apa adanya (*grandfathered*, R1), **tidak** dimigrasi otomatis ke Mini POP — distribusi legacy tidak menyimpan Mini POP asalnya, migrasi otomatis = menebak OLT. Migrasi massal kalau diperlukan jadi task terpisah dengan audit read-only lebih dulu.

### 4.4 Jejak keputusan yang dibatalkan (2026-09-28)

Dipertahankan supaya alasan perubahan tidak hilang. **Jangan diimplementasi.**

| Keputusan lama | Isi | Kenapa dibatalkan |
|---|---|---|
| ADHOC-104 | Tagihan outstanding ikut pindah POP | Laporan cabang lama *drift*, piutang pembuka loncat di dua cabang, cicilan lama tetap di cabang lama → rekonsiliasi pecah |
| K1 = A | Outstanding periode terbuka pindah, periode tertutup tetap | Tunggakan periode tertutup yatim dari worklist kolektor mana pun (kolektor SANDYA di luar scope JETIS, kolektor JETIS sudah dilepas) |
| K5 | Jalur penagihan tunggakan di POP lama (admin JETIS / tahan kolektor / pengecualian scope) | Tidak diperlukan lagi: pelanggan wajib lunas dulu sebelum pindah |
| ADHOC-104 | Kolektor dilepas **hanya kalau** tidak punya akses POP baru | Diganti keputusan §3 no. 5: selalu dilepas |

### 4.5 Analisa Data (DB dev, read-only, 2026-09-28)

Diukur lewat tinker read-only di container `whusnet-app`. **Angka produksi bisa berbeda** — ulangi query yang sama di produksi sebelum rilis.

| Ukuran | Nilai | Arti untuk rancangan |
|---|---|---|
| Pelanggan `active`/`suspended` | 1.492 | Populasi yang CID-nya dibuat ulang otomatis (R3) |
| Pelanggan punya distribusi | 1.206 | — |
| Distribusi yang menempel langsung ke Cabang | **0 dari 14** | T1 di data ini tidak menyentuh pelanggan import massal; jalur import (±2752) tetap *bisa* membuatnya ke depan, jadi R1 tetap perlu |
| Pelanggan punya distribusi tapi `mini_pop_id` NULL | **7** | Populasi nyata T1 hari ini |
| Aktif dengan Mini POP tapi tanpa distribusi | **43** | **Bug lama (sebelum ADHOC-104)**: CID tersimpan `C10RQ…`, langkah 1b Edit menghitung `C00RQ…` → CID berubah setiap kali di-Edit. Tertutup oleh R3 |
| CID yang berubah kalau dihitung ulang dengan rumus K3 | **3 dari 1.492** | Dampak K3 sangat kecil; ketiganya bergantung fallback `olt_number` |
| `customer_technical_details.olt_number` terisi | 7 | Fallback yang dihapus K3 cuma dipakai segelintir pelanggan |
| Pelanggan pra-pemasangan yang sudah punya Mini POP/Distribusi | 4 | R2: nilai lama dipertahankan saat Edit |
| `period_closings` | 0 | Tidak lagi relevan untuk pindah POP (tidak ada tagihan yang dipindah) |
| PPPoE username terisi | 7, **semuanya tidak cocok CID** | Termasuk kasus nyata: CID `D1X6ARQ002022` dengan PPPoE `C1X4ARQ002022_TURI_WALUYOMBER`. Ketujuhnya memakai username yang sama persis → data dummy |

### 4.6 K6, K7 & K8 (✅ diputuskan user 2026-09-28)

#### K6 — Definisi "piutang" untuk guard lunas dulu (✅ `scopePiutang` + tagihan bulan ini ikut pindah)

Jawaban user: *"yang namanya piutang itu semua tagihan yang belum lunas, hanya tunggakan dari bulan-bulan sebelumnya, sesuai istilah 'piutang' di sistem saat ini. Untuk tagihan di bulan ini dengan cabang baru maka pembayaran akan masuk ke cabang baru."*

Dibaca sebagai (konfirmasi ulang saat implementasi kalau ada keraguan):

| Kelompok tagihan belum lunas | Perlakuan saat pindah POP |
|---|---|
| Periode **sebelum** bulan berjalan (`scopePiutang`) | **Guard**: pindah ditolak sampai lunas |
| Periode **bulan berjalan** | **Ikut pindah** ke cabang baru; pembayarannya masuk cabang baru — **hanya yang berstatus `belum_dibayar`**; yang sudah dicicil (`sebagian`) wajib lunas dulu (K8 = A) |
| Periode **setelah** bulan berjalan (mis. tagihan manual bertanggal maju) | Diperlakukan sama dengan bulan berjalan: ikut pindah (bukan piutang, milik masa depan cabang baru). Di dev: 0 baris |

Istilah di kode yang dipakai:

| Istilah | Isi | Sumber |
|---|---|---|
| **Outstanding** | `belum_dibayar`/`sebagian`, periode mana pun | `Invoice::OUTSTANDING_STATUSES` |
| **Piutang** | Outstanding dengan `billing_period` < bulan berjalan | `Invoice::scopePiutang()` / `isPiutang()` |

Opsi yang dibandingkan saat memutuskan (jejak): A = semua outstanding wajib lunas; B = hanya piutang, tagihan bulan ini tertinggal di cabang lama (yatim). Keputusan user mengambil guard B **tapi memindah** tagihan bulan ini, sehingga masalah yatim opsi B tidak terjadi.

#### K7 — Saldo lebih bayar pelanggan saat pindah POP (✅ A: terbawa)

`customer_balance_mutations` mencatat `pop_id` per baris (`CustomerBalanceService`): kredit lebih bayar ber-`pop_id` pembayaran asal (cabang lama), debit pemakaian ber-`pop_id` tagihan yang dibayar (baris ±329). Setelah pindah, saldo dari JETIS otomatis dipakai membayar tagihan SANDYA (`billing:apply-balance`) dengan debit ber-`pop_id` SANDYA. **Tidak perlu kode baru**; cukup test yang mengunci perilaku ini dan catatan di dokumen billing. Opsi B (tolak pindah selama saldo > 0) ditolak karena bisa menahan pelanggan berbulan-bulan.

#### 4.7 Analisa Data Tagihan (DB dev, read-only, 2026-09-28)

| Tagihan outstanding | Jumlah | Perlakuan |
|---|---|---|
| `bulanan`, periode lalu (piutang) | 1.468 | Guard: wajib lunas dulu |
| `bulanan`, periode bulan ini | 1.467 | Ikut pindah |
| `awal` (biaya pasang), periode bulan ini | 3 | Ikut pindah |
| Tanpa `billing_period` | 0 | — (tetap ditangani: lihat R4 langkah 2) |
| `sebagian` di bulan ini | 0 | Wajib lunas dulu (K8 = A) |
| Baris `customer_balance_mutations` | 1 | K7 |

Artinya hampir setiap pelanggan aktif punya tagihan bulan ini yang akan ikut pindah, dan kira-kira setengahnya masih punya piutang yang harus dilunasi dulu sebelum bisa pindah.

#### K8 — Tagihan bulan ini yang sudah dicicil sebagian di cabang lama (✅ A: wajib lunas dulu)

Kasus: tagihan bulan ini Rp150rb, sudah dicicil Rp50rb di JETIS (`payments.pop_id` = JETIS), lalu pelanggan pindah ke SANDYA.

Kalau tagihan itu ikut pindah, laporan bulanan bulan berjalan (dihitung per `invoices.pop_id` untuk blok tagihan, per `payments.pop_id` untuk blok Uang Diterima, `CollectorMonthlyReportService`):

| Laporan bulan ini | Yang terjadi |
|---|---|
| JETIS | Blok Tagihan kehilangan tagihan ini, tapi blok Uang Diterima tetap memuat cicilan Rp50rb → uang tanpa tagihan |
| SANDYA | Blok Tagihan memuat tagihan ini dengan "sudah dibayar" Rp50rb (cicilan dibaca per tagihan, tanpa filter POP), padahal uangnya tidak pernah diterima SANDYA |

Periode ini belum terkunci, jadi tidak ada *drift* snapshot, tapi angka kedua cabang tidak sinambung dan akan dibekukan apa adanya saat bulan ditutup.

| Opsi | Perilaku | Konsekuensi |
|---|---|---|
| **A (rekomendasi)** | Tagihan bulan ini berstatus `sebagian` **wajib lunas dulu** (ikut guard, sama seperti piutang). Yang ikut pindah hanya tagihan bulan ini berstatus `belum_dibayar` (belum ada uang masuk). | Laporan kedua cabang tetap sinambung: tidak ada tagihan yang uangnya terbelah di dua cabang. Kasusnya jarang (0 di dev). |
| B | Tagihan `sebagian` tetap ikut pindah. | Pelanggan tidak tertahan, tapi laporan bulan itu tidak sinambung seperti tabel di atas; perlu penjelasan manual saat rekonsiliasi. |
| C | Tagihan `sebagian` tetap di cabang lama (tidak pindah), sisanya ditagih cabang lama. | Kembali ke masalah tagihan yatim: kolektor lama sudah dilepas (no. 5). Tidak direkomendasikan. |

## 5. Rancangan Perbaikan

### R1 — Distribusi legacy tidak hilang saat Edit (T1, keputusan no. 8)

**Aturan:** nilai `mini_pop_id`/`distribution_id` yang **tidak diubah** admin selalu diterima apa adanya, walaupun tidak memenuhi hierarki (*grandfathered*). Aturan hierarki cuma berlaku untuk **pilihan baru** dan saat **POP berganti**.

**Implementasi:**
1. `CustomerController::edit()`: kalau Mini POP/Distribusi pelanggan saat ini tidak ada di `$miniPops`/`$distributions` hasil scope, tambahkan ke koleksi dengan penanda `is_legacy = true`.
2. Blade: opsi legacy diberi `data-legacy="1"` dan label `"{code} - {name} (legacy — di luar struktur Mini POP)"`.
3. JS `filterChildOptions()`: opsi `data-legacy` tetap tampil **selama POP tidak diganti** (nilai awal POP dirender ke `data-original-pop-id`). Begitu POP diganti, opsi legacy disembunyikan dan dikosongkan seperti opsi lain.
4. `update()`: closure validasi `mini_pop_id`/`distribution_id` langsung lolos kalau `pop_id` tidak berubah **dan** nilainya sama dengan nilai di DB.
5. Observer tidak perlu diubah: invariant cuma jalan kalau kolomnya *dirty*, dan saat POP berganti legacy milik cabang lama memang harus dilepas.

**Kriteria terima:**
- Pelanggan dengan distribusi ber-`pop_id` Cabang: Edit ganti no. HP → `distribution_id` & CID tidak berubah.
- Pelanggan yang sama pindah POP → distribusi legacy dilepas.
- Pelanggan dengan `distribution_id` tapi `mini_pop_id` NULL: Edit tanpa perubahan jaringan → tetap utuh.

### R2 — Gerbang Mini POP/Distribusi di Edit (T2, keputusan no. 2)

**Aturan:** Mini POP/Distribusi di Edit **terkunci** kalau:
- (a) status pelanggan ∈ `NetworkAssignmentService::BLOCKED_STATUSES` (pra-pemasangan), atau
- (b) user **tidak** punya `customers.detail.installation.validate` (permission yang sama dengan modal).

POP Cabang tetap bisa diganti oleh pemegang `customers.update` (dengan guard R4).

**Implementasi:**
1. Satu method privat penghitung `$networkAssignmentLocked` (gabungan a + b), dipakai `edit()` dan `update()`.
2. **Kalau terkunci:** `mini_pop_id`/`distribution_id` **dibuang dari `$validated`** → nilai DB tidak disentuh. Mengganti perilaku ADHOC-104 yang menyimpan NULL karena `select disabled` tidak ikut terkirim.
   - Request yang tetap membawa nilai **berbeda** dari DB (PUT manual) → gagal validasi, pesannya menyebut alasan: "pra-pemasangan" atau "tidak punya izin atur jaringan".
3. **Terkunci + POP berganti:** Mini POP/Distribusi lama dilepas observer (sudah ada). Pemegang izin mengatur yang baru lewat modal.
4. Blade: keterangan di bawah dropdown membedakan dua alasan kunci.

**Kriteria terima:**
- Role tanpa `customers.detail.installation.validate` → dropdown `disabled`; PUT manual dengan Mini POP lain → error; Edit biasa → Mini POP/Distribusi/CID tetap.
- Role tanpa izin memindah POP (pelanggan lunas) → berhasil, Mini POP/Distribusi lama dilepas.
- Pelanggan pra-pemasangan yang punya Mini POP (data lama) → Edit biasa tidak menghapusnya.

### R3 — Satu penulis CID, dipicu observer (T3, T4, T10, keputusan no. 7)

**Aturan:** CID dihitung **satu** method dan dibuat ulang otomatis setiap kali `pop_id`/`mini_pop_id`/`distribution_id` berubah pada pelanggan `active`/`suspended`, dari jalur mana pun.

**Implementasi:**
1. Service baru `App\Services\CustomerCidService`:
   - `resolve(Customer $customer): string` — `$customer->pop->generateComplexCid($customer, $customer->distribution)` dengan rumus K3.
   - `shouldHaveCid(Customer $customer): bool` — status ∈ {active, suspended}.
2. `Pop::generateComplexCid()` / `resolveMiniPopSegment()` diubah sesuai K3: fallback `pop_code` Cabang & `olt_number` dihapus, default segmen `'0'`.
3. `CustomerObserver::updating()`, setelah invariant hierarki: kalau `pop_id`/`mini_pop_id`/`distribution_id` *dirty* dan `shouldHaveCid()` → `$customer->cid = CustomerCidService::resolve($customer)`.
   - Di `updating` (bukan `updated`): satu save = satu baris audit berisi POP lama/baru **dan** CID lama/baru.
   - Relasi `pop`/`miniPop`/`distribution` sudah di-`unsetRelation()` (ADHOC-104), jadi `resolve()` membaca nilai baru.
4. Hapus langkah 1b di `CustomerController::update()` (termasuk `sprintf('%s00%s')`).
5. Modal (`CustomerNetworkAssignmentController::update()`) & `NetworkAssignmentService` berhenti menulis CID sendiri. Audit `update_network_assignment` membaca `$customer->cid` **setelah** `save()`.
6. Aktivasi (`CustomerVerificationController::finalVerify()`, `CustomerController::activate()`) & import (±2960) tetap menulis CID eksplisit (status berubah ke active di sana, bukan jaringannya), tapi lewat `CustomerCidService::resolve()`.
7. `BackfillCidDefaultDistributionSegmentCommand` tidak diubah (historis).

**Kriteria terima:**
- Edit, modal, dan API menghasilkan CID yang **sama** untuk kombinasi Mini POP/Distribusi yang sama; save berulang tanpa perubahan jaringan tidak mengubah CID.
- 43 pelanggan "Mini POP tanpa distribusi" (§4.5): Edit tanpa perubahan jaringan tidak lagi mengubah `C10RQ…` → `C00RQ…`.
- `$customer->update(['pop_id' => SANDYA])` via tinker (pelanggan lunas) → CID langsung `D00RQ…`.
- Pelanggan non-active/suspended → CID tidak disentuh.
- Setiap perubahan CID punya baris `audit_logs` dengan CID lama.

**Risiko:** rumus CID global berubah (disetujui). Simulasi §4.5: cuma 3 dari 1.492 CID yang hasil hitung ulangnya berbeda. Sesuaikan ekspektasi test CID lama yang bergantung pada fallback `pop_code`/`olt_number`.

### R4 — Guard piutang lunas dulu & pemindahan tagihan bulan berjalan (T5, keputusan no. 3–4 & 10, K6, K7, K8 = A)

**Aturan:**
1. **Guard:** pindah POP **ditolak** selama pelanggan punya **piutang** (`Invoice::scopePiutang()`: `belum_dibayar`/`sebagian` dengan `billing_period` < bulan berjalan). Guard juga mencakup tagihan bulan berjalan (dan sesudahnya) berstatus `sebagian` (K8 = A). Pesan menyebut jumlah & total, mis. "Pelanggan masih punya 2 piutang (Rp 300.000). Lunasi dulu sebelum pindah POP."
2. **Tagihan periode lalu** tidak pernah dipindah `pop_id`-nya. Karena guard no. 1, saat pindah semuanya pasti sudah lunas/batal/write-off.
3. **Tagihan bulan berjalan dan sesudahnya** yang masih `belum_dibayar` (belum ada uang masuk sama sekali) **ikut pindah** ke cabang baru → pembayarannya tercatat di cabang baru.
4. **Tagihan lunas/batal/write-off** tidak pernah dipindah, periode apa pun.
5. **Pembayaran** (`payments`) dan **mutasi saldo** (`customer_balance_mutations`) yang sudah ada tidak pernah disentuh.
6. **Saldo lebih bayar** terbawa (K7 = A): tidak ada guard, dipakai `billing:apply-balance` untuk tagihan cabang baru.
7. **Tagihan bulan berikutnya** terbit di cabang baru lewat `GenerateMonthlyInvoicesCommand` (`$customer->pop_id`), tanpa kode baru.

**Implementasi:**
1. Satu service, misalnya `CustomerRelocationService` (nama final saat implementasi), sebagai satu sumber aturan untuk guard dan pemindahan:
   - `blockingInvoicesSummary(Customer): array{count: int, total: float}` — piutang (`scopePiutang`) + tagihan `sebagian` dengan `billing_period` ≥ bulan berjalan (K8 = A) + outstanding tanpa `billing_period`.
   - `invoicesToMove(Customer)` — tagihan `belum_dibayar` dengan `billing_period` ≥ bulan berjalan. Tagihan outstanding tanpa `billing_period` (0 baris di dev) diperlakukan sebagai **penghalang** (masuk summary guard), bukan ikut pindah: tanpa periode, sistem tidak bisa memastikan tagihan itu belum masuk periode terkunci.
   - "Bulan berjalan" = `now()->format('Y-m')`, sama dengan `scopePiutang()`. Jangan hitung ulang dengan cara lain.
2. **Guard lapis 1 — validasi Edit** (`CustomerController::update()`, rule `pop_id`): kalau `pop_id` berubah dan `blockingInvoicesSummary()` > 0 → gagal validasi dengan pesan di atas. Satu rule bersama cek scope & bentrok REQ ID yang sudah ada.
3. **Guard lapis 2 — observer** (`CustomerObserver::updating()`): kalau `pop_id` dirty dan masih ada penghalang → lempar exception domain, supaya import/tinker/API tidak bisa melewatinya. Pola yang sama dengan `PaymentObserver::creating()` (menolak nominal ≤ 0 dari semua jalur).
4. **Pemindahan** di `CustomerObserver::updated()` (menggantikan `moveOutstandingInvoicesToNewPop()` ADHOC-104): hanya `invoicesToMove()`, per model `$invoice->update(['pop_id' => …])` supaya tiap perpindahan tercatat di `audit_logs`.
5. Form Edit: kalau ada penghalang, tampilkan keterangan di bawah dropdown POP, "Pindah POP butuh piutang lunas (sisa Rp …)", supaya admin tahu sebelum submit. Kalau ada tagihan bulan ini yang akan ikut pindah, sebutkan juga: "Tagihan periode {bulan ini} (Rp …) akan ikut pindah ke POP baru".
6. Periksa jalur import (±2960): kalau import bisa meng-update `pop_id` pelanggan yang sudah ada, tangkap exception guard per baris dan laporkan sebagai baris gagal, bukan menggagalkan seluruh batch.

**Batas waktu yang perlu diketahui:** guard & pemindahan memakai bulan berjalan saat pindah. Pelanggan yang dipindah tanggal 30 dengan tagihan bulan itu belum dibayar → tagihan pindah ke SANDYA, dan besoknya (bulan baru) tagihan itu menjadi piutang **SANDYA**. Itu konsisten dengan keputusan no. 4 dan tidak mengusik periode terkunci JETIS.

**Kriteria terima:**
- Pelanggan dengan piutang → pindah lewat Edit ditolak dengan pesan jumlah & total; lewat tinker → exception; data tidak berubah.
- Pelanggan tanpa piutang, dengan tagihan bulan ini `belum_dibayar` → pindah berhasil; tagihan bulan ini ber-`pop_id` cabang baru; pembayaran berikutnya tercatat di cabang baru.
- Tagihan periode lalu (lunas) tetap ber-`pop_id` cabang lama; pembayaran lama tidak disentuh.
- Laporan bulanan cabang lama untuk periode terkunci tidak berubah dan tidak *drift*.
- Tagihan bulan ini berstatus `sebagian` → pindah ditolak sampai lunas.
- Saldo lebih bayar dari cabang lama dipakai untuk tagihan cabang baru; debit saldo ber-`pop_id` cabang baru, kredit asal tetap ber-`pop_id` cabang lama.
- Tagihan bulan berikutnya (generate command) terbit ber-`pop_id` cabang baru.

### R5 — Validasi pakai nilai efektif (T8)

Closure validasi di `update()` membaca nilai efektif, bukan hanya request:
- `$popId = $request->has('pop_id') ? $request->input('pop_id') : $customer->pop_id`
- `$miniPopId = $request->has('mini_pop_id') ? $request->input('mini_pop_id') : $customer->mini_pop_id`

Dikombinasikan dengan R1 (nilai tidak berubah = lolos) dan R2 (terkunci = dibuang).

### R6 — Satu sumber aturan (T9)

1. `CustomerNetworkAssignmentController` menghapus `BLOCKED_STATUSES` privat dan merujuk `NetworkAssignmentService::BLOCKED_STATUSES`.
2. Dua method publik di `NetworkAssignmentService`, dipakai closure Edit, modal, API, dan observer:
   - `miniPopBelongsToPop(?int $miniPopId, ?int $popId): bool` — `type = mini_pop` dan `parent_id = popId`.
   - `distributionBelongsToMiniPop(?int $distributionId, ?int $miniPopId): bool`.
3. Perbarui klaim "satu sumber" di `master/pop/business-logic.md` §7.

### R7 — Peringatan PPPoE tidak cocok dengan CID (T6, keputusan no. 6)

**Aturan:** sistem **tidak** mengubah `customer_devices.pppoe_username` otomatis. Kalau username tidak diawali `{CID}_`, tampilkan peringatan supaya NOC mengubahnya manual di Mikrotik lalu di sistem.

**Implementasi:**
1. Satu method penentu, mis. `Customer::pppoeMatchesCid(): ?bool` — `null` kalau PPPoE atau CID kosong; selain itu `str_starts_with($pppoe, $cid.'_')`.
2. Badge peringatan di tempat PPPoE sudah ditampilkan: detail pelanggan (Ringkasan Teknis) dan Quick Hub (`paymentInfo()`). Teks: "PPPoE belum disesuaikan dengan CID {CID} — ubah di Mikrotik lalu di Edit Pelanggan".
3. Hanya tampilan: tidak memblokir aksi, tidak menulis DB.

**Kriteria terima:**
- CID `D1X6ARQ002022` dengan PPPoE `C1X4ARQ002022_…` → peringatan tampil.
- Setelah PPPoE diperbaiki lewat Edit → peringatan hilang.
- PPPoE kosong → tanpa peringatan.

### R8 — Kolektor selalu dilepas saat pindah POP (keputusan no. 5)

**Aturan:** begitu `pop_id` berubah, `collector_id` → NULL, **tanpa** memeriksa akses POP kolektor. Pelanggan tidak terikat kolektor siapa pun sampai admin cabang baru meng-assign lewat Worksheet Kolektor.

**Implementasi:**
1. Ganti `CustomerObserver::releaseCollectorOutsideNewPop()` dengan pelepasan tanpa syarat: di `updating()`, kalau `pop_id` dirty → `$customer->collector_id = null`. Ketergantungan ke `EffectiveAccessService` di observer ikut hilang.
2. Aman terhadap uang: piutang lama wajib lunas dulu (R4) dan tagihan bulan berjalan ikut pindah ke cabang baru, jadi kolektor lama tidak meninggalkan tagihan yang belum tertagih di cabangnya.
3. Perubahan `collector_id` otomatis tercatat di `audit_logs` (RecordsAuditLogs pada save yang sama).
4. Notifikasi ke kolektor yang dilepas tetap di luar scope (§6).

**Kriteria terima:**
- Kolektor dengan scope JETIS saja → dilepas saat pelanggan pindah ke SANDYA.
- Kolektor dengan akses ke **kedua** cabang (atau all POP) → **tetap dilepas**.
- Edit tanpa ganti POP → `collector_id` tidak berubah.

## 6. Di Luar Scope ADHOC-107 (usulan task terpisah)

| Item | Alasan dipisah |
|---|---|
| Tiket/Task/FopTask terbuka ikut pindah POP (T7) | Menyentuh alur Ticket ↔ FopTask ↔ Task (bagian paling rawan, CLAUDE.md). Perlu keputusan: task terjadwal di cabang lama dibatalkan, dipindah, atau diselesaikan dulu? Bisa juga dijadikan guard seperti R4 ("tidak ada tiket/task terbuka"). |
| Notifikasi ke kolektor yang dilepas (T7) | `kabariPerubahanRute()` privat di `CollectorWorksheetController`; perlu dipindah ke service dulu |
| PPPoE otomatis (K2 opsi B) | Butuh integrasi Mikrotik atau prosedur NOC |
| Keunikan PPPoE username | §4.5: 7 perangkat berbagi username yang sama persis; tidak ada unique guard di `customer_devices.pppoe_username` |
| Migrasi massal distribusi legacy ke Mini POP (keputusan no. 8) | Butuh audit data read-only dan keputusan per cabang |

## 7. Urutan Implementasi

0. **Mulai hanya setelah user menginstruksikan implementasi.** Semua keputusan (K2–K8) sudah dijawab; tidak ada yang tertunda.
1. R6: satu sumber aturan. Tanpa perubahan perilaku; fondasi.
2. R1 + R2 + R5: form Edit & validasi. **Menutup regresi T1; wajib selesai sebelum ADHOC-104 di-commit.**
3. R3: satu penulis CID + rumus K3.
4. R7: peringatan PPPoE (hanya tampilan).
5. R4 + R8: guard piutang lunas dulu, pemindahan tagihan bulan berjalan (ganti `moveOutstandingInvoicesToNewPop()`), kolektor selalu dilepas.
6. Update dokumen (§9), `docs/TASKS.md` (ADHOC-107), Pint, test terdampak (§8). **Jangan full suite** (aturan user 2026-09-26).

## 8. Rencana Test

Test regresi baru, dinamai sesuai gejala (konvensi CLAUDE.md):

| File | Menguji |
|---|---|
| `CustomerEditDistribusiLegacyTidakHilangTest` | R1: distribusi di Cabang & distribusi tanpa Mini POP tetap utuh saat Edit tanpa perubahan jaringan; dilepas saat pindah POP |
| `CustomerEditJaringanButuhIzinValidasiTest` | R2: role tanpa `customers.detail.installation.validate` tidak bisa mengubah Mini POP/Distribusi (form & PUT manual), tapi bisa pindah POP |
| `CidSatuRumusEditModalApiTest` | R3: Edit, modal, API → CID identik; save berulang stabil (termasuk Mini POP tanpa distribusi); tinker pindah POP → CID diperbarui + audit |
| `PindahPopDitolakSelamaAdaPiutangTest` | R4: Edit & tinker ditolak selama ada piutang dan selama ada tagihan bulan ini berstatus `sebagian` (K8 = A); tanpa piutang → boleh; tagihan bulan ini `belum_dibayar` ikut pindah & dibayar ke cabang baru; tagihan periode lalu tetap di POP lama; tagihan bulan berikutnya di POP baru; saldo lebih bayar terpakai di cabang baru (K7) |
| `PppoeTidakCocokCidDiberiPeringatanTest` | R7 |
| `KolektorSelaluDilepasSaatPindahPopTest` | R8: termasuk kolektor yang punya akses ke kedua cabang |

**Test ADHOC-104 yang wajib diubah** (`CustomerPindahPopResetMiniPopTest`):
- `tagihan_outstanding_ikut_pindah_tagihan_lunas_tetap_di_pop_lama` → **diganti** skenario R4: pelanggan dengan piutang ditolak; tagihan bulan ini ikut pindah; tagihan periode lalu tidak pernah pindah.
- `kolektor_yang_punya_akses_pop_baru_dipertahankan` → **dibalik**: kolektor tetap dilepas (R8).
- Skenario pindah POP lain di file itu memakai pelanggan tanpa tagihan → tetap lolos guard R4. Fixture tagihan wajib memakai `billing_period` relatif terhadap `now()` (bukan tanggal tetap), karena batas piutang ditentukan bulan berjalan.

Test lain yang wajib ikut dijalankan: `CustomerEditTest`, `CustomerRegistrationTest`, `CustomerReferralFkTest`, `NominalRupiahBertitikDiterimaTest`, `VerificationQueueNetworkAssignmentModalTest`, `CustomerHubModalNetworkButtonTest`, `Api/NetworkAssignmentTest`, `Api/NetworkAssignmentAlertTest`, `PopModelTest`, `BackfillCidDefaultDistributionSegmentTest`, `TicketCidDisplayTest`, `QrCode/*`, `Collector*` yang menyentuh assign/worklist, test generate tagihan bulanan, test saldo pelanggan (K7), dan test aktivasi/verifikasi yang memanggil `finalVerify()`/`activate()` (R3).

Cara jalan (WSL sempat tidak merespons 2026-09-26):
```
docker exec -u 1000:1000 -e LOG_CHANNEL=null whusnet-app php artisan test --compact tests/Feature/<File>.php
```

## 9. Dokumen yang Wajib Diperbarui Saat Implementasi

Beberapa dokumen di bawah **saat ini masih menjelaskan perilaku ADHOC-104 yang akan diganti** (tagihan ikut pindah, kolektor dilepas bersyarat). Wajib ditulis ulang, bukan ditambal.

| Dokumen | Bagian |
|---|---|
| `docs/ID_NUMBERING_RULES.md` | §10: poin 4 (tagihan ikut pindah → **piutang wajib lunas dulu; hanya tagihan bulan berjalan yang ikut pindah**), poin 5 (kolektor **selalu** dilepas), CID via observer |
| `docs/master/pop/business-logic.md` | §4 rumus CID & fallback (K3); §7 guard izin Edit (R2) & satu sumber (R6); §7a tabel "Saat pindah POP" (tagihan, kolektor, legacy, CID via observer, guard lunas) |
| `docs/master/pop/flowchart.md` | §3 generate CID; §4a: guard lunas, kolektor dilepas, observer menulis CID, hapus langkah "tagihan pindah" |
| `docs/master/pop/user-flow.md` | §5a: guard lunas, kunci karena izin, opsi legacy, kolektor selalu dilepas |
| `docs/data-pelanggan/README.md`, `flow.md` | Step 3 Edit: izin, legacy, guard lunas, CID tidak lagi ditulis controller |
| `docs/billing-pembayaran/README.md` | §Pelanggan Pindah POP: **ditulis ulang** — piutang (`scopePiutang`) wajib lunas dulu; laporan pembayaran & piutang tetap di cabang lama; tagihan bulan berjalan `belum_dibayar` ikut pindah & dibayar ke cabang baru; tagihan bulan berjalan `sebagian` wajib lunas dulu (K8 = A); tagihan berikutnya di cabang baru; saldo lebih bayar terbawa (K7); garis batas = kunci buku kalender (`BookPeriod::isLocked()`) |
| `docs/kolektor/business-logic.md` | §8 "Pelanggan pindah POP": **ditulis ulang** — selalu dilepas, tanpa syarat akses |
| `docs/TASKS.md` | ADHOC-104 (catat bagian yang diganti), ADHOC-107 |

## 10. Risiko

| Risiko | Mitigasi |
|---|---|
| Rumus CID baru (K3) mengubah CID pelanggan legacy yang jaringannya disentuh | Disengaja dan tercatat di audit log; CID legacy yang tidak disentuh tetap. Umumkan ke NOC/admin sebelum rilis. |
| Observer menulis CID → loop / save ganda | Ditulis di `updating` pada model yang sama (tidak memanggil `save()` lagi). `CustomerObserver::updated()` tidak bereaksi pada kolom `cid`. |
| Modal/API berhenti menulis CID sendiri → audit kehilangan CID baru | Baca `$customer->cid` setelah `save()`. Dikunci `CidSatuRumusEditModalApiTest`. |
| R2 mengubah perilaku role yang selama ini bisa mengubah distribusi lewat Edit | Diinginkan (menutup celah). Role yang perlu diberi izin lewat Role Matrix, bukan langsung ke user. |
| Guard lapis 2 (exception di observer) memutus jalur yang selama ini diam-diam memindah POP (import, command) | Periksa semua penulis `pop_id` pelanggan saat implementasi (`grep "pop_id"` pada update Customer); tangkap per baris di import (R4 langkah 6). |
| Banyak pelanggan tertahan pindah karena piutang (§4.7: ±setengah pelanggan aktif punya piutang di dev) | Disengaja (keputusan no. 3). Pesan error menyebut jumlah & total piutang supaya admin bisa menagih dulu. |
| Tagihan bulan berjalan pindah menjelang pergantian bulan | Konsisten: besoknya menjadi piutang cabang baru (R4 "Batas waktu"). Periode terkunci cabang lama tidak terusik. |
| Pelanggan pindah tanpa kolektor → tagihan bulan pertama di cabang baru tidak masuk worklist siapa pun sampai di-assign | Worksheet Kolektor sudah punya daftar pelanggan tanpa kolektor (`whereNull('collector_id')`); sebutkan di catatan rilis untuk admin cabang baru. |
| Angka dampak §4.5 diambil dari DB dev | Ulangi query read-only yang sama di produksi sebelum rilis. |

## 11. Catatan Implementasi (2026-09-29)

Titik awal implementasi adalah kode yang sudah di-commit di `bb15742` (ADHOC-104 + **ADHOC-109**), bukan working tree saat rancangan ditulis. ADHOC-109 sudah menerapkan aturan yang **lebih ketat** dari R1/R2: di Edit, Mini POP & Distribusi hanya ditulis kalau Cabang ikut dipindah (rule `exclude` selain itu). Aturan itu dipertahankan — regresi T1 otomatis tertutup, dan penyesuaian jaringan tanpa pindah Cabang tetap lewat modal ber-izin.

| # | Hal | Keputusan implementasi |
|---|---|---|
| 1 | R1 opsi legacy di dropdown | Tidak perlu penanda `data-legacy`: ADHOC-109 sudah menampilkan nilai legacy dan tidak menulisnya selama Cabang tidak dipindah. Dikunci `CustomerEditDistribusiLegacyTidakHilangTest`. |
| 2 | R2 | Diterapkan pada kasus pindah Cabang (satu-satunya kasus Edit menulis jaringan). `CustomerController::networkLockReason()` = `'status'` / `'permission'` / null. |
| 3 | R3 — **cacat logika yang ketemu saat test** | Kalau CID cuma dibuat ulang observer saat kolom jaringan *dirty*, simpan ulang modal tanpa perubahan pilihan tidak membetulkan CID yang terlanjur campuran — padahal itu jalur perbaikan manual data lama (keputusan no. 9). Perbaikan: `CustomerCidService::sync()` dipanggil eksplisit oleh modal & API setiap simpan; observer tetap menangani jalur lain. |
| 4 | R4 "tagihan tanpa `billing_period`" | Tidak diimplementasi: kolom `invoices.billing_period` NOT NULL di skema, jadi kasusnya tidak mungkin terjadi. |
| 5 | R4 pembayaran | Hanya pembayaran `valid` yang dihitung (penghalang & syarat ikut pindah) — sama dengan laporan bulanan. Pembayaran `ditolak` tidak menahan pelanggan. |
| 6 | R4 penghalang di form | Form Edit menampilkan "Belum bisa pindah Cabang: N tagihan …" atau "N tagihan bulan berjalan … ikut pindah". |
| 7 | R5 | Tidak perlu kode tambahan: dengan `exclude` saat Cabang tidak dipindah, closure validasi hanya jalan saat pindah Cabang, dan saat itu nilai Mini POP lama memang tidak berlaku. |
| 8 | Guard observer vs jalur lain | Diperiksa: satu-satunya penulis `customers.pop_id` pada pelanggan yang sudah ada adalah Edit Pelanggan; import membuat pelanggan baru (dan memakai `updateQuietly`). Guard lapis 2 tidak memutus jalur lain. |
