> **Arsip.** Dokumen historis, sebagian sudah tidak sesuai kode aktif (lihat [../README.md](../README.md) untuk dokumentasi terkini).

# Task Workflow & Approvals (S8.9)

State machine untuk task dan customer workflow dengan approval gate oleh FOP.

## Overview

**Principle:** Teknisi submit laporan → Task selesai, **NO customer status change**. FOP review & approve → Customer transition.

**Key rule:** Laporan submission is NOT auto-update trigger. FOP approval is.

## Workflow State Machine

```
Customer Workflow:
┌─────────────────┐
│   Registrasi    │ (initial state)
└────────┬────────┘
         │
         ├─→ [Survey Task] → [Survey Report] → (waiting FOP)
         │   ↓
         │   [FOP Approve] ─→ Verifikasi Lapangan
         │
         ├─→ [Installation Task] → [Installation Report] → (waiting FOP)
         │   ↓
         │   [FOP Approve] ─→ Aktivasi
         │
         ├─→ [Activation Task] → (auto-transition)
         │
         └─→ Aktif (final state)

Task Workflow:
Pending → Terjadwal → In Progress → (Report Submit) → Selesai [waiting FOP]
                                     ↓
                                [FOP Approve] → FOP Review Complete
                                [FOP Reject] → Back to In Progress
```

## Database Tables

| Tabel | Role |
|-------|------|
| `customers` | Workflow status tracking |
| `tasks` | Task lifecycle + fop_review_status |
| `customer_surveys` | Survey phase record |
| `customer_installations` | Installation phase record |
| `audit_logs` | Full audit trail |

## Controllers

### TaskSurveyReportController

**File:** `app/Http/Controllers/TaskSurveyReportController.php`

#### `store(Request $request, Task $task): JsonResponse`
**Purpose:** Teknisi submit survey report (4-step form: lokasi, hasil, TTD, catatan)

**Flow:**
```php
public function store(Request $request, Task $task)
{
    // 1. Validate input
    // 2. Save survey evidence (foto, TTD digital)
    // 3. Update CustomerSurvey record (survey_result, completed_at)
    // 4. Complete Task
    $this->taskService->complete($task, auth()->user());
    
    // 5. ⚠️ PENTING (S8.9-T004): 
    // NO customer status transition here!
    // Task status = 'selesai', customer status UNCHANGED
    
    // 6. Set fop_review_status = 'pending'
    // Task now visible in "Perlu Aksi FOP" kanban column
    
    return response()->json([
        'success' => true,
        'message' => 'Laporan survey berhasil disimpan. Menunggu approval FOP untuk lanjut ke Verifikasi Lapangan.',
    ]);
}
```

**Key behavior:**
- Task → complete()
- Customer status → **NO CHANGE** ⚠️
- Task fop_review_status → 'pending'
- Message to user: "Waiting FOP approval"

#### `approve(Request $request, Task $task): JsonResponse`
**Purpose:** FOP review & approve survey report

**Flow:**
```php
public function approve(Request $request, Task $task)
{
    // 1. Check FOP permission: task.review
    
    // 2. Update Task:
    $task->update([
        'fop_review_status' => 'approved',
        'fop_reviewed_at' => now(),
        'fop_reviewed_by' => auth()->id(),
    ]);
    
    // 3. TRIGGER Customer Workflow Transition
    $this->workflowService->transition($task->customer, 'verifikasi_lapangan');
    
    // 4. Audit log
    AuditLog::create([
        'user_id' => auth()->id(),
        'auditable_id' => $task->id,
        'action' => 'FOP_APPROVE_SURVEY',
        'changes' => ['fop_review_status' => 'approved'],
    ]);
    
    return response()->json(['success' => true, 'message' => 'Survey approved. Customer moved to Verifikasi Lapangan.']);
}
```

**Key behavior:**
- Task fop_review_status → 'approved'
- **Customer status → transition to Verifikasi Lapangan** ✅
- AuditLog created with FOP user id
- Message: "Customer moved to Verifikasi Lapangan"

#### `reject(Request $request, Task $task): JsonResponse`
**Purpose:** FOP reject survey report (teknisi harus redo)

