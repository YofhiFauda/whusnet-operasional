# Analisa Billing: Tagihan → Pembayaran → Kolektor & Setoran

**Tanggal:** 2026-07-24
**Cakupan:** satu alur billing utuh dari tagihan terbit sampai kas kolektor terekonsiliasi. Gabungan dua analisa (alur/desync + kolektor/kasir cepat) karena keduanya **satu alur, satu tujuan**: `Invoice → Payment` yang benar & konsisten.

Dokumen dibagi dua bagian yang **jangan dicampur maknanya**:

- **BAGIAN A — Kondisi Sekarang (as-built).** Memetakan kode yang SUDAH ada, insight desain, dan utang teknis. Ini fakta implementasi.
- **BAGIAN B — Rancangan Kolektor & Kasir Cepat (belum dikode).** Fitur baru yang dibangun DI ATAS Bagian A. Ini usulan disepakati, belum ada kode.

Ketergantungan silang: fitur Bagian B menunggu utang teknis #1 & #2 di Bagian A (lihat §A-7 dan penanda "prasyarat" di Bagian B).

---
---

# BAGIAN A — KONDISI SEKARANG (as-built)

**Konteks:** verifikasi mental model billing — "Tagihan memunculkan tagihan awal/bulanan, lalu saat dibayar masuk ke Pembayaran." Model itu **benar dan sesuai implementasi**.

## A-1. Alur Nyata (dua arah, sudah terimplementasi penuh)

### A-1a. Tagihan MUNCUL — bukan dibuat manual

`InvoiceController` sengaja **tidak punya** `store()`/`create()`. Tagihan diturunkan otomatis dari dua sumber:

| Tipe | Pemicu | Kode |
|---|---|---|
| Tagihan **awal** (`awal`/`reaktivasi`) | Pelanggan lolos verifikasi final → aktif | `InitialInvoiceService::calculate()` (dipanggil `CustomerVerificationController::finalVerify`) |
| Tagihan **bulanan** (`bulanan`) | Terjadwal per periode | `GenerateMonthlyInvoicesCommand` (`billing:generate-monthly-invoices`) |

### A-1b. Bayar → masuk Pembayaran

`PaymentController::store(Invoice $invoice)` (`PaymentController.php:146-252`):

1. `Payment::create()` terikat ke `invoice_id` + `customer_id` + `pop_id`.
2. Update balik invoice dalam `DB::transaction` + `lockForUpdate`: `paid_amount`, `remaining_amount`, `invoice_status`.
3. Penuh → `LUNAS`; kurang → `SEBAGIAN` (`PaymentController.php:203-205`).

Relasi: `Invoice hasMany Payment`, `Payment belongsTo Invoice`. **Satu tagihan bisa dicicil banyak pembayaran** — Pembayaran adalah ledger terpisah, bukan cerminan 1:1 tagihan.

---

## A-2. Generator BULANAN — `GenerateMonthlyInvoicesCommand`

Terjadwal, periode = `now()->format('Y-m')`. Idempotent.

**Alur:** ambil customer `active`+`suspended` yang punya `customerService` → skip `monthly_price <= 0` → **skip bulan aktivasi** → skip kalau sudah ada AWAL/BULANAN non-BATAL periode itu → nominal murni dari `monthly_price`.

**Insight:**

- **Dua lapis anti-dobel, sengaja.** Cek `activation_date->isSameMonth` (`:57`) + cek existing AWAL/BULANAN (`:75-79`). Komentar `:63-69` jujur: dulu cek cuma per `invoice_type=BULANAN`, jadi invoice AWAL tak terlihat, satu-satunya penahan dobel = `activation_date` (satu kolom tanpa cadangan). Saat kolom itu salah isi (pernah terisi `registration_date`), pelanggan kena AWAL+BULANAN periode sama. Sekarang cek existing ikut hitung AWAL.
- **`due_date` = `day(10)`, bukan `addDays(9)`** (`:126-134`). Aturan kalender tetap (terbit tgl 1, tempo tgl 10) — bukan "run date + 10 hari" yang drift kalau command telat / dipicu manual.
- **REAKTIVASI tak dihitung** (`:71`) — suspend lalu aktif lagi bulan sama boleh dua record.
- **Titik rawan tersisa:** `whereIn('status', ['active','suspended'])` (`:34`) string literal, bukan enum. Kalau nama status berubah, generator diam-diam skip semua → tak ada tagihan terbit, tanpa error. Silent failure.

---

## A-3. Prorata AWAL — `InitialInvoiceService::calculate()`

**Rumus inti:**
```
prorateDays   = daysInMonth - tanggalAktivasi   (hari aktivasi TIDAK ditagih)
prorateAmount = round(prorateDays / daysInMonth × monthly_price)
subtotal      = prorata + pasang + kabel + tiang + materai(other_fee)
ppnAmount     = round((subtotal - diskon) × ppn%)
total         = (subtotal - diskon) + ppnAmount
```

**Insight:**

