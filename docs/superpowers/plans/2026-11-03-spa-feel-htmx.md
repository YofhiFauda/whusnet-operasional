# SPA Feel via htmx Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Navigasi antar halaman instant di seluruh app (htmx `hx-boost`), plus live-data-refresh tanpa reload penuh di 5 halaman yang sering dipakai/sering berubah/lintas role, tanpa menambah beban query baru.

**Architecture:** htmx dibundel via Vite, `hx-boost="true"` di `<body>` layout utama → semua nav jadi AJAX otomatis. Untuk 5 halaman target, listener Echo yang sekarang `fetch(window.location.href)` (fetch ulang SELURUH controller + DOMParser scan seluruh HTML) diganti fetch endpoint sempit baru yang reuse logic privat yang SUDAH ADA di controller/service masing-masing, lalu swap elemen target langsung (bukan re-parse halaman penuh).

**Tech Stack:** htmx (npm, dibundel Vite bareng Alpine), Laravel Echo/Reverb (sudah ada), PHPUnit Feature test.

**Spec:** `docs/superpowers/specs/2026-11-03-spa-feel-htmx-design.md`

## Koreksi Scope dari Spec

Spec §B menyebut endpoint dashboard akan "JSON agregat SQL, gantikan 4× `->get()`". Setelah membaca kode (`NocDashboardController::buildPerformanceLeaderboard()`), kalkulasi SLA-breach/durasi pakai method model (`Ticket::resolutionMinutes()`, `Ticket::isSlaBreached()`) — menulis ulang ini jadi SQL murni adalah pekerjaan optimasi query terpisah (sudah tercatat sebagai kategori 2 di `analisa-performa-seeder-15k.md`, bukan bagian dari task SPA-feel ini). Plan ini **tidak** menulis ulang query berat itu — cuma memindahkan hasil method privat yang SUDAH ADA ke endpoint sempit (JSON/partial), sehingga event broadcast tidak lagi memicu render SELURUH halaman (query+Blade bagian lain yang tidak terkait). Query beratnya sendiri tetap berat sampai dikerjakan terpisah — win di plan ini murni dari menghilangkan overhead full-page render + full-page re-fetch per event per tab.

## Global Constraints

- htmx WAJIB dibundel lewat Vite (`resources/js/app.js`), **bukan** CDN — konsisten alasan Alpine di CLAUDE.md §Tech Stack (ketergantungan jaringan luar = gagal diam-diam di app operasional internal).
- Endpoint partial baru WAJIB lewat POP scope (`EffectiveAccessService`)/policy yang sama dengan halaman penuhnya — tidak ada bypass otorisasi untuk endpoint "kecil".
- Dilarang fallback ke `window.location.reload()` di handler baru — kalau fetch partial gagal, biarkan DOM stale, log console saja.
- Form POST/PUT/DELETE tetap ikut pola PRG (CLAUDE.md) — `hx-boost` tidak mengubah response controller manapun di plan ini.
- `window.Alpine.initTree()` dipanggil ulang pada elemen yang baru di-swap htmx (pola sudah ada di `collector-realtime.blade.php`), supaya directive Alpine di partial baru ikut ter-bind.

## Review Focus

- **User di luar POP scope hit endpoint partial langsung (bukan lewat UI)** — endpoint baru harus 403/data kosong, bukan leak data POP lain. Test per task 2-6.
- **htmx boost pada form yang submit gagal validasi** — harus tetap render `withErrors()->withInput()` di halaman yang sama, bukan redirect kosong/blank (regresi pola PRG CLAUDE.md). Test di Task 1.
- **Event broadcast datang tapi elemen target sudah tidak ada di DOM** (user sudah pindah tab/halaman via boost sebelum event sampai) — listener harus no-op, bukan throw. Test di Task 2 & 3 (elemen dicek `if (!el) return`).
- **Tiket/task yang di-listen sudah berubah status jadi closed/selesai sebelum fetch partial selesai** (race event lama vs status baru) — endpoint partial harus tetap return state TERBARU saat di-fetch (query ulang, bukan pakai payload event), konsisten pola `FopTaskController::row()` yang query ulang dari DB.
- **htmx re-process elemen yang disuntik Alpine `x-if`/dinamis** — kalau partial baru mengandung directive Alpine yang di-swap htmx (bukan lewat `boost` nav biasa), perlu `htmx.process()`/`Alpine.initTree()` manual. Dicek di Task 4-6 (semua 3 dashboard pakai swap manual, bukan htmx swap biasa, jadi tetap pakai `Alpine.initTree()` yang sudah jadi pola).

---

## File Structure