**Flow:**
```php
public function reject(Request $request, Task $task)
{
    // 1. Update Task
    $task->update([
        'status' => 'in_progress', // Back to running
        'fop_review_status' => 'rejected',
        'fop_review_note' => $request->input('note'),
    ]);
    
    // 2. NO customer transition (unchanged)
    
    // 3. Notify technician
    // Notification::send($task->teamMembers->users, new TaskRejectedNotification($task));
    
    return response()->json(['success' => true, 'message' => 'Survey rejected. Task moved back to In Progress.']);
}
```

### TaskInstallationReportController

**File:** `app/Http/Controllers/TaskInstallationReportController.php`

#### `store(Request $request, Task $task): JsonResponse`
**Purpose:** Teknisi submit installation report (4-step: teknis, kontrak, TTD, catatan)

**Flow:** Same as survey
```php
public function store(Request $request, Task $task)
{
    // ... save installation data ...
    
    // Task complete, customer NO auto-update
    $this->taskService->complete($task, auth()->user());
    
    // 5. ⚠️ PENTING (S8.9-T004):
    // NO customer status transition here!
    
    return response()->json([
        'success' => true,
        'message' => 'Laporan pemasangan berhasil disimpan. Menunggu approval FOP untuk lanjut ke Verifikasi Admin.',
    ]);
}
```

**Message fix (S8.9-T004):**
- Old: "Status pelanggan berpindah ke Verifikasi Admin" (misleading)
- New: "Menunggu approval FOP untuk lanjut ke Verifikasi Admin" (accurate)

#### `approve(Request $request, Task $task): JsonResponse`
**Purpose:** FOP review & approve installation report

**Flow:** Same as survey
```php
public function approve(Request $request, Task $task)
{
    // Task approved, then trigger workflow
    $task->update(['fop_review_status' => 'approved']);
    
    // TRIGGER customer workflow
    $this->workflowService->transition($task->customer, 'verifikasi_admin');
    
    return response()->json(['success' => true, 'message' => 'Installation approved. Customer moved to Verifikasi Admin.']);
}
```

## CustomerWorkflowService

**File:** `app/Services/CustomerWorkflowService.php`

### Transition Logic

```php
public function transition(Customer $customer, string $targetStatus): void
{
    // 1. Validate transition
    abort_unless(
        $this->canTransition($customer->workflow_status, $targetStatus),
        422,
        "Cannot transition from {$customer->workflow_status} to {$targetStatus}"
    );
    
    // 2. Update customer status
    $customer->update(['workflow_status' => $targetStatus]);
    
    // 3. Audit log
    AuditLog::create([
        'user_id' => auth()->id(),
        'auditable_id' => $customer->id,
        'auditable_type' => Customer::class,
        'action' => 'WORKFLOW_TRANSITION',
        'changes' => [
            'workflow_status' => [$customer->getOriginal('workflow_status'), $targetStatus],
        ],
    ]);
    
    // 4. Trigger next task creation (if needed)
    $this->createNextTask($customer, $targetStatus);
}

private function canTransition(string $from, string $to): bool
{
    $allowed = [
        'registrasi' => ['verifikasi_lapangan', 'rejected'],
        'verifikasi_lapangan' => ['verifikasi_admin', 'rejected'],
        'verifikasi_admin' => ['aktivasi', 'rejected'],
        'aktivasi' => ['aktif', 'suspend'],
        'aktif' => ['suspend', 'cancel', 'terminate'],
    ];
    
    return in_array($to, $allowed[$from] ?? []);
}
```

## AuditLog Schema

```php
// app/Models/AuditLog.php

Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->references('id')->on('users');
    $table->unsignedBigInteger('auditable_id');
    $table->string('auditable_type'); // 'Task', 'Customer', 'CustomerSurvey'
    $table->string('action'); // 'WORKFLOW_TRANSITION', 'FOP_APPROVE_SURVEY', 'REPORT_SUBMIT'
    $table->json('changes'); // Before/after state
    $table->timestamps();
});
```

**Example audit trail:**
```
2026-06-27 14:00 | User 5 (Teknisi Budi) | Task 123 | REPORT_SUBMIT | task_status: in_progress → selesai, fop_review_status: null → pending
2026-06-27 14:15 | User 2 (FOP Amin) | Task 123 | FOP_APPROVE_SURVEY | fop_review_status: pending → approved
2026-06-27 14:15 | User 2 (FOP Amin) | Customer 10 | WORKFLOW_TRANSITION | workflow_status: registrasi → verifikasi_lapangan
```

