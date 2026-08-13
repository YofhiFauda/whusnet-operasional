# DONE PARTIAL

> ## ⚠️ SEBAGIAN DIREVISI oleh kolektor-2.0 (2026-08-08)
>
> Dua keputusan di dokumen ini **tidak berlaku lagi**. Baca
> `docs/plan/kolektor/analisa-alur-kolektor-2.0.md` §8 sebelum memakai dokumen ini:
>
> - **§B-8 no. 4 & no. 5** — "kolektor tidak boleh input pembayaran", "UI kolektor
>   read-only". DIREVISI: kolektor mencatat pembayarannya sendiri lewat
>   `kolektor.pay` di `/collector-worklist`. Yang tetap: kolektor tak diberi
>   `payments.create`, tak bisa membuka halaman admin, tak bisa menagih pelanggan
>   di luar `collector_id`-nya.
> - **§B-11 ⛔ "DILUAR SCOPE"** — Setoran/rekonsiliasi kas. DIREVISI: dihidupkan
>   lagi di Fase 2 kolektor-2.0, dengan bentuk lebih ringan (`collector_deposits`).
>
> Sisa dokumen ini masih berlaku: model mental Tagihan/Pembayaran (§B-2), dua kolom
> kolektor (§B-3), kelebihan bayar dikembalikan fisik (§B-8 no. 6),
> `payment_date` vs `collected_date` (§B-8 no. 8).

# Analisa Billing: Tagihan → Pembayaran → Kolektor & Setoran

**Tanggal:** 2026-07-24
**Revisi:** 2026-07-31 — review silang terhadap kode. Dua klaim fakta dikoreksi, tiga blocker teknis ditemukan, sembilan temuan desain dituangkan. Ringkasan di **Bagian C**.
**Revisi:** 2026-08-01 — user menjawab 7 pertanyaan validasi (**Bagian D**) + 3 keputusan final putaran kedua (**§D-9**): satu batch satu kolektor, drop unique index `payments` diganti `idempotency_key` + burst-dedup pengganti di jalur single-payment, pisah kolom `payment_method`/`collected_by` di tampilan.
**Revisi:** 2026-08-01 (lanjutan) — **RUANG LINGKUP DIPERSEMPIT.** Lihat kotak di bawah.
**Cakupan:** satu alur billing utuh dari tagihan terbit sampai admin memproses pembayaran kolektor. Gabungan dua analisa (alur/desync + kolektor/kasir cepat) karena keduanya **satu alur, satu tujuan**: `Invoice → Payment` yang benar & konsisten.

> ## ⛔ RUANG LINGKUP DIPERSEMPIT (2026-08-01)
>
> Dokumen ini awalnya diminta dianalisa **"berstandar Enterprise"** — itu yang mendorong sebagian besar isinya ke arah kontrol keuangan skala besar: rekonsiliasi kas kolektor (Setoran), deteksi kolektor yang tak jujur lapor, tutup buku periode, ledger saldo kredit pelanggan. Semua itu valid secara teknis, tapi **user mengonfirmasi kebutuhan sebenarnya lebih sempit**: admin bisa bedain Tagihan/Pembayaran, bayar banyak pelanggan sekaligus lewat Tab Kolektor dengan cepat, lihat riwayat pembayaran tanpa banyak tab, dan input cash/transfer/cicil yang tervalidasi.
>
> **Dua fitur besar di-drop dari scope aktif (2026-08-01), bukan dihapus — ditandai `⛔ DILUAR SCOPE` di tempatnya, konten dipertahankan sebagai arsip referensi:**
>
> 1. **Setoran Kolektor / rekonsiliasi kas** (§B-11, seluruh Fase 3 lama, terkait §D-9 no. 1 & no. 2 sebagian). Tanpa ini: kolektor tetap bisa input batch pembayaran banyak pelanggan sekaligus (tetap dibangun), tapi **tak ada lapis "declared kas vs recorded sistem"** — kalau kolektor kurang setor, itu ditangani di luar sistem (manual), bukan dideteksi otomatis.
> 2. **Saldo kredit pelanggan / ledger kelebihan bayar** (§D-5, `payment_allocations` + `customer_credits`). Tanpa ini: keputusan asli §B-8.6 **berlaku lagi** — kelebihan bayar selalu dikembalikan fisik, tak ada saldo tersimpan di sistem.
>
> **Konsekuensi ke desain yang masih aktif:** batch kolektor (Fase 2) tetap butuh proteksi dobel-submit (idempotency) dan atomicity per sesi — kebutuhan itu **independen** dari Setoran. Solusinya disederhanakan jadi tabel ringan `payment_batches` (cuma untuk dedup + pengelompokan, tanpa `declared_total`/`variance`/status selisih) — lihat §A-7 #6 & Bagian E Fase 1/2 yang sudah diperbarui.
>
> Bagian A, B (khususnya §B-11), C, D tetap dipertahankan utuh sebagai jejak analisa — kalau nanti kebutuhan kolektor "kurang setor" atau "saldo pelanggan" beneran muncul di lapangan, tinggal lanjut dari sana, tak perlu analisa ulang dari nol.

Dokumen dibagi tiga bagian yang **jangan dicampur maknanya**:

- **BAGIAN A — Kondisi Sekarang (as-built).** Memetakan kode yang SUDAH ada, insight desain, dan utang teknis. Ini fakta implementasi.
- **BAGIAN B — Rancangan Kolektor & Kasir Cepat (belum dikode).** Fitur baru yang dibangun DI ATAS Bagian A. §B-11 (Setoran) `⛔ DILUAR SCOPE` — lihat kotak di atas.
- **BAGIAN C — Hasil Review (2026-07-31).** Koreksi, blocker, dan urutan kerja. Bagian A & B sudah diperbaiki di tempat; C menyimpan jejak keputusannya.
- **BAGIAN D — Validasi Alur vs Kebutuhan User (2026-08-01).** Tujuh pertanyaan validasi dari user diadu dengan kode + rancangan, ditutup §D-9 berisi 3 keputusan terkunci. §D-5 (saldo kredit) `⛔ DILUAR SCOPE`.
- **BAGIAN E — Urutan Pengerjaan (2026-08-01, direvisi).** Backlog eksekusi **2 Fase aktif** (Fase 1 & 2), Fase 3 & 4 lama diarsipkan. Ini yang dipakai untuk mulai kerja.

