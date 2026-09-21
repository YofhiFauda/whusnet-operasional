# Analisa & Rancangan: Request Deaktivasi + Cuti Berlangganan — Membebaskan Tagihan Per Periode (ADHOC-87)

**Status:** Terbuka — analisa selesai 2026-09-21, implementasi belum mulai. Di luar sprint aktif (Sprint 8.10), dicatat sebagai ADHOC-87 di `docs/TASKS.md`.

**Keputusan user 2026-09-21 (menjawab §7):** (1) "Request" **hanya label** — tidak ada persetujuan bertingkat. Tombolnya berlabel **"Request Putus Langganan"**, berisi **dropdown riwayat tagihan pelanggan** yang ingin dihilangkan + **catatan**. (2) Jendela mundur **1 bulan** cukup; kalau kurang, admin membatalkan manual bulan berikutnya. (3) Tagihan `batal` disaring di **sisi API** (repo ini), bukan di aplikasi Portal — lihat §4.6 (sudah dicek ke kode Portal).

**Terkait:**
- [`analisa-rancangan-putus-langganan.md`](analisa-rancangan-putus-langganan.md) (ADHOC-69) — denda putus langganan, master alasan. **Tanpa prorate** (keputusan 2026-09-19): mekanisme di dokumen ini adalah cara pemakaian yang tidak terjadi dikeluarkan dari tagihan, sebagai pengganti prorate.
- [`rancangan-terminate-reactivate-state-machine.md`](rancangan-terminate-reactivate-state-machine.md) (ADHOC-85) — terminasi sudah lewat state machine.
- [`analisa-rancangan-tagihan-manual.md`](analisa-rancangan-tagihan-manual.md) (ADHOC-70) — denda terbit sebagai Tagihan Manual; **tidak** terkena mekanisme di sini.
- [`analisa-skema-piutang-dan-laporan-kas.md`](analisa-skema-piutang-dan-laporan-kas.md) — definisi piutang & tutup buku kas.

---

## 1. Kebutuhan (studi kasus dari user, 2026-09-21)

Pelanggan tidak membayar tagihan Agustus, koneksi mati akhir Agustus, sepanjang September tidak ada konfirmasi pembayaran dan internet tidak dipakai. Awal Oktober admin mengajukan deaktivasi/putus langganan/terminate. Piutang yang sah hanya **Agustus**; **September seharusnya tidak ditagih** karena layanan tidak dipakai. Kalau tagihan September sudah terbit, admin berhak menghapusnya.

**Contoh 1 — Request Deaktivasi.** Agustus sudah tutup pembukuan → tetap piutang. Tagihan September sudah terbit (admin baru sadar saat cek akhir bulan). Pelanggan konfirmasi putus per Oktober. Di Modal Pelanggan (List Pelanggan) atau Detail Pelanggan admin memilih **Request Deaktivasi**, memilih **bulan** yang sesuai tagihan yang ada (mis. September), mengisi catatan, simpan → invoice September **tidak jadi terbit / hilang**.

**Contoh 2 — Cuti Berlangganan.** Akhir Agustus pelanggan minta cuti September, kembali Oktober. Admin bisa menghilangkan tagihan September, **baik yang sudah terbit maupun yang belum terbit**. Untuk September pelanggan tidak punya tagihan sama sekali.

## 2. Interpretasi (perlu dikonfirmasi — §7)

Dua contoh memakai **satu kemampuan yang sama**: *membebaskan tagihan langganan pada periode tertentu*. Bedanya hanya pintu masuk dan efek ke status pelanggan:

| | Request Deaktivasi (Contoh 1) | Cuti Berlangganan (Contoh 2) |
|---|---|---|
| Pintu | Form Putus Langganan (ADHOC-69), berlabel **"Request Putus Langganan"** — ditambah dropdown riwayat tagihan + catatan | Aksi baru "Cuti Berlangganan" di Detail Pelanggan |
| Efek status pelanggan | `terminated` (state machine ADHOC-85) | **Tidak berubah** (tetap `active`/`suspended`) |
| Periode yang boleh dipilih | Periode yang **sudah punya invoice** | Periode yang sudah punya invoice **dan** periode ke depan yang belum terbit |
| Denda | Ya (ADHOC-69) | Tidak |

