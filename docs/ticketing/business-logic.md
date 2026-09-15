# Business Logic — Modul Ticketing

## 1. Tipe Tiket yang Berlaku

Cuma 2 dari nilai `TaskType` yang boleh masuk lewat Ticketing:

```php
// App\Enums\TaskType::ticketValues()
[self::MAINTENANCE->value, self::CREQ->value]  // ['MTN', 'C-REQ']
```

Tipe lain (SURVEY, PSB, O-REQ, DEAC, INFR REQ) tetap dibuat langsung dari `/fop-tasks` atau auto-sync Registrasi Pelanggan — gak pernah lewat Ticketing. `TicketController::store()` menolak tipe di luar 2 itu dengan pesan `"Tipe ticket hanya boleh MTN atau C-REQ."`.

## 2. Dua Rezim Status

Tiket punya kolom status **sendiri** selama masih ditangani internal, dan baru numpang `FopTask` setelah dieskalasi ke FOP. Tiga kolom yang berperan:

| Kolom | Enum/Tipe | Arti |
|---|---|---|
| `handler` | `TicketHandler` (`helpdesk`/`noc`/`fop`) | Siapa yang lagi pegang tiket |
| `status` | `TicketHandlingStatus` (`open`/`closed`/`cancelled`) | Status internal — **cuma bermakna selama `handler` ≠ FOP** |

State yang mungkin:

| `handler` | `status` | Label | Siapa yang boleh bertindak |
|---|---|---|---|---|
| `helpdesk` | `open` | — | Ditangani Helpdesk | `helpdesk` |
| `noc` | `open` | **Diproses NOC** | `helpdesk` **DAN** `noc` |
| `helpdesk`/`noc` | `closed` | — | Selesai (…) | tidak ada (final) |
| `helpdesk`/`noc` | `cancelled` | — | Dibatalkan (…) | tidak ada (final) |
| `fop` | (diabaikan) | (diabaikan) | turunan `FopTask.status` | tidak ada di sisi Ticketing |

**Kenapa tiket sekarang punya status sendiri.** Desain awal (`docs/plan/RANCANGAN_WORKSHEET_TICKETING.MD`) menganggap setiap tiket pasti berujung ke lapangan, jadi status tiket cukup diturunkan dari `FopTask`. Kenyataannya mayoritas keluhan (lemot, konfigurasi, routing) selesai dari meja Helpdesk/NOC tanpa teknisi sama sekali. Memaksa `FopTask` kebentuk cuma buat nampung status bikin papan FOP penuh tugas palsu. Sejak migrasi `2026_07_25_000003`, tiket berdiri sendiri sampai benar-benar butuh lapangan.

**Kenapa `handler` gak dilebur jadi satu enum status.** Dua sumbu ini ortogonal: `handler` = *siapa*, `status` = *sudah kelar atau belum*. Dilebur jadi satu enum bakal meledak jadi 3×3 kombinasi (`helpdesk_open`, `noc_closed`, …) yang harus dijaga manual setiap ada aktor baru.

**`handler=FOP` itu beku permanen.** Dipakai juga buat membedakan dua kasus `fop_task_id` NULL yang artinya beda: tiket yang **belum pernah** dieskalasi (`handler` masih helpdesk/noc) vs tiket yang **sudah pernah** tapi FopTask-nya dihapus FOP (orphan/"Terputus").

## 3. Kepemilikan Tiket di Tangan NOC

Begitu Helpdesk klik "Ke NOC" (`escalateToNoc()`), `handler` jadi `noc` dan tiket **langsung berstatus Diproses NOC**. Tiket muncul seketika di tab **Tiket Masuk** Worksheet NOC sebagai pekerjaan berjalan — tab itu isinya `handler=noc & status=open`, jadi "masuk" di situ berarti "masuk ke meja NOC", **bukan** antrean pending yang harus di-Oncheck dulu (window itu dihapus ADHOC-06). Setelah NOC meneruskannya ke FOP, tiket pindah ke tab **Assign FOP** yang read-only.

> **Dihapus (ADHOC-06, 2026-07-29):** window "Pending NOC", aksi "Oncheck NOC", kolom `noc_checked_at`, endpoint `tickets.oncheck-noc`, guard `assertNocCheckedBeforeClose()`, dan flag `can_oncheck_noc`. Dulu tiket menggantung di state "sudah dikirim tapi belum diterima" sampai NOC menekan Oncheck. Pada praktiknya assign = mulai kerja, jadi langkah itu cuma menambah klik tanpa mengubah siapa yang mengerjakan.

Tiket di tangan NOC dipegang **berdua**: `helpdesk` (yang mengirim) **dan** `noc` (yang mengerjakan). Helpdesk tetap boleh Selesaikan/Assign FOP/Batalkan tiketnya sendiri — pilihan sadar supaya tiket tidak macet kalau NOC belum sempat menyentuhnya.

```php
// Ticket::holderRoles() — SATU-SATUNYA sumber; dipakai
// TicketService::assertActorOwnsTicket() (otorisasi asli) DAN
// Ticket::actionFlagsFor() (gerbang tampilan tombol).
match ($this->handler) {
    TicketHandler::HELPDESK => ['helpdesk'],
    TicketHandler::NOC      => ['helpdesk', 'noc'],   // dipegang berdua
    TicketHandler::FOP      => [],                    // terminal
};
```

**Kenapa ada jendela ini sama sekali.** Kalau Helpdesk langsung kehilangan akses begitu klik "Ke NOC", tiket menggantung tanpa pemilik selama NOC belum sempat membuka worksheet-nya — pelanggan nelpon lagi, Helpdesk gak bisa apa-apa. Sebaliknya kalau Helpdesk memegang kendali selamanya, dua orang bisa Close/Assign FOP di objek yang sama nyaris bersamaan (`FopTask` dobel, riwayat konflik). Titik serah-terima yang eksplisit menutup dua-duanya.

