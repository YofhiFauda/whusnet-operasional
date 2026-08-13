# Modul Billing & Pembayaran

Tagihan (`Invoice`) dan pembayaran (`Payment`) pelanggan ISP. Tagihan lahir dari 2 jalur: **otomatis** (aktivasi & bulanan rutin) dan **manual** (admin input lewat form). Pembayaran dicatat per-tagihan (partial atau lunas) atau massal (bulk-pay banyak tagihan sekaligus).

## Konsep Inti

| Entity | Peran |
|--------|-------|
| `Invoice` | 1 row = 1 tagihan periode tertentu. Tipe: `awal` (PSB/aktivasi), `bulanan` (rutin), `reaktivasi`. |
| `Payment` | 1 row = 1 transaksi pembayaran terhadap 1 Invoice. Invoice bisa punya banyak Payment (cicilan/partial) — urutannya dihitung `Payment::installmentContext()` ("Cicilan Ke-N"). |
| `PaymentBatch` | 1 row = 1 sesi submit batch kolektor (idempotency + pengelompokan). BUKAN rekonsiliasi kas — fitur Setoran Kolektor di-drop dari scope. |

**Invoice status** (derived dari akumulasi Payment VALID, dihitung `Invoice::recalculateFromPayments()`): `belum_dibayar` → `sebagian` → `lunas`, atau `batal` (dibatalkan, gak bisa terima pembayaran lagi).

**Payment status**: `valid` (default saat dicatat, semua jalur insert langsung valid — TAK ADA alur verifikasi bertahap), `ditolak` (via `POST /payments/{id}/reject`, wajib alasan). **`pending` sudah dihapus dari enum (2026-08-03)** — kalau menemukan referensi ke `pending` di kode lama, itu bug/sisa, bukan status yang valid.

**Notifikasi in-app (2026-08-06/07)** — `PaymentController::reject()` notif ke pencatat pembayaran (`collected_by` kalau ada / fallback `received_by`), skip kalau yang reject = pencatat sendiri. `CollectorBatchController::store()` sukses notif role `pop_admin` di POP invoice yang kena (pengganti "Finance Pusat" — role itu gak ada di RBAC sistem ini, `pop_admin` dipilih karena pegang `payments.validate`/`reject` per POP). **Pesannya sengaja murni informatif** ("dicatat"), bukan "perlu direkonsiliasi" — selaras sama keputusan produk di atas (`PaymentBatch` BUKAN rekonsiliasi kas, fitur Setoran Kolektor formal di-drop dari scope). Detail: `docs/plan/analisa-status-implementasi-notifikasi.md` §8.3.

**Lebih bayar** (`payments.overpay_amount`, 2026-08-04): admin ketik SATU nominal total diterima, sistem otomatis pisah bagian yang menutup tagihan (`amount`, tetap tak pernah melebihi sisa tagihan) dari kelebihannya (`overpay_amount`). **Bukan saldo kredit** — tak punya sisi debit, tak pernah dipakai otomatis untuk tagihan berikutnya. Tab khusus read-only di `/payments/overpay`.

## Dokumen

| Dokumen | Isi |
|---------|-----|
| [flowchart.md](flowchart.md) | Alur pembuatan invoice (auto & manual), alur pembayaran, status transition |
| [user-flow.md](user-flow.md) | Langkah admin di `/invoices`, `/payments`, bulk-pay, laporan |
| [database-schema.md](database-schema.md) | Tabel, kolom, relasi, index dedup-guard |
| [perbandingan-tagihan-awal-vs-bulanan-legacy.md](perbandingan-tagihan-awal-vs-bulanan-legacy.md) | Cara membedakan tagihan awal vs bulanan: sistem lama vs sekarang, rumus prorata, celah yang tidak diwarisi |
| [analisa-ux-form-verifikasi-aktivasi.md](analisa-ux-form-verifikasi-aktivasi.md) | Form tagihan pertama jadi kwitansi (5 input, sisanya turunan server) |
| [analisa-pencegahan-tagihan-dobel.md](analisa-pencegahan-tagihan-dobel.md) | Lima lapis pencegahan tagihan dobel, mana yang bolong, urutan perbaikan (BILLING-B0b–B0e) |
| [analisa-duplikasi-tagihan-pembayaran-migrasi-legacy.md](analisa-duplikasi-tagihan-pembayaran-migrasi-legacy.md) | Enam cacat migrasi legacy yang bikin tagihan & pembayaran dobel (kasus Ardiyanto, Wiyono) |
| [archive/](archive/) | Analisa & rencana historis (sebagian sudah diimplementasi, sebagian belum) |
| [`../plan/analisa-billing-tagihan-pembayaran-kolektor.md`](../plan/analisa-billing-tagihan-pembayaran-kolektor.md) | Dokumen rancangan sumber untuk kolektor, batch bayar, reject payment, lebih bayar, cicilan — histori keputusan lengkap (termasuk yang di-drop: Setoran Kolektor & Saldo Kredit) |

