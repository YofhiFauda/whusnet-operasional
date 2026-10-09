# Rancangan: Prefix Nomor Invoice per Jenis Tagihan (BUG 13)

> Status: **Diimplementasikan** (2026-10-01) — lihat §7 untuk penyimpangan dari rancangan yang ditemukan saat coding & **1 konflik terbuka yang belum bisa diselesaikan sendiri (§7.3)**.
> Sumber: laporan user (BUG 13), format diberikan user 2026-10-01.
> Legacy (data invoice lama format `INV-...`) **sengaja tidak dicakup** — keputusan user: masih tahap pengembangan, data lama akan direset diganti data baru (backfill migration di §6 TIDAK dikerjakan sesi ini, lihat §7.2).

---

## 1. Masalah

Format nomor invoice saat ini **satu prefix untuk semua jenis** (`InvoiceNumberGenerator::nextFor()`):

```
INV-{YYYYMM}-{NNNN}        contoh: INV-202609-0012
```

Urutan dibagi per bulan kalender saja (`billing_period`), tanpa info jenis tagihan — dari nomor invoice saja tidak bisa dibedakan Aktivasi/Bulanan/Perbaikan/Lainnya/Pindah Lokasi.

Target baru (format diberikan user):

```
{PREFIX}-{YYYYMMDD}-{NNNNNN}
```

| Jenis Tagihan | Prefix | Contoh |
|---|---|---|
| Aktivasi | `ACT` | `ACT-20260919-000012` |
| Bulanan | `TAG` | `TAG-20260919-000012` |
| Perbaikan | `MTN` | `MTN-20260919-000012` |
| Lainnya | `OTH` | `OTH-20260919-000012` |
| Pindah Lokasi | `REL` | `REL-20260919-000012` |

## 2. Sumber jenis tagihan — dua enum, bukan satu

Prefix **tidak** bisa dipetakan 1:1 dari `InvoiceType` saja — `InvoiceType::MANUAL` dipecah lagi jadi 3 sub-kategori lewat `ManualInvoiceCategory` (ADHOC-70). Pemetaan sebenarnya:

| `InvoiceType` | `ManualInvoiceCategory` | Prefix |
|---|---|---|
| `AWAL` | — | `ACT` |
| `BULANAN` | — | `TAG` |
| `MANUAL` | `PERBAIKAN` | `MTN` |
| `MANUAL` | `LAINNYA` | `OTH` |
| `MANUAL` | `PINDAH_LOKASI` | `REL` |
| `INSIDENTAL` *(legacy)* | — | **di luar scope** — lihat §5 |

Resolusi prefix jadi fungsi `(InvoiceType, ?ManualInvoiceCategory) -> string`, bukan `InvoiceType::prefix()` yang sudah ada sekarang (`app/Enums/InvoiceType.php:43-51` — nilainya `pembayaran-awal`/`bulan`/`insidental`/`manual`, dipakai untuk kebutuhan lain, bukan nomor invoice; **jangan dirombak**, buat sumber prefix baru khusus penomoran).

## 3. Struktur nomor baru

```
{PREFIX}-{YYYYMMDD}-{NNNNNN}
```

- `PREFIX` — dari tabel §2.
- `YYYYMMDD` — tanggal terbit invoice (bukan periode billing `YYYYMM` seperti sekarang — ganti granularitas dari bulan ke hari).
- `NNNNNN` — urutan 6 digit (naik dari 4 digit sekarang), **per kombinasi prefix + tanggal** (bukan per bulan lagi). Reset ke `000001` tiap hari, per prefix.

Konsekuensi: `InvoiceNumberGenerator::nextFor()` harus diubah signature-nya (terima jenis tagihan + tanggal terbit, bukan cuma `billingPeriod`), dan query `lockForUpdate()`-nya ganti pola `LIKE` ke `"{$prefix}-{$tanggal}-%"`.

## 4. Titik pemanggil yang wajib disentuh

Semua pemanggil `InvoiceNumberGenerator::nextFor()` (grep `app/Services/InvoiceNumberGenerator.php` dipanggil dari — perlu dicek ulang saat implementasi, minimal):

- `GenerateMonthlyInvoicesCommand` — Bulanan (`TAG`).
- `InitialInvoiceService` — Aktivasi (`ACT`).
- `ManualInvoiceService` / `ManualCategoryInvoiceService` — Manual, prefix ditentukan dari `ManualInvoiceCategory` request (`MTN`/`OTH`/`REL`).

