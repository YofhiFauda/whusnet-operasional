# PARTIAL - SEBAGIAN BELUM DI KERJAKAN

# Analisa Fitur Broadcast + Lonceng Notifikasi (Audit Fungsional)

**Tanggal audit:** 2026-09-15
**Konteks:** Lanjutan dari `analisa-status-implementasi-notifikasi.md` (audit "sudah ada apa belum") dan `analisa-realtime-spa-operasional.md` (fondasi arsitektur broadcast pasif). Dokumen ini fokus ke DUA hal yang belum ada di dua dokumen itu:
1. Daftar fitur yang **sudah pakai KEDUANYA sekaligus** — broadcast pasif (auto-refresh board) **DAN** lonceng notifikasi (`AppNotification`, personal, masuk `/notifications`) — bukan cuma salah satu.
2. Rekomendasi `ShouldBroadcastNow` vs `ShouldBroadcast` (temuan inkonsistensi, belum pernah didokumentasikan di dokumen lain).

Metodologi: bukan cuma baca kode — dijalankan test (`php artisan test`, 54 test relevan lolos), dicek `docker compose ps` (infra `horizon`/`reverb`/`redis` live), dicek `queue:failed` (0 broadcast job nyangkut).

---

## 1. Fitur yang sudah pakai Broadcast + Lonceng Notif sekaligus

Broadcast pasif (`ShouldBroadcast`/`ShouldBroadcastNow`, sinyal refetch ke board yang lagi kebuka) beda mekanisme dari lonceng (`AppNotification`, personal + persisten + histori `/notifications`) — lihat `analisa-status-implementasi-notifikasi.md` §6.1. Tabel di bawah cuma titik yang **PUNYA DUA-DUANYA** di alur yang sama:

| Fitur / Alur | Event Broadcast (board) | Channel | Lonceng Notif (`AppNotification`) | Verifikasi |
|---|---|---|---|---|
| Ticketing eskalasi ke NOC/FOP | `TicketQueueUpdated` | `tickets.{popId}` | `TicketService::escalateToNoc()`/`escalateToFop()` → role `noc`/`fop` | Test `TicketNotificationTest` (6 test) — pass |
| Task FOP → Tasks Saya (assign/reschedule/cancel) | `TaskScheduled` | `teknisi.{userId}` | `TaskService::notifyTeam()`/`reassignTeam()` → anggota tim | `TaskBroadcastingTest` — pass |
| Tasks Saya → Task FOP (mulai/selesai) | `TaskStarted`/`TaskCompleted` | `fop.{pop_id}` + `fop-tasks.{pop_id}` + `teknisi.{userId}` | `TaskService::complete()`/`TaskController::notifyTeamMembers()` → tim + role FOP | `TaskBroadcastingTest`, `FopTaskUpdatedBroadcastTest` — pass |
| Verifikasi Pelanggan (reject/revisi/approve) | `CustomerVerificationStatusChanged` | `customers.{popId}` | `CustomerVerificationController::notifyTaskTeam()` → tim task survey/pemasangan | `CustomerVerificationStatusChangedBroadcastTest`, `CustomerVerificationNotificationTest` — pass |
| Payment ditolak | *(tidak ada broadcast board khusus — invoice ikut `InvoiceStatusUpdated`)* | `invoices.{popId}` | `PaymentController::reject()` → pencatat pembayaran | `PaymentRejectNotificationTest` — pass |
| Setoran Kolektor (ajukan/verifikasi/hapus-buku) | `CollectorDepositUpdated` | `App.Models.User.{id}` (kolektor) + worksheet admin | `CollectorDepositService` → notif langsung kolektor/`pop_admin` | `CollectorDepositTest`, `SetoranKolektorRealtimeTest` — pass |
| Setoran Batch Kolektor → Worksheet Admin | `CollectorActivityUpdated` | `collector-activity.{popId}` | `CollectorBatchController::notifyPopAdmins()` → role `pop_admin` | `CollectorBatchNotificationTest`, `AktivitasKasKolektorRealtimeTest` — pass |
| Setoran Kas Admin → Owner/Bank | `CashDepositUpdated` | `cash-deposits` | `CashDepositService` → depositor/verifikator | `CashDepositBroadcastingTest`, `CashDepositVerificationTest` — pass |
| SLA Breach FOP Task | *(tidak ada broadcast board — cuma command terjadwal)* | — | `CheckFopTaskSlaBreach` → role `fop` | `FopTaskSlaBreachNotificationTest` — pass |
| Sinkronisasi unread count antar tab | `NotificationsMarkedRead` | `App.Models.User.{id}` | (ini justru infrastruktur lonceng itu sendiri, bukan fitur produk) | `NotificationUnreadCountSyncTest` — pass |
| Toast pop-up saat lonceng masuk | *(bukan event terpisah — numpang channel lonceng)* | `App.Models.User.{id}` | Semua lonceng di atas otomatis trigger `window.Toast` | `NotificationToastOnArrivalTest` — pass |

