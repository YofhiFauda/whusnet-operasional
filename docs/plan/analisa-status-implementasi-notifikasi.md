# Analisa Status Implementasi In-App Notification (Audit Kondisi Aktual)

**Tanggal audit:** 2026-08-05 · **Update implementasi:** 2026-08-06 · **Batch #2 (4 fitur + optimasi):** 2026-08-07
**Konteks:** Audit kode aktual — bukan rencana. Untuk kebutuhan/rencana ke depan lihat [`analisa-in-app-dan-push-notifikasi.md`](analisa-in-app-dan-push-notifikasi.md); dokumen ini memotret apa yang **sudah** jalan di kode saat ini, supaya rencana di dokumen itu bisa dicek progress-nya terhadap kondisi riil.

> **Status per 2026-08-07:** SEMUA temuan §5 dan §6 **SUDAH digarap**, termasuk 4 fitur yang sebelumnya sengaja ditunda (SLA Breach, Customer Lifecycle, Import Massal, Setoran Kolektor — via `pop_admin`, sistem ini gak punya role "Finance Pusat") dan seluruh 4 poin optimasi §4. Lihat §8 buat detail lengkap. Satu-satunya yang masih di luar scope: **Suspended** di Customer Lifecycle (§8.5, gak ada action controller khusus buat itu) dan fallback offline/push (§4 no. 4, beda kelas kerja — rencananya di `analisa-in-app-dan-push-notifikasi.md`).

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

1. ✅ **DONE 2026-08-07 — Query dropdown tiap page load.** Unread count sekarang di-cache (`User::unreadNotificationsCountCached()`, TTL 20 detik) — lihat §8.7 poin 2.
2. ✅ **DONE 2026-08-07 — Tidak sinkron antar tab.** Broadcast `NotificationsMarkedRead` pas mark-read/mark-all-read — lihat §8.7 poin 3.
3. ✅ **DONE 2026-08-07 — `NotificationController::index()` scope logic.** Diringkas jadi 2 kasus nyata (bukan 4 kombinasi) — lihat §8.7 poin 4.
4. **Tidak ada fallback offline** — (masih berlaku, gak digarap batch ini) kalau user gak online pas broadcast terjadi, notifikasi cuma nongkrong di DB nunggu user buka app. By design utk internal tool; kalau modul Ticketing/Billing (sekarang udah ada notifnya, lihat §8.1/§8.3) butuh respons instan walau user offline, pertimbangkan push (Telegram) sebagai pelengkap wajib bukan opsional — sudah direncanakan di `analisa-in-app-dan-push-notifikasi.md` §4. Beda kelas kerja dari 3 poin di atas (butuh integrasi channel baru, bukan cuma optimasi internal), sengaja dipisah gak ikut batch ini.

---

## 5. Fitur yang Butuh In-App Notification Tapi Belum Diimplementasikan

Berdasarkan sudah adanya rencana lengkap di `analisa-in-app-dan-push-notifikasi.md`, gap implementasi terhadap kode aktual:

| Modul | Method/Titik | Kondisi Sekarang |
|---|---|---|
| **Ticketing** | `TicketService::escalateToNoc()`, `escalateToFop()`, `close()`, `cancel()`, `returnToHelpdesk()` | ✅ **Selesai 2026-08-06** — lihat §8.1. |
| **Verifikasi Pelanggan** | `CustomerVerificationController` (`reject()`, `revisi()`) | ✅ **Selesai 2026-08-06** — lihat §8.2. `processToTeam()`/`finalVerify()` juga sudah kebagian notif SUCCESS (bukan dari §5 asli, ekstra sekalian). `processToTeam()` juga sudah notif role `fop` soal Task Pemasangan baru (gap dilaporkan user 2026-08-07, fix di §8.2). |
| **Billing & Pembayaran** | `PaymentController::reject()`, `CollectorBatchController::store()` | ✅ **Selesai 2026-08-06 (reject) + 2026-08-07 (setoran kolektor)** — lihat §8.3. "Finance Pusat" gak ada di RBAC sistem ini (dikonfirmasi user) — penerima setoran kolektor dialihkan ke role `pop_admin` (pemegang `payments.validate`/`reject` per POP, fungsinya paling dekat). |
| **NOC Dashboard / SLA Breach** | Handling SLA FOP (`handling_sla_hours`) kelewat | ✅ **Selesai 2026-08-07** — lihat §8.4, scheduled command baru `fop-tasks:check-sla-breach`. |
| **Customer Lifecycle** | Transisi besar `WorkflowTransition` (mis. jadi `active`, `suspended`, `terminated`) | ✅ **Selesai (sebagian) 2026-08-07** — `active` (`finalVerify()`) & `terminated` (`CustomerTerminationController`) notif `customers.created_by`, lihat §8.5. `suspended` **sengaja diskip** — gak ada action controller khusus (cuma generic `CustomerController::update()`), lihat §8.5 buat alasannya. |
| **Import Data Massal** | `CustomerController::confirmImport()` (bukan class `CustomersImport` terpisah — itu gak pernah ada, importnya inline pakai `SimpleExcelReader`) | ✅ **Selesai 2026-08-07** — lihat §8.6. |