## Routes & Permission

| Route | Method | Permission | Controller |
|-------|--------|------------|------------|
| `/invoices` | GET | `invoices.view` | `InvoiceController@index` |
| `/invoices/lunas` | GET | `invoices.view` | `InvoiceController@lunas` |
| `/invoices/belum-lunas` | GET | `invoices.view` | `InvoiceController@belumLunas` |
| `/invoices/{invoice}` | GET | `invoices.view` | `InvoiceController@show` |
| `/customers/{customer}/invoices/manual` | POST | `invoices.create` | `CustomerController@storeManualInvoice` |
| `/payments` | GET | `payments.view` | `PaymentController@index` |
| `/payments/overpay` | GET | `payments.view` | `PaymentController@overpay` |
| `/payments/{payment}` | GET | `payments.view` | `PaymentController@show` |
| `/payments/{payment}/kwitansi` | GET | `payments.view` | `PaymentController@receipt` |
| `/payments/{payment}/reject` | POST | `payments.reject` | `PaymentController@reject` |
| `/invoices/{invoice}/payments/create` | GET | `payments.create` | `PaymentController@create` |
| `/invoices/{invoice}/payments` | POST | `payments.create` | `PaymentController@store` |
| `/collector-worksheet` | GET | `collector_worksheet.view` | `CollectorWorksheetController@index` |
| `/collector-worksheet/{collector}` | GET | `collector_worksheet.view` | `CollectorWorksheetController@show` |
| `/collector-worksheet/{collector}/assign` | POST | `collector_worksheet.assign` | `CollectorWorksheetController@assign` |
| `/collector-worksheet/{collector}/customers/{customer}/release` | POST | `collector_worksheet.assign` | `CollectorWorksheetController@release` |
| `/payment-batches/{collector}` | POST | `payments.create` | `PaymentBatchController@store` |

> **`/invoices/bulk-pay` (`PaymentController@bulkStore`) DIHAPUS 2026-08-11.** Tak pernah punya UI maupun test, dan jaminannya menyimpang dari jalur batch kolektor: transaksi **per invoice** (bukan all-or-nothing), tanpa idempotency, nominal dipaksa lunas penuh, dan `catch (\Throwable)` menelan semua error jadi angka "gagal" tanpa alasan maupun log. Aksi massal yang benar-benar ada: tab Pembayaran di Worksheet Kolektor (`payment-batches.store`) lewat `CollectorPaymentService`.
| `/customers/{customer}/payment-info` | GET | (login) | `CustomerController@paymentInfo` |
| `/reports/invoices`, `/reports/invoices/export` | GET | (report perm) | `InvoiceReportController` |
| `/reports/payments`, `/reports/payments/export`, `/reports/payments/export-xlsx` | GET | (report perm) | `PaymentReportController` |

**POP scope:** semua query pakai `applyUserScope()` (trait `HasPopScope`) — admin non-owner cuma lihat invoice/payment di POP yang di-assign ke dia.

## Console Command

