# BELUM DI IMPLEMENTASIKAN

# Analisa Status Implementasi In-App Notification (Audit Kondisi Aktual)

**Tanggal audit:** 2026-08-05
**Konteks:** Audit kode aktual — bukan rencana. Untuk kebutuhan/rencana ke depan lihat [`analisa-in-app-dan-push-notifikasi.md`](analisa-in-app-dan-push-notifikasi.md); dokumen ini memotret apa yang **sudah** jalan di kode saat ini, supaya rencana di dokumen itu bisa dicek progress-nya terhadap kondisi riil.

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
| **Ticketing** | `TicketService::escalateToNoc()`, `escalateToFop()`, `close()`, `cancel()`, `returnToHelpdesk()` | Nol pemanggilan `AppNotification`. NOC/Helpdesk cuma tau tiket baru lewat buka Worksheet manual. |
| **Verifikasi Pelanggan** | `CustomerVerificationController` (approve/reject) | Tidak ada notif balik ke pengaju (installer/CS/sales) soal hasil verifikasi. |
| **Billing & Pembayaran** | Generate tagihan batch, terima pembayaran, setoran kolektor, overpay/rekonsiliasi (modul baru dari commit `e9c592d`) | Tidak ada notif ke Finance/Admin POP/Collector — matriks lengkap sudah dirancang di §3 dokumen rencana tapi belum ada satupun call site di `CollectorBatchController`/`Invoice`/`Payment`. |
| **NOC Dashboard / SLA Breach** | Handling SLA FOP (`handling_sla_hours`) kelewat | Tidak ada cron/alert in-app; dashboard NOC murni pull, bukan push. |
| **Customer Lifecycle** | Transisi besar `WorkflowTransition` (mis. jadi `active`, `suspended`, `terminated`) | Tidak ada notif ke CS/Sales saat status berubah. |
| **Import Data Massal** | `CustomersImport`/batch import selesai | Tidak ada notif hasil (sukses/gagal) — user harus cek halaman import-batches manual. |

**Prioritas realistis kalau dikerjakan:** Ticketing eskalasi (paling rawan miss-komunikasi, sesuai constraint sinkronisasi Ticket↔FopTask di `CLAUDE.md`) → Billing (setoran kolektor & konfirmasi pembayaran, sudah ada modulnya sejak commit terakhir) → sisanya post-MVP sesuai catatan scope di dokumen rencana §5.

---

## 6. Referensi

- `app/Notifications/AppNotification.php`, `app/Enums/NotificationType.php`
- `app/Http/Controllers/NotificationController.php`
- `resources/views/components/notification-dropdown.blade.php`
- `app/Services/TaskService.php`, `app/Http/Controllers/TaskController.php`, `app/Http/Controllers/FopTaskController.php`
- `ANALISA_REDUNDANSI_LOGIC.md` §9 (histori fix enum `NotificationType`)
- [`analisa-in-app-dan-push-notifikasi.md`](analisa-in-app-dan-push-notifikasi.md) — rencana lengkap & matriks event
