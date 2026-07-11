# ⚠️ ANALISA: Bug "Team Jadi Kosong" saat Task Di-shrink jadi Solo (+ Desync Execution Task)

**Status:** ✅ **SEMUA gap di dokumen ini sudah di-FIX**, termasuk bagian 1-4 (bug team kosong), bagian 6 (desync ke execution `Task`/`TaskTeam`), bagian 7 (modal konflik C3 gak muncul — ternyata false alarm, backend selalu benar), bagian 8 (switch-teknisi otomatis pas resolve konflik), bagian 9 (modal konflik ke-close gak sengaja / ilang pas refresh), dan **bagian 11 (baru — bug "teknisi ke-merge/ketuker Team" pas pakai `switchTechnician()`, Task 2)**. Test akhir gabungan 49/49 hijau. Bagian 10 catat 1 bug lain yang KETEMU gak sengaja pas investigasi, TERPISAH dari topik dokumen ini, BELUM difix.

---

## 0. Koreksi Skenario (revisi dari analisa sebelumnya)

Analisa sebelumnya di file ini SALAH mengartikan skenario sebagai "Task C dibuat baru langsung dengan 2 teknisi dari 2 team beda" (Skenario C3, konflik). Skenario yang benar-benar terjadi:

📌 **Kondisi awal:**
- Task A → **Tim 1**: Harto & Joko.
- Task B → **Tim 2**: Ardhiarja & Aurora.
- (Harto dan Aurora masing-masing cuma punya 1 task, di team masing-masing itu.)

🔄 **Yang dilakukan user:** Task C diarahkan ke Tim 2, lalu Harto & Aurora "dipindah" dari task lama mereka ke Task C. Karena fitur switch-teknisi resmi (Task 2, belum dibangun) belum ada, cara yang tersedia sekarang cuma lewat **edit teknisi** di task lama — artinya Task A di-edit teknisinya dari `[Harto, Joko]` jadi `[Joko]` saja (Harto dicabut), dan Task B di-edit dari `[Ardhiarja, Aurora]` jadi `[Ardhiarja]` saja (Aurora dicabut). Ini yang men-trigger bug.

---

## 1. Root Cause — Dikonfirmasi via Reproduksi Langsung

Reproduksi (bukan lewat HTTP/UI, langsung ke `FopTaskTeamService::rebuildTeamsForDate()` supaya bebas dari variabel UI):

```
Setup: Task A = [Harto, Joko] → rebuild → Task A masuk Team 1 (id=1), aktif.

Aksi: Task A di-edit → teknisi jadi [Joko] doang (Harto dicabut) → rebuild lagi.

Hasil:
  before shrink: taskA.team = 1
  after shrink:  taskA.team = NULL          ← Task A KELUAR dari Team 1!
  team1 exists:  NO - DELETED               ← Team 1 KEHAPUS!
```

Joko (satu-satunya teknisi tersisa di Task A) yang **masih aktif kerja di task itu** malah ikut kelempar keluar dari Team 1, dan Team 1 dianggap kosong lalu dihapus. Ini **persis** bug yang dilaporkan: *"Tim 1 dan Tim 2 tiba-tiba berstatus Kosong, padahal masih ada Joko (di Tim 1) dan Ardhiarja (di Tim 2)."*

### Kenapa ini kejadian — letak bug di kode

File: `app/Services/FopTaskTeamService.php`, method `rebuildTeamsForDate()`, blok penanganan solo task (sekitar baris 128-142):

```php
foreach ($soloTasks as $task) {
    $id = $task->technicians->first()->id;
    $root = $find($id);

    $groupSize = collect(array_keys($parent))
        ->filter(fn ($k) => $find($k) === $root)
        ->count();

    if (isset($rootTeam[$root]) || $groupSize > 1) {
        $groupedTasks[] = $task;
    } elseif ($task->team_id !== null) {
        $task->team_id = null;   // ← Joko kena sini
        $task->save();
    }
}
```