| File | Peran |
|---|---|
| `package.json` | + dependency `htmx.org` |
| `resources/js/app.js` | import htmx, `htmx.config` dasar, listener global `htmx:responseError` (fallback native navigate) |
| `resources/views/layouts/app.blade.php` | tambah `hx-boost="true"` di `<body>` (baris 66) |
| `app/Http/Controllers/TicketController.php` | + method `statusPanel(Ticket $ticket)` |
| `resources/views/tickets/partials/_status-panel.blade.php` | **baru** — partial badge status+handler, diekstrak dari `tickets/show.blade.php` |
| `resources/views/tickets/show.blade.php` | include partial baru, tambah listener Echo `tickets.{popId}` → fetch `statusPanel` |
| `app/Http/Controllers/TaskController.php` | + method `statusPanel(Task $task)` |
| `resources/views/tasks/partials/_status-panel.blade.php` | **baru** — partial status+teknisi, diekstrak dari `tasks/show.blade.php` |
| `resources/views/tasks/show.blade.php` | include partial baru, tambah listener Echo `fop.{pop_id}` (filter `e.id === taskId`) → fetch `statusPanel` |
| `app/Http/Controllers/NocDashboardController.php` | + method `leaderboardStats(Request $request)` (reuse `buildPerformanceLeaderboard()` privat, return JSON) |
| `resources/views/noc/dashboard.blade.php` | ganti `refetchAndSwap()` dari full-page fetch → fetch `leaderboardStats`, assign ke Alpine data (bukan DOMParser) |
| `app/Http/Controllers/FopDashboardController.php` | + method `statsPanel(Request $request)` dan `containersPanel(Request $request)` (reuse data-gathering privat index() yang sudah ada, render partial sesuai target) |
| `resources/views/fop/partials/_stat-cards.blade.php`, `_status-teknisi.blade.php` | **baru** — diekstrak dari `fop/dashboard.blade.php` inline markup |
| `resources/views/fop/dashboard.blade.php` | `refreshTaskStats()`/`refreshDashboardContainers()` ganti target fetch ke 2 endpoint baru, swap per-container (bukan DOMParser seluruh halaman) |
| `app/Http/Controllers/CollectorWorksheetController.php` | + method `summaryPanel(Request $request)` (reuse `AdminCashBalanceService::tunaiBelumDisetor()` + `CollectorBalanceService::technicianSettlementStatus()`, render partial container) |
| `resources/views/partials/collector-realtime.blade.php` | `tambalOtomatis()` ganti target fetch dari `window.location.href` → endpoint `summaryPanel` baru (tetap generic lewat `PATCH_CONTAINER_ID`, cuma ganti URL) |
| `routes/web.php` | + 6 route GET baru (lihat tiap task) |
| `tests/Feature/*` | 6 file test baru (lihat tiap task) |

---

### Task 1: Pasang htmx global + nav instant

**Files:**
- Modify: `package.json`
- Modify: `resources/js/app.js`
- Modify: `resources/views/layouts/app.blade.php:66`
- Test: `tests/Feature/HtmxBoostNavigationTest.php`

**Interfaces:**
- Produces: `window.htmx` global (dari import `htmx.org`), `hx-boost="true"` di `<body>`.

- [ ] **Step 1: Install dependency**

Run: `npm install htmx.org`
Expected: `package.json` dependencies bertambah `"htmx.org": "^<versi terbaru>"`.

- [ ] **Step 2: Tulis test regresi PRG dulu (pastikan existing guard tetap jalan)**

Cek `tests/Feature/PostTargetRenderedServerSideTest.php` sudah ada dan lolos SEBELUM ubah apa pun:
Run: `php artisan test --compact tests/Feature/PostTargetRenderedServerSideTest.php`
Expected: PASS (baseline, belum ada perubahan).

- [ ] **Step 3: Tambah `hx-boost="true"` di `<body>` layout**

`resources/views/layouts/app.blade.php:66` — tambah atribut `hx-boost="true"` di tag `<body>` yang sudah ada.

- [ ] **Step 4: Import htmx & pasang fallback error di `app.js`**

Tambah di `resources/js/app.js`, sejajar import Alpine (setelah import Alpine, sebelum `Alpine.start()` tidak masalah urutan — htmx tidak depend ke Alpine):

```js
import 'htmx.org';

window.addEventListener('htmx:responseError', (e) => {
    window.location.href = e.detail.requestConfig.path;
});
```

- [ ] **Step 5: Build & smoke test manual**

Run: `npm run build`
Manual: buka app, klik beberapa link sidebar, cek Network tab browser — request nav harus `XMLHttpRequest`/fetch (header `HX-Request: true`), bukan full document navigation; address bar tetap berubah benar; tombol back/forward browser jalan normal.