## Message Flow

### Scenario 1: Survey Report Submission

```
Teknisi: Submit form → POST /tasks/{id}/reports/survey
                       ↓
                    TaskSurveyReportController::store()
                       ├─ Save evidence
                       ├─ Task→complete()
                       └─ Task.fop_review_status = 'pending'
                       ↓
Teknisi: "Laporan disimpan. Menunggu approval FOP."
                       ↓
FOP: Review in kanban "Perlu Aksi FOP" column
     → Click approve button
                       ↓
                    TaskSurveyReportController::approve()
                       ├─ Task.fop_review_status = 'approved'
                       ├─ Customer.workflow_status = 'verifikasi_lapangan'
                       └─ AuditLog created
                       ↓
FOP: "Survey approved. Customer moved to Verifikasi Lapangan."
System: Auto-create Verifikasi Lapangan Task
                       ↓
Customer: Transitions to next stage
```

### Scenario 2: Installation Report Rejection

```
Teknisi: Submit installation report
                       ↓
                    TaskInstallationReportController::store()
                       ├─ Save evidence
                       ├─ Task→complete()
                       └─ Task.fop_review_status = 'pending'
                       ↓
FOP: Review → Click reject button
                       ↓
                    TaskInstallationReportController::reject()
                       ├─ Task.status = 'in_progress'
                       ├─ Task.fop_review_status = 'rejected'
                       └─ AuditLog created
                       ↓
Teknisi: "Installation rejected. Task returned to In Progress. See FOP notes."
Customer: Status UNCHANGED (still waiting installation)
                       ↓
Teknisi: Redo installation → Resubmit report
```

## Permissions & Access Control

| User Role | Permission | Action |
|-----------|-----------|--------|
| Technician | task.status.complete | Submit report (store) |
| FOP | task.review | Approve/reject (approve, reject) |
| Admin | any | Audit log access |

**Middleware:** `CheckPermission::class` validates permissions

## Testing

**Unit Tests:**

```php
public function test_report_submit_no_customer_transition()
{
    $customer = Customer::factory()->create(['workflow_status' => 'registrasi']);
    $task = Task::factory()->create(['customer_id' => $customer->id, 'status' => 'in_progress']);
    
    // Teknisi submit
    $this->actingAs($technician)->post("/tasks/{$task->id}/reports/survey", [...]);
    
    // Task selesai, customer tetap
    $this->assertEquals('selesai', $task->fresh()->status);
    $this->assertEquals('registrasi', $customer->fresh()->workflow_status);
}

public function test_fop_approve_triggers_customer_transition()
{
    $customer = Customer::factory()->create(['workflow_status' => 'registrasi']);
    $task = Task::factory()->create([
        'customer_id' => $customer->id,
        'status' => 'selesai',
        'fop_review_status' => 'pending'
    ]);
    
    // FOP approve
    $this->actingAs($fop)->post("/tasks/{$task->id}/approve", []);
    
    // NOW customer transitions
    $this->assertEquals('verifikasi_lapangan', $customer->fresh()->workflow_status);
}
```

## Audit & Compliance

All workflow transitions logged to `audit_logs`:
- User who made action
- Timestamp
- Before/after state
- Action type (REPORT_SUBMIT, FOP_APPROVE, WORKFLOW_TRANSITION, etc.)

Enables:
- ✅ Compliance audit trail (who approved what when)
- ✅ Dispute resolution (prove customer was waiting, not auto-transitioned)
- ✅ Performance tracking (which FOP approves fastest)

## Related Documentation

- [FOP Dashboard](fop-dashboard.md) — Where FOP sees tasks
- [Kanban Task Scheduler](kanban-task-scheduler.md) — "Perlu Aksi FOP" column
- [Overdue Indicator](overdue-indicator.md) — Prioritize overdue reviews

---

**Files:**
- `app/Http/Controllers/TaskSurveyReportController.php`
- `app/Http/Controllers/TaskInstallationReportController.php`
- `app/Services/CustomerWorkflowService.php`
- `app/Models/AuditLog.php`
- `routes/web.php` — POST /tasks/{id}/reports/survey, POST /tasks/{id}/approve

**Key principle:** Report Submit ≠ Auto-Transition. FOP Approve = Transition. ✅

**Last updated:** 2026-06-27
