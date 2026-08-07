# Analisa Status Implementasi In-App Notification (Audit Kondisi Aktual)

**Tanggal audit:** 2026-08-05 · **Update implementasi:** 2026-08-06
**Konteks:** Audit kode aktual — bukan rencana. Untuk kebutuhan/rencana ke depan lihat [`analisa-in-app-dan-push-notifikasi.md`](analisa-in-app-dan-push-notifikasi.md); dokumen ini memotret apa yang **sudah** jalan di kode saat ini, supaya rencana di dokumen itu bisa dicek progress-nya terhadap kondisi riil.

> **Status per 2026-08-06:** Sebagian besar temuan §5 dan §6 di bawah **SUDAH digarap** — Ticketing (5 transisi), Verifikasi Pelanggan (reject/revisi), Payment reject, retensi `database_notifications`, dan channel `fop.{pop_id}` legacy. Lihat §8 buat detail & yang masih tersisa.

---

## 1. Apakah In-App Notification Sudah Berfungsi?

**Ya, berfungsi.** Stack: Laravel Notification (`App\Notifications\AppNotification.php`) dengan `via()` = `['database', 'broadcast']`, broadcast lewat Laravel Reverb ke private channel `App.Models.User.{id}`, di-listen client via Echo (`.notification()`) di `resources/views/components/notification-dropdown.blade.php`.

Komponen yang sudah ada:
- Dropdown lonceng navbar — real-time push (unread badge + list), listen langsung dari Echo tanpa polling.
- Halaman `/notifications` (`NotificationController@index`) — list lengkap + filter tanggal/type/user_id, dibatasi POP scope (`EffectiveAccessService`).
- Mark as read / unread / read-all (`NotificationController::markAsRead/markAsUnread/markAllAsRead`).
- `NotificationType` enum (`info`/`error`/`warning`/`success`) — type-safe sejak fix di `ANALISA_REDUNDANSI_LOGIC.md` §9, sebelumnya string literal bebas.

---

## 2. Fitur yang Sudah Menerapkan In-App Notification

Semua ada di modul **Task Teknisi / FOP** — belum menyentuh modul lain:

| Call site | Trigger | Penerima |
|---|---|---|
| `TaskService::complete()` (`app/Services/TaskService.php:217`) | Task selesai, laporan diunggah | Semua user role FOP di POP task tsb |
| `TaskService::notifyTeam()` (`:506`, dipanggil dari `:73,108,307`) | Task dibuat / direschedule / dibatalkan | Anggota tim task |
| `TaskService::reassignTeam()` (`:405,416,431`) | Assign teknisi baru / unassign lama / jadwal berubah utk anggota lain | Teknisi terkait |
| `TaskController::notifyTeamMembers()` (`:609`, dipanggil dari `:415,441,488,511,524`) | FOP reject survey/instalasi, set pending, approve survey | Anggota tim task |
| `FopTaskController::notifySwitchedTechnician()` (`:940`) | Switch teknisi dalam tim FOP | Teknisi yang keluar/masuk tim |

Total 7 titik pemanggilan `AppNotification`, semuanya seputar siklus Task ↔ FopTask.

---

## 3. Dampak Nyata

- Teknisi & FOP dapet update real-time (assign, reassign, reject, switch, task selesai) tanpa refresh manual — mengurangi ketergantungan pada Telegram bot (`TelegramBotService`, sifatnya opsional/pelengkap, butuh `telegram_chat_id` di-setup dulu).
- Riwayat notifikasi tersimpan di DB (`database_notifications`), bisa ditelusuri lewat `/notifications` — ada jejak audit ringan siapa dikasih tau apa & kapan.
- Terbatas ke modul Task/FOP — modul lain (Ticketing, Verifikasi, Billing) sepenuhnya bergantung pengecekan manual (buka dashboard/list satu-satu).

---

## 4. Yang Perlu Dioptimasi