- [ ] **Step 6: Jalankan ulang test PRG guard, pastikan masih lolos**

Run: `php artisan test --compact tests/Feature/PostTargetRenderedServerSideTest.php`
Expected: PASS — `hx-boost` tidak mengubah cara form merender `action`.

- [ ] **Step 7: Manual test form gagal validasi (Review Focus item 2)**

Manual: buka halaman create dengan form (misal `/customers/create`), submit dengan field wajib kosong. Expected: halaman yang sama muncul lagi dengan pesan error + input yang sudah diisi tetap ada (bukan blank/redirect kosong). Kalau gagal (htmx boost form intercept redirect/error secara aneh), catat sebagai temuan — jangan lanjut Task 2 sebelum ini beres.

- [ ] **Step 8: Commit**

```bash
git add package.json package-lock.json resources/js/app.js resources/views/layouts/app.blade.php
git commit -m "feat: pasang htmx hx-boost global untuk navigasi instant"
```

---

### Task 2: Live status-panel — Ticket detail

**Files:**
- Modify: `app/Http/Controllers/TicketController.php` (dekat `show()` baris 389)
- Create: `resources/views/tickets/partials/_status-panel.blade.php`
- Modify: `resources/views/tickets/show.blade.php`
- Modify: `routes/web.php` (dekat baris 1576, grup route tickets)
- Test: `tests/Feature/TicketStatusPanelLiveUpdateTest.php`

**Interfaces:**
- Consumes: channel `tickets.{popId}` + event `TicketQueueUpdated` (sudah ada, `routes/channels.php`, `app/Events/TicketQueueUpdated.php`) — payload cuma `popId`, TANPA ticket id (sesuai desain event itu, lihat komentarnya).
- Produces: route `tickets.status-panel` (`GET /tickets/{ticket}/status-panel`) → `TicketController::statusPanel(Ticket $ticket): View`, response HTML fragment partial `_status-panel`.

- [ ] **Step 1: Ekstrak markup status badge existing jadi partial**

Cari blok Blade di `tickets/show.blade.php` yang merender badge status+handler tiket (cari `handler`/`status` badge, biasanya dekat header halaman). Pindahkan markup itu verbatim ke `resources/views/tickets/partials/_status-panel.blade.php` (terima variabel `$ticket`), lalu ganti lokasi asalnya jadi `@include('tickets.partials._status-panel', ['ticket' => $ticket])`, bungkus elemen root partial dengan `id="ticket-status-panel"`.

- [ ] **Step 2: Tulis failing test**

```php
#[Test]
public function status_panel_endpoint_returns_current_ticket_status(): void
{
    $this->loginAsAdmin();
    $ticket = Ticket::factory()->create(['handler' => TicketHandler::NOC]);

    $response = $this->get(route('tickets.status-panel', $ticket));

    $response->assertOk();
    $response->assertSee($ticket->ticket_number);
}

#[Test]
public function status_panel_endpoint_respects_pop_scope(): void
{
    // user dgn scope POP lain dari ticket -> harus ditolak, pola sama show()
    $user = $this->loginAsRoleWithPopScope('helpdesk', popId: 999);
    $ticket = Ticket::factory()->create(['pop_id' => 1]);

    $response = $this->get(route('tickets.status-panel', $ticket));

    $response->assertForbidden();
}
```

(Sesuaikan nama helper scope dengan yang sudah dipakai test lain di `tests/Feature/TicketingTest.php` atau sejenis — ikuti pola existing, jangan buat helper baru.)

- [ ] **Step 3: Run test, verify fails**

Run: `php artisan test --compact tests/Feature/TicketStatusPanelLiveUpdateTest.php`
Expected: FAIL — route `tickets.status-panel` belum ada.

- [ ] **Step 4: Tambah route**

`routes/web.php` dekat baris 1576, di dalam grup middleware yang sama dengan `tickets.show`:
```php
Route::get('/tickets/{ticket}/status-panel', [TicketController::class, 'statusPanel'])->name('tickets.status-panel');
```
Static-before-dynamic: taruh SEBELUM route dinamis lain yang bisa menelan path ini kalau ada (cek urutan existing, ikuti CLAUDE.md §Konvensi Kode).

- [ ] **Step 5: Implement `TicketController::statusPanel(Ticket $ticket): View`**

Otorisasi identik dengan `show()` (POP scope + permission tiket) — panggil mekanisme yang sama persis (`$this->authorize(...)` atau scope check yang dipakai `show()`, baca method itu dan replikasi, jangan buat jalur baru). Return `view('tickets.partials._status-panel', ['ticket' => $ticket->fresh()])` — `fresh()` penting (Review Focus item 4: status terbaru, bukan dari cache instance lama).

