# Analisa & Rancangan: Alokasi Pembayaran (Bulanan, Piutang, Cicilan, Lebih Bayar)

**Status:** Terbuka — rancangan **final & disederhanakan** 2026-09-19, implementasi belum mulai. Dicatat sebagai ADHOC-84 di `docs/TASKS.md`, di luar sprint aktif.
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