1. **Query dropdown tiap page load** — `notificationDropdown()` di blade manggil `auth()->user()->notifications()->take(10)->get()` + `unreadNotifications()->count()` setiap render layout (dipakai di semua halaman via `layouts/app.blade.php`). Belum ada cache; worth dipertimbangkan cache count ber-TTL pendek kalau traffic naik, mirip pola cache permission di `EffectiveAccessService`.
2. **Tidak sinkron antar tab** — `unreadCount`/`notifications` state cuma di-init sekali per page load dari Alpine `x-data`. Mark-read di tab A tidak mendorong update ke tab B (masing-masing state lokal, gak ada broadcast balik utk event read).
3. **`NotificationController::index()` scope logic** (baris 27-50) — percabangan ganda `task.view.all` di dalam-luar `if scopeType !== ALL_POP` agak berulang. Kalau nambah modul consumer notifikasi baru, rawan salah tempel logic; kandidat diringkas jadi satu helper di `EffectiveAccessService`.
4. **Tidak ada fallback offline** — kalau user gak online pas broadcast terjadi, notifikasi cuma nongkrong di DB nunggu user buka app. By design utk internal tool, tapi kalau modul Ticketing/Billing (butuh respons cepat, lihat §5) masuk, pertimbangkan push (Telegram) sebagai pelengkap wajib bukan opsional — sudah direncanakan di `analisa-in-app-dan-push-notifikasi.md` §4.

---

## 5. Fitur yang Butuh In-App Notification Tapi Belum Diimplementasikan

Berdasarkan sudah adanya rencana lengkap di `analisa-in-app-dan-push-notifikasi.md`, gap implementasi terhadap kode aktual:

| Modul | Method/Titik | Kondisi Sekarang |
|---|---|---|
| **Ticketing** | `TicketService::escalateToNoc()`, `escalateToFop()`, `close()`, `cancel()`, `returnToHelpdesk()` | ✅ **Selesai 2026-08-06** — lihat §8.1. |
| **Verifikasi Pelanggan** | `CustomerVerificationController` (`reject()`, `revisi()`) | ✅ **Selesai 2026-08-06** — lihat §8.2. `processToTeam()`/`finalVerify()` juga sudah kebagian notif SUCCESS (bukan dari §5 asli, ekstra sekalian). |
| **Billing & Pembayaran** | `PaymentController::reject()` | ✅ **Selesai (sebagian) 2026-08-06** — lihat §8.3. Batch kolektor (`CollectorBatchController::store()`) & "Finance Pusat"-wide notif **BELUM** — gak ada role/permission yang jelas mewakili "Finance Pusat" di RBAC saat ini (§8.3 penjelasan kenapa sengaja gak ditebak). |
| **NOC Dashboard / SLA Breach** | Handling SLA FOP (`handling_sla_hours`) kelewat | Belum — butuh scheduled command baru (cron), beda kelas kerja dari notif "reaktif ke aksi user" yang lain di sini. |
| **Customer Lifecycle** | Transisi besar `WorkflowTransition` (mis. jadi `active`, `suspended`, `terminated`) | Belum. |
| **Import Data Massal** | `CustomersImport`/batch import selesai | Belum. |

**Sisa kalau dilanjutkan:** setoran kolektor batch (perlu keputusan RBAC dulu — role/permission mana yang mewakili "Finance Pusat") → SLA breach cron (kerja baru, bukan nambah notify ke aksi yang sudah ada) → Customer Lifecycle & Import massal (post-MVP sesuai catatan scope di dokumen rencana §5).

---

## 6. Temuan Tambahan (Analisa Lanjutan)

Penelusuran lanjutan nemu nuansa yang bikin gambaran §5 kurang lengkap kalau dibaca "modul X = nol realtime sama sekali". Beberapa modul justru **sudah** punya infrastruktur realtime — cuma bukan lewat `AppNotification`/lonceng, jadi gampang keliru dianggap gak ada sama sekali.