**Catatan:** `FopTaskUpdated` (`fop-tasks.{popId}`) dan Task FOP board **TIDAK** dipasangi lonceng — itu murni broadcast pasif tanpa notif personal (dispatch manual di titik switch teknisi/assign tim, disengaja gak lewat Observer biar gak broadcast storm — lihat komentar di `app/Events/FopTaskUpdated.php`). Jadi baris "Task FOP" itu sendiri gak masuk tabel ini — dia cuma broadcast, lonceng-nya numpang di event `TaskStarted`/`TaskCompleted`/`TaskScheduled` yang beririsan.

**Kesimpulan bagian ini:** 8 alur produk beneran punya DUA mekanisme sekaligus (board auto-refresh + lonceng personal), semua ada test dan lolos, infra hidup (Horizon/Reverb/Redis healthy, 0 broadcast job gagal di `queue:failed`). **Fungsional, bukan cuma kode ada.**

---

## 2. `ShouldBroadcastNow` vs `ShouldBroadcast` — rekomendasi

### Kondisi kode saat ini (inkonsisten)

| `ShouldBroadcastNow` (sinkron, gak lewat queue) | `ShouldBroadcast` (antre ke Horizon/redis) |
|---|---|
| `CashDepositUpdated` | `FopTaskUpdated` |
| `CollectorDepositUpdated` | `TicketQueueUpdated` |
| `CollectorActivityUpdated` | `InvoiceStatusUpdated` |
| `NotificationsMarkedRead` | `TaskStarted`/`TaskCompleted`/`TaskScheduled` |
| | `SurveyStarted`/`SurveyCompleted` |
| | `InstallationStarted`/`InstallationCompleted` |
| | `CustomerVerificationStatusChanged` |

Gak ada alasan teknis yang membedakan dua kelompok ini — payload sama-sama kecil (cuma ID/angka, bukan render lengkap), sama-sama utk auto-refresh board. Pembedaan ini keliatan gak sengaja (ditambah beda waktu, beda developer session, gak ada keputusan eksplisit didokumentasikan kenapa grup kanan dibiarkan queued).

### Bandingan

| | `ShouldBroadcastNow` | `ShouldBroadcast` |
|---|---|---|
| Jalur | Sinkron di request/command yang manggil, langsung push ke Reverb | Job `BroadcastEvent` masuk queue (`redis`, connection `default`), diproses worker Horizon |
| Latensi normal | ~0, gak ada jeda | Tergantung worker nganggur — biasanya submilidetik-detik kalau Horizon idle, tapi bisa nunggu kalau ada backlog job lain di queue `default` (bukan queue khusus) |
| **Kalau Horizon mati/lag** | **Tetap kekirim** (gak butuh worker sama sekali) | **DIAM-DIAM GAK KEKIRIM** sampai worker jalan lagi — board freeze tanpa error kelihatan ke user (temuan sesi audit ini) |
| Beban request/command | Nambah waktu response (nunggu push selesai) — tapi push WebSocket itu sendiri ringan/cepat | Gak nambah waktu response (cuma dorong ke queue, balik cepat) |
| Titik gagal | Kalau Reverb server down, dispatch bisa exception di request itu sendiri (perlu ditangani, lihat pola `safelyNotify()` di `CollectorDepositService`) | Kalau Reverb down, job retry/gagal di queue — gak ganggu request asli, gagalnya keliatan di `queue:failed` (bisa dimonitor) |
| Cocok utk | Event kecil, frekuensi rendah-sedang, butuh "beneran sekarang" (sync unread count antar tab, status setoran) | Event volume tinggi/proses berat, atau saat request asalnya udah numpang proses berat lain |