Logikanya: task yang cuma py 1 teknisi (`$soloTasks`) dianggap "masih part of a team" HANYA kalau:
- `$rootTeam[$root]` ke-set — ini cuma keisi kalau si teknisi ke-*union* dengan teknisi lain lewat task multi-teknisi **di pass rebuild yang SAMA** (lihat baris ~115-123, hanya diisi dari loop task multi-teknisi), ATAU
- `$groupSize > 1` — artinya di union-find pass ini dia udah ke-gabung sama org lain.

**Masalahnya:** begitu Task A di-edit jadi solo (`[Joko]`), Joko gak lagi punya partner di task manapun hari itu — gak ada task multi-teknisi yang nyentuh dia di pass ini. Union-find dia jadi root sendirian (`groupSize=1`), dan `$rootTeam[$root]` juga gak pernah keisi buat dia. Padahal ada 1 sumber informasi yang justru DIABAIKAN di titik ini: variabel `$existingTeamOf` (dibangun di awal method, baris ~45-53) yang isinya snapshot **team_id yang SUDAH ADA sebelum rebuild ini jalan** — termasuk dari Task A itu sendiri (Joko masih `team_id = Team1` di database persis sebelum baris ini dieksekusi). Solo-task handling ini **sama sekali gak konsultasi ke `$existingTeamOf`** — dia cuma percaya graf hasil union PASS INI SAJA. Akibatnya: Task A (dan Team 1) diperlakukan seolah Joko itu C2 murni (solo baru yang belum pernah punya team), padahal senyatanya dia SEDANG di tengah task yang masih aktif di Team 1.

Efek berantai: setelah `task->team_id = null`, di step cleanup (`FopTaskTeam::whereDate(...)->each(fn($team) => ...isActive()...)`) Team 1 udah gak py task aktif manapun yang nunjuk ke dia → `isActive()` return false → team ke-detach & ke-delete.

**Kesimpulan:** ini genuinely bug di algoritma, BUKAN cuma gap ekspektasi. Solo-task handling ketinggalan 1 kondisi anchor: *"teknisi ini emang UDAH punya team lewat task ini sendiri sebelum rebuild jalan"* — bukan cuma *"ke-bridge ke org lain di pass ini"*.

---

## 2. Poin "Data Teknisi Nyangkut di Task Lama" — bukan bug, tapi konsekuensi dari (1)

Laporan: *"Task A masih menampilkan Joko dan Harto (padahal Harto sudah dipindah)"*. Ini keliru dibaca sebagai 2 bug terpisah — sebenarnya cuma ada 1 aksi nyata yang mungkin dilakukan sistem hari ini: **edit teknisi Task A** (hapus Harto dari situ). Begitu itu dilakukan, Task A **memang seharusnya** cuma py Joko — dan itu SUDAH terjadi dengan benar di data (`taskA->technicians` benar-benar cuma `[Joko]` setelah edit). Yang salah bukan "Harto masih nyangkut di Task A" — yang salah adalah **efek sampingnya** (poin 1 di atas): Team 1 malah collapse gara-gara Joko doang yang tersisa. Kalau bug (1) di atas kefix, laporan bagian ini otomatis ke-resolve juga karena datanya sebenarnya sudah benar dari awal, cuma efek team-kosong-nya yang salah.

Catatan tambahan: kalau maksud user beneran "Harto pindah TANPA harus manual edit 2 task terpisah (task lama + task baru) sekaligus milih pengganti" — itu memang fitur **Task 2 (Switch Teknisi antar Team, endpoint atomic)** yang belum dibangun, bukan bug dari Task 1.

---

## 3. Logika Tombol "+ Masukkan ke Team" — Simptom dari Bug (1), Bukan Bug Terpisah

Requirement user: *"Tombol ini seharusnya hanya muncul apabila seorang teknisi benar-benar sudah tidak terikat dengan task/tim mana pun sebelumnya."*

Kondisi render tombol saat ini (`resources/views/fop_tasks/index.blade.php`, kolom Team): tombol muncul kalau `$task->team === null && $task->technicians->count() === 1`. Ini SEBENARNYA sudah sesuai niat awal (solo task tanpa team → boleh drop-in manual). Tapi karena bug (1), Task A (Joko solo) yang **SEHARUSNYA tetap py team** malah py `team_id = null` gara-gara algoritma salah nge-nullify — sehingga tombol ini SALAH muncul di Task A, padahal Joko jelas-jelas masih aktif di Team 1.

