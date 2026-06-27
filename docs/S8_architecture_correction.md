---
name: s8-architecture-correction
description: Architecture breakdown S8.5-S8.8 — sebenarnya apa yang di-butuhkan vs apa yang di-implementasikan
metadata: 
  node_type: memory
  type: project
  originSessionId: da93c49f-a3d0-456b-9f33-d0f32b4fb58c
---

# S8.5-S8.8 ARCHITECTURE CORRECTION

## ISSUE
Implementasi S8.7-S8.8 tidak sesuai brief. Calendar FOP unnecessary. Missing: Central List Task, FOP Reject/Pending, Checklist input scheduling.

---

## BRIEF FLOW YANG BENAR

### 1. TAMBAH PELANGGAN → WAITING_SURVEY

**Flow:**
```
CustomerController::store()
  ├─ Customer status = waiting_survey
  └─ Auto-create Task (type=survey, status=pending)
```

**Result:** 1 Task pending muncul di **List Task**

---

### 2. CENTRAL "LIST TASK" VIEW

**Path:** `/tasks` (FOP Dashboard) — bukan `/fop` (Calendar)

**Content:**
```
Pending Tasks:
├─ TASK-2025-0001 (Survey) — Customer A — Tidak dijadwalkan
├─ TASK-2025-0002 (Pemasangan) — Customer B — Tidak dijadwalkan
└─ ...

Scheduled Tasks:
├─ TASK-2025-0100 (Survey) — Team: Teknisi A,B — Jadwal: 2025-06-28 10:00
├─ TASK-2025-0101 (Pemasangan) — Team: Teknisi C — Jadwal: 2025-06-29 14:00
└─ ...

In Progress:
├─ TASK-2025-0200 (Survey) — Teknisi A sedang mengerjakan
└─ ...
```

**Fitur:**
- Filter: pending, scheduled, in_progress, selesai
- Sort: by date, by type, by status
- Click card → Detail Task + action

---

### 3. DETAIL TASK + PENJADWALAN + CHECKLIST

**Path:** `/tasks/{id}` (same as now)

**FOP Actions on Pending Task:**
```
┌─ Jadwalkan (Schedule)
│  ├─ Input: scheduled_at, team (1-3 teknisi)
│  ├─ Conflict check
│  └─ **NEW: Input Checklist Template** ← FOP specify apa checklist untuk task ini
│      └─ e.g., ["Verifikasi KTP", "Cek Sinyal", "Foto Lokasi", ...]
│
├─ Reject (Tolak)
│  └─ Input: reject reason
│      └─ Customer status tetap, Task status = dibatalkan
│
└─ Pending (Pending)
   └─ Input: pending reason
       └─ Task status = pending (tidak selesai, tidak dijadwalkan)
```

**Teknisi Actions on Scheduled/In-Progress Task:**
```
Terjadwal (Scheduled):
├─ "Mulai Survey" button → status = in_progress + customer = survey_in_progress
├─ "Mulai Pemasangan" button → status = in_progress + customer = installation_in_progress
└─ Other type → generic "Mulai" button

In Progress:
├─ "Laporan Survey" (if survey) → slide-over form, submit → task = selesai + customer = waiting_acc
├─ "Laporan Pemasangan" (if pemasangan) → slide-over form, submit → task = selesai + customer = verification_admin
├─ "Pending" button → reason modal
└─ Generic complete button (non-survey/installation tasks)
```

---

### 4. FOP QUALITY GATE

**After Teknisi Complete Task:**

Task status = `selesai` BUT **FOP verification step masih ada:**

```
FOP Dashboard → Detail Task (selesai)
├─ Baca laporan survey/pemasangan
├─ Review foto, tanda tangan, data
└─ Action:
    ├─ Approve ✓ → Customer transition lanjut (survey_in_progress → waiting_acc, atau installation → verify_admin)
    ├─ Reject ✗ → Task = dibatalkan, Customer status revert, Teknisi isi ulang
    └─ Pending ⏸ → Waiting for fix/clarification
```

**BUT CURRENT:** Teknisi laporan langsung → customer status auto-update (no FOP gate)

---

### 5. YANG DI-IMPLEMENTASIKAN (SALAH)

#### ❌ S8.7 FOP CALENDAR (`/fop`)
- User: "sudah ada" (mungkin di-ambil dari branch lain, atau memang tidak seharusnya ada)
- Issue: Unnecessary duplicate dengan List Task
- **Action:** Delete? Atau ganti jadi list view?

