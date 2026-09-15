# ADHOC-60 — Kategori Pendapatan pada Tagihan

Rancangan per 2026-09-09. Hasil diskusi revisi requirement dari user (rewind ADHOC-58 —
percobaan pertama dibatalkan karena salah tangkap kebutuhan). Dokumen ini adalah keputusan
yang **sudah dikunci**, bukan opsi terbuka.

## Masalah

"Jenis pendapatan" sekarang hidup sebagai **kolom** di `invoices`: `prorate_amount`,
`extra_cable_fee`, `extra_installation_fee`, `extra_pole_fee`, `other_fee`. Dua akibatnya:

1. **Jenis jasa baru butuh migrasi + deploy.** "Ganti ONT", "Pindah Tiang" tidak bisa ditagih
   tanpa developer menambah kolom.
2. **Laporan tidak punya dimensi jenis pendapatan.** `InvoiceReportController` menjumlah
   `total_amount`; `PaymentReportController` menjumlah `amount`. Tidak ada satu pun cara
   menjawab "bulan ini dapat berapa dari Tambah Kabel".

Sekaligus: tombol **Buat Tagihan Manual** yang ada sekarang (modal di
`customers/show.blade.php`) hanya bisa menagih kombinasi kolom tetap di atas.

## Keputusan yang dikunci

| # | Keputusan | Alasan |
|---|---|---|
| 1 | Master **dua lapis**: kategori (4, terkunci) + sub kategori (bebas ditambah admin) | Tiap `code` kategori punya perilaku khusus di kode. Kategori kelima buatan admin tidak akan punya perilaku apa pun — cuma *kelihatan* punya |
| 2 | Rincian disimpan di **`invoice_items`**, bukan kolom baru | Jumlah barisnya variabel; kolom tetap adalah persis masalah yang sedang dibereskan |
| 3 | Invariant `SUM(items.amount) == invoices.subtotal` | Lihat §"Invariant" — ini yang bikin laporan bisa direkonsiliasi & ditest |
| 4 | Kategori melekat ke **tagihan**, bukan ke alokasi pembayaran | Kategori sudah pasti sebelum ada pembayaran. Kalau melekat ke pembayaran, tagihan yang belum dibayar tidak punya kategori — dan justru itu yang paling ingin dilihat |
| 5 | **Tidak** ada tabel alokasi pembayaran→item | Porsi per kategori deterministik dari data yang sudah ada; tabel itu cuma menyimpan hasil hitungan, menambah sumber kebenaran kedua tanpa menambah fakta |
| 6 | Dimensi kategori dipasang di **dua** laporan existing | Beda pertanyaan: Pembayaran = uang masuk; Tagihan = yang ditagihkan & piutang. Tidak ada halaman laporan ketiga |
| 7 | Buat Tagihan Manual = **halaman**, bukan modal | Aturan CLAUDE.md 2026-09-07 |
| 8 | Feature sendiri `revenue_categories.*` | Mengubah master berdampak ke SELURUH tagihan yang akan terbit; `invoices.create` cuma menerbitkan satu tagihan |
| 9 | Backfill tagihan lama **dibuat** | Supaya laporan tidak bolong di periode sebelum fitur live |

## Skema

### `revenue_categories` — 4 baris, terkunci

| code | name |
|---|---|
| `jasa_layanan_internet` | Jasa Layanan Internet |
| `jasa_instalasi` | Jasa Instalasi |
| `jasa_perbaikan` | Jasa Perbaikan |
| `lainnya` | Lainnya |

```
id, code (unique), name, description, is_system, is_active, sort_order, timestamps
index (is_active, sort_order)
```

Keempatnya `is_system = true` dan **ditanam di migrasi, bukan seeder** — migrasi berikutnya
mem-FK ke baris ini lewat pencocokan `code`, sedangkan seeder baru jalan setelah SELURUH
migrasi selesai. Daftarnya ditulis literal, tidak membaca konstanta model: migrasi yang
bergantung pada kode aplikasi berubah artinya kalau konstantanya nanti disunting, padahal
migrasi lama harus tetap menghasilkan bentuk DB yang sama.

Dua `code` yang jadi kontrak di kode:
- `jasa_layanan_internet` — menandai baris langganan (nominal dari `customer_services`, dan
  keberadaannya menentukan tagihan kena guard satu-langganan-per-periode)