### Rekomendasi: **`ShouldBroadcastNow` lebih cocok utk seluruh event board di repo ini**

Alasan spesifik ke konteks Whusnet Operasional (bukan pendapat generik):

1. **Semua event board di sini payload-nya kecil** (ID doang, bukan render HTML/dataset besar) — beban nambah ke response time praktis gak kerasa, sama kayak `CashDepositUpdated` dkk yang udah kepilih `ShouldBroadcastNow` duluan.
2. **Prinsip yang sama udah dipraktikkan buat `AppNotification`** — `analisa-status-implementasi-notifikasi.md` §6.3 eksplisit nyabut `ShouldQueue` dari `AppNotification` dengan alasan "ketergantungan diam-diam ke availability queue worker buat fitur yang butuh nyampe SEKARANG, lebih mahal ketimbang manfaatnya". Event board (`FopTaskUpdated`, `TicketQueueUpdated`, dst.) punya kebutuhan SAMA PERSIS — kenapa gak dapet treatment sama, gak ada alasan yang kekode/terdokumentasi.
3. **Risiko silent failure lebih mahal daripada risiko block request.** Kalau board FOP gak update, ini keliatan kayak bug produk ("aplikasi rusak") — bukan kegagalan yang gampang dicurigai user ke arah "Horizon mati". Sebaliknya, delay beberapa milidetik di request karena push sinkron nyaris gak kerasa dan gak nyembunyiin masalah.
4. **Volume transaksi rendah** — sama kayak alasan Gudang sengaja skip broadcast (`WarehouseReportController` komentar: "volume gudang gak sebanding kompleksitas broadcast"), tapi kebalik: di sini volumenya JUSTRU cukup rendah buat sinkron gak jadi masalah performa (ticketing/task/verifikasi = puluhan-ratusan aksi/hari per POP, bukan ribuan/detik).

**Kapan `ShouldBroadcast` (queued) tetap lebih baik** — dicatat biar gak asal ubah semua: event yang genuinely berat (payload butuh query/agregasi mahal sebelum di-broadcast, atau frekuensi tinggi bgt spt broadcast storm). Gak ada satupun event board yang match kriteria itu saat ini di repo — semua `broadcastWith()` yang dicek cuma ambil ID/angka simpel.

**Yang JANGAN ikut diubah:** job yang sifatnya bukan realtime-UI (mis. `SendWebhookOutboxJob`, notifikasi Telegram lewat `TelegramBotService`) — itu emang harus queued karena manggil API eksternal lambat, beda kelas kebutuhan dari broadcast WebSocket internal.

### ✅ DIEKSEKUSI 2026-09-15 — keputusan user, kriteria eksplisit

User memutuskan aturan pembagian jelas:
- **`ShouldBroadcastNow`** → event yang mengubah indikator status, aksi papan kerja (Kanban/Task/Ticket), lonceng notifikasi, atau dipicu langsung klik tombol pengguna.
- **`ShouldBroadcast` (queued)** → event hasil proses sistem latar belakang (export excel selesai, bulk update status, daily summary report).