- [ ] **Step 6: Run test, verify passes**

Run: `php artisan test --compact tests/Feature/TicketStatusPanelLiveUpdateTest.php`
Expected: PASS

- [ ] **Step 7: Tambah listener Echo di `tickets/show.blade.php`**

Tambah di Alpine component halaman (cari `init()`/`x-data` existing di file ini, tambah di situ, jangan bikin `x-data` baru):
```js
window.Echo.private(`tickets.${popId}`).listen('.TicketQueueUpdated', () => {
    const el = document.getElementById('ticket-status-panel');
    if (!el) return;
    fetch(`{{ route('tickets.status-panel', $ticket) }}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(res => res.text())
        .then(html => {
            el.outerHTML = html;
            if (window.Alpine) window.Alpine.initTree(document.getElementById('ticket-status-panel'));
        })
        .catch(() => {});
});
```
`popId` ambil dari variabel Blade yang sudah ada di halaman ini (`$ticket->pop_id`), bukan hardcode.

- [ ] **Step 8: Manual verification**

Buka tiket yang sama di 2 tab/browser beda user. Di satu tab, eskalasi tiket (trigger `TicketQueueUpdated`). Expected: tab lain badge status berubah TANPA reload, dalam ~1-2 detik.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/TicketController.php resources/views/tickets/partials/_status-panel.blade.php resources/views/tickets/show.blade.php routes/web.php tests/Feature/TicketStatusPanelLiveUpdateTest.php
git commit -m "feat: live status-panel halaman detail tiket tanpa reload"
```

---

### Task 3: Live status-panel — Task detail

**Files:**
- Modify: `app/Http/Controllers/TaskController.php` (dekat `show()` baris 203)
- Create: `resources/views/tasks/partials/_status-panel.blade.php`
- Modify: `resources/views/tasks/show.blade.php`
- Modify: `routes/web.php` (dekat baris 1373)
- Test: `tests/Feature/TaskStatusPanelLiveUpdateTest.php`

**Interfaces:**
- Consumes: channel `fop.{pop_id}` + event `TaskStarted`/`TaskCompleted` (sudah ada, payload punya `id` = Task.id, lihat `app/Events/TaskStarted.php::broadcastWith()`).
- Produces: route `tasks.status-panel` (`GET /tasks/{task}/status-panel`) → `TaskController::statusPanel(Task $task): View`.

- [ ] **Step 1: Ekstrak markup status+teknisi existing jadi partial**

Sama pola Task 2 — cari blok status/teknisi di `tasks/show.blade.php`, pindah ke `resources/views/tasks/partials/_status-panel.blade.php` (terima `$task`), include balik, bungkus `id="task-status-panel"`.

- [ ] **Step 2: Tulis failing test**

```php
#[Test]
public function status_panel_endpoint_returns_current_task_status(): void
{
    $this->loginAsAdmin();
    $task = Task::factory()->create(['status' => TaskStatus::IN_PROGRESS]);

    $response = $this->get(route('tasks.status-panel', $task));

    $response->assertOk();
    $response->assertSee($task->task_number);
}

#[Test]
public function status_panel_endpoint_respects_pop_scope(): void
{
    $user = $this->loginAsRoleWithPopScope('teknisi', popId: 999);
    $task = Task::factory()->create(['pop_id' => 1]);

    $response = $this->get(route('tasks.status-panel', $task));

    $response->assertForbidden();
}
```

- [ ] **Step 3: Run test, verify fails**

Run: `php artisan test --compact tests/Feature/TaskStatusPanelLiveUpdateTest.php`
Expected: FAIL — route belum ada.

- [ ] **Step 4: Tambah route**

`routes/web.php` dekat baris 1373, sebelum `/tasks/{task}/edit` kalau urutan static/dynamic mengharuskan (cek existing):
```php
Route::get('/tasks/{task}/status-panel', [TaskController::class, 'statusPanel'])->name('tasks.status-panel');
```

- [ ] **Step 5: Implement `TaskController::statusPanel(Task $task): View`**

Replikasi otorisasi `show()` (policy `TaskPolicy` yang sudah granular, CLAUDE.md §Arsitektur). Return `view('tasks.partials._status-panel', ['task' => $task->fresh()])`.

- [ ] **Step 6: Run test, verify passes**

Run: `php artisan test --compact tests/Feature/TaskStatusPanelLiveUpdateTest.php`
Expected: PASS

- [ ] **Step 7: Tambah listener Echo dengan filter id di `tasks/show.blade.php`**