1. **Broadcast pasif (auto-refresh) vs notifikasi aktif (lonceng) — dua mekanisme beda, jangan disamakan.**
   Ticketing, Verifikasi, Invoice, dan papan FOP Task **sudah** broadcast realtime via `ShouldBroadcast` event murni (bukan Laravel Notification): `TicketQueueUpdated` (`tickets.{popId}`), `CustomerVerificationStatusChanged` (`customers.{popId}`), `InvoiceStatusUpdated` (`invoices.{popId}`), `FopTaskUpdated` (`fop-tasks.{popId}`) — didaftarkan di `routes/channels.php`. Sengaja gak bawa payload lengkap, cuma sinyal "refetch", dikonsumsi Alpine state di halaman yang lagi kebuka (lihat `docs/plan/analisa-realtime-spa-operasional.md`).
   **Bedanya krusial dari lonceng notifikasi:** event ini cuma nyala kalau user **lagi buka halaman itu juga** — gak ada unread badge, gak masuk riwayat `/notifications`, gak nyusul kalau user pindah halaman/belum login. Jadi klaim "Ticketing/Billing/Verifikasi belum ada realtime" di §5 perlu diperhalus: yang belum ada itu **notifikasi personal yang persisten & actionable** (lonceng + histori), bukan realtime sama sekali. Worth dibedain di rencana implementasi biar gak dikerjain dua kali dari nol.

2. ✅ **DONE 2026-08-06 — Retensi `database_notifications`.** `app/Console/Commands/PruneReadNotifications.php` (`notifications:prune-read {--days=90}`) hapus notif yang `read_at` udah lewat N hari — notif BELUM dibaca sengaja gak disentuh seberapa pun lamanya. Dijadwalkan `dailyAt('00:30')` di `routes/console.php`.

3. **Ketergantungan diam-diam ke queue worker.** (belum digarap)
   `AppNotification implements ShouldQueue` — dikirim lewat queue (`QUEUE_CONNECTION`), bukan sync. Kalau Horizon down/nge-hang, notifikasi ketunda **tanpa ada alert ke siapa pun** (gak ada dead-letter monitoring khusus notifikasi) — beda dari kegagalan yang kelihatan di Horizon dashboard by design tapi gak ada yang mantau proaktif. Worth cek apakah `horizon:snapshot` + alert LongWaitDetected (skill `configuring-horizon`) sudah nyakup queue notifikasi ini juga.

4. ✅ **DONE 2026-08-06 — Channel `fop.{pop_id}` diseragamkan ke `EffectiveAccessService`.** `routes/channels.php` — otorisasi channel `fop.{pop_id}` (dipakai `/fop/dashboard`) sekarang pakai `hasAllPopAccess()`/`getAllowedPopIds()`, sama pola dengan 4 channel lain di file yang sama. Sebelumnya `$user->pops()->where(...)->exists()` (jalur legacy, gak paham `pop_tree` — CLAUDE.md § POP Scope).

---

## 8. Detail Implementasi (2026-08-06)

Realisasi dari §5/§6 di atas, atas permintaan lanjutan user. Semua diverifikasi test baru + regresi suite terkait (`Ticket*`, `*Verification*`, `Payment*`, `*Broadcast*`, `NocWorksheetTest` — 562 passed, 4 gagal pre-existing tak terkait, lihat catatan di bawah §8.5).

### 8.1 Ticketing — `app/Services/TicketService.php`

| Method | Penerima | Type | Catatan |
|---|---|---|---|
| `escalateToNoc()` | Semua user role `noc` di POP tiket | INFO | Role-wide — gak ada langkah "terima" (ADHOC-06), lonceng ini satu-satunya sinyal personal NOC. |
| `escalateToFop()` | Semua user role `fop` di POP FopTask | INFO | Titik terminal Ticketing — FOP baru tau ada kerjaan lewat sini. |
| `close()` | Pembuat tiket (`created_by`), skip kalau sama dgn actor | SUCCESS | |
| `cancel()` | Pembuat tiket, skip kalau sama dgn actor | ERROR | |
| `returnToHelpdesk()` | Pembuat tiket, skip kalau sama dgn actor | WARNING | |