- `billing:generate-monthly-invoices [--period=YYYY-MM] [--dry-run]` — generate tagihan `bulanan` flat untuk semua pelanggan aktif/suspended yang belum punya tagihan langganan di periode itu. Skip pelanggan yang baru aktivasi di bulan yang sama (sudah kena tagihan `awal`). File: `app/Console/Commands/GenerateMonthlyInvoicesCommand.php`.

  **`--period` ditambahkan 2026-08-10.** Tanpa opsi ini command dipatok `now()`, sehingga bulan yang sudah lewat tidak akan pernah tertagih — dan itu terjadi: container `scheduler` mati, jadwal `monthlyOn(1)` terlewat pada 1 Juli & 1 Agustus, dan menghidupkan scheduler saja **tidak** menambalnya (tanggal 1 yang terlewat tidak diulang). Formatnya divalidasi ketat (`2026-7` ditolak) supaya `billing_period` tak pernah menyimpang dari format `Y-m` yang dipakai seluruh sistem. Aman diulang: `Invoice::hasActiveSubscriptionInvoiceForPeriod()` yang sama tetap menahan dobel.

  **Jatuh tempo = tanggal 10 periodenya**, bukan "tanggal cron jalan + 10 hari". Jalur import legacy dulu memakai `addDays(10)` sehingga seluruh tagihan hasil migrasi jatuh tempo tanggal **11** — satu aturan bisnis, dua hasil, tergantung tagihannya lahir dari mana. Disamakan 2026-08-10 (`MigrateLegacyDataCommand::legacyDueDate()`); invoice `awal` yang terbit lewat tanggal 10 digeser ke tanggal 10 bulan berikutnya supaya tempo tidak mendahului terbit.

## Guard Anti-Duplikat

**`payments_invoice_date_amount_unique` DI-DROP 2026-08-03** — dulu satu-satunya guard dobel-submit di DB, tapi ikut menolak cicilan sah (nominal sama, invoice sama, tanggal sama). Sekarang tiga lapis proteksi:

1. **`InvoiceObserver::creating()`** — tolak insert Invoice kalau ada yang identik (customer+type+billing_period+total_amount) dalam 5 menit terakhir.
2. **`PaymentObserver::rejectBurstDuplicate()`** — tolak insert Payment identik (customer+invoice+amount+date) dalam 300 detik, jalur single-payment.
3. **`payment_batches.idempotency_key`** — dedup per sesi submit batch kolektor.
4. **`payments.idempotency_key`** (2026-08-11) — dedup jalur Tagihan. Kuncinya digenerate saat form `payments/create` dirender dan ikut `old()` supaya tetap sama setelah validasi gagal. Dicek **sebelum** validasi, sama seperti jalur kolektor: pada submit ulang tagihannya sudah lunas dari submit pertama, jadi kalau dicek belakangan pengguna menerima error untuk pembayaran yang justru berhasil. Balapan dua request kembar ditahan unique index dan dijawab "sudah tercatat", bukan 500.

**Kenapa lapis ke-4 perlu padahal sudah ada lapis ke-2:** `rejectBurstDuplicate()` menahan duplikatnya, tapi lewat `InvalidArgumentException` yang berakhir **500**. Itu kondisi yang diantisipasi, bukan kerusakan — `PaymentController::store()` sekarang menerjemahkannya jadi pesan validasi biasa. Penolakan observer lain (nominal ≤ 0) tetap dilempar apa adanya.

**Batas tanggal bayar (2026-08-11):** `payment_date` di jalur Tagihan kini `before_or_equal:today`, sejajar dengan `collected_date` di jalur kolektor. Sebelumnya admin bisa memasukkan pembayaran bertanggal tahun depan — merusak pemotongan pendapatan per periode dan membuat laporan bulan berjalan memuat uang yang belum ada.

Invoice gak punya unique index setara (data lama sebelum fix migrasi masih ada pelanggaran, dan invoice `batal` menempati slot periode — lihat catatan di `database-schema.md`) — guard invoice level DB baru ditegakkan di `CustomerController::storeManualInvoice` (app-layer check), belum hard constraint.

## Format Nominal — `150.000`, bukan `150000`

Semua kolom uang yang diketik manusia memakai masking ribuan: ketik `150000`, layar menampilkan `150.000`.

| Kolom | Halaman | Endpoint |
|---|---|---|
| `amount` | `payments/create` (jalur Tagihan) | `PaymentController@store` |
| `amount` | modal Pembayaran Cepat di `/customers` | `PaymentController@store` |
| `qp-amount` | modal Bayar Cepat di `/invoices` & detail pelanggan | `PaymentController@store` |
| `rows.*.amount` | tabel batch kolektor (Worksheet Admin & Worklist) | `PaymentBatchController` / `CollectorPaymentController` |
| `declared_amount`, `settlement_amount` | verifikasi setoran | `CollectorDepositController@verify` |
| `monthly_price`, `installation_fee` | master paket internet | `Master\InternetPackageController` |
| `discount_amount`, `other_fee` | registrasi & edit pelanggan | `CustomerRegistrationRequest`, `CustomerController@update` |
| `prorate_amount`, `extra_*_fee` | modal tagihan manual | `CustomerController@storeManualInvoice` |
| `extra_*_fee`, `other_fee`, `prorate_amount_override` | verifikasi admin (tagihan awal) | `CustomerVerificationController` |