- `lainnya` — menandai baris bernama ketikan bebas

Controller **tidak menyediakan aksi tambah/hapus kategori**, dan `updateCategory()` membuang
field `code` dari input.

### `revenue_subcategories` — lapis yang bebas ditambah admin

```
id, revenue_category_id (FK restrictOnDelete), code (unique), name,
default_amount (nullable), is_system, is_active, sort_order, timestamps
index (revenue_category_id, is_active, sort_order)
```

Isi awal:

| kategori | code | name | is_system |
|---|---|---|---|
| jasa_layanan_internet | `langganan_bulanan` | Langganan Bulanan | ✅ |
| jasa_layanan_internet | `prorata` | Prorata | ✅ |
| jasa_instalasi | `biaya_aktivasi` | Biaya Aktivasi / Registrasi | ✅ |
| jasa_perbaikan | `tambah_kabel` | Tambah Kabel | ❌ |
| jasa_perbaikan | `pindah_lokasi` | Pindah Lokasi | ❌ |
| jasa_perbaikan | `tambah_tiang` | Tambah Tiang | ❌ |

`is_system` di sini berarti **"dirujuk kode"**, bukan sekadar bawaan — `langganan_bulanan`,
`prorata`, `biaya_aktivasi` dirakit otomatis oleh `InitialInvoiceService` /
`GenerateMonthlyInvoicesCommand` / backfill, jadi code-nya kontrak dan tidak boleh dinamai
ulang oleh admin. Tiga sisanya cuma isi awal — admin berhak rename/nonaktifkan.

Kategori `lainnya` **sengaja tanpa sub kategori**: seluruh gunanya menampung nama ketikan
bebas. Kalau dikasih sub bawaan, admin akan memilih sub itu dan kolom ketikan bebasnya tidak
pernah kepakai.

`default_amount` opsional — tarif standar yang mengisi otomatis kolom nominal di form, tetap
bisa ditimpa. "Tambah Kabel" 20 meter dan 200 meter jelas beda harga.

**Master tidak pernah dihapus, hanya dinonaktifkan.** `restrictOnDelete` menegakkannya di
lapis DB; controller memang tidak punya aksi hapus, constraint itu jaring untuk jalur lain
(tinker, SQL langsung). Sub kategori yang dihapus akan membuat tagihan lama kehilangan
artinya.

### `invoice_items`

```
id, invoice_id (FK cascadeOnDelete), revenue_category_id (FK restrictOnDelete),
revenue_subcategory_id (FK restrictOnDelete, NULLABLE — khusus kategori `lainnya`),
category_name_snapshot, subcategory_name_snapshot, description (nullable),
amount, sort_order, timestamps
index (invoice_id, sort_order), index (revenue_category_id)
```

Dua kolom snapshot nama disimpan berdampingan dengan FK-nya — alasan sama dengan
`customer_services.package_name_snapshot`: master boleh dinamai ulang atau dinonaktifkan, dan
tagihan yang sudah terbit harus tetap terbaca persis seperti waktu diterbitkan. Laporan
agregat tetap group by `revenue_category_id` yang stabil; snapshot hanya untuk tampilan.

**Kolom biaya lama di `invoices` tidak dihapus.** Data lama masih menyimpannya dan
`invoices/show.blade.php` (baris 106-124 & 408-425) jatuh balik ke kolom itu untuk tagihan
yang belum punya baris. Penghapusan kolom adalah task terpisah setelah backfill terbukti
di produksi.

## Invariant

```
SUM(invoice_items.amount) == invoices.subtotal
total_amount = subtotal - discount + (subtotal - discount) * ppn/100
```

Baris item adalah komponen **subtotal** (DPP — sebelum diskon & PPN). Diskon dan PPN tetap di
level tagihan, **tidak** dipecah per baris.

Kenapa ini yang dipilih: percobaan pertama (ADHOC-58) menempelkan PPN/diskon hanya ke bagian
langganan, akibatnya `SUM(items) ≠ total_amount` — angka laporan per kategori tidak bisa
direkonsiliasi dengan total tagihan, dan tidak ada satu pun assert yang bisa menjaganya.
Dengan invariant di atas ada satu baris test yang menjaga ketiga jalur penerbit sekaligus,
dan basisnya benar secara akuntansi: **PPN bukan pendapatan, itu titipan pajak.**