**Semua sudah digarap** kecuali `suspended` (§8.5) dan fallback offline (§4 no. 4, beda kelas kerja).

---

## 6. Temuan Tambahan (Analisa Lanjutan)

Penelusuran lanjutan nemu nuansa yang bikin gambaran §5 kurang lengkap kalau dibaca "modul X = nol realtime sama sekali". Beberapa modul justru **sudah** punya infrastruktur realtime — cuma bukan lewat `AppNotification`/lonceng, jadi gampang keliru dianggap gak ada sama sekali.

1. **Broadcast pasif (auto-refresh) vs notifikasi aktif (lonceng) — dua mekanisme beda, jangan disamakan.**
   Ticketing, Verifikasi, Invoice, dan papan FOP Task **sudah** broadcast realtime via `ShouldBroadcast` event murni (bukan Laravel Notification): `TicketQueueUpdated` (`tickets.{popId}`), `CustomerVerificationStatusChanged` (`customers.{popId}`), `InvoiceStatusUpdated` (`invoices.{popId}`), `FopTaskUpdated` (`fop-tasks.{popId}`) — didaftarkan di `routes/channels.php`. Sengaja gak bawa payload lengkap, cuma sinyal "refetch", dikonsumsi Alpine state di halaman yang lagi kebuka (lihat `docs/plan/analisa-realtime-spa-operasional.md`).
   **Bedanya krusial dari lonceng notifikasi:** event ini cuma nyala kalau user **lagi buka halaman itu juga** — gak ada unread badge, gak masuk riwayat `/notifications`, gak nyusul kalau user pindah halaman/belum login. Jadi klaim "Ticketing/Billing/Verifikasi belum ada realtime" di §5 perlu diperhalus: yang belum ada itu **notifikasi personal yang persisten & actionable** (lonceng + histori), bukan realtime sama sekali. Worth dibedain di rencana implementasi biar gak dikerjain dua kali dari nol.

2. ✅ **DONE 2026-08-06 — Retensi `database_notifications`.** `app/Console/Commands/PruneReadNotifications.php` (`notifications:prune-read {--days=90}`) hapus notif yang `read_at` udah lewat N hari — notif BELUM dibaca sengaja gak disentuh seberapa pun lamanya. Dijadwalkan `dailyAt('00:30')` di `routes/console.php`.

3. ✅ **DONE 2026-08-07 — Ketergantungan diam-diam ke queue worker, DIHILANGKAN (bukan cuma dimonitor).**
   `AppNotification` sebelumnya `implements ShouldQueue`. Daripada nambahin monitoring proaktif ke queue (opsi yang disebut di analisa awal — cek `horizon:snapshot`/LongWaitDetected), dicabut interface-nya sekalian: notif sekarang kirim **sinkron** di request/command yang manggil. Volume per panggilan kecil (1 insert DB + 1 broadcast event per penerima, bukan API eksternal lambat), jadi ketergantungan ke availability queue worker buat fitur yang butuh nyampe SEKARANG (lonceng), bukan nanti, lebih mahal ketimbang manfaatnya. Lihat §8.7.

4. ✅ **DONE 2026-08-06 — Channel `fop.{pop_id}` diseragamkan ke `EffectiveAccessService`.** `routes/channels.php` — otorisasi channel `fop.{pop_id}` (dipakai `/fop/dashboard`) sekarang pakai `hasAllPopAccess()`/`getAllowedPopIds()`, sama pola dengan 4 channel lain di file yang sama. Sebelumnya `$user->pops()->where(...)->exists()` (jalur legacy, gak paham `pop_tree` — CLAUDE.md § POP Scope).

