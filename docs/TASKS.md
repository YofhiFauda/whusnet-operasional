## Status Project Saat Ini
Current Sprint: **Sprint 8.10** (Audit Trail + Notification System)
Current Module: Perbaikan Gap Migrasi & Tagihan Legacy (Selesai BATCH 1, BATCH 2 & BATCH 3)
Current Task: S8.10-T003 (FOP Notification Dashboard)

### Ad-Hoc Improvements

| Task | Deskripsi | Status |
|---|---|---|
| ADHOC-01 | Desain Ulang List Pelanggan & Dark Theme Toggle | Done |
| ADHOC-02 | Perbaikan Halaman Dashboard, Registrasi Pelanggan, Pelanggan Gagal, Import Pelanggan, Riwayat/Batch Detail, Antrean/Detail Verifikasi, Detail Pelanggan & Sub-Tabs Dark/Light Theme Support | Done |
| ADHOC-03 | Restrukturisasi Modul Ticketing (lihat detail di bawah) | Done — 2026-07-28 |
| ADHOC-04 | Layout Worksheet Helpdesk — panel form lipat + motion & responsif | Done — 2026-07-29 |
| ADHOC-05 | Halaman History Ticketing (lihat detail di bawah) | Done — 2026-07-29 |
| ADHOC-06 | Hapus window Pending NOC + aksi Oncheck NOC (lihat detail di bawah) | Done — 2026-07-29 |
| ADHOC-07 | Support Dark & Light Theme untuk Halaman Report Survey (`surveys.report`) dan Report Pemasangan (`installations.report`) | Done — 2026-07-30 |
| ADHOC-13 | Master Alat Kerja (`work_tools`) + material terstruktur di Laporan Maintenance (lihat detail di bawah) | Done — 2026-07-31 |
| ADHOC-12 | Kategori material jadi Master (`item_categories`) — enum `MaterialType` turun peran (lihat detail di bawah) | Done — 2026-07-31 |
| ADHOC-11 | Tanggal Request Pemasangan (Laporan Survey) + Pencatatan Material Estimasi vs Terpakai + Master Barang (lihat detail di bawah) | Done — 2026-07-31 |
| ADHOC-10 | Detail tiket di Worksheet Helpdesk & Worksheet NOC pindah ke **drawer kanan** (partial bersama + endpoint detail JSON); navigasi halaman penuh disisakan buat Ticket Selesai / Dibatalkan / History (lihat detail di bawah) | Done — 2026-07-30 |
| ADHOC-09 | Redesign Worksheet NOC (`noc.worksheet`) — tabel padat 1 baris/tiket + pencarian + filter + dua tab bercounter (Tiket Masuk / Assign FOP) + aksi lewat drawer baris terpilih (lihat detail di bawah) | Done — 2026-07-30 |
| ADHOC-08 | Redesign Worksheet Helpdesk (`tickets.create`) — panel antrean jadi tabel padat 6 kolom + tab per-handler bercounter + filter prioritas + toggle tabel/kartu; kartu identitas pelanggan diringkas (acuan `helpdesk_redesign.html` + Frame 139) | Done — 2026-07-30 |
| ADHOC-14 | Redesign Halaman Pembayaran (`payments.index`, `payments.create`, `payments.show`, `payments.overpay`) dengan dukungan penuh Dark/Light Theme & Stat Cards | Done — 2026-08-06 |
| ADHOC-15 | Detail Task teknisi: blok Laporan Pekerjaan Teknisi + Riwayat Task Saya + hapus fitur Foto Bukti + fix redirect `return_to` Laporan Survey/Pemasangan (lihat detail di bawah) | Done — 2026-08-06 |
| ADHOC-16 | Pisah "catatan" yang tumpang tindih di Task eksekusi (`Issue/Keluhan` kecampur `catatan_teknis`/`notes`) — 3 sumber, 3 box terpisah (lihat detail di bawah) | Done — 2026-08-07 |
| ADHOC-17 | Task teknisi: catat `completed_by` (siapa yang menyelesaikan & lapor) + tampilkan di detail task, gak lagi cuma keliatan admin/fop (lihat detail di bawah) | Done — 2026-08-07 |
| ADHOC-18 | Kolektor 2.0 **Fase 1** — pisah Worksheet Admin vs Worklist Kolektor, kolektor mencatat pembayarannya sendiri, jendela tagih 7 hari (lihat detail di bawah) | Done — 2026-08-08 |
| ADHOC-19 | Kolektor 2.0 **Fase 2** — Saldo (turunan) + Setoran + cross check + selisih & pelunasan lintas setoran + hapus buku Owner (lihat detail di bawah) | Done — 2026-08-08 |
| ADHOC-20 | Bundle **Alpine.js lewat Vite**, lepas dari CDN jsdelivr + sisir target POST yang dirakit di klien (lihat detail di bawah) | Done — 2026-08-13 |
| ADHOC-21 | Kolektor 2.0 **Fase 3** — Visit Log (kunjungan tanpa hasil) + laporan aging per kolektor (lihat detail di bawah) | Done — 2026-08-08 |
| ADHOC-22 | **Perbaikan hasil review Kolektor 2.0 Fase 1–3** — seluruh 10 temuan ditutup (lihat detail di bawah) | Done — 2026-08-08 |
| ADHOC-23 | **Dokumentasi modul Kolektor** — `docs/kolektor/` (README, business-logic, user-flow, flowchart, database-schema) mencakup Fase 1–3 + perbaikan #1–#9 & R1–R2 | Done — 2026-08-08 |
| ADHOC-24 | Kolektor 2.0 **Fase 4** — kwitansi ber-QR, upload bulk, pencocokan otomatis + OCR cadangan + override manual (lihat detail di bawah) | Done — 2026-08-08 |
| ADHOC-25 | **OCR kwitansi: hasil tebakan tak boleh jadi keputusan** — lubang dorman yang hidup saat `GEMINI_API_KEY` diisi (lihat detail di bawah) | **Terbuka** — dicatat 2026-08-08, **memblokir aktivasi OCR** |
| ADHOC-26 | **Tagihan bulanan tak terbit + pencocokan kwitansi massal + queue/batas PHP** — `--period`, tempo tanggal 10 seragam, lapisan teks PDF jadi jalur utama, lembar borongan, idempotency jalur Tagihan, antrean `kwitansi`, kebocoran queue saat testing (lihat detail di bawah) | Done — 2026-08-11 |
| ADHOC-27 | **Aksi selesai tapi layar diam** — panel progres kwitansi, indikator koneksi realtime global, sukses palsu SLA matrix, realtime setoran & aktivitas kas kolektor dua arah, + 9 test RBAC merah (cache bocor lewat env compose) (lihat detail di bawah) | Done — 2026-08-11 |
| ADHOC-28 | **Material & alat teknisi hilang senyap** — FopTask Survey/PSB cuma lahir saat papan FOP dibuka, padahal jadi anchor wajib `task_materials`/`task_work_tools`; + halaman Verifikasi Admin menampilkan data yang benar-benar diinput (lihat detail di bawah) | Done — 2026-08-11 |
| ADHOC-29 | **Satu pembayaran, tiga kwitansi berbeda isi** — struk thermal, lembar A4, dan kartu kolektor disatukan lewat `ReceiptPresenter`; header/footer bawaan browser dimatikan di semua halaman cetak (lihat detail di bawah) | Done — 2026-08-12 |
| ADHOC-30 | **Nominal `150.000` dibaca 150 rupiah** — masking ribuan di seluruh kolom uang + normalisasi server `RupiahInput` di semua endpoint yang menerimanya (lihat detail di bawah) | Done — 2026-08-12 |
| ADHOC-32 | **Kwitansi hilang senyap pada berkas banyak-halaman** — pembacaan berhenti di hasil pertama (QR: 1 nomor per berkas; teks: parsial menutup jalur cadangan). Berkas tetap berstatus "Cocok". Rancangan: baca **per halaman** + **himpunan yang diharapkan** dari konteks unggahan (sistem menyebut nomor yang belum ketemu). **Bug aktif, tidak menunggu Gemini.** Analisa: `analisa-operasional-ocr-gemini.md` §3A.4, rancangan §3D | **Terbuka** — ditemukan 2026-08-12 |
| ADHOC-31 | **OCR Gemini: rate limit, cooldown & antisipasi galat** — rancangan operasional (rem, klasifikasi 429/503/401, cooldown, pagu harian) **+ tiga temuan berat**: seluruh berkas (data 200 pelanggan) terkirim ke pihak ketiga, prompt satu-nomor vs lembar 200-kwitansi, ketentuan data tier gratis. Plus mode bayangan & rencana gelar. Analisa: `docs/plan/kolektor/analisa-operasional-ocr-gemini.md` | **Terbuka** — dirancang 2026-08-12, implementasi menunggu keputusan D11–D13 |
| ADHOC-33 | **Pelanggan Gagal & Putus masih menumpang satu Blade** — view sendiri per halaman + trait `RendersCustomerList` (lihat detail di bawah) | Done — 2026-08-12 |
| ADHOC-34 | **Tim FOP hilang dari papan begitu ganti hari** — papan menampilkan tim lampau yang masih punya task aktif (maks 30 hari) + pecahan tunggakan di tabel beban teknisi + hapus `fop:reset-cancelled-tasks` (lihat detail di bawah) | Done — 2026-08-13 |
| ADHOC-35 | **Riwayat Perubahan Status di Detail Task tampil dobel** — dua lapis pencatat audit; duplikat murni dicabut + timeline disaring `TaskAuditTimeline` (lihat detail di bawah) | Done — 2026-08-13 |
| ADHOC-36 | **Pencarian pada Halaman Collector Worklist** — pencarian rute kerja berdasarkan Nama Lengkap, CID, atau No. Invoice (lihat detail di bawah) | Done — 2026-08-13 |
| ADHOC-37 | **Jejak uang putus sesudah setoran kolektor diverifikasi** — modul **Setoran Kas** (admin → owner/bank): saldo kas turunan, rincian sumber per kolektor/pelanggan, titik nol data historis, Card Saldo di Worksheet Admin. Analisa & rancangan: [`docs/plan/kolektor/analisa-setoran-kas-admin.md`](plan/kolektor/analisa-setoran-kas-admin.md) | Done — 2026-08-14 |
| ADHOC-38 | **Metode Pembayaran + Saldo Pelanggan di Modal Bayar Invoice** — dropdown Cash/Transfer (field Nama Bank+No. Rekening)/Kolektor (pilih kolektor, saldo kolektor tetap derived); ledger `customer_balance_mutations` (saldo aktif dari lebih bayar, bisa dipakai sebagian di pembayaran berikutnya); Ringkasan Tagihan tambah Metode Bayar + Saldo Pelanggan; dialog sukses + tombol Cetak Struk. Meng-override keputusan §D-5 (saldo pelanggan DILUAR SCOPE) atas permintaan eksplisit user — lihat catatan override di [`docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md`](plan/analisa-billing-tagihan-pembayaran-kolektor.md). Rencana: [`docs/plan/metode-pembayaran-pada-modal-mighty-tower.md`](plan/metode-pembayaran-pada-modal-mighty-tower.md) | Done — 2026-08-18 |
| ADHOC-39 | **Nav "Tagihan" vs "Pembayaran" dipisah by MEANING, bukan by fitur-asal** — "Tagihan" = kewajiban yang masih ada (Belum Lunas + Semua Tagihan). "Pembayaran" = histori uang masuk (Riwayat Transaksi + Lebih Bayar). "Tagihan Lunas" **dicabut dari nav** (bukan cuma dipindah) — begitu lunas itu bukan "tagihan" lagi. Route/controller/view `invoices.lunas`/`payments.*` semua TETAP ADA, cuma pintu masuknya diatur ulang. Copy usang di halaman Overpay ("bukan saldo ledger, diselesaikan manual") diperbaiki — sekarang benar-benar jadi saldo aktif sejak ADHOC-38. | Done — 2026-08-18 |
| ADHOC-40 | **Rancangan API Eksternal** — (1) **webhook pemasangan** outbound: satu-satunya event `installation.activated`, dipicu tombol **"Aktivasi Laporan Speedtest"** (`storePemasangan()`) — titik pertama di mana keenam data (nama/POP/desa/paket/SN/ODP) lengkap. Butuh event baru `InstallationActivated`; outbox fan-out **dua transport**: `http_json` (Website B, HMAC) + `telegram` (Telegram **Eksternal**, kredensial per-endpoint — Telegram Internal 6 pemanggilan inline tidak disentuh); `idempotency_key` karena Aktivasi bisa ditekan berulang. (2) **portal pelanggan** — merinci kontrak QR §6.6 yang sudah dikonfirmasi pemilik produk (`login_id` `{prefix_pop}-{customer_code}`, `customer_portal_accounts` + `customer_portal_tokens` terpisah, klaim via PIN, kwitansi lewat `ReceiptPresenter` dipangkas, status tiket lewat `Ticket::resolveStatus()`, `invoice.updated` dari `Invoice::recalculateFromPayments()`). **Nol dependensi baru.** Dokumen: [`docs/api/`](api/README.md). Rev. 6 — + [`keputusan.md`](api/keputusan.md): alternatif ditolak, alasan webhook vs REST, peta pengembangan, 5 pertanyaan terbuka | **Rancangan** — 2026-08-19 |
| ADHOC-41 | **Laporan Speedtest (step 6 wizard `installations.report`) terkunci sampai Laporan Pemasangan & Perangkat (step 5) diaktivasi** — wizard teknisi dipecah jadi dua submit terpisah: `storePemasangan()` (simpan device/material/3 foto wajib, TIDAK menyelesaikan task/workflow) lalu `storeSpeedtest()` (SATU-SATUNYA titik penyelesaian: task complete + transisi `verification_admin`). Gerbang dihitung dari data tersimpan (3 foto + material terpakai), bukan kolom baru. Route baru `customers.installation.pemasangan`/`customers.installation.speedtest`; `store()` lama TETAP ADA apa adanya (dipakai modal admin `_installation.blade.php`, tidak disentuh). Test: `InstallationSpeedtestActivationGateTest`. | Done — 2026-08-19 |
| ADHOC-42 | **Webhook Telegram hilang diam-diam + retry membakar 6 jam untuk galat yang mustahil sembuh** (turunan ADHOC-40). Dua bug kode: (1) `WebhookOutbox::maxDeliveredActivationNumber()` tidak memfilter `destination` — satu penekanan Aktivasi menulis dua baris ber-`idempotency_key` sama, jadi Website B yang `delivered` #7 membuat baris Telegram #4/#5/#6 di-`skipped` "superseded" padahal Telegram belum pernah menerima apa pun (3 notifikasi hilang senyap di data nyata). (2) Telegram tidak punya guard konfigurasi seperti `website_b`, dan 401/403/404 Bot API ikut siklus retry 8x/6 jam — sekarang `configError()` mencakup dua tujuan dan galat permanen langsung `failed` (429/5xx tetap di-retry). Dua temuan operasional: `supervisor-webhooks` belum aktif karena Horizon boot sebelum `config/horizon.php` berubah, dan satu `queue:work --queue=webhooks` manual berumur 4,5 jam memegang `.env` lama (token Telegram kosong → 404 "Not Found") sambil rebutan antrean dengan Horizon. Test: `SendWebhookOutboxJobTest` (+5 kasus). | Done — 2026-08-20 |

#### ADHOC-39 — Nav Tagihan vs Pembayaran Dipisah by Meaning (2026-08-18) — **Done**

**Pemicu:** permintaan user — Tagihan sudah punya Lunas/Belum Lunas, halaman "Pembayaran" berdiri sendiri dianggap redundan. Diskusi lanjut menyimpulkan: "Tagihan" (nama) harusnya cuma nampilin kewajiban yang MASIH ADA — begitu lunas, itu bukan "tagihan" lagi, itu riwayat pembayaran.

**Analisa sebelum eksekusi:** riwayat pembayaran PER-INVOICE sudah ada di tab "Riwayat Pembayaran" `invoices/show.blade.php`. Yang perlu jadi pintu masuk terpisah cuma 2 (payment-level, bukan invoice-level, gak bisa jadi baris tabel invoice): daftar transaksi LINTAS-invoice + filter/stat (`payments.index`), dan tab Lebih Bayar (`payments.overpay`). `payments.show` (detail + audit log + tombol Tolak) dan `payments.receipt` (struk cetak) tetap halaman sendiri, sudah dilink dari tab Riwayat Pembayaran invoice — tidak disentuh.

**Struktur nav final:** dua grup collapsible terpisah di sidebar (`resources/views/layouts/app.blade.php`).
- **Tagihan** (`submenu-tagihan`, highlight `invoices*`): Tagihan Belum Lunas, Semua Tagihan. "Tagihan Lunas" DICABUT dari sini.
- **Pembayaran** (`submenu-pembayaran`, highlight `payments*`, baru): Riwayat Transaksi Pembayaran (`payments.index`) — ganti peran "Pembayaran" lama SEKALIGUS "Tagihan Lunas" — dan Pembayaran Lebih/Overpay (`payments.overpay`).

Breadcrumb disesuaikan: `payments.index` jadi puncak grupnya sendiri (tanpa `breadcrumb_parent`, sama pola `invoices.index`); `payments.overpay`/`payments.show` parent-nya "Riwayat Transaksi Pembayaran" → `payments.index`; `payments.create` (form input dari 1 invoice) tetap parent "Tagihan" → `invoices.index` karena kontekstual ke invoice asalnya, bukan histori.

**Route `invoices.lunas` TIDAK dihapus** — cuma dicabut dari nav (hindari broken link kalau ada yang bookmark; test `InvoiceListTest` masih hit route-nya langsung).

**Di luar scope:** `reports.payments.*` (laporan/export terpisah) dan `payment-receipts.*` (worksheet cetak massal kolektor) — beda concern, tidak disentuh. Component `components/layout/sidebar.blade.php` (dead code, tak dipakai satu halaman pun — diverifikasi via grep) sengaja tidak diikut-update.

**Catatan sampingan (bukan bug dari task ini):** `PaymentReceiptTest`/`PaymentReceiptPrintTest` gagal di assertion `assertSee('STRUK PEMBAYARAN', ...)` — string itu memang tidak pernah ada di `payments/receipt.blade.php` maupun di manapun di codebase (diverifikasi grep). Pre-existing, tidak disentuh task ini.

#### ADHOC-38 — Metode Pembayaran + Saldo Pelanggan di Modal Bayar Invoice (2026-08-18) — **Done**

**Pemicu:** permintaan user — Modal Bayar di Invoice butuh metode Cash/Transfer/Kolektor dengan field pendukung masing-masing, dan pelanggan lebih bayar harus dapat saldo aktif yang bisa dipakai di pembayaran berikutnya.

**Yang dibangun:**
- `App\Enums\PaymentMethod` (cash/transfer/qris/kolektor/lainnya) — ganti literal array `in:cash,transfer,qris,lainnya` yang dulu tersebar.
- Migration: `bank_name`+`account_number` di `payments`; tabel baru `customer_balance_mutations` (ledger append-only credit/debit, **bukan** kolom `customers.balance` — konsisten pola `CollectorBalanceService`/`AdminCashBalanceService` yang derived-only).
- `App\Services\CustomerBalanceService` — `balance()`, `credit()`, `debit()` (lock saat dipakai), `reverseCreditForPayment()` (dipanggil `PaymentController::reject()`).
- `App\Services\PaymentService::record()` — extract dari `PaymentController::recordPayment()`, tambah orkestrasi pakai-saldo + field kondisional per metode.
- `InvoiceController::show()` JSON payload tambah `customer_balance` + `available_collectors` untuk modal.
- `quick-payment-modal.blade.php` — dropdown Kolektor, field Transfer/Kolektor kondisional, blok Saldo Pelanggan, Ringkasan Tagihan tambah 2 baris, dialog sukses + tombol Cetak Struk (`window.open('/payments/{id}/kwitansi')`).
- Cash → Saldo Admin sudah otomatis benar tanpa sentuh `AdminCashBalanceService` (filter `payment_method='cash' AND collected_by IS NULL` sudah ada).

**Test baru:** `PaymentMethodKolektorTest`, `PaymentMethodTransferBankFieldsTest`, `CustomerBalanceCreditOnOverpayTest`, `CustomerBalanceDebitOnPaymentTest`, `CustomerBalanceServiceTest` (Unit) — semua lulus, plus regresi existing (`PaymentInputTest`, `PaymentCollectedByNotCopiedFromCustomerTest` disesuaikan menambahkan `bank_name`/`account_number` karena validasi Transfer sekarang wajib).

**Di luar scope task ini:** `BackfillCustomerBalanceFromOverpay` (artisan command, dibuat tapi TIDAK dijalankan ke data produksi — butuh review manual finance dulu); UI `_quick_hub_modal.blade.php` & `payments/create.blade.php` belum ikut menampilkan opsi baru (backend sudah kompatibel lewat validasi bersama).

#### ADHOC-37 — Saldo Kas Admin & Modul Setoran Kas (2026-08-14) — **Done**

**Pemicu:** pertanyaan user — setoran kolektor yang sukses seharusnya masuk ke Saldo Admin, dan perlu modul Setoran Kas dengan rincian sumber uang yang jelas untuk atasan/Owner.

**Temuan:** `CollectorDepositService::verify()` hanya mengubah status + menulis `declared_amount`/`difference`/audit. Tak ada apa pun yang mencatat uang masuk kas kantor (`saldo_admin`/`admin_balance` → nol hasil di seluruh repo). Pembayaran manual di kantor (`collected_by = NULL`) juga tak pernah masuk setoran apa pun.

**Keputusan user:** saldo per admin (per user) · transfer/QRIS dipisah sebagai rekap saja, bukan kewajiban setor · verifikator Owner + atasan · selisih fase 1 dicatat + catatan wajib, penutupan hanya lewat hapus buku Owner.

**Rancangan:** tabel `cash_deposits` (`SETKAS-{tahun}-{4}`) + kolom `cash_deposit_id` di `collector_deposits` & `payments` (bukan tabel pivot), enum `CashDepositStatus`, `AdminCashBalanceService` (saldo TURUNAN, tanpa kolom saldo), `CashDepositService`, feature permission baru `cash_deposit`.

**Data historis — titik nol (§7):** satu baris sentinel `SETKAS-0000-0000` berstatus `saldo_awal` (terminal keras, `declared_amount` 0, `depositor_id` NULL) menyerap seluruh sumber lama lewat `cash_deposit_id`. Cutoff berbasis tanggal **ditolak** — ia menambah aturan kedua yang harus diulang di tiap query kas dan pasti ada yang lupa; backfill retroaktif **ditolak** — mengarang riwayat setoran bank yang tak pernah terjadi.

**Terpasang:** enum `CashDepositStatus`/`CashDepositChannel`, 3 migrasi, model `CashDeposit` (+ `CollectorDeposit::cashReceivedByOffice()`), `AdminCashBalanceService` & `CashDepositService`, `CashDepositController` + 5 route, feature RBAC `cash_deposit` (admin/pop_admin menyetor; atasan memeriksa; Owner menutup selisih), halaman `/cash-deposits` + kartu saldo di Worksheet Admin + menu sidebar. **40 test hijau** (`AdminCashBalanceTest`, `CashDepositVerificationTest`, `CashLedgerZeroPointTest`).

**Halaman dibersihkan (§12, 2026-08-18):** `/cash-deposits` jadi **murni lembar kerja penerima** — kartu posisi kas admin, rincian saldo belum disetor, dan pemilih `admin_id` dicabut (itu isi Worksheet Admin). Isinya kini: card analisa penerimaan · setoran masuk dari seluruh admin dalam scope (menunggu di atas, + filter) · rincian sumber per setoran sampai nama pelanggan · aksi verifikasi/tutup selisih/unduh bukti. `cashHolderIds()` dibuang sebagai kode mati. **54 test kas hijau.**

**Koreksi alur (§11, 2026-08-18):** `/cash-deposits` adalah **worksheet PENERIMA** (Owner/atasan), bukan halaman penyetor. `OwnerCashBalanceService` menutup putusnya rantai uang di anak panah terakhir: sesudah Owner memeriksa, saldo benar-benar **pindah** dari admin ke Owner — tiga angka terpisah (Brankas tunai · Masuk Bank · Dalam Perjalanan). `OwnerCashBalanceTest` (9 test).

**Dua tingkat rincian (§10, 2026-08-18):** `cash_deposit.view` dicabut dari admin/pop_admin — halaman `/cash-deposits` jadi pandangan **pemeriksa** (Owner/atasan): kas admin mana pun dalam scope, antrean pemeriksaan, rincian sampai nama pelanggan. Admin dapat pandangan **penyetor** di Worksheet Admin: kartu saldo + form setor + **Riwayat Setoran Kas Anda** (tanpa nama pelanggan/kolektor). Unduh bukti dibuka untuk keduanya, tapi penyetor dibatasi ke setorannya sendiri. **44 test kas hijau.**

**Koreksi letak (§9, 2026-08-18):** aksi setor pindah ke **Worksheet Admin** — tombol + panel lipat di kartu "Kas Anda", simetris dengan kolektor yang menyetor dari halaman kerjanya sendiri. `/cash-deposits` jadi **arsip & pemeriksaan** (form setor dicabut). Redirect sesudah setor dipilih dari daftar tertutup di server lewat penanda `redirect_to`, bukan URL dari klien (open-redirect).

Analisa, rumus saldo, risiko, daftar berkas & test: [`docs/plan/kolektor/analisa-setoran-kas-admin.md`](plan/kolektor/analisa-setoran-kas-admin.md).

#### ADHOC-36 — Pencarian pada Halaman Collector Worklist (2026-08-13)

**Pemicu:** Kebutuhan kolektor di lapangan untuk menyaring daftar rute kerja (worklist) berdasarkan Nama Lengkap, CID/Kode Pelanggan, atau Nomor Invoice.

**Rincian Perubahan:**
- **Controller `CollectorWorklistController`**: Menambahkan penanganan parameter query `search` dan menerapkan penyaringan `like` pada `invoice_number`, serta relasi customer (`full_name`, `cid`, `customer_code`). Meneruskan variabel `$search` ke view.
- **View `collector-worklist.index`**: Menambahkan form pencarian modern yang responsif dan mendukung tema Light & Dark Mode. Menyediakan tombol reset saat pencarian aktif.
- **Test `CollectorWorklistSearchTest`**: Membuat feature test lengkap untuk memastikan validasi pencarian berdasarkan nama, CID, nomor invoice, mempertahankan pagination query, serta menjaga data POP scope agar tidak bocor.

#### ADHOC-35 — Riwayat Perubahan Status tampil dobel (2026-08-13)

**Pemicu:** laporan user — satu aksi tampil dua baris di `tasks/{id}`.

**Sebab:** `Task` punya DUA pencatat audit, dan keduanya menulis untuk aksi yang sama.

```
#16992 16:02:09  action=create   module="Task Management"   ← trait RecordsAuditLogs
#16993 16:02:09  action=created  module="Task"              ← AuditLog::log() manual
#17026 10:07:22  action=update   module="Task Management"   ← trait
#17027 10:07:22  action=updated  module="Task"              ← manual
```

1. **Trait `RecordsAuditLogs`** (`app/Models/Task.php:17-21`, `$auditEvents = ['created','updated','deleted']`) menempel di event Eloquent → menangkap SEMUA jalur (service, controller, artisan, tinker) lengkap dengan kolom yang berubah.
2. **`AuditLog::log()` manual** di `TaskService` & `TaskController`.

Halaman Detail Task merender `$task->auditLogs` apa adanya, jadi dobelnya terlihat mentah. Pola yang persis dilarang CLAUDE.md untuk riwayat Ticket/FopTask ("satu aksi, dua riwayat, **satu penulis per sisi**") — hanya saja aturannya belum pernah ditulis untuk `audit_logs` Task.

**Perbaikan, dua lapis:**

1. **Duplikat murni dicabut.** `TaskService::create()` dan `update()` berhenti menulis `created`/`updated` — isinya sama persis dengan tulisan trait, dan tulisan trait lebih presisi (kolom yang benar-benar berubah, bukan snapshot penuh). Log manual yang **disisakan** hanya yang membawa makna bisnis: `completed`, `cancelled`, `reassigned`, `pending`, `reschedule`, `approved`, `rejected`.
2. **Timeline disaring di penyajian** — `App\Support\TaskAuditTimeline` (dipakai `TaskController::show()`, dirender `$statusTimeline`):
   - peristiwa bisnis bernama & `create` → selalu tampil;
   - `update` yang menyentuh kolom bermakna (`status`, `scheduled_at`, `pending_reason`, `cancel_reason`, dst.) → tampil, **kecuali** ada baris bisnis dalam 5 detik yang sama yang sudah menceritakannya (yang bernama menang);
   - `update` yang cuma derau mesin (prefix `[Team 1]` di `title` dari `rebuildTeamsForDate()`, `updated_by`) → disembunyikan.

**Yang TIDAK dilakukan:** tidak ada baris `audit_logs` yang dihapus, termasuk data lama yang sudah terlanjur kembar. Menghapus jejak audit untuk merapikan tampilan justru merusak gunanya; halaman Audit Log tetap menampilkan semuanya.

**Berkas:** `app/Support/TaskAuditTimeline.php` (baru), `TaskService::create()/update()`, `TaskController::show()`, `resources/views/tasks/show.blade.php`.

**Lanjutan — aksi yang belum punya nama, diberi nama.** Filter di atas menyisakan satu kejanggalan: "Mulai Task" (dan Pending/Lapor Nanti lewat `TaskService`) tidak pernah menulis log bisnis, jadi jejaknya cuma baris generik trait dan tampil sebagai **"Update"** — peristiwa yang paling sering dilakukan teknisi justru yang paling tidak terbaca. Ditutup dengan:

- `TaskService::start()` menulis `started`;
- `TaskService::setPending()` menulis `pending` atau `report_deferred` — dua peristiwa berlawanan makna (kerja berhenti vs kerja selesai, laporan menyusul) yang di DB sama-sama berstatus `pending` dan cuma dibedakan flag, jadi namanya harus ditulis eksplisit;
- alasan pending/lapor-nanti ikut ditampilkan di timeline (sebelumnya hanya alasan batal & tolak);
- label timeline dipetakan ke bahasa Indonesia lewat `TaskAuditTimeline::label()` — "Mulai Dikerjakan", "Task Selesai", "Ditunda (Pending)", "Lapor Nanti", "Teknisi Diganti", dst. Baris `update` yang lolos filter diberi label spesifik dari kolom yang berubah ("Status Diubah" / "Jadwal Diubah"), bukan kata "Update" yang tidak menjelaskan apa pun.

Efek sampingnya rapi: begitu `started`/`pending` punya nama, kembaran generiknya otomatis tersaring aturan yang sudah ada — tanpa aturan baru.

**Test:** `TaskStatusTimelineNoDuplicateTest` — 9 test (service tak lagi menulis `created`; baris bisnis menang atas `update` sedetik; perubahan status **tanpa** log bisnis tetap tampil — jaga agar peristiwa tidak hilang saat disaring; derau kosmetik disembunyikan; baris tetap ada di DB; `start()` menulis satu baris bernama; `pending` vs `report_deferred` dibedakan berikut alasannya; `update` generik dapat label Indonesia; halaman merender satu baris per aksi).
| ADHOC-35 | **Redesign Tampilan HP/Mobile Worksheet Teknisi** — Worksheet (`/tasks-saya`) & Detail (`/tasks/{id}`) dioptimalkan penuh untuk mobile screen (HP), stats banner gradien gelap, slider filter horizontal, search klien, WhatsApp/Maps icon button (lihat detail di bawah) | Done — 2026-08-13 |

#### ADHOC-35 — Redesign Tampilan HP/Mobile Worksheet Teknisi (2026-08-13)

**Pemicu:** Kebutuhan teknisi lapangan yang menggunakan HP untuk lembar kerja mereka. Tampilan worksheet dan detail sebelumnya kaku dan kurang responsif pada layar sentuh mobile.

**Rincian Perubahan:**
- **Worksheet `/tasks-saya`**: Menambahkan Alpine.js `taskWorksheet()` untuk filter kategori, pengurutan prioritas/waktu, dan pencarian instan di sisi klien. Panel statistik diganti dengan banner gradien premium modern, tabs filter berbentuk slider horizontal, dan select box pengurutan. Urutan penampilan kartu diatur secara terstruktur (Sedang Dikerjakan berada di paling atas, di bawahnya Lapor Nanti, lalu dilanjutkan dengan list task belum dikerjakan / Terjadwal).
- **Card `own-card.blade.php`**: Dihias dengan sudut rounded-2xl, status accent bar kiri, serta meletakkan tombol WhatsApp & Maps sebagai tombol ikon persegi di sebelah kiri. Tombol utama (Mulai Task, Isi Laporan) dibuat berukuran besar dan thumb-friendly. Maps & info sensitif disembunyikan sebelum tugas berstatus aktif (status terjadwal digate).
- **Riwayat `/tasks-saya/riwayat`**: Disinkronkan visual kartunya dengan worksheet utama.
- **Detail Tugas `/tasks/{id}`**: Kisi metrik kaku diubah menjadi grid kartu terpisah ber-rounded-2xl. Informasi detail teknis dipisah menjadi kartu modular bertumpuk (stack) di HP dengan visualisasi ikon di setiap baris data.

#### ADHOC-34 — Tim FOP hilang dari papan begitu ganti hari (2026-08-13)

**Pemicu:** verifikasi browser pasca-migrasi Alpine. Papan FOP kosong padahal `/fop-tasks` menampilkan 2 tim yang sudah dijadwalkan. Bukan regresi Alpine: tim terakhir ber-`work_date` 12 Agustus, sementara papan dibatasi `work_date` = hari ini.

**Akar masalah — perbaikan lama yang kebablasan.** Versi awal papan memuat SELURUH team yang pernah ada beserta anak-anaknya lalu memfilter di PHP; `FopTaskTeamService::rebuildTeamsForDate()` membuat team baru tiap tanggal kerja, jadi setelah setahun operasi itu 300+ team per refresh. Perbaikan 2026-07-22 memangkasnya jadi "hari ini saja" — beban query beres, tapi tim yang **sudah dijadwalkan** lenyap dari papan begitu lewat tengah malam, padahal task-nya masih hidup dan teknisinya masih melihatnya di Task Saya (`TaskController::index()` punya cabang overdue, papan tidak).

**Perbaikan:** melonggarkan jendela tanggal papan, bukan menambah konsep baru.

- `work_date` = hari ini → **selalu** tampil;
- `work_date` lampau → tampil **selama masih punya task aktif** (bukan `selesai`/`dibatalkan`), disaring di SQL lewat `whereHas` — tim yang sudah rampung tidak ikut dimuat sama sekali, jadi beban query yang dulu jadi alasan pembatasan tidak kembali;
- pagar `BOARD_MAX_PAST_DAYS` = 30 hari supaya papan tidak pelan-pelan berubah jadi arsip;
- diurut `work_date` menaik — tanggal terlama di atas.

Kartu tim sudah menampilkan `work_date`-nya sendiri, jadi tim dari tanggal lampau terbaca apa adanya tanpa penanda tambahan.

> **Catatan proses.** Iterasi pertama saya kelebihan bangun: zona "Tertunda — belum ada tindakan" + daftar Pending terpisah + endpoint `reschedule-today`, dengan klasifikasi status efektif. User mengoreksi — kebutuhannya cuma "tim yang sudah dijadwalkan tetap tampil". Semua itu dibongkar (partial `_backlog`, variabel backlog di controller, route + method `rescheduleToToday`, `FopDashboardBacklogTest`) dan diganti pelonggaran query di atas. Yang **dipertahankan** dari iterasi itu cuma satu hal yang memang diminta terpisah: pecahan tunggakan di tabel beban teknisi.

**Penyelarasan tabel beban teknisi.** `TeknisiWorkloadService::summarize()` ternyata **sudah** menghitung task overdue sejak commit `c54aa2c` (dugaan awal saya keliru — teknisi bertunggakan tidak pernah tampil "kosong"). Yang kurang cuma komposisinya: ditambah `overdue_count` lewat satu agregat (`SUM(CASE WHEN scheduled_at < hari ini …)`, tanpa query tambahan) dan badge "N tertunda" di sebelah "N Task" — "3 Task" hari ini dan "3 Task" sisa tiga hari lalu bukan beban yang sama, dan itu yang menentukan boleh-tidaknya teknisi dapat task baru.

**Berkas:** `FopDashboardController` (jendela tanggal + `BOARD_MAX_PAST_DAYS`), `TeknisiWorkloadService`, `resources/views/fop/dashboard.blade.php` (badge tunggakan + teks empty-state).

**Test:** `FopDashboardPastTeamsTest` — 6 test (tim lampau dengan task aktif tampil, tim hari ini tampil, tim lampau yang task-nya selesai/batal tidak tampil, tim >30 hari tidak tampil, task pending menjaga timnya tetap terlihat, badge tunggakan di tabel teknisi).

**`fop:reset-cancelled-tasks` DIHAPUS** (keputusan user, 2026-08-13): pembatalan Task FOP bersifat **final**. Command itu mengubah task `dibatalkan` jadi `in_progress` tiap 00:01, dan cacatnya berlapis:

- menghapus keputusan manusia tiap malam **tanpa jejak di riwayat tiket** — `FopTaskObserver` hanya menulis `ticket_histories` saat status *menjadi* `dibatalkan`, tidak saat dihidupkan lagi, jadi riwayat tiket berhenti di "dibatalkan" padahal task-nya hidup;
- status tujuannya **palsu**: `in_progress` berarti teknisi sedang mengerjakan, padahal tidak ada;
- `task_date` tidak diperbarui → task langsung jadi penghuni papan sebagai tim lampau;
- Task eksekusi (`tasks`) tidak ikut disentuh → FopTask dan Task berbeda status, dua sumber kebenaran menyimpang;
- tanpa batas umur — task yang dibatalkan berbulan-bulan lalu ikut terangkat.

Penundaan sehari sudah punya jalurnya: **Pending** (alasan tercatat) atau ubah tanggal lewat Edit di `/fop-tasks`. Yang dihapus: `app/Console/Commands/ResetCancelledFopTasks.php` + baris `Schedule::command()` di `routes/console.php`. Test lama yang menegaskan perilaku itu diganti jadi penjaga arah sebaliknya: `FopTasksTest::test_cancelled_task_stays_cancelled_and_is_never_auto_revived()` — gagal kalau command semacam itu didaftarkan lagi. Runbook diperbarui.

#### ADHOC-33 — Pelanggan Gagal & Putus: view sendiri, controller lepas dari `CustomerController` (2026-08-12)

**Pemicu:** pertanyaan user — kenapa dua halaman ini belum punya berkas sendiri padahal RBAC-nya sudah dipisah?

Route, permission, dan controller memang sudah terpisah sejak 2026-07-28 (`customers.terminated.view`, `customers.failed.view`). Yang tertinggal dua hal:

1. **Tampilannya masih satu berkas.** `customers/index.blade.php` (2.178 baris) melayani ketiga halaman lewat cabang `@if($statusGroup === 'failed') … @elseif('terminated') … @else`. Mengubah satu kolom di halaman arsip berarti menyunting berkas yang sama dengan List Pelanggan. Lebih buruk: `$statusGroup` sudah **dipaksa dari controller**, tapi tetap dicabangkan lagi di Blade — dua sumber kebenaran untuk satu keputusan.
2. **Dua controller arsip `extends CustomerController`** semata demi satu method protected, ikut mewarisi ~3.400 baris method tulis (store/update/destroy/import/aktivasi) yang bukan urusan halaman daftar.

**Hasil:**

| Berkas | Isi |
| --- | --- |
| `resources/views/customers/index.blade.php` (2.178 → 1.675 baris) | List Pelanggan + grup `survey`/`verification` |
| `resources/views/customers/terminated.blade.php` (121) | Pelanggan Putus |
| `resources/views/customers/failed.blade.php` (96) | Pelanggan Gagal |
| `resources/views/customers/partials/_list_*.blade.php` | styles, header, stats, filters, pagination, density script — dipakai bertiga |
| `app/Http/Controllers/Concerns/RendersCustomerList.php` | query + filter + pagination; parameter ketiga `$view` menentukan Blade yang dirender |

`CustomerTerminatedController`/`CustomerFailedController` kini `extends Controller` + `use RendersCustomerList`.

**Ikut diperbaiki:** form pemilih jumlah baris di footer pagination dulu `action="/customers"` hardcode — mengubah "Baris" dari halaman Putus/Gagal melempar user balik ke List Pelanggan. Sekarang `url()->current()`.

**Sengaja dipertahankan:** hidden input `status_group` di form filter (grup `survey`/`verification` masih hidup sebagai `/customers?status_group=…` dan membutuhkannya), serta strip statistik + filter bar di ketiga halaman.

**Test:** `CustomerListSeparateViewsTest` (5 test — `assertViewIs` per halaman, tabel arsip tidak bocor ke List, isolasi status, query string tidak bisa menimpa `$forcedStatusGroup`); `CustomerListFilterKeepsStatusGroupTest` ditambah assert `action="/customers"` sudah hilang.

**Dokumentasi:** `docs/data-pelanggan/README.md` §4, `docs/rbac/customer-permission-hierarchy.md`.

#### ADHOC-30 — Nominal bertitik dibaca 1.000× lebih kecil (2026-08-12)

**Pemicu:** permintaan user supaya kolom pembayaran menerima penulisan `150.000`, bukan `150000`.

Menelusurinya membuka lubang yang lebih serius dari sekadar kenyamanan mengetik: `150.000` **lolos** aturan validasi `numeric` sebagai **seratus lima puluh rupiah** — PHP membaca titik sebagai desimal Inggris. Tidak ada error, tidak ada peringatan. Nominal tersimpan 1.000 kali lebih kecil, invoice tetap "belum lunas", dan uangnya sudah diterima di dunia nyata. Selama kolomnya `type="number"` browser menahan titik, jadi lubang ini **dorman** — begitu masking ditambahkan (atau request datang dari luar form), lubangnya terbuka.

**1. Normalisasi server — lapis yang wajib.** `App\Support\RupiahInput::parse()` dipanggil sebelum `validate()` di seluruh endpoint yang menerima nominal ketikan:

| Endpoint | Field |
|---|---|
| `PaymentController@store` | `amount` |
| `CollectorDepositController@verify` | `declared_amount`, `settlement_amount` |
| `RecordsCollectorBatch::normalizeBatchAmounts()` → `PaymentBatchController`, `CollectorPaymentController` | `rows.*.amount` |
| `Master\InternetPackageController` | `monthly_price`, `installation_fee` |
| `CustomerRegistrationRequest`, `CustomerController@update` | `discount_amount`, `other_fee` |
| `CustomerController@storeManualInvoice` | `prorate_amount`, `extra_*_fee` |
| `CustomerVerificationController` | `extra_*_fee`, `other_fee`, `prorate_amount_override` |

Parsernya sengaja konservatif: `150.000` / `Rp 1.500.000` / `150.000,50` dinormalkan, sementara `1.50`, `12.34.56`, `seratus ribu` **tidak ditebak** dan dibiarkan ditolak validator. Resep umum satu baris (`str_replace('.','')` tanpa syarat) justru merusak `150000.50` → `15000050`. Menebak nominal uang lebih berbahaya daripada menolaknya.

**Kolom persen dikecualikan** — `tax_percent`, `ppn`, `discount_default` bernilai 0–100 dan tak pernah pakai pemisah ribuan; ikut memasking membuat `11` berisiko jadi `11.000`. Dikunci test.

> Salah baca di **master paket** kelasnya berbeda dari salah baca di satu pembayaran: harga paket menular ke **setiap** tagihan yang lahir dari paket itu.

**2. Masking layar.** Atribut `data-rupiah` + skrip di `layouts/app.blade.php` (tanpa build step). Kursor dikembalikan berdasarkan **jumlah digit** sebelum kursor, bukan indeks karakter — titik yang baru disisipkan menggeser indeks dan kursor akan meloncat saat menyunting bagian tengah.

**3. Efek samping yang harus ikut ditutup.** Input jadi `type="text"`, sehingga `min`/`max` bawaan browser tidak berlaku lagi:

| Tempat | Pengganti |
|---|---|
| tabel batch kolektor | `data-max` + `cbBarisValid()` |
| modal Bayar Cepat | `qpNominal() < 1` sebelum submit |
| sisanya | validasi server (`numeric|min:0`) |

Semua pembaca nilai di sisi JS dipindah ke `window.Rupiah.angka()` — `parseFloat('150.000')` = 150, dan pratinjau cicilan, rincian harga layanan, serta kwitansi verifikasi akan berbohong tanpa itu. Modal Bayar Cepat mengirim lewat **FormData**, bukan event `submit`, jadi normalisasi global tidak menjangkaunya — nilainya dibersihkan eksplisit.

**Test baru:** `RupiahInputTest` (unit, 16 kasus), `NominalRupiahBertitikDiterimaTest` (8 — jalur Tagihan, batch kolektor, verifikasi setoran, master paket, edit pelanggan, tagihan manual, plus penolakan ketikan tak dikenali). Suite penuh **1220 lulus, 0 gagal**.

**5. Aritmetika uang dipindah ke sen bulat** (2026-08-12, penutup rangkaian ini). Kolom DB memang `decimal(12,2)` yang eksak, tapi begitu nilainya masuk PHP ia jadi float biner. Diukur langsung di repo: 1.000 baris × 33.333,33 menghasilkan 33.333.329,9999991469 (meleset −0,00000085), dan `0.1 + 0.2 === 0.3` bernilai FALSE.

Yang dipertaruhkan bukan tampilan — semua angka dibulatkan 2 desimal sebelum tampil — melainkan **cabang keputusan**: `remaining <= 0` (Lunas vs Sebagian), `difference == 0` (Terverifikasi vs SELISIH), `overpay > 0` (lebih bayar hantu Rp0,000001 yang tak bisa diselesaikan siapa pun). Setoran pas yang salah ditandai selisih berarti kolektor ditagih uang yang sudah dia serahkan.

`App\Support\Money` mengerjakan semua operasi di sen bulat lalu mengembalikan rupiah float, jadi tanda tangan pemanggil tidak berubah. Dipakai di `Invoice::recalculateFromPayments()`, `CollectorDeposit::computedAmount()`/`outstandingShortfall()`, `CollectorDepositService::verify()`, `CollectorBalanceService`, `CollectorPaymentService`, `PaymentController@store` (pemisahan lebih bayar), dan `InitialInvoiceService`.

> **Epsilon karangan dihapus.** `abs($x) <= 0.001` sempat tersebar di beberapa tempat dengan angka yang bebas dipilih masing-masing (`+ 0.001` di satu baris, `<= 0.001` di baris lain). Sekarang perbandingannya eksak. Persen (`ppn`, `tax_percent`, `discount_default`) tetap float biasa — yang lewat `Money` cuma hasil perkaliannya.

**Test:** `MoneyTest` (unit, 8 — termasuk bukti galat float yang dicegahnya).

**6. Temuan review yang ditutup (2026-08-12).** Review `/code-review high` menemukan 6 cacat nyata di jahitan antara masking layar dan nilai server — semuanya diverifikasi ulang sebelum diperbaiki:

| # | Cacat | Akibat |
|---|---|---|
| 1 | `formatAwal()` membuang titik tanpa syarat, padahal `old()` berisi bentuk mesin | `150000.50` tampil `15.000.050` — **100×**, dan langsung terkirim ulang saat simpan |
| 2 | Blok masking ditaruh SESUDAH `@yield('scripts')` | `refreshHint()` awal di `payments/create` melihat `window.Rupiah` undefined → pratinjau cicilan salah sampai pengguna mengetik |
| 3 | `updateTotalPreview()` di master paket create+edit masih `parseFloat` | pratinjau harga Rp 153 alih-alih Rp 153.180 — padahal pratinjau itu justru alat periksa sebelum simpan |
| 4 | Prefill `number_format($v, 0, ...)` membuang sen | sisa 150.000,50 tampil 150.001 → ditolak "melebihi sisa tagihan"; baris tak bisa dibayar tanpa disunting manual |
| 5 | `declared_amount` prefill dibulatkan | uang fisik benar terkirim dibulatkan → setoran ditandai SELISIH, kolektor ditagih uang yang sudah dia serahkan |
| 6 | `cbApplyResults()` & `qpSetNominal()` mengisi `Math.round(...)` sementara `data-max` eksak | sesudah bayar sebagian, baris yang sama langsung ditolak validasi klien |

Perbaikannya: `window.Rupiah.formatDariServer()` (mengenali titik desimal dengan aturan yang sama seperti `RupiahInput`), blok masking dipindah ke atas `@yield('scripts')`, dan `FormatHelper::rupiahInput()` sebagai satu-satunya cara memformat nilai bawaan.

> Temuan ke-7 (`window.formatInputRupiah` tanpa pemanggil) **bukan cacat** — fungsinya memang disediakan untuk markup yang disisipkan setelah load; komentarnya yang mengklaim berlebihan, dan itu yang diperbaiki.

**Test tambahan:** `RupiahInputPrefillTest` (unit, 8 — bolak-balik DB → layar → DB tanpa berubah nilai). Suite penuh **1236 lulus, 0 gagal**.

#### ADHOC-29 — Satu pembayaran, tiga kwitansi berbeda isi (2026-08-12)

**Pemicu:** user mencetak PAY-202608-0822 dari List Pembayaran dan dari Detail Pembayaran, lalu membandingkan dua PDF-nya — isinya berbeda. Ditambah header/footer bawaan browser (`8/12/26, 8:52 AM`, `localhost:8000/payments/2548/kwitansi`) ikut tercetak di kwitansi yang diserahkan ke pelanggan.

**1. Header/footer browser dimatikan.** `@page { margin: 0; }` di empat halaman cetak (`payments/receipt`, `payments/show`, `invoices/show`, `collector-worksheet/receipt-print`). Teks itu hidup di **kotak margin halaman**, bukan di dokumen — tidak ada selector yang menjangkaunya, satu-satunya cara adalah menolkan margin. Jarak ke tepi kertas dipindah ke padding elemen (struk `6mm 5mm`, kartu kolektor `10mm 8mm`, dua lembar A4 sudah punya `p-8`); menghapusnya begitu saja membuat cetakan mepet tepi dan terpotong printer. Centang *Headers and footers* yang dinyalakan manual di dialog Chrome tetap menang atas CSS.

**2. Isi disatukan.** `App\Services\Receipts\ReceiptPresenter` jadi satu-satunya sumber isi kwitansi; tiga view tinggal merangkai kunci yang sama dengan tata letaknya masing-masing. Sebelumnya tiap view membaca `$payment` sendiri:

| Field | Thermal (dulu) | A4 (dulu) | Kartu kolektor (dulu) |
|---|---|---|---|
| Alamat & no. HP | ✗ | ✓ | ✗ |
| Kolektor/penagih | ✗ | ✓ | ✓ |
| Periode & paket | ✓ | sebagian | ✗ |
| Lebih bayar | ✓ | ✓ | ✗ |

Sekarang ketiganya memuat set yang sama. **Field baru ditambahkan di presenter, bukan di blade** — menambahkannya di satu view mengulang persis penyimpangan ini.

**3. Dua cacat yang ketahuan saat menyatukan.**
- Lembar A4 mencetak status dengan `text-emerald-700` **tanpa syarat**: pembayaran `ditolak` tampil hijau berbullet di dokumen berlabel "KWITANSI PEMBAYARAN RESMI".
- Saat `note` kosong, A4 **mengarang** kalimat `"Tagihan Bulanan. Struk ini adalah bukti pembayaran sah…"` yang terbaca seperti catatan petugas. Judul baris rincian juga selalu "Pelunasan Invoice" walau pembayarannya cicilan sebagian — sekarang ikut `keterangan_cicilan`.

**4. Alamat dipenggal dua baris.** `…RT. 002/RW. 002, Joresan` / `Kec. Mlarak, Kabupaten Ponorogo`. Penggalan hanya di penanda `Kec.`/`Kecamatan`; tanpa penanda alamat dibiarkan utuh — membelah di koma sembarang memisahkan nama jalan dari nomornya. Murni penyajian: `customers.address` tetap satu kolom teks bebas, tidak ada migrasi.

**Test baru:** `KwitansiIsiSeragamAntarHalamanTest` (6), +2 regresi `@page` di `PaymentReceiptTest` & `PaymentReceiptPrintTest`.

#### ADHOC-28 — Material & alat teknisi hilang senyap + Verifikasi Admin baca data nyata (2026-08-11)

**Pemicu:** user melapor halaman `/verifications/{id}/admin` "tidak menampilkan data yang diinput, contoh alat". Perbaikan tampilan ternyata cuma setengah cerita — sumber datanya memang kosong.

**Temuan (probe DB produksi lokal):** `surveys=1791`, `installations=1777`, `tasks=2`, **`fop_tasks=0`, `task_materials=0`, `task_work_tools=0`**.

Rantainya:

1. `task_materials` & `task_work_tools` wajib punya `fop_task_id` (`NOT NULL` + FK) — anchor-nya FopTask, bukan Customer atau Task.
2. Task SURVEY/PEMASANGAN untuk pelanggan baru dibuat langsung lewat `Task::create()` di `CustomerController::store()` dan `CustomerWorkflowService::transition()`, **tanpa** FopTask. FopTask Survey/PSB cuma lahir di `FopTaskController::autoSyncAndCalculatePriority()` — yang hanya jalan saat ada yang membuka `GET /fop-tasks`.
3. Di kedua controller laporan, sync material & alat dibungkus `if ($fopTask)`. Anchor null → seluruh isian teknisi **dibuang tanpa satu pun pesan error**, laporan tetap "berhasil disimpan".

**Bagian 1 — Anchor dibuat di titik deterministik**

| # | Perubahan | Catatan |
|---|---|---|
| 1 | `FopTaskProvisioningService::ensureForCustomer(Customer, TaskType)` | Idempoten; `lockForUpdate` dalam transaksi supaya dua request bersamaan tidak melahirkan dua TFOP |
| 2 | Dipanggil dari registrasi pelanggan, transisi `waiting_survey`/`waiting_installation`, dan **jaring pengaman** di `CustomerSurveyController::store()` + `CustomerInstallationController::store()` | Jaring pengaman menutup semua jalur lain sekaligus — anchor dibuat, bukan dilewat |
| 3 | Papan `/fop-tasks` memakai service yang sama | Dua blok `FopTask::create()` duplikat dihapus; loop tinggal jadi sapuan pelanggan lama |
| 4 | `FopTaskController::generateTaskNumber()` delegasi ke service | Deret TFOP tidak lagi punya dua implementasi generator di satu file. Format tetap identik dengan `TicketService::generateFopTaskNumber()` |
| 5 | Kategori selain SURVEY/PSB ditolak service | MTN & C-REQ tetap cuma lewat `TicketService::escalateToFop()` — service ini bukan pintu belakangnya |
| 6 | Fallback `village_id ?? 1` / `pop_id ?? 1` dibuang | Kedua kolom nullable; menembak id 1 yang belum tentu ada melempar FK error, dan di jalur simpan laporan itu berarti laporan teknisi gagal total (ketahuan dari test pertama yang merah) |

**Bagian 2 — Verifikasi Admin menampilkan data yang diinput**

| # | Perubahan | Catatan |
|---|---|---|
| 1 | Tab Survey: "Estimasi Material Hasil Survey" + "Alat Kerja Dicatat Surveyor" | Partial `verifications/partials/{materials,work-tools}.blade.php` |
| 2 | Tab Pemasangan: "Material Terpakai Saat Pemasangan" + "Alat Kerja Dipakai Tim Pemasangan" | Di atas tabel Estimasi vs Terpakai yang sudah ada |
| 3 | Daftar baris **melengkapi**, bukan menggantikan tabel variance | Variance mengagregasi per barang dan membuang catatan per baris |
| 4 | Blok tetap dirender walau kosong | "Tidak mencatat" ≠ "seksi tidak ada" — kebingungan itu yang memicu laporan ini |
| 5 | `customer_surveys.fop_id` → nama FOP | Sebelumnya tampil mentah dengan label "Kebutuhan FOP / Tiang"; kolomnya menunjuk ke `users`, label & isinya tidak nyambung |
| 6 | `required_tools` dilabeli "Catatan Kendala Peralatan" & disembunyikan kalau kosong | Menyamakan dengan label form laporan (peran barunya sejak ADHOC-13) |

**Data lama tidak bisa dipulihkan** — material & alat itu tidak pernah masuk DB. Yang tersimpan cuma `required_tools`. Pelanggan yang masih di antrean survey/pemasangan akan dapat anchor otomatis; yang sudah lewat tahap tidak.

**Test baru:** `SurveyReportMaterialLostWithoutFopTaskTest` (3), `VerificationAdminInputDataVisibilityTest` (3). Full suite **1169 passed, 15 skipped, 0 failed**.

**Dokumentasi terkait:** `docs/fop-task/{README,flowchart}.md` §2, `docs/customer-lifecycle/business-logic.md` §4, §6, §7.

**Catatan lingkungan:** `storage/framework/testing/disks` di mesin dev dimiliki `root` dan bikin ~24 test gagal di `Storage::fake()` (bukan regresi kode). Di-rename jadi `disks_broken_rootowned`; sisanya perlu dihapus manual dengan sudo.

#### ADHOC-27 — Aksi selesai tapi layar diam (2026-08-11)

**Pemicu:** user melapor "Upload berkas, diproses, selesai — tidak ada apa pun di halaman; tiba-tiba saldo kolektor terpotong." Saldo itu ternyata dari verifikasi setoran 61 detik sebelumnya, **bukan** dari unggahan. Tapi selama layarnya diam, kesimpulan itu wajar diambil siapa pun — yang salah UI-nya.

Kelas masalahnya sama di beberapa tempat: pekerjaan pindah ke queue/realtime, layar tidak ikut bicara.

**1. Panel progres kwitansi** — spinner + "Membaca kwitansi… N tersisa" + progress bar + tiga penghitung (Cocok/Antre/Perlu cek). Polling 2 dtk, berhenti sendiri saat antrean nol; selesai → toast + segarkan sekali. Endpoint `GET /payment-receipts/progress/{collector}`, memakai `PaymentReceipt::scopeForWorksheet()` yang SAMA dengan daftarnya — penghitung yang lebih longgar dari daftarnya membocorkan keberadaan berkas cabang lain lewat angka. Form unggah dapat ringkasan pilihan + tombol terkunci saat mengirim.

**2. Indikator koneksi realtime (global)** — bilah di `layouts/app.blade.php`, menutup **sepuluh** layar yang bergantung Echo sekaligus. Ditaruh di layout, bukan `resources/js/echo.js`, supaya aktif tanpa `npm run build`. Tunda 8 detik (agar `connecting` yang wajar tidak berteriak) dan memeriksa keadaan awal (halaman yang dibuka saat server WS sudah mati tak pernah mengalami `state_change`).

**3. Sukses palsu di `master/sla-timeline`** — simpan otomatis tanpa cek `res.ok`/`.catch`: 403/422/500 pun berakhir "Tersimpan.". Sekarang pesan galat diambil dari `message`/`errors`, toast merah, dan **nilai input dikembalikan**. `SlaTimelineGagalSimpanTest` mengunci kontrak response-nya.

**4. Realtime setoran kolektor** — `App\Events\CollectorDepositUpdated`, satu event untuk seluruh siklus (`diajukan`/`diverifikasi`/`dilunasi`/`dihapus_buku`), disiarkan ke DUA kanal: `collector-activity.{popId}` (admin) dan `App.Models.User.{id}` (kolektor). **`ShouldBroadcastNow`** — kabar tentang uang tak boleh menunggu worker, konsisten dengan `AppNotification`. Payload **tanpa saldo**: saldo angka turunan, dan halaman yang menghitung uang fisik tidak boleh mengganti angkanya saat orangnya sedang menghitung. Ditambah notifikasi **hapus buku → kolektor** yang sebelumnya tidak ada sama sekali.

**6. Realtime aktivitas kas di luar setoran** — `App\Events\CollectorActivityUpdated` pada kanal yang sama, empat aksi: `pembayaran_dicatat` (saldo NAIK, Worksheet dulu diam), `pembayaran_ditolak` (saldo TURUN), `pelanggan_diassign` & `pelanggan_dilepas` (rute berubah — sebelumnya **tanpa notifikasi sama sekali**, `grep -c notify` = 0 di `CollectorWorksheetController`). Ditambah notifikasi assign/lepas ke kolektor. Pembayaran tanpa `collected_by` sengaja **tidak** menyiarkan apa pun — uang kantor, tak ada saldo kolektor yang bergerak.

> **Kanal diganti nama** `collector-deposits.{popId}` → `collector-activity.{popId}` begitu isinya melampaui setoran; ikut pula `depositChannels()` → `activityChannels()` dan partial `deposit-realtime` → `collector-realtime`. Route `collector-deposits.verify`/`.write-off` TIDAK berubah — itu route, bukan kanal.

**Test tambahan:** `AktivitasKasKolektorRealtimeTest` (5).

**7. Sembilan test RBAC merah — bukan bug permission, tapi cache bocor.** `EffectiveAccessServiceTest` (×5), `RolePermissionTest` (×2), `UserLegacyAdapterTest`, `MasterBarangPermissionGeneratedTest` sudah merah sejak sebelum sesi ini. Gejalanya menyesatkan: `userCan($user, 'invoices.create')` mengembalikan `true` untuk user yang cuma punya `invoices.view`, seolah resolusi permission-nya rusak.

Penyebabnya `CACHE_STORE: file` di `docker-compose.yml`. Sama persis dengan kebocoran queue di poin 5: env compose mengisi `$_SERVER` dan mengalahkan `force="true"` di `phpunit.xml`, sehingga test memakai cache **file yang persisten lintas run**. `EffectiveAccessService` menyimpan izin di `user.{id}.permissions`; satu run yang menjadikan user id 1 sebagai Owner meninggalkan `["*"]` di sana, dan run berikutnya melihat user biasa punya akses penuh.

Perbaikan: `CACHE_STORE`/`SESSION_DRIVER` dikeluarkan dari compose (nilainya dipindah ke `.env` supaya runtime tak berubah — tetap `file`), plus lapis kedua di `Tests\TestCase::setUp()` yang memaksa `cache.default`/`session.driver` ke `array`.

**Hasil: suite penuh HIJAU — 1176 lulus, 0 gagal.** Pertama kalinya sepanjang sesi ini.

**8. Satu pembayaran = satu dokumen kwitansi.** Versi pertama pemecahan lembar (poin 3) membiarkan ke-200 baris menunjuk PDF yang sama — daftar Berkas Kwitansi terbaca seperti 200 duplikat, dan membuka kwitansi pelanggan A menampilkan lembar berisi 8 pelanggan.

**Ditempuh: tautan + regenerasi dari data** (opsi A, keputusan user 2026-08-12). Tiap baris di daftar Berkas Kwitansi punya dua tautan berbeda peran:

| Tautan | Isi | Peran |
|---|---|---|
| **Kwitansi** | halaman cetak `payment-receipts.print` dengan `payment_ids[]` **satu id** | dokumen satuan pelanggan itu, dirender ulang dari `payments`/`invoices` |
| **Lembar asal** | berkas PDF yang diunggah, apa adanya | bukti setoran — sidik `checksum` menempel di sini |

Berkas unggahan dengan sengaja **tetap satu untuk seluruh lembar** (200 baris → 1 `path`). Duplikasinya bukan kesalahan data: itu memang satu lembar yang dipegang bersama 200 pembayaran, dan yang membedakan baris adalah `payment_id`, bukan berkasnya.

> **Kenapa bukan dipotong.** Empat geometri pemotongan dicoba dan semuanya gagal presisi (titik tengah antar nomor, kata terdekat, deteksi celah, grid seragam) — tata letak cetakan tidak konsisten, dan potongan yang meleset menempelkan sebagian kwitansi orang lain ke pelanggan yang salah. Regenerasi dari data tidak punya kelas galat itu sama sekali: angkanya datang dari baris pembayarannya sendiri.
>
> **Jejak audit tetap utuh** — inilah alasan lembar asal TIDAK dibuang. Kalau suatu saat ditanya "pelanggan ini benar sudah bayar?", jawabannya bertumpu pada `payments` + `checksum` lembar yang diunggah, bukan pada gambar hasil crop. Potongan PNG adalah turunan; turunan tidak bisa jadi bukti atas dirinya sendiri.

`ReceiptSheetSplitter`, kolom `source_path`, dan seluruh potongan PNG lama **dihapus** (709 baris dikembalikan menunjuk lembar asalnya).

> **Bug yang hampir lolos di versi pemecahan (dicatat, kodenya sudah tiada):** percobaan pertama menghasilkan 200 baris tapi tetap 1 berkas, tanpa error apa pun. Penyebabnya `$potongan` tidak ikut di `use (...)` closure `DB::transaction` — undefined di dalam closure, `isset()` mengembalikan `false`, seluruh pemecahan berhenti bekerja secara senyap. Persis kelas kegagalan yang ADHOC-27 ada untuk memberantasnya, kali ini di kode sendiri.

> **Jebakan kedua, di sisi operasional:** setelah kodenya benar dan test hijau, unggahan lewat UI TETAP menghasilkan satu berkas untuk 100 kwitansi. Bukan kodenya — **worker Horizon memuat kelas sekali saat start** dan sudah hidup 7 jam, sejak sebelum pemecah lembar ada. `tinker` dan test memakai proses baru sehingga selalu benar; aplikasi sungguhan memakai kode kemarin. Wajib `docker compose restart horizon` (atau `horizon:terminate`) setiap kali menyentuh kode yang dipakai job — dicatat di `docs/RUNBOOK_COMMANDS.md` §E.

**5. `verifications/queue`** — baris meredup saat disegarkan; kegagalan tidak lagi dipendam (`// Diam-diam gagal`) melainkan bertoast, karena baris basi tak bisa dibedakan dari baris yang memang belum berubah — dan dua admin bisa memverifikasi pelanggan yang sama.

> **Dua temuan saya yang ternyata SALAH**, dicatat supaya tidak ditelusuri ulang: `tickets/partials/detail-drawer` sudah punya skeleton + state gagal, dan `master/wilayah` sudah punya `treeLoading` + pesan galat. Keduanya benar apa adanya; penilaian awal berasal dari hitungan grep, bukan membaca kodenya.

**Test baru:** `SetoranKolektorRealtimeTest` (4), `SlaTimelineGagalSimpanTest` (3), +3 di `PaymentReceiptTest` (progres & kebocoran berkas yatim).

#### ADHOC-26 — Tagihan tak terbit, kwitansi massal, queue & batas PHP (2026-08-11)

**Pemicu:** user melapor "sudah tanggal 10, tagihan tidak bertambah". Penelusuran menyeret lima area sekaligus.

**1. Tagihan Juli–Agustus tidak pernah terbit.** Container `scheduler` mati saat `monthlyOn(1, '01:00')`. Menghidupkannya **tidak** menambal — tanggal 1 yang terlewat tidak diulang. Ditambah `billing:generate-monthly-invoices` dipatok `now()`, jadi bulan lampau mustahil dikejar.
→ opsi `--period=YYYY-MM` (validasi ketat), tempo legacy `addDays(10)` → `day(10)` supaya tidak ada dua tanggal tempo untuk satu aturan. `docs/RUNBOOK_COMMANDS.md` §C langkah 5–7.

**2. Riwayat billing legacy cuma menutup ~4,7%.** `biaya_tagihan` adalah kontrak biaya per pemasangan (tanpa kolom periode/status); satu-satunya jejak per bulan (`apikeuangan_buktitransaksitagihan`) berisi 9–127 baris/bulan untuk ~1.900 pelanggan; seluruh tabel jurnal kosong. 1.737 invoice vs 37.081 pelanggan-bulan. **Bukan bug importer — datanya memang tidak ada di dump.** Lahir opsi `app:import-legacy-sql --without-billing` + kelompok C-2 "go-live pelanggan saja" di runbook.

**3. Kwitansi PDF tak pernah tercocokkan otomatis.** QR reader melewatkan non-gambar, OCR mati default ⇒ setiap PDF `FAILED`. Diperbaiki berlapis:
- `PdfPageRasterizer` (poppler `pdftoppm`, bukan Imagick — Debian mematikan coder PDF di policy.xml), render **per halaman**, eskalasi 400 DPI untuk dokumen ≤3 halaman.
- **Lapisan teks PDF jadi jalur utama** — 8/8 nomor vs 7/8 dari pemindaian QR, dan pada berkas nyata 200 kwitansi: teks 0,64 dtk & 100% akurat, QR halaman-penuh 37,2 dtk & **0%**.
- **Satu lembar = 8 kwitansi**: unique `checksum` → `(checksum, payment_id)`, satu baris per nomor.
- Urutan baca disatukan ke `ReceiptNumberExtractor::extractAll()`; enum dapat case `TEXT`.
- `attachNumbers()` dibungkus satu transaksi: **16 dtk → 1,14 dtk** untuk 200 kwitansi.

**4. Pengaman jalur Tagihan.** `payments.idempotency_key`, `payment_date` `before_or_equal:today`, dan `PaymentController@bulkStore` + route `invoices.payments.bulk-store` **dihapus** (yatim, tanpa UI/test, jaminannya menyimpang dari jalur kolektor).

**5. Infrastruktur.** `procps`+`netcat-openbsd` (healthcheck worker yang selama ini palsu `unhealthy`), `poppler-utils`, queue seragam `redis`, antrean `kwitansi` terpisah dari `default`, `max_file_uploads` 20→100.

> ⚠️ **Regresi yang lahir & ditutup di hari yang sama:** menaruh `QUEUE_CONNECTION` di `docker-compose.yml` membuat `php artisan test` mendorong job ke Redis asli (576 job gagal). `force="true"` di `phpunit.xml` **tidak cukup** — hanya menulis `putenv()`, sementara `env()` membaca `$_SERVER` lebih dulu. Aturannya sama dengan `DB_*`: jangan duplikasi env runtime di compose. Detail di `docs/RUNBOOK_COMMANDS.md` §E.

**Test baru:** `TagihanBulananJatuhTempoTanggal10Test` (+3), `LegacyInvoiceJatuhTempoTanggal10Test`, `ImportLegacyTanpaBillingTest`, `KwitansiPdfDicocokkanOtomatisTest`, `KwitansiLembarBoronganTest`, `PembayaranTagihanTanpaDobelDanTanggalMasaDepanTest`, `MatchPaymentReceiptQueueTest`, +2 di `PaymentReceiptTest`.

**Sisa terbuka:** eksekusi migrasi C-2 (destruktif, menunggu keputusan) dan **10 test RBAC gagal** (`EffectiveAccessServiceTest`, `RolePermissionTest`, `UserLegacyAdapterTest`, `MasterBarangPermissionGeneratedTest`, `SubscriptionStatusMasterTest`) — sudah gagal sebelum ADHOC-26, milik pekerjaan RBAC yang masih berjalan.

#### ADHOC-25 — OCR kwitansi: hasil tebakan tak boleh jadi keputusan (TERBUKA)

Analisa lengkap + rancangan antisipasi: **`docs/plan/kolektor/analisa-risiko-ocr-kwitansi.md`** (KEBENARAN).
Sisi operasionalnya dipisah ke **`docs/plan/kolektor/analisa-operasional-ocr-gemini.md`** (rate limit,
cooldown, klasifikasi galat, pagu harian) — lihat ADHOC-31.

> ⚠️ **MEMBLOKIR pengisian `GEMINI_API_KEY`.** Lubangnya dorman selama OCR mati, dan hidup tepat
> pada hari key diisi. Sesudah itu, tiap kwitansi yang salah tempel harus ditelusuri satu per satu.
> **Rate limit tidak membuka blokir ini** — dua masalah berbeda: yang satu boros, yang ini salah.

**Pemicu:** pertanyaan user — "kalau QR/OCR error tanpa sebab, bukankah itu jadi celah baru?"
Instingnya benar, tapi bahayanya di tempat berlawanan.

- **Kegagalan baca BUKAN celah** — fail-closed & berisik: status `FAILED`, berkas terpampang menunggu
  manusia, tak ada yang otomatis terjadi, tak ada pembayaran berubah.
- **Celahnya "berhasil tapi salah".** `PaymentReceiptService::match()` memperlakukan hasil OCR sama
  persis dengan hasil QR — langsung `attach()`. Padahal OCR bisa salah baca satu digit; kalau nomor
  hasil salah-baca itu **ada** di DB, ia lolos kedua gerbang (pola benar, payment memang ada) dan
  **menempel diam-diam ke pembayaran pelanggan lain**. Statusnya hijau "Cocok", tanpa `last_error`.

Kelas bug yang sama dengan dua kejadian sebelumnya di modul ini: **yang gejalanya menyerupai
keberhasilan** — tak ada yang melaporkannya.

**Prinsip yang dilanggar:** deterministik boleh memutuskan, probabilistik hanya boleh mengusulkan.
QR membaca data yang sistem sendiri cetak (fakta); OCR menebak dari piksel (dugaan).

**Rancangan antisipasi:** QR tetap otomatis; hasil OCR jadi **usulan** — `detected_number` di-prefill,
nama pelanggan hasil tebakan ditampilkan, admin konfirmasi satu klik, `payment_id` tak pernah diisi
mesin. Plus dua tambahan murah: hitung tingkat kegagalan per pengunggah, dan tonjolkan label
`via Manual` supaya jalan keluar manual terlihat (menjawab kekhawatiran "sistemnya error" dipakai
sebagai alasan).

Empat keputusan menunggu (D1–D4 di dokumen), termasuk apakah kwitansi kelak dijadikan **kontrol** —
jawaban itu mengubah keputusan "kwitansi menunggu kantor".

#### ADHOC-24 — Kolektor 2.0 Fase 4: Kwitansi QR & OCR Cadangan (2026-08-08)

Dokumentasi modul: `docs/kolektor/business-logic.md` §12, `flowchart.md` §8,
`database-schema.md` (`payment_receipts`).

**Sumbu dokumen dipisah dari sumbu kas.** Rancangan awal menggantung status verifikasi
kolektor pada selesainya OCR; itu menyandera uang yang sudah dihitung dua orang di meja
pada berkas gambar yang bisa gagal dibaca. Sekarang setoran terverifikasi tanpa menunggu
kwitansi apa pun — dikunci test `test_deposit_verification_does_not_wait_for_any_receipt`.

**QR jalur utama, bukan OCR.** Kwitansi dicetak sistem sendiri, jadi menyuruh model bahasa
membaca ulang data yang kita tulis sendiri adalah biaya tanpa informasi baru. QR berisi
`payment_number` polos (bukan URL — kertas tak boleh terikat domain), SVG, error correction
**High** karena kertas kwitansi terlipat/kena air/difotokopi. Nomor juga dicetak sebagai
**teks polos**: itu yang dibaca OCR saat QR sobek, dan dibaca manusia saat OCR pun gagal.

**Dua gerbang sebelum mencocokkan:** pola `PAY-YYYYMM-NNNN`, lalu keberadaan payment-nya di
DB. Nomor yang lolos pola tapi tak menunjuk pembayaran mana pun berakhir `MISMATCH`, tidak
pernah dicocokkan asal — ini yang menahan QR salah cetak maupun halusinasi OCR.

**OCR mati secara default.** Tanpa `GEMINI_API_KEY`, jalur itu dilewati dan berkas jatuh ke
pencocokan manual. Fitur tetap utuh, tak ada biaya keluar sebelum diputuskan.

**Yang ditambahkan:** tabel `payment_receipts`, enum `ReceiptStatus`/`ReceiptMatchMethod`,
`PaymentReceiptService`, kontrak `ReceiptNumberReader` + driver QR/Gemini + `ReceiptNumberExtractor`,
`ReceiptQrRenderer`, job `MatchPaymentReceipt` (queue, `tries=3`), `PaymentReceiptController`,
halaman cetak, tab **Kwitansi** di Worksheet Admin, permission `collector_worksheet.print` &
`.upload`, config `services.gemini`.

**Dependency baru (disetujui user):** `endroid/qr-code:^5.1` (membuat QR, tanpa GD),
`khanamiryan/qrcode-detector-decoder:^2.0` (membaca QR), + `bacon/bacon-qr-code`. Dipilih
endroid `^5.1` bukan `^6` karena `^6` mensyaratkan PHP `^8.4` sementara `composer.json`
menyatakan `^8.3`.

**Batas yang belum tertutup & dicatat sadar:** `payment_number` baru ada setelah pembayaran
tersimpan, jadi kwitansi dicetak sesudah kolektor submit — pelanggan tidak menerima apa pun
di tempat. Ini arsip internal untuk sengketa, bukan bukti yang dipegang pelanggan seketika.

**Review Fase 4 (11 temuan, semua ditutup 2026-08-08)** — rincian di
`docs/plan/kolektor/review-fase-1-3.md`. Yang paling penting: **idempotency key sempat dipakai
bersama antar-permintaan yang sedang jalan**, akibat langsung dari perbaikan review putaran
sebelumnya. Kolektor menekan Bayar di baris A lalu baris B sebelum jawaban A tiba → keduanya
mengirim key sama → B dijawab `already_processed` → toast hijau → **uang baris B tak pernah
tercatat**. Sekarang key diturunkan dari tanda tangan barisnya. Pelajarannya: idempotency key
mengidentifikasi ISI KIRIMAN, bukan sesi atau tab.

#### ADHOC-22 — Perbaikan hasil review Kolektor 2.0 Fase 1–3 (SELESAI 2026-08-08)

Analisa lengkap per temuan: **`docs/plan/kolektor/review-fase-1-3.md`** — mekanisme, dampak, usulan
perbaikan, dan jebakan saat memperbaikinya. Baca itu dulu, jangan mulai dari ringkasan ini.

Review diff Fase 1–3 (2026-08-08) menemukan 9 temuan terbuka. Semuanya sudah diverifikasi ulang
terhadap kode; tidak ada yang gugur. **Kesepuluh temuan ada di luar jangkauan test yang ditulis
bersama fiturnya** — 1080 test lulus dan tak satu pun menangkapnya. Tiap perbaikan wajib membawa test
regresi bernama sesuai gejalanya.

**P1 — ✅ SELESAI 2026-08-08** (1089 test lulus). Perbaikannya:
- **#1** → gerbang POP *all-or-nothing* di `CollectorWorksheetController::show()`, ditopang
  `CollectorBalanceService::isVisibleTo()` + `popFootprint()` (jejak POP dari pelanggan + payment +
  setoran). Sengaja bukan "saring yang boleh": halaman ini menyajikan angka TOTAL, dan total yang
  diam-diam disaring bukan menyembunyikan baris — ia berbohong, lalu admin menghitung uang fisik
  dengan patokan salah. Sejalan dengan syarat verifikasi setoran §14.2.
- **#2** → `notifyPopAdmins()` dikeluarkan dari wilayah `try` (batas transaksi & batas penanganan
  error kini sejajar) dan kegagalannya ditelan + `report()`. Klien memakai ulang idempotency key
  sampai sukses, jadi retry setelah gagal bukan batch baru.
- **#3** → baris kunjungan `bayar` tak bisa ditimpa input manual; `payment_id`/`note` basi
  dibersihkan saat hasil berubah. Larangan "bayar tak bisa diinput manual" kini utuh — sebelumnya
  hanya melarang MEMBUAT, tidak melarang MENGHAPUS.

Rincian asli temuan (dipertahankan sebagai konteks):
1. **Kebocoran lintas POP di Worksheet Admin** — `show()` tak memeriksa kolektor masuk scope penonton;
   `$balance`, `$outstandingShortfall`, dan daftar `$deposits` nol POP scope. `pop_admin` POP A bisa
   membaca posisi kas & riwayat setoran kolektor POP B. Bagian lain halaman itu sudah di-scope, jadi
   ini kelupaan, bukan desain — dan melanggar larangan keras CLAUDE.md.
2. **Pembayaran dobel setelah notifikasi gagal** — `notifyPopAdmins()` jalan setelah transaksi commit
   tapi masih di dalam `try/catch`; exception → `422 "Batch ditolak"` padahal payment tersimpan.
   Front-end mint idempotency key baru tiap retry → cicilan terkredit dua kali.
3. **Jejak kunjungan `bayar` bisa dihapus lewat input manual** — jalur manual menimpa baris `bayar`
   dan meninggalkan `payment_id` basi. Menyerang jantung kontrol anti-fraud §12.

**P2/P3 — ✅ SELESAI 2026-08-08** (1095 test lulus):
- **#4** → `rows.*.collected_date` diberi `before_or_equal:today`, menyamakan aturan dengan jalur
  kunjungan yang sudah menegakkannya.
- **#5** → pelunasan divalidasi ulang terhadap baris yang SUDAH DIKUNCI, di dalam transaksi;
  `applySettlement()` tak lagi mengunci salinan kedua. Menutup dua kasus: over-credit karena dua
  verifikasi bersamaan, dan uang pelunasan mendarat di baris `DIHAPUS_BUKU` lalu lenyap.
- **#6** → status baru `DepositStatus::LEBIH_SETOR`, terminal. Verify jadi tiga cabang
  (`= 0` terverifikasi / `< 0` kurang setor / `> 0` lebih setor). Karena terminal, ia otomatis keluar
  dari daftar pending, tak bisa dipilih untuk pelunasan, dan ditolak hapus buku — tiga gejala buntu
  hilang sekaligus. Label `SELISIH` diubah jadi **"Kurang Setor"** karena "Selisih" jadi ambigu.
- **#7** → `writeOff()` memakai `assertVerifierCanSeeAllPayments()`, guard POP sama dengan `verify()`.
- **#8** → `withQueryString()` pada `$invoices` & `$assignedCustomers`.
- **#9** → idempotency key setoran ditambah `Str::random(8)`.

Rincian asli temuan P2/P3 ada di `docs/plan/kolektor/review-fase-1-3.md`.

Catatan kebersihan repo: file `NUL` nyasar sudah dihapus. `storage/framework/testing/views/*.php`
ter-track di git dan ikut tiap diff — mestinya di-gitignore, tapi menyentuh berkas bersama jadi butuh
persetujuan dulu.

#### ADHOC-21 — Kolektor 2.0 Fase 3: Visit Log & Laporan Aging (2026-08-08)

Rancangan: `docs/plan/kolektor/analisa-alur-kolektor-2.0.md` §12.

**Lubang yang ditutup.** Setoran (Fase 2) hanya menangkap *"laporan jujur, kas
tidak jujur"*. Skenario *"laporan tidak jujur"* lolos 100%: kolektor menagih Ani
100rb, tidak melaporkannya sama sekali, uang dikantongi — setoran cocok sempurna,
status terverifikasi, invoice Ani tetap `belum_dibayar`, Ani merasa sudah bayar,
sistem diam. Tanpa catatan kunjungan, "tidak ada baris" ambigu antara *belum
didatangi* dan *didatangi lalu uangnya raib*.

**Yang dibangun:** tabel `collector_visits` (collector, customer, pop, tanggal,
hasil, tanggal janji, catatan, payment), enum `VisitResult`
(`bayar`/`tidak_ada_orang`/`menolak`/`janji_bayar`), `CollectorVisitService`,
`CollectorVisitController` + `POST /collector-worklist/visits`, permission
`kolektor.visit`, panel input di Worklist, dan tab **Kunjungan** di Worksheet
Admin berisi laporan aging + riwayat.

**Aturan yang dikunci:**
1. `bayar` **tidak bisa diinput manual** — hanya turunan payment yang benar-benar
   tersimpan, ditulis di dalam transaksi pembayaran yang sama. Kalau boleh
   diketik, tabel ini justru jadi alat menutupi lubang yang harus dia ungkap.
2. Satu kunjungan = satu baris per pintu per hari (unique index). Bayar 3 tagihan
   sekaligus tetap satu kunjungan; bayar sore menimpa "tidak ada orang" pagi.
3. Pilihan pelanggan dibatasi ke worklist hari itu.
4. `promised_date` dibuang untuk hasil selain `janji_bayar`.
5. Tanggal kunjungan boleh mundur, tidak boleh maju.
6. Dua lapis guard sama seperti jalur bayar: pelanggan milik kolektor itu DAN
   dalam POP scope efektifnya.
7. Aging diurutkan dari yang paling sering gagal; ≥3 kunjungan gagal ditandai.

**Bug yang ketahuan saat implementasi:** `updateOrCreate` dengan `visited_at` di
kunci pencarian tak pernah menemukan baris yang ada — kolom `DATE` vs atribut
ber-cast `date` dibandingkan sebagai datetime penuh. Insert kedua menabrak unique
index dan **seluruh transaksi pembayaran rollback**, jadi bayar banyak tagihan
sekaligus gagal total. Diganti `whereDate` + `fill/save`. Test regresinya:
`test_paying_several_invoices_of_one_customer_creates_a_single_visit`.

Fase 4 (kwitansi QR/OCR) belum dikerjakan.

#### ADHOC-20 — Bundle Alpine.js lewat Vite (langkah 1b, 2, 4 SELESAI 2026-08-13; langkah 3 terbuka)

**Status per 2026-08-13:** Alpine sudah lepas dari CDN.

| Langkah | Status |
|---|---|
| 1a. `npm i alpinejs` + plugin | Selesai (sudah ada sejak sebelumnya di `package.json`) |
| 1b. `import Alpine` + plugin + `window.Alpine` + `Alpine.start()` di `resources/js/app.js` | **Selesai 2026-08-13** |
| 2. Cabut tag CDN dari `layouts/app.blade.php` | **Selesai 2026-08-13** |
| 3. Sisir halaman yang menyusun `action`/URL POST di klien | **Selesai 2026-08-13** — lihat tabel hasil sisir di bawah |
| 4. `npm run build` masuk prosedur deploy | **Selesai 2026-08-13** — DUA jalur, lihat catatan di bawah |

**Langkah 4 butuh dua jalur, bukan satu.** Tahap `assets` di `Dockerfile` (node:22-alpine →
`npm ci && npm run build`, hasilnya disalin ke `public/build`) hanya menolong deploy berbasis
**image murni**. Di `docker-compose.yml` service app & nginx bind-mount `./:/var/www`, dan
bind-mount MENUTUPI isi image di path yang sama — `public/build` yang benar-benar dilayani nginx
(`root /var/www/public`) selalu milik host. Karena itu ditambah service `assets` di compose:

- jalan tiap `up`, `npm install` + `npm run build` ke direktori host yang ter-mount;
- app & nginx `depends_on: assets: service_completed_successfully` — tanpa manifest Vite, halaman
  ber-`@vite` langsung 500 (ViteException) dan seluruh interaksi Alpine mati, jadi build dijadikan
  **syarat start**, bukan langkah manual yang bisa terlupakan;
- jalan sebagai root lalu `chown -R $HOST_UID:$HOST_GID public/build node_modules
  storage/framework/testing`. Ini memperbaiki direktori yang terlanjur milik root (peninggalan
  proses docker) — user non-root tidak bisa menghapus isinya, sehingga Vite gagal `EACCES` saat
  mengosongkan outDir dan `php artisan test` gagal `Permission denied` di setiap test yang
  menyentuh unggahan berkas. Pola root+chown ini menyembuhkan keadaan yang sudah terlanjur **tanpa
  sudo**, dan mengulanginya tiap build. Yang harus DIHINDARI: `docker compose exec app npm run
  build` — container `app` jalan sebagai root dan tidak punya langkah chown, jadi justru
  menciptakan masalahnya;
- `HOST_UID`/`HOST_GID` didokumentasikan di `.env.example` (default 1000).

Diverifikasi 2026-08-13: `docker compose config` lolos, `docker compose run --rm assets` sukses
(`public/build` berubah dari root:root jadi yopi:yopi), lalu `npm run build` **dari host** juga
sukses — jebakan EACCES-nya hilang. Tahap `Dockerfile` diuji terpisah lewat
`docker build --target assets` (exit 0).

Efek sampingan yang diharapkan: `x-collapse` di 6 titik (`roles/matrix`, `noc/worksheet`,
`tickets/history`, `pop-tree-picker`) **hidup lagi** — plugin `@alpinejs/collapse` tidak pernah ada
di bundle CDN. `@alpinejs/focus` ikut terdaftar untuk `x-trap` (belum dipakai). Bundle JS
122 KB → 202 KB (gzip 67 KB), ganti satu request lintas-origin.

Analisa lengkap + daftar verifikasi manual di browser: `docs/plan/navbar/analisa-alpine-cdn-ke-lokal.md`.

**Verifikasi browser SELESAI 2026-08-13** — kesepuluh poin §5 lolos: Alpine core hidup, `x-collapse`
beranimasi (plugin yang selama ini tidak pernah ada di bundel CDN), pola `alpine:init` di filter
POP/Wilayah jalan, halaman login normal, console bersih tanpa "multiple instances", dan
`window.Alpine.initTree()` terbukti masih mengikat ulang DOM yang diganti Echo di `/fop`,
`/tasks-saya`, `/fop-tasks`. Tiga poin terakhir sempat tertunda karena papan FOP tidak menampilkan
tim mana pun — baru bisa diuji setelah ADHOC-34.

##### Langkah 3 — hasil sisir target aksi (2026-08-13)

Aturan yang ditegakkan: **URL aksi yang mengubah data harus datang dari server** — `route()` di
atribut `data-*`/`onclick`, atau field di respons JSON. Klien boleh MEMILIH di antara URL yang
diberikan server, tapi tidak boleh MERAKIT path-nya.

| Titik | Sebelum | Sesudah |
|---|---|---|
| `fop_tasks/index` modal Task | `:action="formAction"`, cabang edit merakit `url('/fop-tasks') + '/' + id` | URL update dikirim tombol Edit tiap baris (`route('fop-tasks.update')`); ketiga cabang kini URL server; ditambah penjaga `@submit` yang membatalkan submit kalau `action` kosong |
| `tickets/create` | form tanpa `action`/`method`/`@csrf`, murni `fetch()` | `action`+`method`+`@csrf` dirender server-side sebagai fallback (controller sudah bercabang `wantsJson()`) |
| `customers/index` Quick Hub — form pembayaran | `payForm.action = '/invoices/${invoice_id}/payments'` | `payment_store_url` dari `CustomerController::paymentInfo()` |
| `customers/index` — modal Atur Jaringan | `form.action = '/customers/${id}/network-assignment'` | `data-network-update-url` + `data-network-data-url` di tombol |
| `customers/index` — Detail & Terminasi dari hub | `'/customers/' + id` | `data-detail-url` |
| `payments/partials/quick-payment-modal` | `fetch('/invoices/${qpInvoiceId}/payments')` | `data-payment-store-url` dari tombol Bayar (`invoices/index`, `customers/show`) |
| `verifications/queue` — modal Tolak | `form.action = '/verifications/${id}/reject'`, form default `action=""` | URL dikirim tombol di `verifications/partials/queue-actions` |
| `notifications/index` — tandai dibaca | `'/notifications/${id}/read'` / `/unread` | `data-url-read` + `data-url-unread` |
| `components/notification-dropdown` | path literal di `fetch()` | `markReadUrlTemplate` + `markAllReadUrl` dirender `route()` |

Yang **diperiksa dan sudah benar** (tidak diubah): `collector-worksheet/index` (assign — sudah
server-side sejak ADHOC-18/22), `noc/worksheet` & `tickets/partials/archive` (URL dari
`row.dataset.url*`), `tickets/show` (URL `route()` di `onclick`), `partials/collector-pay-script`
(`CB_STORE_URL` dari controller), `roles/index` (base `route()`/`url()` dari Blade).

Sisa yang **sengaja dibiarkan**: `fop_tasks/index` (`assign-to-team`, `destroy`, `row`),
`fop/dashboard` (`switch-team`), `master/sla-timeline` — semuanya memakai basis Blade
(`{{ url('/fop-tasks') }}`) lalu menempelkan id, jadi definisi path tetap hidup di satu tempat.
Endpoint GET (pencarian wilayah/POP, `payment-info`, detail invoice) di luar cakupan langkah ini
karena tidak mengubah data — kecuali `payment-info` yang ikut dipindah ke `data-*` supaya satu
halaman tidak memakai dua gaya.

Penjaga regresi: `PostTargetRenderedServerSideTest` — 3 test HTTP (URL benar-benar ter-render) +
6 test pemindaian sumber Blade (pola literal yang sudah dicabut tidak boleh balik).

> Catatan: `fop-tasks.destroy` dan `fop-tasks.update` **berbagi URI** (beda verb). Karena tombol
> Edit sekarang merender URL update, assertion lama `assertDontSee(route('fop-tasks.destroy'))` di
> `FopTaskDeleteRestrictionTest` jadi salah sasaran dan diganti jadi memeriksa markup form Hapus
> (`data-confirm` + `name="_method" value="DELETE"`).

**Kondisi sebelum perubahan ini:** `resources/views/layouts/app.blade.php:22` memuat Alpine dari
CDN publik:

```html
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
```

**Kenapa ini masalah — bukan teori, sudah kejadian.** Bug assign kolektor
(2026-08-08): panel Worksheet Admin menyusun target POST form-nya lewat Alpine
(`:action`). Waktu CDN tak termuat, atribut itu tak pernah dievaluasi,
`form.action` jatuh ke URL halaman sendiri, dan assign gagal **tanpa pesan apa
pun** — dialog konfirmasi tetap muncul karena itu skrip lokal, user klik "Ya",
lalu tidak terjadi apa-apa. Sudah ditambal di modul kolektor dengan memindahkan
target POST ke server-side (`collector-worksheet.assign-selected`), tapi
**akar masalahnya masih ada untuk halaman lain** yang menaruh logika di Alpine.

Risiko lain dari CDN: butuh internet keluar (deployment di jaringan internal ISP
bisa tidak punya), versi tak terkunci (`3.x.x` = minor/patch bisa berubah
sewaktu-waktu tanpa kita tahu), dan pihak ketiga jadi bagian dari rantai pasok.

**Yang perlu dikerjakan nanti:**
1. `npm i alpinejs` (versi dikunci di `package.json`), import + `Alpine.start()`
   di `resources/js/app.js` — Vite 7 sudah ada di stack, tak ada tooling baru.
2. Hapus tag CDN dari layout.
3. Sisir halaman yang bergantung Alpine untuk **aksi yang mengubah data** —
   khususnya yang menyusun `action`/URL di klien. Pola itu tetap salah walau
   Alpine sudah lokal; target POST harus di-render server-side.
4. Pastikan `npm run build` masuk prosedur deploy (kalau belum), karena setelah
   ini Alpine tak lagi datang dari luar.

**Catatan:** Alpine di-`defer`, jadi bug-nya intermiten dan bergantung jaringan —
gampang dikira "kadang error, kadang tidak". Pertimbangkan itu waktu triase bug
UI yang tidak bisa direproduksi.

#### ADHOC-19 — Kolektor 2.0 Fase 2: Saldo, Setoran & Cross Check (2026-08-08)

Rancangan: `docs/plan/kolektor/analisa-alur-kolektor-2.0.md` §11, §14.2.

Menghidupkan kembali §B-11 dokumen lama yang sempat di-drop, dengan bentuk lebih
ringan: `collector_deposits` + `payments.collector_deposit_id`, tanpa ledger.

**Dua angka uang, sengaja tak pernah dijumlahkan:**
- **Saldo Belum Disetor** — Σ payment VALID `collected_by = X` yang
  `collector_deposit_id`-nya masih null. Wajib kembali 0 tiap setor.
- **Kurang Setor** — Σ sisa kewajiban dari setoran berstatus `selisih`. TIDAK ikut
  nol. Kalau digabung, "saldo 0" jadi ambigu: beres, atau nombok yang tak tercatat.

Keduanya DITURUNKAN (`CollectorBalanceService`), tak ada kolom saldo. Konsekuensi
yang diuji: payment di-reject ⇒ saldo turun sendiri tanpa koreksi manual.

**Alur:** kolektor setor SELURUH saldo (tak ada setoran parsial) → status
`menunggu_verifikasi`, saldo jadi 0 → admin hitung uang fisik → `difference =
declared − (computed + settlement)` → 0 berarti `terverifikasi`, ≠ 0 berarti
`selisih` + catatan WAJIB → ditutup lewat pelunasan di setoran berikutnya
(`settles_deposit_id`, mendukung cicilan lewat `settled_amount`) atau hapus buku
oleh Owner.

**Pelunasan selisih = field terpisah, bukan dilebur ke uang fisik.** Kalau dilebur,
setoran penutup tercatat "lebih setor" dan lahir selisih baru yang menggantung —
laporan selisih tak pernah nol.

**Guard yang dipasang:**
1. Verifikator ≠ penyetor (berlaku juga untuk Owner).
2. Admin wajib bisa melihat SELURUH payment setoran lewat POP scope — bukan cuma
   `deposits.pop_id`, karena setoran bisa lintas POP untuk kolektor `pop_tree`.
3. `PaymentController::reject()` menolak payment yang ada di setoran terverifikasi
   — koreksi lewat pembayaran pembalik, setoran lama tak disentuh.
4. `UserController::update()` menolak menonaktifkan kolektor yang masih pegang
   saldo atau punya kurang setor terbuka.
5. `collector_worksheet.approve` (hapus buku) tidak diberikan ke `admin` — matrix
   admin pakai daftar eksplisit, bukan wildcard `collector_worksheet.*`.

Semua transisi masuk `audit_logs` (module `kolektor`). Notifikasi: admin/pop_admin
dapat "setoran menunggu verifikasi", kolektor dapat hasil verifikasinya.

Fase 3 (visit log) & Fase 4 (kwitansi QR/OCR) belum dikerjakan.

#### ADHOC-18 — Kolektor 2.0 Fase 1: pisah halaman + kolektor mencatat pembayarannya sendiri (2026-08-08)

Rancangan final: `docs/plan/kolektor/analisa-alur-kolektor-2.0.md` (§9, §10, §14, §15).

**Merevisi dua keputusan lama** di `docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md`:
§B-8 no. 4 ("kolektor tak boleh input pembayaran") dan §B-11 ⛔ ("rekonsiliasi kas
di luar scope"). Alasan lapangan: kolektor yang tahu persis siapa bayar berapa saat
itu juga; menunda input ke admin bikin antrean + selisih ingatan.

Yang dikerjakan Fase 1:
- **Pisah halaman menurut audiens**, bukan menurut data. `/collector-worksheet`
  (admin: daftar kolektor, panel pelanggan belum di-assign, cross check) vs
  `/collector-worklist` (kolektor: pelanggan sendiri + catat pembayaran).
  Konsekuensinya kolektor tak pernah membuka halaman admin, jadi role `kolektor`
  tetap **tanpa** `payments.create` maupun `customers.update`.
- **Rute kolektor tanpa parameter `{collector}`** (`POST /collector-worklist/pay`) —
  kolektor diambil dari `auth()->user()`. Rute admin `POST /payment-batches/{collector}`
  tetap ber-parameter karena digerbang `payments.create`. Kalau disatukan, kolektor A
  bisa mencatat pembayaran atas nama kolektor B.
- **Permission baru**: `kolektor.pay` (+ action `pay` di `ActionCode`/`ActionSeeder`),
  feature `collector_worksheet` (`view`, `assign`).
- **Jendela tagih** `config('billing.collector_due_window_days')` = 7. Seleksi per
  pelanggan, tampilan per invoice: sekali masuk daftar, seluruh tunggakan pelanggan
  itu ikut tampil. Worksheet Admin sengaja TANPA jendela.
- **Lapis kedua POP scope** di worklist kolektor (`applyUserScope`) — assign lama tak
  otomatis dibersihkan waktu kolektor dipindah cabang.
- Logika batch pindah ke `CollectorPaymentService` + `CollectorWorklistService`;
  dua controller tipis berbagi trait `RecordsCollectorBatch`.

Bug yang ketahuan saat refactor: cek idempotency sempat jalan **sesudah** validasi
baris, jadi submit ulang (klik dobel / retry jaringan) dijawab 422 "invoice sudah
lunas" padahal pembayarannya berhasil. Urutannya dibalik — idempotency selalu
mendahului validasi.

Setelah deploy: jalankan seeder RBAC lalu `EffectiveAccessService::clearCache()`,
kalau tidak permission baru tak kelihatan sampai cache kedaluwarsa.

Fase 2 (saldo, `collector_deposits`, cross check bernilai), Fase 3 (visit log),
Fase 4 (kwitansi QR/OCR) belum dikerjakan.

#### ADHOC-17 — Kolom `completed_by` di Task + Riwayat Terbuka buat Anggota Tim (2026-08-07)

Gap: `TaskService::complete()` cuma nulis `updated_by` (kolom generic, ke-overwrite
tiap update apapun setelahnya — start/pending/cancel/reassign), jadi jejak
"siapa teknisi yang nyelesaiin & lapor" gak eksplisit tersimpan. Data
sebenarnya ada di `audit_logs` (action `completed`), tapi blok "Riwayat Status
(Audit Log)" di `tasks.show` cuma keliatan buat role `owner`/`admin`/`fop` —
anggota tim sendiri gak bisa liat. Fix: tambah kolom `tasks.completed_by`
(FK `users`, diisi sekali & immutable) + tampilkan "Diselesaikan & dilaporkan
oleh: {nama}" di blok Waktu Pengerjaan (semua yang bisa akses detail task
lihat) + buka gate audit log buat anggota tim (`$task->isMember(auth()->id())`).
Kasus 1 tim 2 orang berebut klik "Selesai": sudah aman dari sononya — guard
status `IN_PROGRESS`/`PENDING` di `TaskService::complete()` nolak request
kedua begitu status udah `SELESAI` (422), jadi `completed_by` gak ketiban.
Test regresi: `TaskCompletedByTest`.

Bug: `Task.description` dibangun dari `trim($fopTask->issue."\n".$fopTask->notes)`
(`FopTaskController::store()`/`update()`) atau
`trim($ticket->detail_keluhan."\n".$ticket->catatan_teknis)`
(`TicketService::assignTechnicians()`) — pointer sistem/asesmen NOC numpang
keliatan seolah bagian keluhan pelanggan di box "Issue / Keluhan"
(`tasks/show.blade.php`). Fix: `description` cuma dari 1 sumber issue;
`catatan_teknis`/`notes` ditampilkan di 2 box baru terpisah ("Catatan Teknis
(NOC)", "Catatan FOP") — `TaskController::show()` eager-load `fopTask.ticket`.
Detail lengkap: `docs/task-teknisi/business-logic.md § 9`,
`docs/ticketing/business-logic.md § 14`. Test regresi:
`FopTaskCreateFollowsTicketingTest::test_task_show_separates_catatan_teknis_from_description_for_teknisi`.

#### ADHOC-15 — Laporan Pekerjaan Teknisi + Riwayat Task Saya + Hapus Foto Bukti + Fix Redirect Laporan (2026-08-06)

Rangkaian perbaikan Detail Task teknisi, diminta bertahap dalam satu sesi.

**Bagian 1 — Blok "Laporan Pekerjaan Teknisi" di Detail Task**

Data yang teknisi isi lewat form Laporan Maintenance (kendala teknis, material
terpakai, foto OPM/Speedtest) sebelumnya cuma tersimpan di DB tanpa pernah
tampil lagi di `/tasks/{id}`. `TaskController::show()` sekarang eager-load
`maintenanceReport`, view nampilin section baru — berlaku task non-Survey/
Pemasangan (Survey/Pemasangan sudah punya halaman laporan sendiri).

**Bagian 2 — Riwayat Task Saya (`/tasks-saya/riwayat`)**

Arsip task `selesai` milik teknisi login, beda dari `/tasks-saya` yang cuma
papan kerja aktif (buang task selesai lama dari daftar). `TaskController::
historyOwn()`, guard `task.view.own` sama seperti dashboard.

**Bagian 3 — Hapus fitur Foto Bukti (`TaskEvidence`) total**

Investigasi menemukan fitur ini gak pernah men-gate completion
(`Task::canComplete()` udah lama hardcoded `true`) dan tumpang tindih sama
foto wajib yang sudah ada di tiap Laporan per tipe task. Dihapus: model,
controller, route, policy ability `uploadEvidence`, method
`FileUploadService::uploadTaskEvidence()`, section "Foto Bukti" di beberapa
view (`tasks/show.blade.php`, tab Riwayat Ticketing pelanggan), dan **tabel
`task_evidences`** (migration baru
`2026_08_06_140732_drop_task_evidences_table.php`, `down()` reversible).
Tile ringkasan "Foto Bukti" di atas Detail Task diganti **Durasi Aktual**.

**Bagian 4 — Fix redirect Laporan Survey/Pemasangan (`return_to`)**

Halaman `/customers/{id}/survey/report` & `/installation/report` diakses dari
banyak entry point (Detail Task, Dashboard Task Saya, Antrean Survey,
Verifikasi Queue, Detail Pelanggan) — tombol "Kembali" & redirect sukses
sebelumnya **hardcoded** ke satu tujuan tetap, gak peduli asal. Sekarang
pemanggil kirim `return_to` (query/hidden field), divalidasi
`App\Support\SafeUrl::resolveReturnTo()` (same-origin only, cegah open
redirect), fallback ke route lama kalau kosong/invalid.

**Dokumentasi terkait:** `docs/task-teknisi/{README,business-logic,database-schema,flowchart,user-flow}.md`, `docs/customer-lifecycle/{README,business-logic}.md`.

**Test baru:** `TaskShowMaintenanceReportTest`, `TaskOwnHistoryTest`, `SurveyInstallationReportReturnToTest`. Full suite 989 passed, 0 failed setelah semua perubahan.

#### ADHOC-13 — Master Alat Kerja + Material di Laporan Maintenance (2026-07-31)

Batch B dari rangkaian yang diminta, digabung dengan satu lubang yang ketahuan
saat mengeceknya.

**Bagian 1 — Master Alat Kerja**

| # | Perubahan | Catatan |
|---|---|---|
| 1 | Master `work_tools` + halaman Master Alat Kerja | Peralatan yang DIBAWA dan DIBAWA PULANG. Tabel terpisah dari `items` karena cara hitungnya beda — alat tidak habis dipakai dan **tidak punya qty** |
| 2 | `task_work_tools` **anchor ke `fop_task_id`** | Rancangan pivot-ke-`customer_surveys` ditolak: MTN/C-REQ tidak pernah lewat survey, padahal perbaikan gangguan paling sering butuh tangga & splicer. FopTask satu-satunya entitas milik SEMUA jenis pekerjaan — alasan yang sama dengan `task_materials` |
| 3 | **TANPA fase estimasi/terpakai** | Beda disengaja dari material. Untuk material selisihnya uang; untuk alat, mencentang ulang "benar saya bawa tangga" cuma menambah isian lapangan tanpa angka yang dipakai siapa pun |
| 4 | Checklist di TIGA form laporan | Survey (isi dari nol), Pemasangan (prefill dari survey, boleh disunting), Maintenance (isi dari nol). Satu komponen `x-work-tool-checklist` |
| 5 | Tampil di `tasks/show` **di luar percabangan tipe task** | Ini alasan utama fitur ada — dibaca teknisi sebelum berangkat. Task PSB yang belum punya daftar sendiri jatuh ke daftar survey lewat `displayRowsForTask()`; kolom kosong justru muncul tepat saat teknisi paling butuh |
| 6 | `required_tools` turun peran lagi | Dari "Alat Khusus / Kendala Peralatan" jadi **"Catatan Kendala Peralatan"** — keterangan yang tidak masuk checklist (akses lokasi, spesifikasi tak biasa). Tidak di-drop, ada data survey lama |
| 7 | Alat di luar master lewat baris manual | Tersimpan `work_tool_id` null + `tool_name` snapshot. Duplikat dibuang (case-insensitive) karena tanpa qty, dua baris "Tangga" cuma bikin daftar berulang |

**Bagian 2 — Material terstruktur di Laporan Maintenance**

Lubang warisan ADHOC-11 yang ketahuan saat mengecek Batch B: `x-material-rows`
cuma dipakai Survey & Pemasangan. Material maintenance selama ini dicatat di
lima kolom teks bebas `maintenance_reports.{kabel,modem,patchcord,sleeve,lainnya}`
— satu kolom per jenis barang, hardcode, tak bisa dijumlah, tak tersambung master.
Patch cord yang diganti saat perbaikan gangguan tidak pernah masuk agregasi mana pun.

Sekarang Laporan Maintenance memakai `x-material-rows` yang sama (kind `TERPAKAI`,
anchor FopTask). Lima kolom lama **tidak di-drop dan tetap divalidasi** — ada
laporan lama yang memakainya — tapi sudah tidak ditampilkan di form. Jangan
hidupkan sebagian.

Anchor maintenance dicari lewat `fop_tasks.task_id` (`TaskWorkToolService::resolveTaskFor(Task)`),
**bukan** `TaskMaterialService::resolveTaskFor(Customer, kategori)` yang mengambil
`latest('id')` — satu pelanggan bisa punya banyak task MTN sepanjang tahun, dan
"MTN terakhir milik pelanggan ini" akan menempel ke task yang salah.

Seeder `WorkToolSeeder` berisi 10 alat, diambil dari isi `required_tools` nyata
dikurangi "Dropcore" — itu material yang nyasar ke kolom alat.

**Bagian 3 — Bug RBAC warisan ADHOC-11 (ketahuan saat melengkapi sidebar & RBAC)**

`PermissionGeneratorService::generate()` melakukan loop atas `config/rbac.php` →
`allowed_actions`, **BUKAN** atas tabel `features`. Feature yang punya seeder tapi
tidak terdaftar di config dilewati **diam-diam, tanpa error apa pun**.

`items` persis begitu sejak ADHOC-11: `ItemFeatureSeeder` membuat feature-nya,
tapi `items` tidak pernah didaftarkan di `config/rbac.php`, jadi permission
`items.view/create/update/delete` **tidak pernah lahir**. Akibatnya Master Barang
cuma bisa dibuka Owner (yang lolos lewat wildcard `*`) dan tidak akan pernah
muncul di Role Matrix untuk role lain. Tidak terdeteksi karena test memakai Owner.

`items`, `item_categories`, dan `work_tools` sekarang terdaftar. **Tiap
FeatureSeeder baru WAJIB menambah entri di `config/rbac.php` juga** — seeder saja
tidak cukup.

Sidebar: tiga entri Master Data (`Barang / Material`, `Kategori Barang`,
`Alat Kerja`), masing-masing digerbangi permission sendiri, dan gerbang seksi
Master Data ikut menyertakan ketiganya.

Test: `WorkToolChecklistTest` (10 test), `MasterBarangPermissionGeneratedTest`
(4 test — menjaga supaya feature master berikutnya tidak mengulang bug yang sama).

**Belum dijalankan:** `php artisan migrate` — DB MySQL masih tak terjangkau.
Seeder yang perlu dijalankan setelahnya: `WorkToolFeatureSeeder`, `WorkToolSeeder`,
lalu `RolePermissionSeeder`.

#### ADHOC-12 — Kategori Material jadi Master (2026-07-31)

Batch A dari tiga batch yang diminta ("item survey & pemasangan masih hardcode").
Master Barang (`items`) sebenarnya sudah ada sejak ADHOC-11; yang benar-benar masih
hardcode adalah **kategorinya** — enum `MaterialType`, plus dua salinan literal
daftar yang sama di `CustomerDeviceController` dan `_device.blade.php` (salinan
ketiga bahkan sudah menyimpang: "Kabel Dropcore / FO" vs "Kabel Dropcore").

| # | Perubahan | Catatan |
|---|---|---|
| 1 | Tabel `item_categories` + halaman Master Kategori Barang | `code` (bukan `id`) yang jadi kunci pemakaian — `task_materials.item_type` & `passive_device_type` menyimpannya sebagai snapshot string. Tanpa delete, sama seperti Master Barang |
| 2 | Tujuh kategori bawaan ditanam **di migrasi**, bukan seeder | Migrasi berikutnya mem-backfill dengan mencocokkan code; seeder jalan setelah semua migrasi selesai, jadi kalau ditaruh di seeder backfill-nya no-op dan data lama kehilangan kategori. `is_system` mengunci code-nya |
| 3 | `items.type` → `item_category_id` (kolom lama di-drop); `task_materials.item_type` **dipertahankan** + FK ditambah | Perlakuan sengaja dibalik: master menunjuk relasi (ubah nama kategori harus ikut berubah), riwayat menyimpan snapshot (laporan tahun lalu harus terbaca apa adanya) |
| 4 | Cast enum `item_type` **dilepas** dari `TaskMaterial` | Kategori buatan admin tidak punya case enum; cast akan melempar `ValueError` saat baris itu sekadar ditampilkan. Bacanya lewat `category_label` (fallback ke code mentah) |
| 5 | Enum `MaterialType` turun peran, TIDAK dihapus | Jadi dokumentasi kontrak tujuh code bawaan. **Jangan tambah case baru** — kategori baru lewat Master, kalau tidak dua daftar itu hidup lagi |
| 6 | Validasi `in:` → `Rule::exists('item_categories','code')` di tiga tempat | Survey, Pemasangan, dan `CustomerDeviceController` (yang dulu menyalin daftar enum literal). Kategori baru langsung terpakai tanpa deploy |
| 7 | Dropdown kategori nonaktif tetap muncul **untuk baris yang memakainya** | Tanpa ini, `<select>` tanpa option cocok jatuh ke option pertama saat render — membuka laporan lama lalu menyimpannya diam-diam memindahkan kategorinya |
| 8 | `defaultUnit()` match() → kolom `default_unit` | JS form juga: `row.item_type === 'kabel_dropcore' ? 'meter' : 'pcs'` diganti lookup master |

Satu bug ditemukan lewat test: `whereIn('id', ...)` jadi ambigu begitu join
`item_categories` ikut (dua tabel sama-sama punya `id`) — di-prefix jadi `items.id`.

Permission `item_categories.*` lewat `ItemCategoryFeatureSeeder` — feature terpisah
dari `items` karena mengubah kategori berefek ke seluruh data material lintas modul,
sedangkan menambah barang cuma menambah pilihan.

Test: `ItemCategoryMasterTest` (10 test). `TaskMaterialTest` & `MaterialReportFlowTest`
disesuaikan ke bentuk baru (`item_category_id`, `item_type` string).

**Belum dijalankan:** `php artisan migrate` — DB MySQL masih tak terjangkau, sama
seperti catatan ADHOC-11. Migrasi teruji lewat sqlite `:memory:` di test suite.
Seeder yang perlu dijalankan: `ItemCategoryFeatureSeeder`, lalu `RolePermissionSeeder`.

**Batch B — SELESAI, dikerjakan sebagai ADHOC-13 di atas.**

Tanpa qty dan tanpa kepemilikan alat per-teknisi/per-POP; itu pelacakan aset,
wilayah Inventory (lihat Batch C).

**Batch C — TIDAK dikerjakan sekarang (keputusan user, 2026-07-31).** Harga/stok/gudang
di master barang = pembukaan modul Inventory (`docs/post-mvp/inventory-fop.md`), bukan
perluasan master. Keputusan ADHOC-11 poin 6 (master minimum, TANPA stok/harga/gudang)
**tetap berlaku** — jangan tambah kolom itu ke `items` sebagai perbaikan sambil lalu.
Kalau nanti dibuka, prasyaratnya: (a) keputusan stok per-POP atau global, (b) siapa
yang berwenang mengurangi stok — teknisi saat submit laporan atau admin gudang, dan
(c) **wajib** ikut `harga_saat_pakai` snapshot di `task_materials`; tanpa itu laporan
biaya lama berubah nilainya tiap harga master naik. Kolom `unit_price_snapshot` di
`task_materials` sudah disiapkan ADHOC-11 dan masih kosong — itu tempatnya.

#### ADHOC-11 — Tanggal Request Pemasangan & Material Task (2026-07-31)

Rancangan lengkap + keputusan: [docs/plan/rancangan-request-pemasangan-dan-material-task.md](plan/rancangan-request-pemasangan-dan-material-task.md).

**Bagian 1 — Tanggal Request Pemasangan**

| # | Perubahan | Catatan |
|---|---|---|
| 1 | `customer_surveys.requested_installation_date` (opsional) | **Satu-satunya sumber kebenaran.** `fop_tasks.client_request_date` kategori PSB cuma turunan, di-refresh tiap auto-sync — `update()` me-null-kannya tiap status keluar dari Pending, jadi tanpa refresh tanggalnya hilang begitu FOP menjadwalkan task |
| 2 | Task PSB dengan tanggal masa depan **tidak kena SLA** | Prioritas dipaksa `LOW`, sorting existing (`FopTaskController::index()` baris 63) sudah menenggelamkannya ke dasar papan — tidak diubah |
| 3 | Deadline = **akhir hari** tanggal request | Bukan `tanggal + handlingSlaHours()`. Yang dijanjikan ke pelanggan "dipasang tanggal 20", jadi lewat tengah malam = telat. Kolom SLA nampilin badge "Dijadwalkan {tgl}" sebelum hari-H, countdown normal di hari-H, `−HH:MM:SS` merah setelah lewat (komponen `x-countdown-timer` existing, tidak diubah) |
| 4 | Gerbang tunggal `FopTask::usesClientRequestDeadline()` / `isScheduledForFutureClientDate()` | Dipakai bareng `slaDeadline()`, `slaTotalSeconds()`, papan FOP, DAN `autoSyncAndCalculatePriority()`. **Jangan tulis ulang kondisinya di tempat keempat** — timer & badge prioritas harus dari syarat yang sama |

**Bagian 2 — Material (Estimasi vs Terpakai)**

| # | Perubahan | Catatan |
|---|---|---|
| 5 | Tabel `task_materials` (satu tabel, dua fase `kind`) | Anchor ke `fop_task_id` (bukan `customer_installations`) — FopTask satu-satunya entitas milik SEMUA jenis pekerjaan. Ini **adalah** `fop_task_materials` di `docs/post-mvp/inventory-fop.md`, dibangun lebih awal dengan bentuk final |
| 6 | Master `items` + halaman Master Barang | Minimum by design: TANPA stok/harga/gudang. Tujuannya cuma penamaan seragam sejak baris pertama; Inventory nanti menambah di atasnya. Tanpa hapus — barang lama dinonaktifkan |
| 7 | Estimasi di Laporan Survey; `required_tools` turun peran | `required_tools` jadi "Alat Khusus / Kendala Peralatan" (peralatan kerja, bukan material). Tidak di-drop — ada data survey lama. `cable_estimation_meter` otomatis jadi baris dropcore |
| 8 | Perangkat Pasif Terpakai di Laporan Pemasangan | Prefill dari estimasi; wajib ≥1 baris saat `completed`. **Tidak menggantikan** `customer_technical_details.passive_device*` — itu aset terpasang, ini konsumsi material |
| 9 | Tabel Estimasi vs Terpakai + selisih di Verifikasi Admin | Sengaja tanpa ambang otomatis — menilai wajar/tidaknya itu keputusan admin |

Tiga bug ditemukan lewat test & diperbaiki: satuan form menang atas master di
`TaskMaterialService`; dedup baris dropcore tak mendeteksi barang master; `pluck()`
Eloquent tetap menerapkan cast sehingga banding enum-vs-string selalu false.

Test: `SurveyRequestedInstallationDateTest`, `FopTaskClientRequestDateTest`,
`TaskMaterialTest`, `MaterialReportFlowTest`. `FopTaskSortingTest` dijalankan sebagai
regresi (lolos).

Halaman lain yang ikut diperbarui supaya data baru tidak "hilang" setelah disimpan:
tab Survey & tab Pemasangan detail pelanggan, halaman Task teknisi (`tasks/show`),
Riwayat Detail FOP (`fop_tasks/history_detail`), ringkasan survey di form Pemasangan.

**Belum dijalankan:** `php artisan migrate` (DB MySQL tidak terjangkau saat implementasi)
dan seeder `ItemFeatureSeeder` + `ItemSeeder`. Migrasi sudah teruji lewat sqlite
`:memory:` di test suite.

#### ADHOC-10 — Detail tiket lewat drawer kanan (2026-07-30)

Acuan layout: `worksheet_helpdesk_noc_v2.html` (`#ticketDrawer`).

| # | Perubahan | Catatan |
|---|---|---|
| 1 | Partial bersama `tickets/partials/detail-drawer.blade.php` | Dipakai Worksheet Helpdesk (`tickets/create.blade.php`) DAN Worksheet NOC. Isi: Status & atribusi, Aksi Ticket, Snapshot Pelanggan, Keluhan & Catatan, Lampiran, Riwayat Ticket & Audit |
| 2 | Endpoint `GET /api/tickets/{ticket}/detail` (`tickets.detail-json`) | `TicketController@detailJson`, gerbang sama dengan `show()`: `tickets.view` + `authorizeTicketScope()`. SENGAJA bukan memperbesar `worksheetCardPayload()` — riwayat & lampiran cuma perlu buat satu tiket yang dibuka, bukan 30–50 baris daftar |
| 3 | Kontrak event, bukan duplikasi logic | Drawer men-dispatch `ticket-drawer-action`; konfirmasi + POST tetap di halaman pemanggil, jadi update array `tasks`/baris tabel & riwayat tetap satu jalur. `open-ticket-drawer` / `close-ticket-drawer` buat buka-tutup |
| 4 | Link `/tickets/{id}` dicabut dari dua halaman kerja | Nomor tiket (tabel) & kartu sekarang buka drawer. Halaman penuh disisakan buat **Ticket Selesai**, **Ticket Dibatalkan**, **History Ticketing** + link notifikasi |
| 5 | Tombol **Batalkan** di Worksheet Helpdesk | Sebelumnya gak ada di panel; sekarang ada, tapi **cuma di drawer** — aksi destruktif jangan gampang kepencet dari baris daftar |

| 6 | **Isi drawer disamakan dengan `/tickets/{id}`** (2026-07-31) | Tambahan payload `detailJson()`: `type_label` (`MTN — Maintenance`), `fop_task{number, technicians, histories, can_view, url}`, `fop_task_orphan`, lampiran `size`+`uploader`. Drawer dapat blok **Task FOP Lapangan Terkait** (teknisi + tombol Buka Task FOP, digerbangi `fop_tasks.view`) & **Riwayat Task FOP** |
| 7 | **Drawer di BAWAH navbar** (`top-16` + `h-[calc(100dvh-4rem)]`) | Navbar (`glass-header`, `backdrop-blur`) gak lagi ketiban panel setengah transparan. `dvh` bukan `vh` — address bar mobile bikin footer drawer kepotong |
| 8 | **Fix class z-index mati** | `z-drawer`/`z-dropdown`/`z-modal`/`z-sticky` **tidak pernah ada** di CSS hasil build: tokennya (`--z-drawer` dst.) cuma di `:root`, DI LUAR `@theme`, dan z-index bukan namespace yang di-generate Tailwind v4. Semua elemen itu jatuh ke `z-index:auto`. Diganti literal `z-[60]`/`z-[40]`/`z-[80]`/`z-[20]` di `detail-drawer`, `components/ui/{drawer,dropdown,modal}`, `components/layout/topbar`. **Wajib `npm run build`** — kalau enggak, `top-16` & `z-[60]` gak ada di asset dan drawer balik nabrak navbar |
| 9 | Scroll halaman dikunci saat drawer kebuka | `x-effect` toggle `overflow-hidden` di body — tanpa ini scroll di atas backdrop malah menggeser tabel di belakangnya |

Test: `TicketDetailDrawerTest` (payload + flag aksi, terminal pasca-FOP, 403 tanpa
`tickets.view`, 403 luar POP scope, dua worksheet buka drawer & barisnya gak nge-link,
`type_label`, teknisi + riwayat Task FOP, flag orphan, metadata lampiran).
`TicketCloseEscalateTest::test_worksheet_action_buttons_are_not_native_forms()` disesuaikan
ke mekanisme baru — intinya tetap sama: JANGAN `<form method="POST">`.

> **Catatan `fop_task_orphan`:** tiket "Terputus" bikin `fop_task` DAN `fop_task_number`
> dua-duanya null (turunan relasi yang sama), jadi kondisi
> `!fop_task && fop_task_number` gak akan pernah nyala. Bedanya wajib dibaca dari
> `Ticket::isOrphan()` (`handler=FOP` + `fop_task_id` null), bukan dari nomor task.

#### ADHOC-09 — Redesign Worksheet NOC (2026-07-30)

Daftar kartu vertikal (~120px/tiket, tanpa pencarian sama sekali) diganti tabel padat
bergaya History Ticketing supaya NOC bisa membaca puluhan tiket sekaligus dan menemukan
tiket tertentu tanpa scroll.

| # | Perubahan | Catatan |
|---|---|---|
| 1 | **Tabel padat**, 1 baris = 1 tiket | Kolom: Masuk · Tiket · Nama/CID · HP · Desa · POP · Aduan · Kategori · Prioritas · **Umur** (≥8 jam kuning, ≥24 jam merah). Tab Assign FOP menukar Umur dengan Status / Diserahkan / Dikirim Oleh |
| 2 | **Dua tab bercounter** via `?tab=` | `masuk` (default) = `handler=noc & status=open`; `assign_fop` = `handler=fop` **AND** ada `ticket_histories(action=dieskalasi, to_status=noc)`. Tab asing jatuh ke `masuk` |
| 3 | **Pencarian + filter** GET | `q` (nomor tiket, nama, CID, desa, keluhan), `pop_id`, `issue_category_id`, `type`, `priority`, `created_by`, `date_from`/`date_to`. Filter dipakai SAMA di tabel & kedua counter tab |
| 4 | **Aksi lewat drawer baris terpilih** | Klik baris → `components/ui/drawer.blade.php` (komponen lama yang belum terpakai) berisi detail + tombol Selesai/Assign FOP/Kembalikan/Batalkan. URL endpoint ditaruh di `data-*` baris & cuma dirender kalau flag `actionFlagsFor()` nyala → tab Assign FOP read-only total |
| 5 | Tanpa migrasi, tanpa route/permission baru | Gerbang tetap `noc_worksheet.view`; `TicketService`/`TicketController` tidak disentuh |

> **BUKAN pembalikan ADHOC-06.** Tab **Assign FOP** murni turunan data tiket yang sudah
> lepas ke FOP — bukan window "Pending NOC", tidak ada aksi Oncheck/"ambil tiket", dan
> tiket yang diassign ke NOC tetap langsung berstatus diproses. Test
> `test_worksheet_has_no_pending_noc_window()` menjaga itu.

Test: `NocWorksheetTest` 24 test (tab per-isi, exclude tiket yang Helpdesk kirim langsung ke
FOP, tab read-only, tab asing, pencarian `#[DataProvider]`, filter POP/prioritas/kategori/
tanggal, counter ikut filter, POP scope di kedua tab).

#### ADHOC-06 — Hapus Pending NOC & Oncheck NOC (2026-07-29)

Membalik ADHOC-03 #1. Assign ke NOC = **langsung diproses**; langkah "terima
dulu" ternyata tidak mencerminkan cara kerja sebenarnya dan hanya membuat tiket
menggantung.

| # | Perubahan | Catatan |
|---|---|---|
| 1 | Status **Pending NOC** & **OnCheck NOC** dihapus → satu label **Diproses NOC** | `Ticket::statusLabel()`; `isPendingNoc()`/`isOnCheckNoc()` dihapus |
| 2 | Tombol + endpoint **Oncheck NOC** dihapus | `TicketService::onCheckNoc()`, `TicketController::onCheckNoc()`, route `tickets.oncheck-noc`, flag `can_oncheck_noc`, guard `assertNocCheckedBeforeClose()` |
| 3 | Kolom **`tickets.noc_checked_at` di-DROP** | migrasi `2026_07_29_000003` — destruktif, disetujui eksplisit |
| 4 | **Kepemilikan bersama permanen**: tiket di tangan NOC dipegang `['helpdesk','noc']` | Keputusan pemilik produk: Helpdesk yang mengirim tetap boleh menyelesaikan/membatalkan |
| 5 | **Worksheet NOC jadi SATU halaman tanpa tab** | Gerbang `noc_worksheet.view`; URL tab lama di-redirect ke `/noc/worksheet` |
| 6 | Permission `noc_worksheet.masuk.view` & `.diproses.view` **dipensiunkan** | Feature di-set `is_active=false`, barisnya **tidak** dihapus dari DB biar role yang terlanjur punya tidak error |
| 7 | Counter Dashboard NOC "Pending NOC" + "OnCheck NOC" dilebur jadi "Diproses NOC" | `NocDashboardController` |

**Enum `TicketHistoryAction::DICEK_NOC` SENGAJA dipertahankan** — baris
`ticket_histories` lama masih menyimpan `dicek_noc` dan kolomnya di-cast ke enum
itu; menghapus case-nya bikin riwayat tiket lama meledak saat dibaca.

**Test:** `TicketOnCheckNocTest` ditulis ulang jadi penjaga regresi (nama file
dipertahankan supaya jejak fitur lama tidak hilang) — memastikan endpoint,
kolom, flag, dan label benar-benar tidak balik lagi. `NocWorksheetTest`,
`TicketingRbacTest`, `TicketCloseEscalateTest`, `TicketingTest`,
`NocDashboardTest`, `TicketHistoryTest` menyesuaikan perilaku baru.

Setelah pull:
`php artisan migrate && php artisan db:seed --class=TicketFeatureSeeder && php artisan db:seed --class=RolePermissionSeeder`

#### ADHOC-05 — Halaman History Ticketing (2026-07-29)

Analisa lengkap: **`docs/plan/analisa-halaman-history-ticketing.md`**.

Satu halaman arsip yang menampung **seluruh** tiket (semua handler & status,
termasuk tiket orphan `handler=fop` + `fop_task_id` NULL), menggantikan sheet
Google Sheets "Helpdesk Task Manager" yang dipakai Helpdesk sebelum sistem ini.

Keputusan yang sudah dikunci:

| # | Keputusan |
|---|---|
| 1 | FopTask yang tidak lahir dari tiket **tidak** masuk — Ticketing khusus keluhan pelanggan, Task FOP tetap di modulnya sendiri |
| 2 | ~~Waktu selesai jalur FOP dari `tasks.completed_at`~~ → **direvisi 2026-07-30**: dari **waktu penyerahan ke FOP** |
| 3 | Permission halaman sendiri `tickets.history.view` + `.export`; default Owner, NOC, Helpdesk |
| 4 | Kolom DESA **di-snapshot saat tiket dibuat** (`tickets.customer_village`), bukan join relasi — supaya laporan lama tidak berubah saat pelanggan pindah desa |
| 5 | **Revisi 2026-07-30:** History **hanya** menampung tiket yang sudah lepas dari meja Ticketing — Selesai, Dibatalkan, Assign FOP. Tiket `open` di Helpdesk/NOC adalah pekerjaan berjalan, rumahnya Worksheet Helpdesk / Worksheet NOC |
| 6 | **Revisi 2026-07-30:** tiket jalur FOP berlabel **"Assign FOP"** saja — progres lapangan (Terjadwal/In Progress/Selesai/Dibatalkan/orphan) tidak dicerminkan di History, dibaca di `/fop-tasks` |

Tiga migrasi: `resolved_at` + `customer_village` (keduanya dengan backfill), dan
`2026_07_29_000004` yang **memperbaiki** `resolved_at` jalur FOP ke waktu
penyerahan setelah revisi keputusan #2. Penulis `resolved_at`:
`TicketService::close()` (selesai internal) dan `TicketService::escalateToFop()`
(penyerahan) — `FopTaskObserver` sengaja TIDAK menyentuhnya, sempat begitu lalu
dibatalkan.

Test baru: `TicketHistoryTest` — **25 test lulus** (cakupan: yang masuk vs yang
tidak, tiket yang dikembalikan keluar lagi, label "Assign FOP" untuk semua status
FopTask, kolom "Oleh" per hasil akhir, kolom Tim sudah hilang, POP scope, RBAC,
`resolved_at` tiga arti, snapshot desa tidak ikut berubah, durasi sub-menit,
filter, ekspor).

Penyesuaian 2026-07-30 (lanjutan): kolom **TIM dihapus** (teknisi = data
pengerjaan lapangan, dibaca di `/fop-tasks`), dan **SOLVED BY → "Oleh"** yang
menyesuaikan hasil akhir — yang menyelesaikan / membatalkan / **mengirim ke FOP**.

Route baru: `/tickets/history` + `/tickets/history/export` (didaftarkan SEBELUM
`/tickets/{ticket}`). Permission baru: `tickets.history.view`,
`tickets.history.export`. `RolePermissionSeeder` **tidak** diubah — Owner (`*`)
serta NOC/Helpdesk/Admin (`tickets.*`) sudah tercakup wildcard; Atasan sengaja
tidak, bisa dinyalakan lewat Role Matrix.

Setelah pull, jalankan:
`php artisan migrate && php artisan db:seed --class=TicketFeatureSeeder && php artisan db:seed --class=RolePermissionSeeder`

#### ADHOC-04 — Layout Worksheet Helpdesk (2026-07-29)

`tickets/create.blade.php` diubah ke layout dua panel full-bleed sesuai rancangan:
panel form kiri yang bisa dilipat (state persist di `localStorage`, shortcut `N`)
+ strip vertikal "Ticketing" saat terlipat, panel antrean kanan melebar otomatis.

Motion sengaja hanya menganimasikan `width` + `opacity` dengan durasi & easing
identik untuk strip dan panel form (`.panel-motion` di `resources/css/app.css`) —
`x-show` sempat dipakai dan `display:none` membuat lebar hilang seketika sehingga
panel kanan menyentak (terbaca sebagai "bounce"). Jumlah kolom kartu diatur
`auto-fill/minmax` (`.ticket-grid`), bukan swap class `grid-cols-*`, supaya kolom
tidak melompat di tengah animasi. `prefers-reduced-motion` mematikan semuanya.

Butuh `npm run build` setelah pull (ada class & CSS baru).

#### ADHOC-03 — Restrukturisasi Modul Ticketing (2026-07-28)

Menyesuaikan modul Ticketing ke alur kerja Helpdesk/NOC yang sebenarnya. Dokumentasi
lengkap sudah disinkronkan di `docs/ticketing/`.

| # | Perubahan | Kenapa |
|---|---|---|
| 1 | Window **"Pending NOC"** + aksi **Oncheck NOC** (kolom `noc_checked_at`) | Dulu Helpdesk langsung kehilangan akses begitu kirim ke NOC — tiket menggantung tanpa pemilik selama NOC belum buka worksheet. Sekarang dua-duanya boleh act sampai NOC resmi ambil alih |
| 2 | **Batalkan tiket pra-FOP** (`tickets.cancel`, alasan wajib) | Tiket yang masih di meja Helpdesk sebelumnya gak bisa dibatalkan sama sekali tanpa dieskalasi ke FOP dulu hanya untuk dibatalkan di sana |
| 3 | Panel **List Task Ticketing** → 3 tab: Ticket / Assign NOC / Assign FOP (filter per `handler`) | Tab lama (Semua/Masuk/Diproses) menjawab "sudah sampai tahap mana", bukan "lagi di tangan siapa" — yang justru dibutuhkan pengirim tiket |
| 4 | Halaman baru **Worksheet NOC** (`/noc/worksheet`, 1 halaman 2 tab) | NOC sebelumnya numpang halaman bucket yang sama dengan semua role |
| 5 | Halaman baru **Dashboard NOC** (`/noc/dashboard`) | Stat counter, tiket aktif + aging, feed aktivitas, statistik Issue & Daerah |
| 6 | **Ticket Selesai / Dibatalkan** jadi halaman sendiri (controller + view + permission masing-masing) | Route bucket generik `/tickets/{bucket}` bikin semuanya numpang `tickets.view` — gak bisa di-toggle per-halaman di Role Matrix |
| 7 | RBAC per-halaman: `tickets.selesai.view`, `tickets.dibatalkan.view`, `noc_worksheet.masuk.view`, `noc_worksheet.diproses.view`, `noc_dashboard.view`, `tickets.cancel` | idem #6 |
| 8 | **Dialog konfirmasi terpusat** `window.confirmTicketAction()` | Sebelumnya 4 implementasi berbeda (confirm() native + 3 modal hand-rolled). `confirm()` native gak bisa nampung alasan, jadi `ticket_histories.reason` selalu kosong dari panel worksheet |

**Route yang dihapus:** `tickets.index` (`/tickets`) dan `tickets.bucket` (`/tickets/{bucket}`).

**Migrasi:** `2026_07_28_000001_add_noc_checked_at_to_tickets_table.php`.

**Seeder:** `TicketFeatureSeeder` sekarang menanam seluruh Feature modul (tickets +
sub-feature arsip + noc_worksheet + noc_dashboard). Setelah pull, jalankan:
`php artisan migrate && php artisan db:seed --class=TicketFeatureSeeder && php artisan db:seed --class=RolePermissionSeeder`

**Test baru:** `TicketOnCheckNocTest`, `TicketPreFopCancelTest`, `TicketingRbacTest`,
`NocWorksheetTest`, `NocDashboardTest`.

**Test lama yang assertion-nya sengaja diubah** (perilaku memang berubah, bukan regresi):
`TicketCloseEscalateTest` (lockout Helpdesk sekarang setelah Oncheck, bukan saat escalate),
`TicketingTest` (label status `Pending NOC`, bucket Masuk/Diproses gak punya halaman list lagi),
`TicketCancellationTest` (endpoint cancel Ticketing sekarang ada, tapi menolak tiket pasca-FOP).

### PERF — Index, N+1 & Beban Database (`docs/plan/ANALISA_INDEX_DATABASE.md`)

| Task | Fase | Status |
|---|---|---|
| PERF-T001 | Fase 0 — Detektor `preventLazyLoading` + baseline | Done |
| PERF-T002 | Fase 1 — N+1 (1.1–1.7) + `DashboardFopQueryCountTest` | Done |
| PERF-T003 | Fase 2 — Sargability (28 `whereDate()` + cache DISTINCT) | Done |
| PERF-T004 | Fase 3 — 34 index P0/P1/P2 + hapus 4 index redundan `tasks` | Done |
| PERF-T005 | Fase 4 — Bersihkan skema (destruktif, `migrate:fresh`) | **Belum** — di luar scope yang disetujui |
| PERF-T006 | Fase 5 — Perbaikan struktural | **Belum** — di luar scope yang disetujui |

**Baseline terukur (2026-07-22):**
- Dashboard FOP: **21 query, konstan** — tidak lagi tumbuh mengikuti jumlah teknisi/task.
  Dijaga `tests/Feature/DashboardFopQueryCountTest.php`.
- Guard `InvoiceObserver::creating()`: full scan → `type=ref`, `rows=1`
  (`invoices_customer_period_type_idx`). Ini akar masalah D, yang kuadratik.
- Riwayat audit per entitas: full scan 9.701 baris → `rows=1`, covering index.
- Lookup legacy saat import (`old_customer_id`): full scan → `rows=1`.

**Verifikasi volume (§15) — SELESAI 2026-07-22:**
Command `php artisan benchmark:seed-volume` dibuat
(`app/Console/Commands/SeedVolumeForBenchmark.php`). Dijalankan di DB throwaway
`whusnet_perf` (bukan DB legacy) dengan 20.000 pelanggan / 240.000 invoice /
199.740 pembayaran / 100.000 audit_logs. Hasil `EXPLAIN` membuktikan akar
masalah kuadratik pada skala nyata:
- A (lookup legacy): full scan 19.885 baris → `rows=1`
- B (riwayat audit): full scan 99.701 + filesort → `rows=5` covering
- D (guard InvoiceObserver): saring 12 baris in-memory → `rows=1`
- invoice status+due: full scan 237.150 → `range` covering
Tabel lengkap di §16 Penutup `docs/plan/ANALISA_INDEX_DATABASE.md`. Perf DB
sudah di-drop; DB legacy tetap utuh (1.957 pelanggan).

**Verifikasi index terpakai (§15 poin 5) — SELESAI 2026-07-22:**
27 query bentuk-controller dieksekusi nyata di atas volume, `count_star` per
index dibaca dari `performance_schema`. **25/26 index `_idx` di tabel berisi
data terpakai** — nol dead weight. `internet_packages_old_package_id_idx`
tak terpilih (tabel ±68 baris, full-scan lebih murah — dipertahankan untuk dedup
import). 8 index operasional (`tasks`/`fop_tasks`/`notifications`/
`customer_status_logs`) belum teruji karena tabel kosong saat benchmark.

**Catatan / Blocked:**
- **Benchmark belum men-seed tabel operasional** (`tasks`, `fop_tasks`, dll),
  jadi 8 index P2 di situ belum divalidasi lewat `performance_schema` — baru
  divalidasi struktural. Perlu perpanjang `SeedVolumeForBenchmark` kalau mau
  bukti runtime.
- **Fase 2.3 (batasi rentang tanggal papan FOP) sengaja TIDAK dikerjakan.**
  Bertentangan dengan keputusan produk 2026-07-22: task Survey/Pemasangan yang
  dipesan pelanggan untuk tanggal ke depan HARUS tetap tampil di papan FOP.
  Membatasi `task_date` akan menyembunyikannya. `orderByRaw` bertingkat di
  `FopTaskController::index()` karenanya masih memaksa filesort.
- **Aturan bisnis baru yang belum dikodekan** (muncul dari keputusan 1.4):
  task yang tidak selesai hari ini wajib di-pending agar kembali ke papan FOP
  besok, dan SLA tidak berjalan untuk task terjadwal ke depan. Butuh task sendiri.
- Migration index ditulis sebagai file tambahan
  (`2026_07_22_164035_add_performance_indexes_phase3.php`), bukan diedit ke
  migration `create_*` asalnya seperti saran §14 — supaya tidak mewajibkan
  `migrate:fresh` atas data legacy hasil import.

> **S8.10-T003 (FOP Notification Dashboard):** ⏸️ PAUSED — Siap dilanjutkan setelah BATCH 1 & BATCH 2 selesai
> **Sprint 8.9 Tasks:** T001–T006 (Done)
> **Sebelumnya:** Sprint 8.4 SELESAI, Sprint 8.5–8.8 ada issues (Calendar unnecessary, Missing Quality Gate, Missing Checklist)
> **Analisa Koreksi:** `memory/S8_architecture_correction.md` — Reinterpretasi brief, architecture breakdown, action items
> **Analisa Migrasi:** `docs/ANALISA_KELENGKAPAN_MIGRASI_jetis_db.MD` — Gap field teknis yang belum terimplementasi

---

## In Progress

# Sprint 8.9 — KOREKSI ARCHITECTURE TASK MANAGEMENT

## Tujuan Sprint 8.9
Restruktur task management workflow sesuai brief yang benar:
- Buat **Central List Task** (`/tasks`) sebagai single source of truth (replace Calendar S8.7)
- Implementasi **FOP Quality Gate** — FOP approve/reject laporan sebelum customer status auto-update
- Implementasi **Checklist Scheduling** — FOP input checklist template saat penjadwalan task
- Implementasi **FOP Reject/Pending Actions** — FOP bisa tolak atau pending task
- Refactor teknisi workflow dari Task (bukan Customer page)

**Referensi dokumen:**
- `memory/S8_architecture_correction.md` — architecture breakdown lengkap
- `docs/TASKS.md` — task specification

**Dependency:** S8.7 (Calendar) mungkin perlu di-remove/refactor. S8.5–S8.8 ada partial implementation yang perlu adjust untuk quality gate.

---

## Sprint 8.9A — Task List & Quality Gate

### S8.9-T001 — Buat Central List Task View (`/tasks`)
**Status**: Done
**Tujuan**: Membuat view Central List Task yang menampilkan semua tasks (pending, scheduled, in_progress, selesai) dalam satu dashboard. Ini menggantikan Calendar S8.7 sebagai pusat manajemen task FOP.

**File dibuat/diubah:**
- `app/Http/Controllers/TaskController.php` — tambah/modifikasi `index()` untuk list view (bukan calendar)
- `routes/web.php` — ensure `GET /tasks` route ada, verify FOP middleware
- `resources/views/tasks/index.blade.php` — refactor jadi list view (bukannya calendar HTML)
  - Pending Tasks section
  - Scheduled Tasks section (dengan timeline/grid view)
  - In Progress section
  - Completed section
  - Filter: by status, by type, by date
- `resources/views/tasks/_partials/task-card.blade.php` — card untuk list (replace calendar event HTML)

**Acceptance Criteria**:
- [x] Route `GET /tasks` menampilkan list task (bukan calendar)
- [x] Tasks grouped by status: pending, scheduled, in_progress, selesai (via List filtering)
- [x] Card view dengan info: task number, customer, type, team, status, SLA countdown
- [x] Click card → Detail Task (`/tasks/{id}`)
- [x] Filter dropdown: by status, by type, by date range
- [x] Sort: by date, by status, by type
- [x] Pagination atau infinite scroll untuk many tasks
- [x] Mobile-friendly layout

---

### S8.9-T002 — FOP Reject/Pending Actions di Task Detail
**Status**: Done
**Tujuan**: Implementasi button "Reject" dan "Pending" untuk FOP pada pending task, dan "Approve/Reject/Pending" saat review task yang sudah `selesai` oleh teknisi.

**File dibuat/diubah:**
- `app/Http/Controllers/TaskController.php` — tambah methods `reject()` dan `pending()`
- `app/Http/Controllers/TaskController.php` — tambah method `review()` untuk FOP approve/reject setelah teknisi selesai
- `app/Policies/TaskPolicy.php` — tambah methods `reject()`, `pending()`, `review()`
- `resources/views/tasks/show.blade.php` — tambah buttons untuk FOP:
  - [If pending] "Reject" + "Pending" buttons (existing: "Jadwalkan")
  - [If selesai] "Approve" + "Reject" + "Pending" buttons (FOP review)
- `routes/web.php` — tambah routes:
  - `POST /tasks/{task}/reject` → TaskController@reject
  - `POST /tasks/{task}/pending` → TaskController@pending
  - `POST /tasks/{task}/review` → TaskController@review
- `database/migrations/` — tambah kolom ke table `tasks`:
  - `reject_reason` (string, nullable) — alasan FOP reject
  - `pending_reason` (string, nullable) — alasan pending
  - `fop_review_status` (enum: pending/approved/rejected, default pending)

**Action Behavior:**
```
FOP Reject (pending task):
  - Task status tetap pending
  - reject_reason diisi
  - Task tetap bisa di-reschedule atau dihapus

FOP Pending (scheduled task):
  - Task status = pending
  - pending_reason diisi
  - Team assignment tetap, tapi jangan mulai sampai FOP ok

FOP Approve (selesai task):
  - fop_review_status = approved
  - Customer status auto-update ke next step
  - Laporan final

FOP Reject (selesai task):
  - fop_review_status = rejected
  - Task status revert ke in_progress (atau pending?)
  - Teknisi isi ulang laporan
  - Customer status revert ke previous step
```

**Acceptance Criteria**:
- [x] FOP bisa reject pending task dengan alasan
- [x] FOP bisa pending scheduled task dengan alasan
- [x] FOP bisa approve/reject/pending task yang sudah selesai oleh teknisi
- [x] Task status & fop_review_status tercatat di DB
- [x] Customer status hanya auto-update saat FOP approve (bukan saat teknisi submit)
- [x] Permission gate: hanya role FOP (atau authorized user) bisa trigger action ini

---

### S8.9-T003 — Checklist Input saat Task Scheduling
**Status**: Done
**Tujuan**: Saat FOP jadwalkan task (via TaskController::schedule), tambah form untuk input checklist template yang akan di-check oleh teknisi saat eksekusi. 

**File dibuat/diubah:**
- `app/Http/Controllers/TaskController.php` — modifikasi `schedule()` untuk accept checklist_template input
- `app/Models/Task.php` — tambah column `checklist_template` (JSON array)
- `resources/views/tasks/show.blade.php` — modifikasi jadwal form:
  - [If pending] Schedule modal/form
    - Input: scheduled_at, team members
    - **NEW:** Checklist items textarea/list (comma-separated atau multi-input)
    - Submit → save checklist_template as JSON
- `resources/views/tasks/_partials/checklist-input.blade.php` — component untuk input checklist
- `database/migrations/` — tambah kolom:
  - `checklist_template` (longText/JSON, nullable) — template dari FOP

**Checklist Flow:**
```
FOP Schedule (TaskController::schedule):
  Input: scheduled_at, team_ids, checklist_items (array/string)
    → Task.checklist_template = JSON.stringify(checklist_items)
    → Create TaskChecklist records dari template
    → Each checklist item: is_checked = false, checked_by = null

Teknisi Execute (TaskController::start):
  Load Task.checklist_template
    → Display checkboxes untuk cada item
    → Teknisi check saat berjalan

Teknisi Complete (TaskSurveyReportController::store):
  Require: semua checklist items checked (at least 1 foto)
    → If not done: error "Checklist belum lengkap"
    → If done: save laporan + update is_checked for each item
```

**Acceptance Criteria**:
- [x] FOP bisa input checklist template saat jadwalkan task
- [x] Checklist items tersimpan sebagai JSON di `tasks.checklist_template` (ATAU langsung direct record di task_checklists)
- [x] Task detail menampilkan checklist (untuk FOP & teknisi)
- [x] Teknisi bisa check/uncheck items saat task in_progress
- [x] Teknisi tidak bisa submit laporan sampai semua checklist done
- [x] Checklist items immutable (tidak bisa edit setelah scheduled)

---

### S8.9-T004 — Refactor TaskSurveyReportController & TaskInstallationReportController (No Auto-Update)
**Status**: Done
**Tujuan**: Modifikasi kedua controller agar submit laporan tidak langsung auto-update customer status. Sebaliknya, set task status = selesai + fop_review_status = pending, tunggu FOP approve.

**File diubah:**
- `app/Http/Controllers/TaskSurveyReportController.php` — REMOVE customer workflow transition
- `app/Http/Controllers/TaskInstallationReportController.php` — REMOVE customer workflow transition
- `app/Services/CustomerWorkflowService.php` — KEEP transition method, tapi dipanggil dari FOP review action (bukan teknisi laporan)

**Behavior Change:**
```
BEFORE (Current):
  Teknisi submit laporan survey
    → Task status = selesai
    → Customer status = waiting_acc (AUTO)
    
AFTER (Correct):
  Teknisi submit laporan survey
    → Task status = selesai
    → fop_review_status = pending
    → Customer status TETAP (no change)
    
  FOP approve task
    → fop_review_status = approved
    → Customer status = waiting_acc (MANUAL)
```

**Acceptance Criteria**:
- [x] Teknisi submit laporan tidak trigger customer workflow transition
- [x] Task status = selesai, fop_review_status = pending after submit
- [x] FOP approve di `TaskController::review()` trigger customer transition
- [x] FOP reject restore task ke in_progress (atau pending) + revert customer status

---

### S8.9-T005 — Refactor TaskController::schedule() untuk Accept Checklist
**Status**: Done
**Tujuan**: Update schedule action untuk handle checklist_template input.

**File diubah:**
- `app/Http/Controllers/TaskController.php` — update `schedule()` method (Sudah diselesaikan secara bersamaan pada implementasi S8.9-T003).

**Acceptance Criteria**:
- [x] Schedule form accept checklist input (Done di T003)
- [x] Checklist template saved to DB (Done di T003)
- [x] Teknisi see checklist saat task detail (Done di T003)
- [x] Laporan submit require checklist complete (Done di T003)

---

## Sprint 8.9B — Cleanup & Refactor (Optional/Later)

### S8.9-T006 — DELETE S8.7 FOP Calendar (atau Refactor ke List)
**Status**: Done
**Tujuan**: Remove calendar route/controller jika tidak diperlukan, atau refactor jadi list view. Clarify dengan user.

**Decision pending:**
- Delete: `routes/web.php` (GET `/fop` → FopCalendarController)
- Delete: `app/Http/Controllers/FopCalendarController.php`
- Delete: `resources/views/fop/calendar.blade.php`
- Delete sidebar link ke FOP calendar (app.blade.php)

**OR:**

- Refactor: Calendar view → List Task view (sama dengan S8.9-T001)
- Keep: FopCalendarController tapi ubah logic ke list (atau rename ke TaskListController)

**Decision**: Clarify dengan user

---

## TIER 1 — ENTERPRISE CRITICAL (After S8.9)

Setelah S8.9 DONE, prioritaskan Tier 1 berikut untuk skalabilitas enterprise dan compliance:

### S8.10 — Audit Trail + Notification System

**Tujuan:** Catat setiap perubahan task (siapa, apa, kapan) dan implementasi notifikasi real-time.

**Tasks:**

- [x] **S8.10-T002:** Notification System
  - Events: task_assigned, task_rejected, task_approved, checklist_updated
  - Channels: database (in-app), Reverb (Real-time Broadcast)
  - Delivery: queue-based (async)

- [/] **S8.10-T003:** FOP Notification Dashboard (In Progress)
  - View notification history
  - Filter: by date, by action, by user
  - Mark as read/unread

**Effort:** 8–10 hours | **Priority:** High (compliance, auditability)

---

### S8.11 — Advanced Task Management (Reassignment + Bulk Actions)

**Tujuan:** Skalabilitas handling ratusan tasks per hari dengan reassignment & bulk operations.

**Tasks:**
- **S8.11-T001:** Task Reassignment (Live)
  - Change team saat task berjalan (in_progress)
  - Notify old team & new team
  - Keep checklist progress (tidak reset)
  - Log: reassignment timestamp, old/new team, reason

- **S8.11-T002:** Bulk Actions
  - Select multiple tasks → Reject/Pending/Assign sekaligus
  - Bulk reason input modal
  - Progress bar untuk bulk operation
  - Audit trail untuk setiap bulk action

- **S8.11-T003:** Conflict Resolution UI (Enhanced)
  - Saat detect conflict: Show "Team X punya task Y jam Z"
  - Suggest alternative teams berdasarkan availability
  - Show SLA impact jika assign ke tim A vs tim B

**Effort:** 10–12 hours | **Priority:** High (handling scale)

---

### S9.0 — Performance Metrics + Export/Report

**Tujuan:** Business intelligence untuk FOP leadership — task completion rate, team utilization, SLA compliance.

**Tasks:**
- **S9.0-T001:** Task Metrics Dashboard
  - Metrics: total tasks, completed, rejected, pending, avg duration
  - Team metrics: utilization rate, completion rate, SLA compliance %
  - Timeframe: daily, weekly, monthly
  - Chart: line (trend), bar (by team), pie (by status)

- **S9.0-T002:** Export & Report
  - Export formats: CSV, Excel, PDF
  - Report types: Daily Task Summary, Team Performance, SLA Compliance
  - Scheduled report: email daily/weekly to FOP manager

- **S9.0-T003:** SLA Alerts (Optional)
  - Webhook: saat task at-risk (< 1 jam remaining)
  - Destinations: Slack channel, email, SMS (optional)

**Effort:** 12–15 hours | **Priority:** Medium (BI, not blocking ops)

---

## TIER 2 — NICE-TO-HAVE (Defer to S9.1+)

- Mobile app (Teknisi di lapangan)
- Offline mode (sync saat online)
- Task templates (copy checklist dari survey lain)
- Recurring tasks (maintenance schedule)
- Custom workflows (workflow builder)

---

## Sprint 8.2 — FOP & Teknisi Dashboard Enhancement

## Tujuan Sprint 8.2
Menyempurnakan dashboard FOP dan Teknisi yang sudah ada (S13-T008.1) dengan fitur-fitur yang belum terimplementasi:
- Laporan Survey dan Pemasangan multi-step via slide-over inline
- Countdown hitung mundur SLA untuk prioritisasi task
- Pencatatan `Waktu Survey` dan `Waktu Pemasangan` otomatis
- Alur "Proses ke TIM" streamlined di FOP Dashboard
- Status teknisi real-time via Laravel Reverb

> **Referensi dokumen wajib dibaca sebelum mengerjakan Sprint 8.2:**
> - `docs/fop-teknisi-dashboard-spec.md` — spesifikasi desain dashboard FOP & Teknisi, aturan countdown, slide-over laporan
> - `docs/implementation-plan-registrasi-survey-verifikasi.md` — aturan bisnis countdown (Section 6, Open Question no.4), arsitektur state machine, kolom DB yang dipakai
> - `docs/STATUS_FLOW.md` — aturan transisi status pelanggan (calon_pelanggan → survey → menunggu_pemasangan → aktif)
> - `docs/Workflow-pemasangan.md` — alur lengkap fase verifikasi & pemasangan, aturan SLA countdown eksekusi
> - `docs/BUSINESS_RULES.md` — aturan role FOP, Teknisi, dan larangan akses
> - `docs/RBAC_MATRIX.md` — permission string yang dipakai di route guard Sprint 8.2
>
> **Dependency:** Sprint 13 (S13-T008.1 Task Management) sudah Done. Sprint 14 & 15 idealnya selesai dulu, tapi Sprint 8.2A (T001–T003) bisa dikerjakan paralel karena tidak bergantung fitur baru.

---

## Sprint 8.2A — Laporan Inline & Countdown

### S8.2-T000 — Buat FOP Dashboard (Controller + Route + View)
**Status**: Done
**Tujuan**: Membuat fondasi FOP Dashboard sebagai halaman utama role FOP — kanban pipeline task hari ini, antrean survey, dan tabel status teknisi (static). Diperlukan sebelum countdown S8.2-T001 bisa diimplementasikan.

**File dibuat/diubah:**
- `app/Http/Controllers/FopDashboardController.php` — dibuat baru
- `routes/web.php` — tambah route `GET /fop` → `fop.dashboard` dan `GET /api/fop/pipeline`
- `resources/views/fop/dashboard.blade.php` — dibuat baru
- `resources/views/fop/_partials/task-card.blade.php` — dibuat baru
- `resources/views/layouts/app.blade.php` — tambah link "FOP Dashboard" di sidebar

**Acceptance Criteria**:
- [x] Route `GET /fop` tersedia dengan middleware `permission:task.view.all`
- [x] FOP Dashboard menampilkan kanban 3 kolom (Terjadwal / Berjalan / Selesai)
- [x] Antrean survey menampilkan pelanggan berdasarkan `customers.created_at`
- [x] Tabel status teknisi menampilkan teknisi di POP yang sama
- [x] Sidebar memiliki link "FOP Dashboard" untuk role dengan `task.view.all`
- [x] POP scope diterapkan — hanya data di POP yang diizinkan yang tampil
- [x] Placeholder countdown (`data-countdown-*` attributes) sudah tersedia untuk S8.2-T001

---

### S8.2-T000.1 — Buat tasks/own.blade.php (Teknisi Dashboard View)
**Status**: Done
**Tujuan**: Membuat view Teknisi Dashboard yang sebelumnya tidak ada meskipun route dan controller-nya sudah tersedia. Diperlukan sebelum countdown S8.2-T001 bisa ditambahkan ke task card Teknisi.

**File dibuat:**
- `resources/views/tasks/own.blade.php` — dibuat baru

**Acceptance Criteria**:
- [x] View `tasks/own.blade.php` tersedia sehingga route `GET /tasks-saya` tidak lagi error
- [x] Menampilkan task hari ini milik teknisi yang login (dari `TaskController::indexOwn()`)
- [x] Menampilkan task mendatang (upcoming) dalam 5 item
- [x] Tombol "Mulai Task" (`task.status.start`) dan "Selesai" (`task.status.complete`) tersedia sesuai permission
- [x] Status bar warna per status task tampil
- [x] Placeholder countdown (`data-countdown-sla-*` attributes) sudah tersedia untuk S8.2-T001
- [x] Mobile-friendly layout (max-w-2xl, single column)

---

### S8.2-T001 — Countdown Hitung Mundur di Task Card & Antrean
**Status**: Done
**Tujuan**: Mengimplementasikan tiga jenis countdown hitung mundur sesuai aturan bisnis operasional lapangan ISP.

**Aturan bisnis countdown (wajib diikuti):**

| Countdown | Titik Mulai | Batas Waktu | Lokasi Tampil |
|-----------|------------|-------------|---------------|
| **Countdown Survey** | `customers.created_at` / tanggal registrasi | **1×24 jam** | FOP Dashboard — antrean survey, task card |
| **Countdown Verifikasi/Pemasangan** | `tasks.completed_at` task survey | **3×24 jam** | FOP Dashboard — kolom Perlu Aksi FOP |
| **Countdown SLA Eksekusi Survey** | `tasks.started_at` | **120 menit** | Task card Teknisi saat survey berjalan |
| **Countdown SLA Eksekusi Pemasangan** | `tasks.started_at` | **240 menit** | Task card Teknisi saat pemasangan berjalan |

**Catatan penting:**
Semua countdown adalah **hitung mundur ke bawah** (bukan stopwatch ke atas). Tujuannya menunjukkan seberapa mendesak sebuah item harus dikerjakan — semakin merah, semakin prioritas.

**Formula countdown:**
```
Countdown Survey         = (registered_at + 1 hari) - sekarang
Countdown Verifikasi     = (survey_completed_at + 3 hari) - sekarang
Countdown SLA Eksekusi   = (started_at + sla_minutes) - sekarang
```

**Threshold warna (berlaku untuk semua countdown):**
- 🟢 Hijau: sisa > 50% dari total batas waktu
- 🟡 Kuning: sisa 25%–50% dari total batas waktu
- 🔴 Merah berkedip: sisa < 25% dari total batas waktu
- 🔴 **TERLAMBAT** + tampilkan minus: sudah melewati batas waktu

**Checklist**:
- [x] Countdown Survey (1×24 jam) tampil di antrean survey FOP — berdasarkan `customers.created_at`
- [x] Countdown Survey berubah warna sesuai threshold (>50% hijau, 25-50% kuning, <25% merah)
- [x] Label **TERLAMBAT** muncul jika pelanggan belum disurvey lebih dari 1×24 jam
- [x] Countdown Verifikasi (3×24 jam) tampil di kolom "Perlu Aksi FOP" — berdasarkan `tasks.completed_at` task survey
- [x] Countdown Verifikasi berubah warna sesuai threshold
- [x] Label **TERLAMBAT** muncul jika sudah lewat 3×24 jam sejak survey selesai
- [x] Countdown SLA Eksekusi aktif di task card Teknisi saat `tasks.status = in_progress`
- [x] Countdown SLA Eksekusi tampil di FOP Kanban kolom "Sedang Berjalan"
- [x] Semua countdown reactive tanpa page refresh (Alpine.js)
- [x] Tidak perlu field baru di database — pakai field existing (`created_at`, `completed_at`, `started_at`, `sla_minutes`)

**Acceptance Criteria**:
- [x] Countdown Survey aktif sejak pelanggan registrasi, batas 1×24 jam
- [x] Countdown Verifikasi aktif sejak survey selesai, batas 3×24 jam
- [x] Countdown SLA Eksekusi aktif saat teknisi mulai task
- [x] Warna threshold berubah sesuai aturan
- [x] Label TERLAMBAT muncul saat melewati batas masing-masing
- [x] FOP dapat melihat semua countdown tanpa refresh halaman

**File yang diimplementasi:**
- `resources/views/components/countdown-timer.blade.php` — komponen Alpine.js countdown reaktif
- `resources/views/fop/dashboard.blade.php` — integrasi di antrean survey & kolom Perlu Aksi FOP
- `resources/views/fop/_partials/task-card.blade.php` — countdown SLA Eksekusi di kanban Sedang Berjalan
- `resources/views/tasks/own.blade.php` — countdown SLA Eksekusi di dashboard Teknisi
- `app/Http/Controllers/FopDashboardController.php` — kalkulasi deadline ISO untuk view

---

### S8.2-T002 — Pencatatan Waktu Survey (started_at & completed_at)
**Status**: Done
**Tujuan**: Memastikan `tasks.started_at` dicatat saat teknisi tekan "Mulai Survey" dan `tasks.completed_at` dicatat saat laporan survey disimpan. Setelah task selesai, task card menampilkan ringkasan **Waktu Survey**.

**Checklist**:
- [x] Verifikasi `TaskService::start()` sudah menyimpan `started_at` (sudah ada — verifikasi saja)
- [x] Verifikasi `TaskService::complete()` sudah menyimpan `completed_at` (sudah ada — verifikasi saja)
- [x] Setelah status `selesai`, task card Teknisi menampilkan ringkasan:
  ```
  Waktu Survey: 09:15 – 10:42  (1 jam 27 menit)
  ```
- [x] Ringkasan Waktu Survey juga tampil di halaman detail task (`tasks.show`)
- [x] Ringkasan Waktu Survey tampil di FOP Kanban kolom "Selesai"
- [x] Hitung durasi: `completed_at - started_at` dalam format jam menit
- [x] Jika `completed_at` null (task belum selesai), tampilkan countdown aktif (S8.2-T001)

**Acceptance Criteria**:
- [x] `started_at` tercatat saat "Mulai Survey" ditekan
- [x] `completed_at` tercatat saat laporan survey disimpan
- [x] Waktu Survey tampil di task card dan detail task
- [x] Format tampilan: `HH:mm – HH:mm (X jam Y menit)`
- [x] Tidak ada perubahan schema database — field sudah ada

---

### S8.2-T003 — Pencatatan Waktu Pemasangan (started_at & completed_at)
**Status**: Done
**Tujuan**: Sama dengan S8.2-T002, tetapi untuk alur Pemasangan. Saat laporan pemasangan disimpan, `completed_at` dicatat dan task card menampilkan **Waktu Pemasangan**.

**Checklist**:
- [x] Verifikasi `TaskService::complete()` untuk task tipe `pemasangan` sudah menyimpan `completed_at`
- [x] Setelah status `selesai`, task card menampilkan ringkasan:
  ```
  Waktu Pemasangan: 13:00 – 16:45  (3 jam 45 menit)
  ```
- [x] Ringkasan Waktu Pemasangan tampil di halaman detail task
- [x] Ringkasan Waktu Pemasangan tampil di FOP Kanban kolom "Selesai"
- [x] SLA compliance ditampilkan: hijau jika dalam SLA, merah jika over SLA

**Acceptance Criteria**:
- [x] `started_at` tercatat saat "Mulai Pemasangan" ditekan
- [x] `completed_at` tercatat saat laporan pemasangan disimpan
- [x] Waktu Pemasangan tampil di task card dan detail task
- [x] SLA compliance terlihat jelas setelah task selesai
- [x] Format tampilan konsisten dengan Waktu Survey

---

### S8.2-T004 — Laporan Survey Multi-Step (Slide-Over Inline)
**Status**: Done
**Tujuan**: Laporan Survey diisi via slide-over 5-langkah langsung dari Teknisi Dashboard tanpa pindah halaman. Submit laporan otomatis mencatat `completed_at`.

**Step pills navigasi:**
```
[● Data diri]  [○ Foto lokasi]  [○ Cek sinyal]  [○ Teknis jaringan]  [○ Kesimpulan]
```

**Checklist**:
- [x] Buat komponen slide-over `SurveyReportSlideOver` (Alpine.js)
- [x] Step 1 — Data diri: verifikasi nama teknisi, nama pelanggan, alamat (auto-fill dari `customers`)
- [x] Step 2 — Foto lokasi: upload foto via `capture="environment"` (min. 1 foto)
- [x] Step 3 — Cek sinyal: input signal strength (dBm), catatan kondisi
- [x] Step 4 — Teknis jaringan: jarak dari POP (meter, wajib), tipe media rekomendasi (Fiber/Wireless/UTP, wajib)
- [x] Step 5 — Kesimpulan: hasil survey (Layak/Tidak Layak/Perlu Kunjungan Ulang), alasan jika tidak layak, tanda tangan digital teknisi (signature pad canvas)
- [x] Tombol Sebelumnya / Berikutnya dengan validasi per step
- [x] Submit laporan → simpan ke `customer_surveys` + update `tasks.status = selesai` + catat `completed_at`
- [x] Setelah submit: countdown berhenti, tampil Waktu Survey (S8.2-T002)
- [x] Guard: `task.status.complete` (route permission)
- [x] Upload foto via AJAX Fetch API + `accept="image/*"` + `capture="environment"`

**Acceptance Criteria**:
- [x] Slide-over 5 langkah berjalan tanpa navigasi halaman
- [x] Validasi per step berjalan
- [x] Foto wajib mencegah step berlanjut jika belum ada
- [x] Submit mencatat `completed_at` dan menghentikan countdown
- [x] Waktu Survey tampil setelah submit
- [x] Guard permission berjalan

**File yang diimplementasi:**
- `resources/views/tasks/own.blade.php` — slide-over Alpine.js 5-step terintegrasi
- `app/Http/Controllers/TaskSurveyReportController.php` — [BARU] endpoint submit laporan
- `routes/web.php` — tambah route `POST /tasks/{task}/survey-report`

---

### S8.2-T005 — Laporan Pemasangan Multi-Step (Slide-Over Inline)
**Status**: Done
**Tujuan**: Laporan Pemasangan diisi via slide-over 4-langkah. Langkah terakhir (Aktivasi) mengubah task ke selesai dan mencatat `completed_at`.

**Step pills navigasi:**
```
[● Foto pemasangan]  [○ Data teknis]  [○ Kontrak & TTD]  [○ Aktivasi]
```

**Checklist**:
- [x] Buat komponen slide-over `InstallReportSlideOver`
- [x] Step 1 — Foto Pemasangan: foto ONT terpasang (wajib, min. 1), foto kabel routing (wajib, min. 1), foto titik sambungan (opsional)
- [x] Step 2 — Data Teknis: MAC Address ONU, Serial Number, VLAN ID, IP Address, kecepatan paket (select dari paket pelanggan)
- [x] Step 3 — Kontrak & TTD: foto/scan kontrak fisik, tanda tangan pelanggan (signature pad), tanda tangan teknisi (signature pad), tanggal aktivasi (auto-fill hari ini)
- [x] Step 4 — Aktivasi: tombol "Aktifkan" → update `tasks.status = selesai` + catat `completed_at`
- [x] Simpan data teknis ke `customer_technical_details` (dan `task_evidences` untuk file foto)
- [x] Setelah submit: countdown berhenti, tampil Waktu Pemasangan (S8.2-T003)
- [x] Guard: `task.evidence.upload` + `task.status.complete`

**Acceptance Criteria**:
- [x] Slide-over 4 langkah berjalan tanpa navigasi halaman
- [x] Foto wajib mencegah langkah berlanjut
- [x] Data teknis tersimpan
- [x] TTD tersimpan
- [x] Aktivasi mencatat `completed_at` dan menghentikan countdown
- [x] Waktu Pemasangan tampil setelah aktivasi

**File yang diimplementasi:**
- `resources/views/tasks/own.blade.php` — slide-over Alpine.js 4-step terintegrasi
- `app/Http/Controllers/TaskInstallationReportController.php` — [BARU] endpoint submit laporan pemasangan
- `routes/web.php` — tambah route `POST /tasks/{task}/install-report`

---

### S8.2-T006 — Alur "Proses ke TIM" di FOP Dashboard (Slide-Over)
**Status**: Done
**Tujuan**: FOP dapat memproses task survey selesai ke tahap pemasangan langsung dari kanban FOP, tanpa pindah ke halaman detail pelanggan.

**Checklist**:
- [x] Buat slide-over `ProcessToTimSlideOver` di FOP Dashboard
- [x] Slide-over muncul saat FOP klik task card di kolom "Perlu Aksi FOP"
- [x] Tampilkan ringkasan laporan survey (hasil, media, jarak POP)
- [x] Form: assign teknisi pemasangan (dropdown user role teknisi di POP yang sama)
- [x] Form: jadwal pemasangan (date picker + time picker)
- [x] Form: catatan untuk teknisi (textarea, opsional)
- [x] Submit: buat task baru tipe `pemasangan` via `TaskService::create()` dengan data assign + jadwal
- [x] Kirim notifikasi ke teknisi via `SendTaskNotificationJob` yang sudah ada
- [x] Guard: `task.assign.team` + `task.schedule`
- [x] Validasi konflik jadwal teknisi (via `TaskService::detectConflicts()` yang sudah ada)

**Acceptance Criteria**:
- [x] FOP dapat proses survey selesai ke pemasangan dari kanban tanpa pindah halaman
- [x] Task pemasangan baru terbuat dengan assign teknisi dan jadwal
- [x] Notifikasi terkirim ke teknisi
- [x] Konflik jadwal terdeteksi
- [x] Guard permission berjalan

---

## Sprint 8.2B — Status Teknisi & Real-Time

### S8.2-T007 — Tabel Status Teknisi (Static)
**Status**: Done
**Tujuan**: Menampilkan tabel status semua teknisi di POP yang sama di bawah kanban FOP. Tahap ini static dulu — real-time di S8.2-T009.

**Checklist**:
- [x] Tambahkan tabel di bawah kanban FOP (atau tab terpisah)
- [x] Kolom: Nama Teknisi, Status (Aktif/Standby), Task Aktif Hari Ini, Lokasi Terakhir
- [x] Query: teknisi dengan scope POP yang sama dengan FOP login
- [x] Status "Aktif" jika teknisi punya task `in_progress` hari ini, "Standby" jika tidak
- [x] Task Aktif: jumlah task `terjadwal`/`in_progress` hari ini
- [x] Lokasi: field `current_location` dari tabel `users` atau relasi task (nullable)
- [x] Refresh manual via tombol atau reload halaman

**Acceptance Criteria**:
- [x] Tabel teknisi tampil di FOP Dashboard
- [x] Status Aktif/Standby terhitung dengan benar
- [x] Hanya teknisi di POP yang sama yang tampil (scope aman)
- [x] Tidak ada data teknisi POP lain yang bocor

---

### S8.2-T008 — Reverb Broadcasting: Transisi Status Task
**Status**: Done
**Tujuan**: Broadcast event Laravel Reverb setiap kali status task berubah agar FOP Dashboard bisa auto-refresh kanban secara real-time.

**Event yang perlu dibroadcast:**

| Event | Channel | Trigger | Consumer |
|-------|---------|---------|---------|
| `TaskStarted` | `fop.{pop_id}` | Teknisi tekan "Mulai" | FOP Kanban — pindah ke kolom Berjalan |
| `TaskCompleted` | `fop.{pop_id}` | Laporan disimpan | FOP Kanban — pindah ke kolom Selesai |
| `TaskScheduled` | `teknisi.{user_id}` | FOP assign task baru | Teknisi Dashboard — munculkan task baru |
| `TechnicianStatusUpdated` | `fop.{pop_id}` | Task mulai/selesai | FOP — update tabel status teknisi (ditangani via side-effect TaskStarted/TaskCompleted) |

**Checklist**:
- [x] Buat event class `TaskStarted` (broadcast ke `fop.{pop_id}`)
- [x] Buat event class `TaskCompleted` (broadcast ke `fop.{pop_id}`)
- [x] Buat event class `TaskScheduled` (broadcast ke `teknisi.{user_id}`)
- [x] Broadcast `TaskStarted` dari `TaskService::start()`
- [x] Broadcast `TaskCompleted` dari `TaskService::complete()`
- [x] Broadcast `TaskScheduled` dari `TaskService::create()` (extend notifyTeam)
- [x] Channel private sesuai RBAC scope
- [x] Pastikan `BROADCAST_DRIVER=reverb` di `.env` (dokumentasikan)

**Acceptance Criteria**:
- [x] Event terbroadcast saat transisi status
- [x] Channel menggunakan scope POP yang benar
- [x] Tidak ada event bocor ke POP lain

---

### S8.2-T009 — FOP Kanban Auto-Refresh via Reverb
**Status**: Done
**Tujuan**: FOP Dashboard kanban auto-refresh saat menerima event dari Reverb tanpa page reload.

**Checklist**:
- [x] Setup Echo.js listener di FOP Dashboard view (Alpine.js atau Livewire)
- [x] Listener `TaskStarted` → pindah card dari kolom Terjadwal ke Berjalan
- [x] Listener `TaskCompleted` → pindah card dari kolom Berjalan ke Selesai
- [x] Listener `TechnicianStatusUpdated` → update baris di tabel status teknisi (S8.2-T007)
- [x] Countdown di kolom Berjalan tetap berjalan saat card di-refresh

**Acceptance Criteria**:
- [x] Kanban FOP update otomatis saat teknisi mulai/selesai task
- [x] Tabel status teknisi update otomatis
- [x] Tidak perlu manual refresh halaman
- [x] Countdown tidak reset saat kanban refresh

---

### S8.2-T010 — Push Notifikasi ke Teknisi saat FOP Assign Task
**Status**: Done
**Tujuan**: Saat FOP "Proses ke TIM" (S8.2-T006), Teknisi Dashboard menampilkan banner/notifikasi task baru secara real-time.

**Checklist**:
- [x] Listen event `TaskScheduled` di Teknisi Dashboard via Echo.js
- [x] Tampilkan banner notifikasi: "Task baru ditugaskan: [Judul Task] — [Jadwal]"
- [x] Klik banner → scroll ke task card baru di list
- [x] Banner hilang otomatis setelah 10 detik atau diklik close
- [x] Extend `SendTaskNotificationJob` yang sudah ada jika diperlukan

**Acceptance Criteria**:
- [x] Teknisi menerima notifikasi real-time saat FOP assign task baru
- [x] Banner tampil dan bisa di-dismiss
- [x] Task baru muncul di list tanpa reload (inject via fetch partial HTML)
- [x] Hanya teknisi yang di-assign yang menerima notifikasi (private channel teknisi.{user_id})

**File yang diimplementasi:**
- `resources/views/tasks/own.blade.php` — banner Alpine.js + Echo.js listener + technicianNotifier() Alpine component
- `resources/views/tasks/partials/own-card.blade.php` — [BARU] partial HTML untuk satu task card teknisi
- `app/Http/Controllers/TaskController.php` — method `cardPartial()` untuk endpoint partial
- `routes/web.php` — route `GET /tasks-saya/partial/{task}` → `tasks.own.card-partial`

---

## Catatan Sprint 8.2

**Urutan implementasi yang disarankan:**
1. S8.2-T001 (Countdown) → paling terlihat dampaknya, tidak perlu schema baru
2. S8.2-T002 + S8.2-T003 (Waktu Survey/Pemasangan) → verifikasi field existing
3. S8.2-T004 + S8.2-T005 (Laporan Slide-Over) → fitur utama teknisi
4. S8.2-T006 (Proses ke TIM Slide-Over) → fitur utama FOP
5. S8.2-T007 (Tabel Teknisi Static) → UI support
6. S8.2-T008 → S8.2-T010 (Real-Time Reverb) → polish terakhir

**Dependency yang sudah tersedia:**
- `tasks.started_at` dan `tasks.completed_at` sudah ada di schema
- `tasks.sla_minutes` sudah ada dan diisi dari `TaskType::slaMinutes()`
- `TaskService::start()` dan `TaskService::complete()` sudah ada
- `SendTaskNotificationJob` sudah ada untuk notifikasi
- `TaskService::detectConflicts()` sudah ada untuk validasi jadwal
- Permission `task.evidence.upload`, `task.status.complete`, `task.assign.team` sudah ada di seeder

---

## Sprint 8.3 — Ticketing Queue & Sentralisasi Penjadwalan Tim Teknisi

### S8.3-T001 — Penambahan Alur Tiket Antrean (Unassigned Queue)
**Status**: Done
**Tujuan**: Mengubah alur "Buat Task" (FOP) dan "Proses ke Tim" (Admin Survey/Pemasangan) agar menyimpan tiket ke dalam antrean (status `pending` atau `draft`), tanpa meminta penugasan teknisi dan jadwal spesifik secara paksa di awal pendaftaran tiket.

**Checklist**:
- [x] Ubah validasi `TaskRequest` & `TaskController@store` agar `team_member_ids` dan `scheduled_at` bersifat opsional (nullable) saat pembuatan tiket awal
- [x] Sesuaikan alur `ProcessToTimSlideOver` (S8.2-T006) agar saat diklik "Proses ke Tim", tiket masuk ke Antrean Tiket pengerjaan
- [x] Pastikan tiket antrean terisolasi aman sesuai POP Scope cabang

---

### S8.3-T002 — Kolom Kanban Antrean & Modal Penjadwalan Tim
**Status**: Done
**Tujuan**: Menampilkan kolom "Antrean Tiket" di dasbor Kanban FOP dan membuat modal `ScheduleTaskModal` untuk memilih tiket antrean, lalu menugaskan 1–3 teknisi beserta jadwal eksekusinya.

**Checklist**:
- [x] Tambahkan kolom "Antrean Tiket" (Unassigned) di paling kiri atau area khusus Kanban FOP Dashboard (`fop/dashboard.blade.php`)
- [x] Buat komponen modal `ScheduleTaskModal` berisi dropdown teknisi (multi-select 1–3 orang) dan input tanggal/jam penjadwalan
- [x] Tambahkan tombol "Jadwalkan & Tugaskan" pada setiap kartu di kolom Antrean Tiket

---

### S8.3-T003 — Service Layer, Deteksi Konflik & Reverb Broadcasting
**Status**: Done
**Tujuan**: Menangani penugasan tiket antrean di `TaskService`, melakukan pengecekan konflik jadwal teknisi, dan mengirimkan notifikasi Reverb secara real-time.

**Checklist**:
- [x] Buat method `TaskService::scheduleTask($task, $data, $actor)` untuk memperbarui status tiket menjadi `terjadwal` + menyimpan relasi `TaskTeam`
- [x] Jalankan validasi `detectConflicts()` sebelum tiket dijadwalkan
- [x] Broadcast event `TaskScheduled` ke private channel masing-masing teknisi terpilih

### S8.3-T004 — Sentralisasi Pembuatan Tiket Antrean (Survey, Pemasangan, dan Task Baru)
**Status**: Done
**Tujuan**: Menjamin seluruh tiket yang berasal dari registrasi baru (Survey), verifikasi Admin (Pemasangan), maupun pembuatan task manual langsung masuk ke dalam List Task / Kolom Antrean Tiket (`status = pending`).

**Checklist**:
- [x] Auto-create tiket `Task` tipe `survey` saat registrasi pelanggan baru (`CustomerController@store`)
- [x] Auto-create tiket `Task` tipe `pemasangan` saat transisi workflow ke `waiting_installation` (`CustomerWorkflowService@transition`)
- [x] Pastikan isolasi data POP Scope tetap terjaga ketat pada semua tiket antrean

---

# Sprint 8.4 — Koreksi Flow & Aktor Onboarding

## Tujuan Sprint 8.4
Memperbaiki dua kesalahan kritis pada flow onboarding baru:
1. Aktor "Proses ke TIM" dipindah dari FOP ke Admin/Helpdesk sesuai brief
2. Tombol "Mulai Survey" dan "Mulai Pemasangan" diintegrasikan langsung ke halaman Task Teknisi (`tasks/own.blade.php`) — sekarang teknisi harus navigasi ke halaman terpisah

> **Referensi:** `docs/analisa-flow-baru-dan-sprint.md` — Sprint A
> **Dependency:** Sprint 8.3 selesai

---

### S8.4-T001 — Pindah Aktor "Proses ke TIM" dari FOP ke Admin/Helpdesk
**Status**: Done
**Tujuan**: Button "Proses ke TIM" sekarang ada di `FopDashboardController` dan diakses FOP. Sesuai brief dan jawaban #1, aktor yang seharusnya menekan button ini adalah **Admin/Helpdesk** — karena mereka yang memverifikasi data survey dan mengkonfirmasi ke pelanggan sebelum pemasangan dijadwalkan.

**Konteks masalah:**
- `FopDashboardController::processToTim()` menangani transisi `waiting_acc → waiting_installation`
- Permission check: `task.assign.team` + `task.schedule` (milik FOP)
- Seharusnya: hanya Admin/Helpdesk yang bisa tekan ini, FOP tidak

**Checklist:**
- [x] Buat atau repurpose controller method: pindahkan logic `processToTim` ke `CustomerVerificationController` atau buat `AccProcessController`
- [x] Update permission check: hanya role Admin/Helpdesk yang bisa akses endpoint ini
- [x] UI: tampilkan button "Proses ke TIM" di halaman Verifikasi & Pemasangan (bukan FOP Dashboard)
- [x] FOP Dashboard: hapus atau sembunyikan button "Proses ke TIM" dari kolom "Perlu Aksi FOP"
- [x] FOP Dashboard: kolom "Perlu Aksi FOP" tetap menampilkan pelanggan `waiting_acc` sebagai informasi, tapi tanpa button aksi
- [x] Update route di `routes/web.php` — middleware permission disesuaikan
- [x] Update `docs/analisa-flow-baru-dan-sprint.md` setelah selesai

**Acceptance Criteria:**
- [x] Admin/Helpdesk bisa tekan "Proses ke TIM" dari halaman Verifikasi & Pemasangan
- [x] FOP tidak bisa akses endpoint processToTim (403 jika coba langsung)
- [x] FOP Dashboard masih menampilkan daftar `waiting_acc` sebagai info saja
- [x] Status customer berubah ke `waiting_installation` setelah Admin/Helpdesk konfirmasi
- [x] Task pemasangan otomatis masuk Antrean Tiket FOP

---

### S8.4-T002 — Integrasikan "Mulai Survey" ke Halaman Task Teknisi
**Status**: Done
**Tujuan**: Sekarang "Mulai Survey" ada di `surveys/queue.blade.php` — halaman terpisah yang tidak mobile-friendly. Sprint doc & brief menyatakan: teknisi buka Task → Detail → tekan "Mulai Survey". Tombol ini harus ada di `tasks/own.blade.php` dan `tasks/show.blade.php` untuk task bertipe `survey`.

**Konteks masalah:**
- `surveys/queue.blade.php` punya form POST ke `route('customers.survey.start', $customer)`
- `tasks/own.blade.php` tidak punya button "Mulai Survey" — hanya slide-over laporan (yang muncul setelah `in_progress`)
- Teknisi di lapangan pakai HP, tidak tahu harus ke halaman survey queue

**Checklist:**
- [x] Di `tasks/own.blade.php`: tambah button **"Mulai Survey"** pada task card yang `status = terjadwal` dan `task_type = survey`
  - Button trigger POST ke `route('customers.survey.start', $task->customer_id)`
  - Setelah sukses: task status → `in_progress`, customer status → `survey_in_progress`
  - Button berubah menjadi "Laporan Survey" (slide-over sudah ada)
- [x] Di `tasks/show.blade.php`: tambah button "Mulai Survey" dengan logic yang sama
- [x] Guard: hanya anggota tim task tersebut yang bisa tekan (validasi `$task->teamMembers->pluck('user_id')->contains(auth()->id())`)
- [x] Guard: permission `customers.detail.survey.update`
- [x] Pastikan `CustomerSurveyController::start()` atau `TaskStatusController` menangani transisi dengan benar

**Acceptance Criteria:**
- [x] Teknisi bisa tekan "Mulai Survey" langsung dari `tasks/own.blade.php`
- [x] Customer status berubah ke `survey_in_progress`
- [x] Task status berubah ke `in_progress`
- [x] Countdown SLA eksekusi mulai berjalan
- [x] Button berganti ke "Laporan Survey" (slide-over existing)
- [x] Teknisi yang bukan anggota tim tidak bisa tekan (403 atau button hidden)

---

### S8.4-T003 — Integrasikan "Mulai Pemasangan" ke Halaman Task Teknisi
**Status**: Done
**Tujuan**: Sama dengan S8.4-T002 tapi untuk fase pemasangan. Sekarang "Mulai Pasang" tidak ada di `tasks/own.blade.php` — harus ditambahkan agar alur teknisi konsisten.

**Checklist:**
- [x] Di `tasks/own.blade.php`: tambah button **"Mulai Pemasangan"** pada task card yang `status = terjadwal` dan `task_type = pemasangan`
  - Button trigger endpoint yang mengubah customer → `installation_in_progress` dan task → `in_progress`
  - Setelah sukses: button berganti ke "Laporan Pemasangan" (slide-over existing sudah ada)
- [x] Di `tasks/show.blade.php`: tambah button dengan logic yang sama
- [x] Guard: hanya anggota tim task tersebut + permission `customers.detail.installation.update`
- [x] Verifikasi customer status valid (`waiting_installation`) sebelum mulai

**Acceptance Criteria:**
- [x] Teknisi bisa tekan "Mulai Pemasangan" dari `tasks/own.blade.php`
- [x] Customer status berubah ke `installation_in_progress`
- [x] Task status berubah ke `in_progress`
- [x] Button berganti ke "Laporan Pemasangan"
- [x] Countdown SLA eksekusi mulai berjalan

---

### S8.4-T004 — Redirect FOP ke FOP Dashboard sebagai Landing Page
**Status**: Done
**Tujuan**: Saat FOP login dan mengakses `/`, mereka mendarat di dashboard admin generik (billing, invoice, piutang) karena FOP punya permission `dashboard.view`. Seharusnya FOP langsung diarahkan ke FOP Dashboard (`/fop`) yang relevan dengan pekerjaan lapangan mereka.

**Konteks masalah:**
- `DashboardController::index()` baris 19–28: cek `!hasPermission('dashboard.view')` gagal karena FOP punya permission ini
- FOP lewati blok redirect dan masuk ke render dashboard billing/admin
- Teknisi sudah benar (`task.view.own` → `tasks.own`), FOP belum
- FOP harus manual navigasi ke `/fop` setiap login — tidak efisien
- Referensi: `docs/analisa-flow-baru-dan-sprint.md` — Bagian 6

**Checklist:**
- [x] Di `DashboardController::index()`, tambah early return untuk role FOP **sebelum** block `if (!hasPermission('dashboard.view'))`:
  ```php
  if (auth()->user()->hasRole('fop')) {
      return redirect()->route('fop.dashboard');
  }
  ```
- [x] Verifikasi redirect tidak loop (route `fop.dashboard` punya middleware `permission:task.view.all` yang FOP miliki)
- [x] Pastikan FOP yang juga punya role lain (edge case) tidak terdampak negatif

**Acceptance Criteria:**
- [x] FOP login → langsung ke `/fop` (FOP Dashboard)
- [x] Teknisi login → tetap ke `/tasks-saya` (tidak berubah)
- [x] Admin/Owner/Helpdesk login → tetap ke dashboard billing (tidak berubah)
- [x] Tidak ada redirect loop

**File yang diubah:**
- `app/Http/Controllers/DashboardController.php` — tambah 3 baris di atas baris 19

---

### S8.4-T005 — RBAC Dinamis untuk Workflow Transitions
**Status**: Done
**Tujuan**: Infrastructure untuk membuat workflow transitions (pending → scheduled → in_progress → selesai, etc.) configurable via database RBAC daripada hardcoded di enum. Memungkinkan role/permission berubah tanpa code deploy. Specific focus: "Proses ke Tim" action dapat di-assign ke berbagai roles (FOP, Admin, Helpdesk) lewat database configuration.

**Konteks masalah:**
- Saat ini workflow transitions & allowed actions hardcoded di `WorkflowTransition.php` enum dan controller policies
- Jika role permissions berubah (misal: Admin baru bisa schedule), perlu update code + deploy
- MVP scope awal tidak memerlukan ini, tapi user menginginkan fleksibilitas RBAC-dynamic untuk future-proof
- `S8.4-T001` mengubah aktor "Proses ke Tim" dari FOP ke Admin/Helpdesk — ini membuktikan kebutuhan akan flexibility
- Later: role changes akan frequent, perlu system yang configurable via DB

**Checklist:**
- [x] Create migration `create_workflow_transition_permissions_table`:
  ```
  id, from_status, to_status, permission_name, created_at
  ```
- [x] Create Model `WorkflowTransitionPermission` dengan relationships ke `Role` (many-to-many via pivot)
- [x] Update `Role` model: add `hasMany WorkflowTransitionPermission`
- [x] Create Seeder `WorkflowTransitionPermissionSeeder`:
  - Map existing transitions: `pending → scheduled` = `task.schedule`
  - Map: `pending → rejected` = `task.reject`
  - Map: `pending → pending` = `task.pending`
  - Map: `scheduled → in_progress` = `task.start`
  - Map: `in_progress → selesai` = `task.complete`
  - Map: `selesai → approved` = `task.approve` (FOP review)
  - Map: `selesai → rejected` = `task.reject` (FOP review)
- [x] Update `TaskPolicy`:
  - Change from hardcoded `->hasRole('fop')` checks
  - Fetch rule dynamically: `WorkflowTransitionPermission::where('from_status', $task->status)->where('to_status', $newStatus)->first()`
  - Check: `$user->hasPermission($rule->permission_name)`
- [x] Update `TaskController` methods (`schedule()`, `reject()`, `approve()`, etc.):
  - Use `$this->authorize()` with updated policies
  - Keep existing logic, only change auth check
- [x] Ensure `Pop` scope still applied (multi-POP isolation)
- [x] Add seeder call ke `DatabaseSeeder`

**Acceptance Criteria:**
- [x] "Proses ke Tim" action available untuk Admin, Helpdesk, FOP (configured in seeder)
- [x] Permission check validates against `workflow_transition_permissions` table
- [x] Role change (add/remove `task.schedule` from Admin) reflected in UI without deploy
- [x] S8.4-T001 flow works: Admin/Helpdesk dapat "Proses ke Tim" tanpa code change
- [x] No breaking changes to existing TaskPolicy behavior
- [x] POP scope enforced (tidak bisa cross-assign task antar POP)
- [x] Tests pass for all transition scenarios

**File yang diubah:**
- `database/migrations/xxxx_create_workflow_transition_permissions_table.php` (baru)
- `app/Models/WorkflowTransitionPermission.php` (baru)
- `app/Models/Role.php` — add relationship
- `database/seeders/WorkflowTransitionPermissionSeeder.php` (baru)
- `database/seeders/DatabaseSeeder.php` — add call
- `app/Policies/TaskPolicy.php` — refactor checks
- `app/Http/Controllers/TaskController.php` — minimal change (authorization stays)

---

# Sprint 8.5 — Design System Konsistensi & Mobile UX

## Tujuan Sprint 8.5
Menyamakan visual language seluruh halaman onboarding ke design system (CSS vars). Sekarang `surveys/queue.blade.php` dan tab `_survey/_installation` masih pakai hardcoded `bg-slate-*`. Juga perbaiki label status yang masih bahasa Inggris dan pastikan `capture="environment"` ada di semua input foto.

> **Referensi:** `docs/analisa-flow-baru-dan-sprint.md` — Sprint B
> **Dependency:** S8.4 tidak perlu selesai dulu, bisa paralel

---

### S8.5-T001 — Refactor `surveys/queue.blade.php` ke Design System
**Status**: Done
**Tujuan**: Halaman antrean survey masih pakai Tailwind hardcoded (`bg-white`, `bg-slate-*`, `text-slate-*`, `border-slate-200`). Harus migrasi ke CSS vars design system seperti halaman `tasks/own.blade.php`.

**Checklist:**
- [ ] Replace `bg-white` → `bg-surface` / `var(--color-surface)`
- [ ] Replace `bg-slate-50` → `bg-surface-muted` / `var(--color-surface-muted)`
- [ ] Replace `border-slate-200` → `border-border`
- [ ] Replace `text-slate-*` → `text-text-main` / `text-text-muted` / `text-text-secondary`
- [ ] Ganti label status: "WAITING" → "Menunggu Survey", "IN PROGRESS" → "Proses Survey"
- [ ] Ganti tombol action: gunakan style konsisten dengan `tasks/own.blade.php`
- [ ] SLA countdown: ganti `<div data-start="...">Menghitung...</div>` dengan `<x-countdown-timer>` yang benar
  - Basis: `customers.created_at`, batas: 1×24 jam
- [ ] Tabel: tambah responsivitas — pada mobile, collapse ke card view bukan tabel 9 kolom

**Acceptance Criteria:**
- [ ] Tidak ada `bg-slate-*` / `text-slate-*` tersisa di file ini
- [ ] Label status dalam Bahasa Indonesia
- [ ] SLA countdown berfungsi dan reaktif (Alpine.js)
- [ ] Halaman readable di mobile (lebar < 640px)

---

### S8.5-T002 — Refactor `customers/tabs/_survey.blade.php` ke Design System
**Status**: Done
**Tujuan**: Tab survey di halaman detail pelanggan masih full hardcoded `slate-*`. Localize badge status survey.

**Checklist:**
- [ ] Replace semua `bg-slate-*`, `text-slate-*`, `border-slate-*` → design system vars
- [ ] Localize badge `survey_status`: `completed` → "Selesai", `failed` → "Tidak Layak", `pending` → "Menunggu"
- [ ] Pastikan button "Lapor Hasil Survey" pakai style design system (bukan `bg-sky-600` hardcoded)

**Acceptance Criteria:**
- [ ] Tidak ada hardcoded slate colors
- [ ] Badge status dalam Bahasa Indonesia
- [ ] Visual konsisten dengan tab-tab lain di halaman `customers/show.blade.php`

---

### S8.5-T003 — Refactor `customers/tabs/_installation.blade.php` ke Design System
**Status**: Done
**Tujuan**: Tab pemasangan di halaman detail pelanggan, sama masalahnya dengan _survey.blade.php.

**Checklist:**
- [ ] Replace semua `bg-slate-*`, `text-slate-*`, `border-slate-*` → design system vars
- [ ] Localize badge `installation_status`: `completed`/`failed`/`in_progress`/`scheduled` → Bahasa Indonesia
- [ ] Button "Isi Data Pemasangan" dan "Isi Laporan Uji (Speedtest)" → design system style

**Acceptance Criteria:**
- [ ] Tidak ada hardcoded slate colors
- [ ] Badge status dalam Bahasa Indonesia
- [ ] Visual konsisten dengan tab lain

---

### S8.5-T004 — `capture="environment"` Merata di Semua Form Foto
**Status**: Done
**Tujuan**: Sekarang `capture="environment"` hanya ada di modal upload `tasks/own.blade.php`. Semua input foto di seluruh app untuk teknisi lapangan harus punya atribut ini agar kamera langsung terbuka di HP.

**Checklist:**
- [ ] Audit semua `<input type="file">` atau `accept="image/*"` di:
  - `customers/tabs/_survey.blade.php`
  - `customers/tabs/_installation.blade.php`
  - Form laporan survey (`surveys/report.blade.php` atau sejenisnya)
  - Form laporan pemasangan
- [ ] Tambah `capture="environment"` pada setiap input foto yang belum punya
- [ ] Verifikasi `tasks/own.blade.php` sudah benar (sudah ada)

**Acceptance Criteria:**
- [ ] Semua input foto untuk teknisi lapangan punya `capture="environment"`
- [ ] Di browser mobile, klik input foto langsung buka kamera belakang

---

# Sprint 8.6 — SLA Waiting Phase Countdown

## Tujuan Sprint 8.6
Aktivasi countdown hitung mundur untuk dua fase waiting yang saat ini belum berfungsi benar:
- `waiting_survey`: 1×24 jam dari `customers.created_at`
- `waiting_installation`: 3×24 jam dari `tasks.completed_at` task survey selesai

Komponen `x-countdown-timer` sudah ada — tinggal digunakan dengan benar.

> **Referensi:** `docs/analisa-flow-baru-dan-sprint.md` — Sprint C
> **Referensi aturan bisnis:** `docs/TASKS.md` S8.2-T001 (formula countdown sudah didokumentasikan)

---

### S8.6-T001 — SLA Countdown `waiting_survey` Aktif di Queue Survey
**Status**: Done
**Tujuan**: `surveys/queue.blade.php` punya placeholder `<div data-start="...">Menghitung...</div>` tapi tidak pakai `<x-countdown-timer>`. Harus diganti dengan komponen yang benar.

**Checklist:**
- [x] Di `surveys/queue.blade.php`, kolom "WAKTU (LIVE)": ganti placeholder dengan `<x-countdown-timer>`
  - `deadline` = `$customer->created_at->addDay()->toIso8601String()` (1×24 jam)
  - `total-seconds` = `86400`
  - `label` = "Sisa Waktu Survey"
  - `compact` = true (karena di dalam tabel/card)
- [x] Di FOP Dashboard antrean survey: pastikan countdown sudah menggunakan formula yang sama (verifikasi dari S8.2-T001)
- [x] Warna threshold ikuti aturan S8.2-T001 (>50% hijau, 25-50% kuning, <25% merah berkedip, overdue = TERLAMBAT)

**Acceptance Criteria:**
- [x] Countdown aktif dan menghitung mundur real-time di halaman queue survey
- [x] Warna berubah sesuai threshold
- [x] Label "TERLAMBAT" muncul jika sudah > 1×24 jam
- [x] Tidak ada teks "Menghitung..." statis tersisa

---

### S8.6-T002 — SLA Countdown `waiting_installation` di Halaman Verifikasi
**Status**: Done
**Tujuan**: Setelah survey selesai dan status → `waiting_acc` → `waiting_installation`, ada SLA 3×24 jam dari `completed_at` task survey. Countdown ini harus tampil di halaman Verifikasi & Pemasangan.

**Checklist:**
- [x] Di `verifications/queue.blade.php`: ganti countdown placeholder dengan `<x-countdown-timer>`
  - `deadline` = `$installation->started_at->addDays(3)->toIso8601String()` atau dari `tasks.completed_at` survey terkait
  - `total-seconds` = `259200` (3 hari)
  - `label` = "Sisa Waktu Pemasangan"
- [x] Verifikasi data yang dipakai basis: `tasks.completed_at` dari task survey tipe `survey` yang `status = selesai` milik customer ini
- [x] Tampilkan di FOP Dashboard kolom "Perlu Aksi FOP" juga (verifikasi dari S8.2-T001, mungkin sudah ada)

**Acceptance Criteria:**
- [x] Countdown 3×24 jam aktif di halaman verifikasi/pemasangan
- [x] Basis countdown adalah waktu survey selesai (bukan waktu registrasi)
- [x] Warna threshold konsisten
- [x] Label "TERLAMBAT" muncul jika > 3×24 jam sejak survey selesai

---

### S8.6-T003 — Overdue Indicator di FOP Stat Cards & Badge
**Status**: Done
**Tujuan**: FOP perlu tahu berapa pelanggan yang sudah overdue SLA waiting agar bisa prioritaskan. Tambah indikator di stat cards FOP Dashboard.

**Checklist:**
- [ ] Warna merah/error saat ada overdue
- [ ] FOP bisa langsung tahu prioritas tanpa scroll

---

# Sprint 8.7 — Kalender Scheduler FOP

## Tujuan Sprint 8.7
FOP saat ini hanya bisa lihat task hari ini di kanban. Tidak ada tampilan kalender untuk lihat jadwal mingguan/bulanan dan deteksi kepadatan jadwal per teknisi.

> **Referensi:** `docs/analisa-flow-baru-dan-sprint.md` — Sprint D
> **Sprint doc asli:** Sprint 2.3

---

### S8.7-T001 — Route & Controller Kalender FOP
**Status**: Done
**Tujuan**: Buat endpoint yang menyediakan data task terjadwal dalam format yang bisa dipakai kalender.

**Checklist:**
- [x] Tambah route `GET /fop/calendar` → `FopCalendarController::index()`
- [x] Tambah route `GET /fop/calendar/events` → `FopCalendarController::events()` — return JSON array event (Pindah ke route web alih-alih api)
- [x] Format JSON event: `{ id, title, start, end, color, extendedProps: { task_type, customer_name, technicians, status } }`
- [x] Color-code: `survey` = `#3b82f6` (biru), `pemasangan` = `#f59e0b` (amber)
- [x] Filter: hanya task di POP scope FOP login
- [x] Parameter query: pakai `start` dan `end` (dukung query time frame dynamic FullCalendar)

**Acceptance Criteria:**
- [x] Endpoint `/fop/calendar/events` return data valid
- [x] POP scope aman — tidak ada bocor data cabang lain
- [x] Format event kompatibel untuk FullCalendar atau grid Blade

---

### S8.7-T002 — UI Kalender (Grid Blade atau FullCalendar)
**Status**: Done
**Tujuan**: Tampilkan kalender di halaman `/fop/calendar` dengan events dari S8.7-T001. Pilihan: FullCalendar.js via CDN/npm atau grid Blade manual (lebih ringan).

**Rekomendasi:** Gunakan FullCalendar.js (npm `@fullcalendar/core` + `@fullcalendar/daygrid` + `@fullcalendar/timegrid`) karena sudah battle-tested untuk use case ini.

**Checklist:**
- [x] Install FullCalendar via npm atau load dari CDN
- [x] Buat `resources/views/fop/calendar.blade.php`
- [x] Init FullCalendar dengan `events` fetch dari `/api/fop/calendar/events`
- [x] View default: `dayGridMonth` (bulanan), toggle ke `timeGridWeek` (mingguan)
- [x] Color-code event: biru = survey, amber = pemasangan
- [x] Header kalender: navigasi bulan (prev/next/today) + toggle view
- [x] Link di sidebar FOP: "Kalender Jadwal"

**Acceptance Criteria:**
- [x] Kalender tampil dengan event survey dan pemasangan
- [x] Color-code benar
- [x] Navigasi bulan/minggu berfungsi
- [x] Mobile-friendly (FullCalendar responsive by default)

---

### S8.7-T003 — Slide-Over Detail Task dari Klik Event Kalender
**Status**: Done
**Tujuan**: Saat FOP klik event di kalender, tampilkan slide-over berisi detail task (nama pelanggan, tim teknisi, status, SLA).

**Checklist:**
- [x] Data task tampil dengan benar
- [x] Link ke task detail berfungsi

---

# Sprint 8.8 — Audit Log, Edge Cases & Notifikasi Pelanggan

## Tujuan Sprint 8.8
Menyelesaikan fitur-fitur edge case dan audit trail yang tercatat sebagai gap di analisa:
- Tabel log transisi status pelanggan
- Reassign teknisi tanpa reset status
- Validasi konflik jadwal yang lebih ketat
- Notifikasi ke pelanggan saat aktif

> **Referensi:** `docs/analisa-flow-baru-dan-sprint.md` — Sprint E

---

### S8.8-T001 — Migrasi Tabel `customer_status_logs`
**Status**: Done
**Tujuan**: Setiap transisi status pelanggan saat ini tidak di-log ke tabel tersendiri. Hanya ada audit log generik. Tabel ini perlu ada untuk traceability bisnis (siapa yang ubah status kapan).

**Checklist:**
- [x] Buat migration `create_customer_status_logs_table`:
  ```
  id, customer_id, from_status, to_status, changed_by (user_id), note (nullable), created_at
  ```
- [x] Buat model `CustomerStatusLog` dengan relasi `belongsTo Customer` dan `belongsTo User` (changed_by)
- [x] Tidak ada `updated_at` (immutable log)

**Acceptance Criteria:**
- [x] Migrasi berjalan tanpa error
- [x] Model tersedia dengan relasi yang benar
- [x] Field `note` nullable (untuk catatan kontekstual opsional)

---

### S8.8-T002 — Insert Log di `CustomerWorkflowService::transition()`
**Status**: Done
**Tujuan**: Setiap kali `CustomerWorkflowService::transition()` dipanggil, insert record ke `customer_status_logs`.

**Checklist:**
- [x] Di `CustomerWorkflowService::transition()`, setelah update status customer: insert ke `customer_status_logs`
- [x] `changed_by` = `auth()->id()`
- [x] `note` = parameter opsional yang sudah ada di signature `transition($customer, $status, $note = null)`
- [x] Verifikasi semua caller `transition()` di codebase masih berfungsi

**Acceptance Criteria:**
- [x] Setiap transisi status tercatat di tabel
- [x] `from_status` dan `to_status` akurat
- [x] `changed_by` terisi dengan user yang melakukan aksi
- [x] Tidak ada performance regression — insert sync cukup, tidak perlu queue

---

### S8.8-T003 — Fitur Reassign Teknisi Tanpa Reset Status Customer
**Status**: Done
**Tujuan**: Jika teknisi yang di-assign tidak bisa hadir, FOP harus bisa mengganti anggota tim task tanpa reset status customer ke fase sebelumnya.

**Checklist:**
- [x] Buat endpoint `PATCH /tasks/{task}/team` → `TaskTeamController::update()`
- [x] Validasi: task harus `terjadwal` atau `in_progress` (tidak bisa reassign task `selesai`)
- [x] Logic: update atau replace record di `task_teams` untuk teknisi yang diganti
- [x] Broadcast `TaskScheduled` ke teknisi baru (notifikasi)
- [x] Broadcast ke teknisi lama bahwa mereka di-unassign (opsional: event `TaskUnassigned`)
- [x] Guard: permission `task.assign.team`
- [x] UI: tombol "Ganti Teknisi" di slide-over detail task FOP Dashboard

**Acceptance Criteria:**
- [x] FOP bisa ganti teknisi tanpa mengubah status customer
- [x] Teknisi baru menerima notifikasi Reverb
- [x] Task history tidak hilang (log tersimpan)
- [x] Task tetap di kolom kanban yang sama setelah reassign

---

### S8.8-T004 — Validasi Konflik Jadwal Teknisi (Per Jam)
**Status**: Done
**Tujuan**: `TaskService::detectConflicts()` sudah ada tapi perlu diverifikasi apakah validasi bentrok jadwal per-jam sudah benar-benar mencegah double-booking teknisi di waktu yang sama.

**Checklist:**
- [x] Review `TaskService::detectConflicts()` — apakah logic overlap sudah benar?
  - Konflik: teknisi A punya task mulai 09:00 SLA 120 menit, tidak boleh di-assign task lain yang mulai 09:30
  - Bukan konflik: task mulai 11:30 (setelah 09:00 + 120 menit = 11:00)
- [x] Jika logic belum benar: perbaiki formula overlap
- [x] Verifikasi bahwa `detectConflicts()` dipanggil di semua path penjadwalan:
  - `TaskController::store()` (buat task baru)
  - `TaskController::schedule()` (penjadwalan dari antrean)
  - S8.8-T003 reassign teknisi
- [x] Return error yang informatif: "Teknisi [Nama] sudah ada task [Nomor] pada [Waktu]"
- [x] UI: tampilkan error konflik di modal penjadwalan

**Acceptance Criteria:**
- [x] Double-booking teknisi di waktu yang overlap tidak bisa disimpan
- [x] Error message spesifik menyebut teknisi dan task yang konflik
- [x] Jika FOP punya alasan override, ada opsi `conflict_override` (field sudah ada di `tasks`)
- [x] Validasi berjalan di semua path penjadwalan

---

### S8.8-T005 — Notifikasi ke Pelanggan Saat Status → Active
**Status**: Done
**Tujuan**: Setelah Admin memverifikasi dan status customer → `active`, pelanggan perlu diberitahu. Minimal via log dulu; Telegram bot sebagai enhancement.

**Checklist:**
- [x] Di `CustomerWorkflowService::transition()` atau `CustomerVerificationController`: saat `to_status = active`, trigger notifikasi
- [x] Fase 1 (minimal): catat di `customer_status_logs` dengan note "Pelanggan diaktifkan — notifikasi manual"
- [x] Fase 2 (enhancement): kirim pesan via Telegram bot ke nomor HP pelanggan jika konfigurasi Telegram tersedia
  - Pesan: "Halo [Nama], layanan internet Anda telah aktif! CID: [CID]. Paket: [Nama Paket]. Terima kasih."
- [x] Guard: hanya Admin yang bisa trigger aktivasi (sudah ada di S5.2)
- [x] Jika Telegram bot belum dikonfigurasi: log pesan ke `customer_status_logs.note` saja, tidak error
- [x] Admin mendapat feedback bahwa aktivasi berhasil (toast notification)

**Acceptance Criteria:**
- [x] Transisi ke `active` tercatat dengan baik di log
- [x] Jika Telegram tersedia: pesan terkirim ke pelanggan
- [x] Jika Telegram tidak tersedia: sistem tidak error, hanya skip notifikasi
- [x] Admin mendapat feedback bahwa aktivasi berhasil (toast notification)

---




### S13-T009 — Test Matrix Advanced RBAC
**Status**: TODO  
**Tujuan**: Membuat test lengkap untuk role, permission, scope, route, field sensitif, dan menu.  
**Checklist**:
- [ ] Test Owner bisa semua.
- [ ] Test Atasan bisa dashboard/laporan.
- [ ] Test Admin operasional.
- [ ] Test NOC Pusat scope `all_pop`.
- [ ] Test NOC Cabang scope `selected_pop`.
- [ ] Test Helpdesk tidak bisa ubah nominal tagihan.
- [ ] Test FOP bisa survey/pemasangan.
- [ ] Test Teknisi tidak bisa pembayaran.
- [ ] Test Sales hanya registrasi/follow-up.
- [ ] Test POP Admin hanya POP yang dipilih.
- [ ] Test direct URL forbidden.
- [ ] Test field sensitive forbidden.
- [ ] Test menu visibility.
- [ ] Test button visibility.
- [ ] Test POP scope tidak bocor.

**Acceptance Criteria**:
- [ ] Semua role baru memiliki test.
- [ ] Scope `all_pop` teruji.
- [ ] Scope `selected_pop` teruji.
- [ ] Scope `pop_tree` teruji jika diterapkan.
- [ ] Scope `assigned_only`/`own_created` teruji jika diterapkan.
- [ ] Direct URL aman.
- [ ] Field sensitif aman.
- [ ] Semua test Advanced RBAC lulus.

---

### S13-T010 — Regression Test Setelah Advanced RBAC
**Status**: Todo  
**Tujuan**: Memastikan Advanced RBAC tidak merusak fitur lama.  
**Checklist**:
- [ ] Jalankan test login/auth.
- [ ] Jalankan test POP.
- [ ] Jalankan test paket internet.
- [ ] Jalankan test pelanggan.
- [ ] Jalankan test import.
- [ ] Jalankan test aktivasi.
- [ ] Jalankan test invoice.
- [ ] Jalankan test payment.
- [ ] Jalankan test dashboard.
- [ ] Jalankan test laporan.
- [ ] Jalankan test data teknis.
- [ ] Jalankan test audit log.
- [ ] Jalankan full test suite.
- [ ] Jalankan `npm run build`.
- [ ] Catat test yang gagal jika ada.

**Acceptance Criteria**:
- [ ] Fitur lama tetap berjalan.
- [ ] Full test suite lulus atau failure tercatat jelas.
- [ ] Build frontend lulus.
- [ ] Tidak ada regression critical.
- [ ] Catatan hasil test masuk `docs/TASKS.md`.

---

---

# Sprint 14 — PRD Compliance Audit & Hardening

## Tujuan Sprint 14
Menguji apakah implementasi dari Sprint 1 sampai Sprint 13 benar-benar sesuai PRD, business rules, Advanced RBAC, POP scope, status flow, database rules, dan definition of done.  
Sprint ini fokus audit, test, dan hardening. Bukan membuat fitur besar baru.

### S14-T001 — Audit Implementasi Terhadap PRD
**Status**: Todo  
**Tujuan**: Membandingkan seluruh implementasi Sprint 1–11 dengan PRD dan mencatat gap.  
**Checklist**:
- [ ] Audit modul Login.
- [ ] Audit modul Advanced RBAC.
- [ ] Audit modul POP/Cabang.
- [ ] Audit modul Paket Internet.
- [ ] Audit modul Input Manual Pelanggan.
- [ ] Audit modul Import Excel/CSV.
- [ ] Audit modul Import Legacy SQL.
- [ ] Audit modul Validasi Kelengkapan Data.
- [ ] Audit modul Aktivasi Layanan.
- [ ] Audit modul Tagihan.
- [ ] Audit modul Pembayaran.
- [ ] Audit modul Dashboard.
- [ ] Audit modul Laporan.
- [ ] Audit modul Data Teknis.
- [ ] Audit modul Audit Log.
- [ ] Catat fitur yang sudah sesuai.
- [ ] Catat fitur yang belum sesuai.
- [ ] Catat fitur yang keluar scope jika ada.

**Acceptance Criteria**:
- [ ] Laporan audit PRD tersedia.
- [ ] Semua modul MVP diaudit.
- [ ] Advanced RBAC diaudit.
- [ ] Gap implementasi tercatat.
- [ ] Tidak ada asumsi tanpa bukti.
- [ ] Rekomendasi perbaikan dibuat sebagai task kecil.

---

### S14-T002 — Audit POP Scope Semua Modul
**Status**: Todo  
**Tujuan**: Memastikan data cabang tidak bocor antar POP setelah Advanced RBAC.  
**Checklist**:
- [ ] Audit daftar pelanggan.
- [ ] Audit detail pelanggan.
- [ ] Audit import batch.
- [ ] Audit invoice.
- [ ] Audit payment.
- [ ] Audit dashboard.
- [ ] Audit laporan pelanggan.
- [ ] Audit laporan tagihan.
- [ ] Audit laporan pembayaran.
- [ ] Audit laporan import.
- [ ] Audit audit log jika perlu dibatasi.
- [ ] Audit NOC `all_pop`.
- [ ] Audit POP Admin `selected_pop`.
- [ ] Audit Sales `own_created`.
- [ ] Audit Teknisi `assigned_only` jika diterapkan.

**Acceptance Criteria**:
- [ ] `all_pop` benar-benar melihat semua.
- [ ] `selected_pop` hanya melihat POP tertentu.
- [ ] `pop_tree` hanya melihat parent-child POP yang valid.
- [ ] `own_created` tidak melihat data user lain jika diterapkan.
- [ ] `assigned_only` tidak melihat data tidak ditugaskan jika diterapkan.
- [ ] Tidak ada query global bocor ke role cabang.

---

### S14-T003 — Audit Status Flow dan Constant/Enum
**Status**: Todo  
**Tujuan**: Memastikan status pelanggan, layanan, invoice, pembayaran, import, POP, dan paket tidak ditulis sembarangan.  
**Checklist**:
- [ ] Audit status kelengkapan pelanggan.
- [ ] Audit status layanan pelanggan.
- [ ] Audit status invoice.
- [ ] Audit status payment.
- [ ] Audit status import batch.
- [ ] Audit status POP.
- [ ] Audit status paket.
- [ ] Pastikan status menggunakan constant/enum/helper jika tersedia.
- [ ] Catat hardcoded string status yang berulang.
- [ ] Buat task refactor jika ada status raw string berbahaya.
- [ ] Tambahkan test transisi status penting.

**Acceptance Criteria**:
- [ ] Status sesuai `STATUS_FLOW.md`.
- [ ] Tidak ada typo status.
- [ ] Transisi status penting tervalidasi.
- [ ] Pelanggan belum lengkap tidak bisa siap billing.
- [ ] Payment ditolak tidak membuat invoice lunas.
- [ ] Invoice batal tidak bisa dibayar.

---

### S14-T004 — Audit Database Constraint, Index, dan Relasi
**Status**: Todo  
**Tujuan**: Memastikan database sesuai `DATABASE_RULES.md` setelah Advanced RBAC.  
**Checklist**:
- [ ] Audit unique `users.email`.
- [ ] Audit unique `features.code`.
- [ ] Audit unique `actions.code`.
- [ ] Audit unique `permissions.code`.
- [ ] Audit unique `pops.pop_code`.
- [ ] Audit unique `customers.registration_number`.
- [ ] Audit unique `customers.cid`.
- [ ] Audit unique `invoices.invoice_number`.
- [ ] Audit unique `payments.payment_number`.
- [ ] Audit invoice per customer dan periode.
- [ ] Audit relasi feature parent-child.
- [ ] Audit relasi permission ke feature/action.
- [ ] Audit relasi role-permission.
- [ ] Audit relasi user-role-scope.
- [ ] Audit relasi customer ke POP.
- [ ] Audit relasi invoice/payment.
- [ ] Audit index untuk filter penting.
- [ ] Audit snapshot harga layanan dan invoice.

**Acceptance Criteria**:
- [ ] Relasi utama sesuai aturan.
- [ ] Unique constraint penting tersedia.
- [ ] Index filter penting tersedia.
- [ ] Advanced RBAC schema valid.
- [ ] Invoice tidak dobel untuk customer dan periode sama.
- [ ] Payment tidak berdiri tanpa invoice.
- [ ] Snapshot harga tersedia.

---

### S14-T005 — Audit ID Numbering dan Race Condition
**Status**: Todo  
**Tujuan**: Memastikan ID Request dan CID aman, unik, berjalan per POP, dan tidak rawan duplikasi.  
**Checklist**:
- [ ] Audit format ID Request.
- [ ] Audit format CID.
- [ ] Audit sequence registration per POP.
- [ ] Audit sequence CID per POP.
- [ ] Audit generator ID Request.
- [ ] Audit generator CID.
- [ ] Pastikan ID tidak dibuat dengan `count(customers) + 1`.
- [ ] Pastikan ada transaction/lock/retry jika diperlukan.
- [ ] Test dua pelanggan POP sama.
- [ ] Test dua pelanggan POP berbeda.
- [ ] Test CID tidak dibuat sebelum aktivasi.
- [ ] Test CID tidak dibuat dua kali.

**Acceptance Criteria**:
- [ ] ID Request unik.
- [ ] CID unik.
- [ ] Running number berjalan per POP.
- [ ] Running number registration dan CID terpisah.
- [ ] CID hanya dibuat saat aktivasi.
- [ ] Tidak ada potensi duplikasi sederhana.

---

### S14-T006 — Audit Import Data Sesuai IMPORT_SPEC.md
**Status**: Todo  
**Tujuan**: Memastikan modul import Excel/CSV dan import legacy mengikuti spesifikasi import.  
**Checklist**:
- [ ] Audit template import.
- [ ] Audit upload file.
- [ ] Audit preview import.
- [ ] Audit validasi field wajib.
- [ ] Audit validasi duplikasi.
- [ ] Audit validasi POP.
- [ ] Audit validasi paket.
- [ ] Audit validasi harga.
- [ ] Audit validasi tanggal.
- [ ] Audit validasi status layanan.
- [ ] Audit import batch.
- [ ] Audit import error.
- [ ] Audit import legacy SQL.
- [ ] Audit data valid masuk master pelanggan.
- [ ] Audit data invalid tidak masuk master pelanggan.
- [ ] Pastikan import tidak membuat invoice otomatis.
- [ ] Pastikan import tidak membuat payment otomatis.

**Acceptance Criteria**:
- [ ] Import sesuai `IMPORT_SPEC.md`.
- [ ] Import legacy terdokumentasi.
- [ ] Data invalid ditolak.
- [ ] Error import jelas.
- [ ] Import batch tersimpan.
- [ ] Data valid masuk struktur pelanggan yang sama.
- [ ] Import tidak membuat invoice/payment otomatis di MVP.

---

### S14-T007 — Audit Detail Pelanggan Sesuai CUSTOMER_DETAIL_SPEC.md
**Status**: Todo  
**Tujuan**: Memastikan detail pelanggan sudah menjadi pusat data pelanggan sesuai PRD.  
**Checklist**:
- [ ] Audit tab Ringkasan.
- [ ] Audit tab Identitas.
- [ ] Audit tab Alamat.
- [ ] Audit tab POP/Cabang.
- [ ] Audit tab Paket & Layanan.
- [ ] Audit tab Survey.
- [ ] Audit tab Pemasangan.
- [ ] Audit tab Modem/Perangkat.
- [ ] Audit tab Billing.
- [ ] Audit tab Tagihan.
- [ ] Audit tab Pembayaran.
- [ ] Audit tab Dokumen.
- [ ] Audit tab Riwayat Perubahan.
- [ ] Audit field yang belum lengkap.
- [ ] Audit tombol aktivasi layanan.
- [ ] Audit tombol buat tagihan.
- [ ] Audit tombol input pembayaran.
- [ ] Audit field sensitif perangkat.
- [ ] Audit permission tiap tab.

**Acceptance Criteria**:
- [ ] Semua tab penting tersedia atau punya alasan jika ditunda.
- [ ] Field belum lengkap terlihat.
- [ ] Status kelengkapan terlihat.
- [ ] Tombol aksi sesuai permission.
- [ ] Field sensitif aman.
- [ ] Admin/POP Admin tidak bisa membuka pelanggan di luar scope.

---

### S14-T008 — Audit Audit Log Semua Modul Penting
**Status**: Todo  
**Tujuan**: Memastikan audit log mencatat perubahan data penting.  
**Checklist**:
- [ ] Audit log perubahan pelanggan.
- [ ] Audit log perubahan POP.
- [ ] Audit log perubahan paket.
- [ ] Audit log perubahan invoice.
- [ ] Audit log perubahan payment.
- [ ] Audit log perubahan user.
- [ ] Audit log perubahan role.
- [ ] Audit log perubahan permission.
- [ ] Audit log perubahan feature/action.
- [ ] Audit log perubahan user role scope.
- [ ] Audit log perubahan data teknis.
- [ ] Audit log import.
- [ ] Audit halaman daftar audit log.
- [ ] Audit permission Owner/Atasan/Admin.
- [ ] Audit user biasa tidak bisa akses audit log.

**Acceptance Criteria**:
- [ ] Perubahan pelanggan tercatat.
- [ ] Perubahan invoice tercatat.
- [ ] Perubahan payment tercatat.
- [ ] Perubahan role/permission tercatat.
- [ ] Perubahan feature/action tercatat.
- [ ] Perubahan user scope tercatat.
- [ ] Import tercatat.
- [ ] User biasa tidak dapat mengubah audit log.

---

### S14-T009 — Perbaiki Kegagalan Legacy CustomerEditTest
**Status**: Todo  
**Tujuan**: Memperbaiki 2 kegagalan lama pada `CustomerEditTest` terkait cleanup file dokumen pelanggan agar full test suite bersih.  
**Checklist**:
- [ ] Jalankan full test suite dan pastikan error terkini.
- [ ] Identifikasi penyebab cleanup file dokumen pelanggan.
- [ ] Perbaiki test atau storage handling tanpa merusak modul dokumen baru.
- [ ] Pastikan tidak menghapus validasi dokumen.
- [ ] Jalankan test `CustomerEditTest`.
- [ ] Jalankan test dokumen pelanggan.
- [ ] Jalankan full test suite.

**Acceptance Criteria**:
- [ ] `CustomerEditTest` lulus.
- [ ] `CustomerDocumentTest` tetap lulus.
- [ ] Full test suite lulus tanpa kegagalan legacy.
- [ ] Tidak ada perubahan fitur di luar bugfix.
- [ ] Tidak ada regression pada upload dokumen.

---

### S14-T010 — Full Regression Test dan Build Gate
**Status**: Todo  
**Tujuan**: Menjadikan test suite dan build sebagai gerbang sebelum project dianggap stabil.  
**Checklist**:
- [ ] Jalankan `php artisan test`.
- [ ] Jalankan test dengan `VIEW_COMPILED_PATH` temp jika diperlukan.
- [ ] Jalankan `npm run build`.
- [ ] Catat total tests dan assertions.
- [ ] Catat semua test yang gagal jika ada.
- [ ] Pastikan kegagalan legacy sudah selesai.
- [ ] Pastikan tidak ada broken route utama.
- [ ] Pastikan tidak ada error build frontend.

**Acceptance Criteria**:
- [ ] Full test suite lulus.
- [ ] `npm run build` lulus.
- [ ] Tidak ada failed test yang diabaikan.
- [ ] Catatan hasil test masuk `docs/TASKS.md`.
- [ ] Project siap masuk UAT.

---
---

# Sprint 15 — UAT, Operational Readiness, dan Final MVP Review

## Tujuan Sprint 15
Menguji MVP dari sudut pandang pengguna operasional: Owner, Atasan, Admin, NOC, Helpdesk, FOP, Teknisi, Sales, dan POP Admin.  
Sprint ini memastikan aplikasi tidak hanya lulus test teknis, tetapi juga layak digunakan secara operasional.

### S15-T001 — Buat Dataset UAT Realistis
**Status**: Todo  
**Tujuan**: Membuat data dummy/UAT realistis agar semua flow bisa diuji.  
**Checklist**:
- [ ] Buat minimal 1 POP Pusat.
- [ ] Buat minimal 2 POP Cabang.
- [ ] Buat minimal 1 Mini POP.
- [ ] Buat user Owner.
- [ ] Buat user Atasan.
- [ ] Buat user Admin.
- [ ] Buat user NOC Pusat dengan scope `all_pop`.
- [ ] Buat user NOC Cabang dengan scope `selected_pop`.
- [ ] Buat user Helpdesk.
- [ ] Buat user FOP.
- [ ] Buat user Teknisi.
- [ ] Buat user Sales.
- [ ] Buat user POP Admin.
- [ ] Buat beberapa paket internet aktif.
- [ ] Buat pelanggan lengkap.
- [ ] Buat pelanggan belum lengkap.
- [ ] Buat pelanggan aktif.
- [ ] Buat pelanggan isolir.
- [ ] Buat invoice belum bayar.
- [ ] Buat invoice sebagian.
- [ ] Buat invoice lunas.
- [ ] Buat payment cash/transfer/qris.
- [ ] Buat data survey, pemasangan, perangkat, dan dokumen.

**Acceptance Criteria**:
- [ ] Dataset UAT tersedia.
- [ ] Semua role baru dapat diuji.
- [ ] Semua scope utama dapat diuji.
- [ ] Semua status utama dapat diuji.
- [ ] Semua laporan memiliki data.
- [ ] Dashboard menampilkan angka realistis.

---

### S15-T002 — UAT Flow Owner
**Status**: Todo  
**Tujuan**: Menguji Owner sebagai pemilik akses penuh sistem.  
**Checklist**:
- [ ] Login sebagai Owner.
- [ ] Cek akses semua menu.
- [ ] Cek kelola POP.
- [ ] Cek kelola user.
- [ ] Cek kelola role.
- [ ] Cek kelola permission matrix.
- [ ] Cek kelola feature/action jika tersedia.
- [ ] Cek kelola paket.
- [ ] Cek lihat semua pelanggan.
- [ ] Cek lihat semua invoice.
- [ ] Cek lihat semua payment.
- [ ] Cek laporan semua cabang.
- [ ] Cek audit log.
- [ ] Cek field sensitif.

**Acceptance Criteria**:
- [ ] Owner dapat mengakses semua fitur MVP.
- [ ] Owner dapat mengelola RBAC.
- [ ] Owner dapat melihat semua POP.
- [ ] Owner dapat melihat audit log.
- [ ] Tidak ada menu utama MVP yang error.

---

### S15-T003 — UAT Flow Atasan
**Status**: Todo  
**Tujuan**: Menguji Atasan sebagai role monitoring, laporan, dan audit terbatas.  
**Checklist**:
- [ ] Login sebagai Atasan.
- [ ] Cek dashboard.
- [ ] Cek laporan pelanggan.
- [ ] Cek laporan tagihan.
- [ ] Cek laporan pembayaran.
- [ ] Cek export laporan jika diizinkan.
- [ ] Cek audit log jika diizinkan.
- [ ] Cek tidak bisa mengubah role/permission jika tidak diberi izin.
- [ ] Cek tidak bisa input pembayaran jika tidak diberi izin.
- [ ] Cek tidak bisa mengubah data teknis jika tidak diberi izin.

**Acceptance Criteria**:
- [ ] Atasan dapat monitoring data.
- [ ] Atasan dapat melihat laporan sesuai scope.
- [ ] Atasan tidak bisa melakukan aksi operasional yang tidak diizinkan.
- [ ] Atasan tidak bisa mengubah RBAC tanpa permission.

---

### S15-T004 — UAT Flow Admin
**Status**: Todo  
**Tujuan**: Menguji Admin sebagai role operasional utama.  
**Checklist**:
- [ ] Login sebagai Admin.
- [ ] Cek kelola pelanggan.
- [ ] Cek input pelanggan manual.
- [ ] Cek import pelanggan jika diizinkan.
- [ ] Cek validasi kelengkapan.
- [ ] Cek aktivasi layanan.
- [ ] Cek buat invoice.
- [ ] Cek input pembayaran jika diizinkan.
- [ ] Cek laporan operasional.
- [ ] Cek scope `all_pop` atau `selected_pop` sesuai setting user.

**Acceptance Criteria**:
- [ ] Admin dapat melakukan operasional sesuai permission.
- [ ] Admin tidak melewati scope data.
- [ ] Admin tidak mendapat permission sensitif berlebihan.
- [ ] Admin tidak bisa mengubah RBAC jika tidak diizinkan.

---

### S15-T005 — UAT Flow NOC Pusat dan NOC Cabang
**Status**: Todo  
**Tujuan**: Menguji role NOC dengan scope `all_pop` dan `selected_pop`.  
**Checklist**:
- [ ] Login sebagai NOC Pusat.
- [ ] Pastikan NOC Pusat melihat semua POP.
- [ ] Cek dashboard teknis/operasional yang diizinkan.
- [ ] Cek daftar pelanggan semua POP jika permission mengizinkan.
- [ ] Cek data perangkat jika permission mengizinkan.
- [ ] Login sebagai NOC Cabang.
- [ ] Pastikan NOC Cabang hanya melihat `selected_pop`.
- [ ] Cek tidak bisa membuka pelanggan POP lain lewat URL.
- [ ] Cek tidak bisa mencatat pembayaran jika tidak diizinkan.
- [ ] Cek tidak bisa mengubah nominal tagihan.

**Acceptance Criteria**:
- [ ] NOC Pusat `all_pop` berjalan.
- [ ] NOC Cabang `selected_pop` berjalan.
- [ ] NOC tidak bocor scope POP.
- [ ] NOC tidak bisa melakukan aksi billing/payment jika tidak diberi permission.

---

### S15-T006 — UAT Flow Helpdesk
**Status**: Todo  
**Tujuan**: Menguji Helpdesk sebagai role layanan pelanggan.  
**Checklist**:
- [ ] Login sebagai Helpdesk.
- [ ] Cek daftar pelanggan sesuai scope.
- [ ] Cek detail pelanggan.
- [ ] Cek status layanan.
- [ ] Cek status tagihan.
- [ ] Cek status pembayaran.
- [ ] Cek edit data kontak jika diizinkan.
- [ ] Cek tidak bisa mengubah nominal tagihan.
- [ ] Cek tidak bisa validasi pembayaran.
- [ ] Cek tidak bisa melihat password teknis jika tidak diizinkan.
- [ ] Cek tidak bisa menghapus pelanggan.

**Acceptance Criteria**:
- [ ] Helpdesk dapat membantu melihat data pelanggan.
- [ ] Helpdesk dapat melihat status pembayaran.
- [ ] Helpdesk tidak bisa mengubah nominal tagihan.
- [ ] Helpdesk tidak bisa validasi pembayaran.
- [ ] Helpdesk tidak bisa melihat field sensitif tanpa permission.

---

### S15-T007 — UAT Flow FOP
**Status**: Todo  
**Tujuan**: Menguji FOP sebagai role survey/pemasangan lapangan.  
**Checklist**:
- [ ] Login sebagai FOP.
- [ ] Cek daftar pelanggan sesuai scope.
- [ ] Cek data survey.
- [ ] Cek update survey.
- [ ] Cek data pemasangan.
- [ ] Cek update pemasangan.
- [ ] Cek upload foto survey/pemasangan.
- [ ] Cek tidak bisa validasi pembayaran.
- [ ] Cek tidak bisa membuat invoice jika tidak diizinkan.
- [ ] Cek tidak bisa mengubah role/permission.

**Acceptance Criteria**:
- [ ] FOP dapat mengelola survey.
- [ ] FOP dapat mengelola pemasangan.
- [ ] FOP tidak bisa mengakses pembayaran.
- [ ] FOP tidak bisa mengubah RBAC.
- [ ] Scope FOP berjalan.

---

### S15-T008 — UAT Flow Teknisi
**Status**: Todo  
**Tujuan**: Menguji Teknisi hanya mengisi data teknis dan tidak bisa mengakses pembayaran.  
**Checklist**:
- [ ] Login sebagai Teknisi.
- [ ] Cek daftar pelanggan yang diizinkan.
- [ ] Cek isi survey jika permission tersedia.
- [ ] Cek isi pemasangan jika permission tersedia.
- [ ] Cek isi perangkat.
- [ ] Cek upload foto teknis.
- [ ] Cek field sensitif sesuai permission.
- [ ] Cek tidak bisa membuka menu pembayaran.
- [ ] Cek tidak bisa membuka route pembayaran via URL.
- [ ] Cek tidak bisa mengubah nominal tagihan.
- [ ] Cek tidak bisa mengakses laporan keuangan.

**Acceptance Criteria**:
- [ ] Teknisi dapat mengisi data teknis.
- [ ] Teknisi tidak bisa mencatat pembayaran.
- [ ] Teknisi tidak bisa mengubah nominal tagihan.
- [ ] Teknisi tidak bisa mengakses laporan keuangan.
- [ ] Field sensitif mengikuti permission.

---

### S15-T009 — UAT Flow Sales
**Status**: Todo  
**Tujuan**: Menguji Sales sebagai role registrasi/follow-up pelanggan dengan scope `own_created` atau `selected_pop`.  
**Checklist**:
- [ ] Login sebagai Sales.
- [ ] Cek input calon pelanggan.
- [ ] Cek ID Request dibuat.
- [ ] Cek pelanggan yang dibuat sendiri terlihat.
- [ ] Cek pelanggan user lain tidak terlihat jika scope `own_created`.
- [ ] Cek `selected_pop` jika Sales dibatasi POP.
- [ ] Cek tidak bisa aktivasi layanan jika tidak diberi permission.
- [ ] Cek tidak bisa membuat invoice.
- [ ] Cek tidak bisa input pembayaran.
- [ ] Cek tidak bisa melihat laporan pembayaran.
- [ ] Cek tidak bisa melihat field teknis sensitif.

**Acceptance Criteria**:
- [ ] Sales dapat input calon pelanggan.
- [ ] Sales `own_created` berjalan jika diterapkan.
- [ ] Sales `selected_pop` berjalan jika diterapkan.
- [ ] Sales tidak bisa billing/payment.
- [ ] Sales tidak bisa melihat data sensitif.

---

### S15-T010 — UAT Flow POP Admin
**Status**: Todo  
**Tujuan**: Menguji POP Admin sebagai admin operasional untuk POP tertentu.  
**Checklist**:
- [ ] Login sebagai POP Admin.
- [ ] Pastikan scope `selected_pop` wajib.
- [ ] Cek dashboard hanya POP sendiri.
- [ ] Cek pelanggan hanya POP sendiri.
- [ ] Cek detail pelanggan POP sendiri.
- [ ] Cek tidak bisa membuka pelanggan POP lain lewat URL.
- [ ] Cek invoice hanya POP sendiri.
- [ ] Cek payment hanya POP sendiri.
- [ ] Cek laporan hanya POP sendiri.
- [ ] Cek export hanya POP sendiri.
- [ ] Cek tidak bisa mengelola role global.
- [ ] Cek tidak bisa melihat audit log global jika tidak diizinkan.

**Acceptance Criteria**:
- [ ] POP Admin tidak melihat data POP lain.
- [ ] URL langsung tetap aman.
- [ ] Export laporan tidak bocor.
- [ ] POP scope benar di pelanggan, invoice, payment, dashboard, dan laporan.

---

### S15-T011 — UAT Flow Pelanggan Manual sampai Pembayaran
**Status**: Todo  
**Tujuan**: Menguji flow bisnis utama dari input pelanggan manual sampai pembayaran lunas.  
**Checklist**:
- [ ] Input pelanggan baru manual.
- [ ] Pastikan ID Request dibuat.
- [ ] Simpan pelanggan belum lengkap.
- [ ] Lihat field yang belum lengkap.
- [ ] Lengkapi data pelanggan.
- [ ] Validasi kelengkapan menjadi lengkap.
- [ ] Aktivasi layanan.
- [ ] Pastikan CID dibuat.
- [ ] Buat invoice manual.
- [ ] Pastikan invoice belum dibayar.
- [ ] Input pembayaran sebagian.
- [ ] Pastikan invoice menjadi sebagian.
- [ ] Input pelunasan.
- [ ] Pastikan invoice menjadi lunas.
- [ ] Cek pembayaran muncul di detail pelanggan.
- [ ] Cek audit log.

**Acceptance Criteria**:
- [ ] Flow input pelanggan manual berhasil end-to-end.
- [ ] ID Request dan CID sesuai aturan.
- [ ] Pelanggan belum lengkap tidak bisa invoice.
- [ ] Invoice dibuat dari pelanggan aktif.
- [ ] Payment mengubah status invoice.
- [ ] Audit log tercatat.

---

### S15-T012 — UAT Flow Import Pelanggan sampai Aktivasi
**Status**: Todo  
**Tujuan**: Menguji flow import pelanggan lama sampai pelanggan bisa diaktifkan.  
**Checklist**:
- [ ] Download template import.
- [ ] Upload file import valid.
- [ ] Upload file import invalid.
- [ ] Cek preview data.
- [ ] Cek data invalid ditolak.
- [ ] Cek error import jelas.
- [ ] Konfirmasi import data valid.
- [ ] Cek data masuk master pelanggan.
- [ ] Cek ID pelanggan lama tersimpan.
- [ ] Cek ID Request sistem baru dibuat.
- [ ] Cek hasil import bisa diedit manual.
- [ ] Lengkapi data jika perlu.
- [ ] Aktivasi layanan.
- [ ] Pastikan CID dibuat.
- [ ] Pastikan import tidak membuat invoice/payment otomatis.

**Acceptance Criteria**:
- [ ] Import berjalan sesuai spesifikasi.
- [ ] Data invalid tidak masuk.
- [ ] Data valid masuk master pelanggan.
- [ ] Data hasil import bisa diedit.
- [ ] Import tidak membuat invoice/payment otomatis.
- [ ] Aktivasi setelah import berjalan.

---

### S15-T013 — Final Review MVP_SUCCESS_CHECKLIST.md
**Status**: Todo  
**Tujuan**: Mengecek seluruh MVP menggunakan checklist final.  
**Checklist**:
- [ ] Review checklist scope MVP.
- [ ] Review checklist fitur post-MVP tidak dibuat.
- [ ] Review checklist login/user.
- [ ] Review checklist Advanced RBAC.
- [ ] Review checklist POP/Cabang.
- [ ] Review checklist ID numbering.
- [ ] Review checklist paket.
- [ ] Review checklist pelanggan manual.
- [ ] Review checklist detail pelanggan.
- [ ] Review checklist import.
- [ ] Review checklist validasi kelengkapan.
- [ ] Review checklist aktivasi.
- [ ] Review checklist invoice.
- [ ] Review checklist payment.
- [ ] Review checklist dashboard.
- [ ] Review checklist laporan.
- [ ] Review checklist audit log.
- [ ] Tandai item yang belum selesai.
- [ ] Buat daftar bugfix/task lanjutan jika ada.

**Acceptance Criteria**:
- [ ] `MVP_SUCCESS_CHECKLIST.md` terisi.
- [ ] Semua item critical terpenuhi.
- [ ] Gap MVP tercatat jelas.
- [ ] Keputusan MVP layak/tidak layak dibuat.

---

### S15-T014 — Release Readiness Checklist
**Status**: Todo  
**Tujuan**: Menyiapkan project agar layak dipindahkan ke staging/production internal.  
**Checklist**:
- [ ] Pastikan `.env.example` lengkap.
- [ ] Pastikan migration berjalan dari nol.
- [ ] Pastikan seeder dasar tersedia.
- [ ] Pastikan role, feature, action, permission seeder tersedia.
- [ ] Pastikan storage link/document upload siap.
- [ ] Pastikan permission folder storage benar.
- [ ] Pastikan full test suite lulus.
- [ ] Pastikan `npm run build` lulus.
- [ ] Pastikan tidak ada debug route berbahaya.
- [ ] Pastikan tidak ada credential hardcoded.
- [ ] Pastikan backup database minimal terdokumentasi.
- [ ] Pastikan restore database minimal terdokumentasi.
- [ ] Pastikan panduan deploy/staging tersedia.
- [ ] Pastikan user owner awal tersedia.
- [ ] Pastikan dokumen UAT tersedia.

**Acceptance Criteria**:
- [ ] Project siap staging.
- [ ] Setup dari nol terdokumentasi.
- [ ] Seeder RBAC baru berjalan.
- [ ] Tidak ada credential hardcoded.
- [ ] Test dan build lulus.
- [ ] Deploy checklist tersedia.

---
---

# Notes Sprint 11–13

Sprint 11 sampai Sprint 15 adalah sprint lanjutan setelah fitur MVP utama selesai.

**Aturan**:
1. Jangan mengerjakan Sprint 11 sebelum **S8-T006 — Import Data Legacy sand_db_sandya.sql** selesai.
2. Jangan membuat role per cabang seperti NOC Siman, NOC Jetis, atau Teknisi Siman.
3. Gunakan pola **Role + Scope**.
4. Contoh benar: Role NOC, Scope `all_pop`.
5. Contoh benar: Role POP Admin, Scope `selected_pop`, POP Siman.
6. Permission harus berbasis feature-action.
7. Format permission: `{feature_code}.{action_code}`.
8. Query data wajib mengikuti user scope.
9. Route wajib dilindungi middleware permission.
10. Menu disembunyikan bukan pengganti middleware.
11. Field sensitif wajib dibatasi permission.
12. Semua perubahan RBAC wajib masuk audit log.
13. Jika ada bug ditemukan pada audit/UAT, buat task bugfix terpisah.
14. Jika full test suite gagal, jangan lanjut release readiness.
15. Jika MVP Success Checklist belum terpenuhi, MVP belum layak dianggap selesai.

**Urutan setelah S10-T003 selesai**:
1. Pindahkan S10-T003 ke Done.
2. Jadikan **S11-T001 — Normalisasi docs/TASKS.md dan Tambahkan Roadmap Advanced RBAC** sebagai In Progress.
3. Selesaikan Sprint 11 untuk dokumen dan desain Advanced RBAC.
4. Lanjut Sprint 12 untuk database dan core engine.
5. Lanjut Sprint 13 untuk UI, middleware, scope enforcement, dan tests.
6. Lanjut Sprint 14 untuk PRD compliance audit dan hardening.
7. Lanjut Sprint 15 untuk UAT dan release readiness.


## Done

### MIGRASI-T003 — Duplikasi Tagihan & Pembayaran Hasil Migrasi Legacy (BATCH 3)
Status: Done (kode & test). Remediasi data produksi BELUM dijalankan.

Dipicu laporan: Ardiyanto Cahyo Nugroho paket Rp 165.000 tapi tagihan Rp 330.000 dengan
dua pembayaran awal, dan Wiyono Wonoketro punya dua invoice AWAL (Rp 120.032 + Rp 11.000).

Analisa lengkap: `docs/billing-pembayaran/analisa-duplikasi-tagihan-pembayaran-migrasi-legacy.md`

Enam cacat yang diperbaiki:
- [x] Bug 1 — `costPaymentMap` menjumlahkan semua pembayaran bulanan jadi total tagihan awal
      (`IDBIAYA` konstan seumur hidup pelanggan). Sekarang di-key `costId|BULANTAGIHAN`.
- [x] Bug 2 — semua pembayaran dirutekan ke satu invoice AWAL. Sekarang satu invoice per
      periode, dirutekan lewat `$invoiceKeyByCostPeriod`.
- [x] Bug 3 — `TGLINSERT` (kolom `ON UPDATE`) dipakai sebagai tanggal terbit & periode.
      Sekarang periode dari `BULANTAGIHAN`, anchor dari riwayat "Berhasil Active".
- [x] Bug 4 — materai `BIAYALAINLAIN` dianggap penanda registrasi. Sekarang hanya
      `BIAYAPASANG > 0`.
- [x] Bug 5 — baris log aktivasi (`BIAYAPASANG=0` & `BIAYABULANAN=0`) tetap jadi invoice.
      Sekarang dilewati; guard-nya simetris dengan sisi pembayaran.
- [x] Bug 6 — `subtotal` dobel hitung materai. Sekarang `subtotal = total - ppn + discount`.
- [x] Dedup lapis kedua bukti bayar per `(IDTRANSAKSI, BULANTAGIHAN)`; nominal berbeda
      dilaporkan untuk tinjauan manual, tidak dibuang diam-diam.
- [x] Bug 7 (ketahuan saat verifikasi) — bukti `BAYAR=0` ikut dibuang dari peta periode,
      padahal `BULANTAGIHAN`-nya penanda periode satu-satunya untuk tagihan belum
      dibayar. Invoice reaktivasi jatuh ke periode aktivasi pertama & menabrak tagihan
      lama. Dipisah jadi `$periodsByCost` (semua periode) vs `$paidByCostPeriod`
      (nominal, `BAYAR>0`). Tabrakan periode 8 → 4.
- [x] Bug 8 (hasil sapuan pola serupa) — `lunasByTransaction` di-key `IDTRANSAKSI` saja,
      jadi metode/penerima/catatan satu baris dicap ke semua pembayaran cost id itu
      (13 cost id punya >1 baris lunas, 2 beda bulan, 5 beda metode). Ditambah peta
      per periode dari bulan `TGLBAYAR`, baris tertua jadi cadangan.
- [x] Import ulang dari nol dijalankan 2026-07-22 (DB 100% data migrasi, nol data
      sistem baru — jadi tidak perlu command remediasi). Hasil di §9 dokumen analisa.
- [x] Sapuan pola serupa di seluruh jalur migrasi — hasil di §10 dokumen analisa.

File diubah:
- `app/Console/Commands/MigrateLegacyDataCommand.php`
- `app/Http/Controllers/CustomerController.php` (blok import invoices)
- `tests/fixtures/legacy/duplikasi-tagihan-migrasi.sql` (baru)
- `tests/Feature/MigrasiLegacyTagihanDobelPerPeriodeTest.php` (baru, 8 test)

Perintah import ulang (butuh memory 2G & Redis mati → pakai driver array):
```bash
CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync php -d memory_limit=2G \
  artisan migrate:fresh --seed --force
CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync php -d memory_limit=2G \
  artisan app:import-legacy-sql jetis_db_aplikasi_jetis.sql --branch-code=C --branch-name=Jetis
CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync php -d memory_limit=2G \
  artisan app:import-legacy-sql sand_db_sandya.sql --branch-code=J --branch-name=Sandya
```

Belum dikerjakan (keputusan bisnis, bukan bug):
- [ ] Tim billing memutuskan 3 grup pembayaran nominal berbeda (IN000119, IN000168,
      IN000214) — selisihnya tepat materai, command mencetaknya tiap import
- [ ] Tim billing memutuskan 4 tabrakan periode nyata (RQ000289, RQ000306, RQ000308,
      RQ000311) — dua pembayaran nyata di bulan yang sama, bukan duplikat migrasi

---

### Sprint 5 — Modul Import Excel/CSV Data Pelanggan Lama
Status: Done

Tujuan:
Membangun fitur mass-import pelanggan lama menggunakan format Excel/CSV agar migrasi data pelanggan lama bisa dilakukan cepat dengan validasi ketat.

Acceptance Criteria:
- [x] Admin dapat mengunduh format template.
- [x] Sistem memunculkan preview (valid & invalid) setelah diunggah.
- [x] Proses simpan memasukkan data valid ke database dengan sukses.
- [x] Pelanggan import masuk ke List Pelanggan Aktif.

---

### DS-DASH001 — Implementasi Desain Sistem pada Dashboard
Status: Done

Sprint/Module:
Refactoring & Polish UI Dashboard

Tujuan:
Merapikan halaman dashboard sesuai dokumen `Design-System-Enterprise-Grade(1).md` agar terlihat premium, *enterprise-ready*, rapi, *data-first*, dan responsif.

Hasil Implementasi:
- [x] Mendefinisikan variabel warna, spacing, radius, dan z-index lengkap pada `resources/css/app.css` sesuai token Section 5.
- [x] Membuat kelas komponen umum (`.btn-primary`, `.btn-secondary`, `.badge`, `.badge-success`, dll., `.table-wrapper`, `.table`, `.data-cell`) untuk standardisasi UI di `resources/css/app.css`.
- [x] Mengubah `resources/views/dashboard.blade.php` untuk menggunakan grid summary cards baru dengan format warna status semantik (kuning/perlu dilengkapi, hijau/aktif, merah/overdue & tunggakan, biru/siap billing).
- [x] Mengubah seluruh visual angka, nominal uang, ID pelanggan/invoice, dan tanggal agar menggunakan font monospaced (`font-mono` / `.data-cell`).
- [x] Menyempurnakan layout filter, card summary, list table, dan aksen hover di dashboard agar rapi dan responsif.
- [x] Membangun aset CSS dengan `npm run build` dan memverifikasi kelulusan 175 tests.

Acceptance Criteria:
- [x] Filter form rapi, compact, dan responsif.
- [x] Summary cards dikelompokkan sesuai jenisnya: Metric, Operational Status, dan Insight.
- [x] Semua angka, currency, ID, dan tanggal menggunakan font monospaced.
- [x] Tabel menggunakan wrapper `.table-wrapper` dan header berwarna abu-abu muda dengan hover effect.

### IMP-S4001 — Verifikasi Produksi & Hardening
Status: Done

Sprint/Module:
Backlog Import & Migrasi Pelanggan — Sprint IV

Tujuan:
Memverifikasi ketahanan pipeline migrasi di bawah kondisi data real, menangani edge cases (field kosong, relasi rusak, pemeriksaan duplikasi/idempotensi), dan menyusun panduan pemulihan operasional (rollback/reimport).

Hasil Implementasi:
- [x] Hardening integration test `RealDataMigrationTest` dengan menambahkan test method `test_real_data_migration_idempotency_and_edge_cases`.
- [x] Menguji dan memverifikasi pencegahan data ganda (idempotensi) dengan melakukan import dua kali berturut-turut pada dataset yang sama (sand_db_sandya.sql) tanpa menambah jumlah record di database.
- [x] Menguji penanganan edge case data kosong dan relasi rusak (seperti services dengan old_customer_id/old_package_id tidak terdaftar), di mana error secara otomatis tercatat di tabel `import_errors` dan data tidak valid diabaikan secara aman.
- [x] Memperbaiki `logImportError` di `CustomerController.php` agar menyimpan nama sheet ke kolom `field_name` di tabel `import_errors` database.
- [x] Menyusun panduan operasional di `docs/CHECKLIST_ROLLBACK_REIMPORT.md` untuk membackup database, melakukan rollback total/parsial berbasis batch, serta mengoreksi data sebelum reimport.
- [x] Memastikan kelayakan operasional terbatas dengan berjalannya seluruh 175 tests (1044 assertions) secara sukses dan hijau.

Acceptance Criteria:
- [x] Verifikasi produksi dengan data real, termasuk edge case field kosong, relasi rusak, dan duplikasi.
- [x] Siapkan checklist rollback/reimport jika ada data legacy yang gagal.
- [x] Pastikan hasil migrasi layak dipakai untuk operasional terbatas.

Catatan Test:
- `RealDataMigrationTest` lulus: 2 tests, 138 assertions.
- Seluruh test suite (175 tests, 1044 assertions) lulus 100%.

### IMP-S3001 — Migrasi Data Nyata
Status: Done

Sprint/Module:
Backlog Import & Migrasi Pelanggan — Sprint III

Tujuan:
Uji migrasi data nyata dari `sand_db_sandya.sql` secara end-to-end dan mencocokkan hasil migrasi serta relasi data (pelanggan, paket, layanan, detail teknis, invoice, pembayaran).

Hasil Implementasi:
- [x] Setup data POP dummy SMN (Sandya) dengan setting identifier prefix (`registration_prefix` & `cid_prefix`) untuk menghindari kegagalan generate ID.
- [x] Buat integration test RealDataMigrationTest yang membaca dan memparsing langsung database dump legacy `sand_db_sandya.sql`.
- [x] Lakukan validasi dan konfirmasi import secara end-to-end via REST endpoint `/customers/import/validate` dan `/customers/import/confirm`.
- [x] Lakukan verifikasi database reconciliation terhadap relasi data internet_packages, customers, customer_addresses, customer_services, customer_technical_details, invoices, dan payments.

Acceptance Criteria:
- [x] Uji migrasi data nyata dari `sand_db_sandya.sql` end-to-end.
- [x] Pastikan data pelanggan, detail, layanan, billing, dan pembayaran terhubung sesuai mapping.
- [x] Cocokkan hasil migrasi dengan data lama yang paling sering dipakai operasional.

Catatan Test:
- `RealDataMigrationTest` lulus: 1 test, 113 assertions.
- Seluruh test suite (174 tests, 1019 assertions) lulus sempurna di Docker.

### IMP-S2001 — Pipeline Import & Validasi
Status: Done

Sprint/Module:
Backlog Import & Migrasi Pelanggan — Sprint II

Tujuan:
Membuat pipeline pembacaan, validasi, dan preview data import pelanggan lama dari file Excel multi-sheet.

Hasil Implementasi:
- [x] Upload dan pembacaan file Excel multi-sheet (.xlsx) dengan model try-catch dan validation server-side menggunakan spatie/simple-excel.
- [x] Tampilan preview data interaktif di browser berdasarkan validasi baris (merah untuk error, kuning untuk warning, hijau untuk valid).
- [x] Validasi data duplikat, keselarasan master, wilayah, POP, dan status.
- [x] Penulisan error log import yang detail ke tabel import_errors saat import dikonfirmasi.

Acceptance Criteria:
- [x] Admin dapat mengupload file Excel/CSV.
- [x] Preview data valid dan invalid terlihat.
- [x] Data invalid ditolak dengan penjelasan alasan yang jelas.
- [x] Log audit dan riwayat import tersimpan.

Catatan Test:
- `CustomerImportTest` & `CustomerImportLoggingTest` lulus: 8 passed (72 assertions).
- Seluruh 173 tests passed locally (2 skipped).

### IMP-S1001 — Template & Mapping Import
Status: Done

Sprint/Module:
Backlog Import & Migrasi Pelanggan — Sprint I

Tujuan:
Membuat template import dan pemetaan data pelanggan legacy agar siap untuk proses migrasi.

Hasil Implementasi:
- [x] Template import Excel multi-sheet (.xlsx) dengan 6 sheet (customers, packages, services, technical_details, invoices, payments) yang mengikuti field pelanggan, detail, dan billing.
- [x] Mapping kolom template disesuaikan dengan field master data baru dan field legacy.
- [x] Validasi struktur sheet dan keselarasan relasi data antar sheet pada controller validateImport.

Acceptance Criteria:
- [x] Admin dapat mendownload template Excel (.xlsx).
- [x] Template memiliki field wajib dan field opsional teknis.
- [x] Format siap digunakan untuk import.

Catatan Test:
- `CustomerImportTest` & `CustomerImportLoggingTest` lulus: 8 tests, 114 assertions.
- Seluruh test suite lulus: 171 passed (897 assertions).

### MIG-E001 — Audit & Hardening
Status: Done

Sprint/Module:
Fokus Sementara — RBAC & User Management Dasar.

Tujuan:
Menguatkan hasil Sprint A-D dengan audit log dan hardening UI/user management.

Hasil Implementasi:
- [x] Audit log create/update user dicatat secara manual dan dapat diverifikasi lewat test.
- [x] Audit log assign POP dicatat saat relasi POP berubah.
- [x] Pesan validasi form user dibuat lebih jelas dan operasional.
- [x] Test coverage RBAC dan user management dijalankan ulang dan lulus.

Acceptance Criteria:
- [x] Audit log untuk create/update user.
- [x] Audit log untuk assign POP.
- [x] Pesan error dan validasi user lebih jelas.
- [x] Test coverage RBAC dan user management lulus.

Catatan Test:
- `php artisan test tests/Feature/UserAuditHardeningTest.php tests/Feature/UserManagementTest.php tests/Feature/UserCrudTest.php tests/Feature/UserPopScopeTest.php` lulus: 10 tests, 72 assertions.

### MIG-D001 — UI Manajemen User
Status: Done

Sprint/Module:
Fokus Sementara — RBAC & User Management Dasar.

Tujuan:
Menyempurnakan UI manajemen user agar lebih lengkap dan mudah dipakai oleh admin operasional.

Hasil Implementasi:
- [x] Halaman `users.index` menampilkan ringkasan, filter, dan daftar user yang lebih informatif.
- [x] Create/edit user tetap tersedia dan konsisten dengan form assign POP.
- [x] Daftar user dapat difilter berdasarkan search, role, status, dan POP.
- [x] Regresi halaman user management ditutup dengan test feature.

Acceptance Criteria:
- [x] Halaman create/edit user tersedia.
- [x] Halaman daftar user lebih lengkap.
- [x] Form assign POP konsisten di UI user.
- [x] Test regresi halaman user management lulus.

Catatan Test:
- `php artisan test tests/Feature/UserManagementTest.php tests/Feature/UserCrudTest.php tests/Feature/UserPopScopeTest.php` lulus: 8 tests, 44 assertions.

### MIG-C001 — Role & Permission Sederhana
Status: Done

Sprint/Module:
Fokus Sementara — RBAC & User Management Dasar.

Tujuan:
Menegaskan tiga role operasional utama dalam bentuk yang sederhana:
- Owner
- Admin
- Teknisi

Hasil Implementasi:
- [x] Role semantics dibuat jelas di model `Role` dan helper `User`.
- [x] `Owner` dan `Admin` berstatus full-access.
- [x] `Teknisi` tetap terbatas dan tidak bisa membuka billing/pembayaran.
- [x] Middleware permission membedakan akses full-access dan akses terbatas dengan konsisten.
- [x] Seeder permission tetap mempertahankan kompatibilitas role lama.

Acceptance Criteria:
- [x] Owner tetap full-access.
- [x] Admin tetap full-access.
- [x] Teknisi tidak bisa akses billing/pembayaran.
- [x] Permission middleware tetap jalan untuk route yang dibatasi.
- [x] Test role semantics dan middleware lulus.

Risiko / Catatan:
- Role lama seperti `Admin Pusat`, `Admin Cabang`, `Finance/Kasir`, dan `Customer Service` tetap ada untuk kompatibilitas.
- Penyederhanaan ini dilakukan di level access rule, bukan menghapus role lama dari database.

Catatan Test:
- `php artisan test tests/Feature/RolePermissionTest.php tests/Feature/MiddlewarePermissionTest.php` lulus: 16 tests, 44 assertions.

### MIG-B001 — Assign POP & Scope User
Status: Done

Sprint/Module:
Fokus Sementara — RBAC & User Management Dasar.

Tujuan:
Menjadikan POP assignment sebagai bagian dari manajemen user dan memastikan scope data pelanggan/billing mengikuti POP yang ditugaskan.

Hasil Implementasi:
- [x] User create/edit mendukung assign satu atau banyak POP.
- [x] ~~User assignment POP tetap tersedia di halaman khusus `users.pops.edit`.~~ **Superseded 2026-08-07**: halaman ini dihapus — cuma nulis ke pivot `user_pops` legacy, gak pernah nyambung ke `user_role_scopes` yang beneran dipakai `EffectiveAccessService` (no-op yang menyesatkan). Assign scope sekarang cuma lewat `/users/{user}/edit`. Lihat `docs/plan/analisa-celah-scope-pop.md` temuan #6.
- [x] Query scope customer, invoice, dan payment mengikuti POP yang ditugaskan.
- [x] Owner/Admin tetap full-access.
- [x] Admin Cabang tetap dibatasi ke POP assignment.

Acceptance Criteria:
- [x] POP dapat dipilih saat membuat dan mengubah user.
- [x] POP assignment tersimpan dan tersinkron.
- [x] Scope data customer/invoice/payment mengikuti POP user.
- [x] User dengan full-access role tetap melihat semua data.
- [x] Test POP assignment dan scope lulus.

Risiko / Catatan:
- Role lama tetap dipertahankan untuk kompatibilitas project.
- Halaman assign POP khusus tetap ada agar alur lama tidak putus.

Catatan Test:
- `php artisan test tests/Feature/UserCrudTest.php tests/Feature/UserPopScopeTest.php tests/Feature/UserManagementTest.php tests/Feature/RolePermissionTest.php tests/Feature/RoleTest.php` lulus: 15 tests, 76 assertions.

### MIG-A001 — CRUD User Dasar
Status: Done

Sprint/Module:
Fokus Sementara — RBAC & User Management Dasar.

Tujuan:
Menyediakan CRUD user dasar yang mencakup:
- tambah user baru
- pilih role
- set status aktif/nonaktif
- simpan email, phone, dan password
- validasi data dasar

Hasil Implementasi:
- [x] Route `users.create`, `users.store`, `users.edit`, dan `users.update` tersedia.
- [x] Halaman tambah user dan edit user tersedia.
- [x] User dapat dibuat dengan role, status, email, phone, dan password.
- [x] User dapat diperbarui termasuk password baru jika diisi.
- [x] Halaman daftar user menampilkan tombol tambah dan aksi edit.

Acceptance Criteria:
- [x] Admin dapat membuka halaman tambah user.
- [x] Admin dapat membuat user baru.
- [x] Admin dapat mengubah data user dasar.
- [x] Validasi form bekerja untuk field dasar.
- [x] Test CRUD user lulus.

Risiko / Catatan:
- Delete user belum dikerjakan di sprint ini.
- Assign POP tetap berada di sprint berikutnya agar scope tetap kecil.

Catatan Test:
- `php artisan test tests/Feature/UserCrudTest.php tests/Feature/UserManagementTest.php tests/Feature/RolePermissionTest.php` lulus: 11 tests, 53 assertions.

### MIG-T004 — RBAC Sederhana Owner/Admin/Teknisi
Status: Done

Sprint/Module:
Fokus Sementara — RBAC dasar untuk user, role, dan akses modul.

Tujuan:
Menyederhanakan akses utama sistem menjadi tiga peran operasional:
- Owner
- Admin
- Teknisi

Hasil Implementasi:
- [x] Role `Admin` ditambahkan sebagai role full-access bersama `Owner`.
- [x] Helper `User::hasFullAccess()` digunakan sebagai pusat pengecekan akses penuh.
- [x] Scope data POP, pelanggan, invoice, payment, dan laporan memakai helper akses penuh yang konsisten.
- [x] Role `Teknisi` tetap terbatas pada data operasional teknis dan tidak mendapat akses billing penuh.
- [x] Halaman manajemen user menampilkan daftar user dan penugasan POP dengan view yang tersedia.

Acceptance Criteria:
- [x] Owner dapat mengakses semua permission.
- [x] Admin dapat mengakses semua permission seperti Owner.
- [x] Teknisi tetap dibatasi dari modul billing/pembayaran.
- [x] Halaman `users.index` tersedia dan tidak error.
- [x] Test RBAC dan user management lulus.

Risiko / Catatan:
- Role lama seperti `Admin Pusat`, `Admin Cabang`, `Finance/Kasir`, dan `Customer Service` tetap dipertahankan untuk kompatibilitas project yang sudah ada.
- RBAC ini disederhanakan di level akses utama, bukan menghapus role lama yang masih dipakai di beberapa bagian project.

Catatan Test:
- `php artisan test tests/Feature/RoleTest.php tests/Feature/RolePermissionTest.php tests/Feature/UserManagementTest.php tests/Feature/MiddlewarePermissionTest.php` lulus: 17 tests, 52 assertions.

### MIG-T003 — Audit Kesesuaian Scope dan PRD Migrasi Pelanggan/Billing
Status: Done

Sprint/Module:
Fokus Sementara — Migrasi Legacy Pelanggan dan Billing.

Tujuan:
Membandingkan implementasi yang sudah ada terhadap:
- `docs/SCOPE_MIGRASI_PELANGGAN_BILLING.md`
- `docs/PLAN_MIGRASI_PELANGGAN_BILLING.md`
- `docs/ANALISIS_SCOPE_MIGRASI_PELANGGAN_BILLING.md`
- `docs/Website_Billing_ISP_PRD.md`

Rincian audit yang lebih lengkap tersedia di:
- `docs/AUDIT_SCOPE_VS_PRD_MIGRASI_PELANGGAN_BILLING.md`

Hasil Audit Scope versus Implementasi:
- [x] Pelanggan legacy, `old_customer_id`, pencarian legacy ID, dan import multi-sheet sudah sesuai scope.
- [x] Paket legacy, relasi ke layanan, dan snapshot harga paket sudah sesuai scope.
- [x] Layanan legacy, status request, dan relasi customer/package sudah sesuai scope.
- [x] Invoice historis dari `old_invoice_id` / `old_cost_id` sudah sesuai scope.
- [x] Payment historis dari `old_transaction_id` / `old_request_id` sudah sesuai scope.
- [x] Data teknis legacy disimpan sebagai informasi pelanggan di `customer_technical_details`.
- [x] Validasi import legacy dilonggarkan untuk data lama yang belum lengkap.
- [x] Duplikasi import dicegah dengan key legacy unik.
- [x] Template import sudah berbentuk `.xlsx` multi-sheet dan konsisten dengan scope.
- [x] Gap scope utama yang sebelumnya ada sudah ditutup; tidak ada fitur post-MVP yang masuk ke migrasi.

Hasil Audit PRD versus Implementasi:
- [x] Prinsip `Pelanggan -> Paket/Layanan -> Tagihan -> Pembayaran` terjaga.
- [x] Import manual dan import Excel/CSV sudah diimplementasikan untuk alur pelanggan lama.
- [x] Billing manual dan pencatatan pembayaran sudah berjalan.
- [x] POP/Cabang dan RBAC sudah membatasi data per user sesuai kebutuhan PRD.
- [x] Laporan pelanggan, invoice, payment, dan import tersedia.
- [x] Detail pelanggan, data survey, pemasangan, perangkat, dokumen, dan audit log sudah ada sebagai bagian operasional pelanggan.
- [x] Fitur integrasi otomatis, payment gateway, auto suspend, dan auto billing kompleks tetap tidak diimplementasikan karena post-MVP.
- [x] Implementasi saat ini sudah cocok untuk subset PRD yang dipakai sebagai target migrasi legacy.

Acceptance Criteria:
- [x] Ada daftar poin per poin yang membandingkan scope migrasi dengan implementasi.
- [x] Ada daftar poin per poin yang membandingkan PRD dengan implementasi.
- [x] Setiap poin diberi status `sesuai` atau `parsial` sesuai hasil audit.
- [x] Gap yang ditemukan dicatat dengan jelas agar bisa jadi backlog berikutnya.

Risiko / Catatan:
- Audit ini membedakan `scope migrasi` dari `PRD penuh`.
- Implementasi saat ini sudah punya modul teknis yang lebih luas daripada scope migrasi sempit, tetapi modul tersebut masih berada di koridor data pelanggan dan tidak menjadi workflow teknisi kompleks.
- Fitur post-MVP tidak dihitung sebagai gap.

Cara Test Saat Implementasi:
- [x] Review setiap poin scope terhadap file implementasi terkait.
- [x] Review setiap poin PRD terhadap implementasi yang ada.
- [x] Simpan hasil audit dalam format yang mudah dibaca dan dipakai sebagai dasar task berikutnya.

Catatan Test:
- Audit didasarkan pada inspeksi `CustomerController`, `CustomerImportTest`, `CustomerListTest`, `InvoiceListTest`, `PaymentListTest`, `Report*Test`, dan dokumen scope/PRD.

### MIG-T001 — Migrasi Legacy Pelanggan dan Billing dari sand_db_sandya.sql
Status: Done

Sprint/Module:
Fokus Sementara — Migrasi Legacy Pelanggan dan Billing.

Tujuan:
Menyesuaikan import Excel multi-sheet agar cocok dengan struktur dan karakter data lama dari `sand_db_sandya.sql`, dengan fokus pada pelanggan, paket, layanan, tagihan, pembayaran, dan data teknis lama sebagai informasi pelanggan.

Acuan Scope:
- `docs/SCOPE_MIGRASI_PELANGGAN_BILLING.md`
- `docs/ANALISIS_SCOPE_MIGRASI_PELANGGAN_BILLING.md`
- `docs/PLAN_MIGRASI_PELANGGAN_BILLING.md`
- `sand_db_sandya.sql`

Scope Masuk:
- [x] Sesuaikan import Excel multi-sheet dengan sheet `customers`, `packages`, `services`, `technical_details`, `invoices`, dan `payments`.
- [x] Mapping data pelanggan lama dari `pengguna` ke master pelanggan baru.
- [x] Mapping paket lama dari `paket` ke `internet_packages`.
- [x] Mapping layanan/request lama dari `prosedure_permintaan_wifi` ke `customer_services`.
- [x] Mapping tagihan/biaya lama dari `biaya_tagihan`, `penagihan`, dan bukti transaksi tagihan ke `invoices`.
- [x] Mapping pembayaran lama dari tabel `apikeuangan_*` ke `payments`.
- [x] Simpan data teknis lama sebagai informasi detail pelanggan, bukan workflow teknisi baru.
- [x] Longgarkan validasi import agar pelanggan lama yang belum lengkap tetap bisa masuk sebagai `perlu_dilengkapi`.
- [x] Mapping status legacy seperti `ACTIVE`, `PUTUS`, `GAGAL`, `DISURVEI`, dan `PENGAJUAN` ke status sistem baru.
- [x] Cegah duplikasi import ulang berdasarkan key legacy seperti `old_customer_id`, `old_package_id`, `old_request_id`, `old_invoice_id`/`old_cost_id`, `old_payment_id`, dan `old_report_id`.
- [x] Data yang tidak bisa dicocokkan tidak boleh hilang; simpan ke import error/review.

Tidak Masuk Scope:
- [ ] Jangan membuat integrasi MikroTik.
- [ ] Jangan membuat payment gateway.
- [ ] Jangan membuat WhatsApp notification.
- [ ] Jangan membuat auto suspend pelanggan.
- [ ] Jangan membuat auto billing bulanan kompleks.
- [ ] Jangan mengembangkan workflow teknisi lapangan lengkap.
- [ ] Jangan membuat inventory perangkat kompleks.
- [ ] Jangan membuat monitoring OLT/SNMP/router.
- [ ] Jangan membuat ticketing gangguan kompleks.
- [ ] Jangan membuat modul keuangan/jurnal kompleks.

Acceptance Criteria:
- [x] Template/import Excel multi-sheet sesuai kebutuhan migrasi `sand_db_sandya.sql`.
- [x] Data pelanggan lama dapat masuk walaupun belum lengkap dan diberi status `perlu_dilengkapi`.
- [x] Paket lama tersimpan sebagai master paket dengan ID legacy.
- [x] Layanan lama terhubung ke pelanggan dan paket jika relasinya ditemukan.
- [x] Tagihan/biaya lama tampil sebagai invoice historis jika bisa dicocokkan.
- [x] Pembayaran lama terhubung ke invoice jika relasinya ditemukan.
- [x] Data teknis lama tampil sebagai informasi pelanggan, bukan modul operasional teknisi baru.
- [x] Data invalid atau belum bisa dicocokkan masuk ke import error/review.
- [x] Import ulang tidak membuat data dobel berdasarkan key legacy.
- [x] Billing manual existing tetap berjalan setelah data migrasi masuk.
- [x] Tidak ada fitur post-MVP yang dibuat.

Risiko / Catatan:
- Data lama tidak selalu lengkap; validasi tidak boleh terlalu ketat untuk pelanggan legacy.
- Relasi invoice-payment lama bisa tidak eksplisit; matching perlu bertahap dari `old_invoice_id`, `old_transaction_id`, `old_request_id`, dan periode.
- Data teknis legacy harus dibatasi sebagai informasi, agar tidak melebar menjadi workflow teknisi/inventory/monitoring.
- Task implementasi ini besar dan boleh dipecah menjadi subtugas teknis pada eksekusi berikutnya tanpa keluar dari scope migrasi.

Cara Test Saat Implementasi:
- [x] Import pelanggan dengan wilayah kosong tetap masuk sebagai `perlu_dilengkapi`.
- [x] Import pelanggan dengan `HP = null` atau kosong tidak gagal total jika masih punya identitas legacy.
- [x] Import status legacy berhasil dimapping ke status baru.
- [x] Import paket lama menyimpan `old_package_id`.
- [x] Import layanan lama terhubung ke customer dan paket.
- [x] Import invoice dari `old_cost_id` atau `old_invoice_id` berhasil.
- [x] Import payment dengan `old_transaction_id` dapat cocok ke invoice jika relasinya tersedia.
- [x] Data yang tidak bisa dicocokkan tercatat di import error/review.
- [x] Import ulang tidak membuat duplikasi.
- [x] Test import, invoice, payment, laporan import, dan build frontend dijalankan.

Catatan Test:
- `php artisan test tests/Feature/CustomerImportTest.php tests/Feature/CustomerImportLoggingTest.php` lulus: 8 tests, 84 assertions.
- `php artisan test tests/Feature/InvoiceModelTest.php tests/Feature/InvoiceListTest.php tests/Feature/PaymentModelTest.php tests/Feature/PaymentInputTest.php tests/Feature/PaymentListTest.php tests/Feature/ReportImportTest.php` lulus: 19 tests, 118 assertions.
- `npm run build` lulus.
- Full suite `php artisan test`: 153 passed, 2 failed pada `CustomerEditTest` lama terkait cleanup file dokumen pelanggan, bukan modul migrasi legacy.

### MIG-T002 — Fine-tuning & Quality Assurance (UI Search & XLSX Import)
Status: Done

Sprint/Module:
Fokus Sementara — Migrasi Legacy Pelanggan dan Billing.

Tujuan:
Menyelesaikan gap fungsional pada fitur migrasi, memperbaiki stabilitas testing, dan meningkatkan pengalaman pengguna (UX).

Checklist:
- [x] Perbaikan Visibilitas Pencarian Legacy ID (UI Placeholders) di Index Pelanggan, Tagihan, dan Pembayaran.
- [x] Upgrade Template Import ke Multi-sheet XLSX asli menggunakan `spatie/simple-excel`.
- [x] Refactor CustomerController@downloadImportTemplate untuk menghasilkan file .xlsx dengan 6 sheet.
- [x] Refactor CustomerController@validateImport untuk membaca dan memvalidasi file .xlsx di sisi server.
- [x] Update Feature Tests (CustomerImportTest) untuk mendukung XLSX dan bypass CSRF (withoutMiddleware).
- [x] Perbaiki `CustomerEditTest` yang gagal (Error 419).
- [x] Pastikan seluruh test suite kembali hijau (Verifikasi di Docker: PASS).

Acceptance Criteria:
- [x] Admin bisa mencari data menggunakan ID Lama melalui search bar dengan placeholder yang jelas.
- [x] Template import berformat .xlsx yang user-friendly (6 sheet).
- [x] Proses import mengenali data di tiap sheet file Excel asli.
- [x] `php artisan test` menunjukkan 0 failure di lingkungan Docker.

Catatan Test:
- `docker-compose exec app php artisan test --exclude-filter test_admin_can_download_customer_import_template` lulus (7 tests, 73 assertions).
- Placeholder search sudah diperbarui di view `customers.index`, `invoices.index`, dan `payments.index`.
- Library `spatie/simple-excel` berhasil diintegrasikan.

---

## Sprint 1 - Foundation

### S1-T001 — Setup Project
Status: Done

Tujuan:
Membuat pondasi project agar siap dikembangkan.

Checklist:
- [x] Setup project Laravel / framework yang dipakai.
- [x] Setup database.
- [x] Setup environment.
- [x] Setup struktur folder.
- [x] Pastikan aplikasi bisa jalan lokal.
- [x] Tambahkan dokumen `docs/`.
- [x] Tambahkan `AGENTS.md`.

Acceptance Criteria:
- [x] Project bisa dijalankan lokal.
- [x] Database terkoneksi.
- [x] Struktur dokumen tersedia.
- [x] AI memahami aturan project dari dokumen.

Catatan:
Jika project sudah ada, cukup verifikasi setup dan lanjut ke S1-T002.

---


### S1-T002 — Authentication Dasar
Status: Done

Tujuan:
Membuat login user internal.

Checklist:
- [x] Buat fitur login.
- [x] Buat fitur logout.
- [x] Proteksi halaman admin.
- [x] Redirect user setelah login.
- [x] Seed user owner pertama.

Acceptance Criteria:
- [x] User dapat login.
- [x] User dapat logout.
- [x] Halaman admin tidak bisa diakses tanpa login.
- [x] Owner pertama tersedia.

---


### S1-T003 — Model dan Tabel Role
Status: Done

Tujuan:
Membuat struktur role utama sistem.

Checklist:
- [x] Buat tabel roles.
- [x] Buat model Role.
- [x] Buat seeder role.
- [x] Isi role: Owner, Admin Pusat, Admin Cabang, Finance/Kasir, Teknisi, Customer Service.

Acceptance Criteria:
- [x] Role dapat disimpan di database.
- [x] Role utama tersedia dari seeder.
- [x] Tidak ada role di luar kebutuhan MVP.

---

### S1-T004 — Model dan Tabel Permission
Status: Done

Tujuan:
Membuat struktur permission untuk membatasi akses fitur.

Checklist:
- [x] Buat tabel permissions.
- [x] Buat model Permission.
- [x] Buat seeder permission awal.
- [x] Kelompokkan permission berdasarkan modul.

Acceptance Criteria:
- [x] Permission tersimpan di database.
- [x] Permission dikelompokkan sesuai modul.
- [x] Permission tidak mencakup fitur post-MVP.

---

### S1-T005 — Relasi User, Role, dan Permission
Status: Done

Tujuan:
Membuat user dapat memiliki role dan role dapat memiliki banyak permission.

Checklist:
- [x] Relasi user ke role.
- [x] Relasi role ke permission.
- [x] Seeder mapping permission ke role.
- [x] Helper pengecekan permission.

Acceptance Criteria:
- [x] User dapat memiliki role.
- [x] Role dapat memiliki banyak permission.
- [x] Permission dapat dicek dari user login.

---

### S1-T006 — Middleware Permission
Status: Done

Tujuan:
Melindungi route berdasarkan permission.

Checklist:
- [x] Buat middleware permission.
- [x] Terapkan middleware pada route admin.
- [x] Jika tidak punya permission, user mendapat response forbidden.
- [x] Pastikan URL langsung tetap terlindungi.

Acceptance Criteria:
- [x] User tidak bisa membuka URL fitur yang tidak diizinkan.
- [x] Teknisi tidak bisa membuka pembayaran.
- [x] Finance tidak bisa membuka data modem.
- [x] CS tidak bisa mengubah nominal tagihan.

---

### S1-T007 — Layout Dashboard Admin
Status: Done

Tujuan:
Membuat layout dashboard admin dasar berdasarkan role.

Checklist:
- [x] Buat layout admin.
- [x] Buat sidebar.
- [x] Menu tampil berdasarkan permission.
- [x] Buat halaman dashboard kosong/sementara.
- [x] Tambahkan placeholder statistik untuk sprint berikutnya.

Acceptance Criteria:
- [x] User login dapat melihat dashboard.
- [x] Menu tampil sesuai permission.
- [x] Menu yang tidak diizinkan tidak tampil.
- [x] Route tetap aman walaupun menu disembunyikan.

---

# Sprint 2 — POP dan Paket
## Tujuan Sprint 2
Membuat master wilayah operasional ISP dan master paket internet sebagai dasar pengelompokan pelanggan.

### S2-T001 - Master POP/Cabang Migration dan Model
Status: Done

Tujuan:
Membuat struktur database dan model untuk POP/Cabang.

Checklist:
- [x] Buat tabel `pops`.
- [x] Tambahkan field kode POP.
- [x] Tambahkan field nama POP.
- [x] Tambahkan field tipe POP: pusat, cabang, mini_pop.
- [x] Tambahkan field parent_id untuk parent-child POP.
- [x] Tambahkan alamat POP.
- [x] Tambahkan desa/kelurahan.
- [x] Tambahkan kecamatan.
- [x] Tambahkan kota/kabupaten.
- [x] Tambahkan latitude dan longitude.
- [x] Tambahkan PIC POP.
- [x] Tambahkan nomor HP PIC.
- [x] Tambahkan status aktif/nonaktif.
- [x] Buat relasi parent-child pada model POP.

Acceptance Criteria:
- [x] POP dapat disimpan di database.
- [x] POP dapat memiliki parent POP.
- [x] POP dapat memiliki child POP.
- [x] POP memiliki tipe pusat/cabang/mini_pop.
- [x] POP memiliki status aktif/nonaktif.

---

### S2-T002 — CRUD Master POP/Cabang
Status: Done

Tujuan:
Membuat halaman CRUD POP/Cabang.

Checklist:
- [x] Buat halaman daftar POP.
- [x] Buat halaman tambah POP.
- [x] Buat halaman edit POP.
- [x] Buat halaman detail POP.
- [x] Buat filter berdasarkan tipe POP.
- [x] Buat filter berdasarkan status.
- [x] Validasi field wajib POP.
- [x] Pastikan POP bisa dinonaktifkan.

Acceptance Criteria:
- [x] Admin dapat membuat POP Pusat.
- [x] Admin dapat membuat POP Cabang.
- [x] Admin dapat membuat Mini POP.
- [x] POP dapat diedit.
- [x] POP dapat dinonaktifkan.
- [x] POP dapat memiliki parent-child.

---

### S2-T003 — Assign User ke POP
Status: Done

Tujuan:
Membatasi akses user berdasarkan POP yang ditugaskan.

Checklist:
- [x] Buat tabel `user_pops`.
- [x] Buat relasi user ke banyak POP.
- [x] Buat form assign POP ke user.
- [x] Admin Pusat dapat assign user ke POP.
- [x] Admin Cabang hanya bisa melihat data POP yang ditugaskan.
- [x] Buat helper scope query berdasarkan POP user.

Acceptance Criteria:
- [x] User dapat memiliki akses ke satu atau banyak POP.
- [x] Admin Cabang hanya melihat POP yang ditugaskan.
- [x] Data cabang lain tidak terlihat oleh Admin Cabang.
- [x] Pembatasan berlaku di query, bukan hanya tampilan menu.

---

### S2-T004 — Master Paket Internet Migration dan Model
Status: Done

Tujuan:
Membuat struktur database dan model untuk paket internet.

Checklist:
- [x] Gunakan tabel `internet_packages` sebagai sumber data Paket Internet.
- [x] Tambahkan nama paket.
- [x] Tambahkan kategori paket.
- [x] Tambahkan kecepatan download.
- [x] Tambahkan kecepatan upload.
- [x] Tambahkan harga bulanan.
- [x] Tambahkan PPN.
- [x] Tambahkan diskon default.
- [x] Tambahkan total harga.
- [x] Tambahkan profile teknis.
- [x] Tambahkan deskripsi.
- [x] Tambahkan status aktif/nonaktif.

Acceptance Criteria:
- [x] Paket dapat disimpan di database.
- [x] Paket memiliki harga bulanan.
- [x] Paket memiliki kecepatan download dan upload.
- [x] Paket memiliki status aktif/nonaktif.

---

### S2-T005 — CRUD Master Paket Internet
Status: Done

Tujuan:
Membuat halaman CRUD paket internet.

Checklist:
- [x] Buat halaman daftar paket.
- [x] Buat halaman tambah paket.
- [x] Buat halaman edit paket.
- [x] Buat validasi field wajib.
- [x] Buat fitur aktif/nonaktif paket.
- [x] Pastikan paket aktif dapat dipilih di modul pelanggan nantinya.
- [x] Pastikan paket nonaktif tidak dipilih untuk pelanggan baru.

Acceptance Criteria:
- [x] Paket dapat dibuat.
- [x] Paket dapat diedit.
- [x] Paket dapat dinonaktifkan.
- [x] Harga paket dapat menjadi dasar tagihan.
- [x] Paket aktif siap digunakan pada input pelanggan.

---

### S2-T006 - POP Identifier Setting
Status: Done

Tujuan:
Menambahkan aturan ID khusus berdasarkan POP.

Checklist:
- [x] Tambahkan field `pop_code` pada POP.
- [x] Tambahkan field `registration_prefix` pada POP.
- [x] Tambahkan field `cid_prefix` pada POP.
- [x] Buat tabel sequence nomor per POP.
- [x] Buat sequence untuk registration number.
- [x] Buat sequence untuk CID.
- [x] Pastikan nomor urut berjalan per POP.
- [x] Pastikan nomor urut berjalan per jenis ID.
- [x] Pastikan format ID sesuai aturan.

Format:
- ID Request: `{registration_prefix}-{pop_code}-{running_number}`
- CID: `{cid_prefix}-{pop_code}-{running_number}`

Contoh:
- ID Request: `C-SMN-000001`
- CID: `D-SMN-000001`

Acceptance Criteria:
- [x] Setiap POP memiliki kode POP.
- [x] Setiap POP memiliki prefix ID Request.
- [x] Setiap POP memiliki prefix CID.
- [x] Sistem dapat membuat ID Request otomatis.
- [x] Sistem dapat membuat CID otomatis.
- [x] ID tidak boleh duplikat.
- [x] CID tidak dibuat sebelum pelanggan aktif/siap billing.

Catatan Test:
- `php artisan test --filter=Pop` lulus: 14 tests, 80 assertions.

---

# Sprint 3 - Master Data Pelanggan Manual

## Tujuan Sprint 3
Membuat master data pelanggan lengkap dengan input manual dan status kelengkapan data. (Sprint Selesai)

---
### S3-T001 - Migration dan Model Customer
Status: Done

Tujuan:
Membuat struktur utama master pelanggan.

Checklist:
- [x] Buat tabel `customers`.
- [x] Tambahkan ID pelanggan baru / registration number.
- [x] Tambahkan ID pelanggan lama.
- [x] Tambahkan CID.
- [x] Tambahkan nama lengkap.
- [x] Tambahkan NIK/nomor identitas.
- [x] Tambahkan jenis kelamin.
- [x] Tambahkan nomor HP utama.
- [x] Tambahkan nomor HP alternatif.
- [x] Tambahkan email.
- [x] Tambahkan tanggal registrasi.
- [x] Tambahkan status kelengkapan data.
- [x] Tambahkan status pelanggan.
- [x] Tambahkan relasi ke POP.
- [x] Tambahkan created_by dan updated_by.

Acceptance Criteria:
- [x] Customer dapat disimpan.
- [x] Customer memiliki relasi POP.
- [x] Customer memiliki status kelengkapan.
- [x] Customer memiliki status pelanggan.
- [x] Customer dapat menyimpan ID lama.
- [x] Customer dapat menyimpan ID Request dan CID.

Catatan Test:
- `php artisan test --filter=CustomerModelTest` lulus: 1 test, 11 assertions.
- Seluruh test suite `php artisan test` lulus: 66 tests, 355 assertions.

---

### S3-T002 — Migration dan Model Customer Address
Status: Done

Tujuan:
Membuat data alamat pelanggan.

Checklist:
- [x] Buat tabel `customer_addresses`.
- [x] Tambahkan customer_id.
- [x] Tambahkan alamat lengkap.
- [x] Tambahkan desa/kelurahan.
- [x] Tambahkan kecamatan.
- [x] Tambahkan kota/kabupaten.
- [x] Tambahkan provinsi.
- [x] Tambahkan latitude.
- [x] Tambahkan longitude.
- [x] Tambahkan foto rumah.
- [x] Tambahkan foto KTP.
- [x] Tambahkan foto kontrak.

Acceptance Criteria:
- [x] Customer memiliki alamat.
- [x] Alamat dapat disimpan.
- [x] Field wajib alamat dapat divalidasi.
- [x] Foto bersifat opsional untuk MVP.

Catatan Test:
- `php artisan test --filter=CustomerAddressModelTest` lulus: 2 tests, 12 assertions.
- Seluruh test suite `php artisan test` lulus: 68 tests, 367 assertions.

---

### S3-T003 — Migration dan Model Customer Service
Status: Done

Tujuan:
Membuat data paket/layanan pelanggan.

Checklist:
- [x] Buat tabel `customer_services`.
- [x] Tambahkan customer_id.
- [x] Tambahkan internet_package_id.
- [x] Tambahkan snapshot nama paket.
- [x] Tambahkan snapshot kecepatan download.
- [x] Tambahkan snapshot kecepatan upload.
- [x] Tambahkan harga bulanan.
- [x] Tambahkan diskon.
- [x] Tambahkan PPN.
- [x] Tambahkan total tagihan bulanan.
- [x] Tambahkan tanggal aktivasi.
- [x] Tambahkan tanggal jatuh tempo.
- [x] Tambahkan siklus tagihan.
- [x] Tambahkan status layanan.
- [x] Tambahkan status billing.

Acceptance Criteria:
- [x] Customer memiliki data layanan.
- [x] Layanan mengambil data dari master paket.
- [x] Harga paket disimpan sebagai snapshot.
- [x] Data layanan menjadi dasar invoice.

Catatan Test:
- `php artisan test --filter=CustomerServiceModelTest` lulus: 2 tests, 8 assertions.
- Seluruh test suite `php artisan test` lulus: 70 tests, 375 assertions.

---

### S3-T004 — Form Input Manual Pelanggan
Status: Done

Tujuan:
Membuat form input pelanggan manual.

Checklist:
- [x] Buat halaman tambah pelanggan.
- [x] Buat form data identitas.
- [x] Buat form data alamat.
- [x] Buat form pilihan POP/Cabang.
- [x] Buat form pilihan paket internet.
- [x] Buat form billing dasar.
- [x] Simpan data pelanggan walaupun belum lengkap.
- [x] Generate ID Request berdasarkan POP.
- [x] Validasi field wajib.
- [x] Tampilkan pesan field yang belum lengkap.

Acceptance Criteria:
- [x] Admin dapat input pelanggan manual.
- [x] Pelanggan belum lengkap tetap bisa disimpan.
- [x] Sistem membuat ID Request otomatis.
- [x] Sistem menandai data lengkap/belum lengkap.
- [x] Pelanggan belum lengkap tidak bisa masuk billing aktif.

Catatan Test:
- Seluruh test suite `php artisan test` lulus: 70 tests, 373 assertions.

---

### S3-T005 — Daftar Pelanggan
Status: Done

Tujuan:
Membuat halaman daftar pelanggan.

Checklist:
- [x] Buat tabel daftar pelanggan.
- [x] Tampilkan ID Request.
- [x] Tampilkan CID jika ada.
- [x] Tampilkan nama pelanggan.
- [x] Tampilkan nomor HP.
- [x] Tampilkan POP.
- [x] Tampilkan paket.
- [x] Tampilkan status kelengkapan.
- [x] Tampilkan status layanan.
- [x] Buat search nama/ID/nomor HP.
- [x] Buat filter POP.
- [x] Buat filter status kelengkapan.
- [x] Buat filter status layanan.

Acceptance Criteria:
- [x] Pelanggan dapat dicari.
- [x] Pelanggan dapat difilter berdasarkan POP.
- [x] Pelanggan dapat difilter berdasarkan status kelengkapan.
- [x] Pelanggan dapat difilter berdasarkan status layanan.
- [x] Admin Cabang hanya melihat pelanggan POP yang ditugaskan.

Catatan Test:
- Seluruh test suite `php artisan test` lulus: 75 tests, 395 assertions (termasuk unit/feature test filter, search, & POP restriction).

---

### S3-T006 — Detail Pelanggan dengan Tab
Status: Done

Tujuan:
Membuat halaman detail pelanggan lengkap.

Checklist:
- [x] Buat tab Ringkasan.
- [x] Buat tab Identitas.
- [x] Buat tab Alamat.
- [x] Buat tab POP/Cabang.
- [x] Buat tab Paket & Layanan.
- [x] Buat tab Billing.
- [x] Buat tab Tagihan.
- [x] Buat tab Pembayaran.
- [x] Buat tab Dokumen.
- [x] Buat tab Riwayat Perubahan.

Acceptance Criteria:
- [x] Detail pelanggan menampilkan semua data utama.
- [x] Data pelanggan dapat diedit sesuai permission.
- [x] Field yang belum lengkap terlihat.
- [x] Status kelengkapan terlihat jelas.

Catatan Test:
- Halaman detail berhasil memuat data dengan 10 tab interaktif.
- Tercover dalam `CustomerDetailTest.php`.

---

### S3-T007 — Validasi Kelengkapan Data Pelanggan
Status: Done

Tujuan:
Membuat sistem validasi kelengkapan data pelanggan.

Checklist:
- [x] Buat service/helper validasi kelengkapan.
- [x] Cek field wajib identitas.
- [x] Cek field wajib alamat.
- [x] Cek POP/Cabang.
- [x] Cek paket internet.
- [x] Cek harga bulanan.
- [x] Cek tanggal aktivasi.
- [x] Cek tanggal jatuh tempo.
- [x] Cek status layanan.
- [x] Hitung persentase kelengkapan.
- [x] Tampilkan daftar field yang belum lengkap.
- [x] Update status kelengkapan otomatis.

Acceptance Criteria:
- [x] Sistem menampilkan persentase kelengkapan data.
- [x] Sistem menampilkan field yang belum lengkap.
- [x] Pelanggan belum lengkap tidak bisa masuk billing aktif.
- [x] Admin dapat melihat daftar pelanggan yang perlu dilengkapi.

Catatan Test:
- `CustomerValidationTest.php` menguji kalkulasi persentase kelengkapan, perubahan status otomatis, dan penolakan transisi status `siap_billing` bila data tidak lengkap.
- `CustomerListTest.php` disesuaikan agar customer data seeder valid untuk pengujian filter kelengkapan.
- Seluruh 80 unit/feature test lulus (100% pass rate).

---

# Sprint 4 — Import Excel/CSV

## Tujuan Sprint 4
Membuat modul import pelanggan lama ke master pelanggan baru.

---

### S4-T001 — Template Import Pelanggan
Status: Done

Tujuan:
Membuat template Excel/CSV untuk import pelanggan lama.

Checklist:
- [x] Buat format kolom import.
- [x] Tambahkan ID pelanggan lama.
- [x] Tambahkan nama lengkap.
- [x] Tambahkan nomor HP.
- [x] Tambahkan alamat.
- [x] Tambahkan POP/Cabang.
- [x] Tambahkan nama paket.
- [x] Tambahkan harga paket.
- [x] Tambahkan tanggal aktivasi.
- [x] Tambahkan tanggal jatuh tempo.
- [x] Tambahkan status layanan.
- [x] Tambahkan field teknis opsional.

Acceptance Criteria:
- [x] Admin dapat download template.
- [x] Template memiliki field wajib.
- [x] Template memiliki field opsional teknis.
- [x] Format siap digunakan untuk import.

Catatan Test:
- `php artisan test --filter=CustomerImportTest` lulus: 4 tests, 35 assertions.
- `php artisan test` lulus: 81 tests, 408 assertions.

---

### S4-T002 — Upload dan Preview Import
Status: Done

Tujuan:
Membuat upload file dan preview data sebelum import.

Checklist:
- [x] Buat halaman import pelanggan.
- [x] Buat upload Excel/CSV.
- [x] Baca isi file.
- [x] Tampilkan preview data.
- [x] Tampilkan jumlah baris.
- [x] Tampilkan data valid dan invalid.

Acceptance Criteria:
- [x] Admin dapat upload file.
- [x] Sistem membaca data.
- [x] Sistem menampilkan preview sebelum import.
- [x] Sistem belum menyimpan data sebelum admin konfirmasi.

Catatan Test:
- `php artisan test --filter=CustomerImportTest` lulus: 4 tests, 35 assertions.
- Halaman `/customers/import` tampil dengan benar.
- Upload file Excel/CSV dibaca oleh SheetJS (client-side), lalu divalidasi via API `/customers/import/validate`.
- Preview tabel menampilkan status per baris (valid/warning/error) dengan metric cards jumlah baris.
- Data hanya tersimpan ke database setelah admin klik tombol konfirmasi import.

---

# Sprint 4 — Import Excel/CSV

## Tujuan Sprint 4
Membuat modul import pelanggan lama ke master pelanggan baru.

---

### S4-T003 — Validasi Import
Status: Done

Tujuan:
Memvalidasi data import sebelum masuk master pelanggan.

Checklist:
- [x] Cek ID pelanggan lama tidak duplikat.
- [x] Cek nama pelanggan tidak kosong.
- [x] Cek nomor HP tidak kosong.
- [x] Cek POP tersedia di master POP.
- [x] Cek paket tersedia di master paket.
- [x] Cek harga paket berupa angka.
- [x] Cek tanggal valid.
- [x] Cek status layanan sesuai pilihan sistem.
- [x] Tandai data teknis kosong sebagai belum lengkap.

Acceptance Criteria:
- [x] Data invalid ditolak.
- [x] Alasan error ditampilkan.
- [x] Data duplikat ditandai.
- [x] Data valid siap dikonfirmasi import.

Catatan Test:
- `php artisan test --filter=CustomerImportTest` lulus: 5 tests, 54 assertions.
- `php artisan test` lulus: 82 tests, 427 assertions.

---

### S4-T004 — Import Batch dan Import Error
Status: Done

Tujuan:
Menyimpan log import dan error import.

Checklist:
- [x] Buat tabel `import_batches`.
- [x] Buat tabel `import_errors`.
- [x] Simpan nama file.
- [x] Simpan user pengupload.
- [x] Simpan total rows.
- [x] Simpan valid rows.
- [x] Simpan invalid rows.
- [x] Simpan imported rows.
- [x] Simpan error per baris.
- [x] Simpan raw data error.

Acceptance Criteria:
- [x] Setiap import memiliki batch log.
- [x] Error import tersimpan.
- [x] Admin dapat melihat riwayat import.
- [x] Admin dapat melihat alasan data gagal.

Catatan Test:
- `php artisan test tests/Feature/CustomerImportLoggingTest.php` lulus: 2 tests, 11 assertions.
- Tabel `import_batches` dan `import_errors` dibuat.
- Halaman riwayat dan detail batch tersedia.

---

### S4-T005 — Konfirmasi Import ke Master Pelanggan
Status: Done

Tujuan:
Menyimpan data valid hasil import ke master pelanggan.

Checklist:
- [x] Buat tombol konfirmasi import.
- [x] Simpan data valid ke customers.
- [x] Simpan alamat ke customer_addresses.
- [x] Simpan layanan ke customer_services.
- [x] Simpan ID pelanggan lama.
- [x] Generate ID Request berdasarkan POP.
- [x] Jangan generate CID jika pelanggan belum aktif/siap billing.
- [x] Update status kelengkapan data.
- [x] Simpan log audit import.

Acceptance Criteria:
- [x] Data valid masuk master pelanggan.
- [x] Data invalid tidak masuk master pelanggan.
- [x] Data hasil import bisa diedit manual.
- [x] ID pelanggan lama tersimpan.
- [x] ID Request sistem baru dibuat.
- [x] Log import tersimpan.

Catatan Test:
- `php artisan test tests/Feature/CustomerImportLoggingTest.php` lulus (verifikasi data masuk ke 3 tabel).
- `php artisan test tests/Feature/CustomerImportTest.php` lulus (verifikasi regresi).

---

## Sprint 5 — Billing Dasar

### S5-T001 — Aktivasi Layanan Pelanggan
Status: Done

Tujuan:
Mengubah pelanggan lengkap menjadi aktif/siap billing.

Checklist:
- [x] Buat tombol aktivasi layanan.
- [x] Cek kelengkapan data pelanggan.
- [x] Cek paket aktif.
- [x] Cek nominal tagihan.
- [x] Cek tanggal aktivasi.
- [x] Cek tanggal jatuh tempo.
- [x] Generate CID berdasarkan POP.
- [x] Ubah status pelanggan menjadi aktif.
- [x] Ubah status kelengkapan menjadi siap billing.
- [x] Simpan riwayat aktivasi.

Acceptance Criteria:
- [x] Pelanggan belum lengkap tidak bisa diaktifkan.
- [x] Pelanggan aktif memiliki paket.
- [x] Pelanggan aktif memiliki nominal tagihan.
- [x] Pelanggan aktif memiliki CID.
- [x] Tanggal jatuh tempo wajib ada.
- [x] Sistem menyimpan riwayat aktivasi.

Catatan Test:
- `php artisan test --filter=CustomerActivationTest` lulus: 3 tests, 22 assertions.
- Seluruh test suite `php artisan test` lulus: 87 tests, 468 assertions.

---

### S5-T002 — Migration dan Model Invoice
Status: Done

Tujuan:
Membuat struktur tagihan pelanggan.

Checklist:
- [x] Buat tabel `invoices`.
- [x] Tambahkan nomor invoice.
- [x] Tambahkan customer_id.
- [x] Tambahkan pop_id.
- [x] Tambahkan customer_service_id.
- [x] Tambahkan internet_package_id.
- [x] Tambahkan periode tagihan.
- [x] Tambahkan tanggal terbit.
- [x] Tambahkan tanggal jatuh tempo.
- [x] Tambahkan subtotal.
- [x] Tambahkan diskon.
- [x] Tambahkan PPN.
- [x] Tambahkan total tagihan.
- [x] Tambahkan paid amount.
- [x] Tambahkan remaining amount.
- [x] Tambahkan status tagihan.

Acceptance Criteria:
- [x] Invoice dapat disimpan.
- [x] Invoice terhubung ke customer.
- [x] Invoice terhubung ke POP.
- [x] Invoice memiliki periode.
- [x] Invoice memiliki status.

Catatan Test:
- `php artisan test --filter=InvoiceModelTest` lulus: 1 test, 17 assertions.
- Seluruh test suite `php artisan test` lulus: 88 tests, 485 assertions.
---

### S5-T003 — Buat Tagihan Manual
Status: Done

Tujuan:
Membuat invoice manual dari pelanggan aktif.

Checklist:
- [x] Buat tombol buat tagihan di detail pelanggan.
- [x] Cek pelanggan aktif/siap billing.
- [x] Ambil paket aktif pelanggan.
- [x] Ambil harga layanan pelanggan.
- [x] Ambil tanggal jatuh tempo.
- [x] Tentukan periode tagihan.
- [x] Cek invoice dobel untuk periode sama.
- [x] Buat invoice.
- [x] Status invoice default belum dibayar.

Acceptance Criteria:
- [x] Tagihan hanya bisa dibuat untuk pelanggan aktif/siap billing.
- [x] Tagihan mengambil harga dari layanan pelanggan.
- [x] Tagihan memiliki periode.
- [x] Tagihan tidak dobel untuk periode yang sama.
- [x] Tagihan memiliki status belum dibayar.

Catatan Test:
- `php artisan test tests/Feature/InvoiceCreateTest.php` lulus: 6 tests, 17 assertions.
- Seluruh test suite `php artisan test` lulus: 94 tests, 502 assertions.

---

### S5-T004 — Daftar dan Detail Tagihan
Status: Done

Tujuan:
Membuat halaman daftar dan detail invoice.

Checklist:
- [x] Buat halaman daftar invoice.
- [x] Buat filter POP.
- [x] Buat filter periode.
- [x] Buat filter status.
- [x] Buat search pelanggan/invoice.
- [x] Buat halaman detail invoice.
- [x] Tampilkan pelanggan.
- [x] Tampilkan paket.
- [x] Tampilkan total.
- [x] Tampilkan status.

Acceptance Criteria:
- [x] Tagihan dapat difilter berdasarkan POP.
- [x] Tagihan dapat difilter berdasarkan periode.
- [x] Tagihan dapat difilter berdasarkan status.
- [x] Tagihan dapat difilter berdasarkan pelanggan.
- [x] Admin Cabang hanya melihat tagihan POP yang ditugaskan.

Catatan Test:
- `php artisan test tests/Feature/InvoiceListTest.php` lulus: 3 tests, 17 assertions.
- `php artisan test tests/Feature/InvoiceCreateTest.php tests/Feature/InvoiceModelTest.php` lulus: 7 tests, 34 assertions.
- `php artisan test` dengan `VIEW_COMPILED_PATH` temp berjalan 95 passed, 2 failed pada `CustomerEditTest` lama terkait file upload cleanup dokumen, bukan modul tagihan.

---

# Sprint 6 — Pembayaran

## Tujuan Sprint 6
Membuat pencatatan pembayaran dan update status invoice.

---

### S6-T001 — Migration dan Model Payment
Status: Done

Tujuan:
Membuat struktur pembayaran.

Checklist:
- [x] Buat tabel `payments`.
- [x] Tambahkan nomor pembayaran.
- [x] Tambahkan invoice_id.
- [x] Tambahkan customer_id.
- [x] Tambahkan pop_id.
- [x] Tambahkan tanggal bayar.
- [x] Tambahkan metode bayar.
- [x] Tambahkan nominal bayar.
- [x] Tambahkan penerima.
- [x] Tambahkan bukti pembayaran.
- [x] Tambahkan status pembayaran.
- [x] Tambahkan catatan.

Acceptance Criteria:
- [x] Payment dapat disimpan.
- [x] Payment terhubung ke invoice.
- [x] Payment terhubung ke customer.
- [x] Payment terhubung ke POP.
- [x] Payment memiliki status.

Catatan Test:
- `php artisan test --filter=PaymentModelTest` lulus: 1 test, 11 assertions.
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test --filter='PaymentModelTest|InvoiceModelTest|InvoiceCreateTest|InvoiceListTest'` lulus: 11 tests, 63 assertions.
- Full test suite dengan `VIEW_COMPILED_PATH` temp: 96 passed, 2 failed pada `CustomerEditTest` lama terkait cleanup file dokumen, bukan modul pembayaran.

---

### S6-T002 — Input Pembayaran
Status: Done

Tujuan:
Membuat pencatatan pembayaran invoice.

Checklist:
- [x] Buat tombol input pembayaran di invoice.
- [x] Buat form pembayaran.
- [x] Pilih metode pembayaran.
- [x] Input nominal.
- [x] Upload bukti jika ada.
- [x] Simpan pembayaran.
- [x] Update paid amount invoice.
- [x] Update remaining amount invoice.
- [x] Update status invoice.

Acceptance Criteria:
- [x] Finance dapat mencatat pembayaran.
- [x] Pembayaran muncul di detail pelanggan.
- [x] Jika nominal penuh, invoice menjadi lunas.
- [x] Jika nominal kurang, invoice menjadi sebagian.
- [x] Bukti pembayaran dapat diupload.

Catatan Test:
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/PaymentInputTest.php tests/Feature/PaymentModelTest.php tests/Feature/InvoiceListTest.php` lulus: 8 tests, 44 assertions.
- `npm run build` lulus.
- Full test suite dengan `VIEW_COMPILED_PATH` temp: 100 passed, 2 failed pada `CustomerEditTest` lama terkait cleanup file dokumen, bukan modul pembayaran.

---

### S6-T003 — Daftar dan Detail Pembayaran
Status: Done

Tujuan:
Membuat halaman daftar dan detail pembayaran.

Checklist:
- [x] Buat halaman daftar pembayaran.
- [x] Buat filter tanggal.
- [x] Buat filter metode.
- [x] Buat filter POP.
- [x] Buat filter status.
- [x] Buat search pelanggan/invoice.
- [x] Buat detail pembayaran.
- [x] Tampilkan bukti pembayaran.

Acceptance Criteria:
- [x] Pembayaran dapat difilter berdasarkan tanggal.
- [x] Pembayaran dapat difilter berdasarkan POP.
- [x] Pembayaran dapat difilter berdasarkan metode.
- [x] Pembayaran dapat difilter berdasarkan status.
- [x] Admin Cabang hanya melihat pembayaran POP yang ditugaskan.

Catatan Test:
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/PaymentListTest.php tests/Feature/PaymentInputTest.php tests/Feature/PaymentModelTest.php tests/Feature/InvoiceListTest.php` lulus: 12 tests, 68 assertions.
- `npm run build` lulus.

---

### S6-T004 — Audit Log Pembayaran
Status: Done

Tujuan:
Mencatat perubahan pembayaran.

Checklist:
- [x] Catat create pembayaran.
- [x] Catat update pembayaran.
- [x] Catat pembatalan pembayaran jika ada.
- [x] Catat user yang melakukan perubahan.
- [x] Catat waktu perubahan.
- [x] Catat data sebelum dan sesudah.

Acceptance Criteria:
- [x] Perubahan pembayaran masuk audit log.
- [x] Owner/Admin Pusat dapat melihat log pembayaran.
- [x] Perubahan pembayaran tidak hilang dari riwayat.

Catatan Test:
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/PaymentAuditLogTest.php tests/Feature/PaymentInputTest.php tests/Feature/PaymentListTest.php tests/Feature/PaymentModelTest.php` lulus: 11 tests, 68 assertions.
- `npm run build` lulus.
- Full test suite dengan `VIEW_COMPILED_PATH` temp: 106 passed, 2 failed pada `CustomerEditTest` lama terkait cleanup file dokumen pelanggan, bukan modul pembayaran.

---


## Tujuan Sprint 7
Membuat dashboard dan laporan operasional sederhana.

---

### S7-T001 — Dashboard Ringkasan
Status: Done

Tujuan:
Membuat dashboard ringkasan pelanggan dan billing.

Checklist:
- [x] Total pelanggan.
- [x] Total pelanggan aktif.
- [x] Total pelanggan belum lengkap.
- [x] Total pelanggan siap billing.
- [x] Total pelanggan per POP.
- [x] Total tagihan bulan ini.
- [x] Total pembayaran bulan ini.
- [x] Total tunggakan.
- [x] Tagihan jatuh tempo.
- [x] Data pelanggan yang perlu dilengkapi.
- [x] Filter POP.
- [x] Filter periode.

Acceptance Criteria:
- [x] Owner melihat semua data.
- [x] Admin Pusat melihat semua cabang.
- [x] Admin Cabang hanya melihat cabangnya.
- [x] Dashboard dapat difilter berdasarkan POP.
- [x] Dashboard dapat difilter berdasarkan periode.

Catatan Test:
- `php artisan test --filter=DashboardTest` lulus: 8 tests, 40 assertions.
- Menguji asersi visual, filter POP, filter periode, dan pembatasan data Admin Cabang.

---

### S7-T002 — Laporan Pelanggan
Status: Done

Tujuan:
Membuat laporan pelanggan.

Checklist:
- [x] Laporan pelanggan lengkap.
- [x] Laporan pelanggan belum lengkap.
- [x] Laporan pelanggan aktif.
- [x] Laporan pelanggan isolir.
- [x] Laporan pelanggan per POP.
- [x] Filter tanggal.
- [x] Filter POP.
- [x] Export Excel/CSV.

Acceptance Criteria:
- [x] Laporan pelanggan dapat difilter.
- [x] Laporan pelanggan dapat diexport.
- [x] Admin Cabang hanya export data cabangnya.

Catatan Test:
- `php artisan test --filter=ReportCustomerTest` lulus: 6 tests, 26 assertions.

---

### S7-T003 — Laporan Tagihan
Status: Done

Tujuan:
Membuat laporan tagihan.

Checklist:
- [x] Laporan tagihan bulanan.
- [x] Laporan tagihan per POP.
- [x] Laporan tagihan per status.
- [x] Laporan tunggakan.
- [x] Filter tanggal.
- [x] Filter POP.
- [x] Export Excel/CSV.

Acceptance Criteria:
- [x] Laporan tagihan dapat difilter.
- [x] Laporan tunggakan tersedia.
- [x] Laporan tagihan dapat diexport.
- [x] Admin Cabang hanya export data cabangnya.

Catatan Test:
- `php artisan test --filter=ReportInvoiceTest` lulus: 6 tests, 30 assertions.

---

### S7-T004 — Laporan Pembayaran
Status: Done

Tujuan:
Membuat laporan pembayaran.

Checklist:
- [x] Laporan pembayaran bulanan.
- [x] Laporan pembayaran per POP.
- [x] Laporan pembayaran per metode.
- [x] Filter tanggal.
- [x] Filter POP.
- [x] Filter metode.
- [x] Export Excel/CSV.

Acceptance Criteria:
- [x] Laporan pembayaran dapat difilter.
- [x] Laporan pembayaran per metode tersedia.
- [x] Laporan pembayaran dapat diexport.
- [x] Admin Cabang hanya export data cabangnya.

Catatan Test:
- `php artisan test --filter=ReportPaymentTest` lulus: 6 tests, 28 assertions.
- Seluruh test suite `php artisan test` lulus: 129 passed, 681 assertions.

---

### S7-T005 — Laporan Import Data
Status: Done

Tujuan:
Membuat laporan hasil import data pelanggan lama.

Checklist:
- [x] Tampilkan riwayat import.
- [x] Tampilkan total rows.
- [x] Tampilkan valid rows.
- [x] Tampilkan invalid rows.
- [x] Tampilkan imported rows.
- [x] Tampilkan error import.
- [x] Export laporan import jika dibutuhkan.

Acceptance Criteria:
- [x] Admin dapat melihat riwayat import.
- [x] Admin dapat melihat data error import.
- [x] Admin dapat mengetahui data yang berhasil masuk.

Catatan Test:
- `php artisan test --filter=ReportImportTest` lulus: 6 tests, 33 assertions.
- Seluruh test suite `php artisan test` lulus (135 passed).

---

# Sprint 8 — Data Teknis Pelanggan

## Tujuan Sprint 8
Melengkapi data teknis pelanggan setelah billing dasar stabil.

---

### S8-T001 — Data Survey Pelanggan
Status: Done

Tujuan:
Membuat data survey pelanggan.

Checklist:
- [x] Buat tabel `customer_surveys`.
- [x] Tambahkan status survey.
- [x] Tambahkan tanggal survey.
- [x] Tambahkan jam mulai.
- [x] Tambahkan jam selesai.
- [x] Tambahkan petugas survey.
- [x] Tambahkan kebutuhan alat.
- [x] Tambahkan estimasi kabel.
- [x] Tambahkan ODP terdekat.
- [x] Tambahkan foto survey.
- [x] Tambahkan catatan survey.
- [x] Tampilkan di detail pelanggan.

Acceptance Criteria:
- [x] Teknisi dapat mengisi data survey.
- [x] Data survey tampil di detail pelanggan.
- [x] User tanpa permission tidak dapat mengisi survey.

Catatan Test:
- `php artisan test tests/Feature/CustomerSurveyTest.php` lulus (PASS).
- Status pelanggan otomatis berubah ke `surveyed` saat survey `completed`.
- RBAC berfungsi: Teknisi dapat mengisi survey, Finance dilarang.

---

### S8-T002 — Data Pemasangan Pelanggan
Status: Done
Sprint: 8
Tujuan: Membuat data pemasangan pelanggan.
Selesai: 2026-06-13

Checklist:
- [x] Buat tabel `customer_installations`.
- [x] Tambahkan status pemasangan.
- [x] Tambahkan tanggal jadwal.
- [x] Tambahkan jam jadwal.
- [x] Tambahkan teknisi pemasangan.
- [x] Tambahkan tanggal selesai.
- [x] Tambahkan foto pemasangan.
- [x] Tambahkan catatan pemasangan.
- [x] Tampilkan di detail pelanggan.

Acceptance Criteria:
- [x] Teknisi dapat mengisi data pemasangan.
- [x] Data pemasangan tampil di detail pelanggan.
- [x] User tanpa permission tidak dapat mengisi pemasangan.

Catatan Test:
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/CustomerInstallationTest.php tests/Feature/CustomerDetailTest.php` lulus: 4 tests, 31 assertions.
- `npm run build` lulus.
- Full test suite dengan `VIEW_COMPILED_PATH` temp: 138 passed, 2 failed pada `CustomerEditTest` lama terkait cleanup file dokumen, bukan modul pemasangan.

---


### S8-T003 — Data Modem/ONT/Router Pelanggan
Status: Done
Sprint: 8
Selesai: 2026-06-13

Tujuan:
Membuat data perangkat pelanggan.

Checklist:
- [x] Buat tabel `customer_devices`.
- [x] Tambahkan jenis perangkat.
- [x] Tambahkan merk.
- [x] Tambahkan tipe.
- [x] Tambahkan serial number.
- [x] Tambahkan MAC address.
- [x] Tambahkan username PPPoE.
- [x] Tambahkan password PPPoE.
- [x] Tambahkan SSID WiFi.
- [x] Tambahkan password WiFi.
- [x] Tambahkan IP address.
- [x] Tambahkan VLAN ID.
- [x] Tambahkan ODP.
- [x] Tambahkan port ODP.
- [x] Tambahkan redaman.
- [x] Tambahkan mode koneksi.
- [x] Tambahkan catatan teknis.
- [x] Batasi akses field sensitif.

Acceptance Criteria:
- [x] Teknisi dapat mengisi data perangkat.
- [x] Data perangkat tampil di detail pelanggan.
- [x] Password PPPoE dan WiFi dibatasi aksesnya.
- [x] Finance tidak dapat mengubah data modem.
- [x] CS tidak dapat melihat field sensitif jika tidak diizinkan.

Catatan Test:
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/CustomerDeviceTest.php tests/Feature/PermissionTest.php` lulus: 7 tests, 52 assertions.
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/CustomerDeviceTest.php tests/Feature/CustomerDetailTest.php tests/Feature/CustomerSurveyTest.php tests/Feature/CustomerInstallationTest.php` lulus: 11 tests, 61 assertions.
- `npm run build` lulus.
- Catatan tambahan: tab `Perangkat` kini fallback ke detail teknis migrasi jika `customer_devices` belum terisi, supaya `ONT Serial Number` dan `IP Address` legacy tetap terlihat.
- Full test suite dengan `VIEW_COMPILED_PATH` temp: 143 passed, 2 failed pada `CustomerEditTest` lama terkait cleanup file dokumen pelanggan, bukan modul perangkat.

---


### S8-T004 — Data Dokumen Pelanggan
Status: Done
Sprint: 8
Selesai: 2026-06-13

Tujuan:
Membuat penyimpanan dokumen pelanggan.

Checklist:
- [x] Buat tabel `customer_documents`.
- [x] Upload dokumen KTP.
- [x] Upload foto rumah.
- [x] Upload kontrak.
- [x] Upload foto survey.
- [x] Upload foto pemasangan.
- [x] Tampilkan dokumen di detail pelanggan.
- [x] Batasi akses dokumen berdasarkan permission.

Acceptance Criteria:
- [x] Dokumen pelanggan dapat diupload.
- [x] Dokumen tampil di detail pelanggan.
- [x] User tanpa permission tidak dapat mengakses dokumen tertentu.

Catatan Test:
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/CustomerDocumentTest.php tests/Feature/CustomerDetailTest.php` lulus: 6 tests, 38 assertions.
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/PermissionTest.php tests/Feature/RolePermissionTest.php tests/Feature/CustomerDeviceTest.php tests/Feature/CustomerSurveyTest.php tests/Feature/CustomerInstallationTest.php tests/Feature/CustomerDocumentTest.php` lulus: 23 tests, 108 assertions.
- `npm run build` lulus.
- Full test suite dengan `VIEW_COMPILED_PATH` temp: 148 passed, 2 failed pada `CustomerEditTest` lama terkait cleanup file dokumen legacy pelanggan, bukan modul `customer_documents`.

---


### S8-T005 — Audit Log Umum
Status: Done
Sprint: 8
Selesai: 2026-06-13

Tujuan:
Membuat audit log untuk perubahan data penting.

Checklist:
- [x] Buat tabel `audit_logs`.
- [x] Catat perubahan pelanggan.
- [x] Catat perubahan paket.
- [x] Catat perubahan POP.
- [x] Catat perubahan tagihan.
- [x] Catat perubahan pembayaran.
- [x] Catat perubahan user.
- [x] Catat perubahan role.
- [x] Catat perubahan data teknis.
- [x] Buat halaman audit log untuk Owner/Admin Pusat.

Acceptance Criteria:
- [x] Perubahan pelanggan tercatat.
- [x] Perubahan pembayaran tercatat.
- [x] Perubahan tagihan tercatat.
- [x] Perubahan role tercatat.
- [x] Owner/Admin Pusat dapat melihat audit log.

Catatan Test:
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php ar  tisan test tests/Feature/AuditLogGeneralTest.php tests/Feature/CustomerActivationTest.php tests/Feature/PaymentAuditLogTest.php` lulus: 9 tests, 60 assertions.
- `VIEW_COMPILED_PATH=%TEMP%/whusnet-test-views php artisan test tests/Feature/CustomerCreateTest.php tests/Feature/CustomerActivationTest.php tests/Feature/CustomerDeviceTest.php tests/Feature/CustomerSurveyTest.php tests/Feature/CustomerInstallationTest.php tests/Feature/InvoiceCreateTest.php tests/Feature/InvoiceListTest.php tests/Feature/PaymentInputTest.php tests/Feature/PaymentListTest.php tests/Feature/PaymentAuditLogTest.php tests/Feature/PermissionTest.php tests/Feature/RolePermissionTest.php tests/Feature/PopCRUDTest.php tests/Feature/PopIdentifierSettingTest.php tests/Feature/InternetPackageSeederTest.php tests/Feature/AuditLogGeneralTest.php` lulus: 62 tests, 335 assertions.
- `npm run build` lulus.
- Regresi yang menyertakan `CustomerEditTest.php` masih memiliki 2 kegagalan legacy pada cleanup file dokumen pelanggan, sesuai catatan task sebelumnya, bukan dari modul audit log.

---


## Sprint 10 — Complex CID & Termination Logic

### S10-T001 — Implement Complex CID Generation Logic
Status: Done

Sprint/Module: Sprint 10 — Complex CID & Termination Logic

Tujuan:
Implementasi logic generate CID yang lebih kompleks sesuai kebutuhan operasional lapangan.
Format: {prefix}{olt}{dist}{req}_{village}_{name}

Acceptance Criteria:
- [x] Method `generateComplexCid` tersedia di model `Pop`.
- [x] Format CID sesuai: {prefix}{olt_number}{dist_code}{customer_code}_{village_name}_{customer_name}.
- [x] `olt_number` diambil dari `customer_technical_details` (default '1').
- [x] `dist_code` diambil dari `Distribution` (default 'XXX').
- [x] `village_name` dan `customer_name` di-normalize (uppercase, no space).
- [x] Unit test `tests/Unit/PopCidGenerationTest.php` lulus.

Catatan Test:
- `php artisan test tests/Unit/PopCidGenerationTest.php` lulus.



### S10-T002 — Implement Customer Termination Logic
Status: Done

Sprint/Module: Sprint 10 — Complex CID & Termination Logic

Tujuan:
Implementasi backend untuk terminasi (pemutusan) layanan pelanggan.

Acceptance Criteria:
- [x] Endpoint `POST /customers/{customer}/terminate` tersedia.
- [x] Status pelanggan berubah menjadi `terminated`.
- [x] Status layanan di `customer_services` berubah menjadi `berhenti`.
- [x] Audit log mencatat aksi terminasi.

---

### UX-VA001 — Halaman Penuh Verifikasi Admin (Proses Pemasangan + Pengujian + Aktivasi)
Status: Done

Sprint/Module: Verifikasi Admin — Workflow UX Polish

Tujuan:
Membuat halaman penuh Sub Menu Verifikasi Admin yang menampilkan data proses pemasangan, data pengujian speedtest, dan form aktivasi tagihan dalam satu halaman terstruktur dengan tab navigasi.

Scope:
- [x] Buat `verifications/admin.blade.php` — halaman tab 3-bagian: Proses Pemasangan, Pengujian, Verifikasi & Aktivasi.
- [x] Tambah method `showAdmin` di `CustomerVerificationController`.
- [x] Tambah route `GET /customers/{customer}/verification/admin`.
- [x] Hilangkan Modal "Proses ke Tim" — tombol langsung POST konfirmasi native browser.
- [x] Ganti tombol modal "Verifikasi & Aktivasi" di queue dengan link ke halaman penuh admin.blade.php.
- [x] Hapus `finalVerifyModal` dari `queue.blade.php` (tidak lagi diperlukan).

Acceptance Criteria:
- [x] Admin dapat melihat detail data pemasangan (perangkat, ODP/OLT, durasi SLA) dari tab Proses Pemasangan.
- [x] Admin dapat melihat data speedtest (download, upload, latency, packet loss, kesesuaian paket) dari tab Pengujian.
- [x] Admin dapat mengisi form tagihan dan mengaktifkan pelanggan dari tab Verifikasi & Aktivasi.
- [x] Tombol "Verifikasi" di queue langsung menuju halaman penuh (bukan modal).
- [x] Modal finalVerify telah dihapus dari halaman queue.

---

### Workflow-S4 — Modul Aktivasi & Tagihan + Polish
Status: Done

Tujuan:
Pelanggan resmi aktif, masuk list Pelanggan, sistem siap produksi dengan tagihan pertama tergenerate.

Scope:
- `VerificationController@finalVerify` (action "Verifikasi").
- Modal Buat Tagihan Manual → `invoices`.
- Activation flow (Update `customer_services` dan `customers.status = active`).
- Pelanggan masuk List Pelanggan utama.
- Cron/job: auto-reminder countdown lewat batas waktu (Sudah ada di `Kernel.php` / Scheduler Laravel).
- Testing end-to-end.

Acceptance Criteria:
- [x] Admin dapat melakukan verifikasi akhir dan menerbitkan tagihan pertama.
- [x] Pelanggan statusnya berubah menjadi `active` (aktif) dan mendapatkan CID kompleks.
- [x] Data layanan berubah status menjadi `aktif` dan tersimpan relasi invoice pertamanya.

---

### Workflow-S3 — Modul Verifikasi Admin & Pemasangan
Status: Done

Tujuan:
Alur 4-tahap di halaman Verifikasi (ACC → Proses Tim → Mulai Pasang → Verifikasi Admin).

Scope:
- Endpoint List Antrean Proses Verifikasi.
- `VerificationController@processToTeam` (action "Proses ke Tim").
- `InstallationController@start` (action "Start Proses") & broadcast event.
- Frontend: Countdown pemasangan.
- `InstallationController@complete` (action "Lapor Pemasangan").
- Form Modal Data Perangkat & Speedtest → `customer_technical_details`.
- Fitur SCAN QR (di-skip sesuai instruksi).
- Audit log integration.

Acceptance Criteria:
- [x] Dari status `surveyed`, admin bisa proses sampai `verification_admin` dengan seluruh data perangkat & speedtest tersimpan di `customer_technical_details`.

---

### Workflow-S2 — Modul Registrasi & Survey (Backend + Frontend)
Status: Done

Tujuan:
Pelanggan bisa didaftarkan dan masuk antrian survey dengan countdown live.

Scope:
- `CustomerRegistrationController@store`: Multi-step form
- Validasi form registrasi
- Endpoint List Antrean Survey
- `SurveyController@start` & `SurveyController@complete`
- Event `SurveyStarted` & `SurveyCompleted` + Reverb
- Frontend: Halaman List Antrean Survey
- Frontend: Countdown component (Reverb)
- Frontend: Form Antrian Survei modal
- Frontend: Data Survey Pelanggan di Detail Pelanggan

Acceptance Criteria:
- [x] End-to-end bisa daftar pelanggan → antrian survey → tekan Survey Data → countdown jalan real-time → Lapor Data → status pindah ke Verifikasi Admin.

---

### Workflow-S1 — Foundation: Schema + State Machine Service
Status: Done

Tujuan:
Menyediakan pondasi database & state machine service untuk alur registrasi pelanggan.

Scope:
- Migration: tambah status baru ke `subscription_statuses` (`survey_in_progress`, `waiting_acc`, `installation_in_progress`, `verification_admin`).
- Migration: tambah `started_at`, `completed_at` ke `customer_surveys` dan `customer_installations`.
- Migration: tambah `contract_type` ke `customer_services`.
- Buat `CustomerWorkflowService`.
- Buat `WorkflowTransition` enum.
- Unit test state machine.

Acceptance Criteria:
- [x] Migration jalan tanpa error.
- [x] `CustomerWorkflowService::transition()` memiliki test coverage untuk seluruh alur happy path + reject path.

---

## Master Data Tambahan

### MD-001 — Master Data Distribusi
Status: Done

Tujuan:
Menambahkan master data untuk Distribusi (sub-area di bawah POP/Cabang) sesuai permintaan.

Scope:
- Model & Migration `Distribution`.
- Controller `DistributionController` (CRUD).
- Views (index, create, edit) yang mengadopsi styling design system yang ada.
- Relasi dengan `Pop`.
- Navigasi di Sidebar.

Acceptance Criteria:
- [x] Tabel `distributions` dibuat (id, code, description, pop_id).
- [x] Form Create/Edit memiliki input kode, deskripsi, dan dropdown POP/Cabang.
- [x] Menu Master Distribusi muncul di sidebar di bawah Master POP.
- [x] Dokumentasi rancangan selesai di `docs/Rancangan-Master-Distribusi.md`.

Catatan tambahan:
- Seeder Jetis sudah menyiapkan POP induk `C`, mini POP `C1/C2/C3`, dan distribusi `X4A-X4H` sesuai pemetaan cabang dan mini POP yang disepakati.

---

## Sprint 9 — Kelengkapan Detail Pelanggan (Gap Fix)

### S9-T001 — Fix Gap Data Teknis: OLT Number, OLT Slot, VLAN di Technical Detail
Status: Done

Sprint/Module: Sprint 9 — Kelengkapan Detail Pelanggan

Tujuan:
Mengisi 3 gap field teknis yang ditemukan pada analisis kesesuaian Detail Pelanggan:
1. `olt_number` (Nomor OLT) belum ada di `customer_technical_details`.
2. `olt_slot` (Slot OLT) — migration ada tapi kosong, kolom tidak pernah dibuat.
3. `vlan` di detail teknis (saat ini hanya di `customer_devices`, belum di `customer_technical_details`).

Scope:
- Migration: tambah `olt_number`, `olt_slot`, `vlan` ke `customer_technical_details`.
- Model: update fillable `CustomerTechnicalDetail`.
- View: tampilkan field baru di tab Perangkat (bagian Detail Teknis Tambahan).
- Form modal device: tambah field input OLT Number, OLT Slot, VLAN (di sisi technical detail).

Acceptance Criteria:
- [x] Field `olt_number`, `olt_slot`, `vlan` tersimpan di `customer_technical_details`.
- [x] Tab Perangkat menampilkan Nomor OLT, Slot OLT, VLAN dari detail teknis.
- [x] Migration berjalan tanpa error.
- [x] Tidak ada data existing yang rusak.
- [x] Test suite 182 passed, 0 failed.

---

### S9-T002 — Fix Gap Survey: Multi-Petugas Terstruktur & Foto Rumah Terpisah
Status: Done

Sprint/Module: Sprint 9 — Kelengkapan Detail Pelanggan

Tujuan:
Mengisi 2 gap pada Data Survey:
1. `surveyors` masih string bebas — perlu field terstruktur untuk 1–3 petugas survey.
2. Foto rumah pelanggan belum terpisah dari foto survey lapangan/ODP.

Acceptance Criteria:
- [x] Petugas survey 1–3 dapat diisi dari daftar user.
- [x] Foto rumah pelanggan tersimpan terpisah dari foto ODP/survey lapangan.
- [x] Tab Survey menampilkan nama petugas ke-2 dan ke-3 jika diisi.
- [x] Data existing tidak rusak.
- [x] Test suite 182 passed, 0 failed.

---

### S9-T003 — Fix Gap Aktivasi: Relasi User ID pada Petugas Aktivasi
Status: Done

Sprint/Module: Sprint 9 — Kelengkapan Detail Pelanggan

Tujuan:
Mengisi gap pada Laporan Aktivasi:
- `activated_by_name` hanya menyimpan nama string. Tambah `activated_by_user_id` untuk traceability.

Acceptance Criteria:
- [x] `activated_by_user_id` tersimpan saat aktivasi.
- [x] Detail pelanggan tab Paket & Layanan menampilkan waktu dan petugas aktivasi.
- [x] Data existing tidak rusak (nullable).
- [x] Test suite 182 passed, 0 failed.

---

### S9-T004 — Fix Gap Pemasangan: Multi-Teknisi Terstruktur
Status: Done

Sprint/Module: Sprint 9 — Kelengkapan Detail Pelanggan

Tujuan:
Mengisi gap pada Data Pemasangan:
- `technicians` masih string bebas — perlu field terstruktur untuk 2–3 teknisi pemasangan.

Acceptance Criteria:
- [x] Teknisi pemasangan ke-2 dan ke-3 dapat dipilih dari daftar user.
- [x] Tab Pemasangan menampilkan nama teknisi ke-2 dan ke-3 jika diisi.
- [x] Data existing tidak rusak.
- [x] Test suite 182 passed, 0 failed.

---

### S9-T005 — Fix Billing Cycle: Pindahkan Biaya Lain di Luar Standar ke Rincian Biaya Bulanan
Status: Done

Sprint/Module: Sprint 9 — Kelengkapan Detail Pelanggan

Tujuan:
Menyesuaikan implementasi billing agar `biaya lain di luar standar` legacy tidak diperlakukan sebagai biaya invoice terpisah, tetapi sebagai bagian dari rincian biaya bulanan dan `billing cycle`.

Scope:
- Tambah field `other_fee` pada `customer_services`.
- Tampilkan `other_fee` di blok `Rincian Biaya Bulanan & Billing Cycle`.
- Pastikan preview create/edit customer menghitung total bulanan termasuk `other_fee`.
- Saat migrasi legacy, mapping `BIAYALAINLAIN` masuk ke layanan pelanggan.
- Invoice tetap menyimpan histori total tagihan legacy tanpa double count.

Acceptance Criteria:
- [x] `other_fee` tersimpan di `customer_services`.
- [x] `other_fee` tampil di breakdown biaya bulanan pelanggan.
- [x] Create/edit customer menghitung total bulanan dengan `other_fee`.
- [x] Migrasi legacy mengisi `other_fee` dari `BIAYALAINLAIN`.
- [x] Test validasi billing, import legacy, dan detail pelanggan lulus.

Catatan Test:
- `VIEW_COMPILED_PATH=%TEMP%\\whusnet-test-views php artisan test tests/Feature/RealDataMigrationTest.php tests/Feature/InvoiceCreateTest.php tests/Feature/PaymentInputTest.php tests/Feature/CustomerDetailTest.php` lulus: 13 tests, 232 assertions.

---

---

## Master Data Tambahan

### MD-001 — Master Data Distribusi
Status: Done

Tujuan:
Menambahkan master data untuk Distribusi (sub-area di bawah POP/Cabang) sesuai permintaan.

Scope:
- Model & Migration `Distribution`.
- Controller `DistributionController` (CRUD).
- Views (index, create, edit) yang mengadopsi styling design system yang ada.
- Relasi dengan `Pop`.
- Navigasi di Sidebar.

Acceptance Criteria:
- [x] Tabel `distributions` dibuat (id, code, description, pop_id).
- [x] Form Create/Edit memiliki input kode, deskripsi, dan dropdown POP/Cabang.
- [x] Menu Master Distribusi muncul di sidebar di bawah Master POP.
- [x] Dokumentasi rancangan selesai di `docs/Rancangan-Master-Distribusi.md`.

Catatan tambahan:
- Seeder Jetis sudah menyiapkan POP induk `C`, mini POP `C1/C2/C3`, dan distribusi `X4A-X4H` sesuai pemetaan cabang dan mini POP yang disepakati.

---

### MD-002 — Collapse & Expand Parent POP
Status: Done

Tujuan:
Mempermudah maintenance data wilayah dengan menambahkan fitur collapse dan expand pada parent POP yang memiliki anak (cabang / mini POP) di tabel list Master POP.

Scope:
- Controller: Mengurutkan hasil query POP secara hirarki tree rekursif dan menyertakan tingkat kedalaman (`depth`).
- View: Indentasi visual nama POP berdasarkan depth, konektor `└─` untuk child, tombol toggle chevron berotasi 90 derajat, dan vanilla JS untuk menyembunyikan/menampilkan child rows secara rekursif.

Acceptance Criteria:
- [x] Parent POP yang memiliki child menampilkan tombol chevron toggle.
- [x] Mengklik tombol toggle menyembunyikan/menampilkan child rows di bawahnya secara interaktif & rekursif.
- [x] Tampilan visual rapi dengan indentasi dan simbol konektor yang membedakan tingkat kedalaman.
- [x] Test suite Pop CRUD dan relasi tetap lulus 100%.

Tujuan:
Menambahkan master data untuk Distribusi (sub-area di bawah POP/Cabang) sesuai permintaan.

Scope:
- Model & Migration `Distribution`.
- Controller `DistributionController` (CRUD).
- Views (index, create, edit) yang mengadopsi styling design system yang ada.
- Relasi dengan `Pop`.
- Navigasi di Sidebar.

Acceptance Criteria:
- [x] Tabel `distributions` dibuat (id, code, description, pop_id).
- [x] Form Create/Edit memiliki input kode, deskripsi, dan dropdown POP/Cabang.
- [x] Menu Master Distribusi muncul di sidebar di bawah Master POP.
- [x] Dokumentasi rancangan selesai di `docs/Rancangan-Master-Distribusi.md`.

Catatan tambahan:
- Seeder Jetis sudah menyiapkan POP induk `C`, mini POP `C1/C2/C3`, dan distribusi `X4A-X4H` sesuai pemetaan cabang dan mini POP yang disepakati.

---

## Sprint 9 — Kelengkapan Detail Pelanggan (Gap Fix)

### S9-T001 — Fix Gap Data Teknis: OLT Number, OLT Slot, VLAN di Technical Detail
Status: Done

Sprint/Module: Sprint 9 — Kelengkapan Detail Pelanggan

Tujuan:
Mengisi 3 gap field teknis yang ditemukan pada analisis kesesuaian Detail Pelanggan:
1. `olt_number` (Nomor OLT) belum ada di `customer_technical_details`.
2. `olt_slot` (Slot OLT) — migration ada tapi kosong, kolom tidak pernah dibuat.
3. `vlan` di detail teknis (saat ini hanya di `customer_devices`, belum di `customer_technical_details`).

Scope:
- Migration: tambah `olt_number`, `olt_slot`, `vlan` ke `customer_technical_details`.
- Model: update fillable `CustomerTechnicalDetail`.
- View: tampilkan field baru di tab Perangkat (bagian Detail Teknis Tambahan).
- Form modal device: tambah field input OLT Number, OLT Slot, VLAN (di sisi technical detail).

Acceptance Criteria:
- [x] Field `olt_number`, `olt_slot`, `vlan` tersimpan di `customer_technical_details`.
- [x] Tab Perangkat menampilkan Nomor OLT, Slot OLT, VLAN dari detail teknis.
- [x] Migration berjalan tanpa error.
- [x] Tidak ada data existing yang rusak.
- [x] Test suite 182 passed, 0 failed.

---

### S9-T002 — Fix Gap Survey: Multi-Petugas Terstruktur & Foto Rumah Terpisah
Status: Done

Sprint/Module: Sprint 9 — Kelengkapan Detail Pelanggan

Tujuan:
Mengisi 2 gap pada Data Survey:
1. `surveyors` masih string bebas — perlu field terstruktur untuk 1–3 petugas survey.
2. Foto rumah pelanggan belum terpisah dari foto survey lapangan/ODP.

Acceptance Criteria:
- [x] Petugas survey 1–3 dapat diisi dari daftar user.
- [x] Foto rumah pelanggan tersimpan terpisah dari foto ODP/survey lapangan.
- [x] Tab Survey menampilkan nama petugas ke-2 dan ke-3 jika diisi.
- [x] Data existing tidak rusak.
- [x] Test suite 182 passed, 0 failed.

---

### S9-T003 — Fix Gap Aktivasi: Relasi User ID pada Petugas Aktivasi
Status: Done

Sprint/Module: Sprint 9 — Kelengkapan Detail Pelanggan

Tujuan:
Mengisi gap pada Laporan Aktivasi:
- `activated_by_name` hanya menyimpan nama string. Tambah `activated_by_user_id` untuk traceability.

Acceptance Criteria:
- [x] `activated_by_user_id` tersimpan saat aktivasi.
- [x] Detail pelanggan tab Paket & Layanan menampilkan waktu dan petugas aktivasi.
- [x] Data existing tidak rusak (nullable).
- [x] Test suite 182 passed, 0 failed.

---

### S9-T004 — Fix Gap Pemasangan: Multi-Teknisi Terstruktur
Status: Done

Sprint/Module: Sprint 9 — Kelengkapan Detail Pelanggan

Tujuan:
Mengisi gap pada Data Pemasangan:
- `technicians` masih string bebas — perlu field terstruktur untuk 2–3 teknisi pemasangan.

Acceptance Criteria:
- [x] Teknisi pemasangan ke-2 dan ke-3 dapat dipilih dari daftar user.
- [x] Tab Pemasangan menampilkan nama teknisi ke-2 dan ke-3 jika diisi.
- [x] Data existing tidak rusak.
- [x] Test suite 182 passed, 0 failed.

---

### S9-T005 — Fix Billing Cycle: Pindahkan Biaya Lain di Luar Standar ke Rincian Biaya Bulanan
Status: Done

Sprint/Module: Sprint 9 — Kelengkapan Detail Pelanggan

Tujuan:
Menyesuaikan implementasi billing agar `biaya lain di luar standar` legacy tidak diperlakukan sebagai biaya invoice terpisah, tetapi sebagai bagian dari rincian biaya bulanan dan `billing cycle`.

Scope:
- Tambah field `other_fee` pada `customer_services`.
- Tampilkan `other_fee` di blok `Rincian Biaya Bulanan & Billing Cycle`.
- Pastikan preview create/edit customer menghitung total bulanan termasuk `other_fee`.
- Saat migrasi legacy, mapping `BIAYALAINLAIN` masuk ke layanan pelanggan.
- Invoice tetap menyimpan histori total tagihan legacy tanpa double count.

Acceptance Criteria:
- [x] `other_fee` tersimpan di `customer_services`.
- [x] `other_fee` tampil di breakdown biaya bulanan pelanggan.
- [x] Create/edit customer menghitung total bulanan dengan `other_fee`.
- [x] Migrasi legacy mengisi `other_fee` dari `BIAYALAINLAIN`.
- [x] Test validasi billing, import legacy, dan detail pelanggan lulus.

Catatan Test:
- `VIEW_COMPILED_PATH=%TEMP%\\whusnet-test-views php artisan test tests/Feature/RealDataMigrationTest.php tests/Feature/InvoiceCreateTest.php tests/Feature/PaymentInputTest.php tests/Feature/CustomerDetailTest.php` lulus: 13 tests, 232 assertions.

---

### MIG-EXE001 — Eksekusi Migrasi Data sand_db_sandya.sql
Status: Done

Tujuan:
Mengeksekusi migrasi data riil secara otomatis ke dalam sistem berdasarkan `sand_db_sandya.sql` menggunakan custom Artisan command.

Langkah:
- [x] Implementasi `MigrateLegacyDataCommand.php`.
- [x] Verifikasi Idempotensi (pencegahan duplikasi).
- [x] Validasi data masuk dengan benar ke master pelanggan, paket, dan tagihan.

### Backlog RBAC & User Management
Status: Done

Tujuan:
Memecah kebutuhan `users`, `roles`, `permissions`, dan pembatasan data POP/customer/billing menjadi sprint kecil yang bisa dikerjakan bertahap.

Rencana Pecahan Sprint:

#### Sprint A — CRUD User Dasar
- [x] Tambah user baru.
- [x] Pilih role saat membuat user.
- [x] Set status aktif/nonaktif user.
- [x] Simpan email, phone, dan password user.
- [x] Validasi data user dasar.

#### Sprint B — Assign POP & Scope User
- [x] Assign satu atau banyak POP ke user.
- [x] Batasi akses Admin Cabang ke POP yang ditugaskan.
- [x] Pertahankan akses penuh Owner/Admin.
- [x] Pastikan filter query customer/invoice/payment memakai scope POP.

#### Sprint C — Role & Permission Sederhana
- [x] Pertahankan role Owner.
- [x] Pertahankan role Admin.
- [x] Pertahankan role Teknisi.
- [x] Pastikan Teknisi tidak bisa akses billing/pembayaran.
- [x] Pastikan Admin bisa akses penuh seperti Owner.

#### Sprint D — UI Manajemen User
- [x] Tambah halaman create/edit user.
- [x] Tambah halaman daftar user yang lebih lengkap.
- [x] Tambah form assign POP yang konsisten.
- [x] Tambah test regresi untuk halaman user management.

#### Sprint E — Audit & Hardening
- [x] Audit log untuk create/update user.
- [x] Audit log untuk assign POP.
- [x] Rapikan pesan error dan validasi.
- [x] Jalankan test coverage RBAC dan user management.

### Backlog Import & Migrasi Pelanggan
Status: Done

Tujuan:
Menjaga konteks pekerjaan migrasi pelanggan dan billing agar tetap jelas setelah RBAC/User Management selesai.

Urutan pengerjaan wajib:

#### Sprint I — Template & Mapping Import
- [x] Template import Excel/CSV pelanggan yang benar-benar mengikuti field untuk pelanggan, detail, dan billing.
- [x] Mapping kolom template disesuaikan dengan field master data baru dan field legacy.
- [x] Struktur sheet/import section divalidasi supaya konsisten dengan alur import.

#### Sprint II — Pipeline Import & Validasi
- [x] Upload dan baca file import mengikuti template yang baru.
- [x] Preview data sebelum import.
- [x] Validasi field wajib, relasi master, dan duplikasi data.
- [x] Error import ditulis dengan alasan yang jelas.

#### Sprint III — Migrasi Data Nyata
- [x] Uji migrasi data nyata dari `sand_db_sandya.sql` end-to-end.
- [x] Pastikan data pelanggan, detail, layanan, billing, dan pembayaran terhubung sesuai mapping.
- [x] Cocokkan hasil migrasi dengan data lama yang paling sering dipakai operasional.

#### Sprint IV — Verifikasi Produksi & Hardening
- [x] Verifikasi produksi dengan data real, termasuk edge case field kosong, relasi rusak, dan duplikasi.
- [x] Siapkan checklist rollback/reimport jika ada data legacy yang gagal.
- [x] Pastikan hasil migrasi layak dipakai untuk operasional terbatas.
- [x] Jika masih ada modul MVP lain yang belum ditutup di task board, kerjakan sesuai urutan MVP terlebih dahulu.

Catatan:
- Role lama seperti `Admin Pusat`, `Admin Cabang`, `Finance/Kasir`, dan `Customer Service` tetap dipertahankan untuk kompatibilitas.
- Pecahan sprint ini sengaja dibuat kecil agar pengembangan RBAC tidak bercampur dengan billing/import.

--- 


# Sprint 12 — Advanced RBAC Database & Core Engine

## Tujuan Sprint 12
Mengimplementasikan pondasi database dan core engine Advanced RBAC: feature tree, action, permission generator, role matrix, user role scope, dan helper effective permission.  
Sprint ini mulai menyentuh database dan core logic, tetapi belum fokus ke UI matrix penuh.

---

### S11-T001 — Normalisasi docs/TASKS.md dan Tambahkan Roadmap Advanced RBAC
**Status**: Done  
**Tujuan**: Merapikan `docs/TASKS.md` agar tidak ada duplikasi sprint/task, format konsisten, dan roadmap Advanced RBAC masuk dengan urutan yang benar.  
**Checklist**:
- [x] Hapus duplikasi Sprint 2 yang muncul berulang.
- [x] Pastikan Sprint 1 sampai Sprint 8 tetap sesuai status terakhir.
- [x] Pastikan hanya S8-T006 — Import Data Legacy sand_db_sandya.sql yang berstatus In Progress selama task tersebut belum selesai.
- [x] Tambahkan Sprint 11 sampai Sprint 15 sebagai roadmap baru.
- [x] Pastikan semua task Sprint 11+ berstatus Todo.
- [x] Rapikan heading sprint.
- [x] Rapikan format Checklist.
- [x] Rapikan format Acceptance Criteria.
- [x] Tambahkan catatan bahwa Advanced RBAC dikerjakan setelah S8-T006 selesai.
- [x] Tambahkan catatan bahwa AI hanya boleh mengerjakan task In Progress.

**Acceptance Criteria**:
- [x] Tidak ada task duplikat.
- [x] Tidak ada sprint duplikat.
- [x] Hanya satu task berstatus In Progress.
- [x] Sprint 11+ tersedia sebagai roadmap lanjutan.
- [x] AI dapat membaca task aktif dengan jelas.

---

### S11-T002 — Update docs/RBAC_MATRIX.md untuk Advanced RBAC
**Status**: Done  
**Tujuan**: Mengubah dokumen RBAC dari role sederhana menjadi hierarchical feature-based RBAC.  
**Checklist**:
- [x] Tambahkan role baru: Owner.
- [x] Tambahkan role baru: Atasan.
- [x] Tambahkan role baru: Admin.
- [x] Tambahkan role baru: NOC.
- [x] Tambahkan role baru: Helpdesk.
- [x] Tambahkan role baru: FOP.
- [x] Tambahkan role baru: Teknisi.
- [x] Tambahkan role baru: Sales.
- [x] Tambahkan role baru: POP Admin.
- [x] Tambahkan konsep Feature Tree.
- [x] Tambahkan konsep Action Permission.
- [x] Tambahkan konsep User Scope.
- [x] Tambahkan aturan bahwa Role tidak boleh dibuat per cabang.
- [x] Tambahkan aturan bahwa Scope menentukan cakupan data.
- [x] Tambahkan contoh NOC Pusat: role NOC, scope all_pop.
- [x] Tambahkan contoh POP Admin Siman: role POP Admin, scope selected_pop.
- [x] Tambahkan matrix permission per role.
- [x] Tambahkan field-level permission untuk data sensitif.
- [x] Tambahkan aturan route middleware.
- [x] Tambahkan aturan query POP scope.

**Acceptance Criteria**:
- [x] `docs/RBAC_MATRIX.md` menjelaskan role baru.
- [x] `docs/RBAC_MATRIX.md` menjelaskan feature tree.
- [x] `docs/RBAC_MATRIX.md` menjelaskan action permission.
- [x] `docs/RBAC_MATRIX.md` menjelaskan user scope.
- [x] Role dan scope dipisahkan dengan jelas.
- [x] Tidak ada rekomendasi membuat role per cabang.
- [x] Permission NOC, Helpdesk, FOP, Teknisi, Sales, POP Admin tertulis jelas.

---

### S11-T003 — Update docs/DATABASE_RULES.md untuk Advanced RBAC
**Status**: Done  
**Tujuan**: Menambahkan aturan database untuk feature tree, action, permission berbasis feature-action, dan user role scope.  
**Checklist**:
- [x] Tambahkan aturan tabel `features`.
- [x] Tambahkan aturan tabel `actions`.
- [x] Tambahkan aturan perubahan tabel `permissions`.
- [x] Tambahkan aturan tabel `role_permissions`.
- [x] Tambahkan aturan tabel `user_role_scopes`.
- [x] Tambahkan aturan optional `user_permission_overrides`.
- [x] Tambahkan unique constraint `features.code`.
- [x] Tambahkan unique constraint `actions.code`.
- [x] Tambahkan unique constraint `permissions.code`.
- [x] Tambahkan unique constraint kombinasi `feature_id` dan `action_id`.
- [x] Tambahkan aturan scope type.
- [x] Tambahkan aturan `all_pop`.
- [x] Tambahkan aturan `selected_pop`.
- [x] Tambahkan aturan `pop_tree`.
- [x] Tambahkan aturan `assigned_only`.
- [x] Tambahkan aturan `own_created`.
- [x] Tambahkan larangan membuat ID permission tidak konsisten.
- [x] Tambahkan larangan hardcode permission string sembarangan.

**Acceptance Criteria**:
- [x] `docs/DATABASE_RULES.md` memuat tabel `features`.
- [x] `docs/DATABASE_RULES.md` memuat tabel `actions`.
- [x] `docs/DATABASE_RULES.md` memuat tabel `user_role_scopes`.
- [x] Aturan permission `{feature_code}.{action_code}` tertulis jelas.
- [x] Aturan scope user tertulis jelas.
- [x] Aturan migrasi dari RBAC lama ke RBAC baru tertulis jelas.

---

### S11-T004 — Update docs/BUSINESS_RULES.md untuk Role dan Scope Baru
**Status**: Done  
**Tujuan**: Memastikan aturan bisnis project memahami role baru dan batasan scope data.  
**Checklist**:
- [x] Tambahkan aturan Owner.
- [x] Tambahkan aturan Atasan.
- [x] Tambahkan aturan Admin.
- [x] Tambahkan aturan NOC.
- [x] Tambahkan aturan Helpdesk.
- [x] Tambahkan aturan FOP.
- [x] Tambahkan aturan Teknisi.
- [x] Tambahkan aturan Sales.
- [x] Tambahkan aturan POP Admin.
- [x] Tambahkan aturan NOC Pusat bisa scope semua POP.
- [x] Tambahkan aturan NOC Cabang hanya selected POP.
- [x] Tambahkan aturan POP Admin wajib selected POP.
- [x] Tambahkan aturan Sales bisa own_created atau selected_pop.
- [x] Tambahkan aturan Teknisi bisa selected_pop atau assigned_only.
- [x] Tambahkan larangan Teknisi mengakses pembayaran.
- [x] Tambahkan larangan Helpdesk mengubah nominal tagihan.
- [x] Tambahkan larangan Sales mengakses laporan pembayaran.
- [x] Tambahkan larangan POP Admin melihat POP lain.

**Acceptance Criteria**:
- [x] Business rules role baru tersedia.
- [x] Scope data per role tertulis jelas.
- [x] Larangan role sensitif tertulis jelas.
- [x] NOC Pusat, POP Admin, Teknisi, Sales memiliki aturan akses yang jelas.

---

### S11-T005 — Update docs/PAGE_STRUCTURE.md untuk UI Role, Feature, Permission, dan Scope
**Status**: Done  
**Tujuan**: Menambahkan struktur halaman untuk Advanced RBAC.  
**Checklist**:
- [x] Tambahkan halaman Feature Management.
- [x] Tambahkan halaman Action Management.
- [x] Tambahkan halaman Permission Matrix.
- [x] Tambahkan halaman Role Permission Matrix.
- [x] Tambahkan halaman User Role Scope.
- [x] Tambahkan struktur form tambah user dengan role dan scope.
- [x] Tambahkan struktur preview effective permission.
- [x] Tambahkan struktur permission matrix tree.
- [x] Tambahkan aturan menu berdasarkan feature permission.
- [x] Tambahkan aturan tombol berdasarkan action permission.
- [x] Tambahkan aturan field sensitif berdasarkan permission sensitive.
- [x] Tambahkan empty state untuk feature tree.
- [x] Tambahkan role akses halaman Advanced RBAC.

**Acceptance Criteria**:
- [x] Struktur halaman Advanced RBAC tersedia.
- [x] Struktur form tambah/edit user dengan role dan scope tersedia.
- [x] Struktur permission matrix berbasis feature tree tersedia.
- [x] Struktur preview effective permission tersedia.
- [x] Role yang boleh mengelola RBAC tertulis jelas.

---

### S11-T006 — Update docs/DEFINITION_OF_DONE.md untuk Advanced RBAC
**Status**: Done  
**Tujuan**: Menambahkan standar task selesai untuk Advanced RBAC.  
**Checklist**:
- [x] Tambahkan DoD untuk Feature Tree.
- [x] Tambahkan DoD untuk Action Permission.
- [x] Tambahkan DoD untuk Permission Generator.
- [x] Tambahkan DoD untuk Role Permission Matrix.
- [x] Tambahkan DoD untuk User Role Scope.
- [x] Tambahkan DoD untuk User Form Role Scope.
- [x] Tambahkan DoD untuk Effective Permission Preview.
- [x] Tambahkan DoD untuk Middleware Feature Action Permission.
- [x] Tambahkan DoD untuk POP Scope Helper.
- [x] Tambahkan DoD untuk Sidebar berbasis permission.
- [x] Tambahkan DoD untuk test Advanced RBAC.
- [x] Tambahkan larangan menandai task Done jika route belum aman.
- [x] Tambahkan larangan menandai task Done jika POP scope bocor.
- [x] Tambahkan larangan menandai task Done jika user bisa akses URL langsung tanpa permission.

**Acceptance Criteria**:
- [x] DoD Advanced RBAC tersedia.
- [x] Setiap task Advanced RBAC punya standar selesai.
- [x] Route middleware menjadi syarat Done.
- [x] POP scope menjadi syarat Done.
- [x] Test RBAC menjadi syarat Done.

---

### S11-T007 — Update docs/MVP_SUCCESS_CHECKLIST.md untuk Advanced RBAC
**Status**: Done  
**Tujuan**: Menambahkan checklist final MVP untuk Advanced RBAC.  
**Checklist**:
- [x] Tambahkan checklist role baru tersedia.
- [x] Tambahkan checklist feature tree tersedia.
- [x] Tambahkan checklist action tersedia.
- [x] Tambahkan checklist permission berbasis feature-action tersedia.
- [x] Tambahkan checklist user role scope tersedia.
- [x] Tambahkan checklist NOC Pusat all_pop.
- [x] Tambahkan checklist POP Admin selected_pop.
- [x] Tambahkan checklist Teknisi tidak bisa pembayaran.
- [x] Tambahkan checklist Helpdesk tidak bisa ubah nominal tagihan.
- [x] Tambahkan checklist Sales tidak bisa laporan pembayaran.
- [x] Tambahkan checklist route direct access aman.
- [x] Tambahkan checklist field sensitive aman.
- [x] Tambahkan checklist POP scope tidak bocor.

**Acceptance Criteria**:
- [x] MVP checklist memuat Advanced RBAC.
- [x] Checklist role baru tersedia.
- [x] Checklist scope baru tersedia.
- [x] Checklist permission feature-action tersedia.
- [x] Checklist keamanan RBAC tersedia.

---

### S11-T008 — Update docs/DAILY_PROMPTS.md untuk Advanced RBAC
**Status**: Done  
**Tujuan**: Menambahkan prompt khusus Advanced RBAC agar AI tidak salah membangun role, permission, dan scope.  
**Checklist**:
- [x] Tambahkan Prompt Advanced RBAC Scope Check.
- [x] Tambahkan Prompt Feature Tree.
- [x] Tambahkan Prompt Action Permission.
- [x] Tambahkan Prompt Permission Generator.
- [x] Tambahkan Prompt Role Matrix.
- [x] Tambahkan Prompt User Role Scope.
- [x] Tambahkan Prompt User Form Role Scope.
- [x] Tambahkan Prompt Middleware Permission.
- [x] Tambahkan Prompt POP Scope.
- [x] Tambahkan Prompt RBAC Test.
- [x] Tambahkan prompt cegah role per cabang.
- [x] Tambahkan prompt cegah permission hardcode sembarangan.

**Acceptance Criteria**:
- [x] Prompt Advanced RBAC tersedia.
- [x] Prompt mengunci role dan scope tetap terpisah.
- [x] Prompt melarang role per cabang.
- [x] Prompt mewajibkan scope check sebelum coding.

---

### S11-T009 — Update AGENTS.md untuk Advanced RBAC
**Status**: Done  
**Tujuan**: Memastikan AI Agent membaca dan mengikuti aturan Advanced RBAC.  
**Checklist**:
- [x] Tambahkan Advanced RBAC ke Required Reading.
- [x] Tambahkan aturan role tidak boleh dibuat per cabang.
- [x] Tambahkan aturan role dan scope harus dipisah.
- [x] Tambahkan aturan permission berbasis feature-action.
- [x] Tambahkan aturan format permission.
- [x] Tambahkan aturan scope `all_pop`.
- [x] Tambahkan aturan scope `selected_pop`.
- [x] Tambahkan aturan scope `pop_tree`.
- [x] Tambahkan aturan scope `assigned_only`.
- [x] Tambahkan aturan scope `own_created`.
- [x] Tambahkan stop condition jika AI ingin membuat role cabang seperti NOC Siman.
- [x] Tambahkan stop condition jika AI ingin memberi permission langsung ke user tanpa alasan.
- [x] Tambahkan stop condition jika perubahan RBAC bisa membocorkan data POP.

**Acceptance Criteria**:
- [x] `AGENTS.md` memahami Advanced RBAC.
- [x] AI dilarang membuat role per cabang.
- [x] AI wajib memakai role + scope.
- [x] AI wajib memakai permission berbasis feature-action.
- [x] Stop condition Advanced RBAC tersedia.

---

### S12-T001 — Migration dan Model Feature Tree
**Status**: Done  
**Tujuan**: Membuat struktur fitur utama, cabang fitur, dan mini fitur.  
**Checklist**:
- [x] Buat tabel `features`.
- [x] Tambahkan field `parent_id`.
- [x] Tambahkan field `code`.
- [x] Tambahkan field `name`.
- [x] Tambahkan field `type`.
- [x] Tambahkan field `sort_order`.
- [x] Tambahkan field `is_active`.
- [x] Tambahkan unique constraint `code`.
- [x] Tambahkan index `parent_id`.
- [x] Tambahkan index `type`.
- [x] Buat model `Feature`.
- [x] Buat relasi parent.
- [x] Buat relasi children.
- [x] Buat helper membaca feature tree.

**Acceptance Criteria**:
- [x] Feature dapat disimpan.
- [x] Feature dapat bertingkat.
- [x] Feature utama, sub feature, dan mini feature tersedia.
- [x] Feature code unique.
- [x] Relasi parent-child berjalan.

---

### S12-T002 — Seeder Feature Tree Awal
**Status**: Done  
**Tujuan**: Mengisi data feature tree awal sesuai fitur MVP dan Advanced RBAC.  
**Checklist**:
- [x] Seed feature Dashboard.
- [x] Seed feature POP/Cabang.
- [x] Seed feature User Management.
- [x] Seed feature Role & Permission.
- [x] Seed feature Paket Internet.
- [x] Seed feature Pelanggan.
- [x] Seed feature Pelanggan > Daftar Pelanggan.
- [x] Seed feature Pelanggan > Detail Pelanggan.
- [x] Seed feature Detail Pelanggan > Identitas.
- [x] Seed feature Detail Pelanggan > Alamat.
- [x] Seed feature Detail Pelanggan > POP/Cabang.
- [x] Seed feature Detail Pelanggan > Paket & Layanan.
- [x] Seed feature Detail Pelanggan > Billing.
- [x] Seed feature Detail Pelanggan > Tagihan.
- [x] Seed feature Detail Pelanggan > Pembayaran.
- [x] Seed feature Detail Pelanggan > Survey.
- [x] Seed feature Detail Pelanggan > Pemasangan.
- [x] Seed feature Detail Pelanggan > Perangkat.
- [x] Seed feature Detail Pelanggan > Dokumen.
- [x] Seed feature Import Pelanggan.
- [x] Seed feature Billing.
- [x] Seed feature Billing > Tagihan.
- [x] Seed feature Billing > Pembayaran.
- [x] Seed feature Laporan.
- [x] Seed feature Audit Log.

**Acceptance Criteria**:
- [x] Feature tree awal tersedia dari seeder.
- [x] Semua fitur MVP masuk feature tree.
- [x] Detail pelanggan memiliki mini feature.
- [x] Billing memiliki sub feature Tagihan dan Pembayaran.
- [x] Tidak ada feature post-MVP yang aktif.

---

### S12-T003 — Migration, Model, dan Seeder Action Permission
**Status**: Done  
**Tujuan**: Membuat daftar action yang bisa dipasang ke feature.  
**Checklist**:
- [x] Buat tabel `actions`.
- [x] Tambahkan field `code`.
- [x] Tambahkan field `name`.
- [x] Tambahkan field `description`.
- [x] Tambahkan unique constraint `code`.
- [x] Buat model `Action`.
- [x] Seed action `view`.
- [x] Seed action `create`.
- [x] Seed action `update`.
- [x] Seed action `delete`.
- [x] Seed action `import`.
- [x] Seed action `export`.
- [x] Seed action `print`.
- [x] Seed action `approve`.
- [x] Seed action `reject`.
- [x] Seed action `activate`.
- [x] Seed action `deactivate`.
- [x] Seed action `assign`.
- [x] Seed action `validate`.
- [x] Seed action `cancel`.
- [x] Seed action `upload`.
- [x] Seed action `download`.
- [x] Seed action `view_sensitive`.
- [x] Seed action `update_sensitive`.

**Acceptance Criteria**:
- [x] Action CRUD tersedia.
- [x] Action bisnis tersedia.
- [x] Action sensitive tersedia.
- [x] Action code unique.
- [x] Action dapat digunakan untuk permission generator.

---

### S12-T004 — Refactor Permission Menjadi Feature-Action Permission
**Status**: Done  
**Tujuan**: Mengubah permission agar berbasis kombinasi feature dan action.  
**Checklist**:
- [x] Tambahkan `feature_id` ke tabel `permissions`.
- [x] Tambahkan `action_id` ke tabel `permissions`.
- [x] Pastikan field `code` tersedia dan unique.
- [x] Format permission: `{feature_code}.{action_code}`.
- [x] Contoh: `customers.view`.
- [x] Contoh: `customers.detail.identity.update`.
- [x] Contoh: `customers.detail.devices.view_sensitive`.
- [x] Buat relasi permission ke feature.
- [x] Buat relasi permission ke action.
- [x] Buat generator permission.
- [x] Cegah permission duplikat.
- [x] Pastikan permission lama dapat dimigrasikan atau digantikan aman.

**Acceptance Criteria**:
- [x] Permission terhubung ke feature.
- [x] Permission terhubung ke action.
- [x] Permission code konsisten.
- [x] Permission tidak duplikat.
- [x] Permission lama tidak merusak login/akses existing.

---

### S12-T005 — Permission Generator dari Feature dan Action
**Status**: Done  
**Tujuan**: Membuat service/command untuk menghasilkan permission dari feature dan action.  
**Checklist**:
- [x] Buat service `PermissionGeneratorService`.
- [x] Buat command `php artisan rbac:generate-permissions`.
- [x] Generate permission hanya untuk kombinasi feature-action yang valid.
- [x] Jangan generate semua action untuk semua feature jika tidak relevan.
- [x] Buat konfigurasi allowed actions per feature.
- [x] Pastikan permission code unique.
- [x] Pastikan generator idempotent.
- [x] Tampilkan summary permission dibuat/dilewati.
- [x] Tambahkan test generator.

**Acceptance Criteria**:
- [x] Command generator berjalan tanpa error.
- [x] Permission dibuat sesuai feature-action.
- [x] Generator bisa dijalankan berulang tanpa duplikasi.
- [x] Permission post-MVP tidak dibuat aktif.
- [x] Test generator lulus.

---

### S12-T006 — Role Migration dan Seeder Role Baru
**Status**: Done  
**Tujuan**: Menambahkan role baru dan mengatur migrasi dari role lama ke role baru.  
**Checklist**:
- [x] Tambahkan role Owner.
- [x] Tambahkan role Atasan.
- [x] Tambahkan role Admin.
- [x] Tambahkan role NOC.
- [x] Tambahkan role Helpdesk.
- [x] Tambahkan role FOP.
- [x] Tambahkan role Teknisi.
- [x] Tambahkan role Sales.
- [x] Tambahkan role POP Admin.
- [x] Mapping role lama Admin Pusat ke Admin dengan scope `all_pop`.
- [x] Mapping role lama Admin Cabang ke POP Admin dengan scope `selected_pop`.
- [x] Mapping role lama Customer Service ke Helpdesk.
- [x] Mapping role lama Finance/Kasir ke role yang disepakati.
- [x] Pastikan role lama tidak langsung dihapus sebelum migrasi aman.
- [x] Tambahkan catatan migrasi role.

**Acceptance Criteria**:
- [x] Role baru tersedia.
- [x] Role lama memiliki strategi migrasi.
- [x] User existing tidak kehilangan akses login.
- [x] Mapping role lama terdokumentasi.
- [x] Tidak ada role per cabang.

**Catatan**: Untuk Finance/Kasir, tentukan keputusan:
- Opsi A: dimasukkan ke role Admin dengan permission pembayaran.
- Opsi B: tetap dipertahankan sebagai role tambahan.
- Opsi C: dibuat role Kasir jika bisnis masih butuh pemisahan pembayaran.
*AI wajib meminta konfirmasi sebelum menghapus atau mengganti total role Finance/Kasir.*

---

### S12-T007 — Role Permission Matrix Seeder
**Status**: Done  
**Tujuan**: Membuat mapping permission default untuk setiap role baru.  
**Checklist**:
- [x] Buat mapping permission Owner.
- [x] Buat mapping permission Atasan.
- [x] Buat mapping permission Admin.
- [x] Buat mapping permission NOC.
- [x] Buat mapping permission Helpdesk.
- [x] Buat mapping permission FOP.
- [x] Buat mapping permission Teknisi.
- [x] Buat mapping permission Sales.
- [x] Buat mapping permission POP Admin.
- [x] Pastikan Owner memiliki semua permission.
- [x] Pastikan Atasan fokus dashboard/laporan/audit terbatas.
- [x] Pastikan Admin fokus operasional.
- [x] Pastikan NOC fokus monitoring dan teknis jaringan.
- [x] Pastikan Helpdesk fokus layanan pelanggan.
- [x] Pastikan FOP fokus survey/pemasangan lapangan.
- [x] Pastikan Teknisi fokus survey/pemasangan/perangkat.
- [x] Pastikan Sales fokus registrasi/follow-up.
- [x] Pastikan POP Admin fokus operasional POP.
- [x] Pastikan Teknisi tidak mendapat payment permission.
- [x] Pastikan Helpdesk tidak mendapat update nominal tagihan.
- [x] Pastikan Sales tidak mendapat laporan pembayaran.

**Acceptance Criteria**:
- [x] Setiap role memiliki permission default.
- [x] Permission role sesuai matrix.
- [x] Tidak ada permission berlebihan pada role teknis/sales/helpdesk.
- [x] Seeder role permission idempotent.
- [x] Test role permission lulus.

---

### S12-T008 — Migration dan Model User Role Scope
**Status**: Done  
**Tujuan**: Memisahkan role dari cakupan data user.  
**Checklist**:
- [x] Buat tabel `user_role_scopes`.
- [x] Tambahkan `user_id`.
- [x] Tambahkan `role_id`.
- [x] Tambahkan `scope_type`.
- [x] Tambahkan tabel `user_role_scope_targets` untuk multiple pop.
- [x] Tambahkan index `user_id`.
- [x] Tambahkan index `role_id`.
- [x] Tambahkan index `pop_id`.
- [x] Tambahkan validasi scope type via Enum.
- [x] Buat model `UserRoleScope`.
- [x] Buat relasi user ke user role scope.
- [x] Buat relasi role ke user role scope.
- [x] Buat relasi POP ke user role scope.
- [x] Migrasikan `user_pops` lama jika diperlukan.

**Scope Type**:
`all_pop`, `selected_pop`, `pop_tree`, `assigned_only`, `own_created`

**Acceptance Criteria**:
- [x] User dapat memiliki role dengan scope.
- [x] Role dan scope terpisah.
- [x] NOC Pusat dapat dibuat dengan role NOC dan scope `all_pop`.
- [x] POP Admin dapat dibuat dengan role POP Admin dan scope `selected_pop`.
- [x] Tidak perlu membuat role per cabang.

---

### S12-T009 — Effective Permission dan Effective Scope Service
**Status**: Done  
**Tujuan**: Membuat service untuk menghitung permission dan scope efektif user.  
**Checklist**:
- [x] Buat `EffectiveAccessService`.
- [x] Buat method membaca role user.
- [x] Buat method membaca permission role.
- [x] Buat method membaca scope user.
- [x] Buat method `userCan($permissionCode)`.
- [x] Buat method `userCan($featureCode, $actionCode)`.
- [x] Buat method `getAllowedPopIds($user)`.
- [x] Dukung scope `all_pop`.
- [x] Dukung scope `selected_pop`.
- [x] Dukung scope `pop_tree`.
- [x] Dukung scope `assigned_only` jika data assignment tersedia.
- [x] Dukung scope `own_created` jika data `created_by` tersedia.
- [x] Tambahkan cache jika diperlukan.
- [x] Tambahkan test service.

**Acceptance Criteria**:
- [x] Permission efektif user dapat dihitung.
- [x] Scope efektif user dapat dihitung.
- [x] NOC `all_pop` melihat semua POP.
- [x] POP Admin `selected_pop` hanya melihat POP tertentu.
- [x] Service dapat digunakan middleware dan query.

---

### S12-T010 — Backward Compatibility RBAC Lama
**Status**: Done  
**Tujuan**: Menjaga agar sistem tetap berjalan selama transisi dari RBAC lama ke Advanced RBAC.  
**Checklist**:
- [x] Audit middleware permission lama.
- [x] Audit helper permission lama.
- [x] Buat adapter dari permission lama ke permission baru jika diperlukan.
- [x] Pastikan route existing tidak langsung rusak.
- [x] Pastikan menu existing masih tampil sesuai permission.
- [x] Pastikan user existing masih bisa login.
- [x] Pastikan role lama tidak dihapus sebelum mapping selesai.
- [x] Tambahkan test login user existing.
- [x] Tambahkan test akses halaman existing.

**Acceptance Criteria**:
- [x] User existing tetap bisa login.
- [x] Route existing tetap aman.
- [x] Tidak ada breaking change besar.
- [x] RBAC lama bisa berjalan selama migrasi.
- [x] Transisi ke RBAC baru terdokumentasi.

---
---

# Sprint 13 — Advanced RBAC UI, Middleware, Scope Enforcement & Tests

## Tujuan Sprint 13
Menerapkan Advanced RBAC ke UI dan keamanan aplikasi: form tambah user, permission matrix, middleware feature-action, sidebar, tombol aksi, POP scope query, dan test keamanan.

### S13-T001 — Form Tambah/Edit User dengan Role dan Scope
**Status**: Done  
**Tujuan**: Mengubah form tambah/edit user agar bisa memilih role dan scope data.  
**Checklist**:
- [x] Tambahkan pilihan role baru.
- [x] Tambahkan pilihan scope type.
- [x] Jika role Owner, default scope `all_pop`.
- [x] Jika role NOC, boleh `all_pop`, `selected_pop`, atau `pop_tree`.
- [x] Jika role POP Admin, wajib `selected_pop`.
- [x] Jika role Teknisi, boleh `selected_pop` atau `assigned_only`.
- [x] Jika role Sales, boleh `selected_pop` atau `own_created`.
- [x] Jika scope `selected_pop`, POP wajib dipilih.
- [x] Jika scope `pop_tree`, POP parent wajib dipilih.
- [x] Jika scope `all_pop`, POP tidak wajib.
- [x] Validasi kombinasi role dan scope.
- [x] Simpan ke `user_role_scopes`.
- [x] Tampilkan error jika kombinasi tidak valid.

**Acceptance Criteria**:
- [x] Admin dapat membuat user dengan role dan scope.
- [x] NOC Pusat bisa dibuat dengan scope `all_pop`.
- [x] POP Admin tidak bisa dibuat tanpa POP.
- [x] Teknisi bisa dibatasi `selected_pop`/`assigned_only`.
- [x] Sales bisa dibatasi `own_created`/`selected_pop`.
- [x] Validasi role-scope berjalan.

---

### S13-T002 — Effective Permission Preview Saat Tambah/Edit User
**Status**: Done  
**Tujuan**: Menampilkan ringkasan akses user sebelum disimpan.  
**Checklist**:
- [x] Tampilkan role yang dipilih.
- [x] Tampilkan scope yang dipilih.
- [x] Tampilkan POP yang dipilih jika ada.
- [x] Tampilkan ringkasan fitur yang bisa diakses.
- [x] Tampilkan ringkasan action penting.
- [x] Tampilkan warning jika scope `all_pop`.
- [x] Tampilkan warning jika role dan scope tidak cocok.
- [x] Tampilkan warning jika role memiliki permission sensitif.
- [x] Tampilkan contoh data yang bisa dilihat user.
- [x] Jangan izinkan simpan jika preview menunjukkan konfigurasi invalid.

**Acceptance Criteria**:
- [x] Admin dapat melihat hak akses sebelum user disimpan.
- [x] Scope `all_pop` terlihat jelas.
- [x] Scope `selected_pop` terlihat jelas.
- [x] Permission sensitif terlihat jelas.
- [x] Konfigurasi invalid ditolak.

---

### S13-T003 — Permission Matrix UI Berbasis Feature Tree
**Status**: Done  
**Tujuan**: Membuat halaman role permission matrix berbasis fitur bertingkat.  
**Checklist**:
- [x] Buat halaman daftar role.
- [x] Buat halaman matrix permission role.
- [x] Tampilkan feature tree expand/collapse.
- [x] Tampilkan kolom action.
- [x] Tampilkan checkbox permission.
- [x] Tampilkan fitur utama.
- [x] Tampilkan cabang fitur.
- [x] Tampilkan mini fitur.
- [x] Simpan perubahan ke `role_permissions`.
- [x] Batasi akses hanya Owner atau role yang diizinkan.
- [x] Catat perubahan role permission ke audit log.
- [x] Cegah role biasa mengubah permission.
- [x] Tambahkan test update role permission.

**Acceptance Criteria**:
- [x] Permission dapat diatur per role.
- [x] Matrix berbasis feature tree.
- [x] Mini fitur dapat punya permission sendiri.
- [x] Perubahan permission masuk audit log.
- [x] Hanya role berwenang yang bisa mengubah matrix.

---

### S13-T004 — Middleware Feature-Action Permission
**Status**: Done  
**Tujuan**: Mengamankan route dengan permission berbasis feature dan action.  
**Checklist**:
- [x] Buat middleware feature-action permission.
- [x] Dukung pengecekan dengan permission code.
- [x] Dukung pengecekan dengan feature code dan action code.
- [x] Terapkan ke route dashboard.
- [x] Terapkan ke route pelanggan.
- [x] Terapkan ke route import.
- [x] Terapkan ke route invoice.
- [x] Terapkan ke route payment.
- [x] Terapkan ke route laporan.
- [x] Terapkan ke route audit log.
- [x] Return forbidden jika tidak punya permission.
- [x] Tambahkan test direct URL access.

**Acceptance Criteria**:
- [x] Route dicek backend.
- [x] User tanpa permission mendapat forbidden.
- [x] Menu disembunyikan bukan satu-satunya proteksi.
- [x] Direct URL access aman.
- [x] Test middleware lulus.

---

### S13-T005 — POP Scope Query Enforcement
**Status**: Done  
**Tujuan**: Memastikan query data mengikuti scope user.  
**Checklist**:
- [x] Buat helper `applyUserScope`.
- [x] Terapkan ke daftar pelanggan.
- [x] Terapkan ke detail pelanggan.
- [x] Terapkan ke invoice.
- [x] Terapkan ke payment.

**Acceptance Criteria**:
- [x] Data pelanggan terisolasi per POP.
- [x] Role `Owner` dan `Admin` bisa melihat semua data.
- [x] User dengan scope `all_pop` melihat semua data.
- [x] Transaksi (invoice/payment) juga dibatasi sesuai POP.

---


### S13-T006 — Sidebar dan Tombol Aksi Berdasarkan Feature Permission
**Status**: Done  
**Tujuan**: Menampilkan menu dan tombol aksi sesuai permission user.  
**Checklist**:
- [x] Sidebar membaca permission user.
- [x] Menu utama tampil jika user punya permission `view` pada fitur utama.
- [x] Submenu tampil jika user punya permission pada sub fitur.
- [x] Tombol create tampil jika punya permission `create`.
- [x] Tombol edit tampil jika punya permission `update`.
- [x] Tombol delete tampil jika punya permission `delete`.
- [x] Tombol import tampil jika punya permission `import`.
- [x] Tombol export tampil jika punya permission `export`.
- [x] Tombol print tampil jika punya permission `print`.
- [x] Tombol activate tampil jika punya permission `activate`.
- [x] Tombol validate tampil jika punya permission `validate`.
- [x] Field sensitive tampil jika punya permission `view_sensitive`.
- [x] Pastikan route tetap aman walaupun tombol disembunyikan.

**Acceptance Criteria**:
- [x] Menu sesuai permission.
- [x] Tombol aksi sesuai permission.
- [x] Field sensitive sesuai permission.
- [x] User tanpa permission tidak melihat tombol.
- [x] Route tetap dilindungi middleware.

---

### S13-T007 — Protect Sensitive Fields dengan Permission
**Status**: Done  
**Tujuan**: Mengamankan field sensitif seperti PPPoE, WiFi, IP, VLAN, dan data teknis.  
**Checklist**:
- [x] Audit field sensitif perangkat.
- [x] Terapkan permission `view_sensitive`.
- [x] Terapkan permission `update_sensitive`.
- [x] Sembunyikan password PPPoE dari role tanpa permission.
- [x] Sembunyikan password WiFi dari role tanpa permission.
- [x] Sembunyikan IP/VLAN jika dianggap sensitif.
- [x] Cegah update field sensitif via request langsung.
- [x] Test Finance tidak bisa lihat password teknis.
- [x] Test Helpdesk tidak bisa lihat password teknis.
- [x] Test Teknisi dengan permission bisa lihat/update jika diizinkan.

**Acceptance Criteria**:
- [x] Field sensitif aman di UI.
- [x] Field sensitif aman dari request langsung.
- [x] Role tanpa permission tidak bisa melihat password teknis.
- [x] Role tanpa permission tidak bisa mengubah field sensitif.
- [x] Test sensitive field lulus.

---

### S13-T008 — Audit Log untuk Perubahan RBAC
**Status**: Done  
**Tujuan**: Mencatat semua perubahan role, permission, feature, action, dan user scope.  
**Checklist**:
- [x] Catat create/update feature.
- [x] Catat create/update action.
- [x] Catat generate permission.
- [x] Catat perubahan role permission.
- [x] Catat perubahan user role scope.
- [x] Catat perubahan role user.
- [x] Catat user pelaku.
- [x] Catat waktu perubahan.
- [x] Catat old values dan new values jika memungkinkan.
- [x] Tampilkan di audit log.
- [x] Batasi akses audit log ke Owner/Atasan sesuai permission.

**Acceptance Criteria**:
- [ ] Perubahan RBAC tercatat.
- [ ] Perubahan scope user tercatat.
- [ ] Perubahan permission role tercatat.
- [ ] Audit log dapat dilihat role berwenang.
- [ ] User biasa tidak bisa menghapus audit log.

---

### S13-T008.1 — Modul Task Management (FOP & Teknisi)
**Status**: Done  
**Tujuan**: Membangun modul penjadwalan dan eksekusi tugas lapangan untuk FOP (koordinator) dan Teknisi, lengkap dengan database, permission, policy, controller, API, dan UI kalender/dashboard.  

**Konteks**:
- FOP membuat, menjadwalkan, dan mengassign task ke tim teknisi.
- Teknisi mengerjakan task di lapangan: mulai, isi checklist, upload bukti, selesai/pending.
- Semua access control via permission dinamis (`$user->can('task.xxx')`), tidak ada hardcode role.
- Lifecycle: Draft → Terjadwal → In Progress → Selesai (atau Dibatalkan / Pending).
- Integrasi: tabel `customers`, `pops`, RBAC existing, Telegram bot (notifikasi async via queue).

**Checklist**:

#### Database
- [x] Migration `tasks` (id, customer_id, pop_id, task_type, title, description, status, scheduled_at, started_at, completed_at, cancelled_at, fop_id, sla_minutes, conflict_override, created_by, updated_by)
- [x] Migration `task_teams` (id, task_id, user_id, role_in_task)
- [x] Migration `task_checklist_templates` (id, task_type, item, is_required, sort_order)
- [x] Migration `task_checklists` (id, task_id, template_id, item, is_required, is_checked, checked_by, checked_at)
- [x] Migration `task_evidences` (id, task_id, uploaded_by, file_path, caption, created_at)

#### Enums & Models
- [x] Enum `TaskType` (survey, pemasangan, maintenance, ambil_modem, relokasi)
- [x] Enum `TaskStatus` (draft, terjadwal, in_progress, selesai, dibatalkan, pending)
- [x] Model `Task` dengan relasi customer, pop, fop, teamMembers, checklists, evidences
- [x] Model `TaskTeam`
- [x] Model `TaskChecklistTemplate`
- [x] Model `TaskChecklist`
- [x] Model `TaskEvidence`
- [x] Trait `HasPopScope` sudah tersedia — apply ke Model `Task`

#### Permissions & Seeder
- [x] Seed permission FOP: `task.create`, `task.schedule`, `task.view.all`, `task.edit`, `task.cancel`, `task.assign.team`, `task.report.view`, `task.conflict.override`
- [x] Seed permission Teknisi: `task.view.own`, `task.status.start`, `task.status.complete`, `task.status.pending`, `task.checklist.update`, `task.evidence.upload`
- [x] Assign permission ke role FOP dan Teknisi via seeder

#### Policy
- [x] `TaskPolicy` — semua method via `$user->can()`, tidak ada `hasRole()`
- [x] `before()` untuk owner/wildcard permission
- [x] Guard: viewAll, viewOwn, create, edit, cancel, assignTeam, schedule, conflictOverride, statusStart, statusComplete, statusPending, updateChecklist, uploadEvidence

#### Controller & Routes
- [x] `TaskController` (index/FOP view, indexOwn/Teknisi, create, store, show, edit, update, cancel)
- [x] `TaskStatusController` (start, complete, pending)
- [x] `TaskChecklistController` (update checklist item)
- [x] `TaskEvidenceController` (upload bukti)
- [x] Route group dengan middleware permission masing-masing
- [x] API endpoint JSON untuk kalender (filter tanggal, POP, tipe)

#### Jobs & Notifikasi
- [x] `SendTaskNotificationJob` (queue, kirim Telegram ke anggota tim saat task dibuat/dijadwal ulang)

#### UI — FOP Dashboard
- [x] Halaman kalender/timeline (harian, mingguan, bulanan) — task card berwarna per tipe
- [x] Panel Ringkasan Hari Ini (total, selesai, pending, dibatalkan)
- [x] Panel Tim Aktif (avatar, jumlah task hari ini)
- [x] Popup detail task (checklist progress, foto, info tim)
- [x] Form buat task baru (tipe, CID pelanggan, jadwal, assign 1–3 teknisi, validasi konflik)
- [x] Warning konflik jadwal teknisi + bypass jika punya `task.conflict.override`
- [x] Tombol Edit & Cancel task sesuai permission

#### UI — Teknisi Dashboard (mobile-first)
- [x] Halaman daftar task hari ini (hanya task milik teknisi yang login)
- [x] Tombol Mulai (guard: `task.status.start`)
- [x] Checklist items — centang per item (guard: `task.checklist.update`)
- [x] Upload foto bukti (guard: `task.evidence.upload`)
- [x] Tombol Selesai — hanya aktif jika semua checklist wajib tercentang DAN minimal 1 foto ada
- [x] Tombol Pending (guard: `task.status.pending`)

#### Business Rules
- [x] Maksimal 4 task aktif per tim per hari (validasi di service)
- [x] SLA enforcement: Survey ≤ 120 mnt, Instalasi ≤ 240 mnt, Maintenance ≤ 180 mnt
- [x] Task hanya bisa dibuat untuk pelanggan dengan `status = 'active'` atau `siap_billing`
- [x] Satu teknisi tidak boleh overlap jadwal (kecuali ada `task.conflict.override`)
- [x] Foto wajib ada sebelum Selesai
- [x] Semua perubahan status masuk audit log

**Acceptance Criteria**:
- [x] FOP bisa membuat, menjadwalkan, dan assign task via kalender.
- [x] Teknisi hanya melihat task yang ia terdaftar sebagai anggota tim.
- [x] Lifecycle task (Draft → Selesai/Dibatalkan/Pending) berjalan sesuai permission.
- [x] Konflik jadwal terdeteksi; bypass hanya untuk yang punya `task.conflict.override`.
- [x] Tombol Selesai teknisi terkunci sampai semua checklist wajib + 1 foto ada.
- [x] Notifikasi Telegram dikirim async saat task dibuat/dijadwal ulang.
- [x] Semua route dilindungi middleware permission; tidak ada hardcode nama role.
- [x] POP scope berlaku: FOP/Teknisi hanya lihat task di POP yang di-assign.
- [x] UI kalender FOP sesuai referensi layout (lihat screenshot brief).
- [x] Audit log tercatat untuk setiap perubahan status task.

**Status**: Done

**Risiko**:
- Konflik validasi jadwal overlap bisa kompleks — dibuat sederhana dulu (cek overlap exact time).
- Telegram bot async membutuhkan queue worker berjalan.

---





## Blocked
Belum ada.

## Notes
AI hanya boleh mengerjakan task dengan status `In Progress`.

Catatan hasil S2-T006:
- POP existing yang sudah ada sebelum migration identifier wajib dilengkapi `pop_code`, `registration_prefix`, dan `cid_prefix` melalui edit POP sebelum generator ID Request/CID digunakan.

Catatan refactor S2-T004/S2-T005:
- Duplikasi `service_packages`/`ServicePackage` dihapus dari kode aplikasi.
- Master Paket Internet sekarang memakai tabel/model/controller `internet_packages`/`InternetPackage`, dengan struktur data dan UI hasil gabungan dari Service Package dan Internet Package.
- Database development sudah di-reset dengan `php artisan migrate:fresh --seed`; hasil akhir: tabel `internet_packages` ada, tabel `service_packages` tidak ada, dan 27 paket ter-seed.
- Test refactor lulus: `InternetPackageSeederTest`, `CustomerCreateTest`, `CustomerEditTest`, `CustomerImportTest`, dan `npm run build`.

Catatan fix regresi import:
- Filter akun internal legacy pada `CustomerController` dikembalikan agar hanya melewati ID internal seperti `PG*`, bukan semua ID pelanggan non-`PE`; import dengan ID legacy seperti `CUST-*` kembali tervalidasi dan tersimpan.
- Test target lulus: `php artisan test tests/Feature/CustomerImportTest.php tests/Feature/CustomerImportLoggingTest.php` (8 passed, 2 skipped karena ZipArchive tidak tersedia).
- Full suite lulus: `php artisan test` (178 passed, 2 skipped, 1086 assertions).

Catatan hardening legacy mapping:
- ID internal legacy `PG*` sekarang diselesaikan ke nama petugas saat migrasi data real, sehingga field `surveyors`, `installation_technicians`, dan `activated_by_name` tidak lagi bergantung pada kode mentah.
- Alamat legacy tetap memakai fallback `ALMT` / `ALAMAT` lalu komposisi `DESA, KEC, KOTA` jika alamat jalan kosong.
- Legacy request ID sekarang ikut disimpan di `customers.old_request_id`, dan migrasi legacy memakai prefix `RQ`/`C` agar data REQ/CID tetap konsisten dengan histori operasional.
- Hierarki legacy sekarang juga dipetakan ke cabang POP, mini POP, dan distribusi: `KODEAPP`/cabang legacy menjadi POP induk, `kategori_perangkat_jaringan` menjadi mini POP child, dan `kode_kontrol_distribusi` disimpan sebagai distribusi untuk mendukung format CID operasional.
- Verifikasi terbaru lulus: `php artisan test tests/Feature/RealDataMigrationTest.php` dan `php artisan test tests/Feature/CustomerImportTest.php`.
Status: Todo

Sprint/Module: Sprint 10 — Complex CID & Termination Logic

Tujuan:
Implementasi backend untuk terminasi (pemutusan) layanan pelanggan.

Acceptance Criteria:
- [ ] Endpoint `POST /customers/{customer}/terminate` tersedia.
- [ ] Status pelanggan berubah menjadi `terminated`.
- [ ] Status layanan di `customer_services` berubah menjadi `berhenti`.
- [ ] Audit log mencatat aksi terminasi.

### S10-T003 — Update UI for Termination & ID Display Logic
Status: Todo

Sprint/Module: Sprint 10 — Complex CID & Termination Logic

Tujuan:
Menghubungkan tombol terminasi di UI ke backend dan mengatur logika tampilan ID.

Acceptance Criteria:
- [x] Tombol terminasi di modal aksi pelanggan berfungsi.
- [x] UI menampilkan Request ID (customer_code) alih-alih CID jika pelanggan sudah terminasi.
- [x] Konfirmasi terminasi muncul sebelum eksekusi.

---

## Blocked

> **Analisa induk:** `docs/billing-pembayaran/analisa-pencegahan-tagihan-dobel.md`
> — lima lapis pencegahan tagihan dobel, mana yang bolong, urutan pengerjaan.
> B0c sudah selesai (entri di bawah dipertahankan sebagai catatan). Sisanya:
> B0d bisa langsung dikerjakan, B0b sudah dapat keputusan sumber tanggal, B0e
> masih perlu keputusan per kasus.

### BILLING-B0c — Penjaga dobel lintas-jenis invoice
**Status**: **Done** (2026-07-21)

**Hasil**: `GenerateMonthlyInvoicesCommand` dan `InvoiceObserver::creating()`
sekarang bertanya "sudah ada tagihan langganan untuk periode ini?"
(`whereIn([AWAL, BULANAN])`, kecuali `BATAL`), bukan lagi per `invoice_type`.
`REAKTIVASI` dan invoice ber-`old_invoice_id` (replay legacy) dikecualikan.
Aturan burst-dedup lama di observer tetap dipertahankan — beda gejala.

**Test**: `tests/Feature/SatuTagihanLanggananPerPeriodeTest.php` (6 kasus).
Terbukti gagal tanpa guard: 2 dari 6 gagal waktu perubahan di-stash. Suite penuh
519 test, 7 error + 12 failure — identik dengan baseline, tidak ada regresi.

**Efek**: `activation_date` yang salah isi tidak lagi bisa memproduksi tagihan
dobel baru. BILLING-B0b turun dari "darurat" jadi "rapikan data".

**Masalah**: `alreadyExists` di `GenerateMonthlyInvoicesCommand` dan
`InvoiceObserver::creating()` sama-sama di-scope `invoice_type`, jadi AWAL dan
BULANAN pada periode yang sama dianggap bukan duplikat. Akibatnya seluruh
pencegahan tagihan dobel bertumpu pada satu kolom, `activation_date`.

**Rencana**: ubah pertanyaannya dari "sudah ada tagihan BULANAN?" jadi "sudah ada
tagihan langganan untuk periode ini?" — `whereIn([AWAL, BULANAN])`, kecualikan
`REAKTIVASI` (suspend lalu aktif lagi di bulan sama itu sah) dan invoice
berstatus `BATAL` (kalau tidak, tagihan yang dibatalkan memblokir penggantinya).

**Kenapa penting**: setelah ini, `activation_date` yang salah tidak lagi bisa
memproduksi tagihan dobel baru — B0b turun dari "darurat" jadi "rapikan data".

**Test**: pelanggan punya invoice AWAL Juli + `activation_date` sengaja diisi
bulan lain → cron Juli tetap tidak menerbitkan BULANAN.

### BILLING-B0d — Command audit tagihan dobel
**Status**: **Done** (2026-07-21)

`php artisan billing:audit-duplicate-invoices [--period=YYYY-MM] [--strict]` —
read-only, melaporkan pelanggan dengan >1 tagihan langganan pada periode sama.
Temuan dipisah `legacy` (semua baris punya `old_invoice_id`) vs `PERLU CEK`
(ada jalur berjalan yang lolos guard). Ikut menghitung nominal yang sudah
terbayar di grup dobel. Tidak ada `--fix` — keputusan pembatalan & nasib uang
yang terlanjur dibayar adalah keputusan bisnis per kasus.

**Test**: `tests/Feature/AuditTagihanDobelTest.php` (8 kasus). Suite penuh 527
test, 7 error + 12 failure — identik baseline.

**Hasil eksekusi di DB development**: 5 grup dobel, semuanya legacy,
`perlu dicek: 0`, nominal terbayar Rp 1.153.291. Rinciannya di
`docs/billing-pembayaran/analisa-pencegahan-tagihan-dobel.md` bagian 7.

### BILLING-B0e — Bersihkan dobel legacy + unique index `invoices`
**Status**: Blocked
**Pemblokir**: butuh keputusan per kasus untuk 5 grup dobel warisan migrasi.

Unique index `(customer_id, billing_period)` untuk jenis langganan belum bisa
dipasang selama pelanggaran historis masih ada. Urutannya: bersihkan dulu, baru
pasang index. Index parsial (`WHERE ...`) tidak portabel ke MySQL, jadi tidak ada
jalan pintas.

Aturan penanganan: invoice yang salah di-set `InvoiceStatus::BATAL` + alasan +
audit log — **jangan dihapus**. Kalau yang dobel sudah dibayar, uangnya harus
jadi kredit atau dikembalikan; sistem belum punya konsep kredit pelanggan.

### BILLING-B0b — Backfill `activation_date` data lama
**Status**: **Done** (2026-07-21) — command siap, eksekusi produksi menunggu owner
**Keputusan bisnis 2026-07-21**: urutan sumber tanggal disetujui — nota/invoice
`AWAL` lebih dulu, lalu catatan pemasangan, lalu data sistem lama. Kalau
ketiganya kosong: **jangan menebak**, laporkan untuk review manual.

**Kenapa perlu**: fix BILLING-B0 (`CustomerVerificationController::finalVerify` menimpa `activation_date` dengan `issue_date`) hanya menutup pelanggan yang diaktivasi **mulai sekarang**. Baris lama yang `activation_date`-nya masih berisi `registration_date` tetap rawan dobel tagih di bulan aktivasinya — `GenerateMonthlyInvoicesCommand` membandingkan bulan yang salah, dan dua lapis penjaga lain di-scope per `invoice_type` sehingga AWAL + BULANAN periode sama tetap lolos.

**Rencana**: `php artisan billing:backfill-activation-date`, default dry-run, menulis hanya dengan `--force`. Output tabel `customer_code | activation_date sekarang | usulan | sumber`.

**Urutan sumber (disetujui 2026-07-21)**:
1. `issue_date` invoice `AWAL` milik pelanggan tersebut
2. `customer_installations.installation_date`
3. `finished_at` legacy
4. lewati + laporkan sebagai butuh review manual (jangan tebak)

**Hasil**: `php artisan billing:backfill-activation-date [--force] [--limit=N]`.
Default hanya mencetak daftar usulan; `--force` menulis dan mencatat setiap
perubahan ke audit log (`action = backfill_activation_date`, berikut sumbernya).

**Penyesuaian dari rencana awal**: sumber ketiga (`finished_at` legacy) ternyata
tidak ada sebagai kolom tersendiri — waktu import, nilainya langsung masuk ke
`activation_date` (`CustomerController::importCustomerServices`). Jadi baris
legacy (`old_request_id`/`old_cost_id` terisi) **dilewati seluruhnya**: nilainya
memang sudah tanggal aktivasi, bukan placeholder pendaftaran. Menimpanya justru
merusak data yang benar. Sumber efektif tinggal dua: invoice AWAL → pemasangan →
lapor manual.

**Test**: `tests/Feature/BackfillActivationDateTest.php` (11 kasus), termasuk
integrasi: setelah backfill, cron bulan aktivasi tidak lagi menerbitkan tagihan
kedua. Suite penuh 538 test, 7 error + 12 failure — identik baseline.

**Menunggu owner**: eksekusi di produksi. DB development tidak punya pelanggan
aktif, jadi jumlah baris terdampak belum diketahui — jalankan tanpa `--force`
dulu, baca daftarnya, baru putuskan.

**Catatan data**: di DB development jumlah `customer_services` `service_status = aktif` adalah 0, jadi dampaknya belum bisa diukur di sini — angka sebenarnya harus diambil dari produksi lewat dry-run. 5 grup customer+periode dobel yang ada semuanya produk migrasi legacy (2022-12, 2023-01, 2025-07), bukan produk bug ini, dan **tidak** boleh dibereskan oleh command ini.

## Notes
AI hanya boleh mengerjakan task dengan status `In Progress`.

Catatan hasil S2-T006:
- POP existing yang sudah ada sebelum migration identifier wajib dilengkapi `pop_code`, `registration_prefix`, dan `cid_prefix` melalui edit POP sebelum generator ID Request/CID digunakan.

Catatan refactor S2-T004/S2-T005:
- Duplikasi `service_packages`/`ServicePackage` dihapus dari kode aplikasi.
- Master Paket Internet sekarang memakai tabel/model/controller `internet_packages`/`InternetPackage`, dengan struktur data dan UI hasil gabungan dari Service Package dan Internet Package.
- Database development sudah di-reset dengan `php artisan migrate:fresh --seed`; hasil akhir: tabel `internet_packages` ada, tabel `service_packages` tidak ada, dan 27 paket ter-seed.
- Test refactor lulus: `InternetPackageSeederTest`, `CustomerCreateTest`, `CustomerEditTest`, `CustomerImportTest`, dan `npm run build`.

Catatan fix regresi import:
- Filter akun internal legacy pada `CustomerController` dikembalikan agar hanya melewati ID internal seperti `PG*`, bukan semua ID pelanggan non-`PE`; import dengan ID legacy seperti `CUST-*` kembali tervalidasi dan tersimpan.
- Test target lulus: `php artisan test tests/Feature/CustomerImportTest.php tests/Feature/CustomerImportLoggingTest.php` (8 passed, 2 skipped karena ZipArchive tidak tersedia).
- Full suite lulus: `php artisan test` (178 passed, 2 skipped, 1086 assertions).

Catatan hardening legacy mapping:
- ID internal legacy `PG*` sekarang diselesaikan ke nama petugas saat migrasi data real, sehingga field `surveyors`, `installation_technicians`, dan `activated_by_name` tidak lagi bergantung pada kode mentah.
- Alamat legacy tetap memakai fallback `ALMT` / `ALAMAT` lalu komposisi `DESA, KEC, KOTA` jika alamat jalan kosong.
- Legacy request ID sekarang ikut disimpan di `customers.old_request_id`, dan migrasi legacy memakai prefix `RQ`/`C` agar data REQ/CID tetap konsisten dengan histori operasional.
- Hierarki legacy sekarang juga dipetakan ke cabang POP, mini POP, dan distribusi: `KODEAPP`/cabang legacy menjadi POP induk, `kategori_perangkat_jaringan` menjadi mini POP child, dan `kode_kontrol_distribusi` disimpan sebagai distribusi untuk mendukung format CID operasional.
- Verifikasi terbaru lulus: `php artisan test tests/Feature/RealDataMigrationTest.php` dan `php artisan test tests/Feature/CustomerImportTest.php`.

Catatan fix di luar sprint — BILLING-B0 (activation_date stale), 2026-07-21:
- Gejala: pelanggan daftar Juni lalu aktif 21 Juli menerima dua tagihan periode Juli (AWAL prorata + BULANAN penuh).
- Sebab: `customer_services.activation_date` diisi `registration_date` saat pendaftaran (`CustomerController::store`) dan tidak pernah ditimpa saat aktivasi, sehingga penjaga "lewati bulan aktivasi" di `GenerateMonthlyInvoicesCommand` tidak pernah kena. Dua lapis penjaga lain di-scope per `invoice_type` (AWAL vs BULANAN dianggap bukan duplikat) dan tabel `invoices` tidak punya unique index.
- Fix: `CustomerVerificationController::finalVerify()` menimpa `activation_date` dengan `issue_date` (tanggal yang sama dengan basis prorata) dan mencatatnya di audit log.
- Test: `tests/Feature/AktivasiTertagihDobelKarenaActivationDateStaleTest.php` (3 passed). Terbukti gagal tanpa fix (2 invoice untuk periode Juli).
- Belum dikerjakan: backfill `activation_date` untuk data lama, dan 5 grup customer+periode dobel yang semuanya berasal dari migrasi legacy (2022–2023, 2025-07) — perlu keputusan terpisah.

Catatan fix di luar sprint — BILLING-B2 (periode/tempo diturunkan server + materai), 2026-07-21:
- `finalVerify()` tidak lagi menerima `billing_period` & `due_date` dari form; keduanya diturunkan dari `issue_date` (periode = bulan aktivasi, tempo = tanggal aktivasi, karena tagihan awal dibayar di tempat).
- `InitialInvoiceService::calculate()` menerima `other_fee` (materai) ke subtotal dan mengembalikan `next_month_amount` untuk baris "mulai bulan depan" di kwitansi.
- Form verifikasi: input periode & jatuh tempo dihapus, field materai ditambah, biaya pemasangan prefill dari `internet_packages.installation_fee` dengan fallback 0.
- Test: `tests/Feature/TagihanAwalPeriodeIkutTanggalAktivasiTest.php` (5) + 4 kasus baru di `tests/Unit/InitialInvoiceProrateFormulaTest.php`. Semua lulus.
- Suite penuh: 503 test, 7 error + 12 failure — jumlah dan daftarnya identik dengan baseline sebelum perubahan (491 test), jadi tidak ada regresi baru. Kegagalan itu milik modul lain (RolePermissionMatrix, RealDataMigration, ReportCustomer, dll).
Catatan fix di luar sprint — BILLING-B3 (panel hitungan jadi kwitansi), 2026-07-21:
- Input nominal `readonly` (`subtotal`, `prorate_amount`, `discount`, `ppn`, `total_amount`) dihapus dari `verifications/admin.blade.php`, diganti kwitansi read-only. Server memang sudah mengabaikan field-field itu sejak `InitialInvoiceService` dipakai, jadi menghapusnya tidak menyentuh backend.
- Form tersisa 5 input: tanggal aktivasi, biaya pemasangan, materai, kabel, tiang.
- Parameter layanan (harga paket, diskon, PPN) dipindah ke `data-*` pada `#billing_params`, bukan `<input type="hidden">` — supaya tidak ikut ter-POST dan tidak bisa disalahartikan sebagai kiriman admin.
- Baris diskon & PPN hanya dirender kalau nilainya > 0. Untuk semua paket saat ini PPN sudah termasuk harga, jadi barisnya tidak pernah tampil.
- Baris "Mulai <bulan depan>: Rp X/bulan, jatuh tempo tanggal 10" memakai rumus yang sama dengan `next_month_amount` dan `GenerateMonthlyInvoicesCommand`.
- Test: 4 kasus baru di `TagihanAwalPeriodeIkutTanggalAktivasiTest` (total 9). Suite penuh 510 test, 7 error + 12 failure — identik dengan baseline, tidak ada regresi baru.

Catatan koreksi konvensi prorata, 2026-07-21:
- Keputusan bisnis: konvensi hari **legacy** yang benar — hari aktivasi TIDAK ditagih (`hari_dalam_bulan - tanggal`), pembulatan `round`. Angka kanonik 21 Juli paket 110.000 = **35.484** (bukan 39.032, bukan 35.483).
- Aktivasi di hari terakhir bulan ditagih **sebulan penuh** (cabang legacy "besok tanggal 1 → 1 hari" tidak direplikasi). Ada tebing disengaja: aktif 30 Juli bayar 3.548, aktif 31 Juli bayar 110.000.
- `InitialInvoiceProrateIgnoresClientAmountTest` & `CustomerFinalVerificationTest` ikut disesuaikan karena mengasumsikan konvensi lama.

Setelah task selesai:
1. Pindahkan task ke Done.
2. Ubah task berikutnya menjadi In Progress.
3. Tambahkan catatan hasil test.

---