> **Persen BUKAN rupiah.** `tax_percent`, `ppn`, dan `discount_default` sengaja **tidak** dimasking dan tidak dinormalkan — nilainya 0–100 dan tidak pernah pakai pemisah ribuan. Ikut memasking keduanya membuat `11` berisiko terbaca `11.000`.

**Normalisasi ada di dua lapis, dan lapis servernya yang wajib.** `App\Support\RupiahInput::parse()` dipanggil sebelum `validate()` di tiap endpoint di atas. Tanpa itu `150.000` **lolos** aturan `numeric` sebagai **seratus lima puluh rupiah** — titik dibaca desimal Inggris. Tidak ada error, tidak ada peringatan: pembayaran tersimpan 1.000 kali lebih kecil dan invoice tetap "belum lunas" padahal uangnya sudah diterima. Nominal uang tidak boleh bergantung pada JavaScript yang jalan.

Aturannya konservatif — yang tidak dikenali **tidak ditebak**, biar ditolak validator daripada diam-diam berubah nilainya:

| Ketikan | Hasil | |
|---|---|---|
| `150.000`, `1.500.000`, `Rp 150.000` | `150000`, `1500000` | grup 3 digit = ribuan |
| `150.000,50`, `150000,50` | `150000.50` | koma = desimal |
| `150000`, `150000.50` | apa adanya | format mesin |
| `1.50`, `12.34.56`, `seratus ribu` | apa adanya → **ditolak validator** | tidak dikenali |

Sisi layar: atribut `data-rupiah` pada input, ditangani skrip di `layouts/app.blade.php` (tanpa build step, sejalan dengan Alpine & banner realtime). Input **wajib `type="text"`** — `type="number"` menolak titik dan mengosongkan sendiri isinya.

**Nilai bawaan wajib lewat `FormatHelper::rupiahInput()`**, bukan `number_format($v, 0, ...)`. Sen dipertahankan kalau ada (`150.000,50`) dan dibuang kalau nol (`150.000`). Membulatkan prefill terlihat rapi tapi **mengubah nominalnya**: sisa tagihan 150.000,50 tampil 150.001, lalu ditolak validasi "melebihi sisa tagihan" — baris yang tidak disentuh siapa pun jadi tak bisa dibayar. Di form setoran akibatnya lebih jauh: uang fisik yang benar terkirim dibulatkan, setoran ditandai SELISIH, dan kolektor ditagih uang yang sudah dia serahkan. Berlaku juga untuk pengisian lewat AJAX (`window.Rupiah.formatDariServer`, bukan `Math.round`).

> **Nilai dari server ambigu, ketikan tidak.** Saat mengetik, titik selalu berarti ribuan — `format()` membuangnya. Tapi `old()` sesudah validasi gagal berisi bentuk **mesin** hasil `RupiahInput`, di mana titik justru desimal (`150000.50`). Melewatkannya ke `format()` menghasilkan `15.000.050` — **seratus kali lipat**, dan langsung terkirim ulang saat pengguna menekan simpan lagi. Karena itu ada `formatDariServer()` yang mengenali titik desimal lebih dulu, dengan aturan yang sama persis seperti `RupiahInput` di server.

> **Urutan skrip penting.** Blok masking di `layouts/app.blade.php` ditaruh **sebelum** `@yield('scripts')`. Kalau sesudah, kode halaman yang berjalan langsung saat parse (mis. `refreshHint()` awal di `payments/create`) melihat `window.Rupiah` masih `undefined` dan jatuh ke `parseFloat('150.000')` = 150 — pratinjau salah sampai pengguna mengetik.

Karena `type="number"` ditinggalkan, batas `min`/`max` bawaan browser ikut hilang dan harus diganti manual:

| Tempat | Pengganti |
|---|---|
| tabel batch kolektor | `data-max` + `cbBarisValid()` di `collector-pay-script` |
| modal Bayar Cepat | `qpNominal() < 1` sebelum submit |