---

## 8. Detail Implementasi (2026-08-06 & 2026-08-07)

Realisasi dari §5/§6 di atas, atas permintaan lanjutan user, dua batch:
- **Batch #1 (2026-08-06):** §8.1, §8.2 (minus fix gap FOP), §8.3 (minus setoran kolektor).
- **Batch #2 (2026-08-07):** sisa 4 fitur "sengaja ditunda" (§8.3 setoran kolektor, §8.4 SLA Breach, §8.5 Customer Lifecycle, §8.6 Import Massal) + semua optimasi §4 (§8.7) + fix gap FOP di §8.2.

Full suite (`php artisan test`) di akhir Batch #2: **1021 passed, 0 failed.**

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

Test: `tests/Feature/CustomerVerificationNotificationTest.php` (6 test — reject + revisi dari batch pertama, ditambah 2 test gap FOP di bawah).

✅ **GAP DIFIX 2026-08-07 — `processToTeam()` sekarang notif FOP soal Task Pemasangan baru.**
Skenario: Task PSB status "Menunggu ACC" (`waiting_acc`), Admin klik "Setujui & Proses ke Tim Pemasangan". Sebelumnya:
1. `CustomerWorkflowService::transition()` ke `waiting_installation` → auto-`Task::create()` Task Pemasangan baru, status `PENDING`, **tanpa tim** (`app/Services/CustomerWorkflowService.php:96-110` — polos `Task::create()`, gak ada `->notify()` di titik ini; masih gitu, sengaja gak diubah, lihat catatan di bawah).
2. `processToTeam()` cuma notif tim **survey** yang laporannya baru disetujui (`notifyTaskTeam($surveyTask, ...)`, SUCCESS) — itu notif "laporan Anda oke", BUKAN notif "ada kerjaan baru masuk antrean pemasangan".
3. Task Pemasangan yang baru itu **nunggu FOP assign tim** — FOP-lah yang bakal buka Task Pemasangan ini dan nentuin teknisi, BUKAN admin verifikasi. Gak ada satu pun notif ke role `fop`.

**Fix:** setelah `$workflowService->transition(...)`, `processToTeam()` ambil Task Pemasangan aktif (`PENDING`/`TERJADWAL`/`IN_PROGRESS`) milik customer itu, lalu — kalau task-nya belum punya `teamMembers` sama sekali (berarti beneran baru, bukan task lama yang dipakai ulang lewat jalur revisi) — panggil `notifyRoleUsersInPop('fop', $customer->pop_id, ...)` (helper baru di controller ini, pola disalin dari `TicketService::usersWithRoleInPop()`/`notifyRoleUsersInPop()` §8.1, bukan diekstrak jadi shared service — cuma dipakai 1 titik). Guard `! $installTaskForNotif->teamMembers()->exists()` nyegah notif dobel kalau `processToTeam()` somehow kepanggil lagi buat task yang udah pernah di-assign.

Titik pemanggilan sengaja di `processToTeam()` sendiri (controller udah punya `$customer` + akses ke Task Pemasangan yang baru dibuat), bukan di `CustomerWorkflowService::transition()` — service itu generik, dipakai banyak transisi lain (`waiting_survey` dst.) yang gak semuanya butuh notif FOP jenis ini.

Test: `tests/Feature/CustomerVerificationNotificationTest.php::test_process_to_team_notifies_fop_role_about_new_install_task` + `::test_process_to_team_does_not_renotify_fop_for_task_with_existing_team`.

### 8.3 Billing — `app/Http/Controllers/PaymentController.php` + `CollectorBatchController.php`