- **Server WAJIB hitung ulang; kiriman klien = preview** (`:14-19`). Form kirim prorate/total sebagai field `readonly` hasil JS. Readonly cuma penghalang UI — siapa pun yang bisa POST bisa kirim nominal apa saja. Sistem lama punya celah sama (TOTALBIAYA dijumlah di klien). Jangan diwariskan.
- **Tebing ujung bulan, DISENGAJA** (`:34-36`, `:59-62`). Aktif 30 Juli → bayar 1 hari. Aktif 31 Juli → 0 hari sisa → dibulatkan **sebulan penuh**. Keputusan bisnis 2026-07-21. Jangan "dirapikan" jadi 1 hari tanpa keputusan bisnis baru.
- **PPN disimpan PERSEN, bukan nominal** (`:38-40`). `invoices.ppn` = angka persen; `invoices/show.blade.php` render ulang nominalnya. Simpan nominal → tampil "PPN 16500%".
- **Materai lewat `other_fee`, sekali seumur hidup** (`:42-46`). Tidak pernah ikut bulanan. JANGAN salin ke `customer_services.other_fee` — kolom nama sama, arti beda (biaya melekat layanan).
- **`next_month_amount`** (`:79-85`) jawab pertanyaan "bulan depan bayar berapa?" di kwitansi. Rumusnya **dijaga identik** dengan generator bulanan. Ubah rumus bulanan → ubah di sini juga, atau kwitansi bohong.

---

## A-4. Guard Anti-Dobel — `InvoiceObserver` & `PaymentObserver`

### InvoiceObserver::creating() — dua guard

1. **Burst dedup** (`:47-58`): tolak insert identik (sama customer + type + period + **amount**) dalam jendela **300 detik**. Retrofit dari bug retry/double-submit di `biaya_tagihan` legacy.
2. **Satu langganan per periode** (`rejectSecondSubscriptionInvoice`, `:81-107`): satu customer hanya boleh SATU tagihan langganan per periode, **lintas jenis** — AWAL dan BULANAN tak boleh koeksis.

Ditegakkan di observer (bukan cuma di command) supaya semua jalur insert tertutup: form manual, import, tinker.

**Batas guard:**

- **Burst 300 detik = HEURISTIK, bukan integritas.** Dua insert identik selang 6 menit → lolos. Yang menjamin sungguhan = **unique index DB** (`add_duplicate_guard_indexes_to_invoices_and_payments`). Observer = lapis lunak defense-in-depth di atas index.
- **Asimetri pengecualian, dan itu benar.** Guard 2 exempt `old_invoice_id` (`:87`) — replay legacy boleh muat pelanggaran historis (AWAL+BULANAN periode sama). Guard 1 tidak exempt, tapi konsisten: pelanggaran historis legacy nominalnya beda → guard 1 (sensitif amount) memang tak menangkapnya; yang beneran dobel identik saat migrasi sengaja di-drop.
- **BATAL tak dihitung** (`:98`) — tagihan dibatalkan tak boleh blokir penggantinya. **Aturan sama persis ada di `GenerateMonthlyInvoicesCommand:78`.** Dua tempat, satu aturan — ubah satu ubah dua. Kandidat konstanta bersama.
- **REAKTIVASI di luar `SUBSCRIPTION_TYPES`** (`:23-26`) — sengaja, boleh nambah record.

### PaymentObserver::creating() — satu guard

Tolak `amount <= 0` dari **semua** jalur (`:18-23`). Nutup lubang legacy `BAYAR=0` (placeholder log aktivasi, bukan pembayaran). Kokoh — jangan dilemahkan.

---

## A-5. Risiko Desync Status Invoice

`invoice_status` = **kolom tersimpan**, bukan turunan live dari `remaining_amount`. Ditulis benar hanya di 3 tempat:

| Penulis | Set status |
|---|---|
| `GenerateMonthlyInvoicesCommand` | `BELUM_DIBAYAR` saat terbit |
| `InitialInvoiceService` → `finalVerify` | `BELUM_DIBAYAR` saat terbit |
| `PaymentController::store` / `bulkStore` | `LUNAS` / `SEBAGIAN` saat bayar |

**Invariant tak tertulis:** `remaining_amount`, `paid_amount`, `invoice_status` harus konsisten. Tidak ada mekanisme yang memaksa — konsistensi hanya terjaga karena semua jalur bayar lewat `PaymentController`.

### Kapan pecah

- **Koreksi manual / migrasi legacy** — ubah `remaining_amount` langsung tanpa lewat controller → status ketinggalan (`remaining=0` tapi status masih `sebagian`).
- **`bulkStore` menelan error diam-diam** (`PaymentController.php:313`): `catch` cuma `$failed++` tanpa alasan. Atomicity aman (transaksi per invoice), tapi kolektor tak tahu kenapa gagal.

**Mitigasi yang ADA:** `AuditDuplicateInvoicesCommand`, `CleanupLegacyDuplicateInvoicesCommand` — tapi itu untuk DOBEL invoice, bukan desync status↔amount.

