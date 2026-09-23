# Analisa & Rancangan: Alokasi Pembayaran (Bulanan, Piutang, Cicilan, Lebih Bayar)

**Status:** Terbuka — rancangan **final & disederhanakan** 2026-09-19, ditambah & dikunci §8 (visibilitas laporan & audit) 2026-09-22, tidak ada pertanyaan tersisa. Implementasi belum mulai. Dicatat sebagai ADHOC-84 di `docs/TASKS.md`, di luar sprint aktif.

**Dikonfirmasi ulang user (2026-09-22): dropdown "Alokasi Pembayaran" manual TETAP tidak jadi dikerjakan** — mekanisme bulanan/piutang/cicilan/lebih-bayar yang sudah otomatis di `PaymentService` dinilai sudah bagus, tidak perlu diketik manual kasir (§1 poin 1–2 tidak berubah). Yang BARU diminta: klasifikasi itu **ditunjukkan** di laporan & audit internal — lihat §8.
**Modul terkait:** Billing & Pembayaran (`invoices`, `payments`, `customer_credits`, `payments/partials/quick-payment-modal.blade.php`, `payments/create.blade.php`, `CollectorPaymentService`)
**Rujukan:**
- `docs/plan/billing/analisa-skema-piutang-dan-laporan-kas.md` (aturan "piutang dibayar dulu", FIFO)
- `docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md` (§D-5)
- `docs/billing-pembayaran/README.md`

> **Riwayat revisi.** Draf pertama (2026-09-18) merancang enum `PaymentAllocation`, kolom `payments.allocation_type`, backfill data lama, dan guard yang menolak nominal tidak pas — empat fase. Review terhadap kode nyata + diskusi user (2026-09-19) menunjukkan **hampir semuanya tidak perlu**: pemisahan cicilan/lebih bayar sudah otomatis dan benar di `PaymentService`, dan dropdown "Alokasi" ternyata cuma dekorasi. Dokumen ini menggantikan draf itu sepenuhnya; draf lama masih ada di riwayat git kalau perlu dibandingkan.

---

## 1. Ringkasan Putusan

1. **Dropdown "Alokasi Pembayaran" dihapus**, tidak diganti dropdown lain. Alokasi **tidak diketik kasir**, dihitung dari data.
2. **Tidak ada enum, kolom, atau migrasi baru.** Klasifikasi (bulanan/piutang/cicilan/lebih bayar) dihitung saat dibutuhkan.
3. **Kontrol kasir = konfirmasi**, bukan pilihan: konfirmasi eksplisit untuk **lebih bayar** (uang masuk saldo pelanggan). Cicilan cukup petunjuk yang sudah ada.
4. **Piutang dibayar dulu = peringatan (bukan blokir)** untuk admin yang membayar tagihan lebih baru saat pelanggan masih punya tagihan lebih lama.
5. **FIFO hanya untuk Kolektor**, sebagai isian awal yang bisa diubah kolektor. Admin **tidak** pakai FIFO.
6. **Klasifikasi tetap ditampilkan** di Laporan Tagihan, Pembayaran, Bulanan Admin, Bayar Kolektor, dan Audit Internal — dihitung dari data yang sama (§2.2), lewat satu classifier bersama, bukan diketik ulang per laporan (ditambahkan 2026-09-22, lihat §8).

---

## 2. Keputusan & Alasan (2026-09-19)

### 2.1 Kenapa dropdown Alokasi dibuang

Empat pilihan lama (Tagihan Bulanan / Bayar Piutang / Cicilan / Lebih Bayar) mencampur dua hal dan menduplikasi data yang sudah dimiliki sistem:

| Pilihan lama | Sebenarnya sudah diketahui sistem dari |
|---|---|
| Tagihan Bulanan | `invoices.invoice_type` — jenis tagihan sudah ada: `awal` (aktivasi/PSB), `bulanan`, `reaktivasi`, dst. Mengulang "bulanan" di alokasi menduplikasi ini (dan tagihan aktivasi/reaktivasi tidak punya padanan sama sekali di dropdown lama). |
| Bayar Piutang | **Kondisi**, bukan jenis tagihan: periode invoice sudah lewat dan belum lunas |
| Cicilan | Nominal diterima < sisa tagihan |
| Lebih Bayar | Nominal diterima > sisa tagihan |

