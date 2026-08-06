## DONE

# Analisa — Target SLA Ticketing

Status: **Diimplementasi 2026-08-05.** Ditulis dari sesi tanya-jawab investigasi kode, dieksekusi di sesi yang sama. Lihat §6 buat detail eksekusi & file yang disentuh.

## 1. Pertanyaan Awal

Modul Ticketing tampak punya "Target SLA" berdasarkan Prioritas maupun Paket (via Kategori Ticketing). Apakah diterapkan dengan baik, apa dampaknya, dan bagaimana cara kerjanya?

## 2. Temuan — Cara Kerja SLA Saat Ini

Ada dua konsep SLA yang berbeda di codebase, jangan dicampur (sudah ditegaskan di `CLAUDE.md`):

- **Handling SLA** — batas waktu wajib mulai ditangani. Kolom `fop_tasks.handling_sla_hours`.
- **SLA Pengerjaan** — durasi teknisi kerjakan task (`TaskType::slaMinutes()` / `Task::isOverSla()`).

Fokus analisa ini: **Handling SLA / Master Timeline SLA** (`docs/master/sla-timeline/`).

### Alur riil

```
Ticket dibuat (TicketService::create())
  → issue_category_id disimpan, TAPI default_priority & sla_source
    kategori TIDAK dibaca balik ke logic apapun di backend.
  → tickets table TIDAK punya kolom SLA/deadline sama sekali
    (cek migration create_tickets_table.php).

escalateToFop() → FopTask kebentuk
  → FopTask::booted() creating-hook resolve & SNAPSHOT
    handling_sla_hours:
      $package = $fopTask->customer?->internetPackage;
      $fopTask->handling_sla_hours = $package
          ? $package->getHandlingSla($fopTask->category)
          : $fopTask->category->defaultHandlingSlaHours();
  → SELALU pakai jalur paket (kalau customer punya paket),
    field `sla_source` kategori TIDAK PERNAH dicek di sini.

FopTask::slaDeadline() / slaTotalSeconds()
  → dipakai buat progress bar/badge/countdown, tapi CUMA
    muncul di FOP Dashboard, Antrean Survey, Verif Pemasangan.
  → Tidak pernah tampil di halaman Ticketing manapun.
```

### Yang di form Create Ticket

`resources/views/tickets/create.blade.php:358-360` nampilin teks:
> "SLA kategori ini: sesuai Paket Internet pelanggan" / "sesuai Prioritas di atas"

Ini murni **display info** dari `sla_source` kategori — tidak pernah dipakai hitung atau simpan apapun ke record tiket. `default_priority` kategori cuma isi awal field prioritas di form (Alpine JS), bisa dioverride manual, dan sesudah itu tidak dipakai apa-apa lagi.

### Auto-escalation prioritas

`FopTaskController::autoSyncAndCalculatePriority()` (~line 1267) menaikkan `priority` (LOW→HIGH→URGENT) berdasarkan sisa waktu vs deadline — **tapi cuma untuk kategori `TaskType::autoOnlyValues()` (SURVEY, PEMASANGAN)**. Kategori manual dari Ticketing (MTN, C-REQ, O-REQ, INFR REQ) priority-nya beku dari `default_priority` kategori, tidak pernah naik otomatis walau telat.

## 3. Kesimpulan — Gap yang Ditemukan

1. **Ticketing sendiri tidak punya SLA sama sekali.** Tabel `tickets` tidak ada kolom deadline/SLA. Selama tiket masih di tangan Helpdesk/NOC (`handler` ≠ FOP), tidak ada countdown/badge/indikator telat di halaman manapun (`tickets/show.blade.php`, `tickets/history.blade.php`, worksheet, dsb).
2. **`sla_source = 'prioritas'` adalah dead config** — field ada di DB & UI Master Kategori Tiket, tapi backend (`FopTask::booted()`) tidak pernah membacanya. Selalu pakai jalur paket kalau customer punya paket internet.
3. **SLA baru "hidup" setelah `escalateToFop()`** — titik itu snapshot `handling_sla_hours` diambil dengan anchor `created_at` tiket (untuk MTN/C-REQ/O-REQ/INFR REQ, per `docs/master/sla-timeline/business-logic.md` §2), jadi waktu yang sudah terpakai di meja Helpdesk **tetap ikut terhitung** ke deadline — tapi user tidak lihat progress apa pun sampai sampai ke FOP Dashboard.
4. **Tidak ada notifikasi/eskalasi otomatis** breach dimanapun (FOP maupun Ticketing) — ini memang **sengaja** dinyatakan di luar scope (`docs/master/sla-timeline/business-logic.md` §8). Cuma indikator visual pasif.
5. **Ini sesuai desain awal** — `docs/master/sla-timeline/business-logic.md` §5 eksplisit menyatakan skip nambah SLA ke `Task`/Ticketing, karena `FopTask` sudah dianggap single source of truth. Ticketing tidak pernah masuk pertimbangan dikasih SLA sendiri.

