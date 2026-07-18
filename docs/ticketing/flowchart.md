# Flowchart — Modul Ticketing

## 1. Alur Submit Tiket → Auto-Sync FopTask

```
POST /tickets (TicketController::store())
│
├─ Validasi: type ∈ {MTN, C-REQ}, customer_id, detail_keluhan, priority
│
├─ Cek permission fop_tasks.create + technicians[] terisi?
│     │
│     YES → $assignment = ['technicians' => [...], 'task_date' => ...]
│     NO  → $assignment = [] (diabaikan diam-diam, walau field-nya ada di payload)
│
▼
TicketService::create($data, $actor, $attachments, $assignment)
│
├─ 1. Resolve Customer (applyUserScope + with pop_id/status/distribution_id)
│      └─ pop_id null? → ValidationException "belum punya POP/Cabang"
│
├─ 2. Simpan Ticket (snapshotCustomer(): 8 kolom customer_* dibekukan)
│
├─ 3. syncToFopTask($ticket, $customer, $actor, $assignment['task_date'])
│      └─ FopTask baru, status = DRAFT, category = ticket->type,
│         issue = detail_keluhan (dipotong 255), notes = composeFopNotes()
│
├─ 4. $assignment['technicians'] terisi?
│      │
│      YES → assignTechnicians():
│      │      ├─ sync teknisi ke FopTask
│      │      ├─ FopTask.status = TERJADWAL
│      │      ├─ TaskService::create() → Task eksekusi
│      │      └─ FopTaskTeamService::rebuildTeamsForDate() → conflicts[]
│      │
│      NO  → FopTask tetap DRAFT, task_id null
│
├─ 5. Simpan attachments (disk 'local', privat)
│
├─ 6. TicketHistory::create(action=DIBUAT, to_status=FopTask.status saat ini)
│
└─ 7. Return ['ticket' => Ticket, 'conflicts' => array]
       │
       ▼
   Controller redirect:
   - $assignment kosong & origin≠fop_tasks → tickets.show
   - origin=fop_tasks (permission fop_tasks.create) → fop-tasks.index
```

## 2. Resolusi Bucket Ticketing

```
Ticket::scopeInBucket($bucket)
│
├─ whereHas('fopTask', status IN bucket->statusValues())
│
└─ bucket == DIBATALKAN? → OR whereNull('fop_task_id')  [tiket "Terputus"]

┌─────────────┬───────────────────────────────────┐
│   Bucket    │        Status FopTask              │
├─────────────┼───────────────────────────────────┤
│ Masuk       │ draft                               │
│ Diproses    │ terjadwal, in_progress, pending      │
│ Selesai     │ selesai                              │
│ Dibatalkan  │ dibatalkan  + orphan (fop_task_id ∅)│
└─────────────┴───────────────────────────────────┘
```

## 3. Transisi Draft → Terjadwal (Assign Teknisi Belakangan)

```
FOP buka modal Edit di /fop-tasks untuk FopTask Draft
│
PUT /fop-tasks/{id}  (FopTaskController::update())
│
├─ $originalStatus ditangkap SEBELUM field apa pun diubah
│
├─ ... update field lain (category/task_date/priority/dll) ...
│
├─ Blok technicians (jika $request->has('technicians')):
│    ├─ sync teknisi
│    ├─ $originalStatus === 'draft' && FopTask.status masih 'draft'
│    │  && teknisi baru TIDAK kosong?
│    │      │
│    │      YES → FopTask.status = TERJADWAL
│    │             + FopTaskStatusHistory::create(draft → terjadwal)
│    │      NO  → status gak berubah (mis. sudah terjadwal, atau
│    │             teknisi dikosongin lagi)
│    │
│    └─ (!empty(teknisi) || udah ada task_id) → buat/update Task eksekusi
│
└─ Ticket::scopeInBucket() otomatis baca status baru di request GET berikutnya
   → tiket pindah dari "Ticket Masuk" ke "Ticket di Proses"
```

## 4. RBAC Decision Tree — Pembatalan

```
User klik Cancel di /fop-tasks
│
PUT /fop-tasks/{id}  status=dibatalkan
│
├─ category ∈ {SURVEY, PSB}?
│      YES → 422 "harus lewat halaman Pelanggan" (SEMUA role, termasuk owner)
│
├─ hasPermission('fop_tasks.cancel')?
│      NO  → 403
│
├─ FopTask.status berubah ke DIBATALKAN
│      └─ FopTaskStatusHistory::create(from=$originalStatus, to=dibatalkan)
│         [ditulis DI SINI karena TaskObserver punya guard early-return
│          begitu FopTask sudah 'dibatalkan' — lihat business-logic.md §9]
│
├─ FopTask punya task_id & Task masih aktif?
│      │
│      YES → TaskPolicy::cancelViaFopTask($linkedTask)
│             │
│             ├─ Task.task_type ∈ {SURVEY, PSB}? → 403 (invarian, cek
│             │    terhadap TASK, bukan FopTask.category — cegah tiket
│             │    MTN jadi jalan pintas cancel Task SURVEY)
│             │
│             ├─ hasPermission('fop_tasks.cancel')?  (BUKAN task.cancel!)
│             │    NO → 403
│             │
│             └─ YES → TaskService::cancel($linkedTask, ...)
│
└─ FopTaskObserver::updated() terpicu (FopTask.status → dibatalkan)
       │
       └─ Ticket terkait ada? → TicketHistory::create(action=DIBATALKAN,
          from_status, to_status, reason=cancel_reason, actor)
```

## 5. Jalur Cancel dari `/tasks` (Cascade Naik)

```
Task dibatalkan langsung dari /tasks (TaskService::cancel())
│
├─ TaskObserver::updated() terpicu
│      │
│      ├─ FopTask terkait status SUDAH 'dibatalkan'? → early return
│      │   (guard proteksi override manual — TIDAK berlaku di sini karena
│      │    FopTask BELUM dibatalkan saat Task ini yang duluan dibatalkan)
│      │
│      └─ Sync FopTask.status = dibatalkan (copy dari Task.status)
│         + FopTaskStatusHistory::create() [jalur normal TaskObserver,
│           TIDAK kena guard karena FopTask baru berubah SEKARANG]
│
└─ FopTaskObserver::updated() terpicu (FopTask.status → dibatalkan)
       └─ Ticket terkait → TicketHistory::create(action=DIBATALKAN)
```

## 6. Bug CID Mentah — Root Cause Visual

```
GET /tickets/masuk
│
TicketController::index()
│
├─ Ticket::with(['customer:id,full_name,cid,customer_code', ...])
│                            ↑
│              pop_id TIDAK ikut ke-select!
│
▼
Blade: $ticket->customer->display_id
│
Customer::getDisplayIdAttribute()
│
├─ $pop = $this->pop;   ← relasi butuh pop_id, tapi kolomnya gak ke-load
├─ if (!$pop) return $this->customer_code;   ← SELALU masuk sini
│
▼
Hasil: "RQ000007" (bare) — padahal customers.cid = "C1X4CRQ000007"

FIX: select tambah pop_id, status, distribution_id
     + eager-load customer.pop:id,name,cid_prefix
     → resolveDisplayId() jalan normal, balikin CID lengkap
```