Kalau kasir diminta mengetik ulang, hasilnya cuma risiko salah pilih tanpa informasi baru. Kondisi saat ini sudah menunjukkan dropdown itu tidak berfungsi: nilainya cuma ditempel sebagai string ke kolom `note` (`quick-payment-modal.blade.php` ± baris 700–703, `note = allocation + ' - ' + userNote`), tidak dibaca backend, dan `payments/create.blade.php` bahkan tidak punya dropdown-nya.

### 2.2 Klasifikasi dihitung, tidak disimpan

Tidak ada kolom `allocation_type`. Semua turunan dari data yang sudah ada:

| Klasifikasi | Cara menurunkan |
|---|---|
| Jenis tagihan | `invoice.invoice_type` (`InvoiceType::label()`) |
| Piutang vs berjalan | `invoice.billing_period` < periode `payment_date` |
| Cicilan | `Payment::installmentContext()` (sudah ada, dipakai kwitansi "Cicilan Ke-N") |
| Lebih bayar | `payments.overpay_amount > 0` |

Kalau kelak laporan butuh memfilter berdasarkan klasifikasi di level SQL dan turunan di atas tidak cukup, baru tambah kolom — waktu itu, bukan sekarang (aturan repo: jangan tabel/kolom baru kalau data yang ada cukup).

### 2.3 Konfirmasi lebih bayar (satu-satunya keputusan sadar kasir)

Yang benar-benar perlu diputuskan manusia adalah **kelebihan uang mau dikemanakan**. Uang lebih selalu jadi saldo pelanggan (`CustomerBalanceService::credit()`), jadi bentuknya konfirmasi, bukan pilihan:

- Nominal diterima **>** sisa tagihan → sebelum simpan, muncul konfirmasi: *"Lebih bayar Rp X akan masuk saldo pelanggan. Lanjutkan?"*
- Tujuan utama: menangkap **salah ketik** (mis. Rp 1.500.000 padahal maksudnya Rp 150.000) — kesalahan paling sering di meja kasir.
- **Cicilan** (nominal < sisa) **tidak** dikasih konfirmasi tambahan — petunjuk "Tercatat sebagai Cicilan Ke-N, sisa setelah ini Rp X" yang sudah ada sudah cukup.
- Konfirmasi ini pengaman UX, bukan pengaman keamanan: server tetap memisahkan `amount` vs `overpay_amount` seperti sekarang dan tidak menolak overpay. Pakai `<x-ui.modal>` (bukan `confirm()` browser), mengikuti preseden ADHOC-82.

### 2.4 Piutang dibayar dulu = peringatan, tidak memblokir

Aturan bisnis: pelanggan yang punya piutang lama sebaiknya membayar piutang dulu (contoh Agus di `analisa-skema-piutang-dan-laporan-kas.md` §1.C). Untuk **admin/kasir**:

- Kalau admin membuka pembayaran untuk invoice X dan pelanggan yang sama masih punya invoice **lebih lama** yang `belum_dibayar`/`sebagian` → tampil **peringatan**: daftar periode + sisa tagihan yang tertunggak.
- Admin **tetap boleh melanjutkan** (peringatan, bukan blokir; tidak ada permission override). Alasan: ada kasus sah pelanggan memang hanya mau bayar tagihan tertentu; blokir keras berisiko menghambat kasir tanpa kasus pembeda yang jelas.
- Tidak ada peringatan kalau tidak ada invoice lebih lama yang belum lunas.
- Data peringatan dirender **server-side** (aturan CLAUDE.md "target aksi/data dirender server"), bukan disusun klien.

### 2.5 FIFO hanya untuk Kolektor

- **Admin/kasir: TIDAK ada FIFO.** Admin memilih invoice sendiri (baris "Bayar"), didukung peringatan §2.4.
- **Kolektor:** menagih banyak orang, tidak bisa mengisi selengkap admin di tiap pembayaran. FIFO menjadi **isian awal**: uang dialokasikan ke invoice belum lunas milik pelanggan dari periode **tertua** (`billing_period` naik) sampai uang habis; sisa setelah semua lunas jadi saldo. Kolektor cukup mengubah kalau pelanggan minta lain.

  | Uang | Tagihan Sept (150k) | Tagihan Okt (150k) | Sisa uang |
  |---|---|---|---|
  | 150k | lunas | belum | 0 |
  | 200k | lunas | sebagian (50k) | 0 |
  | 500k | lunas | lunas | 200k → saldo |

