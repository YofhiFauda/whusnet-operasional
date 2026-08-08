# Perbandingan: `assignToTeam()` (Task 1) vs `switchTechnician()` (Task 2)

Catatan hasil tanya-jawab soal kenapa dua endpoint ini keliatan mirip (sama-sama bisa "mindahin teknisi antar team") tapi sebenernya beda tujuan & beda jaminan. Dipakai buat referensi kalau ada yang nanya lagi "kok Task 1 udah bisa switch teknisi?".

---

## 1. Tabel Perbedaan Implementasi

| | `assignToTeam()` (Task 1) | `switchTechnician()` (Task 2) |
|---|---|---|
| **Method/endpoint** | `POST /fop-tasks/{task}/assign-to-team` — drop-in manual buat Skenario C2 (solo tanpa team) & C3 (konflik 2 team) | `POST /fop-tasks/switch-technician` — endpoint baru, terpisah total |
| **Input** | 1 `task_id` (route param) + `team_id` tujuan (nullable — null = bikin team baru) | `technician_id`, `from_task_id`, `to_task_id`, `replacement_technician_id` — 4 field eksplisit |
| **Siapa yang dipindah** | SEMUA teknisi di task itu (`foreach ($fopTask->technicians as $tech)`) — gak milih 1 orang spesifik | 1 teknisi spesifik yang dipilih FOP |
| **Task tujuan** | Gak ada "task tujuan" — yang dipilih itu TEAM tujuan buat task yang lagi diedit; task lain kena efek cabut otomatis | Task tujuan SPESIFIK dipilih FOP (`to_task_id`) |
| **Pengganti di task asal** | **Gak ada konsep ini sama sekali** — task asal ditinggal apa adanya (bisa jadi solo, bisa kosong kalau dia satu-satunya) | **Wajib** — endpoint nolak kalau pengganti gak diisi/invalid |
| **Validasi in_progress** | Gak ada | Ada — reuse cek dari `TaskService::start()`, pengganti gak boleh lagi in_progress di task lain |
| **Validasi intra-hari** | Gak ada constraint eksplisit (drop-in tetap ke team tujuan tanggal sama by design, dicek lewat match `work_date`) | Eksplisit ditolak kalau `from_task` & `to_task` beda tanggal |
| **Kapan jalan** | Reaktif — bagian dari alur "masukin task ke team" (Skenario C2/C3); efek cabut-teknisi itu SAMPINGAN | Disengaja — aksi FOP eksplisit "pindahin si A dari task ini ke task itu" |
| **UI trigger** | Modal konflik C3 / dropdown "+ Masukkan ke Team..." | Klik chip nama teknisi → modal 2-dropdown |
| **Audit log action** | `assign_to_team` (1 entry) | `switch_technician_out` + `switch_technician_in` (2 entry, eksplisit siapa keluar-masuk) |
| **Sprint Backlog** | Task 1 — bagian dari Auto-Team Formation Engine | Task 2 — item terpisah, file table & test sendiri |

---

## 2. Porsi yang Beneran Overlap vs Net-New

**Reused dari Task 1 (fondasi, bukan fitur):**
- Query "cabut teknisi dari task lain yang team-nya beda" — pattern sync-pivot yang sama gayanya.
- `FopTaskTeamService::rebuildTeamsForDate()` dipanggil abis mutasi.
- `TaskService::update()` buat sync ke execution `Task`.

**0% ada di Task 1, baru dikerjain di Task 2:**
- Endpoint sendiri (`switch-technician`).
- Konsep "pengganti wajib" — Task 1 gak punya validasi ini sama sekali.
- Pilih task tujuan spesifik (bukan cuma team tujuan).
- Validasi in_progress buat pengganti.
- Validasi intra-hari eksplisit.
- Rollback-safe kalau pengganti invalid (Task 1 gak pernah nolak "pengganti gak valid" karena emang gak ada konsep pengganti).
- Audit log per-switch (2 entry, `_out`/`_in`).
- UI-nya (klik chip → modal 2-dropdown).

**Kesimpulan:** fondasi (sync pivot, rebuild, sync execution task) emang dibangun di Task 1 dan bikin Task 2 lebih cepet dikerjain — tapi fitur switch teknisi yang diminta requirement (1 payload atomic + jaminan pengganti + validasi) itu sendiri sepenuhnya baru di Task 2.

---

## 3. Cara Pengujian

### 3.1 Otomatis (test suite)

```bash
php artisan test --filter=FopTaskSwitchTechnicianTest
```