"Request Deaktivasi", "putus langganan", dan "terminate" diperlakukan sebagai **satu aksi** (persis kalimat user: "admin request deactivasi/putus langganan/terminate"). **Dikonfirmasi user:** "Request" hanya label, tidak ada langkah persetujuan bertingkat.

## 3. Temuan di Kode

1. **Belum ada jalur yang menetapkan invoice `batal`.** `InvoiceStatus::BATAL` hanya dipetakan dari data legacy saat import (`CustomerController` ~baris 3488). Tidak ada tombol, route, atau service pembatalan. Yang ada baru sisi *pembaca*:
   - pembayaran ke invoice `batal` ditolak (`PaymentController` ~baris 256, `CollectorPaymentService` baris 94 & 188),
   - `recalculateFromPayments()` melewati invoice `batal` (status tidak berubah walau ada payment nyasar),
   - dedup & generator tidak menghitungnya (`Invoice::hasActiveSubscriptionInvoiceForPeriod()`),
   - laporan tunggakan & Dashboard mengecualikannya (`InvoiceReportController` baris 75/84/165, `DashboardController` baris 151/154/193).
2. **`invoices` tanpa `SoftDeletes`.** Karena itu "menghapus" tagihan = **status `batal`**, bukan `DELETE`: baris tetap ada untuk audit dan FK `payments`, dan semua pembaca di atas sudah benar terhadap `batal`. Hard delete tidak dipakai (juga selaras dengan larangan "hapus history / invoice lunas").
3. **Cron tidak bisa dicegah menerbitkan bulan yang belum terbit.** `billing:generate-monthly-invoices` (tanggal 1, 01:00) menerbitkan untuk semua `active`/`suspended`. Untuk Contoh 2 ("yang belum terbit") harus ada **penanda** yang dibaca generator.
4. **Invoice `batal` bisa terbit ulang.** `hasActiveSubscriptionInvoiceForPeriod()` sengaja tidak menghitung `batal` (supaya pengganti bisa terbit). Akibatnya menjalankan ulang `billing:generate-monthly-invoices --period=2026-09` (yang memang dipakai untuk menambal periode lama) akan **menerbitkan lagi** invoice September yang baru dibatalkan. Penanda pada butir 3 juga menutup celah ini.
5. **Portal pelanggan** (repo `portal-pelanggan`, sudah dicek 2026-09-21):
   - `GET /me/invoices` (`PortalInvoiceController::index`) tidak mengecualikan `batal`. Halaman `/tagihan` Portal mengirim `exclude_status=lunas` saat "Semua Status" dipilih — jadi invoice `batal` **ikut tampil** di daftar itu. Dropdown Portal juga punya opsi eksplisit `batal` (`status=batal`).
   - Dashboard Portal memakai `GET /me/invoices?status=belum_dibayar` — tidak terpengaruh, `batal` tidak pernah masuk di sana.
   - Portal sudah punya tipe dan badge `batal` (`portal-api.ts`, `StatusBadge.tsx`), jadi **tidak butuh perubahan kode Portal**.
   - Receiver webhook `invoice.updated` di Portal (`api/webhooks/invoice-updated/route.ts`) saat ini **hanya mencatat log** (`console.info`, ada TODO realtime-push) dan tidak bertindak. Halaman Portal selalu mengambil data langsung dari API, jadi kebenaran tampilan tidak bergantung pada webhook.
   - Event `invoice.updated` memang **tidak pernah terkirim** untuk invoice `batal` (`recalculateFromPayments()` early-return sebelum dispatch), tapi karena Portal tidak memakainya, itu bukan penghalang.