> Modal Bayar Cepat mengirim lewat **FormData**, bukan event `submit`, jadi normalisasi global tidak ikut jalan di sana — nilainya dibersihkan eksplisit lewat `qpNominalPolos()`. Pola yang sama berlaku untuk form AJAX lain: kalau tidak melewati `submit`, bersihkan sendiri.

Test: `RupiahInputTest` (unit, 16), `RupiahInputPrefillTest` (unit, 8 — bolak-balik DB → layar → DB tanpa berubah nilai), `NominalRupiahBertitikDiterimaTest` (feature, 8 — mencakup seluruh jalur di tabel atas, plus bukti bahwa persen tidak ikut dinormalkan dan ketikan tak dikenali tetap ditolak).

## Aritmetika Uang — sen bulat, bukan float

Kolom DB sudah `decimal(12,2)` (eksak). Begitu nilainya masuk PHP ia jadi **float biner**, dan pecahan desimal tidak punya representasi persis di sana. Terukur di repo ini:

```
1.000 baris × 33.333,33  →  33.333.329,9999991469   (meleset −0,00000085)
0.1 + 0.2 === 0.3        →  FALSE
```

Selisih sekecil itu tak pernah terlihat di layar — semuanya dibulatkan 2 desimal. Masalahnya bukan tampilan, tapi **cabang keputusan**:

| Perbandingan | Kalau meleset |
|---|---|
| `remaining <= 0` | tagihan lunas tetap berstatus **Sebagian** |
| `difference == 0` | setoran pas ditandai **SELISIH** → kolektor ditagih uang yang sudah dia serahkan |
| `overpay > 0` | "bayar pas" melahirkan lebih bayar Rp0,000001 — baris hantu yang tak bisa diselesaikan siapa pun |

**`App\Support\Money`** mengerjakan semua operasi di **sen bulat** (int) lalu mengembalikan rupiah float, jadi pemanggil lama tak berubah bentuknya. Dipakai di titik-titik yang cabangnya ditentukan angka uang:

| Berkas | Yang dijaga |
|---|---|
| `Invoice::recalculateFromPayments()` | `paid`/`remaining` + status Lunas/Sebagian |
| `CollectorDeposit::computedAmount()`, `outstandingShortfall()` | nilai setoran & sisa kewajiban |
| `CollectorDepositService::verify()` | `difference` + status Terverifikasi/Selisih/Lebih Setor |
| `CollectorBalanceService` | saldo & total kurang setor |
| `CollectorPaymentService` | nominal baris batch vs sisa tagihan, total notifikasi |
| `PaymentController@store` | pemisahan bayar-vs-lebih-bayar |
| `InitialInvoiceService` | subtotal, PPN, total tagihan pertama |

> **Epsilon karangan dihapus.** Sebelumnya `abs($x) <= 0.001` tersebar di beberapa tempat, tiap tempat bebas memilih angkanya sendiri (`+ 0.001` di satu baris, `<= 0.001` di baris lain). Sekarang perbandingannya eksak lewat `Money::isZero()` / `Money::greaterThan()`.

**Persen tetap float biasa** — `ppn`, `discount_default`, `tax_percent` bukan nominal uang; yang lewat `Money` cuma hasil perkaliannya.

Batas: sen disimpan int 64-bit, aman sampai ±92 triliun rupiah — jauh di atas `decimal(12,2)` (maks 9.999.999.999,99).

Test: `MoneyTest` (unit, 8 — termasuk bukti galat float yang dicegahnya).

## Cetak Kwitansi — tiga bentuk, satu isi

Satu pembayaran bisa dicetak dari tiga tempat. Bentuknya beda, **isinya wajib sama**.

| Dari | Route → view | Bentuk |
|---|---|---|
| List Pembayaran / List Tagihan / Hub Pelanggan | `payments.receipt` → `payments/receipt.blade.php` | struk thermal 80mm, monospace |
| Detail Pembayaran (Ctrl+P) | `payments.show` → blok `.print-only` | lembar A4 |
| Worksheet Kolektor | `payment-receipts.print` → `collector-worksheet/receipt-print.blade.php` | kartu 2 kolom + QR |

