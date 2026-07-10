> **Arsip.** Dokumen historis, sebagian sudah tidak sesuai kode aktif (lihat [../README.md](../README.md) untuk dokumentasi terkini).

# Rancangan: Team Harian di /fop-tasks

## Business Rule
- Team dibentuk berlaku **1 hari** bisa lebih jika Task Pending.  Satu team bisa isi 2-3 teknisi (atau lebih, gak dibatasi).
- Satu team bisa pegang **lebih dari 1 task** dalam hari itu (bukan cuma 1 task per assignment kayak sekarang).
- Assignment task ke anggota team **manual per-task** — FOP pilih sendiri task mana dikerjain siapa dalam team itu. Sistem gak auto-bagi rata, cuma nampilin ringkasan beban kerja ("Teknisi A: 2/6 task").

Contoh: Team 1 (Teknisi A, B, C) pegang 6 task hari itu → FOP assign task #1,#2 ke A, #3,#4 ke B, #5,#6 ke C secara manual. Dashboard nampilin "A: 2 task, B: 2 task, C: 2 task".

## Kondisi Kode Sekarang
- `FopTask` model: 1 row = 1 tiket/task. Relasi `technicians()` = `belongsToMany(User, 'fop_task_user')` — teknisi yang ditugaskan ke tiket ITU doang, gak ada konsep "team" yang independen dari task.
- Gak ada entity "Team" tersendiri. Yang ada di `/fop` dashboard cuma "Tim Gabungan" — itu bukan entity persisten, cuma hasil query ad-hoc: grouping task hari ini berdasarkan kombinasi member yang sama (`FopDashboardController::activeTeams`, lihat `dashboard.blade.php` bagian "Daftar Tim Gabungan"). Gak nyambung ke `fop_tasks`.
- Modal create/edit `/fop-tasks` (`fop_tasks/index.blade.php`) — checkbox pilih teknisi per-tiket, gak ada konsep team yang bisa dipakai ulang buat tiket lain di hari yang sama.

## Lifecycle Team (Aktif vs Riwayat)

Team **gak pernah di-delete otomatis**. Status-nya derived (dihitung on-the-fly, bukan kolom fisik), berdasarkan status task di bawahnya:

- **Aktif** — masih ada `fop_tasks` dengan `team_id` = team ini yang status-nya BUKAN `Selesai`/`Cancel` (termasuk `Pending`). Muncul di daftar "Team Aktif Hari Ini".
- **Riwayat (closed)** — semua task di bawah team itu udah `Selesai`/`Cancel`. Gak muncul lagi di daftar aktif, tapi tetep ada di DB buat histori/laporan.

Konsekuensi: kalau ada task `Pending` yang nyambung ke hari berikutnya, team-nya TETEP dianggap aktif meski `work_date` udah lewat — sampe semua task-nya kelar. Besok, FOP tetep bikin team baru terpisah buat roster hari itu (karena anggota ganti-ganti tiap hari), team kemarin yang masih ada task pending jalan paralel sampe ditutup.

```php
// FopTaskTeam
public function isActive(): bool
{
    return $this->fopTasks()->whereNotIn('status', ['Selesai', 'Cancel'])->exists();
}
```

## Pindah Anggota Antar-Team (mid-day)

PIC per-task (`fop_task_user` pivot di tiap row `fop_tasks`) **independen** dari roster team (`fop_task_team_user`). Roster cuma dipakai buat convenience filter checkbox pas assign task baru — bukan sumber kebenaran real-time siapa ngerjain apa.

- Task yang UDAH di-assign ke Teknisi A gak berubah PIC-nya walau A dikeluarin dari roster team-nya.
- FOP edit roster (`PUT /fop-tasks/teams/{team}`) — hapus A dari Team 1, tambah A ke Team 2. Task BARU yang mau di-assign ke A bakal muncul difilter checkbox Team 2.
- Gak ada mekanisme "transfer histori" — perpindahan cuma berlaku ke depan, task lama tetep nempel ke team asalnya.

