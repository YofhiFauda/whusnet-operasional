# Analisa Flow Baru: Sprint Doc, Implementasi & UI/UX

> Tanggal analisa: 2026-06-27
> Branch: dev

---

## 1. Kesesuaian Sprint Doc vs Brief

### Kesimpulan: **Mayoritas sesuai**, dengan 2 koreksi wajib dan 2 typo di brief.

| # | Aspek | Sprint Doc | Brief / Jawaban | Verdict |
|---|---|---|---|---|
| 1 | Aktor "Proses ke TIM" | FOP/Helpdesk | **Admin/Helpdesk** (bukan FOP) | ❌ Sprint doc salah |
| 2 | State naming | `waiting-survey` (hyphen) | — | ⚠️ Codebase pakai underscore `waiting_survey` |
| 3 | Step 11 brief: status setelah "Mulai Pemasangan" | `process-installation` ✓ | **Typo** — tulis "Menunggu Pemasangan" | Sprint doc BENAR |
| 4 | Step 6 & 8 brief | Diisi lengkap | Kalimat tidak selesai | Sprint doc BENAR |
| 5 | SLA survey 1×24 jam | Direncanakan Sprint 6 | Jawaban #2 | Sesuai |
| 6 | SLA verifikasi 3×24 jam | Direncanakan Sprint 6 | Jawaban #2 | Sesuai |
| 7 | Teknisi boleh 2 task beda jam | Sprint 6 edge case | Jawaban #4 | Sesuai |

### Koreksi wajib pada sprint doc:
- **Sprint 3.2**: "Proses ke TIM" → aktor adalah **Admin/Helpdesk**, bukan FOP. FOP hanya menjadwal teknisi, bukan ACC.
- **State names**: ganti semua hyphen ke underscore sesuai konvensi codebase (`waiting_survey`, `process_survey`, dll.)

---

## 2. Status Implementasi per Sprint

### Sprint 1 — Fondasi: State Machine & DB

| Task | Status | Catatan |
|---|---|---|
| Kolom `status` pada `customers` | ✅ | `WorkflowTransition` enum ada |
| Tabel `customer_tasks` | ⚠️ | Diimplementasi sebagai tabel `tasks` + model `Task.php` (lebih generik) |
| Pivot `customer_task_technicians` | ⚠️ | Diimplementasi sebagai model `TaskTeam.php` |
| Kolom SLA pada `customer_surveys` | ✅ | `started_at`, `completed_at` ada |
| Kolom SLA pada `customer_installations` | ✅ | `started_at`, `completed_at` ada |
| Model `CustomerTask` | ❌ | Pakai `Task` model — tapi fungsionalitas sama |
| `App\Enums\CustomerStatus` | ❌ | Pakai `App\Enums\WorkflowTransition` — fungsionalitas sama |
| Permission seed | ⚠️ | Nama berbeda: `customers.detail.survey.update` bukan `customer.survey.start` |
| `CustomerStatusLog` tabel | ❌ | Belum ada — transisi tidak di-log ke tabel tersendiri |

### Sprint 2 — FOP Penjadwalan Survey & Task Teknisi

| Task | Status | Catatan |
|---|---|---|
| FOP list task `waiting_survey` | ✅ | `FopDashboardController` — kolom "Antrean Tiket" di kanban |
| Form penjadwalan 1–3 teknisi | ✅ | Button "Jadwalkan & Tugaskan" di kanban |
| `TaskScheduled` event → Reverb → Teknisi | ✅ | `app/Events/TaskScheduled.php` → `private-teknisi.{id}` |
| Halaman Task Teknisi | ✅ | `tasks/own.blade.php` — mobile-first |
| Notifikasi real-time saat task di-assign | ✅ | `technicianNotifier()` Alpine component + banner |
| Kalender Scheduler FOP | ❌ | **Belum ada** — tidak ada FullCalendar atau grid kalender |
| Validasi bentrok jadwal teknisi | ❌ | Belum diimplementasi |

### Sprint 3 — Laporan Survey & Verifikasi ACC

