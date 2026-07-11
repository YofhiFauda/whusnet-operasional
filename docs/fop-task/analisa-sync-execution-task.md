# ⚠️ ANALISA: Bug "Team Jadi Kosong" saat Task Di-shrink jadi Solo (+ Desync Execution Task)

**Status:** ✅ **SEMUA gap di dokumen ini sudah di-FIX**, termasuk bagian 1-4 (bug team kosong) dan bagian 6 (desync ke execution `Task`/`TaskTeam`). Detail implementasi bagian 6 ada di bagian 6.6 di bawah. Test akhir: `FopTaskTeamServiceTest` 13/13 hijau, gabungan `FopTasksTest` + `FopTaskTeamServiceTest` 26/26 hijau — gak ada regresi.

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