Tiap pemanggil wajib lolos tes: nomor yang dihasilkan konsisten dengan tabel §2 untuk jenis/kategorinya.

## 5. Legacy — sengaja diabaikan

`InvoiceType::INSIDENTAL` ("Tagihan Lain-lain") masih bisa punya baris lama di DB dev, tapi:

- Tidak pernah dibuat lagi lewat jalur baru (komentar di enum: "selalu manual", dan jalur manual sekarang sudah pindah ke `MANUAL`+`ManualInvoiceCategory`).
- Keputusan user: **tidak perlu prefix baru untuk ini** — DB dev masih tahap pengembangan, akan direset diganti data baru. Baris lama (format `INV-...`) dibiarkan apa adanya, tidak di-backfill.

## 6. Keputusan user (2026-10-01)

1. **Migrasi data lama — nomor `INV-...` existing WAJIB diganti juga** ke format baru, bukan dibiarkan dua format berdampingan. Backfill lewat migration: baca `invoice_type` + `manual_category` + tanggal terbit (`created_at`, lihat §6a soal kolom mana) tiap baris, susun ulang `invoice_number` pakai prefix §2, urutan 6-digit per prefix+tanggal (resimulasi urutan sesuai tanggal terbit, bukan sekadar re-run nomor lama). Baris `invoice_number like 'INV-LEGACY-%'` (penanda khusus data migrasi legacy, `FixLegacyBillingBatch2Command.php:36`) **tidak ikut** — itu placeholder, bukan nomor jenis tagihan, biarkan apa adanya.
2. **Tidak perlu kolom baru — jenis tagihan sudah tersimpan struktural, terpisah dari `invoice_number`.** Ditelusuri: `invoices.invoice_type` (migrasi `add_invoice_type_to_invoices_table`) + `invoices.manual_category` (migrasi `add_manual_invoice_columns_to_invoices_table`) sudah ada sebagai kolom sendiri. `invoice_number` cuma string tampilan/pencarian — tidak ada kode yang mem-parsing isinya untuk menebak jenis tagihan; satu-satunya pemakaian `LIKE` di luar `InvoiceNumberGenerator` sendiri adalah search generik `%{$term}%` (`InvoiceController`, `CollectorWorklistController`, `PaymentController`) dan guard duplikat (`add_duplicate_guard_indexes_to_invoices_and_payments`) yang pakai `customer_id`+`billing_period`+`type`, bukan string nomor. Ganti format nomor **aman**, tidak merusak logika lain.
3. **Timezone `Asia/Jakarta`** untuk `YYYYMMDD` — konsisten dengan locale aplikasi (`IndonesianDate`).

### 6a. Yang masih perlu dicek saat implementasi (bukan keputusan baru, cuma detail teknis backfill)

- Tanggal terbit dipakai urutan ulang: pastikan pakai kolom yang benar-benar mewakili "tanggal invoice terbit" (`created_at` vs kolom tanggal invoice eksplisit kalau ada) — cek skema `invoices` sebelum nulis migrasi backfill.
- Backfill harus idempotent (migration re-run aman) dan dibungkus transaksi per-batch supaya tidak macet di tengah pada data besar.
- `invoice_number` dipakai di cetakan/kwitansi (`ReceiptPresenter`) dan webhook (`SendInvoiceUpdatedWebhook`) — pastikan tidak ada pihak eksternal yang sudah menyimpan nomor lama sebagai referensi tetap sebelum migrasi jalan di produksi (untuk dev: tidak masalah).

## 7. Implementasi (2026-10-01)

### 7.1 Yang sudah dikerjakan