6. **"Tutup pembukuan" tidak ada di kode.** Itu konsep laporan kas (uang masuk dicatat di bulan diterima, bukan bulan terbit — `analisa-skema-piutang-dan-laporan-kas.md`), bukan kunci periode pada invoice. Tidak ada penghalang teknis untuk membatalkan invoice periode lama, jadi batas "Agustus tidak boleh disentuh" harus dibuat sebagai **aturan** (§4.3).
7. **Laporan akrual berubah surut.** Laporan Tagihan memakai `billing_period` (*invoice basis*). Membatalkan invoice September di bulan Oktober mengubah angka laporan September yang mungkin sudah dilihat. Diterima, dengan syarat pembatalan tercatat rapi dan bisa dilihat (§4.5).
8. **Tombol Putus Langganan sekarang ada di Detail Pelanggan** (inline toggle, `customers/show.blade.php` ~baris 711, syarat `customers.deactivate` + status `active`/`suspended`). Sesuai CLAUDE.md, aksi lanjutan di halaman Detail = inline toggle Alpine; dari List Pelanggan cukup **link** ke Detail, bukan modal baru. (Komentar di `show.blade.php` ~baris 706 sudah mencatat link `#terminate` dari Quick Hub yang mati — perlu diperiksa saat implementasi.)

## 4. Rancangan

### 4.1 Tabel baru `customer_billing_waivers`

Tabel baru dibenarkan: butir 3 butuh tempat menyimpan periode yang **belum punya invoice**, dan kolom di `invoices` tidak bisa mewakili baris yang belum ada.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | | |
| `customer_id` | FK, restrict | |
| `billing_period` | string(50) | format sama dengan `invoices.billing_period` (`YYYY-MM`) |
| `source` | string, enum `BillingWaiverSource` | `termination` \| `leave` (bukan string literal — aturan enum repo) |
| `reason` | text | wajib, dari catatan admin |
| `invoice_id` | FK nullable, restrict | invoice yang dibatalkan; null = periode belum punya invoice saat dibebaskan |
| `created_by` | FK users | |
| timestamps | | |

`unique(customer_id, billing_period)`. Pakai trait audit yang sama dengan model billing lain (buat & hapus tercatat). **Mencabut pembebasan = hapus baris** (riwayat ada di audit log); tidak ada kolom `revoked_*`.

### 4.2 Service `BillingPeriodWaiverService` (logic di Service, controller tipis)

- `eligiblePeriods(Customer, BillingWaiverSource): list` — untuk mengisi form; sumber tunggal aturan §4.3 supaya UI dan validasi server tidak menyimpang.
- `waive(Customer, array $periods, string $reason, BillingWaiverSource, User $actor)` — satu `DB::transaction()`. Per periode: kunci baris invoice (`lockForUpdate`), validasi ulang aturan §4.3 **di dalam transaksi** (invoice bisa dibayar kolektor di sela-sela form dibuka), tulis baris waiver, lalu bila ada invoice: set `invoice_status = batal` dan `remaining_amount = 0` (`total_amount` **tidak** diubah — jejak nominal asli; `remaining_amount` di-nol-kan sebagai sabuk pengaman untuk query yang menjumlahkan kolom itu tanpa filter `batal`).
- Setelah commit, dispatch `InvoiceStatusUpdated` untuk tiap invoice yang dibatalkan, demi konsistensi dengan semua perubahan status invoice lain (listener `SendInvoiceUpdatedWebhook` tidak menyaring `batal`; yang menyaring adalah `recalculateFromPayments()`). Prioritas rendah: Portal saat ini hanya mencatat event itu (§3 butir 5).
- `revoke(waiver, User $actor, string $reason)` — hapus baris. Invoice yang sudah `batal` **tidak dihidupkan kembali**; kalau perlu ditagih lagi, jalankan `billing:generate-monthly-invoices --period=…` setelah waiver dicabut.

### 4.3 Aturan (guard) — semuanya di Service