| Task | Status | Catatan |
|---|---|---|
| "Mulai Survey" button | ✅ | Ada di `surveys/queue.blade.php` |
| Form laporan survey | ✅ | `customers.survey.report` route |
| Status → `waiting_acc` setelah laporan | ✅ | `CustomerWorkflowService::transition()` |
| Panel ACC "Proses ke TIM" | ⚠️ | Ada di FOP Dashboard — **seharusnya di panel Admin/Helpdesk** |
| `SurveyCompleted` event → Reverb | ✅ | `app/Events/SurveyCompleted.php` ada |
| Broadcast ke FOP saat laporan masuk | ⚠️ | Event ada tapi belum dicek apakah di-listen di FOP dashboard |

### Sprint 4 — Fase Pemasangan

| Task | Status | Catatan |
|---|---|---|
| List FOP `waiting_installation` + SLA | ⚠️ | Ada di FOP Dashboard kolom — tapi tidak ada SLA countdown waiting |
| Form penjadwalan tim teknisi pemasangan | ✅ | Sama dengan survey — pakai form task baru |
| Task pemasangan di task teknisi | ✅ | `tasks/own.blade.php` handle `task_type = pemasangan` |
| "Mulai Pemasangan" button | ⚠️ | Ada di `TaskStatusController` — tapi flow ke button tidak obvious dari Task own page |
| Form laporan pemasangan | ✅ | `TaskInstallationReportController` + form di `tasks/own.blade.php` |
| Status → `pending_verification` / `installed` | ✅ | `workflowService->transition()` |
| `InstallationCompleted` event | ✅ | `app/Events/InstallationCompleted.php` ada |

### Sprint 5 — Verifikasi Admin & Aktivasi

| Task | Status | Catatan |
|---|---|---|
| Halaman verifikasi admin | ✅ | `verifications/admin.blade.php` — timeline lengkap |
| Timeline registrasi → survey → pemasangan | ✅ | Data ditampilkan di halaman verifikasi |
| Form verifikasi admin | ✅ | Ada |
| Generate CID | ✅ | Implementasi ada di `CustomerController` |
| Status → `active` | ✅ | |
| Notifikasi ke pelanggan (Telegram/WA) | ❌ | Belum diimplementasi |
| Halaman pelanggan aktif | ✅ | Filter status `active` di customer index |

### Sprint 6 — SLA, Reverb & Edge Cases

| Task | Status | Catatan |
|---|---|---|
| `x-countdown-timer` component | ✅ | Ada di `resources/views/components/countdown-timer.blade.php` |
| SLA countdown saat task `in_progress` | ✅ | Dipakai di `tasks/own.blade.php` dan `tasks/show.blade.php` |
| SLA countdown `waiting_survey` 1×24 jam | ❌ | `surveys/queue.blade.php` punya `data-start` tapi render "Menghitung..." — tidak pakai `x-countdown-timer` |
| SLA countdown `waiting_installation` 3×24 jam | ❌ | Belum ada countdown di halaman verifikasi/pemasangan |
| Badge "Melewati SLA" | ✅ | `isOverSla()` + badge error merah |
| Reverb Echo setup | ✅ | `echo.js` terkonfigurasi ke Reverb |
| Events broadcast | ✅ | TaskScheduled, TaskStarted, TaskCompleted, SurveyStarted, SurveyCompleted, InstallationStarted, InstallationCompleted — semua ada |
| Reassign teknisi tanpa reset status | ❌ | Belum ada |
| Log semua transisi status | ❌ | Tidak ada tabel `customer_status_logs` |
| Notifikasi Telegram ke FOP jika overdue | ❌ | Belum |
| Mobile-friendly task teknisi | ✅ | `max-w-2xl`, cards responsif |
| `capture="environment"` foto | ⚠️ | Hanya di `tasks/own.blade.php` upload modal — belum di semua form foto |

---

## 3. Analisa UI/UX Frontend

### Yang Sudah Baik

**FOP Dashboard (`fop/dashboard.blade.php`)**
- Kanban pipeline 5 kolom: Antrean Tiket → Terjadwal → In Progress → Selesai Hari Ini → Perlu Aksi FOP
- Stat cards ringkas di atas
- Task cards dengan SLA countdown saat in_progress
- Button "Jadwalkan & Tugaskan" per tiket — flow jelas