```js
window.Echo.private(`fop.${popId}`)
    .listen('TaskStarted', (e) => { if (e.id === taskId) refreshStatusPanel(); })
    .listen('TaskCompleted', (e) => { if (e.id === taskId) refreshStatusPanel(); });

function refreshStatusPanel() {
    const el = document.getElementById('task-status-panel');
    if (!el) return;
    fetch(`{{ route('tasks.status-panel', $task) }}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(res => res.text())
        .then(html => {
            el.outerHTML = html;
            if (window.Alpine) window.Alpine.initTree(document.getElementById('task-status-panel'));
        })
        .catch(() => {});
}
```
`taskId` dan `popId` dari variabel Blade halaman ini (`$task->id`, `$task->pop_id`).

- [ ] **Step 8: Manual verification**

Sama pola Task 2 — 2 tab, trigger start/complete task, cek partial update tanpa reload.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/TaskController.php resources/views/tasks/partials/_status-panel.blade.php resources/views/tasks/show.blade.php routes/web.php tests/Feature/TaskStatusPanelLiveUpdateTest.php
git commit -m "feat: live status-panel halaman detail task tanpa reload"
```

---

### Task 4: NOC Dashboard — leaderboard jadi endpoint JSON sempit

**Files:**
- Modify: `app/Http/Controllers/NocDashboardController.php`
- Modify: `resources/views/noc/dashboard.blade.php:523,558`
- Modify: `routes/web.php` (dekat baris 1617)
- Test: `tests/Feature/NocDashboardLeaderboardStatsTest.php`

**Interfaces:**
- Consumes: channel `tickets.{popId}` + event `.TicketQueueUpdated` (sudah ada, listener existing di baris 523/558).
- Produces: route `noc.dashboard.leaderboard-stats` (`GET /noc/dashboard/leaderboard-stats`) → JSON `{helpdesk: array, noc: array}` (bentuk SAMA dengan return `buildPerformanceLeaderboard()` yang sudah ada).

- [ ] **Step 1: Tulis failing test**

```php
#[Test]
public function leaderboard_stats_endpoint_returns_json_shape(): void
{
    $this->loginAsAdmin();

    $response = $this->getJson(route('noc.dashboard.leaderboard-stats'));

    $response->assertOk();
    $response->assertJsonStructure(['helpdesk', 'noc']);
}

#[Test]
public function leaderboard_stats_endpoint_respects_pop_scope(): void
{
    $user = $this->loginAsRoleWithPopScope('noc', popId: 999);
    TicketHistory::factory()->create(['actor_id' => $user->id]); // di POP lain

    $response = $this->getJson(route('noc.dashboard.leaderboard-stats'));

    $response->assertOk();
    $response->assertJson(['helpdesk' => [], 'noc' => []]);
}
```

- [ ] **Step 2: Run test, verify fails**

Run: `php artisan test --compact tests/Feature/NocDashboardLeaderboardStatsTest.php`
Expected: FAIL — route belum ada.

- [ ] **Step 3: Tambah route**

`routes/web.php` dekat baris 1617, grup middleware sama dengan `noc.dashboard`:
```php
Route::get('/noc/dashboard/leaderboard-stats', [NocDashboardController::class, 'leaderboardStats'])->name('noc.dashboard.leaderboard-stats');
```

- [ ] **Step 4: Implement `NocDashboardController::leaderboardStats(Request $request): JsonResponse`**

Baca parameter filter yang sama dipakai `index()` untuk memanggil `buildPerformanceLeaderboard()` (lihat signature privat: `?string $selectedPopId, ?Carbon $dateFrom, ?Carbon $dateTo`) — ambil dari `$request` dengan nama query param YANG SAMA dipakai `index()` sekarang (cek baris pemanggilannya di `index()`, replikasi parsing-nya, jangan define ulang berbeda). Return `response()->json($this->buildPerformanceLeaderboard(...))`.

- [ ] **Step 5: Run test, verify passes**

Run: `php artisan test --compact tests/Feature/NocDashboardLeaderboardStatsTest.php`
Expected: PASS

- [ ] **Step 6: Ganti `refetchAndSwap()` di `noc/dashboard.blade.php`**

Cari fungsi `refetchAndSwap()` (baris ~523-558) yang sekarang `fetch(window.location.href)`. Ganti jadi fetch endpoint baru, assign hasil JSON ke variabel Alpine yang dipakai render leaderboard (cek `x-data`/binding leaderboard existing di file ini, assign ke situ — JANGAN swap innerHTML manual, biar Alpine re-render otomatis dari data yang baru di-assign):
```js
async refetchAndSwap() {
    try {
        const res = await fetch(`{{ route('noc.dashboard.leaderboard-stats') }}` + window.location.search);
        this.leaderboard = await res.json(); // nama properti sesuaikan binding x-data existing
    } catch (e) {
        console.error('Leaderboard refresh error:', e);
    }
}
```

