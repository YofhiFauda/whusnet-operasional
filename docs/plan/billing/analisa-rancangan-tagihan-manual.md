# Analisa & Rancangan: Tagihan Manual (Detail Pelanggan + Halaman Tagihan)

**Status:** Terbuka — ditulis ulang 2026-09-19 murni dari studi kasus user, implementasi belum mulai. Dicatat sebagai ADHOC-70 di `docs/TASKS.md`, di luar sprint aktif.

> **Revisi 2026-09-19.** Versi sebelumnya bertumpu pada rancangan lama ADHOC-60 (master kategori pendapatan, subkategori, `invoice_items`, `ManualInvoiceService`, tipe `INSIDENTAL`). Itu **kode lama** yang implementasinya sudah dihapus user dan konsepnya sudah berubah. Dokumen ini **tidak** memakai satupun dari itu. Versi lama ada di riwayat git.

**Terkait:** `docs/plan/billing/analisa-rancangan-putus-langganan.md`, `docs/plan/billing/upgrade-downgrade/analisa-upgrade-downgrade-paket.md`, `docs/plan/billing/analisa-skema-alokasi-pembayaran-dan-saldo.md` (konfirmasi lebih bayar dipakai ulang di form ini). Urutan pengerjaan: lihat dokumen Alokasi & TASKS.md.

---

## 1. Studi Kasus (sumber kebenaran, dari user)

**Tagihan Pelanggan Manual — dari Detail Pelanggan.** Jenis pendapatan yang bisa ditagihkan:
- Pendapatan Perbaikan
- Pendapatan Lainnya (nama diisi manual — contoh: over kabel, Pendapatan A, Pendapatan B)
- Pendapatan Pindah Lokasi

Deskripsi tagihan diisi sendiri. Nominal tagihan diisi sendiri. ~~Metode pembayaran: Cash atau TF~~ — **dicabut 2026-09-23**, lihat §3.4: form ini cuma menerbitkan tagihan, metode bayar diisi belakangan lewat jalur Pembayaran.

**Dari Halaman Tagihan** — strukturnya sama seperti di atas, tapi pelanggan dicari lewat **CID atau Nama**.

**Batas cakupan (dikonfirmasi user 2026-09-19):** Tagihan Manual **hanya** tiga jenis di atas. Aktivasi, Bulanan, dan Reaktivasi adalah jenis tagihan lain dan bukan bagian dari fitur ini. Tagihan manual lama di Detail Pelanggan **dihapus bersih** (§4 butir 6).

---

## 2. Kondisi Kode Saat Ini (dicek 2026-09-19)

- **Detail Pelanggan** punya modal lama `#manual-invoice-modal` (`resources/views/customers/show.blade.php:1249`) → `CustomerController::storeManualInvoice()` (route `customers.invoices.manual`, `routes/web.php:298`, permission `invoices.create`). Modal ini **bukan** yang diminta: ia menerbitkan tagihan **langganan** (`invoice_type` `awal`/`bulanan`/`reaktivasi`, selalu memuat harga bulanan paket + biaya tambahan flat `prorate_amount`/`extra_cable_fee`/`extra_installation_fee`/`extra_pole_fee`), butuh `customerService` aktif, tanpa deskripsi, tanpa pembayaran.
- **Halaman Tagihan** (`/invoices`, `InvoiceController::index()`) hanya listing + search (sudah bisa cari nama/CID/nomor invoice — pola pencariannya bisa dipakai ulang). Tidak ada `/invoices/create` dan tidak ada tombol "Buat Tagihan".
- **Metode pembayaran:** `App\Enums\PaymentMethod` sudah punya `CASH` dan `TRANSFER` (+ `QRIS`/`KOLEKTOR`/`LAINNYA`) — tidak perlu enum baru, cukup batasi pilihan di form jadi Cash/TF. Aturan `requiresBankDetails()` untuk transfer sudah ada.
- **Pembayaran** dicatat lewat `PaymentService::record()` (auto-split cicilan/lebih bayar sudah benar, menjumlahkan tunai + saldo).

---

## 3. Keputusan Rancangan