**Jadi tombol ini gak perlu logic tambahan** — begitu bug (1) di-fix (solo task yang emang udah py team gak lagi ke-null-in), tombol ini otomatis gak akan muncul lagi buat kasus Joko, karena `$task->team` bakal tetap ke-isi Team 1.

---

## 4. Kondisi yang Seharusnya (Expected Behavior, dikonfirmasi user)

1. Task A jadi solo (Joko) → **tetap** `team_id = Team 1`, Team 1 tetap py 1 anggota (Joko), tetap aktif, gak boleh kehapus.
2. Task B jadi solo (Ardhiarja) → **tetap** `team_id = Team 2`, sama logic-nya.
3. Task C (Harto + Aurora) → ini beneran narik dari 2 team beda (Harto ex-Team1, Aurora ex-Team2) → tetap harus kena **Skenario C3** (dialog konflik, FOP pilih manual) — bagian ini sudah benar di kode existing, gak perlu diubah.
4. Tombol "+ Masukkan ke Team" cuma muncul kalau teknisi BENERAN gak py team sama sekali (C2 murni) — otomatis benar begitu poin 1 & 2 kefix.

---

## 5. Perbaikan yang Diterapkan

Di blok solo-task handling (`FopTaskTeamService.php`, method `rebuildTeamsForDate()`), ditambahkan pengecekan ke `$existingTeamOf[$id]` SEBELUM memutuskan nullify:

```php
foreach ($soloTasks as $task) {
    $id = $task->technicians->first()->id;
    $root = $find($id);

    $groupSize = collect(array_keys($parent))
        ->filter(fn ($k) => $find($k) === $root)
        ->count();

    // Anchor tambahan: teknisi ini udah punya team existing dari SEBELUM
    // rebuild ini jalan (lewat task ini sendiri atau task lain) — misal task
    // yang tadinya multi-teknisi di-shrink jadi solo.
    $existingTeam = $existingTeamOf[$id] ?? null;
    if ($existingTeam !== null && !isset($rootTeam[$root])) {
        $rootTeam[$root] = $existingTeam;
    }

    if (isset($rootTeam[$root]) || $groupSize > 1) {
        $groupedTasks[] = $task;
    } elseif ($task->team_id !== null) {
        $task->team_id = null;
        $task->save();
    }
}
```

Kalau teknisi ini udah py team dari snapshot sebelum-rebuild (baik dari task ini sendiri atau task lain), dia di-treat sebagai anchor yang sama kayak `$rootTeam[$root]` biasa — masuk ke `$groupedTasks`, jadi team-nya di-preserve (roster di-sync ulang, bukan di-nullify). Fallback ke nullify (C2 — solo task yang emang belum pernah punya team) tetap jalan kalau `$existingTeamOf[$id]` juga kosong.

**Test regresi:** `test_shrinking_multi_technician_task_to_solo_keeps_its_team_alive` (`tests/Feature/Services/FopTaskTeamServiceTest.php`) — Task A [Harto,Joko] → rebuild → shrink jadi [Joko] → rebuild lagi → assert `taskA->team_id` tetap sama, team masih ada, roster jadi `[Joko]`.

**Hasil test setelah fix:** `FopTaskTeamServiceTest` 9/9 hijau, gabungan `FopTasksTest` + `FopTaskTeamServiceTest` 22/22 hijau — gak ada regresi ke skenario A/B/C1/C2/C3 yang udah ada.

---

## 6. Isu Terpisah (masih valid, dari analisa sebelumnya): Desync ke Execution Task

Selain bug di atas, ada gap terpisah yang udah dikonfirmasi sebelumnya (lewat riset kode) dan masih berlaku, gak berhubungan langsung sama bug (1):

### 6.1 Dua Model "Team" yang Gak Nyambung

| | Planning layer (FOP) | Execution layer (Teknisi) |
|---|---|---|
| Tabel | `fop_task_teams` | tidak ada tabel team — cuma pivot `task_team` |
| Model | `FopTaskTeam` | `TaskTeam` (`app/Models/TaskTeam.php:1-25`) — fillable `task_id`, `user_id`, `role_in_task` doang, TIDAK ada kolom `team_id`/`fop_task_team_id` |
| Dikelola oleh | `FopTaskTeamService::rebuildTeamsForDate()` | `TaskService::create()`/`update()` |
| Nama team | Kolom `name`, auto (`nextTeamName()`) | TIDAK ADA kolom nama — teks bebas di-bake ke `Task.title` |