Nominal tetap dihitung ulang penuh di server; kiriman klien statusnya cuma preview —
pola yang sudah ditegakkan `InitialInvoiceService`. Semua aritmetika lewat `App\Support\Money`
(ranah sen), tidak ada penjumlahan float berantai.

## Satu perakit baris, tiga jalur penerbit

`App\Services\InvoiceItemBuilder` — dipakai bertiga supaya rumusnya tidak bercabang:

| Jalur | Baris yang dirakit |
|---|---|
| `InitialInvoiceService` (AWAL) | Prorata + Biaya Aktivasi + Tambah Kabel + Tambah Tiang + Lainnya (materai) |
| `GenerateMonthlyInvoicesCommand` (BULANAN) | Langganan Bulanan — 1 baris |
| `ManualInvoiceService` (baru) | Bebas, ≥ 1 baris |

`InvoiceNumberGenerator` diekstrak dari dua salinan identik yang ada sekarang
(`GenerateMonthlyInvoicesCommand:121-135` dan `CustomerController::storeManualInvoice`).
Keduanya menulis ke deret yang **sama** — begitu formatnya menyimpang, nomor tagihan langsung
bentrok. Masalah persis sama sudah pernah didokumentasikan untuk `TFOP-` di CLAUDE.md.
**Wajib dipanggil di dalam transaksi** — `lockForUpdate()` yang menahan dua request bersamaan
mengambil urutan yang sama baru lepas saat transaksi selesai; dipanggil di luar transaksi,
guard-nya jadi hiasan.

## `InvoiceType::INSIDENTAL`

Case baru untuk tagihan di luar langganan — jasa perbaikan, denda, biaya instalasi tambahan.

**Sengaja tidak masuk `Invoice::SUBSCRIPTION_TYPES`**: itulah yang membebaskannya dari guard
"satu tagihan langganan per periode" di `InvoiceObserver::rejectSecondSubscriptionInvoice()`,
sehingga beberapa pekerjaan berbayar boleh ditagih di bulan yang sama. Konsekuensinya jenis
ini tidak pernah dibuat `billing:generate-monthly-invoices` — selalu manual.

Aturan turunannya di `ManualInvoiceService`: tagihan yang punya baris berkategori
`jasa_layanan_internet` **wajib** bertipe `awal|bulanan|reaktivasi`; yang tidak punya baris itu
**wajib** `insidental`. Ditegakkan di service, bukan cuma di form.

### Revisi 2026-09-09 — Jenis Tagihan di-AUTO-DETECT, bukan dropdown terpisah

Rancangan awal (di bawah, "Header") memberi admin **dua** keputusan: pilih dropdown *Jenis
Tagihan*, lalu isi baris rincian — dua sumber kebenaran yang bisa saling bertentangan (admin
pilih "Bulanan" tapi barisnya cuma Tambah Kabel). `ManualInvoiceService` memang sudah menolak
kombinasi yang tidak konsisten (`assertTypeMatchesLines()`), tapi itu penjaga di server —
di UI admin baru tahu setelah submit gagal.

**Diputuskan:** dropdown *Jenis Tagihan* dihapus dari form. Jenisnya diturunkan OTOMATIS dari
isi baris rincian:

```
ada baris kategori jasa_layanan_internet?
  ya  → pelanggan sedang suspended? → REAKTIVASI
        selain itu                 → BULANAN
  tidak → INSIDENTAL
```

Ditegakkan dua lapis, keduanya WAJIB tetap ada — satu untuk UX, satu untuk keamanan:

1. **`ManualInvoiceService::resolveTypeFromLines()`** — dipanggil di server saat
   `invoice_type` kosong. ini yang benar-benar menentukan nilai yang tersimpan.
2. **Alpine di `invoices/create.blade.php`** — field `invoice_type` jadi `<input type="hidden">`
   yang nilainya dihitung ulang tiap baris rincian berubah (event `invoice-subtotal-changed`
   dari `<x-invoice-line-rows>`), ditampilkan sebagai badge read-only "Klasifikasi Tagihan
   (Otomatis)". Ini MURNI pratinjau (JS bisa dimatikan/dimanipulasi) — nilai final tetap
   dihitung ulang `resolveTypeFromLines()` di server kalau field kosong, dan
   `assertTypeMatchesLines()` tetap menolak kalau ada yang mengoprek hidden input via devtools
   supaya tidak cocok dengan barisnya.