**Yang TIDAK ada:** command rekonsiliasi yang cek `Σ payment valid == paid_amount` dan status sesuai remaining.

---

## A-6. Reject Payment — Jebakan Laten (BUKAN bug hidup)

**Fakta rute** (`routes/web.php:131-143`): payment hanya punya `index`, `show`, `create`, `store`, `bulk-store`. **Tidak ada `update`, `destroy`, `reject`.** Sekali `store`, payment beku. Status `DITOLAK` hanya di-set di import legacy (`CustomerController:3001`, `mapLegacyPaymentStatus`), di titik invoice belum direkonsiliasi — jadi tidak ada desync sekarang.

**Tapi jebakannya sudah dipasang setengah jalan.** `Payment.php:61` sudah mengantisipasi `payment_status → DITOLAK` (nulis audit `cancel`). Kode ini hanya masuk akal kalau fitur reject/edit payment direncanakan. Begitu fitur itu dibangun:

> Observer menulis audit `cancel`, TAPI tidak ada yang mengembalikan `invoice.paid_amount / remaining_amount / invoice_status`. Payment ditolak → invoice tetap `LUNAS`, `remaining=0`. Uang ditolak, tagihan tampak lunas.

**Rekomendasi:** saat membangun fitur reject/edit/hapus payment, koreksi invoice **wajib** di transaksi yang sama.

---

## A-7. Utang Teknis (kandidat task)

> **Catatan ketergantungan:** #1 & #2 adalah **prasyarat fitur kolektor (Bagian B)**. Fitur B tak boleh dikode sebelum keduanya beres, karena B mengandalkan rekalkulasi invoice terpusat + rekonsiliasi.

1. **Extract `Invoice::recalculateFromPayments()`** — satukan logika hitung `paid/remaining/status` yang sekarang ter-inline di `PaymentController::store` (`:203-205`) + `bulkStore` (`:289-308`). Prasyarat aman untuk fitur reject payment DAN batch kolektor; menghapus dua salinan logika yang gampang menyimpang. **← prasyarat Bagian B.**
2. **Command rekonsiliasi** `billing:reconcile-invoice-status` — cek `Σ payment valid == paid_amount` & status sesuai `remaining_amount`, laporkan yang menyimpang (dry-run dulu). Jaring untuk desync legacy/koreksi manual yang saat ini tak terpantau. **← sejalan dgn rekonsiliasi Setoran (§B-11).**
3. **Konstanta bersama aturan "abaikan BATAL"** — `InvoiceObserver:98` dan `GenerateMonthlyInvoicesCommand:78` mengulang aturan sama; satukan agar tak menyimpang.
4. **Ganti string literal status jadi enum** di `GenerateMonthlyInvoicesCommand:34` — cegah silent-skip saat nama status berubah.

---
---

# BAGIAN B — RANCANGAN KOLEKTOR & KASIR CEPAT (belum dikode)

**Status:** rancangan disepakati, belum ada kode. Hasil diskusi model mental + data model.
**Prasyarat:** utang teknis #1 & #2 di §A-7.

## B-1. Masalah

Admin kantor harus memproses pembayaran ~1000 pelanggan/hari. Kenyataan lapangan:

- Penagihan door-to-door dilakukan **kolektor** — di sistem = user ber-role **`kolektor`** (role RBAC baru, global, dibatasi scope POP).
- **Kolektor adalah role terpisah, bukan sama dengan Admin POP.** Admin POP **boleh** merangkap role kolektor; tapi punya role kolektor **tidak** memberi hak Admin POP. Jadi: Admin POP ⊃ bisa jadi kolektor, kolektor ⊅ Admin POP. Satu user bisa memegang dua role sekaligus (mis. Admin POP yang juga menagih di lapangan).
- **Kolektor TIDAK berwenang input pembayaran.** Setelah menagih, uang diserahkan ke admin kantor. Hanya admin kantor yang memproses.
- Nominal bayar **tidak seragam** — ada yang lunas penuh, ada yang cicil sebagian.
- Tidak semua pelanggan lewat kolektor: ada yang **bayar pribadi di kantor**, **transfer**, atau **titip teknisi**.

Alat lama = Excel, cepat. Sistem sekarang memaksa buka invoice **satu per satu** (`invoices/{invoice}/payments/create`) → tidak mungkin untuk 1000 baris/hari. Fitur "Bayar Massal" yang ada hanya **lunas penuh**, tidak bisa nominal berbeda per baris, jadi tidak cocok.

Ditambah kebingungan admin baru: halaman **Tagihan** vs **Pembayaran** terlihat mirip.

---

## B-2. Model Mental yang Disepakati

> **Halaman Tagihan = master universal semua tagihan (segala status, segala jalur bayar).**
> **Kolektor = salah satu jalur bayar, bukan tempat tagihan berpindah.**
> **Halaman Pembayaran = riwayat kas (read-mostly).**

Konsekuensi:

- Tagihan **tidak pindah** ke Pembayaran saat dibayar — hanya berganti status (`belum_dibayar → sebagian → lunas`). Satu Tagihan bisa punya banyak Pembayaran (cicilan). Tagihan lunas tetap tampil di halaman Tagihan (audit + link ke pembayarannya). — *konsisten dgn §A-1b.*
- Penugasan kolektor **tidak** mengeluarkan pelanggan dari halaman Tagihan. Pelanggan ter-assign tetap muncul di Tagihan, hanya diberi **badge** penanda kolektornya.
- Tab/halaman kolektor hanyalah **view tersaring** untuk mempercepat batch door-to-door.

---

## B-3. Data Model — DUA kolom kolektor, beda peran

| Kolom | Peran | Sifat | Diisi |
|---|---|---|---|
| `customers.collector_id` | **Rute permanen** — pelanggan ini rutin ditagih kolektor siapa | nullable FK `users`, bisa diubah/dilepas | manual, saat assign |
| `payments.collected_by` | **Snapshot historis** — pembayaran INI ditagih siapa | nullable FK `users` | otomatis, sesuai jalur bayar |
| `payments.received_by` | admin kantor yang **memproses** input | FK `users` (sudah ada) | `auth()->id()` |

### Kenapa dua, bukan satu

- `customers.collector_id` menjawab "siapa yang rutin menagih pelanggan ini **sekarang**" → dipakai mengelompokkan tab kolektor.
- `payments.collected_by` menjawab "pembayaran ini **faktanya** ditagih siapa" → untuk audit & laporan setoran per kolektor.

**`collected_by` TIDAK otomatis menyalin `collector_id`.** Diisi sesuai **jalur masuk pembayaran**:

| Jalur bayar | `collected_by` |
|---|---|
| Lewat tab kolektor A | `= A` |
| Dari halaman Tagihan (bayar pribadi di kantor / transfer / titip teknisi) | `null` |

Contoh kritis: pelanggan rutinnya ditagih A (`collector_id = A`), tapi bulan ini transfer sendiri. Pembayaran itu `collected_by = null`, **bukan A**. Kalau disalin buta dari `collector_id`, laporan setoran A jadi bohong (mencatat uang yang tidak pernah dia tagih).

Manfaat memisahkan snapshot: kalau kolektor pelanggan diganti tahun depan (`collector_id` A→B), **riwayat pembayaran lama tetap benar** karena `collected_by` sudah beku per transaksi.

### Relasi

- **1 pelanggan : 0-atau-1 kolektor** (nullable, reassignable).
- **1 kolektor : banyak pelanggan.**
- Bukan many-to-many.

### Catatan struktur eksisting

- `collector_id` & `collected_by` **FK ke `users`** (bukan tabel entitas baru). Kolektor = user ber-role `kolektor`, jadi tetap satu identitas user.
- `customers` sudah punya `pop_id` + `mini_pop_id`. Kolektor **berbeda** dari POP — satu POP bisa punya banyak kolektor — jadi `collector_id` kolom terpisah, bukan diturunkan dari `pop_id`.
- `payments` sudah punya `received_by` (admin kantor) + kolom legacy `received_by_old` / `deposited_by_old`. `collected_by` adalah kolom baru, jangan dicampur dengan yang legacy.
- **Tipe bayar sudah ada:** `payments.payment_method` (string 50) untuk Cash/Transfer. Tak perlu kolom `payment_channel` baru.

---

## B-4. Arti "Selamanya" (klarifikasi user)

"Selamanya" = **sekali di-assign ke Kolektor A, pelanggan tetap muncul di halaman Kolektor A** tanpa perlu input ulang tiap pembayaran. BUKAN terkunci mati.

Assign bersifat **fleksibel & dinamis**:
- bisa **dilepas** dari kolektor (kembali tanpa kolektor),
- bisa **dipindah** ke kolektor lain (mis. pelanggan pindah POP, atau salah assign).

Jadi: default menetap (hindari kerja berulang), tapi selalu bisa dikoreksi.

---

## B-5. Alur

### A. Assign kolektor (jarang, sekali di depan)

Halaman Tagihan → tombol **"Atur Kolektor"** → layar assign:
- cari + centang pelanggan (satu atau banyak),
- tetapkan ke kolektor terpilih → simpan `customers.collector_id`.
- reassign / lepas lewat layar yang sama (masuk audit log).

### B. Bayar via kolektor (harian, cepat — inti fitur)

Halaman kolektor menampilkan **tab per kolektor** (dari `collector_id`), untuk mobilitas tinggi seperti Excel lama. Di tab kolektor:
- daftar pelanggan kolektor itu yang **belum lunas**,
- **kolom nominal + metode per baris** (default nominal = sisa penuh, boleh diubah untuk parsial; metode Cash/Transfer sendiri per baris),
- centang yang setor → **Simpan** sekali untuk seluruh batch,
- `collected_by` = kolektor tab tersebut, `received_by` = admin login.
- Alur lengkap dengan rekonsiliasi kas ada di §B-11 (Setoran).

### C. Bayar non-kolektor (dari halaman Tagihan)