- `InvoiceNumberGenerator::nextFor()` diganti total: signature baru `nextFor(InvoiceType $type, ?ManualInvoiceCategory $category, string|DateTimeInterface $issueDate): string`. `resolvePrefix()` privat baru (AWAL→ACT, BULANAN→TAG, MANUAL+kategori→MTN/OTH/REL). Format nomor `{PREFIX}-{YYYYMMDD}-{NNNNNN}`, timezone `Asia/Jakarta` lewat `Carbon::parse($issueDate, 'Asia/Jakarta')`.
- 4 pemanggil disesuaikan ke signature baru: `GenerateMonthlyInvoicesCommand` (BULANAN), `CustomerTerminationService` (MANUAL+LAINNYA, denda putus langganan), `ManualCategoryInvoiceService` (MANUAL+kategori dari request), `ManualInvoiceService` (BULANAN/MANUAL dari `resolveTypeFromLines()`, kategori selalu null — jalur ADHOC-60 lama).
- **Cacat ditemukan & diperbaiki (di luar 4 pemanggil yang disebut rancangan §4):** `InitialInvoiceService::issue()` (Invoice Aktivasi, dipanggil `CustomerVerificationController::finalVerify()` & `BusinessDevelopmentVerificationController::verify()`) **sama sekali tidak memakai `InvoiceNumberGenerator`** — generate nomor sendiri (`'INV-'.now()->format('Ymd').'-'.strtoupper(uniqid())`), tidak terurut, tidak match format mana pun. Diperbaiki: constructor inject `InvoiceNumberGenerator`, panggil `nextFor(InvoiceType::AWAL, null, $issueDateStr)`. Kedua pemanggil sudah membungkusnya dalam `DB::transaction()`/`DB::beginTransaction()`, jadi `lockForUpdate()` di generator bermakna tanpa perlu transaksi baru.
- Test disesuaikan: `tests/Unit/InitialInvoiceProrateFormulaTest.php` (unit test murni, instansiasi manual `new InitialInvoiceService` butuh dependency baru — diberi `new InvoiceNumberGenerator`).
- Diverifikasi: 65 test lintas `TagihanBulananJatuhTempoTanggal10Test`, `GenerateMonthlyInvoicesSkipsWaivedPeriodTest`, `CustomerTerminationPenaltyTest`, `CreqBillingVerificationTest`, `CustomerFinalVerificationTest`, `BusinessDevelopmentVerificationGateTest`, `InstallationFeeDynamicApprovalTest`, `InitialInvoiceProrateFormulaTest` — hijau. `vendor/bin/pint --dirty` bersih.
- §6 poin 2 & 3 (tidak perlu kolom baru, timezone Asia/Jakarta) terpakai langsung di implementasi di atas.

### 7.2 Yang BELUM dikerjakan — §6 poin 1 (backfill nomor lama)

Migrasi backfill nomor `INV-...` existing ke format baru (keputusan user §6 poin 1) **tidak dikerjakan sesi ini** — di luar scope "benerin logic generator + titik pemanggil" yang jadi fokus, dan backfill data (mengganti `invoice_number` ribuan baris existing, dipakai di cetakan/webhook/referensi) berisiko lebih tinggi daripada perubahan kode murni. Perlu konfirmasi eksplisit terpisah sebelum dikerjakan (kolom tanggal mana yang jadi basis resimulasi urutan, strategi batching).

### 7.3 Perubahan bersamaan di `InstallationFeeInvoiceService` — DISELESAIKAN

`app/Services/InstallationFeeInvoiceService.php` berubah di disk selama implementasi — dikonfirmasi user: **perubahan manual user sendiri**, bukan proses lain. Isinya: Biaya Instalasi pelanggan Bisnis tidak lagi lewat `ManualInvoiceService::create()` (jalur lines-based ADHOC-60), langsung `Invoice::create()` dengan `invoice_type=MANUAL` + `manual_category=LAINNYA` eksplisit, dan sudah memanggil `nextFor(InvoiceType::MANUAL, ManualInvoiceCategory::LAINNYA, now())`.

**Keputusan final (menggantikan keputusan ACT sebelumnya di sesi ini):** Biaya Instalasi Bisnis = `ManualInvoiceCategory::LAINNYA` → prefix **OTH**, bukan ACT. Tabel §2 tetap berlaku apa adanya (`MANUAL`+`LAINNYA`→`OTH`); tidak ada perubahan kode di `InvoiceNumberGenerator::resolvePrefix()` — sudah benar menangani kedua cabang (`null`→`ACT`, sekarang tidak ada pemanggil hidup yang pakai cabang ini lagi; `LAINNYA`→`OTH` untuk jalur baru).

## 8. Perluasan ke nomor Pembayaran (`payment_number`) — 2026-10-01

Diskusi lanjutan user, **2 revisi** sebelum bentuk final:

1. **Draf pertama** (dibuang): `PAY-{PREFIX}-{YYYYMMDD}-{NNNNNN}`, segmen kedua prefix jenis tagihan + counter sendiri per (prefix, hari). Masalah yang ditemukan user: tidak menjawab pertanyaan "pembayaran ke berapa / cicilan ke berapa" pada SATU invoice — dua cicilan beda hari dapat nomor yang kelihatan tidak berhubungan sama sekali.
2. **Bentuk final (disetujui user):** `PAY-{invoice_number}-{NN}` — `invoice_number` tagihannya ditempel **APA ADANYA**, `{NN}` = urutan pembayaran ke berapa pada invoice itu (2 digit). Contoh: Tagihan `TAG-20260919-000012` dibayar 2x → `PAY-TAG-20260919-000012-01` (bayar ke-1), `PAY-TAG-20260919-000012-02` (bayar ke-2/cicilan).

**Konsekuensi desain dari bentuk final (dampak baik):**
- **Tidak perlu tabel counter terpisah sama sekali** untuk Payment — `{NN}` dihitung dari `Payment::where('invoice_id', $invoice->id)->count() + 1`, bukan dari sequence global per hari. Draf pertama (tabel `payment_number_sequences` + kolom `type_code`) **dibatalkan total** — migrasinya dihapus sebelum pernah dijalankan di mana pun (aman, cuma ada di working tree sesi ini), model `PaymentNumberSequence` dikembalikan ke bentuk semula (sekarang jadi tabel/model YATIM, dibiarkan apa adanya — sudah tidak dibaca generator mana pun, lihat docblock barunya).
- `Invoice::billingPrefix()` + `InvoiceNumberGenerator::prefixFor()` (public) yang sempat dibuat untuk draf pertama **juga dibatalkan/dibalik** — `resolvePrefix()` kembali privat, tidak ada lagi yang butuh akses publiknya karena Payment tidak lagi memetakan prefix sendiri, cukup menempel `invoice_number` utuh.
- **`{NN}` dihitung dari SEMUA baris payment pada invoice (apa pun statusnya, termasuk ditolak)** — angka tidak pernah dipakai ulang. Ini SENGAJA beda basis dari badge "Cicilan Ke-N" yang sudah ada di UI (`Payment::installmentContext()`, cuma menghitung yang VALID): `payment_number` itu identitas HISTORIS yang beku begitu tercetak, badge UI itu status TERKINI yang boleh bergeser kalau ada payment lama yang ditolak belakangan — dua hal berbeda, didokumentasikan eksplisit di docblock `generatePaymentNumber()` biar tidak disamakan paksa nanti.
- **WAJIB dipanggil setelah invoice-nya dikunci** (`lockForUpdate()`) di transaksi yang sama — `count()` di method ini TIDAK mengunci apa pun sendiri, mengandalkan lock invoice yang sudah dipegang pemanggil (`$lockedInvoice`/`$invoice`, ketiga pemanggil sudah begini sebelum perubahan ini) untuk menyerialkan dua payment bersamaan pada invoice yang sama.
- Nomor tetap AMAN untuk invoice lama yang belum di-backfill (§7.2) — `invoice_number`-nya ditempel apa adanya, jadi bentuk `PAY-INV-202609-0012-01` (invoice lama) dan `PAY-TAG-20260919-000012-01` (invoice baru) SAH berdampingan tanpa perlu migrasi serentak.