| # | Aturan | Alasan |
|---|---|---|
| G1 | Hanya invoice **`bulanan`**. Bukan `awal` (Aktivasi), bukan Tagihan Manual/denda/reaktivasi | Yang dibebaskan adalah *langganan yang tidak dipakai*; denda dan biaya aktivasi bukan itu |
| G2 | Hanya invoice **tanpa pembayaran valid** (`paid_amount = 0`, status `belum_dibayar`). `sebagian`/`lunas` **ditolak** | Membatalkan invoice yang sudah ada uangnya = urusan refund/void payment, bukan pembebasan. Larangan "tagihan lunas tidak dihapus sembarangan" |
| G3 | Jendela periode: **bulan berjalan dan 1 bulan sebelumnya**, plus periode ke depan yang belum terbit (khusus Cuti). Periode lebih lama (Agustus di Contoh 1) **ditolak** | Menegakkan "Agustus sudah tutup → tetap piutang". Tanpa batas ini admin bisa menghapus piutang lama diam-diam. Batas mundur disimpan sebagai config, bukan angka di kode |
| G4 | `reason` wajib | Jejak audit |
| G5 | Permission **terpisah** dari `customers.deactivate` dan `customers.update` (nama final mengikuti generator RBAC — `docs/rbac/`); role global + POP scope (`authorizeCustomerPopScope`), bukan role per cabang | Larangan keras RBAC: helpdesk tidak boleh mengubah nominal tagihan terbit; membatalkan tagihan lebih berat dari itu |
| G6 | Pelanggan berstatus `active`/`suspended` (Cuti) atau sedang diputus (Deaktivasi) | Pelanggan yang belum aktif belum punya tagihan langganan |
| G7 | Periode yang sudah punya waiver **ditolak** (idempoten, bukan error 500) | Unique index sebagai jaring terakhir, pengecekan eksplisit di Service |

### 4.4 Pembaca waiver (agar bebas tidak bocor lewat jalur lain)

- `GenerateMonthlyInvoicesCommand`: lewati pelanggan+periode yang punya waiver (hitung sebagai `skipped`).
- `InvoiceObserver::rejectSecondSubscriptionInvoice()`: tolak pembuatan invoice **langganan** untuk periode yang punya waiver, dengan pesan yang menyebut cara mencabutnya. Di observer, bukan hanya di command, karena aturan repo: invariant harus jalan dari semua jalur (import, tinker, `--period`). Ditaruh berdampingan dengan guard yang sudah ada dan memakai satu method bersama di model (pola `hasActiveSubscriptionInvoiceForPeriod`).

### 4.5 UI

