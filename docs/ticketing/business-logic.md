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
| `noc_checked_at` | timestamp nullable | Penanda NOC sudah resmi ambil alih (lihat § 3) |

State yang mungkin:

| `handler` | `status` | `noc_checked_at` | Label | Siapa yang boleh bertindak |
|---|---|---|---|---|
| `helpdesk` | `open` | — | Ditangani Helpdesk | `helpdesk` |
| `noc` | `open` | NULL | **Pending NOC** | `helpdesk` **DAN** `noc` |
| `noc` | `open` | terisi | **OnCheck NOC** | `noc` saja |
| `helpdesk`/`noc` | `closed` | — | Selesai (…) | tidak ada (final) |
| `helpdesk`/`noc` | `cancelled` | — | Dibatalkan (…) | tidak ada (final) |
| `fop` | (diabaikan) | (diabaikan) | turunan `FopTask.status` | tidak ada di sisi Ticketing |

**Kenapa tiket sekarang punya status sendiri.** Desain awal (`docs/plan/RANCANGAN_WORKSHEET_TICKETING.MD`) menganggap setiap tiket pasti berujung ke lapangan, jadi status tiket cukup diturunkan dari `FopTask`. Kenyataannya mayoritas keluhan (lemot, konfigurasi, routing) selesai dari meja Helpdesk/NOC tanpa teknisi sama sekali. Memaksa `FopTask` kebentuk cuma buat nampung status bikin papan FOP penuh tugas palsu. Sejak migrasi `2026_07_25_000003`, tiket berdiri sendiri sampai benar-benar butuh lapangan.

**Kenapa `handler` gak dilebur jadi satu enum status.** Dua sumbu ini ortogonal: `handler` = *siapa*, `status` = *sudah kelar atau belum*. Dilebur jadi satu enum bakal meledak jadi 3×3 kombinasi (`helpdesk_open`, `noc_closed`, …) yang harus dijaga manual setiap ada aktor baru.

**`handler=FOP` itu beku permanen.** Dipakai juga buat membedakan dua kasus `fop_task_id` NULL yang artinya beda: tiket yang **belum pernah** dieskalasi (`handler` masih helpdesk/noc) vs tiket yang **sudah pernah** tapi FopTask-nya dihapus FOP (orphan/"Terputus").

## 3. Window "Pending NOC" & Aksi Oncheck NOC

Begitu Helpdesk klik "Ke NOC" (`escalateToNoc()`), `handler` langsung jadi `noc` **tapi** `noc_checked_at` masih NULL. Di jendela ini Helpdesk **belum kehilangan akses** — dia masih boleh Selesaikan, Assign FOP, atau Batalkan tiketnya. NOC melihat tiket ini di tab **Ticket Masuk**.

NOC klik **"Oncheck NOC"** (`onCheckNoc()`) → `noc_checked_at` terisi, tiket pindah ke tab **Ticket Diproses**, dan mulai titik ini **cuma NOC** yang boleh bertindak.

```php
// Ticket::holderRoles() — SATU-SATUNYA sumber; dipakai
// TicketService::assertActorOwnsTicket() (otorisasi asli) DAN
// Ticket::actionFlagsFor() (gerbang tampilan tombol).
match ($this->handler) {
    TicketHandler::HELPDESK => ['helpdesk'],
    TicketHandler::NOC      => $this->noc_checked_at === null
                                 ? ['helpdesk', 'noc']   // window pending
                                 : ['noc'],              // sudah di-Oncheck
    TicketHandler::FOP      => [],                       // terminal
};
```

**Kenapa ada jendela ini sama sekali.** Kalau Helpdesk langsung kehilangan akses begitu klik "Ke NOC", tiket menggantung tanpa pemilik selama NOC belum sempat membuka worksheet-nya — pelanggan nelpon lagi, Helpdesk gak bisa apa-apa. Sebaliknya kalau Helpdesk memegang kendali selamanya, dua orang bisa Close/Assign FOP di objek yang sama nyaris bersamaan (`FopTask` dobel, riwayat konflik). Titik serah-terima yang eksplisit menutup dua-duanya.

**Guard tambahan: NOC wajib Oncheck sebelum boleh Selesaikan.** `assertNocCheckedBeforeClose()` menolak `close()` dari role `noc` selama `noc_checked_at` masih NULL — kalau enggak, "Oncheck NOC" cuma jadi tombol hiasan yang bisa dilewati. Aksi lain (Assign FOP, Kembalikan, Batalkan) **tidak** kena guard ini: NOC memang boleh langsung melempar/menolak tiket dari tab Ticket Masuk tanpa harus mengklaimnya dulu.