Helper baru: `notifyCreatorIfDifferentActor()` (notif personal, skip self-notify) dan `usersWithRoleInPop()`/`notifyRoleUsersInPop()` (notif role-wide per POP, pola disalin dari `TaskService::complete()`). Test: `tests/Feature/TicketNotificationTest.php` (6 test).

**Efek samping yang ketahuan pas implementasi:** notifikasi baru ini bikin 2 test lama (`NocWorksheetTest::test_closed_ticket_leaves_the_worksheet`, `TicketHistoryTest::test_ticket_returned_to_helpdesk_leaves_history`) gagal — bukan karena state tiket salah, tapi karena teks notifikasi (berisi `ticket_number`) ikut ke-embed di `<script>` dropdown navbar (`notification-dropdown.blade.php`, tampil di **semua** halaman), dan `assertDontSee($ticket->ticket_number)` polos menangkap itu juga. Diperbaiki dengan strip `<script>` sebelum assert (test sekarang beneran cuma ngecek tabel worksheet/history, bukan seluruh halaman) — regresi test, bukan regresi behavior.

### 8.2 Verifikasi Pelanggan — `app/Http/Controllers/CustomerVerificationController.php`

Helper baru `notifyTaskTeam()` (pola sama `TaskController::notifyTeamMembers()`), notif ke tim task (survey/pemasangan) yang laporannya diperiksa:

| Method | Notif ke tim task | Type |
|---|---|---|
| `processToTeam()` | Survey disetujui | SUCCESS |
| `finalVerify()` | Pemasangan disetujui + pelanggan aktif | SUCCESS |
| `reject()` | Laporan ditolak | ERROR |
| `revisi()` | Perlu revisi | WARNING |

Test: `tests/Feature/CustomerVerificationNotificationTest.php` (2 test, reject + revisi — dua yang eksplisit diminta di §5 asli).

**GAP ketauan 2026-08-06 (belum difix) — `processToTeam()` gak notif FOP.**
Skenario: Task PSB status "Menunggu ACC" (`waiting_acc`), Admin klik "Setujui & Proses ke Tim Pemasangan". Yang kejadian:
1. `CustomerWorkflowService::transition()` ke `waiting_installation` → auto-`Task::create()` Task Pemasangan baru, status `PENDING`, **tanpa tim** (`app/Services/CustomerWorkflowService.php:96-110` — polos `Task::create()`, gak ada `->notify()` di titik ini).
2. `processToTeam()` cuma notif tim **survey** yang laporannya baru disetujui (`notifyTaskTeam($surveyTask, ...)`, SUCCESS) — itu notif "laporan Anda oke", BUKAN notif "ada kerjaan baru masuk antrean pemasangan".
3. Task Pemasangan yang baru itu **nunggu FOP assign tim** — FOP-lah yang bakal buka Task Pemasangan ini dan nentuin teknisi, BUKAN admin verifikasi. Tapi gak ada satu pun notif ke role `fop` yang bilang "ada Task Pemasangan baru nunggu di-assign".

Beda karakter dari gap lain di §5/§6: ini bukan "modul X belum disentuh sama sekali" — `CustomerVerificationController` UDAH punya notif (ke tim survey), tapi **penerimanya salah target** buat langkah selanjutnya. Pola yang bener harusnya niru `TicketService::escalateToFop()` (§8.1): `notifyRoleUsersInPop('fop', $customer->pop_id, ...)` dipanggil abis `$workflowService->transition(...)`, ngasih tau semua user role `fop` di POP itu ada Task Pemasangan baru (`task_number`-nya) yang perlu di-assign. Titik pemanggilan paling pas: di `processToTeam()` sendiri (controller udah punya `$customer` + akses ke Task Pemasangan yang baru dibuat), bukan di `CustomerWorkflowService::transition()` (service generik, dipakai banyak transisi lain yang gak semuanya butuh notif FOP).

### 8.3 Billing — `app/Http/Controllers/PaymentController.php`

`reject()` notif ke pencatat pembayaran (`collected_by` kalau ada / fallback `received_by`), skip kalau actor = pencatat sendiri. Type ERROR. Test: `tests/Feature/PaymentRejectNotificationTest.php` (2 test).