**Diverifikasi dulu tiap titik dispatch** (bukan asumsi) sebelum ubah — semua 11 event ini dispatch dari controller/observer yang dipicu 1 aksi tombol per pelanggan/task/tiket, BUKAN loop bulk:
- `FopTaskUpdated` — `FopTaskController` (switchTechnician/assignToTeam/update), payload cuma ID.
- `TicketQueueUpdated` — `TicketService` (create/close/cancel/escalate*), payload cuma `popId`.
- `InvoiceStatusUpdated` — `Invoice::recalculateFromPayments()`, dipicu 1 aksi catat/verifikasi pembayaran.
- `TaskStarted`/`TaskCompleted` — tombol Mulai/Selesai teknisi.
- `TaskScheduled` — assign/reschedule/cancel tim task oleh FOP.
- `SurveyStarted`/`SurveyCompleted` — `CustomerSurveyController`.
- `InstallationStarted`/`InstallationCompleted` — `CustomerInstallationController`.
- `CustomerVerificationStatusChanged` — `CustomerObserver::updated()` pas kolom `status` dirty. **Dicek khusus:** import massal (`CustomerController::confirmImport()`) pakai `Customer::create()` (bukan `update()`), jadi TIDAK nyentuh observer ini — aman gak jadi broadcast storm sinkron pas import ratusan baris. Gak ketemu satu pun command/job yang mass-update status Customer per-model dalam loop.

**Perubahan:** `implements ShouldBroadcast` → `implements ShouldBroadcastNow` + import `Illuminate\Contracts\Broadcasting\ShouldBroadcastNow` di 11 file event di atas, plus komentar penjelas alasan di tiap file (biar keputusan ini gak diam-diam dibalik lagi ke depan tanpa alasan baru — ikut gaya komentar argumentatif repo ini).

**Verifikasi:**
- `vendor/bin/pint --dirty --format agent` → `passed`.
- 45 test terkait (`FopTaskUpdatedBroadcastTest`, `InvoiceStatusUpdatedBroadcastTest`, `CustomerVerificationStatusChangedBroadcastTest`, `TaskBroadcastingTest`, `TicketNotificationTest`, `CustomerVerificationNotificationTest`, `FopTaskSlaBreachNotificationTest`, `NotificationToastOnArrivalTest`) → **45 passed**, 0 gagal.
- Full suite `php artisan test` dijalankan sesudahnya — hasil di §5.

**Belum diubah (sengaja, gak masuk kriteria user):** `webhooks` (`SendWebhookOutboxJob`), `MatchPaymentReceipt`, `SendTaskNotificationJob`, `SendCustomerActivationNotification` — ini job async yang manggil proses eksternal/berat (API pihak ketiga, OCR kwitansi), bukan broadcast WebSocket UI, beda kelas kebutuhan (tetep `ShouldQueue` job biasa, bukan event `ShouldBroadcast`/`ShouldBroadcastNow`). Command terjadwal (`CheckFopTaskSlaBreach`, `GenerateMonthlyInvoicesCommand`, dll) juga gak disentuh — itu justru contoh nyata "proses sistem latar belakang" yang kriteria user bilang harus TETAP `ShouldBroadcast`/job biasa, bukan `Now`.

---

## 3. Ringkasan Analisa Sesi Ini (rekap dari 2 turn percakapan sebelumnya)

### 3.1 Analisa Realtime per Alur (peta lengkap 16 alur user-defined, turn 1)

Cek kode langsung: Events, Observer, `routes/channels.php`, blade Echo. **Legenda:** ✅ full (broadcast board + lonceng notif) · 🟡 sebagian · ❌ nihil, pull manual/reload.