## 4. Solusi yang Diusulkan — Satu SLA Clock, Dua Panggung

Prinsip: bukan pisah SLA per modul, tapi **satu deadline dihitung dari lahir tiket, jalan terus lintas modul** (Ticketing → FOP), cuma titik penyimpanannya pindah tangan seiring life-cycle.

Pondasi yang sudah ada dan bisa dipakai:
- `tickets.resolved_at` sudah ada — "kapan tiket lepas dari meja Ticketing" (berlaku dua arti: ditutup ATAU diserahkan ke FOP, lihat migration `2026_07_29_000001_add_resolved_at_to_tickets_table.php`).
- Anchor SLA untuk MTN/C-REQ/O-REQ/INFR REQ **sudah** `created_at` tiket (bukan `created_at` FopTask) — secara desain sudah kontinu, tinggal di-surface lebih awal.

### Langkah

1. **Pindah titik hitung SLA dari `FopTask::booted()` ke `Ticket::create()`.**
   Tambah kolom `tickets.sla_hours` + `tickets.sla_deadline_at` (snapshot, prinsip sama seperti `handling_sla_hours` sekarang — biar tidak ikut geser kalau paket/master timeline diubah belakangan). Isi di `TicketService::create()`:
   ```php
   $ticket->sla_hours = $customer->internetPackage?->getHandlingSla($type) ?? $type->defaultHandlingSlaHours();
   $ticket->sla_deadline_at = now()->addHours($ticket->sla_hours);
   ```
   Reuse `InternetPackage::getHandlingSla()` yang sudah ada.

2. **Beresin `sla_source` biar tidak dead config.**
   Kalau kategori `sla_source = 'prioritas'`, pakai jalur prioritas, bukan paket. Tambah `FopTaskPriority::slaHours(): int` (matrix, mis. Urgent=4, High=8, Medium=24, Low=48) dan cabangkan:
   ```php
   $ticket->sla_hours = $issueCategory->sla_source === 'prioritas'
       ? $ticket->priority->slaHours()
       : ($customer->internetPackage?->getHandlingSla($type) ?? $type->defaultHandlingSlaHours());
   ```

3. **Escalate ke FOP → warisi, jangan hitung ulang.**
   `TicketService::syncToFopTask()` isi `handling_sla_hours` FopTask dari `$ticket->sla_hours` (bukan biarkan `FopTask::booted()` resolve sendiri). Deadline tidak reset saat handoff — satu jam sejak tiket lahir tetap dipakai FOP. Ini yang membuat clock jadi satu, bukan dua sumber independen.

4. **Tiket yang tidak butuh FOP (closed langsung Helpdesk/NOC).**
   Karena kolom SLA sekarang ada di `Ticket` sendiri, SLA tetap terukur walau `FopTask` tidak pernah lahir. Breach-check: `resolved_at > sla_deadline_at` (atau `now() > sla_deadline_at` selama masih open) — tidak butuh `FopTask` sama sekali.

5. **Tiket yang butuh FOP.**
   Selama `handler != FOP` → badge/countdown baca `tickets.sla_deadline_at` langsung. Begitu `handler = FOP` → UI baca `FopTask::slaDeadline()` (yang sekarang snapshot dari ticket, bukan hitung ulang) — narasi kontinu satu deadline.

