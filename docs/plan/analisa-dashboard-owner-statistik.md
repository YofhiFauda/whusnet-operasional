# BELUM DI IMPLEMENTASIKAN

# Analisa Dashboard Owner — Statistik Pelanggan, Ticketing, FOP Task, Pembayaran, Penagihan

Status: **Analisa awal, belum eksekusi.** Belum masuk sprint aktif — perlu entry task baru di `docs/TASKS.md` sebelum dikerjakan.

## Konteks

Owner minta dashboard (`/`, `DashboardController@index`, `dashboard.blade.php`) menampilkan perhitungan & statistik lengkap lintas modul: Pelanggan, Ticketing, FOP Task, Pembayaran, Penagihan. Analisa ini membandingkan isi dashboard saat ini vs kebutuhan, lalu usulan penambahan.

## Kondisi saat ini (`DashboardController::index()`)

| Modul | Yang sudah ada |
|---|---|
| Pelanggan | Total, aktif, belum lengkap (`draft`/`perlu_dilengkapi`), siap_billing, distribusi per POP |
| Penagihan | Total tagihan (periode filter), tagihan belum lunas (`remaining_amount`), jumlah + list invoice jatuh tempo (top 10) |
| Pembayaran | Total pembayaran valid (periode filter) |
| Ticketing | **Tidak ada** |
| FOP Task | **Tidak ada** (statistik FOP ada, tapi di `/fop` — `FopDashboardController`, khusus role FOP: antrian survey, overdue survey/install, task hari ini, workload teknisi. Bukan untuk owner.) |