| # | Alur | Status | Bukti |
|---|---|---|---|
| 1 | Antrean Survey → Task FOP | 🟡/❌ | Task `SURVEY` auto-create di `CustomerWorkflowService::transition()` (`Task::create()` polos, bukan lewat `TaskService`) — **tidak** fire event apapun, tidak ada notif. Kalau masuknya lewat `TicketService::escalateToFop()` baru ✅ (broadcast `FopTaskUpdated`→`fop-tasks.{popId}` + notif role `fop`). Beda jalur, beda nasib. |
| 2 | Verif & Pemasangan → Task FOP | 🟡 | Task `PSB` auto-create juga raw `Task::create()`, gak fire broadcast. Tapi ada patch (`CustomerVerificationController::processToTeam()`) yang notif role `fop` (lonceng) khusus utk task pemasangan baru — jadi lonceng jalan, **board Task FOP sendiri gak auto-refresh** (gak ada `TaskScheduled`/`FopTaskUpdated` di titik ini). |
| 3 | Pelanggan Putus (Ambil Alat/DEAC) → Task FOP | ❌ | `Task::create()` di `CustomerController.php:591/612` — nihil event, nihil notif. |
| 4 | Task FOP → Tasks Saya | ✅ | `TaskScheduled`→`PrivateChannel('teknisi.{userId}')` (assign/reschedule/cancel) + `AppNotification`. `tasks/own.blade.php` listen langsung. |
| 5 | Tasks Saya → Task FOP (status teknisi) | ✅ | `TaskStarted`/`TaskCompleted`→`fop.{pop_id}` + `fop-tasks.{pop_id}` + `teknisi.{userId}` sekaligus, plus notif tim. Full. |
| 6 | Gudang → Cabang Gudang (transfer/approval) | ❌ | **Sengaja by design** — komentar eksplisit di kode: "Realtime SENGAJA SKIP, volume gudang gak sebanding kompleksitas broadcast". Nol `notify`/`broadcast` di seluruh controller Warehouse. |
| 7 | Cabang Gudang → Teknisi (penyerahan barang) | ❌ | `WarehouseCustodyController`/`WarehouseIssueController` — nol notif. |
| 8 | Ticketing → Task FOP | ✅ | `escalateToFop()`: notif role `fop` + `FopTaskUpdated`→`fop-tasks.{popId}`. |
| 9 | Task → Ticketing (task selesai) | ❌ | `TaskService::complete()` gak nyentuh balik ke pembuat tiket/`TicketQueueUpdated` sama sekali — pembuat tiket gak dikabari FOP kelar kerjain. |
| 10 | Kolektor → Worksheet Admin (setoran masuk) | ✅ | `CollectorDepositUpdated` (diajukan) + notif `pop_admin`. |
| 11 | Worksheet Admin → Kolektor (setoran diproses) | ✅ | `verify()`/`writeOff()` dispatch `CollectorDepositUpdated` + notif langsung ke kolektor (`App.Models.User.{id}`). |
| 12 | Worksheet Admin → Setoran Kas | ✅ | `CashDepositUpdated`→channel `cash-deposits` + notif. |
| 13 | Worksheet Admin → Kolektor (pelanggan harus ditagih) | ❌ | `CollectorWorklistController` (assign pelanggan ke kolektor) — nol `notify`/`broadcast` ditemukan. |
| 14 | Verif & Pemasangan → Verifikasi Busdev | 🟡 | Event `CustomerVerificationStatusChanged` fire tiap `status` berubah (termasuk masuk antrean BD), TAPI `business-development-verifications/index.blade.php` **gak listen** apapun — cuma `verifications/queue.blade.php` (CS) yang pasang Echo. Board BD murni pull. Lonceng cuma nyala ke pembuat **setelah** BD selesai verifikasi, bukan pas pelanggan baru masuk antrean. |
| 15 | Pelanggan → Tagihan (baru terbit) | ❌ | `InitialInvoiceService`, `InstallationFeeInvoiceService`, `GenerateMonthlyInvoicesCommand` — nol `notify`. |
| 16 | Tagihan → Pembayaran | ✅ | `InvoiceStatusUpdated`→`invoices.{popId}`, dipakai halaman invoice/payment. |

**Ringkas status:**
- ✅ full (broadcast+lonceng): #4 Task FOP→Tasks Saya, #5 Tasks Saya→Task FOP, #8 Ticketing→Task FOP, #10 Kolektor→Worksheet Admin, #11 Worksheet Admin→Kolektor, #12 Worksheet Admin→Setoran Kas, #16 Tagihan→Pembayaran.
- 🟡 sebagian: #1 Antrean Survey→Task FOP (tergantung jalur masuk), #2 Verif&Pemasangan→Task FOP (lonceng ada, board gak refresh), #14 Verif&Pemasangan→Verifikasi Busdev (event fire, gak ada yang listen).
- ❌ nihil: #3 Pelanggan Putus→Task FOP, #6 Gudang→Cabang Gudang (sengaja by design), #7 Cabang Gudang→Teknisi (sengaja by design), #9 Task→Ticketing (task selesai gak balik ke pembuat tiket), #13 Worksheet Admin→Kolektor (pelanggan harus ditagih), #15 Pelanggan→Tagihan (invoice baru terbit gak ada notif).