- Klasifikasi per baris (bulanan/piutang/cicilan/lebih bayar) tetap dihitung server dari §2.2, bukan diketik kolektor.
- **Status kode saat ini:** logika urut-tertua **belum ditemukan** di `CollectorPaymentService` (dicek 2026-09-19). Klaim FIFO di `analisa-skema-piutang-dan-laporan-kas.md` §3.B sebelumnya belum terverifikasi → ini pekerjaan baru.

### 2.6 Test lama

Test yang gagal akibat perubahan ini **diperbaiki**, bukan perubahan dilonggarkan agar test lolos. Tiap kegagalan diperiksa satu-satu: test usang → diubah; kalau ternyata alur nyata di lapangan yang rusak → logikanya yang diperbaiki.

---

## 3. Kondisi Kode Saat Ini (dicek 2026-09-19) — yang TIDAK perlu diubah

| Hal | Status | Lokasi |
|---|---|---|
| Tunai + saldo dijumlahkan sebelum dibandingkan ke sisa tagihan | **Sudah benar** — contoh sisa 150k, saldo 50k + tunai 100k = lunas, bukan "cicilan" | `PaymentService::record()` `:77` (`$totalReceived = amount + use_balance_amount`) |
| Pemisahan bagian penutup tagihan vs `overpay_amount` | **Sudah otomatis & benar** (dipisah di ranah sen via `Money`) | `PaymentService::record()` `:78–79` |
| Kredit saldo dari overpay | Sudah ada | `PaymentService` `:116–118` → `CustomerBalanceService::credit()` |
| Petunjuk cicilan/lebih bayar sebelum simpan | Sudah ada (teks, belum berupa konfirmasi) | `quick-payment-modal.blade.php` (`#qp-overpay-hint`), `payments/create.blade.php` (`#overpay-hint`) |
| Label "Cicilan Ke-N" / "Lebih Bayar" di kwitansi | Sudah ada | `payments/show.blade.php` (`installmentContext`) |
| Laporan uang masuk basis `payment_date` | Sudah benar | `PaymentReportController` |

**`PaymentService::record()` tidak diubah.** Tidak ada migrasi.

---

## 4. Rancangan Implementasi

### 4.1 Hapus dropdown Alokasi
- `payments/partials/quick-payment-modal.blade.php`: hapus `<select id="qp-allocation">` beserta labelnya (± baris 194–199), reset nilainya saat modal dibuka (± baris 444), dan penempelan ke `note` (± baris 700–703) — `note` kembali murni catatan user.
- `payments/create.blade.php`: tidak ada dropdown, tidak ada yang dihapus (**jangan** menambahkannya, ini kebalikan dari draf lama).
- Catatan lama yang sudah berawalan "Tagihan Bulanan - …" dibiarkan (data historis).

### 4.2 Konfirmasi lebih bayar (§2.3)
- Modal Bayar Cepat + halaman create: saat submit dan nominal diterima > sisa tagihan → tampilkan `<x-ui.modal>` konfirmasi sebelum form benar-benar dikirim. Nominal lebih dihitung dari parser rupiah yang sama dengan hint yang sudah ada (tunai + saldo dipakai ikut dihitung).
- **Jangan** merakit URL di JS; `action` form tetap dari `route()` (guard `PostTargetRenderedServerSideTest`).

### 4.3 Peringatan piutang admin (§2.4)
- Controller yang me-render modal/halaman create menghitung daftar invoice lebih lama milik pelanggan yang sama (`billing_period` < invoice ini, status `belum_dibayar`/`sebagian`) dan meneruskannya ke view — render server-side.
- View menampilkan peringatan (periode + sisa) kalau daftar tidak kosong; submit tetap diizinkan.
- Query melalui relasi `invoice->customer` (pelanggan sudah lolos POP scope karena invoicenya sudah diakses lewat scope); jangan bikin query invoice lintas pelanggan.

### 4.4 FIFO Kolektor (§2.5)
- Baca dulu `CollectorPaymentService::record()` + view/controller kolektor sebelum coding (belum dibaca detailnya di analisa ini).
- Urutan invoice per pelanggan: `billing_period` naik (tie-break `id`). Isi otomatis nominal per invoice dari total uang yang diterima; sisa → saldo.
- Tetap dalam satu `DB::transaction()` + kunci idempotency batch yang sudah ada — jangan dilonggarkan.

---

## 5. Test yang Wajib Ada