- **Detail Pelanggan** — form Putus Langganan yang ada berlabel **"Request Putus Langganan"** (label saja, §2) dan ditambah **dropdown riwayat tagihan pelanggan** (multi-pilih, tidak ada yang terpilih secara default) + **catatan**, wajib diisi bila ada tagihan yang dipilih. Dropdown menampilkan riwayat tagihan langganan terbaru pelanggan itu; yang **tidak eligible tetap tampil tetapi nonaktif dengan alasannya** ("Sudah dibayar", "Di luar jendela 1 bulan", "Bukan tagihan langganan"), supaya admin paham kenapa Agustus tidak bisa dipilih alih-alih mengira daftarnya salah. Waive dan terminate berjalan dalam **satu transaksi** (waive dulu, baru transisi status), supaya tidak ada keadaan setengah jadi (pelanggan putus tapi tagihan belum dibatalkan, atau sebaliknya).
- **Detail Pelanggan** — aksi baru **"Cuti Berlangganan"** (inline toggle, pola CLAUDE.md #3): pilih satu atau beberapa periode (yang sudah terbit + ke depan), catatan wajib, simpan. Daftar waiver aktif tampil di tab Tagihan (periode, alasan, siapa, kapan, tombol cabut bila punya permission).
- **List Pelanggan** — hanya link ke Detail (bukan modal), sesuai CLAUDE.md.
- **Detail Invoice yang `batal`** — tampilkan alasan & pembatalnya (dari baris waiver) supaya admin lain paham kenapa.
- **Laporan** — filter status `batal` sudah ada di daftar invoice; cukup pastikan invoice yang dibatalkan lewat mekanisme ini bisa ditemukan dan dijelaskan. Daftar khusus "pembebasan tagihan" untuk Owner adalah pengembangan opsional, tidak dirancang sekarang.
- Cuti **tidak** otomatis mengisolir koneksi. Kalau perlu mematikan layanan, admin memakai toggle Isolir yang sudah ada (tagihan tetap tidak terbit karena waiver, walau status `suspended`).

### 4.6 Portal pelanggan

**Keputusan user: API yang menyaring.** Perubahan di repo ini (`CustomerPortal\PortalInvoiceController::index`), **tanpa perubahan kode Portal**:

- Kalau parameter `status` **kosong**, hasil daftar **tidak menyertakan `batal`** (selain pengecualian `exclude_status` yang sudah ada). `exclude_status` hanya menampung satu nilai, jadi `batal` disaring oleh aturan bawaan di controller, bukan lewat `exclude_status`.
- Kalau `status=batal` dikirim **eksplisit**, invoice batal tetap ditampilkan — opsi "Batal" di dropdown Portal tetap berfungsi, dan pelanggan tetap bisa melihat riwayat pembatalan.
- `GET /me/invoices/{invoice_number}` (detail) **tidak diubah**: pelanggan yang membuka nomor invoice batal tetap melihatnya dengan badge "Batal", bukan 404.
- Efek samping yang diterima: invoice `batal` hasil **import legacy** ikut hilang dari "Semua Status" di Portal. Itu memang perilaku yang diinginkan.
- Dokumentasi yang ikut diperbarui: atribut Scramble (`#[QueryParameter]`) di controller, `docs/api/api-portal-pelanggan/business-logic.md`, dan catatan di dokumen Portal (`portal-pelanggan/docs/DOKUMENTASI-PROJEK.md` baris ~485–490, yang sekarang menyebut "Semua Status" hanya mengecualikan `lunas`). Koleksi Postman di repo Portal perlu disegarkan.

## 5. Interaksi dengan Rancangan Lain

- **ADHOC-69 (denda)** — independen. Denda = Tagihan Manual → G1 mengecualikannya. Urutan di form Putus Langganan: waive periode terpilih → transisi `terminated` → terbitkan denda (bergantung ADHOC-70).
- **ADHOC-69 "tanpa prorate"** — mekanisme ini mengganti sebagian fungsi prorate: pemakaian yang tidak terjadi dikeluarkan per bulan penuh oleh admin, bukan dihitung harian. Batasan lama yang dicatat di sana (pelanggan diputus sebelum invoice periode berjalan terbit tidak tertagih) tetap ada.
- **ADHOC-85** — terminasi lewat `CustomerWorkflowService::transition()`; pre-check status di `CustomerTerminationController` dipertahankan. Cuti tidak melewati state machine (status tidak berubah).
- **ADHOC-84 (alokasi pembayaran/FIFO)** — invoice `batal` tidak boleh ikut diantre FIFO; `CollectorPaymentService` sudah menolaknya, tapi perhitungan "piutang lebih tua" untuk peringatan admin harus mengecualikan `batal` (verifikasi saat implementasi).

## 6. Test yang Wajib Ada

Nama sesuai gejala (konvensi repo), PHPUnit atribut modern:

- Deaktivasi, September `belum_dibayar` → invoice `batal`, `remaining_amount = 0`, `total_amount` utuh, baris waiver `source=termination` terisi, pelanggan `terminated`, **Agustus tetap** `belum_dibayar`.
- Deaktivasi dengan periode Agustus (di luar jendela G3) → ditolak, tidak ada yang berubah (termasuk status pelanggan: transaksi utuh).
- Invoice `sebagian` / `lunas` → ditolak (G2).
- Invoice bukan `bulanan` (Aktivasi, Tagihan Manual) → ditolak (G1).
- Race: invoice dibayar setelah form dibuka, sebelum submit → ditolak oleh validasi ulang di dalam transaksi.
- Cuti periode belum terbit → baris waiver dengan `invoice_id` null; **`billing:generate-monthly-invoices --period` tidak menerbitkan invoice** untuk pelanggan itu, pelanggan lain tetap terbit.
- Cuti periode sudah terbit → invoice `batal`; menjalankan ulang generator `--period` **tidak menerbitkan ulang** (regresi temuan §3.4).
- `Invoice::create` langganan untuk periode ber-waiver dari jalur lain (tinker/import) → ditolak observer.
- Cuti tidak mengubah `customers.status`; Cuti pada pelanggan `suspended` tetap tidak diterbitkan tagihan.
- Waiver ganda untuk periode yang sama → ditolak dengan pesan, bukan 500.
- Cabut waiver → generator `--period` kembali menerbitkan; invoice lama tetap `batal`.
- Permission: tanpa izin → 403; POP scope: pelanggan luar scope → ditolak.
- `InvoiceStatusUpdated` terkirim saat pembatalan; pembayaran ke invoice `batal` tetap ditolak (regresi).
- API Portal: `GET /me/invoices` tanpa `status` **tidak memuat** invoice `batal`; `?status=batal` memuatnya; `?exclude_status=lunas` (kiriman Portal) juga tidak memuatnya; `GET /me/invoices/{no}` untuk invoice batal tetap 200; `?status=belum_dibayar` (dashboard) tidak berubah.
- Denda putus langganan (Tagihan Manual) **tidak** terpengaruh waiver.

## 7. Keputusan yang Diambil & Perlu Konfirmasi

Dikerjakan sebagai default terbaik; ubah kalau salah:

| # | Default rancangan | Alternatif |
|---|---|---|
| K1 | "Hapus tagihan" = status **`batal`**, bukan `DELETE` | Hard delete — tidak direkomendasikan (temuan §3.2) |
| K2 | Satu kemampuan, dua pintu (Deaktivasi & Cuti) | Dua fitur terpisah dengan tabel masing-masing — lebih banyak kode, aturan yang sama |
| K3 | Hanya invoice **tanpa pembayaran**; yang sudah ada bayarannya ditolak | Izinkan `sebagian` dengan refund/saldo — butuh kebijakan keuangan (lihat ADHOC-84) |
| K4 | Jendela mundur **1 bulan** (bulan berjalan + 1 sebelumnya); config — **dikonfirmasi user**, kalau kurang dibatalkan manual bulan berikutnya | Lebih longgar, atau override khusus Owner untuk periode lama |
| K5 | Cuti **tidak** otomatis mengisolir koneksi | Cuti = waiver + isolir otomatis |
| K6 | Mencabut waiver **tidak** menghidupkan invoice `batal` (terbitkan ulang lewat generator) | Tombol "pulihkan invoice" — menambah jalur mutasi status yang rawan |
| K7 | Tidak ada langkah persetujuan bertingkat — **dikonfirmasi user: "Request" hanya label** | — |
| K8 | Aturan denda ≤/> 1 tahun (ADHOC-69 §3.1a) tetap terpisah dari pilihan bulan | — |

**Tiga pertanyaan terbuka sebelumnya sudah dijawab user 2026-09-21** (ringkasan di bagian atas dokumen): K7 label saja, K4 cukup 1 bulan, Portal disaring di API. Tidak ada pertanyaan terbuka tersisa; K1–K3, K5, K6 masih default rancangan dan bisa diubah user kapan saja.

## 8. Urutan Pengerjaan

1. Migrasi `customer_billing_waivers` + model + enum `BillingWaiverSource` + permission RBAC (generator, bukan hardcode) + config jendela.
2. `BillingPeriodWaiverService` + test guard G1–G7 (belum ada UI).
3. Pembaca waiver: `GenerateMonthlyInvoicesCommand` + `InvoiceObserver` + test regresi generator/`--period`.
4. UI Cuti Berlangganan (Detail Pelanggan) + tab Tagihan.
5. Integrasi ke form Putus Langganan (satu transaksi) — setelah/bersamaan ADHOC-69.
6. Perubahan API Portal di repo ini (§4.6: `PortalInvoiceController::index` menyaring `batal` secara default) + dokumen API/Portal; dispatch `InvoiceStatusUpdated`. Bisa dikerjakan **lebih awal dan terpisah** dari langkah 1–5: invoice batal hasil import legacy sudah ada sekarang.
7. `vendor/bin/pint`, `npm run build`, update `docs/customer-lifecycle/`, `docs/billing-pembayaran/`, `docs/rbac/`, dan `docs/TASKS.md` sesuai `docs/DEFINITION_OF_DONE.md`.