## Data Model Baru

### Tabel `fop_task_teams`
```
id
name            varchar        -- "Tim 1", opsional auto-generate "Tim {nama lead}"
pop_id          FK nullable    -- cabang/area, opsional filter
work_date       date           -- tanggal berlaku (1 hari)
created_by      FK users
timestamps
```

### Tabel `fop_task_team_user` (pivot roster)
```
team_id   FK fop_task_teams
user_id   FK users
```

### Perubahan tabel `fop_tasks`
```
+ team_id   FK fop_task_teams, nullable
```
Kolom `technicians` (pivot `fop_task_user`) **tetap dipakai** — itu yang nyimpen siapa PIC aktual di 1 tiket itu (manual per-task, biasanya 1 orang meski kolom pivotnya many-to-many, gak diubah strukturnya). `team_id` cuma penanda "tiket ini bagian dari team mana" buat grouping & summary.

## Model Baru: `FopTaskTeam`
```php
class FopTaskTeam extends Model
{
    protected $fillable = ['name', 'pop_id', 'work_date', 'created_by'];
    protected $casts = ['work_date' => 'date'];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'fop_task_team_user');
    }

    public function fopTasks(): HasMany
    {
        return $this->hasMany(FopTask::class, 'team_id');
    }

    public function pop(): BelongsTo { return $this->belongsTo(Pop::class); }

    /** Ringkasan beban kerja per anggota: ['user_id' => count] */
    public function workloadSummary(): array
    {
        return $this->fopTasks()
            ->with('technicians')
            ->get()
            ->flatMap(fn ($t) => $t->technicians->pluck('id'))
            ->countBy()
            ->toArray();
    }
}
```

`FopTask` tambah:
```php
public function team(): BelongsTo
{
    return $this->belongsTo(FopTaskTeam::class, 'team_id');
}
```

## Controller & Route

Opsi: gabung ke `FopTaskController` (module kecil, gak perlu controller terpisah) — tambah action:
```
GET    /fop-tasks/teams              -> list team hari ini (buat dropdown filter + modal kelola)
POST   /fop-tasks/teams              -> bikin team baru (name, pop_id, work_date, member_ids[])
PUT    /fop-tasks/teams/{team}       -> update roster/nama team
DELETE /fop-tasks/teams/{team}       -> hapus team (cuma kalau gak ada fop_tasks yang masih nempel, atau set null dulu)
```
RBAC: **gak bikin permission baru** — reuse `fop_tasks.create`/`fop_tasks.update`/`fop_tasks.view` yang udah ada, karena team ini bagian gak terpisah dari alur kerja Task FOP (dikelola aktor yang sama: FOP + Admin).

## Perubahan UI `/fop-tasks`

1. **Filter bar** — tambah dropdown "Team" (opsional), filter `fop_tasks` by `team_id`.
2. **Tombol baru** "Kelola Team" di header (sebelah "Tambah Task FOP") — buka modal terpisah:
   - List team hari ini (nama, anggota, jumlah task, badge workload per orang)
   - Form bikin team baru: nama (opsional, default "Tim {tanggal}-{urutan}"), tanggal, pilih anggota (multi-select dari teknisi)
3. **Modal Create/Edit Task FOP** — tambah dropdown "Team" (opsional, filter berdasarkan tanggal `task_date` = `work_date` team). Kalau pilih Team:
   - Checkbox "Pilih Teknisi" otomatis di-filter cuma nampilin anggota team itu (biar FOP gampang pilih 1 dari situ, tetep manual pick, bukan auto-assign semua).
   - Kalau gak pilih Team, checkbox tetep nampilin semua teknisi kayak sekarang (backward compatible, team opsional).
4. **Tabel FopTask** — tambah kolom/badge kecil "Team: Tim 1" di baris yang punya `team_id`.
5. **Panel ringkasan** (baru, di bawah tabel atau di modal Kelola Team) — per team, tampilin workload:
   ```
   Tim 1 (A, B, C) — 6 task
   ├─ A: 2 task
   ├─ B: 2 task
   └─ C: 2 task
   ```