- Form/modal bayar disubmit **tanpa** field alokasi; `note` tersimpan apa adanya (tidak berawalan nama alokasi).
- (Regresi) tunai 100k + saldo 50k pada sisa 150k → invoice `lunas`, tanpa `overpay_amount`.
- (Regresi) nominal > sisa → `overpay_amount` terisi dan saldo pelanggan ter-kredit sebesar selisih.
- (Regresi) nominal < sisa → invoice `sebagian`, `installmentContext()` benar.
- Halaman/modal bayar memuat elemen konfirmasi lebih bayar; server **tidak** menolak overpay.
- Peringatan piutang: tampil kalau ada invoice lebih lama belum lunas milik pelanggan yang sama; tidak tampil kalau tidak ada; pembayaran tetap tersimpan (non-blokir); invoice pelanggan lain tidak ikut terdaftar.
- FIFO Kolektor: uang 200k pada Sept 150k + Okt 150k → Sept `lunas`, Okt `sebagian` 50k; uang 500k → keduanya `lunas` + saldo 200k; kolektor bisa mengubah urutan/isian awal; batch tetap atomik + idempotent.
- Admin **tidak** kena FIFO: bayar invoice yang dipilih tidak otomatis menggeser uang ke invoice lain.
- Test lama yang gagal: diperiksa satu-satu sesuai §2.6.

---

## 6. Checklist Eksekusi

- [ ] Hapus dropdown Alokasi + penempelan ke `note` di `quick-payment-modal.blade.php` (§4.1)
- [ ] Konfirmasi lebih bayar di modal & halaman create (§4.2)
- [ ] Peringatan piutang untuk admin (§4.3)
- [ ] FIFO Kolektor di `CollectorPaymentService` + UI kolektor (§4.4)
- [ ] Tulis test (§5), perbaiki test lama yang usang (§2.6)
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `php artisan test --compact` (filter ke test terkait dulu)
- [ ] Update `docs/billing-pembayaran/` (README, business-logic) sesuai `docs/DEFINITION_OF_DONE.md`; pindahkan ADHOC-84 ke Done di `docs/TASKS.md`

---

## 7. Di Luar Scope

- Kolom/enum `allocation_type`, backfill data lama, guard penolak nominal tidak pas — **dibatalkan** (§1, §2.2).
- Blokir keras untuk pembayaran tagihan baru saat piutang lama ada — tidak dipilih; peringatan saja (§2.4).
- Tagihan bulanan otomatis memakai saldo pelanggan (`GenerateMonthlyInvoicesCommand` tidak memakai saldo; saldo dipakai manual lewat `use_balance_amount` saat bayar) — tidak diubah, di luar ADHOC-84.
- **Dropdown/input manual alokasi di form bayar** — dikonfirmasi ulang **tidak** dikerjakan (lihat catatan status di atas). §8 di bawah cuma soal **menampilkan** klasifikasi yang sudah dihitung otomatis, bukan membiarkan kasir memilihnya.

---

## 8. Visibilitas Klasifikasi di Laporan & Audit (ditambahkan 2026-09-22, permintaan user)

**Kebutuhan:** klasifikasi per pembayaran (Bulanan / Piutang / Cicilan / Lebih Bayar, dihitung dari §2.2 — **tetap tidak disimpan sebagai kolom**, tetap dihitung saat dibutuhkan) harus **terlihat** di lima permukaan: **Laporan Tagihan**, **Laporan Pembayaran**, **Laporan Bulanan Admin**, **Laporan Bayar Kolektor**, dan **Audit Internal**. Ini murni soal **tampilan/pelaporan** — tidak mengubah cara pembayaran dicatat (§1–§7 tetap berlaku apa adanya).

### 8.1 Satu classifier bersama, dipakai di semua tempat

Supaya logika klasifikasi (§2.2) tidak diketik ulang 5x dengan risiko menyimpang satu sama lain, dibuat satu titik hitung bersama — mis. `PaymentClassifier::classify(Payment $payment): array` (nama & lokasi final diputuskan saat implementasi, pola service/helper existing) — mengembalikan label yang konsisten dipakai kelima permukaan di bawah:

| Klasifikasi | Sumber (sama seperti §2.2) |
|---|---|
| **Bulanan** | `payment->invoice->invoice_type` = `bulanan`, DAN `invoice->billing_period` = periode berjalan saat `payment_date` (bukan piutang) |
| **Piutang** | `invoice->billing_period` < periode `payment_date` (pelunasan tagihan periode lalu — definisi sama `analisa-skema-piutang-dan-laporan-kas.md`/ADHOC-89) |
| **Cicilan** | `Payment::installmentContext()` menunjukkan ini bukan pelunasan pertama/penuh |
| **Lebih Bayar** | `payment->overpay_amount > 0` |