**Sengaja TIDAK dikerjakan:** notif "setoran kolektor" (`CollectorBatchController::store()`) ke "Finance Pusat" — RBAC repo ini (`owner`, `atasan`, `admin`, `noc`, `helpdesk`, `fop`, `teknisi`, `sales`, `pop_admin`, `kolektor`) **gak punya role/permission yang jelas mewakili "Finance Pusat"**, beda dari Ticketing (role `noc`/`fop` jelas) atau Verifikasi (tim task jelas dari `teamMembers`). Nebak role sembarangan (mis. asumsi `admin`+`owner`+ALL_POP scope) berisiko nyasar notif ke orang yang salah atau bikin asumsi keliru soal siapa yang "harusnya" jadi Finance — lihat CLAUDE.md "Berhenti & Tanya Kalau requirement ambigu". Butuh keputusan eksplisit dulu soal role/permission mana yang dipakai sebelum dikerjakan.

### 8.4 Quick-fix (§6.2, §6.4)

- `app/Console/Commands/PruneReadNotifications.php` + jadwal `dailyAt('00:30')` di `routes/console.php`. Test: `tests/Feature/PruneReadNotificationsTest.php` (2 test).
- `routes/channels.php` channel `fop.{pop_id}` diseragamkan ke `EffectiveAccessService`. Diverifikasi lewat `tests/Feature/TaskBroadcastingTest.php` (regresi, masih hijau).

### 8.5 Yang belum, dan kenapa

- **`processToTeam()` gak notif FOP soal Task Pemasangan baru** (§8.2) — ketauan 2026-08-06 dari laporan user, belum difix. Prioritas tinggi: FOP-lah yang assign tim buat Task Pemasangan, bukan admin verifikasi — tanpa notif ini FOP cuma bisa tau lewat cek dashboard manual.
- **NOC Dashboard / SLA Breach**, **Customer Lifecycle**, **Import Data Massal** (§5) — belum digarap, di luar batch permintaan ini.
- **Setoran kolektor batch** (§8.3) — butuh keputusan RBAC dulu.
- **Ketergantungan queue worker** (§6.3), **query dropdown tiap page load**, **sinkron antar tab**, **`NotificationController::index()` scope logic** (§4) — belum digarap, sifatnya optimasi bukan gap fungsional.
- 4 test gagal di suite penuh (`FopTaskCreateFollowsTicketingTest`, `FopTaskHistoryFollowsTicketDetailTest`, `TicketingTest` ×2) — **pre-existing**, `FilesystemIterator::__construct(...tickets): Permission denied` saat teardown disk attachment. Dikonfirmasi ada juga di kode SEBELUM perubahan sesi ini (`git stash` + run ulang) — masalah lingkungan Windows/WSL bridge, bukan regresi dari kerjaan ini.

---

## 9. Referensi

- `app/Notifications/AppNotification.php`, `app/Enums/NotificationType.php`
- `app/Http/Controllers/NotificationController.php`
- `resources/views/components/notification-dropdown.blade.php`
- `app/Services/TaskService.php`, `app/Http/Controllers/TaskController.php`, `app/Http/Controllers/FopTaskController.php`
- `app/Services/TicketService.php`, `app/Http/Controllers/CustomerVerificationController.php`, `app/Http/Controllers/PaymentController.php` (§8)
- `app/Console/Commands/PruneReadNotifications.php`, `routes/channels.php`, `routes/console.php` (§8.4)
- `tests/Feature/TicketNotificationTest.php`, `tests/Feature/CustomerVerificationNotificationTest.php`, `tests/Feature/PaymentRejectNotificationTest.php`, `tests/Feature/PruneReadNotificationsTest.php`
- `ANALISA_REDUNDANSI_LOGIC.md` §9 (histori fix enum `NotificationType`)
- [`analisa-in-app-dan-push-notifikasi.md`](analisa-in-app-dan-push-notifikasi.md) — rencana lengkap & matriks event