NOC boleh langsung Selesaikan/Assign FOP/Kembalikan/Batalkan begitu tiket masuk — tidak ada langkah klaim yang harus dilewati dulu.

**`returnToHelpdesk()`** mengembalikan `handler` ke `helpdesk`; tiket bisa dikirim ulang ke NOC kapan pun tanpa state sisa.

## 4. Dua Jalur Masuk, Satu Logic

`TicketService::create()` melayani dua aktor lewat satu jalur, dibedakan parameter `$fopOrigin` + `$assignment`:

| Jalur | Siapa | Endpoint | Hasil |
|---|---|---|---|
| Submit worksheet | Helpdesk/NOC/Sales/Admin/POP Admin, dari `/tickets/new` | `POST /tickets` | `handler=HELPDESK`, `status=OPEN`, **tanpa FopTask** |
| Submit + assign langsung | FOP, dari modal "Tambah Task FOP" (`/fop-tasks`) | `POST /tickets` + `origin=fop_tasks` | `handler=FOP` langsung, `FopTask` kebentuk; kalau `technicians[]` terisi → langsung Terjadwal + `Task` eksekusi |

**Kenapa aman dari self-assign:** field `technicians[]`/`task_date` cuma diproses kalau aktor punya `fop_tasks.create`:

```php
$assignment = [];
if (!empty($validated['technicians']) && auth()->user()->hasPermission('fop_tasks.create')) {
    $assignment = ['technicians' => $validated['technicians'], 'task_date' => $validated['task_date'] ?? null];
}
```

Helpdesk yang nge-craft request manual lewat devtools TETAP diabaikan diam-diam (bukan 422/403) — tiket tetap kebentuk normal di tangan Helpdesk.

## 5. Snapshot Data Pelanggan

8 kolom pelanggan di `tickets` (`customer_name`, `customer_address`, `customer_phone`, `customer_odp`, `customer_package`, `customer_device`, `customer_latitude`, `customer_longitude`) diisi **sekali** saat tiket dibuat (`TicketService::snapshotCustomer()`), **BUKAN** dibaca live.

**Kenapa dibekukan:** tiket adalah catatan keluhan pada satu titik waktu. Kalau dibaca live, riwayat tiket lama "berubah" begitu pelanggan pindah alamat atau ganti paket — padahal bukan itu yang terjadi saat keluhan dilaporkan.

**Pengecualian — POP tidak di-snapshot sebagai teks:** `tickets.pop_id` tetap FK ke master data; ID-nya sendiri sudah cukup jadi jangkar historis.

**Pengecualian — CID juga tidak di-snapshot:** CID itu identitas permanen yang terikat ke `customer_id`, bukan data yang berubah-ubah. Selalu dibaca live via `$ticket->customer->display_id`. Lihat § 10 untuk bug yang pernah terjadi di sini.

**Urutan resolusi ODP:** `customers.odp_code` diprioritaskan, fallback ke `customer_devices.odp` — urutan ini diduplikasi identik di `TicketService::snapshotCustomer()` dan `TicketController::customerPayload()`, sengaja gak saling manggil biar independen.

## 6. Aksi & Guard

Semua aksi lewat `TicketService`, semuanya dibuka dengan `lockForUpdate()` di dalam transaksi **sebelum** guard dicek — dua request nyaris bersamaan wajib antre, bukan dua-duanya lolos sebelum salah satu commit (TOCTOU).

| Aksi | Method | Guard tambahan | Efek | Notifikasi in-app |
|---|---|---|---|---|
| Selesai | `close()` | `assertNocCheckedBeforeClose()` | `status=CLOSED` | Pembuat tiket (skip kalau actor = pembuat) — SUCCESS |
| Ke NOC | `escalateToNoc()` | wajib `handler=HELPDESK` | `handler=NOC` (langsung Diproses NOC) | Semua user role `noc` di POP tiket — INFO |
| Ke FOP | `escalateToFop()` | — (boleh dari Helpdesk maupun NOC) | `handler=FOP`, `FopTask` DRAFT kebentuk | Semua user role `fop` di POP FopTask — INFO |
| Kembalikan | `returnToHelpdesk()` | wajib `handler=NOC` | `handler=HELPDESK` | Pembuat tiket (skip kalau actor = pembuat) — WARNING |
| Batalkan | `cancel()` | `reason` **wajib** (validasi controller) | `status=CANCELLED` | Pembuat tiket (skip kalau actor = pembuat) — ERROR |

**Notifikasi (`AppNotification`, ditambahkan 2026-08-06 — `docs/plan/analisa-status-implementasi-notifikasi.md` §8.1) jalan lewat 2 pola:**
- **Role-wide** (`escalateToNoc`/`escalateToFop`) — semua user berrole terkait yang scope POP-nya nyakup tiket ini kena notif, gak ada skip-self. Ini "antrean baru masuk", bukan aksi personal — begitu tiket masuk NOC gak ada langkah "terima" (ADHOC-06, § 3), jadi lonceng notifikasi ini SATU-SATUNYA sinyal personal yang NOC dapat di luar buka Worksheet manual.
- **Personal ke pembuat** (`close`/`cancel`/`returnToHelpdesk`) — notif ke `created_by`, **di-skip kalau yang aksi = pembuat tiket sendiri** (mis. Helpdesk yang nutup tiket bikinannya sendiri gak perlu dikasih tau — dia yang ngelakuin). Berguna terutama saat NOC yang menyelesaikan/membatalkan/mengembalikan tiket yang tadinya dikirim Helpdesk.