**Task Teknisi (`tasks/own.blade.php`)**
- `max-w-2xl mx-auto px-4` — layout mobile-first ✅
- Color stripe di atas setiap task card (biru/amber/hijau/merah sesuai status)
- `ring-2 ring-amber-400` highlight task yang sedang in_progress — visual priority jelas
- Countdown SLA real-time saat task berjalan (`x-countdown-timer`)
- Banner real-time saat task baru di-assign (Reverb `TaskScheduled`)
- Upload foto dengan modal langsung di halaman — tidak pindah halaman

**Task Detail (`tasks/show.blade.php`)**
- Metric strip: Tipe / Jadwal / SLA / POP / Checklist — info key dalam 1 baris
- Checklist dengan progress bar
- Tim teknisi card dengan avatar inisial
- Ringkasan waktu aktual vs SLA setelah selesai

---

### Masalah UI/UX yang Perlu Diperbaiki

#### 🔴 Kritis

**1. Design system tidak konsisten**
- `surveys/queue.blade.php` dan `customers/tabs/_survey.blade.php` masih pakai `bg-slate-*`, `text-slate-*`, `bg-white border-slate-200`
- `tasks/show.blade.php` dan `tasks/own.blade.php` pakai CSS vars `var(--color-*)` dari design system
- Dua gaya visual berbeda dalam satu app — kelihatan tidak selesai

**2. SLA countdown `waiting_survey` tidak berfungsi**
- `surveys/queue.blade.php` render `<div data-start="...">Menghitung...</div>` tapi tidak ada logic Alpine/JS yang menghitung countdown
- `x-countdown-timer` component sudah ada tapi tidak dipakai di sini
- Pelanggan yang sudah 20+ jam waiting_survey tidak ada indikator urgensi

**3. "Mulai Survey" tidak di alur Task Teknisi**
- Sprint doc: Teknisi buka Task → Detail → tekan "Mulai Survey"
- Aktual: "Mulai Survey" ada di `surveys/queue.blade.php` — halaman terpisah, bukan dari task teknisi
- Teknisi di `tasks/own.blade.php` tidak bisa mulai survey dari sana — harus tahu ke mana pergi

**4. "Proses ke TIM" ada di FOP Dashboard, bukan Admin**
- Brief: Admin/Helpdesk yang tekan setelah verifikasi ke pelanggan
- Aktual: button di `FopDashboardController` — FOP yang tekan
- Ini beda aktor dan beda konteks bisnis

#### 🟡 Perlu Diperbaiki

**5. `surveys/queue.blade.php` tidak mobile-friendly**
- Layout tabel dengan 9 kolom — tidak bisa dipakai teknisi di HP
- Teknisi yang perlu mulai survey dari HP akan kesulitan

**6. Label status bahasa campur**
- `surveys/queue.blade.php`: "WAITING", "IN PROGRESS" (English)
- `tasks/own.blade.php`: "Terjadwal", "Sedang Berjalan" (Indonesian)
- Pilih satu — konsisten Indonesian

**7. `capture="environment"` hanya di 1 tempat**
- Hanya di modal upload `tasks/own.blade.php`
- Form laporan pemasangan di `_installation.blade.php` tidak punya `capture="environment"`
- Teknisi lapangan pakai HP — semua input foto harus langsung kamera

**8. Tidak ada Kalender Scheduler**
- FOP tidak bisa lihat jadwal mingguan/bulanan
- Hanya bisa lihat task hari ini di kanban
- Sprint doc Sprint 2.3 belum diimplementasi sama sekali

**9. SLA waiting_installation tidak ada countdown**
- `verifications/queue.blade.php` punya countdown placeholder tapi pakai JavaScript manual (`data-start`) bukan komponen countdown
- Tidak konsisten dengan `x-countdown-timer` yang sudah ada

**10. Status badge di `_survey.blade.php` tidak dilocalize**
- Menampilkan raw value: "completed", "failed", "pending"
- Seharusnya: "Selesai", "Gagal", "Menunggu"