**Baris rincian juga dapat tombol *Tambah Cepat*** (`<x-invoice-line-rows>` bagian atas) —
`+ Langganan Bulanan`, `+ Tambah Kabel`, `+ Pindah Lokasi`, `+ Tambah Tiang`,
`+ Biaya Aktivasi`, `+ Jasa Lainnya (Ketik)`. Tiap tombol memanggil `addPreset(categoryCode,
subcategoryCode)` yang mengisi kategori+sub sekaligus (dan nominal langganan/tarif standar bila
ada) — bukan cuma menambah baris kosong yang masih perlu dua kali pilih dropdown. Admin di
lapangan cukup klik tombol yang sesuai pekerjaannya; jenis dokumennya beres sendiri di
belakang layar, sesuai kritik yang diangkat: "admin tidak perlu dipusingkan memilih Jenis
Tagihan dan Master Kategori secara terpisah."

Konsekuensi pada validasi (`InvoiceController::store()`): `invoice_type` sekarang
**`nullable`**, bukan `required`. Kiriman eksplisit tetap diterima (dipakai test & jalur non-JS)
tapi kosong tidak lagi jadi error — `ManualInvoiceService` yang menebaknya dari baris.

## Halaman Buat Tagihan Manual

`GET /invoices/create` + `POST /invoices` — **halaman, bukan modal.**

Alasannya teknis, bukan selera (CLAUDE.md, aturan 3 pola aksi): `back()->withErrors()
->withInput()` balik ke *referer*. Form ini punya baris majemuk + validasi server; sebagai
modal di atas halaman List, gagal validasi akan menutup modal dan menampilkan List kosong
tanpa pesan error yang nyantol. Modal lama di `customers/show.blade.php` diganti tombol yang
nge-link ke halaman ini dengan `?customer_id=` ter-prefill.

**Header:** Pelanggan (dropdown ber-`applyUserScope()`) · Klasifikasi tagihan (badge otomatis,
lihat "Revisi 2026-09-09" di atas) · Periode (`Y-m`) · Tanggal terbit · Jatuh tempo.

**Baris rincian** — repeat rows, pola `<x-inventory-line-rows />` yang sudah ada (termasuk
rehydrate `old()` supaya submit gagal tidak mengosongkan baris yang sudah diketik), plus tombol
*Tambah Cepat* per jenis jasa (lihat di atas):

| Kolom | Perilaku |
|---|---|
| Kategori | 4 pilihan, cascading ke sub |
| Sub kategori | terfilter kategori. Kategori **Lainnya** → berganti jadi **input teks bebas** |
| Keterangan | opsional, 255 |
| Nominal | prefill `default_amount`, bisa ditimpa. Kategori **Jasa Layanan Internet** → **readonly**, diambil dari `customer_services.monthly_price` |

Nominal langganan tidak diketik admin karena harga langganan datang dari master layanan —
aturan bisnis existing ("Tagihan turunan dari Pelanggan Aktif + Paket Aktif", CLAUDE.md).
Server mengabaikan nominal kiriman klien untuk baris kategori itu, bukan sekadar `readonly`
di UI.

Redirect setelah simpan → `invoices.show` (PRG, create satu record → halaman Detail).

`invoices/show.blade.php` menampilkan tabel rincian dari `invoice_items`; **fallback ke kolom
lama** kalau tagihan belum punya baris.

## Laporan — dimensi baru, bukan halaman baru

Dua laporan yang sudah ada menjawab dua pertanyaan berbeda. Keduanya dapat dimensi kategori
dari sumber yang sama (`invoice_items`), lewat satu kelas bersama
`App\Services\RevenueBreakdownService` supaya rumus alokasinya tidak ditulis dua kali.

### `/reports/invoices` — Laporan Tagihan (piutang)

Blok ringkasan baru: **per kategori → drill ke sub kategori**, plus filter
`revenue_category_id`.

- **Ditagihkan** = `SUM(invoice_items.amount)` — **eksak**, langsung dari baris.
- **Sisa piutang per kategori** = proporsional dari `remaining_amount` (lihat rumus di bawah).

### `/reports/payments` — Laporan Pembayaran (uang masuk)

Blok ringkasan baru: uang masuk per kategori.

```
porsi kategori X = payments.amount × (SUM(items kategori X) / invoices.subtotal)
```