| Test | Buktiin apa |
|---|---|
| `test_switch_technician_succeeds_in_one_submit` | 1 submit langsung pindah + isi pengganti |
| `test_switch_rejects_when_replacement_missing_from_request` | Task asal gak berubah kalau pengganti kosong — guard yang gak ada di `assignToTeam()` |
| `test_switch_rejects_when_replacement_is_same_as_departing_technician` | Pengganti gak boleh sama dengan yang dipindah |
| `test_switch_rejects_when_replacement_is_in_progress_elsewhere` | Cek `in_progress` — 0% ada di `assignToTeam()` |
| `test_switch_rejects_across_different_days` | Tolak lintas hari — gak dicek di `assignToTeam()` |
| `test_switch_triggers_rebuild_and_updates_team_rosters` | Rebuild jalan di kedua Team, roster ke-update bener |
| `test_switch_records_audit_log_entries` | 2 audit log entry beda action |

Buat banding-bandingin langsung: jalanin juga `test_resolving_conflict_removes_technician_from_other_team_tasks` (`FopTaskTeamServiceTest.php`, punya `assignToTeam()`) — assertion-nya gak ada yang soal "pengganti wajib", "in_progress check", atau "rollback kalau invalid". Beda test, beda jaminan yang dibuktikan.

### 3.2 Manual (sebagai FOP di website)

**Setup awal:** login FOP, buka `/fop-tasks`, bikin 3 task tanggal sama:
- **Task A**: Harto + Joko
- **Task B**: Ardhiarja + Aurora
- **Task C**: Harto + Aurora → modal "Konflik Team Terdeteksi" bakal muncul (Harto udah di Tim 1 lewat Task A, Aurora udah di Tim 2 lewat Task B).

**Uji 1 — Jalur lama (`assignToTeam`, via modal konflik):**
1. Di modal konflik Task C, klik salah satu tombol Team (misal "Team 2").
2. Refresh, lihat Task A → Harto ilang, Task A tinggal Joko, **tanpa pernah ditanya siapa gantiin Harto**.
3. Ulangi dengan Task A cuma solo Harto (1 teknisi doang) → resolve konflik ke Team 2 → **Task A jadi BENERAN KOSONG teknisi**, sistem gak nolak, gak ngasih peringatan.

**Uji 2 — Jalur baru (`switchTechnician`, klik chip nama):**
1. Bikin ulang Task A (Harto+Joko), Task B (Ardhiarja+Aurora) — 2 task aja.
2. Klik chip nama "Harto" di Task A → modal "Switch Teknisi antar Team" muncul.
3. Pilih Task Tujuan = Task B, biarin Pengganti kosong → coba submit → **tombol disabled / gak bisa submit**.
4. Pilih Pengganti = Joko, submit → Harto pindah ke Task B, Joko otomatis gantiin di Task A, Task A gak pernah kosong.
5. Coba pengganti yang lagi in_progress di task lain → **ditolak**, pesan jelas.
6. Coba Task Tujuan beda hari → **ditolak** ("cuma boleh intra-hari").

**Ringkasan beda yang paling kelihatan:**

| Coba ini | Jalur lama | Jalur baru |
|---|---|---|
| Task asal solo 1 teknisi, dipindah | Task asal jadi kosong, gak ada penolakan | Gak bisa submit sampai pengganti dipilih |
| Pengganti lagi in_progress di task lain | Gak dicek | Ditolak eksplisit |
| Task tujuan beda hari | Gak relevan (yang dipilih Team, bukan task) | Ditolak eksplisit |
| Cara milih | Team tujuan doang (dropdown) | Task tujuan + pengganti spesifik (2 dropdown) |

---

## 4. Kapan Pakai yang Mana (Bukan "Mana Lebih Bagus")

Dua-duanya gak saling gantiin — beda job:

- **`assignToTeam()`** tetap tepat buat:
  - Nyelesein konflik C3 (task narik dari 2 team beda) — di sini emang cuma perlu mutusin "task ini masuk team mana", bukan "pindahin 1 orang spesifik".
  - Solo task tanpa team (C2) — drop-in ke team existing, bukan "switch".
  - Auto cross-task detach di situ itu **convenience** buat task asal yang masih punya orang LAIN (Task A = Harto+Joko → Joko otomatis nyambung, gak masalah).

- **`switchTechnician()`** tepat kapanpun FOP niatnya emang "pindahin si A, biar B gantiin dia" — ada niat eksplisit soal siapa isi slotnya.

**Titik lemah nyata di `assignToTeam()` (bukan bug, tapi gap desain):** kalau task asal SOLO (1 orang) kena resolve/drop-in ke team lain, task asal bisa ditinggal KOSONG teknisi tanpa penolakan/warning — karena emang bukan tujuan desainnya buat jaga itu. Ini yang bikin `switchTechnician()` tetap perlu jadi endpoint terpisah, bukan cuma extend `assignToTeam()`.

**Rekomendasi pakai:**
- Mindahin 1 orang dengan rencana pengganti jelas → `switchTechnician()`.
- Nentuin task ambigu (solo/konflik) masuk team mana → `assignToTeam()` (modal konflik / drop-in).