Filter yang sudah jalan: `pop_id`, `period_from`/`period_to` (bulan). Semua query lewat `applyUserScope()` — wajib dipertahankan di penambahan manapun (lihat CLAUDE.md — larangan keras #3: query tanpa POP scope).

`docs/dashboard/README.md` sudah **outdated** — tidak menyebut `pop_id`/`period_from`/`period_to`/`data_completeness_status`, masih pakai kolom `status='active'` versi lama tanpa filter periode. Perlu update kalau task ini dieksekusi.

## Gap & usulan penambahan

Keputusan lokasi: **section baru ditambahkan ke dashboard existing** (`/`), bukan halaman terpisah.

| Section | Metric usulan | Sumber data |
|---|---|---|
| Ticketing | Total per `TicketBucket` (masuk/diproses/selesai/dibatalkan) | `Ticket::applyUserScope()`, group by bucket mapping |
| Ticketing | Breakdown tipe tiket, avg `handling_sla_hours`, jumlah tiket overdue handling SLA | tabel `tickets` |
| FOP Task | Total per `TaskStatus` (draft/terjadwal/in_progress/selesai/dibatalkan/pending) | `Task::applyUserScope()` |
| FOP Task | Breakdown `TaskType`, SLA pengerjaan (durasi teknisi selesaikan task), overdue SLA pengerjaan | pola `TaskService`/`TaskReport` — **bukan** `PackageSlaSetting` (itu SLA paket, beda konsep, lihat CLAUDE.md § SLA) |
| Penagihan | Breakdown count per `InvoiceStatus` (belum_dibayar/sebagian/lunas/batal), tren bulanan | `Invoice::applyUserScope()` |
| Pembayaran | Breakdown per `PaymentStatus`, tren bulanan | `Payment::applyUserScope()` |

Semua metric tambahan wajib:
- Pakai `applyUserScope()` (POP scope) — tidak terkecuali.
- Hormati filter `pop_id` + `period_from`/`period_to` yang sudah ada, konsisten dengan section Pelanggan/Penagihan existing.
- Query tambahan ikut pola private method existing (`scopedCustomerQuery()`, `scopedInvoiceQuery()`, `scopedPaymentQuery()`) → tambah `scopedTicketQuery()`, `scopedTaskQuery()` sejenis.

## Breakdown detail per section (formula konkret)

Ticketing modul sedang dikerjakan paralel (file uncommitted: `TicketHandler`, `TicketHandlingStatus`, migrasi `add_handler_and_status_to_tickets_table`, `TicketIssueCategory`) — breakdown ini WAJIB ikut model terbaru, bukan asumsi lama.

### Ticketing

Tiket **tidak punya kolom status sendiri**. Status ditentukan `Ticket::bucket()` (`app/Models/Ticket.php:198`) dengan logic dua fase:

1. Selama `handler` masih `helpdesk`/`noc` (belum ke FOP) → bucket ditentukan dari `handling_status` (`open`/`closed`), BUKAN dari `TaskStatus`.
2. Begitu `handler` jadi `fop` → bucket balik ke turunan `FopTask::status` (`TaskStatus`), lewat `TicketBucket::statuses()` mapping:
   - `MASUK` = `[DRAFT]`
   - `DIPROSES` = `[TERJADWAL, IN_PROGRESS, PENDING]` (termasuk "Lapor Nanti" = `PENDING` + `report_deferred=true`, bukan status terpisah)
   - `SELESAI` = `[SELESAI]`
   - `DIBATALKAN` = `[DIBATALKAN]` + tiket orphan (`fop_task_id` null padahal `handler=fop`, karena FopTask-nya kehapus)

**Jangan** hitung bucket count pakai raw `groupBy` kolom — **wajib** pakai `Ticket::scopeInBucket()` yang sudah ada (`app/Models/Ticket.php:133`) per bucket, atau replikasi logic yang sama persis. Hitung count 4x (`TicketBucket::cases()`) pakai scope itu + `applyUserScope()`.

Metric usulan:
| Metric | Cara hitung |
|---|---|
| Total per bucket (4 angka) | `Ticket::applyUserScope()->scopeInBucket($bucket)->count()` per bucket |
| Breakdown `issue_category_id` | join `ticket_issue_categories`, group by nama kategori |
| Avg handling SLA | `fop_tasks.handling_sla_hours` — rata² dari tiket yg sudah py FopTask; **bukan** SLA pengerjaan teknisi (beda konsep, lihat CLAUDE.md § SLA) |
| Tiket overdue handling | tiket `handler != fop` dan `created_at` + `handling_sla_hours` (dari issue category / default type) < now |

### FOP Task

| Metric | Cara hitung |
|---|---|
| Total per `TaskStatus` (6 case) | `Task::applyUserScope()->where('status', ...)->count()` — hormati nuance PENDING+report_deferred kalau mau pisah "Pending" vs "Lapor Nanti" |
| Breakdown `TaskType` (7 case) | group by `task_type`; ingat `autoOnlyValues()` (SURVEY/PSB/DEAC) vs manual — kalau owner mau lihat asal task (auto-sync vs manual), pisahkan |
| SLA pengerjaan (durasi teknisi) | selisih `completed_at` - `started_at`/`scheduled_at`, dibandingkan `TaskType::slaMinutes()`. Pola hitung ada di `TaskService`/`TaskReport` — reuse, jangan reimplement |
| Overdue SLA pengerjaan | task `status` aktif (`isActive()` = TERJADWAL/IN_PROGRESS) dan `scheduled_at` + `slaMinutes()` < now |
| Overdue handling (assign) | task `DRAFT` dan `created_at` + `handlingSlaHours` (dari `PackageSlaSetting` atau `TaskType::defaultHandlingSlaHours()`) < now — ini yang dipakai `FopDashboardController` (`overdue_survey`, `overdue_installation`), bisa reuse query pattern-nya |

### Penagihan (Invoice)

| Metric | Cara hitung |
|---|---|
| Breakdown count per `InvoiceStatus` (4 case: belum_dibayar/sebagian/lunas/batal) | `Invoice::applyUserScope()->groupBy('invoice_status')` dalam rentang `billing_period` filter |
| Tren bulanan | group `billing_period`, sum `total_amount` — mirip pola tren registrasi pelanggan yang ada di versi lama dashboard |
| Total belum lunas (sudah ada) | tetap pertahankan, exclude LUNAS+BATAL, sum `remaining_amount` |

### Pembayaran (Payment)

| Metric | Cara hitung |
|---|---|
| Breakdown per `PaymentStatus` (3 case: pending/valid/ditolak) | `Payment::applyUserScope()->groupBy('payment_status')` dalam rentang `payment_date` filter |
| Tren bulanan | group by bulan `payment_date`, sum `amount` where `payment_status = VALID` |
| Total valid (sudah ada) | tetap pertahankan |

Catatan guard: `PaymentObserver::creating()` nolak `amount <= 0` — kalau breakdown pakai data legacy lama, pastikan tidak ke-exclude/ke-include salah karena baris placeholder BAYAR=0 (harusnya udah gak ada berkat guard, tapi cek data seed/legacy dulu kalau angka aneh).

## Yang perlu diputuskan sebelum eksekusi

1. Task ini belum ada di `docs/TASKS.md` — sprint aktif saat ini S8.10-T003 (FOP Notification Dashboard), topik beda. Perlu entry task baru (nomor sprint, acceptance criteria) sebelum coding.
2. Definisi "overdue SLA pengerjaan" untuk FOP Task perlu dikonfirmasi ke `TaskService`/`TaskReport` — pastikan tidak duplikasi logic SLA yang sudah ada di tempat lain.
3. Perlu test baru (`DashboardController` sudah tersedia test existing? cek dulu) untuk tiap section baru — sesuai aturan "fitur/perbaikan baru wajib ada test".
4. `docs/dashboard/README.md` perlu direvisi bareng implementasi karena sudah tidak sinkron dengan kode saat ini.