### 3.1 Halaman create tersendiri, bukan modal
Mutasi data → halaman `/invoices/create` (aturan CLAUDE.md 2026-09-07: `back()->withErrors()` balik ke *referer*, modal-di-atas-List kehilangan pesan error saat validasi gagal). Satu halaman melayani dua pintu masuk:
- **Detail Pelanggan:** tombol "Buat Tagihan Manual" → `/invoices/create?customer_id=…` (pelanggan ter-prefill & terkunci).
- **Halaman Tagihan:** tombol "Buat Tagihan" → `/invoices/create` polos, dengan pencarian pelanggan by CID/Nama di dalam form (pakai salah satu pola existing: `whereHas` seperti `InvoiceController::index()` atau typeahead seperti `customers.search-referral` — jangan bikin mekanisme pencarian ketiga).

### 3.2 Taksonomi jenis tagihan (dikonfirmasi user 2026-09-19)

```
Jenis Tagihan
├─ Aktivasi            (invoice_type `awal`, sudah ada)
├─ Bulanan             (invoice_type `bulanan`, sudah ada)
├─ Reaktivasi          (invoice_type `reaktivasi`, sudah ada — pelanggan putus lalu berlangganan lagi)
└─ Tagihan Manual      (invoice_type BARU — dokumen ini)
     ├─ Tagihan Perbaikan
     ├─ Tagihan Lainnya      (sub-nama diisi sendiri: over kabel, Pendapatan A, B, dst)
     └─ Tagihan Pindah Lokasi
```

- **Tagihan Manual = satu `invoice_type` baru** (nilai `manual`, label "Tagihan Manual"), di luar `Invoice::SUBSCRIPTION_TYPES` (alasan teknis: §3.3). Tiga anaknya bukan tipe invoice sendiri, melainkan **jenis** di dalam tagihan manual.
- Jenis = satu enum PHP dengan tiga nilai (`Perbaikan`, `Lainnya`, `Pindah Lokasi`). **Bukan** tabel master, **bukan** subkategori berjenjang, **bukan** `invoice_items`.
- **Lainnya:** wajib diketik nama sub-nya (over kabel, A, B, dst — nama ketikan bebas, bukan daftar baku).
- **Label:** `InvoiceType::AWAL` berlabel **"Aktivasi"** (diputuskan & diubah 2026-09-19 di `InvoiceType::label()` + opsi filter `invoices/index.blade.php`; nilai `awal` di DB tidak berubah). Teks "Tagihan Awal" lain di UI (KPI, judul grup di Detail Pelanggan, teks bantuan verifikasi) belum diubah.
- Semua jenis: **deskripsi** (teks bebas) dan **nominal** (diketik, format rupiah via `RupiahInput`) diisi manual.
- Kalau kelak butuh jenis baru yang bisa ditambah admin, dibahas terpisah — tidak dirancang sekarang (jangan overengineered).

### 3.3 Tipe invoice non-langganan: kebutuhan teknis, kode lama tidak dipakai
Tagihan manual boleh terbit di bulan yang sama dengan tagihan bulanan pelanggan. Tapi `InvoiceObserver::rejectSecondSubscriptionInvoice()` menolak invoice kedua bertipe langganan (`Invoice::SUBSCRIPTION_TYPES`, `app/Models/Invoice.php:33`) untuk pelanggan + periode yang sama, dan unique index sengaja tidak dipasang (lihat migrasi `2026_07_21_164556`). Akibatnya tagihan manual **tidak boleh** bertipe `awal`/`bulanan`; ia butuh satu nilai `invoice_type` di luar `SUBSCRIPTION_TYPES`.