---

## 4. Sprint Terbarukan (Koreksi + Yang Tersisa)

### Sprint A — Koreksi Flow & Aktor (Prioritas Tinggi)

**Goal:** Pindah "Proses ke TIM" dari FOP ke Admin/Helpdesk + Integrasikan "Mulai Survey/Pemasangan" ke dalam alur Task Teknisi.

- [ ] Pindah endpoint "Proses ke TIM" dari `FopDashboardController` ke controller Admin/Helpdesk
- [ ] Buat permission check: hanya role Admin/Helpdesk yang bisa tekan "Proses ke TIM"
- [ ] Di `tasks/own.blade.php`: tambah button "Mulai Survey" untuk task tipe `survey` yang status `terjadwal` — trigger `customers.survey.start`
- [ ] Di `tasks/own.blade.php`: tambah button "Mulai Pemasangan" untuk task tipe `pemasangan` yang status `terjadwal`
- [ ] Setelah mulai, button berganti ke "Laporan Survey" / "Laporan Pemasangan" (sudah ada logic-nya — tinggal tambah di halaman yang tepat)

### Sprint B — Design System & Konsistensi UI

**Goal:** Semua halaman pakai CSS vars, semua label Indonesian, `capture="environment"` merata.

- [ ] Refactor `surveys/queue.blade.php`: ganti `bg-slate-*` → `var(--color-*)`, ganti label "WAITING"/"IN PROGRESS" → "Menunggu Survey"/"Proses Survey"
- [ ] Refactor `customers/tabs/_survey.blade.php` dan `_installation.blade.php`: ganti hardcoded slate colors ke design system vars
- [ ] Localize status badge `_survey.blade.php`: "completed" → "Selesai", "failed" → "Tidak Layak", "pending" → "Menunggu"
- [ ] Tambah `capture="environment"` ke semua `<input type="file">` untuk foto di form survey dan pemasangan

### Sprint C — SLA Waiting Phase

**Goal:** Countdown real-time untuk `waiting_survey` (1×24 jam) dan `waiting_installation` (3×24 jam).

- [ ] Tambah kolom `waiting_since` atau pakai `updated_at` sebagai basis SLA waiting
- [ ] Replace `surveys/queue.blade.php` countdown placeholder dengan `<x-countdown-timer>` yang menghitung dari `created_at` customer (1×24 jam deadline)
- [ ] Tambah `<x-countdown-timer>` di `verifications/queue.blade.php` untuk `waiting_installation` (3×24 jam dari `completed_at` survey)
- [ ] Warning badge merah jika SLA < 6 jam tersisa
- [ ] FOP Dashboard stat card: tambah indikator jumlah overdue

### Sprint D — Kalender Scheduler FOP

**Goal:** FOP bisa lihat jadwal teknisi mingguan/bulanan.

- [ ] Install `fullcalendar` via npm atau gunakan grid Blade manual
- [ ] Route `GET /fop/calendar` → controller fetch semua task dengan `scheduled_at` dalam bulan ini
- [ ] Color-code: survey = biru, pemasangan = amber
- [ ] Klik event → slide-over detail task
- [ ] Filter per POP

### Sprint E — Audit Log & Edge Cases

**Goal:** Semua transisi status tercatat, teknisi bisa di-reassign, notifikasi pelanggan saat aktif.

- [ ] Buat migrasi tabel `customer_status_logs` (customer_id, from_status, to_status, changed_by, note, created_at)
- [ ] Panggil insert log di `CustomerWorkflowService::transition()`
- [ ] Fitur reassign teknisi: FOP ganti anggota tim task tanpa reset status customer
- [ ] Validasi bentrok jadwal: teknisi tidak bisa di-assign ke 2 task yang waktu-nya overlap
- [ ] Notifikasi ke pelanggan saat status → `active` (minimal via log dulu, Telegram/WA sebagai enhancement)

---

## 5. Ringkasan Status Keseluruhan