Satu payment bisa kena **lebih dari satu label sekaligus** (mis. bayar piutang lama secara mencicil = Piutang + Cicilan) — tampilkan sebagai badge majemuk, bukan dipaksa satu kategori tunggal seperti dropdown lama yang dibuang (§2.1).

### 8.2 Per permukaan

| Permukaan | Controller | Perubahan |
|---|---|---|
| **Laporan Tagihan** | `InvoiceReportController` (`/reports/invoices`) | Kolom/badge per invoice: apakah ini pelunasan Piutang (periode lalu) atau Bulanan berjalan (turunan langsung `billing_period` vs periode laporan, sudah dekat ke logika piutang ADHOC-89 yang sudah ada) — tidak butuh classifier payment, cukup status invoice yang sudah dibaca laporan ini. |
| **Laporan Pembayaran** | `PaymentReportController` (`/reports/payments`) | Kolom baru "Jenis" per baris pembayaran (badge majemuk dari §8.1) + **filter dropdown** (Bulanan/Piutang/Cicilan/Lebih Bayar) supaya bisa disaring, bukan cuma ditampilkan. |
| **Laporan Bulanan Admin** | `CollectorMonthlyReportController` (`/reports/collector-monthly`, ADHOC-90) | Blok Tagihan sudah pisah Terbit/Bulanan/Dimuka/Piutang (ADHOC-90) — modal detail per-sel (`GET /reports/collector-monthly/detail`, sudah ada dari ADHOC-90) ditambah kolom "Jenis" per transaksi di dalam modal, pakai classifier yang sama supaya konsisten sama Laporan Pembayaran. |
| **Laporan Bayar Kolektor** | `CollectorPaymentReportController` (`/reports/collector-payments`, ADHOC-90) | Tabel "Bayar Wifi Cash" — kolom "Jenis" per baris transaksi, classifier sama. |
| **Audit Internal** | `AuditLogController` (`/audit-logs`) | Entri `module=Pembayaran` (atau modul apapun nama Payment di audit) — saat merender detail entri, tampilkan label klasifikasi hasil classifier di samping payload JSON mentah yang sudah ada (`Payment::auditPayload()` sudah menyimpan `amount`/`overpay_amount`/`invoice_id`/`billing_period`, cukup untuk classifier menghitung ulang tanpa query invoice hidup — cek saat implementasi apakah cukup dari payload tersimpan atau perlu load relasi `payment->invoice` untuk entri yang invoice-nya belum berubah). |

### 8.3 Belum diputuskan (untuk implementasi nanti)

> **Dikunci (user, 2026-09-22) — tidak ada lagi yang terbuka di §8:**

1. **Classifier numpang di model `Payment`** — method baru `Payment::classification(): array`, pola sama persis `installmentContext()` yang sudah ada di model ini. Bukan Service terpisah.
2. **Badge majemuk = beberapa pil kecil bersebelahan**, satu pil per label (`[Piutang] [Cicilan]`), bukan digabung jadi satu teks — konsisten sama pola badge status yang sudah ada di tempat lain (invoice, task, dll).
3. **Filter di Laporan Bulanan Admin & Laporan Bayar Kolektor — TIDAK perlu.** Cukup kolom/badge "Jenis" tampil di modal detail & tabel (§8.2), tanpa kontrol filter tambahan di dua laporan itu. Cuma **Laporan Pembayaran** yang dapat filter (sudah dari awal §8.2).

### 8.4 Test yang wajib ada

- Classifier: payment Bulanan biasa → label `Bulanan` saja; pelunasan periode lalu → `Piutang`; nominal kurang → `+Cicilan`; nominal lebih → `+Lebih Bayar`; kombinasi piutang+cicilan sekaligus → kedua label muncul.
- Laporan Pembayaran: kolom "Jenis" tampil benar per baris; filter per jenis menyaring hasil dengan benar; POP scope tetap tertegak (regresi).
- Laporan Bulanan Admin & Laporan Bayar Kolektor: modal/tabel detail menampilkan "Jenis" konsisten dengan Laporan Pembayaran untuk transaksi yang sama.
- Audit Internal: entri Payment menampilkan label klasifikasi yang benar, tidak error untuk entri lama (payment yang invoice-nya sudah dihapus/berubah — pakai payload tersimpan, jangan crash kalau relasi live sudah tidak valid).