#### ❌ OPTION A FORM DI TASKS.SHOW
- ✅ Benar: Teknisi isi laporan dari Task
- ❌ Salah: Tidak ada FOP Reject/Pending action
- ❌ Salah: Checklist input saat penjadwalan missing
- ❌ Salah: Customer status auto-update (no FOP gate)

---

## ARCHITECTURE YANG BENAR

### VIEW HIERARCHY

```
FOP Dashboard (/tasks)
├─ List Task (pending + scheduled + in_progress + selesai)
│
└─ [Click Card] → Detail Task (/tasks/{id})
   ├─ Task Info + Team Assignment
   ├─ Checklist (FOP set saat schedule, Teknisi check saat kerja)
   │
   ├─ [If Pending] FOP Actions: Schedule + Reject + Pending
   │                            └─ Schedule → Input team + checklist template
   │
   ├─ [If Scheduled/In Progress] Teknisi Actions: Mulai + Laporan + Pending
   │
   └─ [If Selesai] FOP Review + Quality Gate: Approve / Reject / Pending
       └─ Approve → Customer transition automatic
       └─ Reject → Task revert ke pending, Teknisi isi ulang
       └─ Pending → Wait for fix

Teknisi Dashboard (/tasks/my)
└─ My Tasks (my scheduled + in_progress)
   └─ [Click] → Detail Task
      └─ Mulai + Laporan + Pending (same as above)

Customer Dashboard (/customers)
└─ [Click Customer] → Customer Detail
   ├─ Survey Tab (read-only dalam task context)
   ├─ Installation Tab (read-only dalam task context)
   └─ **NOTE:** Teknisi workflow NOW from Task, NOT from Customer page
```

---

## DATABASE / MODEL CHANGES

### Task Model
```php
$task->checklist_template // JSON array dari FOP saat schedule
$task->reject_reason // alasan FOP reject
$task->pending_reason // alasan pending
$task->fop_review_status // pending / approved / rejected
```

### TaskChecklist (existing but new context)
```php
$checklist->is_checked // filled by teknisi
$checklist->checked_by // teknisi user_id
$checklist->checked_at // timestamp
```

---

## WORKFLOW TIMELINE

### STATUS TRANSITIONS

#### Customer Workflow
```
registered
  → waiting_survey (auto saat task create)
  → survey_in_progress (saat teknisi klik "Mulai Survey")
  → waiting_acc (saat teknisi submit laporan survey + FOP approve)
  → waiting_installation (auto saat FOP proses ke tim)
  → installation_in_progress (saat teknisi klik "Mulai Pemasangan")
  → verification_admin (saat teknisi submit laporan pemasangan + FOP approve)
  → active (saat admin verifikasi final)
```

#### Task Workflow
```
pending
  → (FOP action)
     ├─ scheduled (penjadwalan + checklist input)
     ├─ rejected (FOP reject)
     └─ pending (FOP pending)
  
  → (if scheduled) terjadwal
  → (teknisi start) in_progress
  → (teknisi laporan) selesai
  → (FOP review)
     ├─ approved (final)
     ├─ rejected (revert ke pending)
     └─ pending (wait fix)
```

---

## ACTION ITEMS

### DELETE
- [ ] `/fop` kalender route (S8.7) — or clarify if needed differently

### CREATE / MODIFY
- [ ] `/tasks` List Task view (central repository)
- [ ] TaskController::schedule() → add checklist_template input
- [ ] TaskController::reject() → new action (FOP only)
- [ ] TaskController::review() → new action (FOP approve/reject after selesai)
- [ ] Task model migration → add checklist_template, reject_reason, fop_review_status
- [ ] Task policy → FOP can reject/approve own tasks
- [ ] Modify TaskSurveyReportController → don't auto-transition, create "pending review" state
- [ ] Modify TaskInstallationReportController → don't auto-transition, create "pending review" state

### KEEP (with fixes)
- [ ] tasks/show.blade.php (Detail Task) → add FOP reject/pending/approve buttons
- [ ] Option A Laporan form → working correctly, don't delete

---

## NOTES

**FOP vs Teknisi Permissions:**
- **FOP** (Koordinator Lapangan): View all, Schedule, Input Checklist, Reject, Pending, Approve
- **Teknisi** (Eksekutor): View own, Start, Complete (Laporan), Pending

**Checklist Immutability:**
- FOP sets template at schedule time
- Teknisi checks items during execution
- Cannot modify template after scheduled

**Quality Gate Purpose:**
- FOP verify laporan survey/pemasangan complete & accurate
- Prevent wrong data forward ke verification_admin step
- If reject → teknisi isi ulang