| Komponen | Status |
|---|---|
| Core state machine flow | ✅ Berjalan |
| FOP penjadwalan task survey & pemasangan | ✅ Berjalan |
| Task Teknisi (own page) | ✅ Berjalan — mobile-friendly |
| Reverb real-time (task assign ke teknisi) | ✅ Berjalan |
| Countdown SLA saat task in_progress | ✅ Berjalan |
| Verifikasi admin + aktivasi | ✅ Berjalan |
| "Mulai Survey" dari halaman task teknisi | ✅ Berjalan (S8.4-T002 Done) |
| "Mulai Pemasangan" dari halaman task teknisi | ⚠️ In Progress (S8.4-T003) |
| "Proses ke TIM" aktor benar (Admin/Helpdesk) | ❌ Belum — masih di FOP (S8.4-T001) |
| FOP landing di FOP Dashboard (bukan admin dashboard) | ❌ Belum — masih di dashboard billing (S8.4-T004) |
| Kalender scheduler FOP | ❌ Belum (S8.7) |
| SLA countdown waiting phases | ❌ Belum berfungsi (S8.6) |
| Design system konsisten di semua halaman | ❌ Belum (S8.5) |
| Customer status log | ❌ Belum (S8.8) |
| Notifikasi pelanggan saat aktif | ❌ Belum (S8.8) |

---

## 6. Temuan Tambahan dari Review Kode (2026-06-27)

> Review menyeluruh kode aktual (bukan hanya dokumen sprint) menemukan satu gap kritis yang belum terdokumentasi di sprint sebelumnya.

### Gap Baru: FOP Landing di Dashboard Admin, Bukan FOP Dashboard

**File**: `app/Http/Controllers/DashboardController.php` — baris 19–28

```php
public function index(Request $request)
{
    if (!auth()->user()->hasPermission('dashboard.view')) {
        if (auth()->user()->hasPermission('task.view.own')) {
            return redirect()->route('tasks.own');  // ✅ Teknisi diarahkan benar
        }
        // ...
    }
    // FOP punya 'dashboard.view' → masuk ke sini → dashboard billing/invoice admin
}
```

**Masalah**: FOP memiliki permission `dashboard.view` sehingga mereka **tidak** masuk ke blok `if (!hasPermission)`. Akibatnya FOP mendarat di dashboard admin generik yang menampilkan stat billing, invoice jatuh tempo, dan piutang — bukan FOP Dashboard (`/fop`) yang relevan untuk pekerjaan mereka.

**Dampak operasional**: FOP harus manually navigasi ke `/fop` setiap login. Tidak ada link yang jelas. Dashboard yang mereka lihat pertama (billing/invoice) tidak relevan untuk peran lapangan FOP.

**Fix**: Tambahkan redirect spesifik untuk role FOP di `DashboardController::index()` sebelum render dashboard admin:

```php
// Tambahkan di atas blok permission check
if (auth()->user()->hasRole('fop')) {
    return redirect()->route('fop.dashboard');
}
```

**File yang diubah**: `app/Http/Controllers/DashboardController.php` — insert 3 baris di baris 19.

---

### Konfirmasi Temuan Sebelumnya (Sudah Ada di Sprint)

| Temuan | Status di Kode | Sprint |
|---|---|---|
| "Mulai Survey" di `tasks/own.blade.php` | ✅ Ada — baris 255, POST ke `customers.survey.start` | S8.4-T002 Done |
| Slide-over laporan survey inline (5 step) | ✅ Ada — `surveyReportWizard()` Alpine component | S8.2-T004 Done |
| Slide-over laporan pemasangan inline (4 step) | ✅ Ada — `installReportWizard()` Alpine component | S8.2-T005 Done |
| Real-time notif teknisi via Reverb | ✅ Ada — `technicianNotifier()` + Echo listener | S8.2-T010 Done |
| FOP Kanban Antrean Tiket + Jadwalkan & Tugaskan | ✅ Ada — `fop/dashboard.blade.php` | S8.3-T002 Done |
| Conflict check jadwal teknisi di FOP | ✅ Ada — `/api/tasks/check-conflict` | S8.3-T003 Done |
| FOP landing page redirect | ❌ Belum ada | **S8.4-T004 (Baru)** |