Karena satu pembayaran menempel ke satu tagihan (`payments.invoice_id`) dan `payments.amount`
sudah pasti bagian yang menutup tagihan itu (`PaymentService::record()` memisahkan
`overpay_amount` ke saldo pelanggan), porsi ini deterministik dan tidak perlu disimpan.

**Eksak** untuk tagihan lunas (bayar 100% ⇒ tiap baris 100%); **proporsional** hanya untuk
tagihan yang dicicil. Label ini wajib tampil di UI, jangan disembunyikan.

Dua hal yang harus dilabeli jelas di halaman:

1. **PPN ditampilkan sebagai barisnya sendiri**, tidak disebar ke kategori — `payments.amount`
   termasuk PPN sedangkan baris item adalah DPP, jadi tanpa baris ini akan ada sisa yang
   tampak "hilang".
2. **Pembayaran lintas periode.** Tagihan Agustus dibayar September masuk laporan pembayaran
   September dengan kategori milik tagihan Agustus. Itu benar untuk cash basis, tapi harus
   dinyatakan supaya tidak dikira laporan tagihan yang salah hitung.

Permission tetap `reports.view` (dua halaman itu sudah di bawahnya) — tidak ada permission
laporan baru, karena tidak ada halaman baru.

### Revisi 2026-09-10 — Filter kategori diimplementasikan, export dilengkapi, satu bug produksi ditemukan & diperbaiki

**Filter.** Dua dropdown baru di kedua form filter — Kategori Pendapatan & Sub Kategori
Pendapatan (`revenue_category_id`/`revenue_subcategory_id`, sub kategori pakai `<optgroup>`
per kategori, tidak perlu JS cascade). Filternya membatasi tagihan/pembayaran yang
**MENGANDUNG** minimal satu baris kategori itu (`whereHas('items', …)` / `whereHas('invoice.items',
…)`), **bukan** memotong nominal dokumen di kartu ringkasan & tabel — tagihan gabungan (mis.
AWAL yang punya Prorata + Biaya Aktivasi + Tambah Kabel sekaligus) tetap tampil dengan total
DOKUMEN penuh, karena baris tabel = 1 tagihan/1 pembayaran, bukan 1 item. Yang murni terisolasi
ke kategori terpilih adalah blok breakdown-nya sendiri, lewat parameter baru
`RevenueBreakdownService::forInvoices()`/`forPayments()` (`$categoryId`, `$subcategoryId`) yang
menambahkan `WHERE invoice_items.revenue_category_id = …` **di dalam join**, terpisah dari
filter `whereHas()` di level dokumen. Badge penjelasan ini tampil di halaman saat filter aktif,
supaya tidak disalahartikan sebagai porsi.

Sub kategori yang tidak cocok kategorinya (id dioprek manual di URL) diabaikan diam-diam, jatuh
balik ke "tanpa filter sub kategori" — pola sama dengan `pop_id` yang sudah ada di kedua
controller, bukan 403/422 untuk sekadar filter tampilan.

**Export.** CSV Laporan Tagihan dan CSV/XLSX Laporan Pembayaran dapat dua kolom baru —
"Kategori Pendapatan" dan "Sub Kategori Pendapatan", daftar nama unik dipisah `; ` (satu
dokumen bisa berkategori campuran). Kedua tombol export sudah otomatis mewarisi filter
kategori aktif karena keduanya memakai `request()->query()` apa adanya.

**XLSX Laporan Pembayaran dapat sheet kedua** "Rincian Kategori Pendapatan" — replika persis
kartu "Uang Masuk per Kategori Pendapatan" di halaman (kategori/sub, nominal, porsi %, baris
Diskon/PPN, baris TOTAL), dihitung dari query export yang sama (hormat semua filter termasuk
kategori) dipersempit ke `payment_status = valid`. CSV sengaja TIDAK ikut dapat sheet — format
itu satu tabel datar tanpa konsep sheet, dan menyisipkan baris rekap di tengah data mentah akan
salah ke-parse sebagai baris pembayaran oleh alat lain yang membaca CSV itu. Implementasi:
`PaymentReportController::writeRevenueBreakdownSheet()`, dipakai `SimpleExcelWriter::
addNewSheetAndMakeItCurrent()` (dependency `spatie/simple-excel` yang sudah ada, bukan baru).