`PaymentController::reject()` (Batch #1) notif ke pencatat pembayaran (`collected_by` kalau ada / fallback `received_by`), skip kalau actor = pencatat sendiri. Type ERROR. Test: `tests/Feature/PaymentRejectNotificationTest.php` (2 test).

✅ **Setoran kolektor (Batch #2) — `CollectorBatchController::store()` notif role `pop_admin`.**
Sebelumnya sengaja ditunda karena RBAC sistem ini (`owner`, `atasan`, `admin`, `noc`, `helpdesk`, `fop`, `teknisi`, `sales`, `pop_admin`, `kolektor`) gak punya role "Finance Pusat". **User mengkonfirmasi role itu memang gak ada di sistem ini** — jadi penerima dialihkan ke `pop_admin`: satu-satunya role yang pegang `payments.validate`/`payments.reject` per POP (`database/seeders/RolePermissionSeeder.php`), fungsinya paling dekat. Detail:
- Sukses batch → di-grup per `pop_id` invoice yang kena (satu batch kolektor secara teknis bisa nyentuh invoice lintas POP walau jarang), notif ke SEMUA user `pop_admin` di tiap POP itu (role-wide, sama pola `escalateToNoc`/`escalateToFop` §8.1 — gak skip-self, siapa pun `pop_admin` yang submit tetap dapet biar konsisten kalau di real world ada >1 `pop_admin` per POP).
- Pesan: jumlah pembayaran + total nominal per POP, actionUrl ke `collectors.show`. **Redaksi sengaja murni informatif** ("dicatat"), BUKAN "perlu direkonsiliasi" — dikoreksi setelah ketauan `docs/billing-pembayaran/README.md` eksplisit bilang `PaymentBatch` **BUKAN** rekonsiliasi kas, fitur Setoran Kolektor formal di-drop dari scope produk. Notif ini murni informasi "ada setoran baru", bukan nyiratin ada langkah lanjutan yang wajib dikerjakan `pop_admin`.
- Batch gagal (validasi/exception) → gak ada notif.
- Resubmit idempotent (`idempotency_key` sama) → gak renotif.

Helper baru `notifyRoleUsersInPop()` + `usersWithRoleInPop()` di controller ini (pola sama §8.1, disalin bukan diekstrak — cuma 1 titik pakai). Test: `tests/Feature/CollectorBatchNotificationTest.php` (3 test).

### 8.4 NOC Dashboard / SLA Breach (Batch #2) — command baru `fop-tasks:check-sla-breach`

Sebelumnya nol alert — dashboard NOC/FOP murni pull. Sekarang:
- Command baru `app/Console/Commands/CheckFopTaskSlaBreach.php`, dijadwalkan `everyThirtyMinutes()` di `routes/console.php`.
- Reuse `FopTask::slaDeadline()` (bukan hitung ulang dari `handling_sla_hours`) — itu SATU-SATUNYA sumber kebenaran deadline yang sama dipakai badge countdown FOP dashboard.
- Kandidat: FopTask status bukan `selesai`/`dibatalkan`, `sla_breach_notified_at` masih null, `now() > slaDeadline()`. Notif ke semua user role `fop` di POP FopTask itu, type WARNING.
- **Dedup via kolom baru `fop_tasks.sla_breach_notified_at`** (migration `2026_08_07_102131_add_sla_breach_notified_at_to_fop_tasks_table.php`) — sekali notif per breach, bukan diulang tiap command jalan. Direset ke `null` otomatis (`FopTask::booted()` → `updating`) begitu `task_date` berubah (reschedule) — deadline baru, layak breach-check ulang.

Test: `tests/Feature/FopTaskSlaBreachNotificationTest.php` (5 test — breach ke-notif, dedup, belum breach, task selesai dikecualikan, reschedule reset flag).

### 8.5 Customer Lifecycle (Batch #2) — `active` & `terminated`

Notif ke `customers.created_by` (pendaftar asli/Sales-CS), skip kalau actor = pendaftar sendiri:
- `CustomerVerificationController::finalVerify()` → customer jadi `active`, type SUCCESS.
- `CustomerTerminationController::__invoke()` → customer jadi `terminated`, type ERROR.

**`suspended` SENGAJA diskip** — beda dari `active`/`terminated` yang masing-masing punya SATU action controller khusus dengan niat jelas (`finalVerify()`, `CustomerTerminationController`), gak ada aksi "suspend pelanggan" yang berdiri sendiri di sistem ini. Status `suspended` cuma keset lewat `CustomerController::update()` — form edit generik yang nulis ulang banyak field customer sekaligus. Nempelin notif di situ berisiko false-positive: setiap kali admin edit data customer apa pun (alamat, no HP, dll) yang KEBETULAN sudah berstatus `suspended`, atau override status dari form generik, bisa ke-notif padahal bukan itu niatnya. Kalau ke depan ada tombol/aksi "Isolir Pelanggan" berdiri sendiri, itu titik yang tepat buat ditambah notif — bukan generic `update()`.

Test: `tests/Feature/CustomerLifecycleNotificationTest.php` (3 test — final verify notif, terminate notif, terminate oleh diri sendiri gak self-notify).

### 8.6 Import Data Massal (Batch #2) — `CustomerController::confirmImport()`

Koreksi asumsi di analisa awal: gak ada class `CustomersImport` terpisah (spatie/simple-excel dipakai inline lewat `SimpleExcelReader`, bukan lewat kelas import Laravel-Excel-style), dan prosesnya **sinkron** dalam satu request HTTP (bukan queued job seperti diasumsikan awal) — uploader udah langsung liat hasil di response. Notif tetap ditambahkan biar `batch_number` gak ilang kalau lupa dicatat manual & ninggalin jejak di `/notifications`:
- Sukses → notif uploader sendiri (`auth()->user()`), SUCCESS, jumlah baris + `batch_number`.
- Gagal (exception, `catch` block) → notif uploader, ERROR, pesan exception.
- actionUrl ke `customers.import.batch-detail`.

Test: `tests/Feature/CustomerImportNotificationTest.php` (2 test).

### 8.7 Optimasi §4 (Batch #2)

Keempat poin §4 sekaligus, saling terkait (satu perubahan blade + satu model + satu controller):

1. **`AppNotification` gak lagi `ShouldQueue`** (§6.3) — kirim sinkron. Dampak diverifikasi lewat full suite (1021 test, termasuk semua notif yang ditambahkan sesi ini yang tadinya diasumsikan queued).
2. **Cache unread count** — `User::unreadNotificationsCountCached()` (TTL 20 detik, `Cache::remember`, pola sama `EffectiveAccessService`) dipakai di `notification-dropdown.blade.php` gantiin `unreadNotifications()->count()` polos yang jalan tiap page load. Sengaja gak di-invalidate tiap notif baru masuk (staleness window pendek, badge di klien udah nambah realtime lewat Echo `.notification()` duluan) — cuma di-clear pas mark-read/mark-all-read (`User::clearUnreadNotificationsCountCache()`, dipanggil `NotificationController`).
3. **Sinkron unread count antar tab** — event baru `App\Events\NotificationsMarkedRead` (`ShouldBroadcastNow`, konsisten sama keputusan poin 1 — event UI-sync kecil gak boleh nunggu queue), dibroadcast ke channel `App.Models.User.{id}` yang SAMA dipakai notifikasi (gak ada channel baru), `->toOthers()` biar tab yang barusan aksi gak kena balik event sendiri. Klien `.listen('.NotificationsMarkedRead', ...)` di object channel yang sama dengan `.notification()`. Fetch call di blade ditambah header `X-Socket-ID` manual (skill `echo-development` — pitfall umum: `fetch()` beda dari axios, gak otomatis nempelin header itu, tanpa ini `toOthers()` gak bisa nyaring tab pengirim).
4. **`NotificationController::index()` scope logic diringkas** — dari 4 kombinasi (`scopeType !== ALL_POP` × `hasPermission`) jadi 2 kasus nyata (`hasPermission('task.view.all')` → filter POP kalau bukan `hasAllPopAccess()`; kalau gak punya → langsung batasi ke diri sendiri, scope POP jadi gak relevan). Query fragment "user ini punya akses ke salah satu POP" yang tadinya diduplikasi 2x disatukan jadi `scopedToAllowedPops()`.

Test: `tests/Feature/NotificationUnreadCountSyncTest.php` (3 test — cache kepakai, mark-read clear+broadcast, mark-all clear+broadcast). Regresi `NotificationDashboardTest`/`NotificationTypeColumnTest` tetap hijau.

### 8.8 Verifikasi akhir

- `vendor/bin/pint --dirty` — clean di seluruh perubahan Batch #1 + #2.
- Full suite `php artisan test` (bukan cuma filter modul) di akhir Batch #2: **1021 passed, 0 failed** — termasuk 4 test yang di Batch #1 sempat dilaporkan gagal karena `FilesystemIterator Permission denied` (lingkungan Windows/WSL bridge); ternyata flaky tergantung urutan eksekusi, di run penuh terakhir semuanya hijau.
- Total file baru sesi ini: 1 migration, 2 command (`PruneReadNotifications`, `CheckFopTaskSlaBreach`), 1 event (`NotificationsMarkedRead`), 10 file test baru.

### 8.9 Toast pop-up saat notifikasi realtime masuk (2026-08-07, ditemukan dari laporan user)

**Gap:** semua notif di atas (§8.1–§8.6) beneran realtime lewat WebSocket (Reverb + Echo `.notification()`, BUKAN polling — lihat pembahasan mekanisme di bawah), tapi begitu event masuk cuma nambah angka badge lonceng secara **diam-diam**. User yang gak lagi buka dropdown gak sadar ada kejadian baru (tiket masuk NOC, task selesai, dll) — komponen `window.Toast` (`components/toast.blade.php`) udah ada di layout tapi cuma dipicu dari session-flash pas reload halaman, **gak pernah** dipicu dari notifikasi realtime.

**Fix:** `notification-dropdown.blade.php`, handler `.notification()` — tambah `window.Toast[type](title, message)` (fallback ke `.info` kalau type gak dikenal). Satu titik perbaikan nutup ketiga skenario yang dilaporkan user sekaligus (tiket ke FOP selesai, tiket Helpdesk→NOC masuk, task teknisi selesai) karena semuanya lewat `AppNotification` → channel `App.Models.User.{id}` yang sama.

**Konfirmasi mekanisme realtime (ditanya user, bukan asumsi):** WebSocket push murni (Laravel Reverb), nol polling/`setInterval`-ke-server/reload manual di seluruh jalur notifikasi. Satu-satunya `setInterval` yang ketemu di modul terkait (`verifications/queue.blade.php:189`) itu jam mundur visual (update teks tiap detik, NO network call) — data row-nya sendiri tetap event-driven lewat `Echo.listen('.CustomerVerificationStatusChanged', ...)`.

Test: `tests/Feature/NotificationToastOnArrivalTest.php` — regresi ringan (assert JS `window.Toast` call tetap nempel di komponen), bukan uji visual (di luar jangkauan PHPUnit tanpa Dusk).

---

## 9. Referensi

- `app/Notifications/AppNotification.php`, `app/Enums/NotificationType.php`
- `app/Http/Controllers/NotificationController.php`, `app/Models/User.php` (§8.7)
- `app/Events/NotificationsMarkedRead.php` (§8.7)
- `resources/views/components/notification-dropdown.blade.php` (§8.7, §8.9), `resources/views/components/toast.blade.php` (§8.9)
- `app/Services/TaskService.php`, `app/Http/Controllers/TaskController.php`, `app/Http/Controllers/FopTaskController.php`
- `app/Services/TicketService.php` (§8.1)
- `app/Http/Controllers/CustomerVerificationController.php` (§8.2, §8.5)
- `app/Http/Controllers/PaymentController.php`, `app/Http/Controllers/CollectorBatchController.php` (§8.3)
- `app/Console/Commands/CheckFopTaskSlaBreach.php` (§8.4), `app/Models/FopTask.php` (§8.4)
- `app/Http/Controllers/CustomerTerminationController.php` (§8.5)
- `app/Http/Controllers/CustomerController.php::confirmImport()` (§8.6)
- `app/Console/Commands/PruneReadNotifications.php`, `routes/channels.php`, `routes/console.php`
- `tests/Feature/TicketNotificationTest.php`, `tests/Feature/CustomerVerificationNotificationTest.php`, `tests/Feature/PaymentRejectNotificationTest.php`, `tests/Feature/PruneReadNotificationsTest.php`, `tests/Feature/CollectorBatchNotificationTest.php`, `tests/Feature/FopTaskSlaBreachNotificationTest.php`, `tests/Feature/CustomerLifecycleNotificationTest.php`, `tests/Feature/CustomerImportNotificationTest.php`, `tests/Feature/NotificationUnreadCountSyncTest.php`, `tests/Feature/NotificationToastOnArrivalTest.php` (§8.9)
- `ANALISA_REDUNDANSI_LOGIC.md` §9 (histori fix enum `NotificationType`)
- [`analisa-in-app-dan-push-notifikasi.md`](analisa-in-app-dan-push-notifikasi.md) — rencana lengkap & matriks event
- [`analisa-realtime-spa-operasional.md`](analisa-realtime-spa-operasional.md) — fondasi arsitektur realtime existing (broadcast pasif, dibedakan dari lonceng notifikasi di §6.1 dokumen ini)