- [ ] **Step 7: Manual verification**

Buka `/noc/dashboard` 2 tab. Trigger aksi tiket (selesai/eskalasi) di satu tab. Expected: leaderboard tab lain update tanpa reload, dan Network tab browser menunjukkan request ke `leaderboard-stats` (bukan full `/noc/dashboard`).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/NocDashboardController.php resources/views/noc/dashboard.blade.php routes/web.php tests/Feature/NocDashboardLeaderboardStatsTest.php
git commit -m "feat: endpoint leaderboard-stats NOC dashboard, hilangkan full-page refetch"
```

---

### Task 5: FOP Dashboard — stat-cards & containers jadi endpoint partial

**Files:**
- Modify: `app/Http/Controllers/FopDashboardController.php`
- Create: `resources/views/fop/partials/_stat-cards.blade.php`
- Create: `resources/views/fop/partials/_status-teknisi.blade.php`
- Modify: `resources/views/fop/dashboard.blade.php:794-842`
- Modify: `routes/web.php` (dekat baris 1289)
- Test: `tests/Feature/FopDashboardPanelsTest.php`

**Interfaces:**
- Consumes: channel `fop.{pop_id}` + event `TaskStarted/TaskCompleted/SurveyStarted/SurveyCompleted/InstallationStarted/InstallationCompleted` (sudah ada, listener existing baris 794-800).
- Produces: route `fop.dashboard.panels` (`GET /fop/dashboard/panels?targets=stat-cards,status-teknisi,antrian-survey,emergency-ticker,customer-pipeline`) → HTML, concatenated partial fragments masing-masing dibungkus elemen ber-`id` sesuai nama target (sama id dengan container existing: `stat-cards-container`, `status-teknisi-container`, `antrian-survey-container`, `emergency-ticker-container`, `customer-pipeline-funnel`).

- [ ] **Step 1: Ekstrak container existing jadi partial**

Di `fop/dashboard.blade.php`, cari 5 container (`stat-cards-container` dekat baris sebelum 222, `antrian-survey-container` baris 222, `status-teknisi-container`, `emergency-ticker-container` — ini mungkin sudah pakai `fop/partials/_emergency_ticker.blade.php`, cek dulu — , `customer-pipeline-funnel` — mungkin sudah `_pipeline_funnel.blade.php`). Untuk yang BELUM punya partial (`stat-cards-container`, `status-teknisi-container` berdasar grep sebelumnya), ekstrak markup-nya verbatim ke file baru di atas. Yang SUDAH punya partial (`_emergency_ticker.blade.php`, `_pipeline_funnel.blade.php`) tidak perlu diekstrak ulang — langsung dipakai di Step 3.

- [ ] **Step 2: Tulis failing test**

```php
#[Test]
public function panels_endpoint_returns_requested_containers_only(): void
{
    $this->loginAsAdmin();

    $response = $this->get(route('fop.dashboard.panels', ['targets' => 'stat-cards']));

    $response->assertOk();
    $response->assertSee('id="stat-cards-container"', false);
    $response->assertDontSee('id="customer-pipeline-funnel"', false);
}