**Bug produksi ditemukan saat verifikasi filter ini** (bukan regresi dari filter itu sendiri —
sudah ada sejak breakdown pertama kali dipasang 2026-09-09, cuma belum pernah ketiban test yang
memakai user ber-scope selain `all_pop`): `payments` dan `invoices` sama-sama punya kolom
`pop_id`. `HasPopScope::scopeApplyUserScope()` (dipakai bersama banyak model) menulis
`whereIn('pop_id', …)` **tanpa prefiks tabel** — aman selama query itu masih satu tabel, tapi
begitu `RevenueBreakdownService` menambahkan `join('invoices', …)` di atas query `Payment` yang
sudah discope, `pop_id` jadi ambigu dan SQL-nya ditolak (`ambiguous column name: pop_id` di
SQLite, error setara di MySQL). Owner/Atasan tidak pernah kena — scope mereka `return $query`
tanpa where sama sekali — jadi lolos tak terdeteksi sampai user ber-scope `selected_pop`/
`pop_tree` (admin cabang, pop_admin, dst — mayoritas pengguna nyata) membuka
`/reports/payments` dan mendapat **HTTP 500**.

Diperbaiki di `RevenueBreakdownService::joinPaymentsToInvoices()`: `$payments` yang masuk
diisolasi lebih dulu jadi subquery id (`whereIn('payments.id', (clone $payments)->select('payments.id'))`)
— subquery itu masih satu tabel jadi `pop_id`-nya tidak ambigu — baru query BARU yang benar-benar
di-`join()` dimulai bersih tanpa where lawas. Dipakai bersama oleh `forPayments()` dan
`nonCategoryPortions()`, dua-duanya kena bug yang sama. Regresi dijaga
`RevenueBreakdownReportTest::laporan_pembayaran_tidak_meledak_untuk_user_ber_scope_selected_pop()`.
`forInvoices()` TIDAK kena — `invoice_items` tidak punya kolom `pop_id`, jadi cuma satu tabel
(`invoices`) yang mengeksposnya di join itu, tidak pernah ambigu.

## Backfill tagihan lama

`php artisan billing:backfill-invoice-items {--period=} {--dry-run}`

### Temuan yang menentukan bentuk perintah ini

Survei DB produksi-dev 2026-09-09 (6.092 tagihan) menunjukkan **dua populasi yang tidak boleh
diperlakukan sama**:

```
non-legacy (old_invoice_id NULL) : 4.364  — semua kolom biaya tambahan 0/NULL
legacy     (old_invoice_id ADA)  : 1.728  — kolom biaya tambahan TERISI tapi TIDAK ikut ditagihkan
```

Contoh legacy:

```
INV-IN000011-AWAL   subtotal=110.000  prorate=110.000  instalasi=250.000  lain=11.000  total=110.000
INV-IN001825-202507 subtotal= 76.452  extras=72.000                                    total= 76.452
```

`subtotal == total_amount == prorate`, sementara `extra_installation_fee` dan `other_fee`
berisi angka yang **tidak pernah masuk hitungan tagihan**. Di data legacy kolom-kolom itu
sifatnya informasi, bukan komponen.

**Konsekuensi:** memetakan kolom → baris untuk tagihan legacy akan membuat
`INV-IN000011-AWAL` punya baris berjumlah 371.000 padahal yang ditagihkan 110.000 — laporan
pendapatan menggelembung sampai 4×, dan angkanya masuk sebagai fakta yang tak ada penandanya.
Rancangan awal dokumen ini melakukan persis itu; dibatalkan.

### Pemetaan final

**Tagihan legacy (`old_invoice_id` terisi) → SATU baris senilai `subtotal`:**

| invoice_type | Kategori | Sub | description |
|---|---|---|---|
| `awal` | Jasa Layanan Internet | Prorata | `"Migrasi data lama — rincian biaya tidak dapat diverifikasi"` |
| `bulanan` / `reaktivasi` | Jasa Layanan Internet | Langganan Bulanan | idem |

Kolom biaya tambahan **sengaja tidak dipetakan** — nilainya tidak pernah ikut ditagihkan.
Angkanya tetap utuh di `invoices`, tidak dihapus, jadi kalau suatu saat ada keputusan bisnis
untuk menagihkannya susulan datanya masih ada.