`Task` model (`app/Models/Task.php:80-83`) cuma py relasi `teamMembers(): hasMany(TaskTeam::class)` — gak nyambung ke `FopTaskTeam` sama sekali.

### 6.2 Titik Baking Nama Team ke `Task.title`

`FopTaskController::store()` baris 206-217 (pola sama di `update()` baris ~328-333):
```php
$taskTitle = 'FOP: ' . $fopTask->tugas;
if (count($technicians) > 1) {
    $leadUser = User::find($technicians[0]);
    $teamName = $leadUser ? 'Tim ' . strtok($leadUser->name, ' ') : 'Tim Gabungan';
    $taskTitle = '[' . $teamName . '] ' . $taskTitle;
}
```
`$teamName` BUKAN nama `FopTaskTeam` asli — cuma "Tim " + nama depan teknisi pertama. Di-bake permanen ke `Task.title` (`TaskService.php:46`, `:88`), gak pernah di-refresh ulang kalau `FopTaskTeamService` merge/rename/pindahin team belakangan.

### 6.3 Kapan Persisnya Jadi Basi

| Aksi | Title (`tasks.title`) | Pivot `task_team` | Kena rebuild otomatis? |
|---|---|---|---|
| Buat FopTask baru (`store()`) | Di-bake dari teknisi pertama, bukan dari `FopTaskTeam` hasil rebuild (baris 234) | Dibuat sesuai `technicians` input | ❌ |
| Edit teknisi via `update()` | Di-generate ulang tiap kali, tapi tetap ad-hoc, bukan dari `FopTaskTeam` | Di-refresh (`TaskService::update()` ~baris 92-100) | ❌ Refresh terjadi SEBELUM rebuild (~baris 368) |
| `assignToTeam()` (drop-in manual) | Beku total — gak pernah panggil `TaskService` | Beku total | ❌ |
| `rebuildTeamsForDate()` (merge/rename otomatis) | Beku total — file ini gak reference `Task`/`TaskTeam`/`TaskService` sama sekali | Beku total | — |

**Kesimpulan:** teknisi di `/tasks-saya` & detail task selalu lihat nama team versi lama (dari saat FopTask dibuat/terakhir di-edit lewat form), gak peduli berapa kali auto-team engine ngerombak struktur team di FOP dashboard.

### 6.4 Kenapa Belum Ketauan di Task 1

Task 1 scope-nya cuma `fop_tasks` + `fop_task_teams`. Sync ke execution layer disebut di dokumen utama (`analisa-auto-team.md` bagian "4. Alur Sinkronisasi & Switch Teknisi") tapi belum pernah dijabarkan jadi Task konkret di Sprint Backlog manapun — bukan regresi, emang belum digarap.

Ada 1 arah sync yang SUDAH ada: `TaskService::syncToFopTask()` (baris 503-531, dipanggil dari `update()` baris 108 & `reassignTeam()` baris 415) — dorong perubahan **Task → FopTask** (termasuk trigger `rebuildTeamsForDate()`). Arah sebaliknya (`FopTaskTeam` → `Task`) belum ada sama sekali.

### 6.5 Rekomendasi (asli, sebelum dikerjakan)

1. Sync searah **FopTaskTeam → Task** di ujung `rebuildTeamsForDate()`: update `Task.title` pakai nama `FopTaskTeam` asli, sinkronkan roster `TaskTeam`.
2. Tambah sync yang sama di `FopTaskController::assignToTeam()`.
3. Pertimbangkan ganti pendekatan: tampilkan nama team di UI teknisi via relasi live, bukan bake ke string `title`.
4. Butuh test end-to-end: perubahan nama/roster team harus kelihatan efeknya juga di `Task.title` & `TaskTeam`.

### 6.6 Implementasi yang Diterapkan