## Migration Plan
1. `create_fop_task_teams_table`
2. `create_fop_task_team_user_table`
3. `add_team_id_to_fop_tasks_table` (nullable, `onDelete('set null')` biar hapus team gak mecahin data task lama)

## Validasi & Edge Case
- Hapus Team yang masih punya `fop_tasks` nempel → `team_id` di-set null otomatis (FK `set null`), task-nya gak ikut kehapus.
- Team `work_date` beda dari `fop_tasks.task_date` — boleh aja secara teknis (gak divalidasi ketat), tapi UI filter dropdown Team di modal create/edit cuma nampilin team yang `work_date`-nya sama/deket sama `task_date` yang diinput biar gak salah pilih.
- 1 FopTask cuma bisa 1 team (kolom `team_id` tunggal, bukan many-to-many) — sesuai definisi "task itu milik 1 team, PIC-nya salah satu anggota team itu".
- Anggota team tetep bisa dikerjain teknisi yang BUKAN anggota team itu juga (checkbox teknisi gak dikunci ketat ke roster team) — biar fleksibel kalau ada dadakan.

## Yang TIDAK diubah
- `TaskService::create()`/`update()` (sinkron ke tabel `tasks` pas FopTask di-assign teknisi) — tetep jalan sama persis, `team_id` gak perlu diteruskan ke situ (tabel `tasks` gak butuh tau soal Team FOP, itu concern-nya `/fop-tasks` doang).
- Struktur pivot `fop_task_user` (PIC per tiket) — gak diubah.
- "Tim Gabungan" ad-hoc query di `/fop` dashboard — dibiarin apa adanya (beda concern, itu nunjukin tim yang KEBETULAN sama-sama kerja bareng hari itu berdasarkan data `tasks`, bukan Team FOP yang di-declare eksplisit).

## Checklist Implementasi
- [x] Migration: `fop_task_teams`, `fop_task_team_user`, alter `fop_tasks` +`team_id`
- [x] Model `FopTaskTeam` + relasi di `FopTask`
- [x] `FopTaskController`: action `teamStore`/`teamUpdate`/`teamDestroy` (gabung ke controller yang sama, gak bikin `FopTaskTeamController` terpisah)
- [x] Route baru di `routes/web.php` (`fop-tasks.teams.store/update/destroy`, reuse middleware `fop_tasks.create/update/delete`)
- [x] View: modal "Kelola Team" (list + form create + hapus), dropdown Team di modal create/edit (filter checkbox teknisi otomatis), kolom Team di tabel, filter dropdown Team di filter bar
- [x] `isActive()` derived status + badge Aktif/Riwayat di modal Kelola Team
- [x] Smoke test via tinker: create team → attach FopTask → relasi `team()` jalan, `isActive()` benar (false tanpa task, true pas ada task Proses), `workloadSummary()` akurat — cleanup sukses

- [x] **Gap Edit Roster ditutup** — tombol Edit (pencil icon) per team di modal Kelola Team, buka form yang sama (mode edit: nama/POP/anggota bisa diubah, tanggal berlaku dikunci). Submit ke `PUT /fop-tasks/teams/{team}`.
- [x] Smoke test: edit roster (keluarin A, masukin C) → task yang udah di-assign ke A tetep PIC-nya A, gak ikut kepindah. Sesuai desain.

### Belum diverifikasi (manual browser test)
- [ ] Assign 6 task manual ke 3 orang (2 per orang) lewat UI beneran, cek badge workload di modal Kelola Team akurat
- [ ] Hapus Team yang punya task aktif lewat UI, pastikan `team_id` di tabel jadi null bukan task ikut ke-delete
- [ ] Klik tombol Edit Team di browser beneran, cek checkbox anggota ke-checklist sesuai roster existing (JS type-coercion string vs number udah dihandle, tapi belum dicoba manual di browser)