**Tagihan non-legacy (`old_invoice_id` NULL) → pemetaan per kolom:**

| Kolom | Kategori | Sub |
|---|---|---|
| `subtotal` dikurangi kolom di bawah | Jasa Layanan Internet | Langganan Bulanan |
| `prorate_amount` | Jasa Layanan Internet | Prorata |
| `extra_installation_fee` | Jasa Instalasi | Biaya Aktivasi / Registrasi |
| `extra_cable_fee` | Jasa Perbaikan | Tambah Kabel |
| `extra_pole_fee` | Jasa Perbaikan | Tambah Tiang |
| `other_fee` | Lainnya | snapshot `"Materai / Biaya Lain"` |

Baris Langganan Bulanan berperan sebagai **residual** — menyerap selisih, sehingga invariant
`SUM(baris) == subtotal` terpenuhi by construction. Per hari ini keempat kolom tambahan di
populasi ini semuanya 0, jadi hasilnya satu baris; pemetaan tetap ditulis general karena
`InitialInvoiceService` akan mulai mengisi kolom-kolom itu untuk tagihan AWAL baru.

### Aturan keselamatan

- **Idempotent** — tagihan yang sudah punya baris dilewati, bukan ditimpa.
- **Invariant diverifikasi per tagihan sebelum menulis.** `SUM(baris) ≠ subtotal` ⇒ tagihan
  dilewati dan dilaporkan, bukan ditambal diam-diam. Dengan pemetaan di atas ini seharusnya
  tidak pernah terjadi; kalau terjadi, artinya ada populasi ketiga yang belum teridentifikasi
  dan itu harus dilihat manusia dulu.
- **Tidak ada `--force-balance`.** Opsi menambal selisih otomatis dihapus dari rancangan:
  satu-satunya sumber ketidakseimbangan yang diketahui sudah ditangani dengan pemetaan
  terpisah di atas, dan menyediakan pintu tambal berarti mengundang angka karangan masuk ke
  laporan keuangan.
- `--dry-run` mencetak rencana per populasi tanpa menulis. **Wajib dijalankan dan hasilnya
  dibaca sebelum eksekusi sungguhan.**

## Berkas

**Baru**

```
database/migrations/…_create_revenue_categories_table.php
database/migrations/…_create_revenue_subcategories_table.php
database/migrations/…_create_invoice_items_table.php
app/Models/RevenueCategory.php
app/Models/RevenueSubcategory.php
app/Models/InvoiceItem.php
app/Services/InvoiceItemBuilder.php
app/Services/ManualInvoiceService.php
app/Services/InvoiceNumberGenerator.php
app/Services/RevenueBreakdownService.php
app/Http/Controllers/Master/RevenueCategoryController.php
app/Console/Commands/BackfillInvoiceItemsCommand.php
database/seeders/RevenueCategoryFeatureSeeder.php   (Feature ROOT, sort_order 23)
resources/views/master/revenue-categories/{index,create,edit}.blade.php
resources/views/invoices/create.blade.php
resources/views/components/invoice-line-rows.blade.php
```

**Disunting**

```
app/Enums/InvoiceType.php                        + case INSIDENTAL
app/Models/Invoice.php                           + relasi items()
app/Services/InitialInvoiceService.php           + rakit baris
app/Console/Commands/GenerateMonthlyInvoicesCommand.php  + rakit baris, pakai InvoiceNumberGenerator
app/Http/Controllers/InvoiceController.php       + create()/store()
app/Http/Controllers/InvoiceReportController.php + breakdown kategori
app/Http/Controllers/PaymentReportController.php + breakdown kategori
app/Http/Controllers/CustomerController.php      − storeManualInvoice() (diganti halaman)
config/rbac.php                                  + revenue_categories (view/create/update)
database/seeders/{DatabaseSeeder,RolePermissionSeeder}.php
routes/web.php                                   + master + invoices.create/store
resources/views/customers/show.blade.php         modal → link ke halaman
resources/views/invoices/show.blade.php          tabel rincian + fallback kolom lama
resources/views/reports/{invoices,payments}/index.blade.php
resources/views/components/layout/sidebar.blade.php   + menu Master
docs/TASKS.md                                    + baris ADHOC-60
```