Ketergantungan silang: fitur Bagian B menunggu **seluruh Fase 1** di §C-5 (utang teknis #1, #2, #5, #6, #7 di §A-7). Baca §C-5 sebelum menjadwalkan task.

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

- **Burst 300 detik = HEURISTIK, bukan integritas.** Dua insert identik selang 6 menit → lolos.
- **TIDAK ADA unique index DB untuk invoice — observer adalah SATU-SATUNYA lapis.** (Koreksi review 2026-07-31; versi sebelumnya dokumen ini keliru menyebut ada index penjamin.) Migration `add_duplicate_guard_indexes_to_invoices_and_payments` **cuma menyentuh tabel `payments`** (`:23-25`), bukan `invoices`. Unique index `(customer_id, invoice_type, billing_period)` **sengaja tidak dipasang** — lihat migration `2026_07_21_164556_add_invoice_period_unique_index_to_invoices.php` yang dibiarkan kosong sebagai catatan: kunci polos bikin tagihan berstatus `batal` terus menempati slot periode sehingga tagihan pengganti ditolak DB, melanggar aturan bisnis yang sudah punya test (`SatuTagihanLanggananPerPeriodeTest`, `AuditTagihanDobelTest`). MySQL tak punya partial index, jadi "kecualikan yang batal" tak bisa ditulis di index.
  → **Konsekuensi:** anti-dobel invoice hidup **murni di layer aplikasi** (`InvoiceObserver` + `CustomerController::storeManualInvoice`), dipantau `billing:audit-duplicate-invoices`. Setiap jalur insert baru yang mem-bypass observer (raw query, `insert()` massal, `DB::table()`) langsung menembus semua guard. Ini menaikkan prioritas utang teknis #2 (rekonsiliasi) dari pelengkap jadi **kompensasi wajib**.
- **Unique index yang BENAR-BENAR ada cuma di `payments`: `(invoice_id, payment_date, amount)`.** Melindungi dari retry/double-submit legacy, tapi **bertabrakan dengan cicilan** — lihat §C-2(a), harus diputuskan sebelum fitur kolektor dikode.
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

**Rekomendasi:** saat membangun fitur reject/edit/hapus payment, koreksi invoice **wajib** di transaksi yang sama (lewat `recalculateFromPayments()`, §A-7 #1).

**Status naik jadi WAJIB (review 2026-07-31):** fitur ini tak lagi opsional. §B-11 mensyaratkan koreksi setoran; koreksi setoran mustahil tanpa membatalkan payment. Jadi void payment masuk **prasyarat Bagian B** (§A-7 #7), bukan backlog. Catat juga: unique index `(invoice_id, payment_date, amount)` membuat pola "void lalu input ulang dengan nominal & tanggal yang sama" **gagal di DB** — lihat §A-7 #6.

---

## A-7. Utang Teknis (kandidat task)

> **Catatan ketergantungan:** #1, #2, #5, #6, #7 adalah **prasyarat fitur kolektor (Bagian B)**. Fitur B tak boleh dikode sebelum semuanya beres — B mengandalkan rekalkulasi invoice terpusat, rekonsiliasi, penomoran yang tak jebol di skala 1000/hari, dan jalur koreksi pembayaran.

1. **Extract `Invoice::recalculateFromPayments()`** — satukan logika hitung `paid/remaining/status` yang sekarang ter-inline di `PaymentController::store` (`:203-205`) + `bulkStore` (`:289-308`). Prasyarat aman untuk fitur reject payment DAN batch kolektor; menghapus dua salinan logika yang gampang menyimpang. **← prasyarat Bagian B.**
   → **Wajib diputuskan eksplisit saat implementasi: `payment_status` mana yang ikut dijumlah.** Kolom `payments.payment_status` default `pending` di migration (`create_payments_table:25`), tapi `PaymentController` selalu menulis `valid`. Kalau rekalkulasi cuma menghitung `VALID`, setiap jalur insert yang tak menyetel status (import legacy, seeder, tinker) langsung menciptakan desync `paid_amount ≠ Σ payment` sejak baris pertama. Pilih satu, tulis di komentar, kunci dengan test.
2. **Command rekonsiliasi** `billing:reconcile-invoice-status` — cek `Σ payment valid == paid_amount` & status sesuai `remaining_amount`, laporkan yang menyimpang (dry-run dulu). Jaring untuk desync legacy/koreksi manual yang saat ini tak terpantau. **← sejalan dgn rekonsiliasi Setoran (§B-11), dan kompensasi wajib atas absennya unique index invoice (§A-4).**
3. **Konstanta bersama aturan "abaikan BATAL"** — `InvoiceObserver:98` dan `GenerateMonthlyInvoicesCommand:78` mengulang aturan sama; satukan agar tak menyimpang.
4. **Ganti string literal status jadi enum** di `GenerateMonthlyInvoicesCommand:34` — cegah silent-skip saat nama status berubah.
5. **Ganti generator `payment_number` jadi berbasis tabel sequence.** `PaymentController::generatePaymentNumber()` (`:335-354`) pakai pola `PAY-{Ym}-%04d` dengan MAX+1 hasil `orderBy(...)->lockForUpdate()->first()`. Dua cacat: (a) **4 digit = maksimum 9.999 pembayaran per bulan**, sementara premis Bagian B ~1000/hari ⇒ ~30.000/bulan — sequence jebol di hari ke-10; (b) `lockForUpdate()` tak mengunci apa pun kalau belum ada baris di periode itu, dan di `bulkStore` tiap baris jalan di transaksi terpisah → race → tabrakan `payment_number` (unik) → error ditelan `catch { $failed++ }`. Ikuti pola `PopSequence` yang sudah ada di repo, dan lebarkan jadi 6 digit. **← prasyarat Bagian B.**
6. ~~Putuskan nasib unique index~~ **DIPUTUSKAN (§D-9): DROP `payments_invoice_date_amount_unique`.** Diganti `payment_batches.idempotency_key` (unik, per sesi submit batch) untuk jalur kolektor. **Disederhanakan 2026-08-01:** semula direncanakan di `collector_deposits` (tabel Setoran), tapi Setoran di-drop dari scope (§B-11) — dedup/atomicity batch tetap dibutuhkan **independen** dari fitur rekonsiliasi, jadi dipindah ke tabel ringan `payment_batches` (cuma id, `idempotency_key`, `submitted_by`, `submitted_at`, `collector_id` — tanpa `declared_total`/`variance`/status selisih). **← prasyarat Bagian B.**
7. **Bangun jalur void/reject payment + koreksi invoice satu transaksi** (§A-6). Bukan "nanti" lagi: §B-11 mensyaratkan koreksi setoran, dan koreksi setoran mustahil tanpa membatalkan payment. **← prasyarat Bagian B.**
8. **BARU (§D-9): jalur single-payment butuh penjaga dobel pengganti.** Index yang di-drop di #6 adalah **satu-satunya** guard dobel-submit di `PaymentController::store` (bayar satu-invoice, non-batch) — `PaymentObserver` cuma cek `amount <= 0` (`:20`), tak ada burst-dedup seperti `InvoiceObserver`. Tambahkan burst-dedup heuristik di `PaymentObserver` (pola sama §A-4: tolak insert identik customer+invoice+amount+date dalam jendela pendek). **← prasyarat Bagian B, wajib selesai BERSAMAAN dengan #6, jangan drop index dulu sebelum penggantinya ada.**

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
| `payments.collected_date` | **tanggal uang diterima di lapangan** (bukan tanggal input) | date nullable, kolom baru | diisi admin per baris saat input setoran; `null` untuk jalur non-kolektor |

`collected_date` ditambahkan hasil review (§C-3 no. 2). Tanpa itu, kas yang ditagih 31 Juli tapi disetor 2 Agustus tercatat penuh sebagai pendapatan Agustus, invoice Juli tampak telat, dan sengketa pelanggan ("saya sudah bayar tanggal 28") tak terbantahkan. `payment_date` **tetap** tanggal posting/validasi (§B-8.8) — dua kolom, dua pertanyaan berbeda.

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

### Validasi wajib pada `collector_id` (hasil review)

FK polos ke `users` tidak cukup — dua guard server-side wajib, bukan cuma penyaringan dropdown:

1. **User target wajib ber-role `kolektor`.** Tanpa cek ini, siapa pun bisa jadi "kolektor" lewat request langsung.
2. **POP pelanggan wajib ada di scope efektif kolektor** (`EffectiveAccessService::getAllowedPopIds()`, ingat `hasAllPopAccess()` untuk kasus ALL_POP). Assign pelanggan POP Jetis ke kolektor bercakupan POP Siman = worklist bocor lintas cabang → melanggar larangan keras #3 di `CLAUDE.md`.
3. **Kolektor tidak boleh dinonaktifkan/dihapus selama masih memegang pelanggan.** `nullOnDelete` akan diam-diam melepas seluruh `collector_id`-nya — worklist lenyap tanpa jejak dan pelanggan jatuh ke "tanpa kolektor" tanpa ada yang tahu. Guard: tolak nonaktifkan sampai pelanggannya di-reassign (atau paksa reassign massal di layar yang sama, masuk audit log).

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
- **kolom `collected_date` per baris** (default = tanggal setoran; diubah kalau kas ditagih hari sebelumnya) — §B-3,
- centang yang setor → **Simpan** sekali untuk seluruh batch, **satu transaksi untuk seluruh sesi** (§B-7 no. 7): ada baris gagal → batch ditolak dengan daftar alasan, bukan tersimpan separuh,
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
6. **Assign kolektor wajib lolos tiga guard** (role, POP scope, larangan nonaktif) — lihat §B-3 "Validasi wajib pada `collector_id`". Server-side.
7. **Batch = satu transaksi per setoran, bukan per baris.** Pola `bulkStore` lama (transaksi per invoice, `PaymentController:281`) menghasilkan setoran separuh jadi kalau ada baris gagal: `declared_total` sudah tercatat, sebagian payment masuk, sisanya tidak, dan `recorded_total` jadi angka yang tak bermakna. Untuk setoran, atomicity yang benar adalah **seluruh sesi**: gagal satu baris → tolak batch, tampilkan daftar gagal + alasan, admin perbaiki, submit ulang. (Bandingkan invariant 2: pesan gagal tetap wajib detail — yang berubah cuma unit atomicity-nya.)
8. **Nomor pembayaran wajib dari sequence tahan-race** (§A-7 #5). Di batch 1000 baris, generator MAX+1 menghasilkan tabrakan `payment_number` yang — di kode sekarang — berakhir jadi `$failed++` senyap.

---

## B-8. Keputusan yang Sudah Dikunci

1. **"Selamanya" = menetap tapi reassignable** (bisa dilepas / dipindah). Assign fleksibel & dinamis.
2. **Pelanggan default di halaman Tagihan (master), tetap tampil walau ter-assign kolektor** (dengan badge). Alasan: tidak semua pelanggan lewat kolektor. Tidak dibuat tab terpisah "Belum Ada Kolektor" yang mengeluarkan pelanggan dari master.
3. **1 kolektor : banyak pelanggan** (dan 1 pelanggan : 0-atau-1 kolektor).
4. **Kolektor = role RBAC baru `kolektor`, bukan sama dengan Admin POP.** Role global (batasi lewat scope, sesuai aturan repo — tidak bikin role per cabang). Admin POP boleh merangkap kolektor; sebaliknya tidak. FK `collector_id`/`collected_by` tetap ke `users`. Kolektor **tidak** boleh input pembayaran (jangan beri `payments.create`). → butuh: tambah `kolektor` ke `RoleSeeder` (`is_system`) + matrix permission-nya.
5. **UI kolektor minimal: hanya melihat daftar pelanggannya.** Kolektor login cuma dapat **worklist read-only** = pelanggan ber-`collector_id = dia` + status belum lunas (biar tahu siapa didatangi). Tak ada input apa pun. Permission efektif: baca pelanggan yang ter-assign ke dirinya saja (bukan `customers.view` penuh). Perubahan hak ke depan cukup lewat matrix RBAC — tidak perlu ubah kode.
6. **Metode & kembalian:** batch bayar per baris punya metode sendiri (Cash/Transfer) + nominal sendiri (penuh/parsial). Kembalian dikembalikan fisik, tak masuk sistem, tak jadi kredit (lihat §B-9 Kembalian).
   → Sempat digugat 2026-08-01 (usulan saldo kredit §D-5), tapi **§D-5 diputuskan ⛔ DILUAR SCOPE** (2026-08-01, lanjutan) — keputusan no. 6 ini **berlaku lagi tanpa syarat**. Kelebihan bayar selalu dikembalikan fisik.
7. **Reassign kolektor masuk audit log.**
8. **`payment_date` = tanggal pembayaran diterima/divalidasi admin** (tanggal input kantor), bukan tanggal tagih lapangan. Konsisten dengan rekap harian "yang saya proses hari ini".
   → **Direvisi (review 2026-07-31):** keputusan ini dipertahankan untuk `payment_date`, **tapi tanggal tagih lapangan tidak boleh dibuang** — disimpan terpisah di `payments.collected_date` (§B-3). Membuang tanggal lapangan sepenuhnya bikin pendapatan lintas-bulan salah potong dan sengketa pelanggan tak terbantahkan.
9. **Batch bayar = satu transaksi per setoran** (§B-7 no. 7), bukan per baris seperti `bulkStore` lama.
10. **Selisih setoran bukan status terminal** — wajib punya jalur penutupan (§B-11 "Penutupan selisih").

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
  → ⚠️ **"Kelak" itu sudah datang (2026-08-01).** User meminta fitur ini. Analisa lengkap + dua opsi ada di **§D-5**; keputusannya di **§D-8 no. 1**. Belum diambil.

Ditambah dari review (semuanya di §B-11, memblokir Fase 3):

- **Kontrol sisi pelanggan mana yang dipakai** — kwitansi bernomor / notifikasi pelanggan / aging + `visit_result`. Tanpa minimal satu, Setoran tak menangkap kolektor yang tak melapor.
- **Opsi penutupan selisih** — A (pointer `settles_deposit_id`) atau B (ledger saldo kolektor).
- **Invariant POP setoran** — ketat (satu setoran satu POP) atau longgar (scope dari payment).

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

> ## ⛔ DILUAR SCOPE (2026-08-01)
>
> User mengonfirmasi kebutuhan sebenarnya tak sampai butuh rekonsiliasi kas kolektor — cukup batch bayar cepat per kolektor (§B-5B, tetap dibangun). Seluruh §B-11 (tabel `collector_deposits`, declared/recorded/variance, kontrol anti-fraud, penutupan selisih) **tidak dikerjakan**. Konten di bawah dipertahankan sebagai arsip — kalau nanti masalah "kolektor kurang setor" beneran muncul di lapangan, analisa ini sudah siap dilanjut tanpa mulai dari nol.
>
> **Yang tetap dibangun** meski Setoran di-drop: kebutuhan dedup/atomicity batch submit (independen dari fitur rekonsiliasi) — disederhanakan jadi tabel ringan `payment_batches` tanpa `declared_total`/`variance`/status selisih. Lihat §A-7 #6 dan Bagian E.

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
| `collector_id` | FK `users` | kolektor yang menyetor (role `kolektor`). **Satu setoran = satu kolektor, wajib (§D-9)** — batch lintas kolektor dilarang; declared_total tak punya makna fisik & variance tak bisa ditanggungjawabkan kalau kas dari >1 orang dicampur satu submit. Admin proses banyak kolektor = banyak setoran terpisah |
| `idempotency_key` | string unik, nullable | **BARU (§D-9).** Digenerate klien sekali per sesi submit batch (bukan per baris). Submit ulang dgn key sama = ditolak/diabaikan. Menggantikan `payments_invoice_date_amount_unique` yang di-drop (§C-2a) |
| `pop_id` | FK `pops` | scope (untuk `applyUserScope`) |
| `deposit_date` | date | tanggal serah-terima (= tanggal validasi, sinkron dgn `payment_date`) |
| `declared_total` | decimal(12,2) | kas fisik diterima |
| ~~`recorded_total`~~ | — | **DICORET (review 2026-07-31).** Jangan simpan sebagai kolom cache — turunkan live dari `SUM(payments.amount) WHERE collector_deposit_id = ?` (index-nya murni satu kolom, murah). Menyimpannya berarti mengulang persis penyakit yang dikritik dokumen ini sendiri di §A-5: kolom tersimpan tanpa mekanisme yang memaksa konsistensi. Kalau kelak terbukti jadi bottleneck, baru cache — dan wajib ikut dicek `billing:reconcile`. |
| `status` | string | `draft` → `matched` / `selisih_open` → `selisih_settled` / `selisih_written_off` → `closed`. Lihat "Penutupan selisih". |
| `received_by` | FK `users` | admin yang menerima & memproses |
| `note` | text nullable | wajib diisi kalau selisih (alasan/penjelasan) |
| `timestamps` | | |

Kolom penutupan selisih (lihat "Penutupan selisih" di bawah): `settled_at`, `settled_by`, `settled_note`, dan — kalau memilih opsi A — `settles_deposit_id` (FK ke `collector_deposits`).

Kolom baru di `payments`: **`collector_deposit_id`** (nullable FK `collector_deposits`). Cuma terisi untuk jalur kolektor. Jalur non-kolektor: `collector_deposit_id = null` DAN `collected_by = null`.

Invariant: kalau `collector_deposit_id` terisi → `collected_by` **wajib** = `collector_deposits.collector_id` (auto, konsisten; jangan biarkan menyimpang).

### Relasi

- **1 Setoran : banyak Payment.**
- **1 Setoran = 1 SESI serah-terima** (bukan "1 kolektor : 1 tanggal"). **Direvisi:** rumusan lama menyiratkan unique `(collector_id, deposit_date)`, padahal kolektor wajar setor dua kali sehari (pagi & sore) atau menyetor kas dua hari sekaligus dalam dua sesi terpisah. **Jangan pasang unique constraint di pasangan itu** — kunci identitasnya `deposit_number`.
- Payment non-kolektor tidak punya setoran.

### Invariant POP pada setoran (hasil review)

`collector_deposits.pop_id` ambigu kalau kolektor bercakupan `pop_tree` / multi-POP: satu sesi bisa memuat pelanggan dari beberapa POP, sehingga `payments.pop_id` (ikut invoice) berbeda dari `deposit.pop_id`. Pilih satu, tulis di kode:

- **Opsi ketat (disarankan):** satu setoran dikunci ke satu POP. Baris pelanggan di luar POP itu ditolak saat submit dengan alasan eksplisit. Laporan & scope jadi lurus.
- **Opsi longgar:** `pop_id` setoran informatif saja (POP utama kolektor), scoping laporan bersandar pada `payments.pop_id`. Boleh, tapi `applyUserScope` di layar setoran wajib memakai payment, bukan header — kalau tidak, admin bisa melihat setoran berisi pelanggan luar scope-nya.

Jangan biarkan tak diputuskan — ini titik kebocoran POP scope.

### Alur setoran (menyempurnakan §B-5-B)

```
1. Kolektor A serahkan kas fisik ke admin, sebut totalnya (mis. 4.500.000).
2. Admin buka tab Kolektor A → "Setoran Baru"
      → declared_total = 4.500.000  (kas nyata di meja)
3. Admin centang pelanggan yang (menurut laporan kolektor) bayar,
   isi nominal + metode per baris. Sistem jumlahkan recorded_total berjalan.
4. Submit batch (SATU transaksi untuk seluruh sesi) → tiap Payment ter-link
   collector_deposit_id + collected_by = A, received_by = admin,
   payment_date = deposit_date, collected_date = tanggal tagih lapangan.
   Ada baris gagal → seluruh batch ditolak + daftar alasan, bukan tersimpan separuh.
5. Sistem hitung variance (recorded dihitung live dari Σ payment ter-link):
      recorded 4.500.000 = declared 4.500.000 → status matched ✔
      recorded 5.000.000 > declared 4.500.000 → variance +500.000
            → status selisih_open, admin WAJIB isi note (kurang setor kolektor)
            → selisih jadi saldo terutang kolektor; setoran TIDAK boleh closed
              sampai settled / written_off
6. Setoran tersimpan sebagai jejak permanen: siapa, kapan, deklarasi, tercatat, selisih.
```

### Invariant setoran

1. **Scope:** admin hanya boleh membuat/memproses setoran untuk kolektor dalam POP scope-nya (`applyUserScope` / `EffectiveAccessService`). Server-side, bukan cuma UI.
2. **`selisih` wajib beralasan:** setoran tak boleh `closed` dengan `variance ≠ 0` tanpa `note`.
3. **`recorded_total` bukan sumber kebenaran, hasil turunan:** selalu = Σ payment ter-link — **dihitung live, tidak disimpan** (lihat catatan di tabel). Perhitungan efek ke invoice tetap lewat `recalculateFromPayments()` bersama (utang teknis #1, §A-7), jangan di-inline & diduplikasi.
4. **Audit:** pembuatan setoran, selisih, dan perubahan status masuk audit log (uang + tanggung jawab).
5. **Batch gagal tak boleh senyap:** kalau sebagian baris gagal, setoran mencerminkan hanya yang sukses; kembalikan daftar gagal + alasan (sejalan §B-7.2 & §A-5).

### Batas deteksi Setoran — BACA SEBELUM MENGANGGAP INI KONTROL ANTI-FRAUD

**Setoran hanya menangkap "laporan jujur, kas tidak jujur". Skenario "laporan tidak jujur" lolos 100%.**

Sebabnya struktural: karena kolektor tak boleh input (§B-1), `recorded_total` **bersumber dari laporan kolektor sendiri** yang diketik ulang admin. Kolektor mengontrol kedua sisi persamaan.

```
Budi tagih Ani 100rb → Ani TIDAK dilaporkan sama sekali → uang dikantongi.
declared 250.000, recorded 250.000 → variance 0 → status MATCHED ✔
Invoice Ani tetap belum_dibayar. Ani merasa sudah bayar. Sistem diam.
```

Contoh §B-12 (Budi setor 320rb tapi melapor 350rb) mengasumsikan penipu yang melapor benar — asumsi yang tak realistis. Status `matched` **tidak** berarti "beres"; artinya cuma "aritmatika laporan konsisten".

Menutupnya butuh kontrol dari **sisi pelanggan**, bukan sisi kolektor. Minimal satu wajib ikut sebelum Setoran dianggap kontrol:

1. **Kwitansi bernomor prasetak.** Blok nomor diserahkan ke kolektor, nomornya wajib diinput per baris payment. Kwitansi hilang / lompat nomor = wajib dipertanggungjawabkan. Kontrol klasik door-to-door collection, paling murah, tak butuh pelanggan melek digital. Butuh: kolom `payments.receipt_number` + registri blok kwitansi per kolektor.
2. **Notifikasi ke pelanggan saat payment ter-posting** (WA/SMS). Pelanggan yang sudah bayar tapi tak menerima notifikasi = alarm dari luar sistem. Butuh nomor HP valid (sudah wajib untuk siap billing).
3. **Aging piutang per kolektor + hasil kunjungan.** Kolektor melaporkan juga kunjungan yang **tidak** menghasilkan uang (`visit_result`: bayar / tidak di rumah / menolak / janji). Pelanggan yang berulang "tidak di rumah" tapi tunggakannya menua = pola yang layak diaudit. Tanpa ini, "tidak ada baris" ambigu: belum didatangi, atau didatangi lalu uangnya raib.

**Keputusan yang harus diambil:** pilih minimal satu dari tiga, atau nyatakan eksplisit bahwa Setoran diterima sebagai kontrol parsial dan risiko "laporan tidak jujur" ditanggung. Jangan dibiarkan implisit — bahayanya justru rasa aman palsu.

### Penutupan selisih — variance wajib punya jalan pulang

`variance = +30.000` bukan sekadar catatan: itu **uang perusahaan yang sedang dipegang kolektor**. Di rancangan awal, angka itu berhenti sebagai `note` teks dan status `selisih` — tak pernah ditutup.

Pertanyaan yang tak terjawab rancangan awal: besok Budi menyerahkan 30rb pelunasan, masuk mana?

- Bukan `Payment` — payment wajib terikat `invoice_id` (NOT NULL) dan invoice-nya sudah lunas.
- Kalau dibuat setoran baru `declared 30.000 / recorded 0` → `variance = −30.000`. Hasilnya dua selisih berlawanan mengambang, tak terhubung, dan laporan "selisih per kolektor" jadi akumulasi sampah yang tak pernah nol.

Standar enterprise: **akun kliring kolektor (cash-in-transit)** — selisih adalah saldo berjalan, bukan atribut satu dokumen. Dua opsi implementasi:

- **Opsi A — pointer antar-setoran (ringan).** Setoran pelunasan menunjuk setoran bermasalah lewat `settles_deposit_id`. Setoran lama pindah ke `selisih_settled`. Cukup untuk kasus 1:1, canggung kalau satu pelunasan menutup beberapa selisih sekaligus.
- **Opsi B — ledger saldo kolektor (benar).** Tabel `collector_balance_entries` (debit saat selisih terbit, kredit saat dilunasi / dihapusbukukan), saldo berjalan per kolektor. Menangani parsial, banyak-ke-banyak, dan langsung memberi laporan "kolektor X menunggak Y". Lebih mahal sedikit, tapi ini bentuk yang benar.

Aturan yang menyertai, apa pun opsinya:

1. **`selisih_open` bukan status terminal.** Setoran tak boleh `closed` selama selisihnya belum `settled` atau `written_off`.
2. **Hapus buku (`written_off`) wajib approval atasan** + alasan. Ini titik di mana kerugian diakui — jangan biarkan admin yang sama menutupnya sendiri.
3. **Selisih negatif (`declared > recorded`) bukan selisih, tapi input belum lengkap** — selama setoran `draft`. Baru bermakna "lebih setor" setelah admin menyatakan input selesai. Jangan hitung variance sebagai temuan di status `draft`.
4. Semua perubahan status selisih masuk audit log (uang + tanggung jawab).

### Yang MASIH perlu diputuskan di setoran

- ~~**Boleh input lintas hari?**~~ **TERJAWAB oleh `collected_date` (§B-3).** `deposit_date` = hari serah-terima (Rabu), `payment_date` = hari posting (Rabu), `collected_date` = hari tagih di lapangan (Senin) per baris. Satu setoran boleh memuat baris dengan `collected_date` berbeda-beda — itu justru kasus normal kolektor yang menyetor kas beberapa hari sekaligus.
- **Edit setoran setelah `closed`?** Rekomendasi: tidak; koreksi lewat void payment + setoran baru, biar jejak selisih tak bisa dihapus diam-diam. (Catatan: void payment kini **prasyarat**, §A-7 #7 — dan pola "void lalu input ulang nominal & tanggal sama" terbentur unique index `payments`, §A-7 #6.)
- **Pilih kontrol sisi pelanggan yang mana** (kwitansi bernomor / notifikasi pelanggan / aging + `visit_result`) — lihat "Batas deteksi Setoran". **BELUM DIPUTUS, memblokir Fase 3.**
- **Opsi penutupan selisih: A (pointer) atau B (ledger saldo kolektor)** — lihat "Penutupan selisih". **BELUM DIPUTUS, memblokir Fase 3.**
- **Invariant POP setoran: opsi ketat atau longgar** — lihat "Invariant POP pada setoran". **BELUM DIPUTUS.**

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
declared=320.000 | recorded=350.000 (dihitung live, bukan kolom) | status=selisih_open
received_by=Sri | note="Budi kurang setor 30rb, klarifikasi"
→ selisih +30.000 tercatat sebagai saldo terutang Budi; setoran TIDAK boleh closed
  sampai settled / written_off (lihat "Penutupan selisih")
```

**3 baris `payments`** (semua ter-link setoran):

```
collected_by = Budi            (auto = collector_id setoran, BUKAN salinan customers.collector_id)
collector_deposit_id = STR-2026-0001
received_by = Sri
payment_date = 2026-07-24       (tanggal posting/validasi kantor)
collected_date = 2026-07-23     (tanggal Budi keliling — beda dari posting)
payment_method = cash
```

Catatan realistis: setoran ini **hanya** ketahuan karena Budi jujur melaporkan ketiga pelanggan. Kalau Budi tidak melaporkan Ani sama sekali, `declared = recorded = 250.000` → `matched`, dan 100rb Ani hilang tanpa jejak. Lihat "Batas deteksi Setoran".

### Langkah 4 — Deni (jalur non-kolektor)

Deni transfer sendiri 100rb. Sri bayar dari **halaman Tagihan** (bukan tab kolektor):

```
amount=100.000, payment_method=transfer
collected_by = null              ← bukan lewat kolektor
collector_deposit_id = null      ← tak masuk setoran
collected_date = null            ← tak ada penagihan lapangan
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

# BAGIAN C — HASIL REVIEW (2026-07-31)

Review menyilangkan isi dokumen dengan kode nyata. Bagian A & B **sudah dikoreksi di tempat**; bagian ini merangkum temuan supaya jejak keputusannya tak hilang, dan menetapkan urutan kerja.

## C-1. Koreksi fakta

| # | Klaim lama | Fakta | Dampak |
|---|---|---|---|
| 1 | "Yang menjamin sungguhan = unique index DB" (§A-4) | Migration `add_duplicate_guard_indexes_to_invoices_and_payments` **cuma menyentuh `payments`**. Unique index invoice sengaja **tidak dipasang** (`2026_07_21_164556_...` migration kosong berisi alasannya: tagihan `batal` menempati slot periode, MySQL tak punya partial index) | Anti-dobel invoice **tanpa lapis DB sama sekali**. `InvoiceObserver` satu-satunya penjaga. Utang teknis #2 naik jadi kompensasi wajib |
| 2 | Index `payments` disinggung sebagai penjamin murni | Bentuknya `(invoice_id, payment_date, amount)` — **memblokir cicilan sah** dan memblokir koreksi | Blocker fitur kolektor, §C-2(a) |

## C-2. Blocker teknis — fitur gagal di hari pertama kalau tak dibereskan

**(a) Unique index `payments_invoice_date_amount_unique` bentrok cicilan & koreksi.**
Satu invoice, satu hari, dua sesi setoran dengan nominal sama → `SQLSTATE` unique violation. Di `bulkStore` sekarang ditelan `catch { $failed++ }` (`PaymentController.php:313`) → admin cuma lihat "1 gagal" tanpa alasan, uang hilang dari sistem. Pola koreksi "void lalu input ulang nominal & tanggal sama" juga mati.

**DIPUTUSKAN (§D-9, 2026-08-01): opsi (ii) — drop index, ganti `idempotency_key` unik di `payment_batches`** (per sesi submit batch, bukan per baris — atomicity per sesi §B-7 no. 7). Alasan menolak opsi (i): MySQL memperlakukan `NULL ≠ NULL` di unique index, jadi index gabungan tetap tak menutup jalur non-kolektor (`payment_batch_id = null`) — celah persis di jalur paling rawan dobel-submit.

**Disederhanakan (2026-08-01, RUANG LINGKUP DIPERSEMPIT):** semula key ditaruh di `collector_deposits` (tabel Setoran, §B-11), tapi Setoran di-drop dari scope. Kebutuhan dedup/atomicity batch tetap ada terlepas dari itu → dipindah ke `payment_batches`, tabel ringan tanpa `declared_total`/`variance`/status selisih.

**Efek samping yang WAJIB ditutup bersamaan (jangan drop index sebelum ini ada):** index lama adalah **satu-satunya** guard dobel-submit di jalur single-payment (`PaymentController::store`, non-batch) — `PaymentObserver` cuma cek `amount <= 0`, tak ada burst-dedup. Ganti dengan burst-dedup heuristik di `PaymentObserver`, pola sama §A-4 (`InvoiceObserver`). Sudah masuk §A-7 #8.

**(b) `payment_number` jebol di skala target.**
`PAY-{Ym}-%04d` (`PaymentController.php:353`) = maksimum **9.999/bulan**, sementara premis §B-1 ~1000/hari ⇒ ~30.000/bulan → habis di hari ke-10. Ditambah MAX+1 dengan `lockForUpdate()` yang tak mengunci apa pun saat periode masih kosong, dan `bulkStore` bertransaksi per baris → race → tabrakan nomor unik → `$failed++` senyap. Ganti: sequence berbasis tabel (pola `PopSequence`), 6 digit, alokasi blok untuk batch.

**(c) Throughput batch.**
1000 baris = 1000 transaksi, masing-masing `lockForUpdate` invoice + query sequence. Bukan sekadar lambat: dua admin input paralel saling kunci. Perbaikan menyertai (b): satu transaksi per setoran, sequence dialokasikan sekali.

## C-3. Temuan desain (sudah dituangkan ke Bagian B)

| # | Temuan | Ditangani di |
|---|---|---|
| 1 | `recorded_total` kolom cache = mengulang penyakit desync yang dikritik §A-5 | §B-11 tabel — dicoret, dihitung live |
| 2 | Tanggal tagih lapangan dibuang → pendapatan lintas bulan salah potong, sengketa tak terbantahkan | §B-3 `collected_date`, §B-8.8 |
| 3 | "1 kolektor : 1 tanggal" menutup kasus dua sesi sehari | §B-11 Relasi — 1 setoran = 1 sesi |
| 4 | `deposit.pop_id` vs `payment.pop_id` bisa beda pada kolektor multi-POP → kebocoran scope | §B-11 "Invariant POP pada setoran" |
| 5 | `collector_id` FK polos tanpa cek role / scope / nonaktif | §B-3 "Validasi wajib pada `collector_id`", §B-7 no. 6 |
| 6 | Setoran tak menangkap "laporan tidak jujur" — kolektor kuasai kedua sisi persamaan | §B-11 "Batas deteksi Setoran" |
| 7 | Variance tak punya jalan pulang — bukan piutang, tak pernah ditutup | §B-11 "Penutupan selisih" |
| 8 | `payment_status` default `pending` vs controller selalu `valid` → semantik rekalkulasi ambigu | §A-7 #1 |
| 9 | Void payment (§A-6) jadi prasyarat, bukan backlog | §A-6, §A-7 #7 |

## C-4. Yang sudah benar — jangan diutak-atik

- Pemisahan `customers.collector_id` (rute) vs `payments.collected_by` (snapshot beku per transaksi), termasuk alasan "jangan salin buta".
- `collected_by = null` untuk jalur non-kolektor — inti kebenaran laporan setoran.
- Arah `variance = recorded − declared`.
- Kembalian fisik tak masuk sistem, tak jadi kredit.
- Halaman Tagihan tetap master; kolektor cuma view tersaring.
- `PaymentObserver` tolak `amount <= 0`.

## C-5. Urutan kerja

> **Superseded 2026-08-01 (RUANG LINGKUP DIPERSEMPIT).** Urutan di bawah ini versi lama (masih menyebut Fase 3 Setoran). Ikuti **Bagian E** untuk backlog eksekusi final — cuma 2 Fase aktif, Setoran & saldo kredit di-drop dari scope. Bagian ini dipertahankan sebagai jejak historis.

**Fase 0 — dokumen.** Selesai: koreksi §A-4, §A-6, §A-7, §B-3, §B-7, §B-8, §B-11, §B-12 + Bagian C ini.

**Fase 1 — prasyarat (semua wajib sebelum Bagian B disentuh).**
1. `Invoice::recalculateFromPayments()` + keputusan `payment_status` mana yang dijumlah (§A-7 #1).
2. `billing:reconcile-invoice-status`, dry-run dulu (§A-7 #2).
3. Sequence `payment_number` berbasis tabel, 6 digit (§A-7 #5 / §C-2b).
4. Keputusan + migration unique index `payments` (§A-7 #6 / §C-2a).
5. Void/reject payment + koreksi invoice satu transaksi (§A-7 #7 / §A-6).
6. Ganti `catch { $failed++ }` jadi daftar gagal + alasan (§A-5, §B-7 no. 2).

**Fase 2 — kolektor tanpa setoran.** Role `kolektor` di `RoleSeeder` + matrix permission, `customers.collector_id` (dengan tiga guard §B-3), `payments.collected_by` + `collected_date`, worklist read-only kolektor, batch nominal-per-baris. **Memberi ~90% nilai kecepatan yang jadi tujuan asli §B-1.**

**Fase 3 — Setoran.** ⛔ **DI-DROP dari scope (2026-08-01).** Lihat §B-11 dan kotak "RUANG LINGKUP DIPERSEMPIT" di header dokumen.

---
---

# BAGIAN D — VALIDASI ALUR vs KEBUTUHAN USER (2026-08-01)

**Status: TEMUAN & USULAN, BUKAN KEPUTUSAN.** Bagian ini menjawab tujuh pertanyaan validasi user dengan mengadu rancangan Bagian B ke kode nyata. Beberapa usulan di sini **mengubah** keputusan yang sudah dikunci di §B-8 — perubahan itu tidak berlaku sampai diputuskan eksplisit di §D-8.

## D-0. Ringkasan verdict

| # | Pertanyaan user | Verdict | Detail |
|---|---|---|---|
| 1 | Admin tahu di halaman mana dia membayar? | ⚠️ SEBAGIAN | 3 pintu masuk, tak ada peta; dok belum menetapkan pintu utama |
| 2 | Halaman lihat/download laporan harian/mingguan/bulanan | ⚠️ SEBAGIAN | `/reports/payments` + CSV sudah ada; tanpa preset periode, tanpa dimensi kolektor, scope POP pakai jalur usang |
| 3 | Bayar per kolektor, 1 kolektor banyak pelanggan | ✅ DIRANCANG | §B-5B; belum dikode; gap "banyak invoice per pelanggan" |
| 4 | Bayar sebagian / dicicil | ✅ SELESAI (2026-08-03) | unique index pematah sudah di-drop (E1.6); UI cicilan (list parent-child, kolom Cicilan Ke-N, petunjuk di form) selesai — §D-4. Sisa terbuka: indikator umur/aging cicilan |
| 5 | Sisa uang jadi saldo bulan depan | ❌ TETAP TIDAK | §B-8.6 mengunci "tak ada kredit"; `amount` tetap ditolak kalau melebihi sisa tagihan. `overpay_amount` (2026-08-03) cuma **mencatat** kelebihan, tidak menjadikannya saldo — lihat catatan di §D-5 |
| 6 | Kerja cepat, pelanggan sangat banyak | ⚠️ SEBAGIAN | arah benar; `paginate(10)` & sequence jebol jadi penghalang |
| 7 | Laporan bulanan yang datanya valid | ❌ BELUM | tak ada rekonsiliasi, tak ada tutup buku, scope laporan bocor |

---

## D-1. Di halaman mana admin membayar?

### Kondisi sekarang — tiga pintu, tak diumumkan

| Pintu | Route | Untuk |
|---|---|---|
| Per-invoice | `GET /invoices/{invoice}/payments/create` (`routes/web.php:186`) | satu pelanggan, nominal bebas |
| Bayar massal | `POST /invoices/bulk-pay` (`routes/web.php:188`), dipicu dari `/invoices` | banyak invoice, **lunas penuh saja** |
| Tab kolektor | — | belum ada (rancangan §B-5B) |

Halaman **Pembayaran** (`/payments`) sengaja **tidak punya** tombol create — cuma `index` + `show` (`routes/web.php:176-178`). Admin baru yang membuka menu "Pembayaran" untuk menginput akan menemui jalan buntu. Kebingungan yang disebut §B-1 terkonfirmasi di struktur route, bukan cuma di persepsi.

### Gap

§B-6 membahas UI tapi **tak pernah menetapkan pintu utama**. Tiga pintu tanpa hierarki = tiga kebiasaan berbeda antar admin.

### Usulan (belum diputus — lihat §D-8 no. 2)

> **Pintu utama input = halaman Tagihan (`/invoices`).** Semua jalur berangkat dari sana:
> - baris ber-badge kolektor tetap punya tombol "Bayar" → jalur non-kolektor, `collected_by = null`;
> - tombol "Setoran Kolektor" di header → masuk tab kolektor (jalur batch, §B-5B);
> - halaman Pembayaran diberi banner permanen: *"Halaman ini riwayat kas. Untuk input pembayaran, buka Tagihan."* + tombol ke `/invoices`.

---


### Usulan Dari User Atau Saya.
```
1. Yaaa itu benar jadi Baik untuk jalur kolektor maupun non kolektor tetap ada tombol bayar.
2. iyaaa itu benar . Tombol Setoran Kolektor untuk masuk kedalam Tab Kolektor. dan admin bisa memasukan banyak user sekaligus pada tab kolektor dengan masing masing kolektor.
3. yaaaa saya setuju jika halaman Pembayaran diberi banner permanen: "Halaman ini riwayat kas. Untuk input pembayaran, buka Tagihan." + tombol ke /invoices.
```

## D-2. Halaman laporan & download per hari / minggu / bulan

### Kondisi sekarang — sudah ada, ini bagian paling matang

- `/reports/payments` (`PaymentReportController::index`) — filter `start_date`, `end_date`, `pop_id`, `payment_method`, `status`; ringkasan `totalAmountSum` / `totalValidSum` / `totalPendingSum`.
- `/reports/payments/export` — CSV stream, `lazy(500)`, BOM UTF-8 (aman dibuka Excel).
- Setara tersedia: `/reports/invoices`, `/reports/customers`, `/reports/imports`.
- Rentang tanggal ditulis eksplisit `startOfDay`/`endOfDay` (`PaymentReportController:71,75`) — sengaja, supaya index `payment_date` tetap terpakai. Jangan diganti `whereDate()`.

### Empat kekurangan

1. **Tak ada preset periode.** Admin mengetik dua tanggal tiap kali. Butuh tombol cepat: Hari Ini / 7 Hari / Bulan Ini / Bulan Lalu / per `billing_period`. Murah, dampak besar ke kecepatan harian.
2. **Tak ada dimensi kolektor.** Begitu `collected_by` ada, laporan wajib punya filter **Kolektor**, kolom Kolektor, dan kolom Setoran di CSV — kalau tidak, seluruh Bagian B tak terlihat di laporan.
3. **Scope POP pakai jalur usang — BUG HIDUP.** `PaymentReportController:36` dan `:129` memfilter POP lewat `whereHas('users', ...)` = pivot `user_pops`. `CLAUDE.md` menandai jalur ini **tidak paham `pop_tree`**. Akibatnya user ber-scope `pop_tree` **kehilangan POP turunan** di dropdown & validasi laporan — laporan tampak "kurang" tanpa error apa pun. Yang benar: `EffectiveAccessService::getAllowedPopIds()` + `hasAllPopAccess()` (ingat array kosong = ALL_POP, jangan ditafsir sendiri). **Perlu dicek apakah controller laporan lain kena pola sama.**
4. **CSV saja.** `spatie/simple-excel` sudah jadi dependency (dipakai import pelanggan). Laporan bulanan yang diarsipkan lebih pantas XLSX.

---

### Jawab Empat Kekurangan di atas. dan ini dengan pendatapat saya yang masih bisa kita diskusikan jika ada kesalahan.
```
1. Baik sayua setuju dengan ituuu dan jika pendapat saya seperti ini bagaimana?
 - Hari Ini: 00:00:00 s.d. 23:59:59 hari berjalan.
 - 7 Hari Terakhir: now()->subDays(6)->startOfDay() s.d. now()->endOfDay().
 - Bulan Ini: now()->startOfMonth() s.d. now()->endOfMonth().
 - Bulan Lalu: now()->subMonth()->startOfMonth() s.d. now()->subMonth()->endOfMonth().
 - Per Billing Period: Mengisi tanggal sesuai rentang siklus penagihan aktif.

2. Kalau Menurut saya untuk mengatasi dimensi kolektor ini adalah sebagai berikut F
 - ilter: Dropdown filter berdasarkan ID Kolektor / Petugas.
 - Tampilan Laporan: Menampilkan kolom nama Kolektor di tabel web.
 - Export CSV: Menambahkan kolom Setoran (menandakan apakah dana transaksi sudah disetorkan oleh kolektor ke kasir/admin atau belum).
 - Kondisi Khusus: Tanpa atribut/filter kolektor ini, seluruh ringkasan dan data Bagian B (transaksi via kolektor) wajib disembunyikan agar tidak membingungkan.

3. Lakukan audit pada controller laporan lainnya (InvoiceReportController, TransactionReportController, dll.) untuk memastikan pola usang whereHas('users').

4.Saya Lebih setuju jika menggunakan Excel dengan format XLSX. 
```

---

## D-3. Bayar berdasar kolektor — 1 kolektor, banyak pelanggan sekaligus

Rancangan §B-3 (relasi 1:N) + §B-5B (tab kolektor, batch nominal-per-baris) **sudah menjawab pertanyaan ini**. Dua hal yang perlu ditegaskan:

1. **Batch = satu transaksi per sesi** — sudah masuk §B-7 no. 7 pada revisi 2026-07-31.
2. **GAP BARU: satu pelanggan bisa punya banyak invoice belum lunas.** Pelanggan menunggak 3 bulan lalu membayar sekaligus di satu kunjungan = 3 invoice, 1 setoran, 1 baris di mata kolektor. Rancangan §B-5B menyebut *"daftar pelanggan yang belum lunas"* — ambigu, dan implisit mengasumsikan 1 pelanggan = 1 invoice.

   **Usulan:** tab kolektor menampilkan **daftar invoice belum lunas** yang dikelompokkan per pelanggan, dengan aksi "bayar semua tunggakan" yang mengalokasikan **dari invoice paling tua (FIFO)**. Butuh mekanisme alokasi satu pembayaran ke banyak invoice — struktur yang sama dengan yang dibutuhkan §D-5. Dua kebutuhan, satu struktur.

---

## D-4. Bayar sebagian / dicicil

> ## ✅ STATUS: UI CICILAN SELESAI (2026-08-03)
>
> Mekanisme cicil sendiri memang sudah jalan sejak lama (lihat "Sudah jalan di kode" di bawah) — yang hilang selama ini **tampilannya**: admin tak punya cara melihat sebuah tagihan sedang dicicil ke berapa, dan form bayar tak memberi tahu konsekuensinya sebelum submit. Tiga hal itu sekarang ada:
>
> 1. **List tagihan (`invoices/index`)** — tagihan berstatus `sebagian` jadi baris induk yang bisa di-expand (pola chevron sama persis dgn Master POP, `togglePopChildren`). Baris anak = "Cicilan Ke-N" + no. pembayaran + tanggal + badge `payment_method` & `collected_by` **terpisah** (§D-9 no. 3) + sisa setelah cicilan itu. Begitu `lunas`, tombol expand hilang dan baris kembali jadi ringkasan Total & Sisa saja — sesuai usulan "card parent hilang".
> 2. **Detail tagihan (`invoices/show`)** — tab Riwayat Pembayaran dapat kolom "Cicilan Ke-N" + badge Cicil/Lunas/Ditolak. **Penomoran dihitung menaik dari pembayaran VALID saja**, bukan dari `$loop->iteration` (tabel ditampilkan menurun) dan bukan dari semua payment — pembayaran ditolak sengaja tak diberi nomor supaya urutan cicilan tetap rapat, tidak bolong.
> 3. **Form input pembayaran (`payments/create`)** — petunjuk hidup: begitu nominal < sisa tagihan, muncul "Tercatat sebagai Cicilan Ke-N, sisa setelah ini: Rp X"; kalau melunasi, muncul konfirmasi status jadi Lunas.
>
> **Yang TIDAK ikut dikerjakan:** poin 3 di "Tiga hal yang mematahkannya" (indikator umur/aging cicilan) — masih terbuka, menyambung §D-7. Poin 1 & 2 sudah lunas lebih dulu lewat E1.6 & E2.5.
>
> Test: `InstallmentAndOverpayDisplayTest` (8 test, termasuk regresi "pembayaran ditolak tak boleh memakan nomor cicilan" dan "tagihan lunas tak lagi punya baris anak").

### Sudah jalan di kode

`PaymentController::store` menerima nominal bebas; `remaining <= 0 ? LUNAS : SEBAGIAN` (`:205`); `Invoice hasMany Payment`. §B-12.1 sudah mencontohkan cicilan lintas setoran.

### Tiga hal yang mematahkannya

1. **Unique index `payments_invoice_date_amount_unique`** — cicil 50rb pagi lalu 50rb sore pada invoice & tanggal sama → unique violation, dan di `bulkStore` ditelan `catch { $failed++ }` (`PaymentController:313`). Sudah tercatat §A-7 #6 / §C-2(a). **Ini jalur paling mungkin membuat uang hilang tanpa jejak.**
2. **Bayar massal lama hanya lunas penuh** — `bulkStore:284` mengunci `amount = remaining_amount`. Digantikan tab kolektor nominal-per-baris.
3. **Tak ada indikator umur cicilan.** Invoice `sebagian` yang menggantung 3 bulan tampak identik dengan yang menggantung 3 hari. Butuh kolom aging di list — menyambung §D-7.

---

### JAWAB dengan Usulan saya
```
1. Disini kita masih binggung bagaimana cara mengatasi dari Cicilan itu bagaimana. Kalau saran saya seperti ini. jika terdapat pelanggan yang mencicil maka akan terdapat TOTAL dan SISA, yang dimana ketika dia mencicil maka Card tagihan awal tadi menjadi parent dan ketika dia mencicil maka akan masuk ke Child Card dengan contoh hirarki seperti ini. dan untuk Menampilkannya adalah ketika Parent di tekan maka akan Slide Down dan menampilkan Child-nya. Dan ketika sudah Lunas maka card parent tersebut akan hilang dan masuk kedalam card yang isinya hanya Total dan Sisa. Konsep Expanded dan Collapsenya sama seperti yang ada di Halaman Master POP / Cabang.

Parent Card (Tagihan Awal)
   Child Card (Cicilan 1)
   Child Card (Cicilan 2)
   Child Card (Cicilan 3)
```
## Informasi Invoice

| Invoice ID | Nama Pelanggan | Customer ID | Collector | Periode | Total Tagihan | Total Dibayar | Status |
|------------|----------------|-------------|-----------|---------|---------------:|---------------:|--------|
| INV-IN000037-2026_07_21_164556 | Wahyu Aulia Zahro | J1XXRQ000006 | Sandya | 2026-01 | Rp 133.065,00 | Rp 133.065,00 | Cicil / Sebagian |

## Riwayat Pembayaran

| Cicilan | Nominal Tagihan | Tanggal Pembayaran | Nominal Dibayar | Status | Metode Pembayaran |
|----------|----------------:|--------------------|----------------:|--------|-------------------|
| Cicilan Ke-1 | Rp 50.000,00 | 21 Juli 2026 14:15:24 | Rp 50.000,00 | Cicil | Collector (Sandya) |
| Cicilan Ke-2 | Rp 50.000,00 | 24 Juli 2026 16:18:22 | Rp 50.000,00 | Cicil | Admin (Non Collector) |
| Cicilan Ke-3 | Rp 33.065,00 | 27 Juli 2026 09:12:59 | Rp 33.065,00 | Lunas | Admin (Non Collector) |

> ⚠️ **Kolom "Metode Pembayaran" di contoh di atas SUDAH DIREVISI oleh keputusan §D-9 no. 3** — "Collector (Sandya)" / "Admin (Non Collector)" digabung dari dua konsep berbeda. Final: dua kolom/badge terpisah, `payment_method` (Cash/Transfer) dan `collected_by` (nama kolektor / "Langsung"). Contoh di atas dipertahankan sebagai jejak diskusi, jangan dijadikan acuan implementasi.
>
> ⚠️ **Blocker teknis unique index (poin 1 di atas tabel) BELUM terjawab oleh mockup ini saat pertama ditulis — sudah terjawab di §D-9 no. 2** (drop index, ganti `idempotency_key`).

---

## D-5. Sisa uang masuk ke bulan depan (saldo/kredit) — BERTENTANGAN DENGAN §B-8.6

> ## ⛔ DILUAR SCOPE (2026-08-01)
>
> **Diputuskan: DROP.** Keputusan asli §B-8.6 (kembalian fisik, tak ada kredit) **berlaku lagi tanpa syarat**. `payment_allocations` + `customer_credits` (perubahan struktural terbesar di dokumen ini) tidak dikerjakan. Analisa di bawah dipertahankan sebagai arsip kalau kebutuhan ini muncul lagi nanti — termasuk jawaban teknis rinci (ledger vs kolom, idempotensi debit, migrasi data lama) yang sudah pernah dibahas.
>
> ### ⚠️ CATATAN 2026-08-03 — `payments.overpay_amount` BUKAN pembatalan keputusan ini
>
> Ada kolom baru `payments.overpay_amount` (migration `2026_08_03_140001_...`). **Itu catatan informatif, bukan saldo kredit**, dan §D-5 tetap DROP. Bedanya tegas:
>
> | | `overpay_amount` (dikerjakan) | Saldo kredit §D-5 (tetap drop) |
> |---|---|---|
> | Sisi debit | **Tidak ada** — nilainya tak pernah berkurang, tak pernah dipakai | Ada, dipakai saat invoice berikutnya terbit |
> | Pengaruh ke invoice | **Nol** — `amount` tetap divalidasi `max: remaining_amount`, `paid_amount` tak pernah melampaui `total_amount` | Mengubah invoice jadi `sebagian`/`lunas` saat terbit |
> | Struktur | Satu kolom di `payments` | Ledger `customer_credits` + `payment_allocations` |
> | Penyelesaian | Manual di luar sistem (refund fisik / potong tagihan berikutnya secara manual) | Otomatis oleh generator invoice |
>
> **Konsekuensi yang diterima sadar:** kolom ini tak menjawab "sisa uang jadi saldo bulan depan" (§D-2 no. 5 tetap ❌). Ia cuma menjawab "kelebihan itu pernah terjadi, sebesar sekian, di pembayaran mana" supaya tak hilang jejak.
>
> **Larangan:** jangan pakai `overpay_amount` sebagai saldo — tak punya sisi debit, jadi begitu dikurangi di satu tempat ia langsung jadi penyakit desync §A-5 yang menyangkut uang pelanggan. Kalau kredit sungguhan dibutuhkan, jalurnya tetap arsip di bawah ini, utuh.
>
> **Susulan 2026-08-04 — desain 2-input DIBALIK jadi auto-split, dari laporan lapangan konkret.** Versi awal (dua field terpisah: `amount` dibatasi sisa tagihan + `overpay_amount` manual) gagal di lapangan: contoh nyata Boyke Santiago, sisa Rp141.097, admin terima Rp200.000 — admin harus HITUNG SENDIRI "200000 − 141097 = 58903" dan pisah ke dua field, dan karena `amount` masih dibatasi `max` HTML/validasi, mengetik 200000 langsung ditolak browser sebelum sempat kirim. Diperbaiki: **satu input** `amount` = TOTAL uang diterima dari pelanggan, TIDAK dibatasi sisa tagihan lagi. `PaymentController::store()` yang membagi otomatis di dalam `DB::transaction`: `appliedAmount = min(total, remaining)` disimpan ke `payments.amount` (tetap tak pernah melebihi `total_amount` invoice — jaminan §D-5 soal "paid_amount tak boleh lampaui total" TETAP UTUH, cuma pindah dari validasi input jadi kalkulasi), sisanya `overpayAmount = total - appliedAmount` ke `payments.overpay_amount`. UI (`payments/create.blade.php` & `quick-payment-modal.blade.php`) tinggal preview hidup: kalau amount > sisa, tampilkan "Rp X diterapkan ke tagihan (Lunas), Rp Y tercatat sebagai lebih bayar" — bukan input kedua yang mesti diisi manual. Field "Nominal Lebih" terpisah **dihapus total** dari kedua form.
>
> **Susulan 2026-08-04 — lebih bayar & cicilan disebar ke semua permukaan.** Sebelumnya info ini cuma nampak di list/detail tagihan. Ditambahkan `Payment::installmentContext()` (satu sumber kebenaran "Cicilan Ke-N" + apakah payment ini melunasi, dipakai lintas view) dan disebar ke: `payments/show` (badge di header + sub-baris di metric NOMINAL BAYAR + baris di struk print-only), `payments/receipt` (kwitansi thermal — baris Keterangan & Lebih Bayar), `invoices/show` (badge "Lebih Bayar Rp X" di header saat tagihan Lunas), `customers/show` (tab Riwayat Pembayaran Pelanggan), `payments/index` (Riwayat Pembayaran global). Cicilan Ke-N sengaja TIDAK ditambahkan ke `customers/show`/`payments/index` — kedua tabel itu lintas-invoice, penomoran cicilan cuma masuk akal per-invoice.
>
> **Susulan 2026-08-04 — label tombol ikut status.** Tombol "Bayar" di `/invoices` (list & detail) berubah jadi "Bayar Cicil" saat `invoice_status = sebagian`, supaya jelas beda dari pembayaran pertama.
>
> **Susulan 2026-08-03 — celah "Modal Bayar Cepat" ditemukan & ditutup.** User laporkan field/hint tak muncul sama sekali saat bayar. Penyebab: ada **dua** form input pembayaran, dan pengerjaan pertama cuma menyentuh satu — `payments/create.blade.php` (halaman penuh, ditembus lewat tombol "Bayar" di `invoices/show`). Jalur yang SEBENARNYA paling sering dipakai adalah `payments/partials/quick-payment-modal.blade.php` (modal AJAX, dipakai tombol "Bayar" di `/invoices` list DAN tab Tagihan Detail Pelanggan) — sama sekali beda file, tak ikut ke-review di batch pertama. Field Nominal Lebih + hint cicilan sekarang ditambahkan ke modal ini juga, disinkronkan lewat `qpRefreshInstallmentHint()` (versi JS dari hint yang sama di halaman penuh). Pelajaran: kalau ada dua jalur UI untuk satu aksi, cek KEDUANYA sebelum lapor "selesai".
>
> **Susulan 2026-08-03 — Tab Khusus dibangun.** Permintaan asli user eksplisit minta "Tab Khusus untuk menampung" pelanggan lebih bayar, bukan cuma badge di Detail Pelanggan. Dibangun `GET /payments/overpay` (`PaymentController::overpay()`, view `payments/overpay.blade.php`), link dari header `/payments`. **Tetap read-only** — daftar payment yang `overpay_amount > 0` & `payment_status = valid`, scope POP (`applyUserScope()`), tak ada aksi apa pun selain lihat & buka Detail. Reuse permission `payments.view` (bukan permission baru) — ini sudut pandang lain dari data yang sama, bukan objek bisnis baru. Test: 3 tambahan di `InstallmentAndOverpayDisplayTest`.

**Ini keputusan bisnis, bukan detail teknis. Dicatat apa adanya, belum diubah.**

### Dokumen saat ini mengunci kebalikannya

- §B-8 no. 6: *"Kembalian dikembalikan fisik, tak masuk sistem, tak jadi kredit."*
- §B-9 "Kembalian": *"sistem hanya mencatat nominal terpakai ke invoice"*.
- §B-9 "Masih terbuka": *"Kalau kelak kelebihan disimpan jadi kredit → fitur baru terpisah."*

### Kode juga menutupnya

`PaymentController:173` memvalidasi `'amount' => ...|max:'.$invoice->remaining_amount`, dan `:197-201` melempar `ValidationException` kalau nominal melebihi sisa. **Kelebihan bayar tidak bisa masuk sama sekali hari ini.**

### Bentuk yang benar kalau fitur ini diambil

Saldo kredit pelanggan (customer credit / deposit) — **bukan** "kelebihan disimpan di invoice".

```
Bayar 150.000 atas invoice sisa 100.000
  → Payment amount = 150.000
  → alokasi: 100.000 ke invoice  (LUNAS)
  →           50.000 ke saldo kredit pelanggan
Bulan depan invoice terbit 100.000
  → saldo 50.000 dipakai otomatis → pelanggan bayar kekurangan 50.000
```

### Yang dibutuhkan

| Butuh | Kenapa |
|---|---|
| Tabel `customer_credits` — **ledger** (`+` dari lebih bayar, `−` saat dipakai, referensi payment/invoice) | Saldo wajib ledger, bukan kolom `balance` di `customers`. Kolom saldo tunggal = persis penyakit desync §A-5, tapi menyangkut uang pelanggan. Ledger bisa diaudit baris per baris |
| Tabel `payment_allocations` — payment → banyak invoice + sisa ke kredit | `payments.invoice_id` sekarang **satu invoice** (FK NOT NULL, `create_payments_table:17`). Begitu satu pembayaran bisa menutup 3 tunggakan + sisa ke saldo, relasi 1:1 itu patah. **Ini perubahan struktural terbesar dari seluruh dokumen** |
| Longgarkan validasi `max: remaining_amount` | Dengan guard baru: kelebihan **wajib** punya tujuan (kredit), tak boleh menggantung tanpa alokasi |
| Pemakaian saldo otomatis saat invoice terbit | `GenerateMonthlyInvoicesCommand` + `InitialInvoiceService` konsultasi saldo → invoice terbit sudah `sebagian`/`lunas`. **Hati-hati:** menyentuh generator yang §A-2 sebut paling rawan dobel; `next_month_amount` (§A-3) juga ikut terpengaruh — kwitansi harus menyebut saldo, kalau tidak jadi bohong |
| Laporan saldo pelanggan | Saldo = **utang perusahaan ke pelanggan**. Wajib tampil di laporan bulanan, bukan angka tersembunyi |

### Konflik yang harus diputuskan

Kembalian fisik (§B-8.6) dan saldo kredit **tak bisa dua-duanya jadi default**:

- **Opsi A — default kembalian fisik, saldo hanya kalau pelanggan minta** (checkbox "titip untuk bulan depan" per baris batch). Paling dekat dengan kebiasaan lapangan sekarang; kolektor tak perlu mengingat saldo siapa pun.
- **Opsi B — default masuk saldo, kembalian fisik jadi pengecualian.** Lebih rapi di pembukuan, tapi mengubah perilaku kolektor di lapangan dan menambah beban penjelasan ke pelanggan.

Bonus: `payment_allocations` sekaligus menyelesaikan gap FIFO tunggakan di §D-3.

---

### JAWABAN SAYA 

```
Setuju penuh

Ledger, bukan kolom balance. Ini non-negotiable. Kolom saldo tunggal di customers akan busuk persis seperti §A-5 — race condition di concurrent payment gampang bikin dua transaksi baca saldo yang sama, keduanya commit, saldo jadi salah tanpa jejak. Ledger (customer_credits dengan tipe credit/debit + referensi) itu wajib kalau menyangkut uang.

payment_allocations adalah perubahan struktural terbesar — betul, dan efeknya menjalar ke semua tempat yang query payments.invoice_id langsung (laporan, kwitansi, rekonsiliasi). Ini bukan sekadar tambah tabel, tapi migrasi model mental dari "1 payment = 1 invoice" ke "1 payment = N alokasi".

Yang saya tambahkan

1. Pisahkan "bangun infrastruktur" dari "pilih default behavior".

Opsi A vs B itu sebenarnya bisa didamaikan: bangun customer_credits + payment_allocations sekali, lalu default-nya cukup jadi flag (overpayment_policy: physical | credit), bukan dua jalur kode berbeda. Rekomendasi saya: mulai dengan Opsi A sebagai default, karena:

Risiko rollout lebih kecil — kolektor di lapangan tidak perlu belajar kebiasaan baru
Validasi max: remaining_amount tetap jadi default guard (aman), checkbox "titip untuk bulan depan" adalah exception path yang eksplisit
Kalau nanti bisnis mau switch ke Opsi B, tinggal ganti default flag — infrastrukturnya sudah ada

Opsi B sebagai default sekarang berisiko tinggi karena ada 3 sistem yang harus konsisten sekaligus (kolektor lapangan, kwitansi, laporan bulanan) — kalau salah satu telat menyesuaikan, itu jadi sumber komplain pelanggan soal "uang saya kemana".

2. Race condition di pemakaian saldo otomatis.

GenerateMonthlyInvoicesCommand konsultasi saldo saat generate invoice — ini butuh locking. Kalau command ini pernah retry/dijalankan concurrent (yang menurut §A-2 sudah rawan dobel), konsumsi saldo yang tidak idempoten bisa memotong saldo pelanggan dua kali untuk satu invoice yang sama. Solusi: setiap entri ledger debit harus punya invoice_id unik sebagai referensi, dan sebelum membuat entri baru, cek dulu apakah entri untuk invoice itu sudah ada (unique constraint di (customer_credit_id, invoice_id, type) kalau relevan).

3. Rekonsiliasi saldo sebagai job terpisah, bukan trust blind.

Karena ini "utang perusahaan ke pelanggan", saya akan tambah scheduled job yang membandingkan SUM(customer_credits) per customer terhadap ekspektasi (total lebih bayar dikurangi total terpakai), mirip semangat kehati-hatian di §A-5. Kalau drift terdeteksi, alert — jangan biarkan silent sampai ketahuan saat pelanggan komplain.

4. Kwitansi & migrasi data lama.

Setuju soal kwitansi harus sebut saldo. Tambahan: begitu payments.invoice_id FK NOT NULL dilonggarkan, perlu strategi migrasi data existing — apakah payment lama otomatis dibuatkan satu baris payment_allocations (1:1) supaya laporan historis tetap konsisten tanpa cabang logika "payment lama vs baru" di kode pelaporan.
```

----

## D-6. Kerja cepat untuk pelanggan yang sangat banyak

Arah §B-5B benar. Penghalang konkret di kode:

| Hambatan | Lokasi | Perbaikan |
|---|---|---|
| `paginate(10)` di daftar tagihan | `InvoiceController:82` | 10 baris untuk ~1000 pembayaran/hari = ratusan kali klik halaman. Tab kolektor butuh pagination sendiri (100–200/halaman) atau muat penuh per kolektor |
| Sequence `payment_number` jebol 9.999/bulan + race | `PaymentController:353` | §A-7 #5 |
| `bulkStore` gagal senyap | `PaymentController:313` | daftar gagal + alasan (§B-7 no. 2) |
| Search sudah bagus | `InvoiceController:30-43` — nama, `customer_code`, `cid`, HP, ID legacy | pertahankan; tab kolektor pakai search yang sama |

Tambahan yang belum ada di dokumen tapi menentukan kecepatan nyata:

- **Input keyboard-only** — Tab antar baris, Enter simpan, tanpa mouse. Ini yang membuat Excel terasa cepat, bukan jumlah kolomnya.
- **Draft tersimpan** — 200 baris diketik lalu browser tertutup = kerja hilang. Simpan setoran sebagai `draft` sejak dibuka. §B-11 sudah punya status `draft`; pakai sungguhan, jangan cuma label.
- **Default nominal = sisa penuh** (sudah di §B-5B) — mayoritas baris tinggal dicentang.

---

## D-7. Laporan bulanan yang datanya valid

Bagian paling lemah. "Valid" butuh tiga hal, **belum ada satu pun**.

### a. Rekonsiliasi

Belum ada `billing:reconcile-invoice-status` (§A-7 #2). Sekarang tak ada yang bisa menjawab *"apakah `paid_amount` sama dengan Σ pembayarannya?"*. Ditambah temuan §A-4: **anti-dobel invoice tak punya lapis DB sama sekali** — laporan bulanan bisa memuat tagihan dobel tanpa terdeteksi. Command ini bukan pelengkap; ia syarat dari kata "valid".

### b. Tutup buku periode

Tak ada konsep periode terkunci. Konsekuensi hari ini: laporan Juli yang sudah dicetak dan diserahkan **bisa berubah** kalau ada yang menginput pembayaran ber-`payment_date` Juli pada bulan Agustus. Angka sama dicetak dua kali beda hasil = laporan tak dipercaya.

Butuh:
- kunci periode — setelah ditutup, `payment_date` di periode itu ditolak; koreksi lewat jurnal di periode berjalan;
- snapshot laporan bulanan tersimpan — siapa menutup, kapan, angkanya berapa.

### c. Dimensi yang lengkap

Laporan bulanan harus menjawab, dan sekarang belum bisa:

- kas per **kolektor** (butuh `collected_by`),
- **selisih setoran** yang belum ditutup (butuh §B-11 "Penutupan selisih"),
- **saldo kredit pelanggan** kalau §D-5 diambil — ini kewajiban perusahaan, harus muncul,
- **piutang / aging** per umur (0–30, 31–60, 60+),
- pemisahan `collected_date` vs `payment_date` (§B-3) — tanpa itu kas akhir bulan salah potong.

Dan bug scope POP di §D-2 no. 3 harus diperbaiki lebih dulu: laporan yang diam-diam kehilangan POP turunan tak bisa disebut valid, sebanyak apa pun fitur ditambahkan.

---

### JAWABAN SAYA
```
a. Rekonsiliasi

Setuju ini syarat, bukan pelengkap. Tambahan konkret untuk billing:reconcile-invoice-status:

Sumber kebenaran harus satu arah: paid_amount di invoice adalah derived value dari Σ payments (atau Σ payment_allocations kalau §D-5 diambil), bukan kolom yang di-update manual di banyak tempat. Command reconcile idealnya tidak hanya mendeteksi selisih, tapi punya mode --fix yang menghitung ulang dari sumber (payments) dan menimpa paid_amount yang salah, dengan log setiap koreksi (invoice_id, nilai lama, nilai baru, timestamp).
Deteksi invoice dobel (§A-4) sebaiknya jadi bagian dari command yang sama atau command sibling — cek unique constraint kandidat: (customer_id, period) untuk invoice bulanan. Kalau belum ada lapis DB, minimal command ini jadi jaring pengaman harian sebelum constraint di-DB benar-benar ditambahkan (yang idealnya tetap jadi target, bukan pengganti permanen — command reconcile tak boleh jadi alasan menunda unique constraint).
Jadwalkan reconcile sebelum proses tutup buku (bagian b), bukan paralel — supaya tutup buku selalu jalan di atas data yang sudah divalidasi.
b. Tutup buku periode

Setuju penuh dengan kunci periode + snapshot. Detail teknis yang perlu dipertegas:

Definisi "periode" harus eksplisit: dikunci berdasarkan payment_date atau collected_date? Ini nyambung ke poin c soal pemisahan keduanya (§B-3). Kalau kolektor menyetor tanggal 2 Agustus untuk kas yang dipegang sejak 30 Juli, collected_date = Juli tapi payment_date = Agustus. Kunci berdasarkan yang mana menentukan bulan mana yang "benar" mencatat kas itu. Saran: kunci berdasarkan payment_date (tanggal resmi sistem), tapi laporan periode itu tetap menampilkan breakdown collected_date untuk rekonsiliasi kas fisik kolektor.
Koreksi lewat jurnal, bukan edit langsung — ini penting untuk audit trail. Butuh tabel semacam period_adjustments atau reuse customer_credits-style ledger: setiap koreksi ke periode terkunci dicatat sebagai entri baru di periode berjalan, dengan referensi ke record asal yang "salah" (misal payment_id, invoice_id) dan alasan.
Snapshot — simpan bukan cuma angka agregat, tapi cukup detail untuk direproduksi kalau ada audit: minimal berupa export/JSON dari semua invoice + payment state pada saat penutupan, plus siapa & kapan. Snapshot inilah yang jadi "kebenaran resmi" bulan itu, terlepas dari perubahan data mentah setelahnya.
Urutan operasi: reconcile → generate laporan → review manual (opsional) → tutup periode → snapshot. Kalau tutup periode gagal di tengah jalan (misal command mati), harus idempotent / bisa di-retry tanpa menutup dua kali atau snapshot ganda.
c. Dimensi yang lengkap

Semua kebutuhan yang disebut make sense sebagai read model terpisah, bukan query ad-hoc tiap kali laporan diminta:

Kas per kolektor — butuh collected_by yang konsisten terisi (cek apakah field ini sudah ada di skema sekarang atau perlu migrasi tambah kolom).
Selisih setoran (§B-11) — perlu status eksplisit "belum ditutup" vs "ditutup", supaya laporan bisa memisahkan selisih yang masih open (butuh tindak lanjut) dari yang sudah diselesaikan.
Saldo kredit (§D-5) — kalau diambil, ini wajib relasi langsung ke customer_credits ledger yang dibahas di D-5, jadi laporan bulanan dan D-5 harus dibangun berbarengan/dependent, bukan berurutan sembarang.
Aging piutang (0–30/31–60/60+) — ini butuh definisi "umur" yang jelas: dihitung dari due_date invoice atau dari issued_date? Perlu dipastikan konsisten dengan definisi tunggakan di FIFO §D-3.
collected_date vs payment_date — setuju ini kritis untuk potongan kas akhir bulan yang benar; sudah disebut di atas terkait kunci periode.

Soal bug scope POP §D-2 no. 3 — setuju ini blocker duluan. Laporan yang datanya sendiri "bocor" diam-diam (POP turunan hilang dari scope) itu masalah integritas data, bukan kelengkapan fitur — kelas masalahnya beda dan harus selesai sebelum reconcile/tutup buku dibangun di atasnya, karena kalau tidak, reconcile hanya akan memvalidasi angka yang sudah cacat dari awal.
```

---

## D-8. Keputusan terbuka dari validasi ini

Tiga hal ini memblokir penulisan rancangan lanjutan. Belum dijawab per 2026-08-01.

1. **Saldo kredit (§D-5): Opsi A (opt-in per baris) atau Opsi B (default masuk saldo)?**
   Membalik keputusan §B-8.6 yang sudah dikunci, dan membawa `payment_allocations` (payment → banyak invoice) — perubahan struktur terbesar dari seluruh dokumen. Kalau diambil, §D-3 (FIFO tunggakan) ikut selesai.
2. **Pintu utama input = halaman Tagihan (§D-1)?**
3. **Tutup buku bulanan (§D-7b): dipakai atau tidak?**
   Kalau tidak dipakai, kata "valid" di kebutuhan laporan harus diturunkan jadi "akurat pada saat dicetak" — dan itu harus ditulis eksplisit supaya tak jadi ekspektasi yang tak pernah terpenuhi.

### Dampak ke §C-5 kalau ketiganya diambil

- **Fase 2** bertambah: `payment_allocations` + `customer_credits` (struktural, harus sebelum tab kolektor dipakai produksi — mengubahnya setelah ada data pembayaran jauh lebih mahal).
- **Fase 2** juga menyerap perbaikan bug scope POP laporan (§D-2 no. 3) — bug hidup, tak perlu menunggu fase mana pun.
- **Fase 4 baru:** tutup buku + snapshot laporan bulanan + aging.
---

### JAWABAN SAYA
```
Posisi saya konsisten dengan yang sudah dibahas:

Saldo kredit: mulai Opsi A (kembalian fisik default) + bangun infrastruktur payment_allocations/customer_credits sekali untuk melayani D-3 dan D-5 sekaligus.
Pintu utama = Tagihan: setuju, sudah dikonfirmasi user.
Tutup buku: kalau timeline mendesak, opsi realistis adalah tunda tutup buku formal ke Fase 4 terpisah, tapi — sesuai yang sudah ditulis dokumen sendiri — kata "valid" di kebutuhan laporan harus diturunkan eksplisit jadi "akurat pada saat dicetak" sampai Fase 4 selesai, supaya tidak ada ekspektasi diam-diam yang tak terpenuhi.
```

> ⚠️ **Superseded 2026-08-01 (RUANG LINGKUP DIPERSEMPIT).** Jawaban di atas ("mulai Opsi A + bangun infrastruktur payment_allocations/customer_credits") **dibatalkan** — user selanjutnya mengonfirmasi kebutuhan saldo kredit tak dibutuhkan sama sekali untuk kebutuhan simpel yang diminta. §D-5 penuh jadi ⛔ DILUAR SCOPE, bukan cuma "Opsi A dulu". Pintu utama = Tagihan tetap berlaku. Tutup buku tetap ditunda, tapi kini bukan "Fase 4 nanti dikerjakan" — seluruh Fase 4 lama ikut di-drop bareng Setoran (lihat Bagian E).

---

## D-9. Keputusan Final Putaran Kedua (2026-08-01)

Menjawab 3 pertanyaan sisa dari review terhadap jawaban §D-1 dan §D-4. **Ini keputusan terkunci**, bukan usulan lagi — beda dari status "usulan" di sisa Bagian D.

### 1. Satu batch = satu kolektor — DIKUNCI

Konsisten §B-2 (tab per kolektor). Batch lintas kolektor bikin tanggung jawab hasil batch tak jelas milik siapa kalau nanti dibutuhkan pelacakan per kolektor. Admin perlu proses banyak kolektor sekaligus → **banyak batch/sesi submit terpisah**, bukan satu batch gabungan. Diterapkan ke tabel `payment_batches` (disederhanakan dari rencana `collector_deposits` — Setoran di-drop dari scope, lihat kotak "RUANG LINGKUP DIPERSEMPIT" di header dan §B-11).

### 2. Unique index `payments`: DROP, ganti `idempotency_key` — DIKUNCI, dengan syarat tambahan

Opsi (ii) dari §C-2(a) dipilih. Alasan: opsi (i) (lebarkan index dengan `payment_batch_id`) tetap bolong di jalur non-kolektor karena `NULL ≠ NULL` di unique index MySQL — persis jalur paling rawan dobel-submit yang justru butuh proteksi.

Koreksi cakupan key: **per sesi submit (batch/form), bukan per baris di dalam batch** — cocok dengan atomicity per-batch (§B-7 no. 7). Ditaruh di `payment_batches.idempotency_key`, bukan di `payments`. (Disederhanakan 2026-08-01: semula direncanakan di `collector_deposits`/Setoran, dipindah ke tabel ringan setelah Setoran di-drop dari scope.)

**Syarat yang ditemukan saat review — WAJIB selesai bersamaan, jangan drop index duluan:**
Index lama adalah **satu-satunya** guard dobel-submit di jalur single-payment (`PaymentController::store`, non-batch, dipicu dari halaman Tagihan). `PaymentObserver` sekarang cuma menolak `amount <= 0` — tak ada burst-dedup seperti `InvoiceObserver`. Kalau index di-drop tanpa penggantinya di jalur ini, admin yang klik dobel tombol "Bayar" akan membuat dua payment identik tanpa penjagaan apa pun — invoice ter-update dua kali. **Perbaikan:** tambahkan burst-dedup heuristik ke `PaymentObserver`, pola sama §A-4 (`InvoiceObserver`: tolak insert identik customer+invoice+amount+date dalam jendela pendek). Masuk §A-7 #8.

### 3. Pisah kolom `payment_method` vs `collected_by` — DIKUNCI

Dua pertanyaan berbeda: *bagaimana* uang masuk (Cash/Transfer) vs *siapa* yang menagih (kolektor/null). Digabung jadi satu string ("Collector (Sandya)") menghilangkan kemampuan filter laporan by `payment_method` independen dari `collected_by` — padahal §D-2 no. 2 sudah minta dimensi kolektor sebagai filter terpisah. Tampilan card cicilan (§D-4 parent-child) render **dua badge berdampingan**, bukan satu string gabungan.

### Dampak ke dokumen lain

- §A-7 #6 & #8, §C-2(a), §B-11 tabel `collector_deposits` — sudah diperbarui di tempat.
- §D-4 parent-child card: keputusan tampilan (expand/collapse) diterima sebagai desain UI, **tapi belum menjawab blocker teknisnya** — kini terjawab lewat keputusan #2 di atas (drop index + idempotency_key + burst-dedup pengganti).
- §D-1 "batch lintas kolektor" — ambiguitas selesai, dikunci ke "satu batch satu kolektor" (keputusan #1).

---

## Referensi Silang

- `docs/billing-pembayaran/analisa-pencegahan-tagihan-dobel.md` — detail guard dobel & unique index.
- `docs/billing-pembayaran/perbandingan-tagihan-awal-vs-bulanan-legacy.md` — konvensi prorata legacy.
- `docs/billing-pembayaran/analisa-duplikasi-tagihan-pembayaran-migrasi-legacy.md` — cacat duplikasi data migrasi.

---

# BAGIAN E — URUTAN PENGERJAAN (Execution Backlog)

> **RUANG LINGKUP DIPERSEMPIT (2026-08-01).** Backlog di bawah **sudah trim** ke kebutuhan yang dikonfirmasi user: bedain Tagihan/Pembayaran, batch bayar cepat per kolektor, riwayat pembayaran tanpa banyak tab, cash/transfer/cicil tervalidasi. Setoran (rekonsiliasi kas kolektor) dan saldo kredit pelanggan **di-drop** — Fase 3 & 4 versi lama diarsipkan di bagian paling bawah, bukan dihapus.

**Status keseluruhan dokumen:** semua task di bawah **BELUM DIKERJAKAN** — dokumen ini masih tahap analisa/rancangan, nol baris kode diubah. Urutan wajib top-to-bottom per Fase; dalam satu Fase, task ber-tanda **[BLOCKING]** wajib selesai sebelum task lain di Fase yang sama mulai. **Cuma 2 Fase aktif** (Fase 1 & 2) — selesai keduanya, kebutuhan yang diminta sudah terpenuhi.

Setiap task merujuk balik ke bagian analisa (§A/§B/§C/§D) — jangan kerjakan dari checklist saja tanpa baca konteksnya, checklist ini ringkasan eksekusi, bukan pengganti analisa.

## FASE 1 — Prasyarat (wajib selesai semua sebelum Fase 2 disentuh)

> **✅ FASE 1 SELESAI (2026-08-03).** Semua 9 task dikerjakan, bukan cuma yang `[BLOCKING]`. Ringkasan:
> - **Model/skema baru:** `Invoice::recalculateFromPayments()`, `Invoice::SUBSCRIPTION_TYPES` + `hasActiveSubscriptionInvoiceForPeriod()`, tabel `payment_batches` + model `PaymentBatch`, tabel `payment_number_sequences` + model `PaymentNumberSequence`, `Payment::generatePaymentNumber()`, kolom `payments.reject_reason`/`rejected_at`/`rejected_by`/`payment_batch_id`.
> - **Migration:** drop `payments_invoice_date_amount_unique`; 5 migration baru (lihat commit/file di `database/migrations/2026_08_03_*`).
> - **Command baru:** `billing:reconcile-invoice-status` (`--fix`, `--fix-threshold`).
> - **Route baru:** `POST /payments/{payment}/reject` (permission `payments.reject` — sudah tersedia penuh di RBAC matrix sejak awal, tinggal dipakai).
> - **Bug hidup diperbaiki:** scope POP di `PaymentReportController` (`Pop::forUser()` menggantikan `whereHas('users', ...)`).
> - **Test baru:** 7 file (`PaymentBurstDuplicateSubmitTest`, `PaymentNumberSequenceWidthExpansionTest`, `ReconcileInvoiceStatusCommandTest`, `PaymentRejectRecalculatesInvoiceTest`, + 1 test baru di `ReportPaymentTest`), total 25 test baru.
> - **Regresi:** suite penuh 874 passed / 2 failed — dua yang gagal (`CustomerDocumentTest`, `CustomerInstallationTest`) tak menyentuh kode billing sama sekali (modul dokumen pelanggan & instalasi), terkonfirmasi pra-eksisting di luar scope Fase 1.
> - `vendor/bin/pint --dirty` bersih di semua file yang disentuh.
>
> **Belum dikerjakan (sengaja, di luar Fase 1):** UI batch kolektor yang benar-benar memakai `payment_batches.idempotency_key` (E2.5, Fase 2) — skema & mekanismenya sudah siap, tinggal disambung ke form.

### E1.1 — `Invoice::recalculateFromPayments()` [BLOCKING]

- **STATUS:** ✅ Selesai (2026-08-03) — `Invoice::recalculateFromPayments()`, dipakai `PaymentController::store` & `bulkStore`. Keputusan `payment_status`: VALID saja. Test: suite payment existing tetap hijau.
- **TUJUAN:** Satu fungsi sumber kebenaran untuk `paid_amount` / `remaining_amount` / `invoice_status`, dipakai semua jalur (single payment, batch, void, setoran).
- **KONTEKS MASALAH:** Logika ini sekarang ter-duplikasi di `PaymentController::store` (`:203-205`) dan `bulkStore` (`:289-308`). Dua salinan gampang menyimpang — perubahan di satu tempat, lupa di tempat lain, `invoice_status` desync dari `remaining_amount` (§A-5). Fase 2 (batch kolektor, void payment) butuh titik hitung tunggal ini, kalau tidak duplikasi bertambah lagi.
- **CHECKLIST:**
  - [ ] Extract method/service `Invoice::recalculateFromPayments()` (atau service terpisah, ikuti pola `app/Services/`).
  - [ ] **Putuskan eksplisit:** `payment_status` mana yang ikut dijumlah — `VALID` saja, atau `VALID + PENDING`? (kolom default `pending` di migration, tapi controller selalu tulis `valid` — ambigu sekarang). Tulis keputusan di komentar kode.
  - [ ] Ganti pemakaian inline di `PaymentController::store` dan `bulkStore` dengan pemanggilan method ini.
  - [ ] Test: nominal penuh → `LUNAS`; sebagian → `SEBAGIAN`; nol payment valid → `BELUM_DIBAYAR`.
- **ACCEPTANCE CRITERIA:** Tidak ada lagi kalkulasi `paid/remaining/status` inline di controller manapun. Test lama (`PaymentControllerTest` dkk) tetap hijau setelah refactor. Ada test baru yang mengunci keputusan `payment_status` yang dihitung.
- **Rujukan:** §A-5, §A-7 #1.

### E1.2 — Command rekonsiliasi `billing:reconcile-invoice-status`

- **STATUS:** ✅ Selesai (2026-08-03) — `--fix` cuma untuk selisih ≤ `--fix-threshold` (default Rp100rb), selisih besar ditandai PERLU REVIEW MANUAL, tak di-auto-fix. Audit koreksi otomatis lewat `RecordsAuditLogs` (tak perlu logging terpisah). Test: `ReconcileInvoiceStatusCommandTest` (4 test).
- **TUJUAN:** Jaring pengaman yang mendeteksi desync `paid_amount ≠ Σ payment` dan status yang tak sesuai `remaining_amount`, sebelum ketahuan lewat komplain pelanggan.
- **KONTEKS MASALAH:** Tak ada mekanisme yang memaksa konsistensi `invoice_status` terhadap `remaining_amount` (§A-5) — hanya terjaga selama semua jalur bayar lewat `PaymentController`. Ditambah temuan §A-4: **anti-dobel invoice tak punya lapis DB sama sekali**, jadi command ini juga satu-satunya jaring untuk tagihan dobel yang lolos observer.
- **CHECKLIST:**
  - [ ] Command `php artisan billing:reconcile-invoice-status --dry-run` (default dry-run, wajib flag eksplisit untuk apply).
  - [ ] Mode `--fix` **hanya** untuk selisih di bawah ambang tertentu; selisih besar wajib direview manusia — jangan auto-timpa buta.
  - [ ] Log setiap koreksi: `invoice_id`, nilai lama, nilai baru, timestamp, siapa/apa yang menjalankan.
- **ACCEPTANCE CRITERIA:** Command bisa dijalankan di database dgn data sengaja di-desync (test), melaporkan semua penyimpangan dengan benar, `--fix` tak mengubah apa pun tanpa flag eksplisit, output-nya bisa dibaca admin non-teknis.
- **Rujukan:** §A-7 #2, §D-7a.

### E1.3 — Konstanta bersama aturan "abaikan BATAL"

- **STATUS:** ✅ Selesai (2026-08-03) — `Invoice::SUBSCRIPTION_TYPES` + `Invoice::hasActiveSubscriptionInvoiceForPeriod()`, dipakai `InvoiceObserver` & `GenerateMonthlyInvoicesCommand`. `SatuTagihanLanggananPerPeriodeTest`/`AuditTagihanDobelTest` tetap hijau.
- **TUJUAN:** Satu sumber kebenaran untuk aturan "invoice `BATAL` tak menghalangi penggantinya di periode sama".
- **KONTEKS MASALAH:** Aturan yang sama ditulis dua kali — `InvoiceObserver:98` dan `GenerateMonthlyInvoicesCommand:78`. Ubah satu, lupa ubah yang lain = tagihan dobel lolos atau tagihan sah ketolak.
- **CHECKLIST:**
  - [ ] Tarik jadi konstanta/method bersama (mis. `Invoice::nonBlockingStatuses()` atau sejenis, ikuti gaya enum yang ada).
  - [ ] Ganti pemakaian di kedua lokasi.
  - [ ] Test regresi: `SatuTagihanLanggananPerPeriodeTest` dan `AuditTagihanDobelTest` tetap hijau.
- **ACCEPTANCE CRITERIA:** Grep `BATAL` di kedua file menunjuk ke sumber yang sama, bukan literal terpisah.
- **Rujukan:** §A-4, §A-7 #3.

### E1.4 — Ganti string literal status jadi enum di `GenerateMonthlyInvoicesCommand`

- **STATUS:** ✅ Selesai (2026-08-03) — `WorkflowTransition::ACTIVE`/`SUSPENDED`, bukan literal `'active'`/`'suspended'`.
- **TUJUAN:** Cegah silent-skip generator bulanan kalau nama status pelanggan berubah.
- **KONTEKS MASALAH:** `whereIn('status', ['active','suspended'])` (`:34`) pakai string literal. Kalau status berganti nama, generator diam-diam skip semua pelanggan — tak ada tagihan terbit, tanpa error apa pun. Silent failure paling berbahaya di seluruh alur billing karena tak ada gejala sampai pelanggan komplain tak dapat tagihan.
- **CHECKLIST:**
  - [ ] Ganti literal jadi enum status pelanggan yang sudah ada di codebase.
  - [ ] Tambah test yang sengaja mengubah nama status → assert command tidak silent-skip (harus error atau tetap match by enum).
- **ACCEPTANCE CRITERIA:** Tak ada string literal status di `GenerateMonthlyInvoicesCommand`. Test regresi ada untuk mencegah reintroduksi.
- **Rujukan:** §A-2, §A-7 #4.

### E1.5 — Sequence `payment_number` berbasis tabel [BLOCKING]

- **STATUS:** ✅ Selesai (2026-08-03) — tabel `payment_number_sequences`, `Payment::generatePaymentNumber()` (lock baris counter, sinkron MAX existing, lebar digit auto-expand lewat 9999). Test: `PaymentNumberSequenceWidthExpansionTest` (4 test).
- **TUJUAN:** Nomor pembayaran yang tak jebol dan tak race di skala ~1000 transaksi/hari.
- **KONTEKS MASALAH:** `PAY-{Ym}-%04d` (`PaymentController.php:353`) maksimum 9.999/bulan — premis Bagian B (~30.000/bulan) bikin sequence habis di hari ke-10. `lockForUpdate()` tak mengunci apa pun saat periode masih kosong; `bulkStore` bertransaksi per baris → race → tabrakan nomor unik → error ditelan `$failed++` senyap (§A-5).
- **CHECKLIST:**
  - [ ] Ikuti pola `PopSequence` yang sudah ada di repo — tabel sequence terpisah, bukan MAX+1 dari tabel utama.
  - [ ] Lebarkan format jadi 6 digit (`PAY-{Ym}-%06d` atau setara).
  - [ ] Untuk batch: alokasikan blok nomor sekali per sesi, bukan query per baris.
  - [ ] Test: generate 15.000 nomor dalam satu bulan simulasi tanpa tabrakan; test race condition dgn concurrent request.
- **ACCEPTANCE CRITERIA:** Tak ada lagi `orderBy(...)->first()` sebagai sumber nomor berikutnya. Test concurrent-request lolos tanpa duplikat maupun deadlock.
- **Rujukan:** §A-7 #5, §C-2(b).

### E1.6 — Drop unique index `payments`, ganti `idempotency_key` di `payment_batches` [BLOCKING]

- **STATUS:** ✅ Selesai (2026-08-03) — index lama di-drop, tabel `payment_batches` (+ model `PaymentBatch`) & `payments.payment_batch_id` dibuat. `idempotency_key` disiapkan di skema; pemakaian nyata (klien generate key per submit) menyusul di E2.5 (batch UI belum ada di Fase 1). Dideploy SETELAH E1.8 sesuai urutan wajib.
- **TUJUAN:** Buka jalan cicilan sah (nominal sama, hari sama) tanpa kehilangan proteksi dobel-submit di jalur batch kolektor.
- **KONTEKS MASALAH:** `payments_invoice_date_amount_unique` `(invoice_id, payment_date, amount)` menolak cicilan sah dan menolak pola koreksi "void lalu input ulang nominal & tanggal sama". **Keputusan final §D-9 no. 2, disederhanakan 2026-08-01:** drop index, ganti kolom `payment_batches.idempotency_key` (unik, per sesi submit — bukan per baris). Semula direncanakan di `collector_deposits` (tabel Setoran), tapi Setoran di-drop dari scope — dedup/atomicity batch tetap dibutuhkan independen, jadi dipindah ke tabel ringan `payment_batches` (id, `idempotency_key`, `submitted_by`, `submitted_at`, `collector_id` — **tanpa** `declared_total`/`variance`/status selisih).
- **CHECKLIST:**
  - [ ] Migration: drop `payments_invoice_date_amount_unique`.
  - [ ] Migration: tabel baru `payment_batches` (id, `idempotency_key` unique nullable, `submitted_by` FK users, `submitted_at`, `collector_id` FK users).
  - [ ] Migration: `payments.payment_batch_id` (nullable FK `payment_batches`) — terisi untuk jalur batch kolektor, `null` untuk jalur single-payment.
  - [ ] Klien generate key sekali per sesi submit batch (UUID/token), submit ulang dgn key sama = ditolak/diabaikan di server.
  - [ ] Test: submit batch dua kali dgn key sama → hanya satu set payment tercipta.
- **ACCEPTANCE CRITERIA:** Index lama sudah tak ada di skema. Idempotency key mencegah dobel-submit batch. **Migration ini TIDAK BOLEH di-deploy sebelum E1.8 (burst-dedup single-payment) selesai** — urutan deploy: E1.8 dulu, baru E1.6.
- **Rujukan:** §A-7 #6, §C-2(a), §D-9 no. 2.

### E1.7 — Void/reject payment + koreksi invoice satu transaksi

- **STATUS:** ✅ Selesai (2026-08-03) — route `POST /payments/{payment}/reject` (permission `payments.reject` — ternyata sudah lengkap tersedia di RBAC matrix, action code `REJECT` sudah ada di `payments` sejak awal). Kolom baru `reject_reason`/`rejected_at`/`rejected_by`. Audit `cancel` otomatis lewat mekanisme `Payment::booted()` yang sudah lama mengantisipasi ini. Test: `PaymentRejectRecalculatesInvoiceTest` (7 test: full/partial reject, audit log, validasi alasan wajib, tolak reject-dobel, permission, POP scope).
- **SUSULAN (2026-08-03):** backend di atas sempat **tanpa tombol sama sekali** — jalurnya hidup tapi tak bisa dipakai dari UI. Sekarang ada tombol "Tolak Pembayaran" di `payments/show`, dibuka jadi modal (alasan wajib, `ReasonValidationRule::required(1000)`), muncul **hanya** saat status masih `valid` dan pemakai punya `payments.reject`. Pembayaran yang sudah ditolak menampilkan panel alasan + siapa/kapan menolak, bukan tombol.
- **SUSULAN (2026-08-03):** `PaymentStatus::PENDING` **dihapus** dari enum — sistem ini tak punya alur verifikasi bertahap, semua jalur insert baru selalu `VALID` langsung, jadi case-nya cuma jadi status mati yang membingungkan. Default kolom `payments.payment_status` diubah ke `valid` (migration `2026_08_03_130001_...`). Data legacy berstatus `pending` dipetakan ke `VALID` di `mapLegacyPaymentStatus()` — bukan dibuang, karena baris itu memang sudah tercatat sebagai transaksi. Kartu "Pending" di laporan pembayaran diganti kartu "Ditolak".
- **TUJUAN:** Jalur resmi membatalkan payment yang salah input, dengan invoice ikut terkoreksi otomatis — bukan `LUNAS` palsu setelah payment-nya dibatalkan.
- **KONTEKS MASALAH:** `Payment.php:61` sudah mengantisipasi `payment_status → DITOLAK` (nulis audit `cancel`), tapi tak ada yang mengembalikan `invoice.paid_amount/remaining_amount/invoice_status`. Jebakan laten (§A-6) — admin salah input batch tetap butuh cara koreksi resmi, meski tak ada lagi kebutuhan "koreksi setoran" secara spesifik.
- **CHECKLIST:**
  - [ ] Route + controller/service untuk void payment (permission terpisah, bukan numpang `payments.create`).
  - [ ] Dalam satu `DB::transaction`: set `payment_status = DITOLAK` + panggil `Invoice::recalculateFromPayments()` (E1.1).
  - [ ] Audit log wajib (siapa, kapan, kenapa — alasan wajib diisi, ikuti pola `ReasonValidationRule` yang sudah ada).
  - [ ] Test: void payment yang membuat invoice `LUNAS` → invoice kembali `SEBAGIAN`/`BELUM_DIBAYAR` sesuai sisa payment valid.
- **ACCEPTANCE CRITERIA:** Tak ada state di mana payment `DITOLAK` tapi invoice masih menghitungnya sebagai lunas. Test regresi mengunci perilaku ini secara eksplisit (ikuti pola penamaan test regresi di `CLAUDE.md`: sesuai gejala, bukan kelas).
- **Rujukan:** §A-6, §A-7 #7.

### E1.8 — Burst-dedup `PaymentObserver` untuk jalur single-payment [BLOCKING]

- **STATUS:** ✅ Selesai (2026-08-03) — `PaymentObserver::rejectBurstDuplicate()`, window 300 detik, dideploy sebelum E1.6. Test: `PaymentBurstDuplicateSubmitTest` (3 test: dobel-submit ditolak, cicilan sah lolos, nominal beda lolos).
- **TUJUAN:** Ganti proteksi dobel-submit yang hilang dari jalur `PaymentController::store` (non-batch) begitu unique index lama dicabut di E1.6.
- **KONTEKS MASALAH:** Index `payments_invoice_date_amount_unique` adalah **satu-satunya** guard dobel-submit di jalur single-payment sekarang — `PaymentObserver` cuma menolak `amount <= 0` (`:20`), tak ada burst-dedup seperti `InvoiceObserver`. Kalau E1.6 di-deploy tanpa ini, admin yang klik dobel tombol "Bayar" menciptakan dua payment identik tanpa penjagaan apa pun, invoice ter-update dua kali.
- **CHECKLIST:**
  - [ ] Tambahkan guard di `PaymentObserver::creating()`, pola sama `InvoiceObserver` (§A-4): tolak insert identik (customer + invoice + amount + date) dalam jendela waktu pendek (mis. 300 detik, konsisten dgn window invoice).
  - [ ] Test: submit form bayar dua kali cepat (simulasi double-click) → payment kedua ditolak.
  - [ ] Test: dua payment sah beda hari/beda nominal tetap lolos (bukan false-positive).
- **ACCEPTANCE CRITERIA:** Deployed **sebelum** E1.6. Test dobel-submit lolos. Payment sah (cicilan beda waktu/nominal) tak ikut tertolak.
- **Rujukan:** §A-7 #8, §C-2(a), §D-9 no. 2.

### E1.9 — Perbaiki scope POP di `PaymentReportController` (bug hidup)

- **STATUS:** ✅ Selesai (2026-08-03) — `Pop::forUser()` menggantikan `whereHas('users', ...)` di `index()` & `export()`. Audit controller laporan lain: tak ada pola serupa di tempat lain. Test: `ReportPaymentTest::test_pop_tree_scope_user_sees_pop_and_descendants_in_report` (regresi, gagal sebelum fix).
- **TUJUAN:** User ber-scope `pop_tree` tidak lagi kehilangan POP turunan di laporan pembayaran.
- **KONTEKS MASALAH:** `PaymentReportController:36` dan `:129` memfilter POP lewat `whereHas('users', ...)` (pivot `user_pops`) — jalur yang menurut `CLAUDE.md` **tidak paham `pop_tree`**. Laporan tampak "kurang" tanpa error apa pun — ini kebocoran integritas data, bukan sekadar kelengkapan fitur. Bug hidup, langsung berdampak ke kebutuhan "lihat track pembayaran cepat" yang diminta user — laporan yang diam-diam bocor scope bukan laporan yang bisa dipercaya.
- **CHECKLIST:**
  - [ ] Ganti ke `EffectiveAccessService::getAllowedPopIds()` + `hasAllPopAccess()` (ingat: array kosong dari `getAllowedPopIds()` untuk ALL_POP itu ambigu, jangan ditafsir sendiri — pakai `hasAllPopAccess()`).
  - [ ] Audit controller laporan lain (`InvoiceReportController`, `CustomerReportController`, `ImportReportController`) untuk pola `whereHas('users', ...)` yang sama.
  - [ ] Test: user `pop_tree` dengan Mini POP turunan → laporan menampilkan data dari semua POP turunan, bukan cuma POP langsung.
- **ACCEPTANCE CRITERIA:** Tak ada lagi `whereHas('users', ...)` untuk scoping POP di controller laporan manapun. Test regresi untuk kasus `pop_tree` spesifik.
- **Rujukan:** §D-2 no. 3, §D-7c.

---

## FASE 2 — Kolektor & Kasir Cepat

> **✅ FASE 2 SELESAI (2026-08-03).** Semua 9 task aktif dikerjakan (E2.0–E2.5, E2.7–E2.9; E2.6 tetap DILUAR SCOPE). Ringkasan:
> - **RBAC baru:** role `kolektor` (`is_system`), feature+permission `kolektor.view` (satu-satunya, tanpa `payments.create`).
> - **Skema baru:** `customers.collector_id` (+3 guard), `payments.collected_by`/`collected_date`.
> - **Controller baru:** `CollectorAssignmentController` (Atur Kolektor), `CollectorWorklistController` (worklist read-only), `CollectorBatchController` (Tab Kolektor — inti fitur, validasi 2 fase + all-or-nothing + idempotency).
> - **UI:** banner "riwayat kas" di Pembayaran, tombol Atur Kolektor & Setoran Kolektor di Tagihan, badge kolektor terpisah dari metode bayar (invoice show, payments index/show), preset periode + filter Kolektor + export XLSX di laporan.
> - **Guard 3** (kolektor bermuatan tak boleh dinonaktifkan) di-hook ke `UserController::update()` — sempat salah taruh di `store()` saat implementasi, ketangkep test, diperbaiki.
> - **Test baru:** 7 file (`CollectorRoleCannotCreatePaymentsTest`, `CollectorAssignmentGuardsTest`, `PaymentCollectedByNotCopiedFromCustomerTest`, `CollectorWorklistScopeTest`, `CollectorBatchPaymentTest`, `PaymentReportCollectorFilterAndXlsxTest`), total 25 test baru — semua lolos.
> - **Regresi:** `RoleTest` perlu diupdate (hardcode jumlah role 9→10) — bukan bug, konsekuensi wajar penambahan role. 2 kegagalan pra-eksisting di luar billing (`CustomerDocumentTest`, `CustomerInstallationTest`) tetap ada, tak tersentuh Fase 2.
> - `vendor/bin/pint --dirty` bersih di semua file yang disentuh.
>
> **Backlog aktif dokumen ini SELESAI di titik ini** — kebutuhan simpel yang diminta user (bedain Tagihan/Pembayaran, batch bayar cepat per kolektor, riwayat tanpa banyak tab, cash/transfer/cicil tervalidasi) sudah terpenuhi. Fase 3 & 4 lama (Setoran, tutup buku, saldo kredit) tetap diarsipkan di bagian paling bawah, tak dikerjakan.

**Prasyarat:** Fase 1 [BLOCKING] items selesai & di-deploy (urutan E1.8 → E1.6 khususnya, lihat blocking note di E1.6). E1.2/E1.3/E1.4/E1.7 boleh menyusul paralel, tak wajib selesai dulu.

### E2.0 — Pintu bayar & banner Pembayaran

- **STATUS:** ✅ Selesai, **direvisi 2026-08-03 (putaran kedua)** — banner riwayat kas tetap sama. Header Tagihan disederhanakan: tombol "Atur Kolektor" + dropdown "Setoran Kolektor" digabung jadi **satu tombol "Kolektor"** menuju hub `/collectors` (lihat E2.5). Badge kolektor per baris di list Tagihan tak berubah.
- **TUJUAN:** Halaman Tagihan jadi satu-satunya pintu masuk input pembayaran (single-payment maupun batch kolektor); halaman Pembayaran murni riwayat, mengarahkan admin balik ke Tagihan kalau mau input.
- **KONTEKS MASALAH:** Keputusan sudah dikunci (§D-1, §D-9) tapi belum pernah dituliskan jadi task tersendiri — sebelumnya dianggap "murah, nempel ke E2.5" dan nyaris kececer. Tanpa ini, kebingungan admin baru yang jadi alasan awal seluruh fitur ini (§B-1: "halaman Tagihan vs Pembayaran terlihat mirip") tetap tak terselesaikan meski Tab Kolektor sudah jadi.
- **CHECKLIST:**
  - [ ] Halaman Tagihan (`/invoices`): baris biasa tetap punya tombol "Bayar" (jalur non-kolektor, `collected_by = null`).
  - [ ] Halaman Tagihan: baris ber-badge kolektor tetap punya tombol "Bayar" (bayar langsung/transfer meski ter-assign kolektor) **plus** tombol "Setoran Kolektor" di header yang membuka Tab Kolektor (E2.5).
  - [ ] Halaman Pembayaran (`/payments`): tambah banner permanen — *"Halaman ini riwayat kas. Untuk input pembayaran, buka Tagihan."* — dengan tombol ke `/invoices`. Pastikan `index`/`show` tetap read-only, tak ada tombol create di halaman ini (sudah begitu di route, tinggal dikuatkan di UI).
  - [ ] Review: admin baru (tanpa penjelasan lisan) bisa menemukan tombol bayar dari halaman Tagihan tanpa nyasar ke Pembayaran dulu.
- **ACCEPTANCE CRITERIA:** Tak ada jalur input pembayaran yang start dari halaman Pembayaran. Banner tampil konsisten di `/payments` (index & show). Review UI/UX memastikan satu pintu jelas, bukan tiga pintu tanpa hierarki seperti kondisi awal (§D-1).
- **Rujukan:** §D-1, §D-9 "Dampak ke dokumen lain".

### E2.1 — Role `kolektor` + matrix permission

- **STATUS:** ✅ Selesai (2026-08-03) — role `kolektor` (`RoleSeeder`, `is_system`), feature+permission `kolektor.view` (satu-satunya), tanpa `payments.create`. Test: `CollectorRoleCannotCreatePaymentsTest` (6 test).
- **TUJUAN:** Identitas RBAC untuk user yang menagih di lapangan, dengan hak paling minimal (tak bisa input pembayaran).
- **KONTEKS MASALAH:** Kolektor bukan Admin POP (§B-8 no. 4) — role terpisah, global (bukan per-cabang, larangan keras `CLAUDE.md`), dibatasi via scope.
- **CHECKLIST:**
  - [ ] Tambah `kolektor` ke `RoleSeeder` (`is_system`).
  - [ ] Matrix permission: **tanpa** `payments.create`. Worklist read-only = baca pelanggan ber-`collector_id` dirinya saja (bukan `customers.view` penuh).
  - [ ] Test RBAC: user role `kolektor` tak bisa akses endpoint input payment sama sekali (403), baik langsung maupun lewat batch.
- **ACCEPTANCE CRITERIA:** Role `kolektor` ada di seeder, permission matrix terdefinisi, test RBAC negatif (mencoba akses yang dilarang) lolos.
- **Rujukan:** §B-8 no. 4, §B-8 no. 5.

### E2.2 — `customers.collector_id` + 3 guard wajib

- **STATUS:** ✅ Selesai, **direvisi 2026-08-03 (putaran kedua)** — logic pindah dari `CollectorAssignmentController` (dihapus) ke `CollectorController::assign()`/`release()` dalam hub `/collectors/{collector}` (lihat E2.5). Guard 1 sekarang manifestasinya 404 (kolektor fixed dari route param, gak dipilih dari dropdown). Guard 2 tetap redirect+error. Guard 3 tetap di `UserController::update()`, tak berubah. Reassign/lepas tetap lewat `Customer::update()` → audit log otomatis. Test: `CollectorAssignmentGuardsTest` (6 test, +1 baru buat "release pelanggan yang bukan miliknya ditolak") — sempat menemukan bug nyata (guard 3 salah taruh di `store()`, diperbaiki putaran pertama).
- **TUJUAN:** Kolom rute permanen kolektor per pelanggan, dengan validasi yang mencegah kebocoran scope.
- **KONTEKS MASALAH:** FK polos ke `users` tak cukup. Tanpa guard: (a) siapa pun bisa jadi "kolektor" lewat request langsung; (b) assign pelanggan POP A ke kolektor bercakupan POP B = kebocoran lintas cabang (larangan keras #3 `CLAUDE.md`); (c) `nullOnDelete` diam-diam melepas semua pelanggan kalau kolektor dinonaktifkan, worklist lenyap tanpa jejak.
- **CHECKLIST:**
  - [ ] Migration `customers.collector_id` (nullable FK `users`).
  - [ ] Guard 1: validasi user target ber-role `kolektor` (server-side, bukan cuma dropdown).
  - [ ] Guard 2: validasi POP pelanggan ada di `EffectiveAccessService::getAllowedPopIds()` kolektor (pakai `hasAllPopAccess()` untuk kasus ALL_POP).
  - [ ] Guard 3: tolak nonaktifkan user `kolektor` yang masih memegang pelanggan (atau paksa reassign massal di layar yang sama).
  - [ ] Layar "Atur Kolektor" (assign/reassign/lepas) — masuk audit log (§B-8 no. 7).
  - [ ] Test: ketiga guard, plus reassign & lepas, plus audit log tercatat.
- **ACCEPTANCE CRITERIA:** Tiga guard lolos test negatif. Reassign/lepas tercatat di audit log dengan aktor & alasan.
- **Rujukan:** §B-3 "Validasi wajib pada `collector_id`", §B-4, §B-7 no. 6.

### E2.3 — `payments.collected_by` + `payments.collected_date`

- **STATUS:** ✅ Selesai (2026-08-03) — kolom + relasi `Payment::collector()`. `collected_by` cuma terisi di jalur batch (E2.5); jalur single-payment tetap `null` walau pelanggan punya `collector_id`. Test: `PaymentCollectedByNotCopiedFromCustomerTest` — skenario kritis §B-3 eksplisit dites.
- **TUJUAN:** Snapshot beku siapa yang menagih tiap payment + kapan uangnya diterima di lapangan (terpisah dari tanggal posting).
- **KONTEKS MASALAH:** `collected_by` **tidak** disalin otomatis dari `collector_id` — diisi sesuai jalur masuk (kolektor tab = terisi, jalur Tagihan langsung = `null`), supaya laporan setoran tak mencatat uang yang tak pernah ditagih kolektor bersangkutan (§B-3). `collected_date` mencegah pendapatan lintas-bulan salah potong ketika kolektor telat setor (§B-3).
- **CHECKLIST:**
  - [ ] Migration `payments.collected_by` (nullable FK `users`), `payments.collected_date` (nullable date).
  - [ ] Isi otomatis di jalur batch kolektor (Fase 2.5), `null` di jalur single-payment dari Tagihan.
  - [ ] Test: pelanggan ber-`collector_id = A` bayar transfer sendiri (bukan lewat A) → `collected_by = null`, **bukan** A.
- **ACCEPTANCE CRITERIA:** Test kritis di atas lolos eksplisit — mencegah bug "disalin buta" yang sudah diperingatkan di §B-3.
- **Rujukan:** §B-3, §B-8 no. 8, §D-9 no. 3 (persiapan pisah kolom tampilan).

### E2.4 — Worklist read-only kolektor

- **STATUS:** ✅ Selesai (2026-08-03) — `CollectorWorklistController` + `collector-worklist/index.blade.php`, gate `kolektor.view`. Nol tombol input. Test: `CollectorWorklistScopeTest` (3 test: scope per-kolektor, exclude lunas, nol elemen aksi).
- **TUJUAN:** Kolektor login melihat daftar pelanggannya yang belum lunas — nol tombol input.
- **KONTEKS MASALAH:** UI kolektor sengaja minimal (§B-8 no. 5) — kolektor tak berwenang input pembayaran (§B-1), cuma perlu tahu siapa yang harus didatangi.
- **CHECKLIST:**
  - [ ] Halaman worklist: pelanggan ber-`collector_id = auth()->id()` + status belum lunas.
  - [ ] Tak ada elemen input/aksi apa pun di halaman ini.
  - [ ] Test RBAC: kolektor tak bisa lihat pelanggan kolektor lain.
- **ACCEPTANCE CRITERIA:** Kolektor A login hanya melihat pelanggan A. Tak ada tombol/form input di halaman.
- **Rujukan:** §B-8 no. 5, §B-12 Langkah 5.

### E2.5 — Batch pembayaran per kolektor (tab kolektor)

- **STATUS:** ✅ Selesai, **direvisi 2026-08-03 (putaran kedua)** — atas permintaan user, "Atur Kolektor" (E2.2) + "Tab Kolektor" (E2.5) digabung jadi satu **hub `/collectors`**: daftar semua kolektor (jumlah pelanggan + total tunggakan) → klik masuk `/collectors/{collector}` dengan 2 tab: **Worklist & Bayar** (bayar 1-by-1 per baris ATAU centang banyak baris + Bayar Massal, dua-duanya lewat endpoint yang sama `CollectorBatchController::store()`) dan **Atur Pelanggan** (assign/reassign/lepas, di-scope ke kolektor ini — bukan pilih dari dropdown di tengah proses). `CollectorController` baru (`index`/`show`/`assign`/`release`); `CollectorAssignmentController` lama **dihapus total** (bukan diarsipkan — user pilih "diganti total" saat ditanya). `CollectorBatchController` disusutkan jadi cuma `store()` (validasi + all-or-nothing + idempotency tak berubah). Bug nyata ketemu & diperbaiki: JOIN Invoice+Customer buat hitung total tunggakan per kolektor nabrak `pop_id` ambigu (HasPopScope nulis kolom tanpa qualifier tabel) — diganti agregasi PHP. Test: `CollectorHubTest` (3 test baru) + `CollectorAssignmentGuardsTest`/`CollectorBatchPaymentTest`/`CollectorRoleCannotCreatePaymentsTest` disesuaikan ke route baru — total 26 test kolektor, semua hijau.
- **TUJUAN:** Admin memproses banyak pembayaran satu kolektor sekaligus, nominal & metode per baris, secepat Excel.
- **KONTEKS MASALAH:** `bulkStore` lama cuma lunas-penuh, transaksi per baris (§A-5, §C-2c), gagal senyap. Kebutuhan nyata (§D-3): satu pelanggan bisa punya banyak invoice tunggakan sekaligus.
- **Penyederhanaan (2026-08-01):** semula gap ini dijawab pakai `payment_allocations` (satu payment terpecah ke banyak invoice) — struktur itu ikut di-drop bareng §D-5. Cara lebih sederhana yang tetap memenuhi kebutuhan: batch cukup membuat **satu baris payment per invoice** (struktur `payments.invoice_id` 1:1 yang sudah ada, tak berubah), diurutkan FIFO dari invoice tertua. "Bayar semua tunggakan" di UI = beberapa payment tercipta sekaligus dalam satu transaksi batch, bukan satu payment yang dipecah.
- **CHECKLIST:**
  - [ ] Tab per kolektor, daftar **invoice** belum lunas (bukan daftar pelanggan) dikelompokkan per pelanggan.
  - [ ] Kolom per baris: nominal (default sisa penuh, bisa parsial), metode, `collected_date`.
  - [ ] Aksi "bayar semua tunggakan" per pelanggan → generate satu payment row per invoice tunggakan, urut FIFO dari invoice tertua, sisa nominal (kalau ada) dikembalikan fisik ke pelanggan (bukan disimpan sistem, konsisten §B-8.6).
  - [ ] **Satu transaksi DB untuk seluruh batch** (bukan per baris) — gagal satu baris → seluruh batch ditolak + daftar gagal & alasan per baris (§B-7 no. 2 & no. 7).
  - [ ] Wajib satu kolektor per batch — validasi server menolak batch yang mereferensi lebih dari satu `collector_id` (§D-9 no. 1).
  - [ ] `payment_batches.idempotency_key` per sesi submit (dari E1.6).
  - [ ] Pagination tab kolektor 100-200/halaman, bukan `paginate(10)` bawaan `InvoiceController` (§D-6).
  - [ ] Test: batch sukses semua, batch sebagian gagal (assert pesan gagal spesifik per baris, bukan angka telanjang), submit ulang dgn idempotency key sama.
- **ACCEPTANCE CRITERIA:** Batch 100+ baris tersimpan/gagal sebagai satu unit. Pesan gagal menyebut nama & alasan per baris. Tak bisa submit batch lintas kolektor.
- **Rujukan:** §B-5B, §B-7, §D-3, §D-6, §D-9 no. 1.

### E2.6 — ⛔ DILUAR SCOPE — `payment_allocations` + `customer_credits` (saldo kredit pelanggan)

- **STATUS:** DI-DROP dari scope (2026-08-01). Tidak dikerjakan.
- **Alasan:** §D-5 diputuskan diluar scope — kebutuhan simpel yang dikonfirmasi user tak menyebut saldo kredit. Gap FIFO tunggakan (§D-3) yang tadinya jadi alasan tambahan untuk task ini sudah terpecahkan lebih sederhana di E2.5 (banyak payment per batch, bukan satu payment terpecah) — jadi tak ada lagi kebutuhan struktural yang memaksa task ini.
- **Isi lengkap (checklist, acceptance criteria, detail teknis ledger/idempotensi) dipertahankan sebagai arsip di §D-5** — kalau kelak saldo kredit pelanggan beneran dibutuhkan, lanjut dari sana, bukan analisa ulang dari nol.
- **Rujukan:** §D-5 (arsip), §D-3 (sudah terpecahkan di E2.5 tanpa task ini).

### E2.7 — Preset periode & dimensi kolektor di laporan pembayaran

- **STATUS:** ✅ Selesai (2026-08-03) — 4 tombol preset (JS, submit form), dropdown filter Kolektor (`collected_by`), kolom Kolektor di tabel web. Pola `startOfDay`/`endOfDay` dipertahankan. Test: `PaymentReportCollectorFilterAndXlsxTest`.
- **TUJUAN:** Admin bisa lihat/download laporan Hari Ini / 7 Hari / Bulan Ini / Bulan Lalu / per Billing Period tanpa isi tanggal manual, plus filter & kolom Kolektor.
- **KONTEKS MASALAH:** `/reports/payments` sudah ada tapi admin harus ketik dua tanggal tiap kali (§D-2 no. 1); tak ada dimensi kolektor sama sekali (§D-2 no. 2) — begitu Fase 2 selesai, laporan tak bisa menunjukkan hasil kerja kolektor.
- **CHECKLIST:**
  - [ ] Tombol preset: Hari Ini (`startOfDay`–`endOfDay`), 7 Hari Terakhir (`subDays(6)->startOfDay()`–`endOfDay()`), Bulan Ini (`startOfMonth`–`endOfMonth`), Bulan Lalu (`subMonth()->startOfMonth()`–`endOfMonth()`), per `billing_period`.
  - [ ] Filter dropdown Kolektor (user ber-role `kolektor` dalam scope admin).
  - [ ] Kolom "Kolektor" di tabel web + CSV/XLSX export.
  - [ ] ~~Kolom "Setoran"~~ **DIHAPUS dari checklist** — kolom ini butuh `collector_deposits` (Setoran), yang di-drop dari scope. Tanpa Setoran tak ada status "sudah disetor/belum" untuk ditampilkan.
  - [ ] Tanpa filter kolektor aktif: seluruh ringkasan tetap termasuk data non-kolektor, tidak disembunyikan (klarifikasi: "sembunyikan" berlaku saat filter kolektor aktif tapi datanya kosong, bukan default state).
  - [ ] Pertahankan pola `startOfDay`/`endOfDay` eksplisit yang sudah ada — jangan ganti `whereDate()` (mematikan index).
- **ACCEPTANCE CRITERIA:** Semua preset menghasilkan rentang tanggal yang benar (test per preset). Filter Kolektor berfungsi di web & export. Kolom Kolektor konsisten antara tampilan web dan CSV/XLSX.
- **Rujukan:** §D-2, §D-9 no. 3 (kolom terpisah, bukan digabung).

### E2.8 — Export laporan format XLSX

- **STATUS:** ✅ Selesai (2026-08-03) — `PaymentReportController::exportXlsx()` pakai `spatie/simple-excel` (pola sama `TicketHistoryController::export()`), CSV dipertahankan sebagai opsi terpisah. Test: `PaymentReportCollectorFilterAndXlsxTest`.
- **TUJUAN:** Laporan bulanan yang diarsipkan dalam format yang lazim dipakai (Excel asli, bukan CSV).
- **KONTEKS MASALAH:** Export sekarang CSV saja. `spatie/simple-excel` sudah jadi dependency (dipakai import pelanggan) — tinggal dipakai untuk export juga.
- **CHECKLIST:**
  - [ ] Tambah opsi export XLSX di `/reports/payments/export` (dan laporan lain kalau relevan), pertahankan CSV sebagai opsi.
  - [ ] Pakai `spatie/simple-excel`, bukan dependency baru.
- **ACCEPTANCE CRITERIA:** File XLSX terbuka bersih di Excel, kolom & data sama dengan versi CSV.
- **Rujukan:** §D-2 no. 4.

### E2.9 — Pisah kolom `payment_method` vs `collected_by` di UI

- **STATUS:** ✅ Selesai (2026-08-03) — badge terpisah di `invoices/show` (tab Riwayat Pembayaran), `payments/index`, `payments/show`. Tak ada lagi string gabungan.
- **TUJUAN:** Tampilan riwayat pembayaran (termasuk card cicilan parent-child §D-4) menampilkan dua badge terpisah, bukan satu string gabungan.
- **KONTEKS MASALAH:** Contoh awal di §D-4 menggabungkan jadi satu kolom ("Collector (Sandya)") — menghilangkan kemampuan filter independen yang justru dibutuhkan E2.7.
- **CHECKLIST:**
  - [ ] Card/tabel riwayat pembayaran: badge `payment_method` (Cash/Transfer/QRIS/Lainnya) + badge `collected_by` (nama kolektor atau "Langsung") berdampingan.
  - [ ] Terapkan ke halaman invoice show, payments show/index, dan card parent-child cicilan (§D-4).
- **ACCEPTANCE CRITERIA:** Tak ada lagi string gabungan seperti contoh lama di §D-4. Review visual di ketiga halaman.
- **Rujukan:** §D-9 no. 3.

---

## FASE 3 (ARSIP) — Setoran Kolektor — ⛔ DILUAR SCOPE (2026-08-01)

> **Fase ini tidak dikerjakan.** User mengonfirmasi kebutuhan sebenarnya tak sampai butuh rekonsiliasi kas kolektor (declared/recorded/variance) — cukup batch bayar cepat per kolektor, yang sudah dipenuhi Fase 2/E2.5 tanpa fitur ini. Isi di bawah dipertahankan sebagai arsip: kalau nanti masalah "kolektor kurang setor" beneran muncul di lapangan (bukan cuma diantisipasi), lanjut dari sini — termasuk 3 keputusan yang sempat mengganjal (kontrol anti-fraud, opsi penutupan selisih, invariant POP setoran), sudah dianalisa lengkap, tinggal diputuskan ulang.

**Prasyarat (kalau nanti diaktifkan):** Fase 1 & 2 stabil di produksi, **dan** tiga keputusan §B-11 berikut sudah diambil:
- Kontrol sisi pelanggan (kwitansi bernomor / notifikasi pelanggan / aging + `visit_result`) — minimal satu.
- Opsi penutupan selisih: A (pointer `settles_deposit_id`) atau B (ledger saldo kolektor).
- Invariant POP setoran: ketat (satu setoran satu POP) atau longgar (scope dari payment).

> Setoran tanpa keputusan pertama = teater kontrol — status `matched` dibaca "beres" padahal cuma "aritmatika laporan konsisten" (§B-11 "Batas deteksi Setoran").

### E3.1 — Tabel `collector_deposits`

- **STATUS:** Belum Dikerjakan
- **TUJUAN:** Wadah satu sesi serah-terima kas kolektor→admin, dengan `declared_total` (kas fisik) vs `recorded_total` (turunan live) sebagai titik rekonsiliasi.
- **KONTEKS MASALAH:** Tanpa header ini, kurang-setor kolektor tak terdeteksi — sistem cuma tahu apa yang diketik admin, bukan apa yang seharusnya diterima (§B-11 "Ide inti").
- **CHECKLIST:**
  - [ ] Migration sesuai skema §B-11 (termasuk `idempotency_key` dari E1.6, `collector_id` wajib tunggal per §D-9 no. 1).
  - [ ] `recorded_total` **live query**, bukan kolom cache (`SUM(payments.amount) WHERE collector_deposit_id = ?`).
  - [ ] Status lifecycle: `draft` → `matched`/`selisih_open` → `selisih_settled`/`selisih_written_off` → `closed`.
  - [ ] `payments.collector_deposit_id` (nullable FK) + invariant: terisi → `collected_by` wajib = `collector_deposits.collector_id`.
- **ACCEPTANCE CRITERIA:** Skema sesuai §B-11 tabel (yang sudah direvisi). Query `recorded_total` teruji benar & cukup cepat untuk 1000 baris.
- **Rujukan:** §B-11.

### E3.2 — Alur input setoran (declared → batch → variance)

- **STATUS:** Belum Dikerjakan
- **TUJUAN:** Admin buka setoran, input declared_total, proses batch baris kolektor, sistem hitung variance otomatis.
- **KONTEKS MASALAH:** Alur lengkap ada di §B-12 (contoh end-to-end) dan §B-12.1 (cicilan lintas-setoran).
- **CHECKLIST:**
  - [ ] Layar "Setoran Baru" dari tab kolektor: input `declared_total`.
  - [ ] Reuse batch E2.5, tambahkan link ke `collector_deposit_id`.
  - [ ] Hitung `variance = recorded − declared` setelah submit; `selisih` (≠0 setelah batch selesai, **bukan** selama `draft`) wajib `note`.
  - [ ] Invariant POP sesuai keputusan yang diambil (ketat/longgar).
  - [ ] Test skenario §B-12 (Budi/Sri) dan §B-12.1 (cicilan lintas-setoran) sebagai test case literal.
- **ACCEPTANCE CRITERIA:** Skenario §B-12 & §B-12.1 lolos sebagai automated test, bukan cuma manual.
- **Rujukan:** §B-11 "Alur setoran", §B-12, §B-12.1.

### E3.3 — Kontrol sisi pelanggan (anti-fraud)

- **STATUS:** Belum Dikerjakan — **menunggu pilihan opsi**
- **TUJUAN:** Menutup celah "kolektor tak melapor" yang tak terdeteksi oleh variance declared/recorded semata.
- **KONTEKS MASALAH:** Setoran hanya menangkap "laporan jujur, kas tak jujur". Kolektor yang sengaja tak melaporkan satu pelanggan → `declared = recorded` → `matched` ✔ padahal uang pelanggan hilang (§B-11 "Batas deteksi Setoran").
- **CHECKLIST:**
  - [ ] Pilih minimal satu: (1) kwitansi bernomor prasetak + kolom `payments.receipt_number`, (2) notifikasi WA/SMS ke pelanggan saat payment ter-posting, (3) aging piutang + `visit_result` per kunjungan (bayar/tidak di rumah/menolak/janji).
  - [ ] Implementasi sesuai pilihan.
- **ACCEPTANCE CRITERIA:** Minimal satu kontrol berjalan & teruji mendeteksi skenario "kolektor tak melapor" di data simulasi.
- **Rujukan:** §B-11 "Batas deteksi Setoran".

### E3.4 — Penutupan selisih (piutang kolektor)

- **STATUS:** Belum Dikerjakan — **menunggu pilihan opsi**
- **TUJUAN:** Variance yang positif (kurang setor) punya jalan pulang yang bisa diaudit — bukan catatan teks yang menggantung selamanya.
- **KONTEKS MASALAH:** Tanpa mekanisme ini, selisih jadi dua entri mengambang tak terhubung saat kolektor melunasi kekurangan di lain waktu (§B-11 "Penutupan selisih").
- **CHECKLIST:**
  - [ ] Pilih Opsi A (pointer `settles_deposit_id` antar-setoran) atau Opsi B (ledger saldo kolektor `collector_balance_entries`).
  - [ ] `selisih_open` bukan status terminal — setoran tak boleh `closed` sebelum `settled`/`written_off`.
  - [ ] `written_off` wajib approval atasan + alasan.
  - [ ] Audit log untuk semua perubahan status selisih.
- **ACCEPTANCE CRITERIA:** Ada laporan "kolektor X menunggak Y" yang bisa dijawab sistem. Tak ada setoran `closed` dengan selisih terbuka.
- **Rujukan:** §B-11 "Penutupan selisih".

---

## FASE 4 (ARSIP) — Laporan & Tutup Buku — ⛔ DILUAR SCOPE (2026-08-01)

> **Fase ini tidak dikerjakan.** Tutup buku periode + snapshot audit adalah kontrol enterprise yang tak disebut di kebutuhan simpel yang dikonfirmasi user. Konsekuensinya diterima secara eksplisit: label "valid" untuk laporan bulanan **diturunkan** jadi **"akurat pada saat dicetak"** — laporan yang sudah dicetak bisa berubah kalau ada input `payment_date` di periode itu setelahnya (§D-7b). Kalau ini jadi masalah nyata (laporan yang sudah diserahkan lalu berubah), lanjut dari sini.
>
> E4.2 (dimensi laporan lengkap: kas per kolektor, aging piutang) sebagian sudah tak relevan (selisih setoran, saldo kredit — keduanya di-drop). Yang masih relevan (aging piutang, `collected_date` vs `payment_date`) sudah cukup ditampung di E2.7, tak perlu Fase terpisah.

**Prasyarat (kalau nanti diaktifkan):** Fase 1–3 stabil.

### E4.1 — Tutup buku periode + snapshot

- **STATUS:** Belum Dikerjakan
- **TUJUAN:** Laporan bulanan yang sudah ditutup tak bisa berubah lagi, dengan jejak siapa/kapan/angka berapa.
- **KONTEKS MASALAH:** Sekarang laporan Juli yang sudah dicetak bisa berubah kalau ada input `payment_date` Juli di bulan Agustus — angka sama dicetak dua kali beda hasil (§D-7b).
- **CHECKLIST:**
  - [ ] Kunci periode berdasarkan `payment_date` (bukan `collected_date`) — tapi laporan tetap tampilkan breakdown `collected_date` untuk rekonsiliasi kas fisik kolektor.
  - [ ] Setelah dikunci: insert/update `payment_date` di periode itu ditolak; koreksi lewat jurnal (`period_adjustments` atau reuse pola ledger `customer_credits`) di periode berjalan, referensi ke record asal + alasan.
  - [ ] Snapshot: bukan cuma angka agregat — cukup detail untuk direproduksi saat audit (minimal export/JSON semua invoice + payment state saat penutupan), plus siapa & kapan.
  - [ ] Urutan operasi wajib: **reconcile (E1.2) → generate laporan → review manual (opsional) → tutup periode → snapshot.** Proses idempotent — retry setelah command mati di tengah jalan tak boleh menutup dua kali / snapshot ganda.
- **ACCEPTANCE CRITERIA:** Payment ber-`payment_date` di periode terkunci ditolak sistem. Snapshot tersimpan & bisa direproduksi. Proses tutup buku idempotent (test dgn simulasi crash di tengah).
- **Rujukan:** §D-7b, §D-9 (jawaban D-7 user).

### E4.2 — Dimensi laporan bulanan lengkap

- **STATUS:** Belum Dikerjakan
- **TUJUAN:** Satu laporan bulanan menjawab kas per kolektor, selisih setoran terbuka, saldo kredit pelanggan, dan aging piutang — bukan query ad-hoc tiap diminta.
- **KONTEKS MASALAH:** §D-7c: lima dimensi ini belum bisa dijawab laporan sekarang; masing-masing sudah punya sumber data dari fase sebelumnya (E2.3, E3.4, E2.6, E1.2).
- **CHECKLIST:**
  - [ ] Read model/laporan terpisah (bukan query ad-hoc) untuk: kas per kolektor, selisih setoran open vs closed, saldo kredit pelanggan (kalau E2.6 diambil), aging piutang (0–30/31–60/60+).
  - [ ] Definisi "umur" aging eksplisit: dari `due_date` atau `issued_date`? Harus konsisten dgn definisi tunggakan FIFO di E2.5.
  - [ ] Pemisahan `collected_date` vs `payment_date` tampil di laporan (bukan cuma tersimpan).
- **ACCEPTANCE CRITERIA:** Kelima dimensi terjawab di satu laporan bulanan, definisi "umur" tertulis eksplisit di kode/dokumentasi.
- **Rujukan:** §D-7c.

---

## Ringkasan Ketergantungan Antar-Fase

**Superseded 2026-08-01 (RUANG LINGKUP DIPERSEMPIT).**

```
Fase 1 (9 task, 4 blocking) ──► Fase 2 (9 task, minus E2.6) ──► SELESAI
                                                                  │
                          ┆ diarsipkan, tak dikerjakan ┆
                          Fase 3 — Setoran (⛔ drop)
                          Fase 4 — Tutup Buku (⛔ drop)
                          E2.6 — Saldo kredit (⛔ drop)
```

**Yang memblokir mulainya coding sama sekali:** tak ada. Fase 1 bisa mulai kapan saja, semua keputusan desain untuk Fase 1 & 2 sudah dikunci (§D-9). **Setelah Fase 2 selesai, backlog aktif dokumen ini habis** — kebutuhan yang diminta user (bedain Tagihan/Pembayaran, batch bayar cepat per kolektor, riwayat tanpa banyak tab, cash/transfer/cicil tervalidasi) sudah terpenuhi tanpa perlu Fase 3/4.

**Konsekuensi yang diterima sadar** (bukan diabaikan diam-diam):
- Tak ada deteksi otomatis kalau kolektor kurang setor kas — ditangani manual di luar sistem kalau terjadi.
- Kelebihan bayar selalu kembali fisik, tak ada saldo tersimpan untuk bulan depan.
- Laporan bulanan berstatus "akurat saat dicetak", bukan "terkunci/tak bisa berubah" — kalau ada input mundur ke periode yang sudah dilaporkan, angkanya bisa bergeser.

Kalau salah satu dari tiga ini nanti jadi masalah nyata di lapangan, Bagian B (§B-11), §D-5, dan Fase 3/4 arsip sudah siap dilanjut tanpa analisa ulang dari nol.