- Ini kebutuhan teknis dari guard yang sudah ada, bukan sisa rancangan lama.
- **Kode lama `INSIDENTAL` tidak dipakai** (instruksi user 2026-09-19). Tipe barunya **`manual` / "Tagihan Manual"** (§3.2) — case baru di `InvoiceType`, di luar `SUBSCRIPTION_TYPES`. Bentuk penyimpanan jenis / nama sub / deskripsi di tabel `invoices` (kolom baru vs kolom catatan yang sudah ada) diputuskan saat implementasi — cek kolom existing dulu sebelum menambah.
- Kode ADHOC-60 yang masih ada di repo (`RevenueCategory`, `RevenueSubcategory`, `InvoiceItem`, `ManualInvoiceService`, `InvoiceItemBuilder`) **tidak dipakai dan tidak diubah** oleh pekerjaan ini. `ManualInvoiceService` masih dipanggil `CustomerAcquisitionController::updateInstallationFee()` (biaya instalasi Busdev) — nasibnya (dihapus/dibiarkan) di luar scope dokumen ini.

> **Catatan lintas-dokumen (2026-09-19):** Tagihan Manual jenis Lainnya punya **produsen kedua** di luar form ini — denda putus langganan (ADHOC-69, sub "Denda Putus Langganan" diisi sistem). Produsen itu menerbitkan invoice **tanpa Payment** (`belum_dibayar`) — sama seperti form ini sejak revisi §3.4 di bawah, jadi tidak ada lagi perbedaan perilaku antara dua produsen ini soal Payment.

### 3.4 Invoice saja, TANPA Payment (revisi 2026-09-23)
**Keputusan user 2026-09-23** (mengoreksi rancangan awal): form `/invoices/create` **hanya** menerbitkan `Invoice` (`belum_dibayar`) — **tidak** ada field metode pembayaran/nominal dibayar, dan **tidak** memanggil `PaymentService::record()`. Alasan: metode bayar & pencatatan pembayaran itu ranah **Pembayaran**, bukan ranah **Tagihan** — sudah ada jalur resminya sendiri (List Tagihan → tombol Bayar/Bayar Cicil → `PaymentController::store()`/`PaymentService::record()`), jadi menaruh field itu lagi di form pembuatan tagihan cuma duplikasi UI untuk kemampuan yang sudah ada.

~~Versi awal (dibatalkan): satu submit menghasilkan Invoice + Payment sekaligus, field metode Cash/Transfer + nominal dibayar di form yang sama.~~ Tidak dipakai — dicatat di sini supaya tidak terulang tanpa sadar.

---

## 4. Rancangan Implementasi

1. **`InvoiceController::create()`/`store()`** (baru) — halaman `/invoices/create`; `store()`: `DB::transaction()` → buat invoice → `PaymentService::record()` → redirect `invoices.show` (PRG). Permission: pakai `invoices.create` yang sudah ada (tidak menambah permission baru). Route statis `/invoices/create` **sebelum** route dinamis `/invoices/{invoice}`.
2. **`InvoiceType::MANUAL`** (case baru, di luar `SUBSCRIPTION_TYPES`) + **enum jenis tagihan manual** (3 nilai) + migrasi kolom di `invoices` (§3.3).
3. **Detail Pelanggan:** tombol "Buat Tagihan Manual" jadi link ke `/invoices/create?customer_id=…`.
4. **Halaman Tagihan:** tombol "Buat Tagihan" → `/invoices/create`.
5. **POP scope:** pencarian pelanggan & submit tunduk `applyUserScope()`.
6. **Hapus bersih tagihan manual lama di Detail Pelanggan** (keputusan §5.1) — tidak boleh ada kode mati atau rujukan yang tersisa:
   - `routes/web.php:297-299` — grup `permission:invoices.create` berisi hanya route `customers.invoices.manual`; hapus route + grupnya (permission `invoices.create` **dipertahankan**, dipakai ulang untuk `/invoices/create`).
   - `CustomerController::storeManualInvoice()` (± `:3734` s.d. akhir method) + import yang jadi tidak terpakai.
   - `resources/views/customers/show.blade.php`: tombol pembuka modal (± `:107-109` dan `:934-936`, beserta `@can` pembungkusnya — ganti jadi link ke `/invoices/create?customer_id=…`, §3.1), blok modal `#manual-invoice-modal` (± `:1234-1295`), dan JS `openInvoiceModal()`/`closeInvoiceModal()` (± `:1599-1600`).
   - `tests/Feature/InvoiceCreateTest.php` — seluruh isinya menembak route yang dihapus (`customers.invoices.manual`). Tes yang menjaga aturan yang **masih berlaku** (POP scope, anti-dobel per periode) dipindahkan ke tes fitur baru, bukan hilang. Penghapusan/penggantian file tes ini **minta persetujuan user dulu** saat implementasi.
   - Komentar yang masih menyebut `storeManualInvoice`: `GenerateMonthlyInvoicesCommand.php:127`, `InvoiceNumberGenerator.php:10`, `ManualInvoiceService.php:20`.
   - Dokumentasi: `docs/billing-pembayaran/README.md` (`:43`, `:85`, `:100`), `flowchart.md:9`, `database-schema.md:59`, dan tabel di `docs/TASKS.md:493`. Catatan `README.md:85` menyebut guard anti-dobel invoice "ditegakkan di `storeManualInvoice`" — setelah dihapus, guard itu tinggal di `InvoiceObserver`; tulis ulang kalimatnya, jangan sekadar dicoret.