**Haram disentuh** — pekerjaan user yang belum di-commit di working tree yang sama:
`FopAnalyticsController`, `FopAnalyticsFeatureSeeder`, `resources/views/fop/**`,
`FopAnalyticsDashboardTest`, `NocDashboardController`, `resources/views/noc/**`,
`NocDashboardTest`, `TicketFeatureSeeder`, `docs/plan/noc-dashboard-analysis.md`,
`docs/plan/analisa-dashboard-analitik-fop.md`, migrasi
`…add_actor_index_to_ticket_histories_table`. Suntingan pada `config/rbac.php`,
`routes/web.php`, `RolePermissionSeeder`, `DatabaseSeeder`, `sidebar.blade.php`, `TASKS.md`
harus **menambah baris**, bukan menulis ulang bagian yang sudah mereka ubah.

## Permission

`revenue_categories` — Feature ROOT, `sort_order 23` (22 dipakai `fop_analytics`).
Action: `view`, `create`, `update`. **Tanpa `delete`** — master tidak pernah dihapus,
hanya dinonaktifkan lewat toggle (pola `Master/ItemCategoryController::toggleStatus()`).

Assignment role di `RolePermissionSeeder`: `owner` (lewat `*`), `admin` penuh,
`atasan` view saja. `pop_admin` **tidak** dapat — master pendapatan berlaku lintas cabang,
mengubahnya dari satu cabang berdampak ke semua.

## Test

| Test | Yang dijaga |
|---|---|
| `InvoiceItemSubtotalInvariantTest` | `SUM(items) == subtotal` di **ketiga** jalur penerbit |
| `ManualInvoiceCreateTest` | happy path; gate `invoices.create`; POP scope; nominal langganan dari server bukan klien; kategori Lainnya wajib nama ketikan; INSIDENTAL boleh 2× sebulan; non-insidental kena guard langganan |
| `RevenueCategoryMasterTest` | CRUD sub kategori; kategori tidak bisa ditambah/dihapus; `code` kategori tidak bisa disunting; toggle nonaktif; gate permission |
| `RevenueBreakdownReportTest` | breakdown eksak di Laporan Tagihan; alokasi proporsional di Laporan Pembayaran; baris PPN terpisah; POP scope; **regresi ambiguous-column `pop_id` untuk user ber-scope `selected_pop`**; filter kategori/sub kategori (tabel tetap total dokumen, breakdown murni terisolasi); sub kategori kategori-lain diabaikan; export CSV bawa kolom kategori |
| `BackfillInvoiceItemsCommandTest` | tagihan legacy → satu baris senilai `subtotal`, kolom tambahan diabaikan (regresi penggelembungan 4×); tagihan non-legacy → pemetaan per kolom dengan residual; idempotent; tagihan tak seimbang dilewati & dilaporkan |

## Urutan kerja

1. Migrasi + model + seeder Feature/permission
2. `InvoiceItemBuilder` + `InvoiceNumberGenerator`, pasang ke `InitialInvoiceService` &
   `GenerateMonthlyInvoicesCommand` — **plus `InvoiceItemSubtotalInvariantTest` lebih dulu**,
   supaya invariant sudah dijaga sebelum jalur ketiga ditambah
3. Master Kategori Pendapatan (controller + view + route + sidebar)
4. `ManualInvoiceService` + halaman `/invoices/create`, cabut modal lama
5. Breakdown di dua laporan (`RevenueBreakdownService`)
6. `BackfillInvoiceItemsCommand`
7. `vendor/bin/pint --dirty`, `npm run build`, update `docs/TASKS.md`

## Sengaja TIDAK dibuat

- **Tabel alokasi pembayaran→item.** Porsinya deterministik dari data yang sudah ada;
  menyimpannya cuma menambah sumber kebenaran kedua yang bisa menyimpang. Baru punya alasan
  berdiri kalau muncul kebutuhan yang tidak bisa diturunkan — misalnya admin ingin
  **menentukan sendiri** cicilan ini melunasi baris yang mana (waterfall manual).
- **Halaman "Laporan Pendapatan" ketiga.** Akan tumpang tindih dengan dua laporan yang sudah
  memisah uang-masuk vs piutang.
- **Penghapusan kolom biaya lama di `invoices`.** Task terpisah, setelah backfill terbukti.
- **Kategori pendapatan pada `customer_services`.** Harga langganan sudah punya tempatnya;
  menambah dimensi di sana akan melahirkan sumber kebenaran kedua untuk nominal yang sama.