**Ringkasan temuan (dari analisa awal, sebelum verifikasi fungsional §3.2):**

Infra Reverb+Echo udah solid dan dipakai konsisten (private channel per POP/user, `EffectiveAccessService` gate, `toOthers()` anti-echo-balik) — bukan masalah kapabilitas platform. Gap-nya di titik pemanggilan yang belum ditambah, konsisten satu pola: setiap kali entity dibuat lewat `Model::create()` polos (bukan lewat Service yang udah punya notify), dia gak kebagian broadcast/notif.

**Gap konkret yang belum digarap** (urut prioritas dampak operasional, status per 2026-09-15):
1. Task SURVEY/PSB/DEAC auto-create (flow 1,2,3) — gak notif FOP, gak broadcast board.
2. Task selesai gak balik ke pembuat tiket (flow 9).
3. Assign pelanggan ke kolektor (flow 13) — kolektor gak tau ada tagihan baru.
4. Board Verifikasi BD gak listen event yang sebenarnya udah ada (flow 14) — tinggal tambah Echo di blade-nya, event-nya udah jalan.
5. Invoice baru terbit (flow 15) — pelanggan/CS gak dikabari.
6. Gudang (flow 6,7) — ini **keputusan sadar**, bukan bug, kalau mau diaktifkan itu perubahan scope, bukan fix.

### 3.2 Verifikasi fungsional (turn 2)

- 54 test terkait broadcast/notif dijalankan, semua **pass**.
- `broadcastAs()` custom vs default dicek satu-satu cocok sama listener Echo di blade (titik depan `.` dipakai konsisten cuma pas `broadcastAs()` di-override).
- Payload event (`fop_task_id`, dll) dicek cocok sama field yang diakses JS.
- Infra live dicek via `docker compose ps` — `horizon`, `reverb`, `redis` semua **healthy** (uptime 3 jam).
- `queue:failed` — 0 job broadcast nyangkut (5 gagal semuanya `SendWebhookOutboxJob`, gak terkait).
- **Temuan risiko** (bagian 2 dokumen ini): mayoritas event board pakai `ShouldBroadcast` (queued lewat Horizon) sementara grup Kolektor/CashDeposit/NotificationsMarkedRead pakai `ShouldBroadcastNow` (sinkron) — inkonsistensi tanpa alasan teknis, bikin sebagian board rawan silent-stale kalau Horizon down, gak kejadian saat ini (infra sehat) tapi arsitekturnya rapuh.

---

## 4. Referensi

- `docs/plan/analisa-status-implementasi-notifikasi.md` — audit lonceng notifikasi + histori keputusan `ShouldQueue` dicabut dari `AppNotification`.
- `docs/plan/analisa-realtime-spa-operasional.md` — fondasi broadcast pasif.
- `app/Events/*.php` — seluruh event broadcast, lihat `implements ShouldBroadcast`/`ShouldBroadcastNow`.
- `routes/channels.php` — otorisasi tiap channel.
- Test: `TicketNotificationTest`, `TaskBroadcastingTest`, `FopTaskUpdatedBroadcastTest`, `CustomerVerificationStatusChangedBroadcastTest`, `CustomerVerificationNotificationTest`, `PaymentRejectNotificationTest`, `CollectorDepositTest`, `SetoranKolektorRealtimeTest`, `CollectorBatchNotificationTest`, `AktivitasKasKolektorRealtimeTest`, `CashDepositBroadcastingTest`, `CashDepositVerificationTest`, `FopTaskSlaBreachNotificationTest`, `NotificationUnreadCountSyncTest`, `NotificationToastOnArrivalTest`, `InvoiceStatusUpdatedBroadcastTest`.
