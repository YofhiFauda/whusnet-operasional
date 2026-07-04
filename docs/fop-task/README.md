# Sprint 8 — FOP Task Management & Design System

Dokumentasi fitur Sprint 8: Kanban Task Scheduler, Dashboard FOP, Design System UI, Calendar Scheduler, dan Task Workflow Management.

## Fitur Utama

| Sprint | Fitur | Status | Dokumentasi |
|--------|-------|--------|-------------|
| S8.1 | FOP Dashboard Overview | ✅ DONE | [FOP Dashboard](fop-dashboard.md) |
| S8.2-S8.3 | Kanban Task Scheduler | ✅ DONE | [Kanban Task Scheduler](kanban-task-scheduler.md) |
| S8.5 | Design System UI Konsistensi | ✅ DONE | [Design System](design-system-ui.md) |
| S8.6 | Overdue Indicator di Stat Cards | ✅ DONE | [Overdue Indicator](overdue-indicator.md) |
| S8.7 | Calendar Scheduler FOP | ✅ DONE | [Calendar Scheduler](calendar-scheduler.md) |
| S8.9 | Task Workflow & Approvals | ✅ DONE | [Task Workflow](task-workflow.md) |

---

## Ringkasan Fitur

### FOP Dashboard (S8.1)
Dashboard untuk FOP (Field Operations Planner) menampilkan ringkasan:
- Total tasks, completed, pending, cancelled
- Overdue indicator untuk SLA waiting phase
- Kanban 5-kolom dengan real-time updates via Reverb
- Stat cards dengan warna design system

**Route:** `/fop`  
**Controller:** `FopDashboardController.php`  
**View:** `fop/dashboard.blade.php`

### Kanban Task Scheduler (S8.2-S8.3)
Pipeline task 5-kolom dengan drag-drop support:
- **Antrean** — Task baru, belum dijadwalkan
- **Terjadwal** — Task sudah punya tanggal kerja
- **Berjalan** — Task in_progress
- **Selesai** — Task completed
- **Perlu Aksi FOP** — Task menunggu FOP review/approval

Real-time update via Reverb Echo.js, SLA countdown timer di setiap task card.

**Route:** `/fop/kanban`  
**Controller:** `FopKanbanController.php`  
**View:** `fop/kanban.blade.php`  
**Broadcasting:** `TaskStatusChanged` event via Reverb

### Design System UI Konsistensi (S8.5)
Migrasi dari hardcoded Tailwind colors ke design system CSS vars:
- Ganti `bg-slate-*`, `text-slate-*` → `var(--color-surface)`, `var(--color-text-main)`, dll
- Audit semua input foto: tambah `capture="environment"` untuk mobile camera
- Localize status labels ke Bahasa Indonesia

**Files updated:**
- `resources/views/surveys/queue.blade.php`
- `resources/views/customers/tabs/_survey.blade.php`
- `resources/views/customers/tabs/_installation.blade.php`
- `resources/views/tasks/show.blade.php`

### Overdue Indicator (S8.6)
Stat card FOP Dashboard menampilkan jumlah overdue per SLA:
- **Overdue Survey:** SLA 1×24 jam dari created_at
- **Overdue Installation:** SLA 3×24 jam dari survey completed_at

Warna merah indicator untuk prioritas FOP.

**File modified:** `FopDashboardController.php` (lines 164-181)

### Calendar Scheduler (S8.7)
Weekly calendar grid view untuk FOP:
- 7-hari (Senin-Minggu) dengan task cards
- Sidebar tim aktif + task count
- Detail panel untuk task checklist progress
- Next/prev week navigation

**Route:** `/fop/calendar`  
**Controller:** `FopCalendarController.php`  
**View:** `fop/calendar.blade.php`

### Task Workflow & Approvals (S8.9)
State machine untuk task dan customer workflow:
- Teknisi submit laporan → Task selesai, FOP review status pending
- FOP approve → Customer status transition (no auto-update)
- Clear audit trail via AuditLog

**Controllers involved:**
- `TaskSurveyReportController.php` (store, approve)
- `TaskInstallationReportController.php` (store, approve)
- `CustomerWorkflowService.php` (transition logic)

---

## Teknologi

| Komponen | Stack |
|----------|-------|
| Backend | Laravel 13, PHP 8.3 |
| Frontend | Blade, Alpine.js, Tailwind CSS + Design System vars |
| Real-time | Reverb (WebSocket), Echo.js |
| State Management | Alpine.js x-data, Laravel session |
| Database | MySQL (Task, Customer, AuditLog tables) |

---

## Database Tables

**Key tables untuk Sprint 8:**

| Tabel | Kolom Penting | Fungsi |
|-------|---------------|--------|
| `tasks` | id, customer_id, task_type, status, scheduled_at, started_at, completed_at | Core task record |
| `customers` | id, full_name, workflow_status, created_at | Customer workflow tracking |
| `customer_surveys` | id, customer_id, created_at, completed_at, survey_result | Survey phase record |
| `customer_installations` | id, customer_id, installation_status, completed_at | Installation phase record |
| `audit_logs` | id, user_id, auditable_id, action, changes | Workflow audit trail |
| `task_team_members` | task_id, user_id | Task assignment to technicians |
| `task_evidences` | task_id, evidence_type, file_path | Photo/document attachments |

---

## Keamanan & Access Control

**Permission checks:**
- `task.view.all` — FOP can view all tasks (POP-scoped)
- `task.status.complete` — Technician can submit report
- `task.review` — FOP can approve/reject

**Middleware:** `CheckPermission` middleware checks user role + permission

**Audit:** All workflow transitions logged to `audit_logs` table

---

## Next Steps

1. **Browser Testing:** Verify kanban drag-drop, calendar grid, overdue indicator
2. **Performance:** Monitor Reverb connections on high task volume
3. **UAT:** End-user testing S8 features (Sprint 14-15)

---

**Last updated:** 2026-06-27  
**Version:** 1.0