---

## 5. Belum Diputuskan

1. ~~Nasib modal lama + `storeManualInvoice()` + route `customers.invoices.manual`.~~ **Diputuskan user 2026-09-19: dihapus bersih** (daftar penghapusan: §4 butir 6). Tagihan Manual hanya terdiri dari Perbaikan / Lainnya / Pindah Lokasi; Aktivasi, Bulanan, dan Reaktivasi adalah **jenis tagihan lain**, bukan bagian dari Tagihan Manual.
   - **Dampak yang perlu diketahui:** dicek 2026-09-19, **tidak ada jalur otomatis yang menerbitkan invoice Reaktivasi** — hanya modal lama ini (Aktivasi terbit dari `InitialInvoiceService`, Bulanan dari `billing:generate-monthly-invoices`). Setelah modal dihapus, tagihan Reaktivasi tidak bisa diterbitkan sama sekali sampai ada fitur tersendiri untuknya. Itu di luar scope ADHOC-70; dicatat sebagai follow-up (pelanggan putus lalu berlangganan lagi — `CustomerController::reactivate()` sekarang tidak menerbitkan invoice).
2. Bentuk penyimpanan jenis / nama sub / deskripsi di tabel `invoices` (§3.3) — cek kolom catatan existing dulu.
3. Pola pencarian pelanggan: server-side `whereHas` (submit form biasa) atau typeahead AJAX — pilih yang paling konsisten dengan form lain.

---

## 6. Test yang Wajib Ada

- `/invoices/create?customer_id=…` — pelanggan terkunci; invoice terbit `belum_dibayar` dengan jenis, deskripsi, nominal sesuai input, **tanpa** Payment.
- `/invoices/create` polos — cari by CID & by Nama; submit menghasilkan invoice untuk pelanggan yang benar (bukan tertukar hasil pencarian lain).
- Jenis **Lainnya** wajib nama ketikan; jenis lain tidak.
- Tagihan manual **bisa terbit di periode yang sama** dengan tagihan bulanan pelanggan (regresi guard `rejectSecondSubscriptionInvoice`).
- Nominal wajib diisi, format ribuan (`150.000`) ternormalisasi benar.
- POP scope: tidak bisa membuat tagihan untuk pelanggan di luar POP yang diizinkan.
- Route `customers.invoices.manual` dan modal lama sudah tidak ada; `grep` `storeManualInvoice|invoices.manual|manual-invoice-modal` di `app/ routes/ resources/ tests/ docs/billing-pembayaran/` bersih (§4 butir 6).

---

## 7. Dampak ke Modul Lain
- `InvoiceObserver::creating()` — guard dedup (customer+type+billing_period+total_amount dalam 5 menit) jangan false-positive untuk dua tagihan manual sah dengan nominal sama di hari yang sama (mis. dua perbaikan berbeda); cek saat implementasi.
- Laporan (`InvoiceReportController`, `PaymentReportController`) — breakdown per jenis pendapatan tidak termasuk scope ini (follow-up).
- `docs/billing-pembayaran/` — update README/business-logic setelah selesai, sesuai `docs/DEFINITION_OF_DONE.md`.