**Pilihan desain:** tetap pakai pendekatan "sync title" (rekomendasi 1&2), BUKAN pindah ke relasi live (rekomendasi 3) — karena title yang di-bake lebih murah buat blade teknisi existing (`$task->title` udah dipakai di banyak tempat: `tasks/show.blade.php`, `tasks/own.blade.php`, dst — lihat riset di bagian awal analisa) dan gak perlu ubah tampilan sama sekali, cukup betulin sumber datanya. Ganti ke relasi live butuh ubah semua blade itu — scope lebih besar, ditunda sampai ada kebutuhan konkret.

**1) Sync searah FopTaskTeam → Task, dipanggil dari `rebuildTeamsForDate()` sendiri:**

Di `app/Services/FopTaskTeamService.php`, ditambah method privat `syncExecutionTaskTitle(FopTask $fopTask)`, dipanggil di step TERAKHIR `rebuildTeamsForDate()` (setelah cleanup team kosong) untuk SEMUA task aktif tanggal itu (`$tasks`, bukan cuma yang ke-touch grouping):

```php
foreach ($tasks as $task) {
    $this->syncExecutionTaskTitle($task);
}

// ...

private function syncExecutionTaskTitle(FopTask $fopTask): void
{
    if (!$fopTask->task_id) {
        return;
    }

    $execTask = $fopTask->task ?: Task::find($fopTask->task_id);
    if (!$execTask) {
        return;
    }

    $teamName = $fopTask->team_id ? $fopTask->team?->name : null;
    $baseTitle = 'FOP: ' . $fopTask->tugas;
    $newTitle = $teamName ? "[{$teamName}] {$baseTitle}" : $baseTitle;

    if ($execTask->title !== $newTitle) {
        $execTask->title = $newTitle;
        $execTask->save();
    }
}
```

Update `title` kolom LANGSUNG (bukan lewat `TaskService::update()`) — sengaja, biar:
- gak mancing `notifyTeam()` (notifikasi in-app + Telegram + broadcast) tiap kali rebuild jalan, padahal teknisinya gak ganti apa-apa.
- gak ke-trigger `TaskService::syncToFopTask()` (dipanggil dari dalam `update()`) yang balik manggil `rebuildTeamsForDate()` lagi → risiko infinite recursion.

Roster `TaskTeam` SENGAJA gak ikut di-resync di titik ini, karena analisa kode nunjukin roster itu udah konsisten secara alami — `TaskTeam` cuma berubah lewat `TaskService::create()`/`update()`, yang SELALU dipanggil bareng `$fopTask->technicians()->sync()` di `FopTaskController::store()`/`update()`. Karena `rebuildTeamsForDate()` sendiri gak pernah mengubah `$fopTask->technicians` (cuma `team_id`), gak ada sumber drift buat `TaskTeam` roster yang perlu diperbaiki.

**2) Sync di `assignToTeam()`:** TERNYATA gak perlu endpoint terpisah — `FopTaskController::assignToTeam()` sudah manggil `FopTaskTeamService::rebuildTeamsForDate()` di baris terakhirnya (buat propagate perubahan team ke task lain yang mungkin ke-bridge). Karena sync title sekarang jadi bagian dari `rebuildTeamsForDate()` itu sendiri, panggilan yang udah ada otomatis nyertain sync title juga — gak perlu ubah `assignToTeam()` sama sekali.

**3) `FopTaskController::store()`/`update()` disederhanakan:** logic ad-hoc nebak nama team (`'Tim ' . strtok($leadUser->name, ' ')`) DIHAPUS dari kedua method. Title dikirim ke `TaskService::create()`/`update()` polos (`'FOP: ' . $fopTask->tugas`, tanpa bracket) — karena `rebuildTeamsForDate()` yang dipanggil tepat setelahnya di request yang sama bakal langsung ngisi prefix `[Nama Team]` yang benar begitu team-nya kebentuk.