**`App\Services\Receipts\ReceiptPresenter` adalah satu-satunya sumber isinya** (2026-08-12). Sebelum itu tiap view membaca `$payment` sendiri-sendiri, dan satu pembayaran yang sama tercetak berbeda tergantung tombol mana yang ditekan: alamat, no. HP, dan kolektor cuma ada di A4; periode & paket cuma ada di thermal. Untuk dokumen yang diserahkan ke pelanggan dan dibuka lagi saat audit, itu bukan perbedaan selera tata letak.

→ **Field baru ditambahkan di presenter, bukan di salah satu view.** Menambahkannya langsung di blade membuat ketiganya menyimpang lagi.

Dua cacat yang ikut ditutup saat penyatuan:

1. Lembar A4 mencetak status dengan warna emerald **tanpa syarat** — pembayaran `ditolak` tampil hijau berbullet di kwitansi yang berlabel "resmi". Sekarang warnanya ikut `status_valid`.
2. Saat `note` kosong, A4 **mengarang** kalimat `"Tagihan Bulanan. Struk ini adalah bukti pembayaran sah…"` yang terbaca seperti catatan petugas. Sekarang catatan kosong tetap kosong ("Tanpa catatan."), dan kalimat legal dipisah sebagai teks penerbit. Judul baris rincian juga ikut keterangan cicilan — dulu selalu "Pelunasan Invoice" walau pembayarannya cicilan sebagian.

**Alamat dipenggal dua baris** (2026-08-12) — `Jl. Veteran … RT. 002/RW. 002, Joresan` / `Kec. Mlarak, Kabupaten Ponorogo`. Penggalannya cuma di penanda `Kec.`/`Kecamatan`; alamat tanpa penanda dibiarkan utuh satu baris, karena membelah di koma sembarang bisa memisahkan nama jalan dari nomornya. Ini **penyajian**, bukan perubahan data: `customers.address` tetap satu kolom teks bebas — memecahnya jadi kolom desa/kecamatan/kota berarti menebak struktur ~1.900 alamat legacy yang formatnya tidak seragam, dan salah tebak pada dokumen alamat lebih mahal daripada satu baris yang kepanjangan.

**Header/footer bawaan browser** (tanggal, judul dokumen, URL `localhost:8000/...`) dimatikan di keempat halaman cetak lewat `@page { margin: 0; }`. Teks itu dicetak di kotak margin halaman — di luar jangkauan selector mana pun, jadi satu-satunya cara adalah menolkan marginnya. Jarak ke tepi kertas dipindah ke padding elemen, bukan dihapus; menghapusnya membuat cetakan mepet tepi dan terpotong printer. Kalau pengguna mencentang sendiri opsi *Headers and footers* di dialog cetak Chrome, centangnya menang atas CSS.

Test: `KwitansiIsiSeragamAntarHalamanTest`, `PaymentReceiptPrintTest`, `PaymentReceiptTest`.

## Teknologi

| Komponen | Stack |
|----------|-------|
| Backend | Laravel 13, PHP 8.3 |
| Frontend | Blade, vanilla JS (bulk-pay bar `/customers`, batch bar `/collectors/{id}`), modal AJAX (`quick-payment-modal`) |
| Dialog/Alert | Komponen global `window.Dialog`/`window.Toast` (`resources/views/components/dialog.blade.php`, `toast.blade.php`) — bukan `alert()`/`confirm()` native, bukan modal ad-hoc per halaman |
| Database | MySQL — `invoices`, `payments`, `payment_batches`, `payment_number_sequences` |
| File | `FileUploadService::uploadPaymentProof()` — bukti transfer/foto |

---

## Pola Redirect (PRG)

Catat pembayaran (`store`) → redirect ke `invoices.show` (Detail invoice induk, aturan "aksi child →
Detail parent"). Aturan lengkap + kenapa: **[`docs/PRG_REDIRECT_CONVENTION.md`](../PRG_REDIRECT_CONVENTION.md)**.

---

**Last updated:** 2026-08-04 — sinkronisasi penuh setelah drift ~1 bulan (Fase 1: payment_batches/idempotency/burst-dedup; Fase 2: collector_id/collected_by; reject payment; `PaymentStatus::PENDING` dihapus; lebih bayar auto-split; tampilan cicilan; migrasi Dialog/Toast global). Rujukan lengkap keputusan desain: [`docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md`](../plan/analisa-billing-tagihan-pembayaran-kolektor.md).