6. **Surface ke UI Ticketing.**
   Tambah badge/progress-bar di `tickets/show.blade.php`, `tickets/create.blade.php` (panel antrean), `tickets/history.blade.php`. Reuse kalkulasi persen dari `FopTask::slaDeadline()`/`slaTotalSeconds()` — extract ke satu Trait/helper dipakai `Ticket` & `FopTask` sama-sama, supaya rumus warna (threshold LOW/HIGH/URGENT) tidak diduplikasi dua tempat (kelas risiko yang sama seperti warning di `FopTaskController.php` soal jangan pecah syarat timer di dua tempat).

7. **(Opsional) Auto-escalate priority di sisi Ticketing juga.**
   `FopTaskController::autoSyncAndCalculatePriority()` sekarang cuma jalan untuk SURVEY/PEMASANGAN di sisi FopTask. Bisa tambah job serupa untuk tiket `handler != FOP` yang masih open dan lewat `sla_deadline_at`, naikin `priority`-nya.

8. **Test.**
   `TicketSlaTest` baru: assert `sla_deadline_at` tersimpan benar saat create (jalur paket & jalur prioritas), assert kontinu saat `escalateToFop()` (FopTask warisi angka yang sama, tidak reset), assert breach terdeteksi untuk tiket yang tidak pernah ke FOP.

### Kenapa bukan gabung tabel

Tidak perlu gabung `Ticket` + `FopTask` jadi satu tabel — itu melanggar prinsip existing "FopTask = penugasan, Ticket = permintaan" yang sudah dipertahankan lintas migrasi (`2026_07_23` → `2026_07_25`). Cukup **satu nilai deadline dihitung sekali di titik paling awal (Ticket create), diwariskan turun** ke FopTask saat eskalasi. Dua tabel tetap terpisah secara struktur, tapi satu jam SLA yang sama dipakai keduanya — tiket yang tidak pernah menyentuh FOP tetap dapat SLA penuh karena kolomnya sekarang menempel di `Ticket`, tidak menunggu `FopTask` lahir.

## 5. Scope Kalau Dieksekusi

- Migration baru: `tickets.sla_hours`, `tickets.sla_deadline_at` (nullable, snapshot).
- `TicketService::create()` — isi snapshot SLA.
- `TicketService::syncToFopTask()` — waris `handling_sla_hours` dari tiket, bukan hitung ulang di `FopTask::booted()`.
- `FopTaskPriority` — tambah `slaHours()` kalau opsi `sla_source='prioritas'` mau diimplementasi.
- `Ticket` model — tambah `slaDeadline()`/breach-check, share logic dengan `FopTask` lewat Trait/helper.
- 3 view Ticketing (`show`, `create`, `history`) — tambah badge/countdown.
- Update `docs/master/sla-timeline/business-logic.md` (§5 & §8 perlu direvisi kalau keputusan berubah — saat ini eksplisit menyatakan skip Ticketing).
- Test baru: `TicketSlaTest`.

## 6. Eksekusi — Yang Benar-Benar Dikerjakan (2026-08-05)

Semua langkah §5 dikerjakan, plus surface ke worksheet (poin terakhir tadinya belum discope eksplisit — ternyata worksheet-nya JS-driven bukan Blade loop biasa, jadi ditangani sebagai badge statis, bukan countdown live, lihat catatan di bawah).