**4) Test end-to-end** (`tests/Feature/Services/FopTaskTeamServiceTest.php`), pakai helper baru `makeTaskWithExecution()` yang beneran manggil `TaskService::create()` buat bikin execution Task asli:
- `test_execution_task_title_gets_synced_with_real_team_name_after_rebuild` — Skenario A, title jadi `[Team 1] FOP: ...`.
- `test_execution_task_title_updates_when_team_merges_via_bridge` — Skenario B, 2 task ke-merge 1 team, KEDUA execution Task dapet title dengan nama team yang sama.
- `test_execution_task_title_has_no_team_prefix_when_task_has_no_team` — Skenario C2, solo tanpa overlap, title polos tanpa bracket.
- `test_execution_task_title_syncs_after_manual_assign_to_team` — simulasi `assignToTeam()` (drop-in manual), title ke-update ke nama team tujuan.

**Hasil:** `FopTaskTeamServiceTest` 13/13 hijau, gabungan sama `FopTasksTest` 26/26 hijau. Gak ada perubahan skema/tabel baru — cuma nyambungin data yang udah ada.

---

## 7. Laporan "Modal Konflik Gak Muncul" — Diinvestigasi, Ternyata False Alarm

**Laporan:** skenario Task A (Joko+Cagak, Cagak cuma 1 task di Tim 1), Task B (Suci+Tri, Suci cuma 1 task di Tim 2), Task C (Cagak+Suci) — seharusnya trigger modal konflik C3, tapi katanya gak muncul.

**Investigasi dilakukan di 3 layer, semua ngonfirmasi backend BENAR:**
1. **Service langsung** (`rebuildTeamsForDate()` dipanggil manual, simulasi per-request kayak UI asli) — konflik KEDETEKSI dengan benar, `result['conflicts']` isi task C + 2 kandidat team.
2. **HTTP end-to-end** (`POST /fop-tasks` 3x buat Task A/B/C via `store()`) — response redirect 302, session `fop_team_conflicts` keisi bener.
3. **Rendered HTML+JS** — di-extract dari response beneran, `node --check` gak ada syntax error, `teamConflictModal: { open: false, conflicts: [...] }` ke-render lengkap dengan data konflik yang bener, `x-init="initTeamConflicts()"` ada di root element.

**Kesimpulan:** gak ketemu bug kode. Kemungkinan waktu itu browser masih pegang versi cache lama (halaman ini emang lagi sering berubah — teamSelectionModal, teamConflictModal, fix naming, dll nyusul satu-persatu). 3 test regresi udah ditambahkan buat ngonci perilaku ini di `FopTaskTeamServiceTest.php`:
- `test_conflict_detection_for_user_scenario` — persis skenario Joko/Cagak/Tri/Suci di atas.
- `test_conflict_detection_when_editing_task_with_existing_team_id` — konflik muncul walau Task C-nya hasil EDIT (bukan create baru).
- `test_conflict_detection_when_task_under_review_has_lower_id_than_conflict_source` — mastiin urutan ID task gak ngaruh ke deteksi konflik.

---

## 8. Fitur Baru: Switch Teknisi Otomatis Pas Resolve Konflik C3

**Requirement user:** kalau Task C (Cagak+Suci) di-resolve ke Tim 2, Cagak (yang tadinya di Task A/Tim 1) harus otomatis kecabut dari Task A — biar gak nyangkut di 2 tempat. Suci gak perlu dicabut dari Task B karena Task B & Task C sama-sama di Tim 2.

**Implementasi** di `FopTaskController::assignToTeam()`: setelah `$fopTask->team_id` di-set ke team tujuan, sistem loop tiap teknisi di task yang lagi diresolve, cari task LAIN di tanggal sama yang masih nempel ke teknisi itu tapi `team_id`-nya BEDA dari team tujuan — teknisi itu di-detach dari task lama itu, dan kalau task lama itu punya execution `Task` terkait, `TaskService::update()` dipanggil buat refresh roster `TaskTeam`-nya juga.

**Test:** `test_resolving_conflict_removes_technician_from_other_team_tasks` — assert Task C masuk Tim 2, Cagak kecabut dari Task A (Task A sisa Joko doang), Suci TETAP di Task B, roster Tim 1 jadi `[Joko]`, roster Tim 2 jadi `[Cagak, Suci, Tri]`.

---

## 9. Fix: Modal Konflik Ke-close Gak Sengaja / Ilang Pas Refresh

**Masalah:** modal konflik cuma muncul dari session flash (`session('fop_team_conflicts')`) yang sifatnya sekali-pakai — begitu ke-close atau halaman di-refresh sesudah flash ke-baca, datanya ilang, padahal konfliknya di DB masih belum ke-resolve.