**Diimplementasikan:**
- `Payment::generatePaymentNumber(Invoice $invoice): string` — signature baru (dulu `string $paymentDate` lalu sempat jadi `string $paymentDate, string $typeCode` di draf pertama, sekarang ambil `Invoice` utuh).
- 3 pemanggil disesuaikan: `PaymentService::record()`, `CollectorPaymentService` (batch kolektor), `CustomerBalanceService::applyToOpenInvoices()` (auto-pay SALDO) — semua kirim `$lockedInvoice`/`$invoice` langsung.
- Regex gerbang OCR/QR kwitansi (`ReceiptNumberExtractor::normalize()`, `PdfTextNumberReader::read()`) **dilonggarkan** jadi `PAY-[A-Z0-9-]{5,40}` — sejak badan nomor menempelkan `invoice_number` apa adanya, bentuknya ikut bervariasi (invoice baru vs lama yang belum di-backfill), menghardcode satu bentuk pasti kurang lengkap. Gerbang sesungguhnya tetap pencarian ke DB (nomor yang lolos pola tapi tidak ketemu baris `payments`-nya berakhir MISMATCH, bukan tercocokkan asal) — jadi pola permisif di sini aman.
- **Bug ditemukan & diperbaiki sekaligus (laporan user):** `resources/views/payments/show.blade.php` hardcode label "Pelunasan Tagihan Internet" + subtitle nama paket internet, SELALU, tanpa pengecualian jenis tagihan — invoice MTN/OTH/REL (bukan langganan internet) tetap menampilkan label itu. Diperbaiki jadi dinamis: `manual_subtype_name` > `manual_category->label()` > `invoice_type->label()` (urutan sama dengan badge jenis tagihan di `invoices/show.blade.php`), subtitle jadi `invoice->description` > nama paket > fallback "Layanan ISP".
- Test ditulis ulang total: `PaymentNumberSequenceWidthExpansionTest` (nama file dipertahankan dari era lama, isinya sekarang menguji `PAY-{invoice_number}-{NN}`: urutan 1/2/cicilan, urutan tidak dipakai ulang walau ada yang ditolak, dua invoice independen, ikut baris payment lama hasil import). `PaymentInputTest`, `PaymentReceiptTest` disesuaikan literalnya.
- Diverifikasi independen (ad-hoc, dihapus setelah lulus, bukan bagian test suite): FIFO saldo lewat jalur command asli, label pembayaran MTN beneran tampil "Pelunasan Tagihan Perbaikan", dan cicilan 2x lewat form bayar asli (`POST invoices.payments.store` dua kali) menghasilkan `-01` lalu `-02` persis sesuai contoh user.
- Test lintas payment/kwitansi/kolektor/saldo (180+ kasus) hijau, `pint` bersih.
- **Tidak disentuh (di luar scope, bukan jalur produksi aktif/sekadar dokumentasi API):** contoh `payment_number` di docblock `#[Response(...)]` Scramble (`PortalPaymentController`, `PortalBalanceController`) dan komentar contoh di `CollectorVisitService` — cosmetic, tidak memengaruhi perilaku.
- **Ditemukan tapi TIDAK diperbaiki (di luar scope, pre-existing, dikonfirmasi lewat `git stash` tanpa perubahan sesi ini juga gagal):** `PaymentAuditLogTest > owner dan admin pusat dapat melihat...` gagal di `assertSee('Riwayat Audit Pembayaran')` — tab itu digerbangi permission `audit_logs.view`, kemungkinan regresi dari perubahan RBAC di sesi/kerja lain yang berjalan bersamaan di repo yang sama, bukan dari BUG 13.

### 8.1 Dedup rincian "Cicilan Ke-N" di Tagihan (laporan user, 2026-10-01)

`invoices/show.blade.php` (tab "Riwayat Pembayaran") sudah lama punya kolom "Cicilan Ke-N" + "No. Pembayaran" per baris, tapi nomor cicilannya dihitung ULANG manual inline (filter valid → urut tanggal+id → akumulasi → `settles`) — SALINAN KEDUA dari algoritma yang sama persis dengan `Payment::installmentContext()` (dipakai `payments/show.blade.php` & kwitansi). Dua salinan identik yang gampang menyimpang diam-diam begitu salah satu diubah (pola yang sama berulang di repo ini — CLAUDE.md).

Diganti: blok manual dibuang, loop langsung panggil `$payment->installmentContext()` — satu sumber kebenaran, "susunan Cicilan Ke-N" di Tagihan **dijamin sama** dengan di Detail Pembayaran/kwitansi, bukan cuma kebetulan sama karena rumusnya ditulis dua kali identik.

Efek samping yang ketemu & dibetulkan: `installmentContext()` membaca `$this->invoice->total_amount` — relasi balik `Payment→Invoice` tidak otomatis terisi oleh eager-load `Invoice→payments` yang sudah ada di `InvoiceController::show()`, jadi lazy-load (ditolak strict mode app ini). Fix: `$invoice->payments->each(fn ($payment) => $payment->setRelation('invoice', $invoice))` sesudah `$invoice->load([...])` — sekalian mencegah N+1 query per baris pembayaran.

Test: `InstallmentAndOverpayDisplayTest`, `KwitansiIsiSeragamAntarHalamanTest`, `PaymentReceiptPrintTest`, `InvoiceListTest`, `InvoiceCreateTest`, `InvoiceModelTest`, + sweep lanjutan (51 test lain yang menyentuh `InvoiceController::show()`) — hijau.