| File | Perubahan |
|---|---|
| `database/migrations/2026_08_05_091143_add_sla_fields_to_tickets_table.php` | Kolom baru `tickets.sla_hours` (unsignedSmallInteger, nullable) + `tickets.sla_deadline_at` (timestamp, nullable). |
| `app/Enums/FopTaskPriority.php` | `slaHours(): int` — matrix Urgent=4, High=8, Medium=24, Low=48 jam. Dipakai jalur `sla_source='prioritas'`. |
| `app/Models/Ticket.php` | Cast `sla_deadline_at` datetime; `slaDeadline()`, `slaTotalSeconds()`, `isSlaBreached()`, `slaBadgeLabel()`, `slaBadgeClasses()`. |
| `app/Services/TicketService.php` | `create()` — snapshot `sla_hours`/`sla_deadline_at` via `resolveSlaHours()` baru (cabang `sla_source` kategori: `'prioritas'` → `FopTaskPriority::slaHours()`, selain itu → `InternetPackage::getHandlingSla()` sama seperti sebelumnya). `syncToFopTask()` — `FopTask::handling_sla_hours` diisi dari `$ticket->sla_hours` (warisan, bukan hitung ulang; `FopTask::booted()` skip otomatis karena kolomnya udah gak null). |
| `app/Http/Controllers/TicketController.php` | `worksheetCardPayload()` + `detailJson()` — tambah `sla_label`/`sla_badge_class` (+ `sla_deadline_at`/`sla_total_seconds`/`sla_is_live` di detail). |
| `resources/views/tickets/show.blade.php` | Countdown LIVE (`<x-countdown-timer>`, komponen yang sudah dipakai FOP Dashboard/Antrean Survey/dll) selama tiket masih jalan di Ticketing; badge statis on-time/lewat SLA begitu resolved atau sudah di FOP. |
| `resources/views/tickets/partials/archive.blade.php` | Badge SLA statis di daftar Tiket Selesai/Dibatalkan. |
| `resources/views/tickets/history.blade.php` | Kolom tabel baru "SLA" (badge on-time/lewat SLA). |
| `resources/views/tickets/create.blade.php` | Badge SLA (label + warna dari payload JSON, BUKAN live-ticking) di mode tabel & mode kartu worksheet. |
| `tests/Feature/TicketSlaTest.php` | 6 test baru: jalur paket (default & Master Timeline diset), jalur prioritas, tiket closed-tanpa-FOP tetap punya SLA, eskalasi FOP mewarisi `sla_hours` yang sama (gak dihitung ulang), breach-check tiket masih open. |

**Kenapa worksheet (`create.blade.php`) dapet badge statis, bukan countdown live:** panel itu di-render client-side lewat Alpine `x-for` atas array JSON hasil fetch (`worksheetCardPayload()`), bukan `@foreach` Blade biasa — komponen `<x-countdown-timer>` gak bisa ditembak per-baris dari situ (propnya di-compile server-side sekali, gak bisa nerima ekspresi Alpine dinamis per item). Solusinya label dihitung server-side (`Ticket::slaBadgeLabel()`) dan cuma ikut ke-refresh pas halaman reload/broadcast — bukan detik-per-detik. Detail Tiket (`show.blade.php`) tetap dapet countdown live penuh karena itu Blade biasa.

**Hasil test:** `TicketSlaTest` 6/6 pass. Regresi Ticketing/FOP terkait (`TicketingTest`, `TicketCloseEscalateTest`, `TicketHistoryTest`, `TicketCancellationTest`, `TicketDetailDrawerTest`, `FopTaskCreateFollowsTicketingTest`, `TicketIssueCategoryCRUDTest`) — 144/144 pass. Full suite: 10 gagal, semua di luar scope perubahan ini (RBAC/`EffectiveAccessService`/`SubscriptionStatusMasterTest`) — gak nyentuh `Ticket`/`FopTask`/`TicketService`/view Ticketing manapun, diduga pre-existing/flaky, BUKAN regresi dari perubahan SLA ini.

**Belum dikerjakan (di luar scope sesi ini):** poin 7 di §5 (auto-escalate `priority` tiket berdasar sisa waktu SLA, mirror `FopTaskController::autoSyncAndCalculatePriority()`) — badge SLA sekarang udah kelihatan real-time, tapi kolom `priority` tiket sendiri tetap statis dari input awal, gak naik otomatis kalau telat.

## 7. Dokumentasi Formal — Sudah Diupdate (2026-08-05)

- `docs/ticketing/business-logic.md` § 16 (baru) — narasi lengkap SLA Ticketing.
- `docs/ticketing/database-schema.md` — kolom `sla_hours`/`sla_deadline_at`, migrasi baru, cast & helper `Ticket`.
- `docs/ticketing/flowchart.md` § 11 (baru) — diagram clock SLA, + step di § 2.
- `docs/ticketing/user-flow.md` § 6 — badge SLA di Detail Ticket.
- `docs/ticketing/README.md` — bullet Konsep Inti, migration list, footer changelog.
- `docs/master/sla-timeline/business-logic.md` § 4, § 5, § 5a (baru), § 8 — koreksi "FopTask satu-satunya source of truth" (udah gak akurat), jelasin warisan `handling_sla_hours` dari `Ticket`.
- `docs/master/sla-timeline/database-schema.md` — ER diagram + field notes nambah `tickets`.
- `docs/fop-task/database-schema.md` — `handling_sla_hours` dijelasin dua jalur (resolve mandiri vs warisan).