#[Test]
public function panels_endpoint_respects_pop_scope(): void
{
    $user = $this->loginAsRoleWithPopScope('fop', popId: 999);

    $response = $this->get(route('fop.dashboard.panels', ['targets' => 'stat-cards']));

    $response->assertOk(); // scope ditegakkan di DALAM data, bukan status code — assert data tidak bocor POP lain
}
```

- [ ] **Step 3: Run test, verify fails**

Run: `php artisan test --compact tests/Feature/FopDashboardPanelsTest.php`
Expected: FAIL — route belum ada.

- [ ] **Step 4: Tambah route**

```php
Route::get('/fop/dashboard/panels', [FopDashboardController::class, 'panels'])->name('fop.dashboard.panels');
```

- [ ] **Step 5: Implement `FopDashboardController::panels(Request $request): View`**

Baca `$request->query('targets')` jadi array nama target (comma-separated). Panggil SUBSET method privat data-gathering yang SUDAH dipakai `index()` — baca `index()` buat identifikasi method mana menyuplai data ke tiap container (misal variabel yang dioper ke view untuk `stat-cards`, `status-teknisi`, dst), panggil hanya yang relevan dengan target yang diminta (jangan panggil SEMUA data index() kalau cuma `targets=stat-cards` yang diminta — itu poin utama dari task ini, hindari replikasi biaya full index()). Return view baru `fop.partials._panels-wrapper` yang `@foreach($targets as $target) @include("fop.partials._{$target}", $data[$target]) @endforeach` — ATAU, lebih sederhana, string konkatenasi tiap partial view render manual di controller. Pilih yang lebih konsisten dengan pola Blade existing di file ini.

- [ ] **Step 6: Run test, verify passes**

Run: `php artisan test --compact tests/Feature/FopDashboardPanelsTest.php`
Expected: PASS

- [ ] **Step 7: Ganti `refreshTaskStats()`/`refreshDashboardContainers()` di `fop/dashboard.blade.php`**

Ganti isi 2 fungsi itu (baris 811-842) dari `fetch(window.location.href)` + DOMParser-seluruh-halaman jadi:
```js
async refreshPanels(targets) {
    try {
        const res = await fetch(`{{ route('fop.dashboard.panels') }}?targets=${targets.join(',')}`);
        const html = await res.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        targets.forEach(id => {
            const containerId = id + '-container'; // sesuaikan mapping nama target -> id existing
            const el = document.getElementById(containerId);
            const newEl = doc.getElementById(containerId);
            if (el && newEl) {
                el.innerHTML = newEl.innerHTML;
                if (window.Alpine) window.Alpine.initTree(el);
            }
        });
    } catch (e) {
        console.error('Panel refresh error:', e);
    }
},
refreshTaskStats() { this.refreshPanels(['stat-cards', 'status-teknisi']); },
refreshDashboardContainers() { this.refreshPanels(['stat-cards', 'antrian-survey', 'status-teknisi', 'emergency-ticker', 'customer-pipeline']); },
```
(DOMParser di sini scope-nya kecil — cuma parse response partial yang sudah sempit, BUKAN parse full-page HTML seperti pola lama. Bedanya di payload yang di-fetch, bukan di teknik parsing-nya.)

- [ ] **Step 8: Manual verification**

Buka `/fop` 2 tab. Trigger `TaskStarted` (teknisi mulai task). Expected: stat-cards + status-teknisi tab lain update, Network tab browser nunjukkin request ke `/fop/dashboard/panels?targets=stat-cards,status-teknisi` (bukan full `/fop`).

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/FopDashboardController.php resources/views/fop/partials/_stat-cards.blade.php resources/views/fop/partials/_status-teknisi.blade.php resources/views/fop/dashboard.blade.php routes/web.php tests/Feature/FopDashboardPanelsTest.php
git commit -m "feat: endpoint panels sempit FOP dashboard, hilangkan full-page refetch"
```

---

### Task 6: Collector Worksheet — summary jadi endpoint partial

**Files:**
- Modify: `app/Http/Controllers/CollectorWorksheetController.php`
- Modify: `resources/views/partials/collector-realtime.blade.php`
- Modify: `routes/web.php` (dekat baris 475)
- Test: `tests/Feature/CollectorWorksheetSummaryPanelTest.php`

**Interfaces:**
- Consumes: channel `collector-activity.{popId}` + event `.CollectorDepositUpdated`/`.CollectorActivityUpdated`/`.CashDepositUpdated` (sudah ada, listener existing `collector-realtime.blade.php:150-157`).
- Produces: route `collector-worksheet.summary-panel` (`GET /collector-worksheet/summary-panel`) → HTML fragment, isi sama dengan container yang di-patch `PATCH_CONTAINER_ID` existing.

- [ ] **Step 1: Identifikasi container yang dipatch `tambalOtomatis()` saat ini**

Baca `collector-worksheet/index.blade.php` dan `show.blade.php` — cari nilai `PATCH_CONTAINER_ID` yang dioper ke `collector-realtime.blade.php` di masing-masing (kemungkinan beda antara index & show). Catat elemen mana itu dan variabel apa yang direndernya (kemungkinan hasil `AdminCashBalanceService::tunaiBelumDisetor()` + `CollectorBalanceService::technicianSettlementStatus()` dari §11 analisa performa).

- [ ] **Step 2: Tulis failing test**

```php
#[Test]
public function summary_panel_endpoint_returns_current_balance_data(): void
{
    $this->loginAsAdmin();

    $response = $this->get(route('collector-worksheet.summary-panel'));

    $response->assertOk();
}

#[Test]
public function summary_panel_endpoint_respects_pop_scope(): void
{
    $user = $this->loginAsRoleWithPopScope('pop_admin', popId: 999);

    $response = $this->get(route('collector-worksheet.summary-panel'));

    $response->assertOk(); // data di dalamnya harus ter-scope, bukan status code
}
```

- [ ] **Step 3: Run test, verify fails**