**Fix** — `FopTaskController::index()` sekarang punya `currentTeamConflicts()`: query LANGSUNG ke DB nyari task aktif dengan `team_id = null` TAPI teknisi >= 2 (kondisi ini gak mungkin kejadian kecuali lagi nunggu resolve C3 — beda dari solo/C2 yang emang `team_id` null tapi cuma 1 teknisi). Buat tiap tanggal yang ketemu, `rebuildTeamsForDate()` dipanggil ulang (idempoten) buat regenerate daftar konfliknya, di-merge sama session flash (dedupe by `task_id`). Hasilnya dikirim ke view sebagai `$teamConflicts`, GAK gantung ke session lagi.

**UI:** ditambah tombol "Konflik Team (n)" di header halaman, muncul kapan aja selama masih ada konflik pending — klik buat buka ulang modalnya, gak peduli udah di-close atau halaman baru di-refresh.

**Test:** `test_team_conflict_still_shows_after_modal_closed_and_page_reloaded` (`FopTasksTest.php`) — create Task A/B/C, GET index() 2x berturut-turut, assert modal konflik & task_number Task C tetap muncul di kedua response (bukan cuma yang pertama).

---

## 10. Isu Lain yang Ketemu (TERPISAH, BELUM Difix)

Pas verifikasi sync Task FOP / Dashboard FOP / Detail Task, sempet coba jalanin `FopDashboardController::index()` lewat test — dapet error 500:

```
SQLSTATE[HY000]: General error: 1 near "1": syntax error
SQL: ... where "status" in (...) and DATE_ADD(created_at, INTERVAL 1 DAY) < NOW()
```

**Penyebab:** `$overdueSurvey`/`$overdueInstallation` di `FopDashboardController` pakai raw SQL `DATE_ADD(...)`/`NOW()` — sintaks MySQL doang, gak valid di SQLite. Kalau production emang jalan di MySQL, ini gak masalah (gak nyentuh user). Tapi kalau butuh dashboard ini bisa dites/di-jalanin di atas SQLite (misal buat test suite), perlu diganti ke query yang portable (`Carbon::now()->subDay()` dibandingin di PHP, atau `whereRaw` yang driver-agnostic).

**Status:** cuma dicatat, BELUM difix — di luar topik dokumen ini (gak berhubungan sama auto-team/sync), FOP dashboard di production (asumsi MySQL) gak kepengaruh.

**Verifikasi terpisah (gak lewat dashboard controller penuh, langsung query team-card-nya doang):** dikonfirmasi Team 1 = `[Joko]`, Team 2 = `[Cagak, Suci, Tri]`, per-task technician list bener semua — bagian "Team FOP Aktif" di dashboard (yang emang relevan buat sync teknisi) udah kebukti sinkron, terlepas dari bug 500 di widget survey yang gak berhubungan itu.

---

## 11. Bug "Teknisi Ke-merge/Ketuker Team" via `switchTechnician()` (SUDAH DIFIX)

**Laporan user:** habis pakai fitur Switch Teknisi (Task 2), teknisi yang seharusnya di Tim 1 malah kepindah/ketuker jadi Tim 2, dan sebaliknya.

### 11.1 Reproduksi

```
Setup: Task A = Wito+Yanto (Tim 1), Task B = Joko+Wito+Karim (Tim 1, Wito jembatan), Task C = Abdul+Ajis (Tim 2).

Aksi: switchTechnician() — pindah Wito dari Task A ke Task C, pengganti Yanto di Task A.

Hasil SEBELUM fix:
  Team 1: Yanto              (bener)
  Team 2: KEHAPUS             ← Abdul & Ajis ilang teamnya!
  Task C: technicians=[Wito,Abdul,Ajis], team_id=1   ← ke-merge paksa ke Team 1, padahal
                                                          harusnya jadi conflict (Wito jembatan
                                                          ke 2 team beda: Team1 via Task B,
                                                          Team2 via Task C)
```

### 11.2 Root Cause #1 — `rebuildTeamsForDate()` Dipanggil Berlapis Tanpa Sadar