Broadcast realtime (`TicketQueueUpdated`, § 13) TETAP ada di semua 5 aksi — dua mekanisme ini independen: broadcast cuma nyala kalau Worksheet-nya lagi kebuka (auto-refresh tabel), notifikasi lonceng persisten & masuk `/notifications` walau user lagi di halaman lain. Lihat `docs/plan/analisa-status-implementasi-notifikasi.md` §6.1 buat penjelasan lengkap bedanya.

Dua guard yang berlaku ke hampir semua aksi:

- **`assertActorOwnsTicket()`** — aktor harus punya salah satu role dari `holderRoles()` (§ 3). Full-access (owner/admin) dibebaskan.
- **`assertTicketStillOpen()`** — tolak kalau `handler=FOP` ("udah di FOP, lihat Task FOP"), `status=CLOSED`, atau `status=CANCELLED`.

**Semua aksi lewat `assertActorOwnsTicket()`** yang membaca `Ticket::holderRoles()` — tidak ada aksi yang mengecek role secara langsung lagi (dulu `onCheckNoc()` melakukan itu karena Oncheck khusus NOC).

**Kenapa `reason` wajib khusus Batalkan:** membatalkan keluhan pelanggan itu keputusan yang menutup jalur, beda dari menyelesaikan (yang hasilnya terbukti dari kondisi layanan). Tanpa alasan tertulis, audit gak punya apa-apa buat ditelusuri.

## 7. Pembatalan: Dua Pintu, Dua Wewenang

| | Tiket **pra-FOP** (`handler` helpdesk/noc) | Tiket **pasca-FOP** (`handler=fop`) |
|---|---|---|
| Endpoint | `POST /tickets/{id}/cancel` | `PUT /fop-tasks/{id}` status=dibatalkan |
| Permission | `tickets.cancel` | `fop_tasks.cancel` |
| Riwayat | 1 baris (`ticket_histories`) | 2 baris (`ticket_histories` + `fop_task_status_history`) |
| Siapa | pihak yang lagi pegang tiket | FOP/admin/owner |

Endpoint Ticketing **menolak** tiket yang sudah di FOP (`assertTicketStillOpen()`), dan sebaliknya `/fop-tasks` gak bisa menyentuh tiket yang belum punya `FopTask`. Dua pintu ini gak tumpang tindih.

> Sebelumnya modul Ticketing sama sekali gak punya endpoint cancel — satu-satunya jalur adalah `/fop-tasks`. Akibatnya tiket yang masih di meja Helpdesk **tidak bisa dibatalkan sama sekali** (salah input, pelanggan batal komplain) tanpa mengeskalasinya dulu ke FOP hanya untuk dibatalkan di sana. `tickets.cancel` menutup lubang itu.

### RBAC pembatalan pasca-FOP — 3 lapis

| | `fop_tasks.cancel` | `task.cancel` | `TaskPolicy::cancelViaFopTask()` |
|---|---|---|---|
| Batalin | `FopTask` (tiket) | `Task` langsung dari `/tasks` | `Task` sebagai efek cascade dari `/fop-tasks` |
| Role default | owner, admin, fop | owner, fop (**admin TIDAK punya**) | Otoritasnya = `fop_tasks.cancel`, BUKAN `task.cancel` |

**Kenapa cascade pakai `fop_tasks.cancel`:** role `admin` punya `fop_tasks.*` tapi gak punya `task.cancel`. Kalau cascade dipaksa lewat `task.cancel`, admin kehilangan kemampuan membatalkan tiket yang selama ini dia punya.

**Invarian:** `cancelViaFopTask()` memeriksa `task_type` milik **Task yang beneran dibatalkan** (bukan `FopTask.category`) — kalau dua kolom itu menyimpang, tiket MTN gak bisa jadi jalan pintas membatalkan Task SURVEY/PSB. `TaskPolicy::before()` sengaja mengecualikan method ini dari bypass wildcard owner.

## 8. Bucket & Halaman

`TicketBucket` masih ada sebagai **klasifikasi**, tapi sudah **bukan lagi route**. Dipakai `Ticket::bucket()`/`scopeInBucket()` buat aksen visual, badge, dan query halaman arsip.

| Bucket | Kondisi `handler=FOP` (dari `FopTask.status`) | Kondisi internal (`handler` helpdesk/noc) |
|---|---|---|
| Masuk | `draft` | `handler=HELPDESK` & `status=OPEN` |
| Diproses | `terjadwal`, `in_progress`, `pending` | `handler=NOC` & `status=OPEN` |
| Selesai | `selesai` | `status=CLOSED` |
| Dibatalkan | `dibatalkan` + orphan (`handler=FOP` & `fop_task_id` NULL) | `status=CANCELLED` |

Empat bucket ini wajib saling lepas dan menutupi seluruh `TaskStatus` — dijaga `test_buckets_cover_every_task_status_exactly_once()`.

**Pemetaan bucket → halaman** (sejak restrukturisasi 2026-07-28):