**`returnToHelpdesk()` me-reset `noc_checked_at` ke NULL** — kalau tiket dikirim ulang ke NOC belakangan, jendela pending mulai fresh lagi.

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

| Aksi | Method | Guard tambahan | Efek |
|---|---|---|---|
| Selesai | `close()` | `assertNocCheckedBeforeClose()` | `status=CLOSED` |
| Ke NOC | `escalateToNoc()` | wajib `handler=HELPDESK` | `handler=NOC`, `noc_checked_at=NULL` |
| Oncheck NOC | `onCheckNoc()` | wajib `handler=NOC`, aktor role `noc`/full-access, belum pernah di-check | `noc_checked_at=now()` |
| Ke FOP | `escalateToFop()` | — (boleh dari Helpdesk maupun NOC) | `handler=FOP`, `FopTask` DRAFT kebentuk |
| Kembalikan | `returnToHelpdesk()` | wajib `handler=NOC` | `handler=HELPDESK`, `noc_checked_at=NULL` |
| Batalkan | `cancel()` | `reason` **wajib** (validasi controller) | `status=CANCELLED` |

Dua guard yang berlaku ke hampir semua aksi:

- **`assertActorOwnsTicket()`** — aktor harus punya salah satu role dari `holderRoles()` (§ 3). Full-access (owner/admin) dibebaskan.
- **`assertTicketStillOpen()`** — tolak kalau `handler=FOP` ("udah di FOP, lihat Task FOP"), `status=CLOSED`, atau `status=CANCELLED`.

**`onCheckNoc()` sengaja TIDAK pakai `assertActorOwnsTicket()`** — di window pending guard itu mengizinkan `helpdesk` juga, padahal Oncheck spesifik cuma buat NOC. Jadi dia mengecek role secara langsung.

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
| Masuk | `/tickets/masuk` | Tab **Ticket** di panel List Task Ticketing (handler=helpdesk) + tab **Ticket Masuk** Worksheet NOC (pending NOC) |
| Diproses | `/tickets/diproses` | Tab **Assign NOC**/**Assign FOP** di panel + tab **Ticket Diproses** Worksheet NOC |
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

Channel `tickets.{popId}` diotorisasi lewat `EffectiveAccessService::hasAllPopAccess()`/`getAllowedPopIds()` — jalur POP-scope yang benar, **bukan** `$user->pops()` legacy yang dipakai channel `fop.{pop_id}` lama.

Listener me-*refetch* sendiri lewat endpoint yang sudah lolos scope & permission user, bukan mempercayai payload broadcast mentah. Panel worksheet menarik `/api/tickets/worksheet-tasks`; Dashboard NOC me-refetch halamannya sendiri lalu menukar `innerHTML` per container.

## 14. Format `tugas`: `{CID}_{Nama}`

`FopTask.tugas` untuk SURVEY, PSB, MTN, dan C-REQ memakai format `"{customer->display_id}_{customer->full_name}"`, mis. `C1X4ARQ000631_Masudah Yuni Fitri` — bukan label generik. Konsisten dengan identitas pelanggan di seluruh sistem.

`composeFopNotes()` (isi `fop_tasks.notes`) SENGAJA cuma pointer pendek (`"Ticket TKT-xxx — dikirim oleh yyy."`), **BUKAN** menyalin ulang `catatan_teknis` — menyalin bikin dua sumber kebenaran yang menyimpang begitu salah satu diedit.

## 15. Restriksi Hapus Task FOP

`FopTaskController::destroy()` menolak (422):

| Kategori | Kenapa ditolak |
|---|---|
| SURVEY, PSB | `destroy()` mentransisikan customer ke `rejected` sebagai efek samping — konsekuensi bisnis nyata, harus disengaja lewat halaman Pelanggan |
| MTN, C-REQ **yang punya `ticket` terkait** | Riwayat pengirim harus tetap bisa ditelusuri — hapus `FopTask` gak boleh bikin jejak Ticketing jadi yatim |

MTN/C-REQ yang dibuat manual langsung di `/fop-tasks` (tanpa `ticket`) tetap boleh dihapus.

---

**Last updated:** 2026-07-28