`switchTechnician()` manggil `rebuildTeamsForDate()` 1x eksplisit di akhir. TAPI method privat `syncSwitchedExecutionTask()` (dipanggil 2x, buat `fromTask` & `toTask`) awalnya lewat `TaskService::update($execTask, ['team_member_ids' => ...], ...)` — dan `TaskService::update()` (baris 108) SELALU manggil `syncToFopTask()` di akhir, yang (baris 528-530) **UNCONDITIONAL manggil `rebuildTeamsForDate()` lagi**. Jadi total ada **3 rebuild pass** per 1 switch (2 tersembunyi + 1 eksplisit), bukan 1.

Pass pertama BENER (Task C terdeteksi C3 conflict, `team_id` di-null-in). Tapi pass ke-2 (dari `syncSwitchedExecutionTask(toTask, ...)`) jalan SETELAH itu — snapshot `$existingTeamOf`-nya udah gak nemu bukti Team 2 lagi (karena Team 2 kadung kehapus di pass 1, lihat 11.3), jadi Wito+Abdul+Ajis dianggap AMAN di-union ke Team 1. Pass ke-3 cuma nguatin hasil yang udah salah itu.

**Fix:** `syncSwitchedExecutionTask()` diganti — SENGAJA gak lewat `TaskService::update()` lagi, langsung manipulasi pivot `task_team` (hapus + insert ulang manual), biar gak mancing `syncToFopTask()` dan rebuild tersembunyi. `switchTechnician()` sekarang bener-bener cuma 1x manggil `rebuildTeamsForDate()`.

### 11.3 Root Cause #2 — Team Kandidat Konflik Ke-cleanup di Pass yang Sama

Walau root cause #1 udah difix, bug MASIH kejadian (cuma versinya beda: gak ke-merge, tapi Team 2 tetep kehapus). Sebabnya: Team 2 di skenario ini CUMA punya 1 task (Task C). Begitu Task C ke-deteksi C3 conflict dan `team_id`-nya di-null-in (dalem 1 pass rebuild yang sama), Team 2 langsung keliatan "gak py task aktif lagi" → step cleanup ("hapus team kosong") di ujung `rebuildTeamsForDate()` LANGSUNG ngehapus Team 2 — padahal FOP belum sempet mutusin konfliknya. Efeknya: rebuild berikutnya (misal cuma buka ulang `/fop-tasks`) udah gak nemu bukti Team 2 pernah ada, jadi Wito+Abdul+Ajis di-treat kayak gak py konflik sama sekali, di-union diam-diam ke Team 1.

**Fix:** di `FopTaskTeamService::rebuildTeamsForDate()`, step cleanup sekarang skip team yang `id`-nya ada di daftar `candidates` dari `$conflicts` pass itu — team yang lagi jadi "opsi buat FOP pilih" gak boleh dihapus sampai konfliknya beneran diputusin (lewat `assignToTeam()`, yang baru manggil rebuild lagi setelah FOP milih).

### 11.4 Fix Tambahan — Response `switchTechnician()` Sekarang Nyertain Conflict

Sebelum fix, kalau switch bikin task LAIN (bukan yang lagi di-switch) jadi C3 conflict, `$conflicts` hasil `rebuildTeamsForDate()` dibuang begitu aja (gak dipakai). Sekarang di-flash ke session (`fop_team_conflicts`) & disertain di response JSON (`team_conflicts`), sama persis pola `store()`/`update()`/`assignToTeam()` — jadi modal konflik otomatis muncul abis switch kalau ternyata nyisain conflict yang perlu diputusin FOP.

### 11.5 Test Regresi

- `test_c3_conflict_candidate_team_survives_even_when_its_only_task_is_the_one_conflicted` (`FopTaskTeamServiceTest.php`) — replikasi persis skenario Wito/Team1/Team2: mastiin Team 2 TETAP ADA dan roster-nya utuh walau task satu-satunya lagi jadi conflict candidate.
- Semua 7 test `FopTaskSwitchTechnicianTest.php` di-re-run, tetep hijau (gak ada regresi ke behavior switch yang udah bener sebelumnya).

**Hasil akhir:** gabungan seluruh test FOP 49/49 hijau.