## Review Ulang — 5 Temuan + Keputusan

1. **Dropdown Team gak difilter tanggal.** Dokumen sempat bilang mau filter berdasarkan `task_date` = `work_date`, tapi implementasi nampilin semua team tanpa filter. **Status: belum di-fix, backlog.** FOP mesti hati-hati manual milih team yang bener.
2. **`pop_id` di level Team redundant** — tiap `FopTask` udah punya `pop_id`/`village_id` sendiri. **Status: DIHAPUS.** Field "POP / Cabang" dicabut dari form + kolom DB.
3. **Gak ada validasi 1 teknisi rangkap 2 team aktif di hari yang sama.** **Status: DITAMBAHIN.** `teamStore()`/`teamUpdate()` sekarang nolak kalau ada member yang udah kepasang di team lain yang masih aktif & `work_date` sama.
4. **`work_date` dikunci pas edit, gak bisa di-extend manual.** **Status: gak perlu fix** — karena `isActive()` itu derived dari status task (bukan dari `work_date`), team otomatis tetep dianggap aktif walau tanggal berjalan udah lewat `work_date` aslinya. Gak butuh field tanggal diubah manual.
5. **Gak ada visibility "kenapa Team ini masih aktif".** **Status: DITUTUP** via fitur baru Team Card di Dashboard FOP (lihat bagian di bawah) — nunjukin task per anggota, jadi kelihatan task mana yang bikin team itu belum closed.

## Fitur Baru: Team Card di Dashboard FOP (`/fop`)

Setiap Team yang **aktif** ditampilin sebagai card di `/fop` (di bawah/atas Antrean Survey — bagian yang disisain pas kanban dihapus). Isi card:
- Nama Team + tanggal berlaku
- List anggota, tiap anggota nampilin task yang jadi tanggung jawabnya (nomor tiket, tugas, status) dalam team itu

```php
// FopDashboardController::index()
$activeFopTeams = FopTaskTeam::with(['members', 'fopTasks.technicians'])
    ->get()
    ->filter->isActive()
    ->map(function (FopTaskTeam $team) {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'work_date' => $team->work_date->format('d M Y'),
            'members' => $team->members->map(function (User $m) use ($team) {
                $tasks = $team->fopTasks->filter(fn ($t) => $t->technicians->contains('id', $m->id));
                return [
                    'name' => $m->name,
                    'tasks' => $tasks->map(fn ($t) => [
                        'task_number' => $t->task_number,
                        'tugas' => $t->tugas,
                        'status' => $t->status->value,
                    ])->values(),
                ];
            })->values(),
        ];
    })->values();
```

## Validasi Baru: Cegah 1 Teknisi Rangkap 2 Team Aktif

Di `teamStore()`/`teamUpdate()`, sebelum sync member, cek tiap `member_id`:
- Cari team LAIN (exclude team ini sendiri kalau update) yang `work_date` sama DAN statusnya masih aktif (`isActive()`) DAN user itu ada di roster-nya.
- Kalau ketemu → tolak dengan pesan jelas nama team konflik + nama user.

## Status Eksekusi (final)

- [x] Migration drop `pop_id` dari `fop_task_teams` (kolom + FK constraint dicabut bersih)
- [x] Model `FopTaskTeam::findMemberConflicts()` — static helper, dipake `teamStore()` & `teamUpdate()`
- [x] Form "POP / Cabang" dicabut dari modal Kelola Team (create + edit)
- [x] `FopDashboardController::index()` — tambah `$activeFopTeams` (team aktif + task per anggota)
- [x] `fop/dashboard.blade.php` — section "Team FOP Aktif" (card grid, di atas Antrean Survey)
- [x] Smoke test tinker: konflik kedetect bener (teknisi yang udah di Team 1 ditolak masuk Team 2 tanggal sama), kolom `pop_id` konfirmasi hilang dari schema, data card dashboard (team → member → task count) akurat