Pelanggan bayar pribadi / transfer / titip teknisi → admin bayar langsung dari halaman Tagihan (atau detail invoice). `collected_by = null`. Berlaku baik pelanggan itu punya `collector_id` maupun tidak.

---

## B-6. UI

- **Halaman Tagihan (master):** semua tagihan tampil. Yang ter-assign kolektor diberi **badge "Kolektor: A"**. Tetap bisa dibayar dari sini (jalur non-kolektor).
- **Halaman/tab Kolektor:** view tersaring per kolektor, baris dengan input nominal + metode, batch submit. Prioritas kecepatan (mirip Excel).
- **Halaman Pembayaran:** tetap riwayat. Tambahkan kolom/filter **Kolektor** agar bisa lihat setoran per kolektor.
- **UI kolektor (login role `kolektor`):** worklist read-only pelanggannya saja (lihat §B-8.5).
- Onboarding admin baru: teks penjelas singkat di tiap halaman (Tagihan = belum tentu lunas & master; Pembayaran = riwayat).

---

## B-7. Aturan & Invariant (wajib dijaga)

1. **POP scope tidak boleh bocor.** Admin kantor hanya melihat kolektor & pelanggan dalam scope-nya (`applyUserScope` / `EffectiveAccessService`). Dropdown kolektor = user ber-role **`kolektor`** dalam scope admin (termasuk Admin POP yang merangkap role kolektor). Validasi server: admin tak boleh memproses setoran kolektor di luar scope.
2. **Batch tidak boleh gagal senyap.** `bulkStore` lama hanya `$failed++` (lihat §A-5). Untuk skala 1000, wajib mengembalikan **daftar gagal + alasan** ("Sri: nominal > sisa", "Tono: sudah lunas"), bukan angka telanjang.
3. **Nominal parsial per baris** — bukan hanya lunas penuh.
4. **Nominal ≤ 0 ditolak** — sudah dijaga `PaymentObserver` (§A-4), jangan dilemahkan.
5. **Konsistensi invoice** — tiap pembayaran (dari jalur mana pun) tetap update `paid_amount` / `remaining_amount` / `invoice_status` atomik. **Wajib lewat `Invoice::recalculateFromPayments()` (utang teknis #1, §A-7)**, bukan logika ter-inline yang diduplikasi.

---

## B-8. Keputusan yang Sudah Dikunci

1. **"Selamanya" = menetap tapi reassignable** (bisa dilepas / dipindah). Assign fleksibel & dinamis.
2. **Pelanggan default di halaman Tagihan (master), tetap tampil walau ter-assign kolektor** (dengan badge). Alasan: tidak semua pelanggan lewat kolektor. Tidak dibuat tab terpisah "Belum Ada Kolektor" yang mengeluarkan pelanggan dari master.
3. **1 kolektor : banyak pelanggan** (dan 1 pelanggan : 0-atau-1 kolektor).
4. **Kolektor = role RBAC baru `kolektor`, bukan sama dengan Admin POP.** Role global (batasi lewat scope, sesuai aturan repo — tidak bikin role per cabang). Admin POP boleh merangkap kolektor; sebaliknya tidak. FK `collector_id`/`collected_by` tetap ke `users`. Kolektor **tidak** boleh input pembayaran (jangan beri `payments.create`). → butuh: tambah `kolektor` ke `RoleSeeder` (`is_system`) + matrix permission-nya.
5. **UI kolektor minimal: hanya melihat daftar pelanggannya.** Kolektor login cuma dapat **worklist read-only** = pelanggan ber-`collector_id = dia` + status belum lunas (biar tahu siapa didatangi). Tak ada input apa pun. Permission efektif: baca pelanggan yang ter-assign ke dirinya saja (bukan `customers.view` penuh). Perubahan hak ke depan cukup lewat matrix RBAC — tidak perlu ubah kode.
6. **Metode & kembalian:** batch bayar per baris punya metode sendiri (Cash/Transfer) + nominal sendiri (penuh/parsial). Kembalian dikembalikan fisik, tak masuk sistem, tak jadi kredit (lihat §B-9 Kembalian).
7. **Reassign kolektor masuk audit log.**
8. **`payment_date` = tanggal pembayaran diterima/divalidasi admin** (tanggal input kantor), bukan tanggal tagih lapangan. Konsisten dengan rekap harian "yang saya proses hari ini".

---

## B-9. Pertanyaan Terbuka & Klarifikasi

### Sudah diputus

1. ~~**Siapa yang boleh jadi kolektor?**~~ **DIPUTUSKAN:** role RBAC baru `kolektor` (§B-8.4).
2. ~~**Perubahan assign masuk audit log?**~~ **DIPUTUSKAN: YA** (§B-8.7).
3. ~~**Metode bayar per baris di batch?**~~ **DIPUTUSKAN: BEDA PER BARIS.** UI batch = per-baris {nominal, metode}. `bulkStore` terima array `{invoice_id, amount, method}`.
4. ~~**`payment_channel` enum?**~~ **DICORET (redundan).** Tipe bayar sudah ada di `payments.payment_method`. Kolektor sudah lewat `collected_by` + tab. Sisa satu-satunya = "titip teknisi" (Q di bawah).
5. ~~**`payment_date` tanggal tagih atau input?**~~ **DIPUTUSKAN: tanggal input/validasi** (§B-8.8).
6. ~~**Rekonsiliasi setoran hilang?**~~ **DIBUAT.** Lihat §B-11.

### Masih terbuka

- **Titip teknisi** — apakah `collected_by` khusus (label "Teknisi") atau cukup `null` + catatan? Sekarang diasumsikan `null`. **BELUM DIPUTUS.**
- **Laporan setoran per kolektor** — masuk scope sekarang atau menyusul? (`group by collected_by` / per `collector_deposits`).
- **Kredit dari kelebihan bayar** — default sekarang: kelebihan selalu dikembalikan fisik, tak ada saldo. Kalau kelak kelebihan disimpan jadi kredit → fitur baru terpisah.

### Kembalian (klarifikasi user)

Kadang pelanggan bayar lebih dari nominal → kolektor kasih **kembalian di tempat (fisik)**. Aturan: sistem hanya mencatat **nominal terpakai ke invoice**; kembalian TIDAK masuk sistem, TIDAK jadi saldo/kredit. Contoh: tagihan 100rb, pelanggan kasih 150rb, kembali 50rb → `Payment.amount = 100.000`, invoice `lunas`.

---

## B-10. Ringkas Perbandingan Rancangan

| Aspek | Rancangan awal (label per-bayar) | Rancangan disepakati (assign permanen) |
|---|---|---|
| Pilih kolektor | tiap pembayaran (berulang) | sekali, permanen + reassignable |
| Tampilan | 1 list difilter | tab per kolektor (master tetap di Tagihan) |
| Nominal | parsial per baris | parsial per baris (dipertahankan) |
| `collected_by` | disalin dari pilihan | diisi sesuai jalur masuk (kolektor / null) |
| Beban admin | tinggi (1000× pilih) | rendah (assign sekali) |

Rancangan permanen menang di beban kerja; nominal-parsial-per-baris dari rancangan awal dipertahankan. `collected_by` sesuai jalur masuk adalah sintesis yang menjaga poin "tidak semua lewat kolektor".

---

## B-11. Konsep Setoran Kolektor (rekonsiliasi kurang/lebih setor)

Menyelesaikan gap rekonsiliasi: `collected_by` cuma mencatat *siapa* nagih, tak mendeteksi **kurang setor**. Karena kolektor tak boleh input, satu-satunya jejak = yang admin masukkan — jadi selisih antara uang yang kolektor terima dari pelanggan dan uang yang benar-benar dia serahkan **tak terlihat**. Setoran menutup lubang itu. Sejalan dgn utang teknis #2 (§A-7): rekonsiliasi, beda objek (setoran kas vs invoice).

### Ide inti

Satu sesi serah-terima kas kolektor→admin = satu **Setoran** = **wadah (header)** sekumpulan Payment yang di-input admin dalam batch itu, plus **angka deklarasi fisik** yang bisa dibandingkan.

```
Setoran (collector_deposits)
  ├─ declared_total  = UANG FISIK yang admin terima dari kolektor (diketik admin saat buka setoran)
  ├─ recorded_total  = Σ payments.amount yang ter-link ke setoran ini (dihitung sistem)
  └─ variance        = recorded_total − declared_total
         = 0   → cocok
         > 0   → pelanggan (menurut input) bayar LEBIH dari kas yang disetor → indikasi KURANG SETOR kolektor
         < 0   → kas disetor lebih besar dari yang teralokasi → input belum lengkap / lebih setor
```

Arah `variance` sengaja `recorded − declared`: sumber kebenaran nominal per-pelanggan tetap dari laporan kolektor (yang admin ketik per baris); `declared` adalah kas nyata di meja. Selisih positif = ada uang pelanggan yang tak sampai jadi kas.

### Tabel baru — `collector_deposits`

| Kolom | Tipe | Isi |
|---|---|---|
| `id` | PK | |
| `deposit_number` | string unik | penomoran seperti entitas lain (mis. `STR-{tahun}-{4 digit}`) |
| `collector_id` | FK `users` | kolektor yang menyetor (role `kolektor`) |
| `pop_id` | FK `pops` | scope (untuk `applyUserScope`) |
| `deposit_date` | date | tanggal serah-terima (= tanggal validasi, sinkron dgn `payment_date`) |
| `declared_total` | decimal(12,2) | kas fisik diterima |
| `recorded_total` | decimal(12,2) | cache Σ payment (di-rekalkulasi tiap payment berubah) |
| `status` | string | `draft` → `matched` (variance 0) / `selisih` (variance ≠ 0) / `closed` |
| `received_by` | FK `users` | admin yang menerima & memproses |
| `note` | text nullable | wajib diisi kalau `selisih` (alasan/penjelasan) |
| `timestamps` | | |

Kolom baru di `payments`: **`collector_deposit_id`** (nullable FK `collector_deposits`). Cuma terisi untuk jalur kolektor. Jalur non-kolektor: `collector_deposit_id = null` DAN `collected_by = null`.

Invariant: kalau `collector_deposit_id` terisi → `collected_by` **wajib** = `collector_deposits.collector_id` (auto, konsisten; jangan biarkan menyimpang).

### Relasi

- **1 Setoran : banyak Payment.**
- **1 Setoran : 1 kolektor : 1 tanggal** (satu sesi serah-terima).
- Payment non-kolektor tidak punya setoran.

### Alur setoran (menyempurnakan §B-5-B)

```
1. Kolektor A serahkan kas fisik ke admin, sebut totalnya (mis. 4.500.000).
2. Admin buka tab Kolektor A → "Setoran Baru"
      → declared_total = 4.500.000  (kas nyata di meja)
3. Admin centang pelanggan yang (menurut laporan kolektor) bayar,
   isi nominal + metode per baris. Sistem jumlahkan recorded_total berjalan.
4. Submit batch → tiap Payment ter-link collector_deposit_id + collected_by = A,
   received_by = admin, payment_date = deposit_date.
5. Sistem hitung variance:
      recorded 4.500.000 = declared 4.500.000 → status matched ✔
      recorded 5.000.000 > declared 4.500.000 → variance +500.000
            → status selisih, admin WAJIB isi note (kurang setor kolektor)
6. Setoran tersimpan sebagai jejak permanen: siapa, kapan, deklarasi, tercatat, selisih.
```

### Invariant setoran

1. **Scope:** admin hanya boleh membuat/memproses setoran untuk kolektor dalam POP scope-nya (`applyUserScope` / `EffectiveAccessService`). Server-side, bukan cuma UI.
2. **`selisih` wajib beralasan:** setoran tak boleh `closed` dengan `variance ≠ 0` tanpa `note`.
3. **`recorded_total` bukan sumber kebenaran, hasil turunan:** selalu = Σ payment ter-link. Lewat `recalculateFromPayments` bersama (utang teknis #1, §A-7), jangan di-inline & diduplikasi.
4. **Audit:** pembuatan setoran, selisih, dan perubahan status masuk audit log (uang + tanggung jawab).
5. **Batch gagal tak boleh senyap:** kalau sebagian baris gagal, setoran mencerminkan hanya yang sukses; kembalikan daftar gagal + alasan (sejalan §B-7.2 & §A-5).

### Yang MASIH perlu diputuskan di setoran

- **Boleh input lintas hari?** Satu setoran = satu `deposit_date`; kalau kolektor telat setor (kas Senin baru disetor Rabu), `deposit_date` = hari serah (Rabu) atau hari tagih (Senin)? Default sekarang: hari serah/validasi (sinkron `payment_date`).
- **Edit setoran setelah `closed`?** Rekomendasi: tidak; koreksi lewat pembatalan payment + setoran baru, biar jejak selisih tak bisa dihapus diam-diam. (Catatan: reject/edit payment sendiri belum ada — lihat §A-6, koreksi invoice wajib satu transaksi.)

---

## B-12. Contoh End-to-End (skenario angka)

Menyambungkan semua bagian (assign → tagih fisik → setor + rekonsiliasi → input batch → efek invoice → jejak) jadi satu cerita.

### Pemain

| Entitas | |
|---|---|
| POP | Jetis |
| Kolektor | **Budi** (role `kolektor`, scope POP Jetis) |
| Admin kantor | **Sri** (yang input → `received_by`) |
| Pelanggan Budi | Ani, Bima, Cici (`collector_id = Budi`) |
| Pelanggan non-kolektor | Deni (`collector_id = null`) |

Tagihan Juli terbit, semua status `belum_dibayar`:

| Pelanggan | Tagihan | `collector_id` |
|---|---|---|
| Ani | 100.000 | Budi |
| Bima | 150.000 | Budi |
| Cici | 200.000 | Budi |
| Deni | 100.000 | — |

`collector_id` di-set sekali lewat "Atur Kolektor" (menetap, reassignable, perubahan → audit log).

### Langkah 1 — Budi keliling (fisik, nol baris di sistem)

- **Ani** → penuh 100rb cash, uang pas.
- **Bima** → cuma sanggup 50rb cash (cicil).
- **Cici** → penuh 200rb, kasih 250rb → **Budi kembalikan 50rb** (kembalian fisik, tak masuk sistem).

Kas Budi = 100 + 50 + 200 = **350.000**. Sampai sini **belum ada Payment** — sistem belum tahu apa-apa.

### Langkah 2 — Budi setor ke Sri (titik rekonsiliasi)

Budi serahkan kas **320.000** (kurang 30rb). Sri buka **tab Kolektor Budi → "Setoran Baru"**:

```
declared_total = 320.000   ← kas fisik nyata di meja Sri
```

Sri input per baris (dari laporan Budi):

| Baris | Nominal | Metode | Efek invoice |
|---|---|---|---|
| Ani | 100.000 | Cash | 100.000 = 100.000 → **lunas** |
| Bima | 50.000 | Cash | 50.000 < 150.000 → **sebagian**, sisa 100.000 |
| Cici | 200.000 | Cash | 200.000 = 200.000 → **lunas** |

```
recorded_total = 350.000
variance = recorded − declared = 350.000 − 320.000 = +30.000
       → status SELISIH → Sri WAJIB isi note ("Budi kurang setor 30rb")
```

Tanpa header `declared_total`, sistem hanya tahu 350rb tercatat — kurang setor 30rb **tak akan ketahuan**. Inilah nilai konsep Setoran.

### Langkah 3 — Yang tersimpan

**`collector_deposits`:**

```
STR-2026-0001 | collector=Budi | pop=Jetis | deposit_date=2026-07-24
declared=320.000 | recorded=350.000 | status=selisih
received_by=Sri | note="Budi kurang setor 30rb, klarifikasi"
```

**3 baris `payments`** (semua ter-link setoran):

```
collected_by = Budi            (auto = collector_id setoran, BUKAN salinan customers.collector_id)
collector_deposit_id = STR-2026-0001
received_by = Sri
payment_date = 2026-07-24       (tanggal validasi)
payment_method = cash
```

### Langkah 4 — Deni (jalur non-kolektor)

Deni transfer sendiri 100rb. Sri bayar dari **halaman Tagihan** (bukan tab kolektor):

```
amount=100.000, payment_method=transfer
collected_by = null              ← bukan lewat kolektor
collector_deposit_id = null      ← tak masuk setoran
invoice Deni → lunas
```

Poin dijaga: **`collected_by = null` walau seandainya Deni punya `collector_id`.** Kalau disalin buta dari `collector_id`, laporan setoran kolektor mencatat uang yang tak pernah dia pegang.

### Langkah 5 — Siapa lihat apa

| Aktor | Layar |
|---|---|
| **Budi** (login kolektor) | **worklist read-only**: Ani (lunas), Bima (sisa 100rb), Cici (lunas). Nol tombol input. |
| **Sri** (admin) | tab Kolektor (batch), Setoran, Tagihan (master + badge "Kolektor: Budi"), Pembayaran (riwayat). |
| **Owner/atasan** | laporan setoran per kolektor: Budi 24 Jul → declared 320rb / recorded 350rb / **selisih +30rb**. |

Semua difilter POP scope. Sri tak bisa proses setoran kolektor di luar scope-nya.

### Langkah 6 — Efek invoice (tetap di halaman Tagihan, cuma ganti status)

```
Ani  → lunas      (tetap tampil di Tagihan, audit)
Bima → sebagian   (sisa 100.000, masih muncul di worklist Budi)
Cici → lunas
Deni → lunas      (via transfer, tanpa kolektor)
```

Tak ada tagihan "pindah" ke Pembayaran.

### B-12.1 Cicilan lintas-setoran (Bima, 2 payment 2 setoran)

Lanjutan: invoice Bima masih `sebagian`, sisa 100.000. Kunjungan berikutnya Budi tagih lagi.

**Setoran ke-2 (hari lain):** Budi setor, Sri buka **Setoran Baru** → `STR-2026-0002`, `deposit_date = 2026-08-05`. Input Bima 100.000 cash.

```
Payment #2 Bima: amount=100.000, cash
collected_by = Budi
collector_deposit_id = STR-2026-0002   ← setoran BERBEDA dari payment #1
payment_date = 2026-08-05
→ invoice Bima: paid 50.000 + 100.000 = 150.000 = total → LUNAS
```

Hasil akhir 1 invoice Bima:

| Payment | Nominal | Setoran | Tanggal |
|---|---|---|---|
| #1 | 50.000 | STR-2026-0001 | 2026-07-24 |
| #2 | 100.000 | STR-2026-0002 | 2026-08-05 |
| **Σ** | **150.000** | — | invoice **lunas** |

Poin penting:

- **1 invoice : banyak payment** (cicilan) — masing-masing terhubung ke setoran-nya sendiri. `invoice_status` naik `belum_dibayar → sebagian → lunas` seiring `recalculateFromPayments()` (§A-7 #1).
- **Snapshot beku:** kalau tahun depan kolektor Bima diganti (`customers.collector_id` Budi→lain), payment #1 & #2 tetap `collected_by = Budi` karena sudah dibekukan per transaksi. Riwayat lama tak berubah.
- **Rekonsiliasi tetap per-setoran:** payment #1 masuk hitung variance STR-2026-0001, payment #2 masuk STR-2026-0002. Tak dicampur.

---
---

## Referensi Silang

- `docs/billing-pembayaran/analisa-pencegahan-tagihan-dobel.md` — detail guard dobel & unique index.
- `docs/billing-pembayaran/perbandingan-tagihan-awal-vs-bulanan-legacy.md` — konvensi prorata legacy.
- `docs/billing-pembayaran/analisa-duplikasi-tagihan-pembayaran-migrasi-legacy.md` — cacat duplikasi data migrasi.