Run: `php artisan test --compact tests/Feature/CollectorWorksheetSummaryPanelTest.php`
Expected: FAIL — route belum ada.

- [ ] **Step 4: Tambah route**

```php
Route::get('/collector-worksheet/summary-panel', [CollectorWorksheetController::class, 'summaryPanel'])->name('collector-worksheet.summary-panel');
```
Taruh SEBELUM `/collector-worksheet/{collector}` (baris 477) — static-before-dynamic (CLAUDE.md), biar `{collector}` gak menelan `summary-panel`.

- [ ] **Step 5: Implement `CollectorWorksheetController::summaryPanel(Request $request): View`**

Panggil method yang SAMA dipanggil `index()` untuk mengisi container yang diidentifikasi Step 1 (`AdminCashBalanceService`/`CollectorBalanceService`) — jangan hitung ulang data lain yang tidak relevan ke container itu. Return view partial yang isinya sama dengan yang dirender `index()` untuk container itu (ekstrak ke partial kalau belum ada, ikuti pola Task 4/5).

- [ ] **Step 6: Run test, verify passes**

Run: `php artisan test --compact tests/Feature/CollectorWorksheetSummaryPanelTest.php`
Expected: PASS

- [ ] **Step 7: Ganti target fetch di `tambalOtomatis()`**

`resources/views/partials/collector-realtime.blade.php:115` — ganti:
```js
fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
```
jadi:
```js
fetch(`{{ route('collector-worksheet.summary-panel') }}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
```
Sisanya (`DOMParser` cari `PATCH_CONTAINER_ID`, `replaceWith`) tetap sama — cukup sumber fetch-nya yang berubah dari halaman penuh ke endpoint sempit.

- [ ] **Step 8: Manual verification**

Buka `/collector-worksheet` 2 tab (admin). Kolektor setor di tab lain/device lain (trigger `CollectorDepositUpdated`). Expected: container update tanpa reload, Network tab browser nunjukkin request ke `summary-panel` bukan full `/collector-worksheet`.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/CollectorWorksheetController.php resources/views/partials/collector-realtime.blade.php routes/web.php tests/Feature/CollectorWorksheetSummaryPanelTest.php
git commit -m "feat: endpoint summary-panel sempit collector worksheet, hilangkan full-page refetch"
```

---

### Task 7: Regresi & verifikasi akhir

**Files:**
- Test: jalankan seluruh test Feature yang disentuh plan ini (bukan full suite — CLAUDE.md melarang `composer test` tanpa filter)

**Interfaces:**
- Consumes: semua task sebelumnya.

- [ ] **Step 1: Jalankan semua test baru sekaligus**

Run: `php artisan test --compact tests/Feature/HtmxBoostNavigationTest.php tests/Feature/TicketStatusPanelLiveUpdateTest.php tests/Feature/TaskStatusPanelLiveUpdateTest.php tests/Feature/NocDashboardLeaderboardStatsTest.php tests/Feature/FopDashboardPanelsTest.php tests/Feature/CollectorWorksheetSummaryPanelTest.php`
Expected: semua PASS.

- [ ] **Step 2: Jalankan ulang guard existing yang berisiko kena regresi**

Run: `php artisan test --compact tests/Feature/PostTargetRenderedServerSideTest.php`
Expected: PASS.

- [ ] **Step 3: `vendor/bin/pint --dirty --format agent`**

Run pint, perbaiki temuan format kalau ada.

- [ ] **Step 4: `npm run build` final**

Run build, pastikan tidak ada error Vite/manifest.

- [ ] **Step 5: Manual verification checklist (tidak ada test otomatis untuk ini)**

- Nav instant: klik berurutan 5+ halaman berbeda, address bar & back/forward browser benar, tidak ada flash reload.
- Form gagal validasi (ulangi Task 1 Step 7 di 2-3 halaman create lain) — pastikan tidak regresi.
- Live-refresh 2 tab untuk kelima halaman (Task 2-6 manual verification masing-masing) — ulang sekali lagi setelah semua task gabung, pastikan tidak ada konflik antar listener.

- [ ] **Step 6: Update `docs/TASKS.md`**

Tandai task SPA-feel htmx ini selesai di tabel Ad-Hoc Improvements (CLAUDE.md §Cara Kerja Task poin 5). Minta user konfirmasi nomor ADHOC yang dipakai sebelum menulis (nomor urut saat ini ADHOC-261+, cek baris terakhir tabel).

- [ ] **Step 7: Commit penutup (kalau ada file tersisa belum ter-commit, mis. TASKS.md)**

```bash
git add docs/TASKS.md
git commit -m "docs: tandai SPA-feel htmx selesai di TASKS.md"
```