| Bucket | Dulu | Sekarang |
|---|---|---|
| Masuk | `/tickets/masuk` | Tab **Ticket** di panel List Task Ticketing (handler=helpdesk) |
| Diproses | `/tickets/diproses` | Tab **Assign NOC**/**Assign FOP** di panel + tab **Tiket Masuk** Worksheet NOC (handler=noc & open) |
| Selesai | `/tickets/selesai` (bucket) | Halaman sendiri, controller + permission sendiri |
| Dibatalkan | `/tickets/dibatalkan` (bucket) | idem |

Panel **List Task Ticketing** di `/tickets/new` memfilter per **`handler`**, bukan per bucket — tab Ticket / Assign NOC / Assign FOP menjawab "tiket ini lagi di tangan siapa", pertanyaan yang beda dari "sudah sampai tahap mana pengerjaannya".

## 9. Halaman Punya Permission Masing-masing

Sebelumnya semua daftar tiket numpang satu route `/tickets/{bucket}` dengan `tickets.view`, jadi memberi akses "lihat arsip" otomatis memberi akses "lihat antrean kerja NOC" — gak bisa dipisah di Role Matrix. Sekarang tiap halaman punya feature sendiri (pola sama `customers.terminated`/`customers.failed`):

```
tickets              (root)   → view, create, update, cancel
  ├─ tickets.selesai          → view
  └─ tickets.dibatalkan       → view
noc_worksheet        (root)   → view          (penanda modul, buat dependency chaining)
  ├─ noc_worksheet.masuk      → view
  └─ noc_worksheet.diproses   → view
noc_dashboard        (root)   → view
```

Semuanya ditanam `TicketFeatureSeeder`, permission-nya digenerate `PermissionGeneratorService` dari `config/rbac.php`. Label kontekstual per permission diatur di `permission_name_overrides` biar di Role Matrix kelihatan nama halamannya, bukan cuma "Lihat".

Tab yang user gak punya izinnya **tidak dirender sama sekali** (bukan cuma ditolak saat diklik) — `NocWorksheetController::worksheetTabs()` dan `TicketArchiveController::archiveTabs()` menyaring per permission.

## 10. Bug: CID Tampil Mentah di List (Fixed)

**Gejala:** daftar tiket menampilkan `RQ000007` padahal `customers.cid` menyimpan `C1X4CRQ000007`.

**Akar masalah:** eager-load customer dengan kolom dibatasi TANPA `pop_id`. `Customer::getDisplayIdAttribute()` butuh relasi `$this->pop`; tanpa `pop_id` ke-select relasi itu selalu `null` dan accessor diam-diam jatuh ke fallback paling awal (`if (!$pop) return $this->customer_code;`).

**Fix:** select wajib menyertakan `pop_id`, `status`, `distribution_id` + eager-load `customer.pop:id,name,cid_prefix`. Berlaku di `TicketArchiveController`, `NocWorksheetController`, `TicketController::worksheetTasks()`, dan `FopTaskController::index()`.

## 11. Dua History per Pembatalan Pasca-FOP

| Jalur cancel | History FOP (`fop_task_status_history`) | History Ticket (`ticket_histories`) |
|---|---|---|
| `/fop-tasks` (FOP batalin) | `FopTaskController::update()` | `FopTaskObserver` |
| `/tasks` (Task dibatalin, cascade naik) | `TaskObserver` | `FopTaskObserver` |
| `/tickets/{id}/cancel` (pra-FOP) | — (belum ada FopTask) | `TicketService::cancel()` |

`FopTaskObserver` adalah **satu-satunya** penulis sisi Ticket untuk pembatalan pasca-FOP — dua jalur tertutup tanpa nulis dobel (dijaga `test_assigned_ticket_cancellation_does_not_duplicate_histories`).

**Bug lama yang ditutup:** `TaskObserver` punya guard early-return begitu `FopTask` sudah `dibatalkan`; efek sampingnya cancel dari `/fop-tasks` gak pernah nulis history FOP. Fix: `FopTaskController::update()` nulis `FopTaskStatusHistory` sendiri persis di titik itu, di luar jangkauan guard.

**Prinsip "satu aksi dua riwayat" hanya berlaku pasca-FOP.** Aksi pra-FOP (close/escalate/oncheck/return/cancel) cuma nulis `ticket_histories` — belum ada FopTask buat dicatat sisi lainnya.

## 12. Dialog Konfirmasi & Input Alasan

Semua aksi tiket di **semua** halaman lewat satu helper `window.confirmTicketAction()` (`tickets/partials/action-dialog.blade.php`), yang numpang `window.Dialog` global (`components/dialog.blade.php`).

**Kenapa bukan `confirm()` native:** `confirm()` cuma bisa Ya/Tidak — gak bisa menampung textarea alasan. Padahal `reason` itu yang mengisi `ticket_histories.reason`; selama panel worksheet masih pakai `confirm()`, kolom itu **selalu kosong** dari sana meski backend-nya sudah siap menerima.

**Kenapa bukan modal sendiri per halaman:** sempat begitu — tiga file punya markup modal yang hampir identik, dan halaman detail punya versi Alpine keempat. Empat implementasi untuk satu perilaku artinya empat tempat yang harus diperbaiki setiap ada perubahan.

Catatan teknis: `Dialog.show()` men-*disable* tombol konfirmasi begitu diklik (proteksi double-submit). Kalau validasi alasan gagal, tombol itu harus dihidupkan lagi lewat `e.currentTarget` — kalau tidak, dialognya buntu dan user gak bisa submit ulang setelah mengisi alasan.

Halaman detail memakai POST native (form dirakit on-the-fly, diberi class `no-confirm` agar tidak kena listener `submit` global yang akan memunculkan dialog konfirmasi kedua); halaman list memakai `fetch()` JSON agar baris hilang in-place tanpa navigasi.

## 13. Auto-Refresh Realtime

`TicketQueueUpdated` di-broadcast **setelah** `DB::transaction()` commit (bukan di dalam closure — gak boleh nembak kalau rollback), dengan `toOthers()` supaya tab aktor sendiri gak refetch dobel.

Channel `tickets.{popId}` diotorisasi lewat `EffectiveAccessService::hasAllPopAccess()`/`getAllowedPopIds()` — jalur POP-scope yang benar. Channel `fop.{pop_id}` (dashboard FOP) dulu masih pakai `$user->pops()` legacy yang gak paham `pop_tree`; sudah diseragamkan ke pola yang sama per 2026-08-07 (`docs/plan/analisa-status-implementasi-notifikasi.md` §8, `routes/channels.php`).

Listener me-*refetch* sendiri lewat endpoint yang sudah lolos scope & permission user, bukan mempercayai payload broadcast mentah. Panel worksheet menarik `/api/tickets/worksheet-tasks`; Dashboard NOC me-refetch halamannya sendiri lalu menukar `innerHTML` per container.

## 14. Format `tugas`: `{CID}_{Nama}`

`FopTask.tugas` untuk SURVEY, PSB, MTN, dan C-REQ memakai format `"{customer->display_id}_{customer->full_name}"`, mis. `C1X4ARQ000631_Masudah Yuni Fitri` — bukan label generik. Konsisten dengan identitas pelanggan di seluruh sistem.

`composeFopNotes()` (isi `fop_tasks.notes`) SENGAJA cuma pointer pendek (`"Ticket TKT-xxx — dikirim oleh yyy."`), **BUKAN** menyalin ulang `catatan_teknis` — menyalin bikin dua sumber kebenaran yang menyimpang begitu salah satu diedit.

Prinsip yang sama berlaku waktu `Task` eksekusi teknisi kebentuk (`TicketService::assignTechnicians()` / `FopTaskController::store()`/`update()`): `task->description` cuma diisi dari `detail_keluhan`/`issue` — **BUKAN** digabung sama `catatan_teknis`/`notes`. Dua field itu tetap harus kebaca teknisi, tapi lewat box terpisah di `tasks/show.blade.php`, bukan digabung jadi satu string (fix 2026-08-07, detail: [docs/task-teknisi/business-logic.md § 9](../task-teknisi/business-logic.md#9-pemisahan-catatan--issue-teknis-catatan-fop-catatan-teknis-noc)).

## 15. Restriksi Hapus Task FOP

`FopTaskController::destroy()` menolak (422):

| Kategori | Kenapa ditolak |
|---|---|
| SURVEY, PSB | `destroy()` mentransisikan customer ke `rejected` sebagai efek samping — konsekuensi bisnis nyata, harus disengaja lewat halaman Pelanggan |
| MTN, C-REQ **yang punya `ticket` terkait** | Riwayat pengirim harus tetap bisa ditelusuri — hapus `FopTask` gak boleh bikin jejak Ticketing jadi yatim |

MTN/C-REQ yang dibuat manual langsung di `/fop-tasks` (tanpa `ticket`) tetap boleh dihapus.

## 16. Target SLA Ticketing

Sebelum `2026_08_05`, Ticketing gak punya SLA sama sekali — `handling_sla_hours` cuma ada di `FopTask`, jadi selama tiket masih di tangan Helpdesk/NOC (`handler` ≠ FOP) gak ada deadline terukur, dan tiket yang gak pernah dieskalasi ke FOP gak pernah dapet SLA. Analisa lengkap: `docs/plan/analisa-target-sla-ticketing.md`.

**Sekarang: satu clock SLA, dua panggung.**

1. **Snapshot di titik tiket lahir** — `TicketService::create()` isi `tickets.sla_hours` + `tickets.sla_deadline_at` lewat `resolveSlaHours()`, ANCHOR-nya selalu `created_at` tiket (Ticketing cuma bikin MTN & C-REQ, dua-duanya anchor `created_at` per Master Timeline SLA — lihat `docs/master/sla-timeline/business-logic.md` § 2).
2. **Dua jalur resolusi**, dipilih lewat `ticket_issue_categories.sla_source`:
   - `'paket'` (default) → `InternetPackage::getHandlingSla($type)`, fallback `TaskType::defaultHandlingSlaHours()`.
   - `'prioritas'` → `FopTaskPriority::slaHours()` (matrix: Urgent=4j, High=8j, Medium=24j, Low=48j). Sebelum ini `sla_source='prioritas'` adalah **dead config** — ada di DB/UI tapi gak pernah dibaca backend.
3. **Eskalasi ke FOP mewarisi, gak hitung ulang** — `TicketService::syncToFopTask()` isi `fop_tasks.handling_sla_hours` langsung dari `$ticket->sla_hours`. `FopTask::booted()` skip resolve sendiri karena kolomnya udah gak `null`. Deadline gak reset di titik handoff Ticketing → FOP.
4. **Tiket yang gak pernah ke FOP tetap punya SLA** — karena snapshot-nya nempel di `Ticket` sendiri (bukan nunggu `FopTask` lahir), tiket yang ditutup langsung Helpdesk/NOC tetap bisa dicek breach lewat `Ticket::isSlaBreached()`.

**Tampilan** — dua rezim, beda level "kehidupan":
- **Detail Tiket** (`tickets/show.blade.php`) — countdown LIVE (`<x-countdown-timer>`) selama tiket masih jalan & belum di FOP; badge statis on-time/lewat-SLA begitu resolved atau sudah diserahkan ke FOP.
- **Worksheet, Arsip, History** (`create.blade.php`, `archive.blade.php`, `history.blade.php`) — badge statis (`Ticket::slaBadgeLabel()`/`slaBadgeClasses()`), precomputed server-side, ngikut refresh/broadcast — BUKAN countdown detik-per-detik. Worksheet-nya JS-driven (Alpine `x-for` atas payload JSON), Blade component `<x-countdown-timer>` gak bisa ditembak per-baris dari situ.

**Belum ada:** notifikasi/eskalasi otomatis saat breach (cuma indikator visual pasif, sengaja di luar scope — sama kayak SLA FopTask), dan auto-naikin `priority` tiket berdasar sisa waktu (beda dari `FopTaskController::autoSyncAndCalculatePriority()` yang cuma jalan buat SURVEY/PEMASANGAN di sisi FopTask).

## 17. Worksheet Helpdesk — Sort Kolom & Keyboard Shortcut

Panel kanan Worksheet Helpdesk (`tickets/create.blade.php`) punya sort & navigasi keyboard di atas array `filteredTasks`/`sortedTasks` (clientside, cap 30 baris). Spec lengkap & histori keputusan: `docs/plan/analisa-percepatan-alur-helpdesk-noc.md` § 6–8.

**Sort kolom** — klik header toggle ASC/DESC:

| Kolom | Field | Catatan |
|---|---|---|
| Ticket ID & Time | `code` (nomor tiket) | Bukan `created_at` |
| Status / Issue | `issue_category` | Bukan `status_label` |
| Pelanggan (CID & Contact) | — | Sengaja gak sortable |
| Lokasi / POP / ODP | `odp` | Bukan nama POP |

Sort manual override urutan default server (`latest('created_at')`) sampai user reload halaman.

**Navigasi keyboard** (semua nonaktif kalau fokus lagi di input/textarea/select, atau drawer detail kebuka):

| Tombol | Aksi |
|---|---|
| `N` | Toggle panel Create New Ticket (existing, gak diubah) |
| `↑` / `↓` | Pindah fokus antar row (state `focusedTicketId`) |
| `←` / `→` | Pindah tab Ticket/Assign NOC/Assign FOP, reset fokus ke row pertama |
| `Enter` | Buka drawer detail row yang fokus |
| `C` / `V` / `B` | Close / Ke NOC / Ke FOP row yang fokus — digerbangi `task.actions` (sumber sama kayak tombol Quick Dispatch), modal konfirmasi § 12 **tetap muncul** (sengaja gak di-skip) |

Shortcut ini jalur TAMBAHAN — manggil fungsi JS yang sama persis (`closeTicket()`, `escalateTicket()`, `openTicketDetail()`) dengan tombol Quick Dispatch yang udah ada di baris tabel/kartu dan tombol aksi di drawer detail. Gak ada tombol yang dihapus/diganti.

**Batalkan & Kembalikan ke Helpdesk sengaja gak dikasih hotkey** — Batalkan butuh alasan wajib (gak cocok jadi aksi 1-tombol), Kembalikan cuma relevan buat NOC.

**Sinkronisasi state drawer** — `detail-drawer.blade.php` dispatch `ticket-drawer-shown`/`ticket-drawer-hidden` tiap `shown` beneran berubah (nangkep SEMUA jalur tutup: tombol X, klik backdrop, Escape, atau `close-ticket-drawer` dari luar). Worksheet dengerin event itu buat nonaktifin navigasi keyboard selama drawer kebuka — **bukan** `open-ticket-drawer`/`close-ticket-drawer` (itu event permintaan, bukan notifikasi state, gak reliable buat tutup manual).

## 18. Kategori Batch, No. HP Pelapor & Search Free-Text (2026-09-09)

Empat revisi Worksheet Helpdesk, dikerjakan di luar sprint aktif atas permintaan eksplisit user:

**Search Customer Data jadi dua mode** — input yang sama (`cidQuery`) tetap coba lookup CID/nama/HP seperti biasa (`TicketController::lookupCustomer()`, gak berubah). Bedanya cuma di sisi FORM: kalau kategori issue yang dipilih ber-checklist `is_batch` (lihat di bawah) DAN gak ada hasil yang di-`pick()`, teks yang diketik dipakai APA ADANYA sebagai label tiket (numpang kolom snapshot `tickets.customer_name`, **bukan kolom baru**) — bukan error "tidak ditemukan". Mode ini dipilih dari KATEGORI, bukan dari ada/tidaknya hasil pencarian (`Ticket::isBatch()`/`TicketIssueCategory::is_batch`).

**Dropdown POP manual** — muncul (`x-show="!selected && selectedCategoryIsBatch"`) begitu mode label-bebas aktif, karena gak ada satu pelanggan pun yang bisa dijadiin acuan POP. Daftar POP dari `Pop::forUser()` (variabel `$allowedPops`, beda dari `$allowedPopIds` yang cuma array ID buat subscribe channel realtime).

**No. HP Pelapor** (`tickets.reporter_phone`, nullable) — input opsional di kedua mode, buat laporan dari grup komplain yang pelapornya beda dari pelanggan di data master. `Ticket::contactPhone()` = `reporter_phone` kalau diisi, fallback HP pelanggan (data master atau snapshot) kalau kosong — SATU-SATUNYA sumber buat kolom kontak di List Task (`TicketController::worksheetCardPayload()`).

**Kategori Batch → tiket Parent/Child** — `ticket_issue_categories.is_batch` (boolean, default false, Master Issue). Kategori kayak "ODP LOS" yang berdampak ke banyak pelanggan sekaligus dicentang ini.

- Tiket batch **gak menunjuk satu pelanggan** — `tickets.customer_id` jadi nullable (migration `add_batch_and_reporter_fields_to_tickets_table`), diisi lewat `TicketService::createBatchTicket()` (dipanggil dari `create()` kalau `$issueCategory?->is_batch && empty($data['customer_id'])`).
- **Child BUKAN Ticket/FopTask baru** — sengaja gak ada `TKT-`/`TFOP-` per pelanggan terdampak, gak lewat sync `TaskService`/`FopTaskTeamService` sama sekali. Rasionalnya: satu ODP rusak = SATU perbaikan lapangan (satu FopTask lewat `escalateToFop()` di tiket PARENT), bukan N tugas identik. Child cuma catatan informasional di tabel `ticket_batch_members` (`ticket_id`, `customer_id` nullable, `cid`/`customer_name`/`phone` snapshot, `added_by`) — lihat `TicketBatchMember`.
- **Tombol "Tambah"** di List Task (Quick Dispatch Actions, cuma muncul kalau `task.is_batch`) toggle expand/collapse baris child, lalu modal "Tambah Pelanggan Terdampak" (`TicketService::addBatchMember()` via `POST tickets.batch-members.store`) — bisa dari lookup CID (nama/HP ambil dari data master) atau input manual (belum ketemu lewat lookup).
- **Modal ini SENGAJA menyimpang dari pola default repo** ("mutasi data + input majemuk → halaman create tersendiri", lihat CLAUDE.md § Konvensi Kode) — keputusan eksplisit user, ditutup dengan submit AJAX (bukan native POST + `back()->withErrors()`): validasi gagal balik JSON 422 yang dibaca modal langsung, jadi gak kena masalah ADHOC-20 (modal nutup nampilin List kosong tanpa pesan). URL endpoint tetap dirender server-side per tiket (`batch_members_store_url` di `worksheetCardPayload()`) — klien TIDAK merakit path dari `ticket.id` (lihat `PostTargetRenderedServerSideTest`).
- `TicketService::resolveSlaHours()` nerima `?Customer` (sebelumnya wajib) — tiket batch gak punya pelanggan buat diacu SLA `sla_source='paket'`, fallback `TaskType::defaultHandlingSlaHours()`.

Test: `tests/Feature/TicketBatchTest.php` (9 kasus).

**Perbaikan pasca-review (sesi lanjutan, laporan user)** — dua bug: (1) Helper "Pelanggan tidak ditemukan. Silakan periksa kembali CID atau Nama." dicabut dari Search Customer Data — pesan generik itu gak relevan lagi begitu field yang sama juga dipakai sebagai label bebas. (2) **`is_batch` gak pernah bisa diaktifkan sama sekali** — CRUD Master Issue (`Master\TicketIssueCategoryController`, `master/ticket-issue-categories/{create,edit}.blade.php`) gak pernah disentuh waktu kolom ini ditambahkan: `validateCategory()` gak menerima field itu, form gak punya checkbox-nya. Akibatnya dropdown POP manual & mode label-bebas di Worksheet Helpdesk gak pernah nongol di real usage — bukan bug di komponen Alpine-nya, tapi gak ada satu kategori pun di DB yang beneran `is_batch=true`. Fix: checkbox "Support Batch (Parent/Child)" di create & edit + `validateCategory()` normalisasi `$request->boolean('is_batch')` (checkbox unchecked gak ngirim field sama sekali — WAJIB dinormalisasi eksplisit, bukan dibiarkan absen dari `$validated`, supaya uncheck beneran reset ke false pas update, bukan nyangkut true). Badge "Batch" ditambahkan di kolom Nama Kategori index listing. Test: `TicketIssueCategoryCRUDTest::test_is_batch_checkbox_can_be_toggled_on_and_off`.

**Perbaikan kelima (sesi lanjutan) — teknisi gak lihat daftar pelanggan terdampak di Task-nya.** Laporan user: "ketika dikirim ke NOC sudah benar, tapi dikirim ke FOP dan ditugaskan ke Teknisi itu masih salah". Diagnosa: eskalasi ke FOP & assign teknisi sendiri sudah AMAN dari crash (di-sweep menyeluruh — `FopTaskController::store()/update()`, `TaskService::create()`, semua view teknisi sudah null-safe atas `customer_id`/`customer` sejak sebelum fitur batch ada), tapi teknisi yang beneran ditugaskan **gak punya cara lihat siapa aja 30 pelanggan yang terdampak** — Detail Task cuma nampilin label ODP di judul, gak ada daftarnya sama sekali. Fix (BUKAN bug crash, murni gap tampilan):
- `TaskController::show()`/`indexOwn()` eager-load `fopTask.ticket.batchMembers` + `fopTask.ticket.issueCategory`.
- `tasks/show.blade.php` (Detail Task) — blok baru "Pelanggan Terdampak" (di bawah Issue/Keluhan) nampilin SEMUA `batchMembers` (Nama/CID/HP) kalau `$task->fopTask?->ticket?->isBatch()`.
- `tasks/partials/own-card.blade.php` (kartu "Task Saya") — badge "N Pelanggan" di header kartu buat kasus yang sama, biar keliatan dari list tanpa buka detail dulu.
- Ditemukan efek samping saat menulis blok baru: shorthand `@php($var = expr)` gagal dikompilasi Blade kalau `expr`-nya pakai nullsafe `?->` diikuti baris `@if` persis di bawahnya (compiler nge-drop `?>` penutup, sisa template ke-swallow jadi raw PHP → `ParseError`). Diganti bentuk blok penuh `@php ... @endphp` — aman.

Test: `TicketBatchTest::test_technician_sees_affected_customers_on_assigned_task` — full chain: buat tiket batch → tambah 2 batch member → escalate ke FOP → assign teknisi lewat `/fop-tasks/{id}` → GET Detail Task teknisi → assert kedua nama pelanggan & badge "2 Pelanggan" muncul.

**Perbaikan keenam (sesi lanjutan, pertanyaan user) — "Detail Task" ternyata DUA halaman beda, satu belum kesentuh.** `tasks/show.blade.php` (yang difix di atas) itu Detail Task versi TEKNISI. FOP punya Detail Task VERSI SENDIRI — `fop_tasks/history_detail.blade.php` (`FopTaskController::showHistory()`, dibuka dari papan `/fop-tasks` → History), dan itu belum disentuh sama sekali — blok "Data Pelanggan (saat ticket dibuat)" di sana nampilin CID/Nama/HP/dll versi single-customer yang dash semua buat tiket batch (gak ada satu pelanggan yang diacu), tanpa daftar batchMembers sama sekali. Fix: `showHistory()` eager-load `ticket.batchMembers` + `ticket.issueCategory:id,name,is_batch`; `history_detail.blade.php` dapat blok "Pelanggan Terdampak (N)" yang sama persis polanya dengan `tasks/show.blade.php`, disisipkan tepat di bawah grid "Data Pelanggan" (masih di dalam `@if($showTicketDetail)`, gerbang yang sama dgn blok Ticketing lain di halaman itu). Test: `TicketBatchTest::test_fop_history_detail_page_shows_affected_customers`.

**Konfirmasi + perbaikan keempat (sesi lanjutan) — Assign NOC/FOP cuma Parent, Arsip (Selesai/Dibatalkan/History) nampilin Parent+Child.** User menegaskan dua aturan yang belum eksplisit ditest sebelumnya:

1. **Escalate ke NOC/FOP cuma ngirim tiket PARENT** — child (`ticket_batch_members`) TIDAK PERNAH ikut ke `FopTask`/`composeFopNotes()`/histories NOC-FOP. Ini sebenarnya sudah benar by design sejak awal (child cuma baris informasional, gak ada jalur kode yang membacanya di `escalateToNoc()`/`escalateToFop()`/`syncToFopTask()`), TAPI belum pernah dites — dan pas ditest, **ketemu bug nyata**: `escalateToFop()` manggil `Customer::query()->findOrFail($ticket->customer_id)` TANPA cek null dulu — tiket batch punya `customer_id` null, jadi `findOrFail(null)` selalu 404. **Eskalasi ke FOP buat SEMUA tiket batch gagal total sebelum fix ini** (bukan cuma soal notes ikut/gak ikut child — tombol "Ke FOP" di Worksheet Helpdesk 404 mentah-mentah). Fix: `escalateToFop()` + `syncToFopTask()` sekarang terima `?Customer` nullable — kalau null (tiket batch), `FopTask.tugas` numpang label bebas (`ticket->customer_name`, mis. "ODP JTS 13 LOS") gantiin format `{CID}_{Nama}`, `village_id`/`customer_id` dibiarkan null (sudah nullable di skema `fop_tasks`), `pop_id` fallback ke `ticket->pop_id`, `handling_sla_hours` fallback ke default tipe (gak ada paket buat diacu). Test: `TicketBatchTest::test_escalating_batch_ticket_sends_only_parent_not_children`.
2. **Ticket Selesai, Ticket Dibatalkan, dan History Ticketing nampilin Parent + SEMUA Child** — beda dari Worksheet Helpdesk yang cuma Parent. `TicketArchiveController`/`TicketHistoryController` (index, bukan export) eager-load `batchMembers`; `partials/archive.blade.php` render blok "Pelanggan Terdampak (N)" statis (gak perlu expand/collapse, tiket arsip udah kelar) di bawah baris Parent kalau `$ticket->isBatch()`; `tickets/history.blade.php` render baris `<tr>` child terpisah per anggota (kolom Nama/CID/HP terisi, sisanya kosong) tepat di bawah baris Parent. Test: `TicketBatchTest::test_archive_page_shows_parent_and_children_of_closed_batch_ticket`, `test_history_page_shows_parent_and_children_of_batch_ticket`.

**Perbaikan ketiga (sesi lanjutan) — modal Tambah Pelanggan Terdampak auto-isi Nama/HP dari pick CID, tetap bisa diedit.** `pickBatchCustomer()` sekarang isi `batchModal.customerName`/`phone` dari data master begitu staf pilih hasil pencarian (sebelumnya kosong, staf harus ketik ulang manual walau udah pick CID); dua input itu juga gak lagi `disabled` pasca-pick. Backend `TicketService::addBatchMember()` DIBALIK urutan prioritasnya — sebelumnya data master SELALU menang atas input (`$customer?->full_name ?: $data['customer_name']`), sekarang input yang diketik staf (termasuk hasil edit manual pasca-autofill) yang menang, fallback ke data master cuma kalau field dikosongkan (`$data['customer_name'] ?? null) ?: $customer?->full_name`). `customer_id` tetap kesimpen buat jejak link ke pelanggan aslinya walau nama/HP yang ditampilkan menyimpang. Test: `TicketBatchTest::test_edited_name_and_phone_override_customer_master_data`.

**Perbaikan kedua (sesi lanjutan) — urutan alur nyata gak cocok sama gerbang dropdown POP.** Contoh alur ODP LOS dari user: isi Search Customer Data ("ODP JTS 13 LOS") → pilih POP (JETIS) → **baru** pilih Kategori Issue ("ODP LOS") belakangan. Gerbang dropdown POP yang lama (`x-show="!selected && selectedCategoryIsBatch"`) nunggu kategori batch dipilih DULU sebelum dropdown-nya nongol — gak match urutan itu (di titik pilih POP, kategori belum dipilih sama sekali, `selectedCategoryIsBatch` masih false). Fix: gerbang diganti murni dari HASIL PENCARIAN (`!selected && searched && results.length === 0`) — dropdown POP + helper info-nya nongol begitu search gak match pelanggan manapun, terlepas dari kategori sudah dipilih atau belum. `selectedCategoryIsBatch` TETAP dipakai di `canSubmit()`/`submitForm()` (submit tetap gak boleh lolos tanpa kategori beneran batch) — cuma VISIBILITAS dropdown yang gak lagi nunggu kategori.

---

**Last updated:** 2026-08-05
