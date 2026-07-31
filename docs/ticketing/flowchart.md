# Flowchart — Modul Ticketing

## 1. State Machine Tiket

```
                          POST /tickets
                               │
                               ▼
                   ┌──────────────────────┐
                   │  handler = HELPDESK  │
                   │  status  = OPEN      │  ← tab "Ticket" di List Task Ticketing
                   └──────────────────────┘
                     │        │         │
        ┌────────────┘        │         └──────────────┐
        │ close()             │ escalateToNoc()        │ escalateToFop()
        ▼                     ▼                        │
  ┌───────────┐   ┌──────────────────────────┐         │
  │  CLOSED   │   │ handler = NOC            │         │
  └───────────┘   │ status  = OPEN           │         │
        ▲         │ ── DIPROSES NOC ──       │         │
        │         │ (seketika, tanpa Oncheck)│         │
        │         │ Helpdesk & NOC bisa act  │         │
        │         └──────────────────────────┘         │
        │            │       │        │                │
        └────────────┘       │        └────────────────┤ escalateToFop()
             close()         │ returnToHelpdesk()      │
                             └──────► balik ke HELPDESK│
                                                        ▼
                                        ┌──────────────────────────────┐
                                        │ handler = FOP  (TERMINAL)    │
                                        │ FopTask DRAFT kebentuk       │
                                        │ status tiket ikut FopTask    │
                                        │ Semua aksi Ticketing DITOLAK │
                                        └──────────────────────────────┘

  cancel() bisa dari state HELPDESK / PENDING NOC / ONCHECK NOC → CANCELLED
  (alasan WAJIB). Setelah handler=FOP, pembatalan pindah ke /fop-tasks.
```

## 2. Alur Submit Tiket

```
POST /tickets (TicketController::store())
│
├─ Validasi: type ∈ {MTN, C-REQ}, customer_id, detail_keluhan, priority,
│            issue_category_id (nullable), attachments (maks 5 × 5MB)
│
├─ origin=fop_tasks DAN punya permission fop_tasks.create?
│     YES → $fopOrigin = true
│     NO  → $fopOrigin = false (field origin diabaikan diam-diam)
│
├─ technicians[] terisi DAN punya permission fop_tasks.create?
│     YES → $assignment = ['technicians' => [...], 'task_date' => ...]
│     NO  → $assignment = [] (diabaikan diam-diam, walau ada di payload)
│
▼
TicketService::create($data, $actor, $attachments, $assignment, $fopOrigin)
│
├─ 1. Resolve Customer (applyUserScope + with pop_id/status/distribution_id)
│      └─ pop_id null? → ValidationException "belum punya POP/Cabang"
│
├─ 2. Simpan Ticket
│      ├─ snapshotCustomer(): 8 kolom customer_* dibekukan
│      ├─ handler = HELPDESK
│      └─ status  = OPEN
│
├─ 3. $fopOrigin?
│      │
│      YES → syncToFopTask() → FopTask DRAFT
│      │     ├─ ticket.fop_task_id = FopTask.id
│      │     ├─ ticket.handler = FOP
│      │     └─ $assignment['technicians'] terisi?
│      │           YES → assignTechnicians():
│      │                 ├─ sync teknisi, FopTask.status = TERJADWAL
│      │                 ├─ TaskService::create() → Task eksekusi
│      │                 └─ FopTaskTeamService::rebuildTeamsForDate()
│      │           NO  → FopTask tetap DRAFT
│      │
│      NO  → TIDAK ADA FopTask. Tiket berhenti di tangan Helpdesk.
│            (beda dari perilaku lama yang selalu auto-sync)
│
├─ 4. Simpan attachments (disk 'local', privat)
│
├─ 5. TicketHistory::create(action=DIBUAT, to_status=handler saat ini)
│
└─ 6. Return → broadcast TicketQueueUpdated (SETELAH commit, toOthers())
       │
       ▼
   Controller:
   - wantsJson (worksheet)  → 201 + worksheetCardPayload
   - origin=fop_tasks       → redirect fop-tasks.index
   - selain itu             → redirect tickets.show
```

## 3. Guard Aksi Tiket (berlaku untuk semua aksi)

```
POST /tickets/{id}/{aksi}
│
├─ Middleware permission (tickets.update, atau tickets.cancel buat Batalkan)
│      NO → 403
│
├─ authorizeTicketScope() — tiket ada dalam POP scope user?
│      NO → 403
│
▼
TicketService::<aksi>()  — DB::transaction
│
├─ lockForUpdate()   ← WAJIB duluan; dua request bareng antre di sini,
│                       bukan dua-duanya lolos guard sebelum commit (TOCTOU)
│
├─ assertActorOwnsTicket()
│      │
│      ├─ full-access (owner/admin)? → lolos
│      │
│      └─ role aktor ∈ Ticket::holderRoles()?
│            handler=HELPDESK            → ['helpdesk']
│            handler=NOC, belum di-check → ['helpdesk','noc']   ← window pending
│            handler=NOC, sudah di-check → ['noc']
│            handler=FOP                 → []  (gak ada yang lolos)
│                  NO → ValidationException "bukan di tangan Anda"
│
├─ assertTicketStillOpen()
│      handler=FOP        → tolak "udah di FOP, lihat Task FOP"
│      status=CLOSED      → tolak "udah selesai"
│      status=CANCELLED   → tolak "udah dibatalkan"
│
├─ [guard khusus per aksi — lihat § 4]
│
├─ Update kolom + TicketHistory::create(...)
│
└─ (setelah commit) broadcast TicketQueueUpdated
```

## 4. Guard Khusus per Aksi

```
close()
└─ assertNocCheckedBeforeClose()
     aktor role 'noc' (bukan full-access) DAN handler=NOC DAN
     (guard "NOC wajib Oncheck dulu" DIHAPUS — ADHOC-06)
        NO  → lanjut
   [Helpdesk TIDAK kena guard ini — dia boleh close kapan pun
    selama masih pegang tiket — tiket di NOC dipegang helpdesk + noc]

escalateToNoc()
└─ handler harus HELPDESK
     (NOC gak bisa kirim ke NOC lagi)

[onCheckNoc() DIHAPUS — ADHOC-06, 2026-07-29]

escalateToFop()
└─ (tanpa guard handler tambahan — boleh dari Helpdesk maupun NOC)

returnToHelpdesk()
└─ handler harus NOC

cancel()
└─ reason WAJIB (validasi di controller: required|string|max:1000)
```

## 5. Resolusi Bucket (Dua Rezim)

```
Ticket::scopeInBucket($bucket) / Ticket::bucket()
│
├─ handler = FOP ?
│    │
│    YES ├─ fopTask ada?  → cocokkan fopTask.status ke bucket->statuses()
│        └─ fopTask NULL? → bucket DIBATALKAN (orphan / "Terputus")
│
└─ handler = HELPDESK / NOC (rezim internal)
     ├─ status = CLOSED     → SELESAI
     ├─ status = CANCELLED  → DIBATALKAN
     ├─ handler = HELPDESK  → MASUK
     └─ handler = NOC       → DIPROSES

┌─────────────┬──────────────────────────────┬────────────────────────────┐
│   Bucket    │  handler=FOP (FopTask.status)│  Internal (helpdesk/noc)   │
├─────────────┼──────────────────────────────┼────────────────────────────┤
│ Masuk       │ draft                        │ handler=HELPDESK & OPEN    │
│ Diproses    │ terjadwal, in_progress,      │ handler=NOC & OPEN         │
│             │ pending                      │                            │
│ Selesai     │ selesai                      │ status=CLOSED              │
│ Dibatalkan  │ dibatalkan + orphan          │ status=CANCELLED           │
└─────────────┴──────────────────────────────┴────────────────────────────┘
```

## 6. Peta Halaman & Filter

```
/tickets/new  — New Ticket (Worksheet Helpdesk & NOC)
│
├─ [kiri]  Form submit tiket
└─ [kanan] Panel "List Task Ticketing" — filter per HANDLER, bukan bucket
             ├─ Tab "Ticket"      → handler = helpdesk
             ├─ Tab "Assign NOC"  → handler = noc  (Diproses NOC)
             └─ Tab "Assign FOP"  → handler = fop  (pantau status FopTask)
           Sumber: scopeActiveForWorksheet() — exclude Selesai & Dibatalkan
           di AKAR query, bukan disaring client-side. Cap 30 terbaru.

/noc/worksheet — SATU route, SATU permission (noc_worksheet.view), dua tab via ?tab=
│
├─ ?tab=masuk (default)      → handler=noc & status=open
│                              (yang diassign Helpdesk; BISA diaksi)
└─ ?tab=assign_fop           → handler=fop
                               AND EXISTS ticket_histories(action=dieskalasi,
                                                           to_status=noc)
                               ("pernah lewat meja NOC"; READ-ONLY)

   Tab asing (?tab=apapun-selain-itu) → jatuh ke `masuk`, bukan 500.
   Filter: q, pop_id, issue_category_id, type, priority, created_by,
           date_from, date_to — dipakai SAMA di tabel & counter kedua tab.
   Aksi: drawer baris terpilih → endpoint TicketController (close/escalate/
         return-to-helpdesk/cancel). Bukan Oncheck — window Pending NOC tetap
         tidak ada (ADHOC-06).

/noc/worksheet/masuk, /noc/worksheet/diproses → redirect ke /noc/worksheet

/tickets/selesai         → scopeInBucket(SELESAI)
/tickets/dibatalkan      → scopeInBucket(DIBATALKAN)
   (dua-duanya lewat TicketArchiveController, controller & view sendiri-sendiri)

/noc/dashboard  — stat counter, list aktif+aging, feed aktivitas,
                  statistik per Issue, statistik per Daerah
```

## 7. Tombol yang Muncul per State

`Ticket::actionFlagsFor($user)` — **satu-satunya** sumber gerbang tampilan. Otorisasi asli tetap di `TicketService`.

```
$isHolder = full-access ATAU role ∈ holderRoles()
$canAct   = handler ≠ FOP  DAN  status = OPEN
            DAN punya tickets.update  DAN $isHolder

can_close            = $canAct  DAN BUKAN (pending NOC & aktor role noc)
can_escalate_noc     = $canAct  DAN handler = HELPDESK
can_escalate_fop     = $canAct
can_return_to_helpdesk = $canAct DAN handler = NOC
can_cancel           = $canAct  DAN punya tickets.cancel
(can_oncheck_noc DIHAPUS — ADHOC-06)
                       DAN (full-access ATAU role noc)
```

Hasilnya per state:

| State | Helpdesk lihat | NOC lihat |
|---|---|---|
| handler=HELPDESK | Selesai, Ke NOC, Ke FOP, Batalkan | — (bukan pemegang) |
| Diproses NOC | Selesai, Ke FOP, Kembalikan, Batalkan | Selesai, Ke FOP, Kembalikan, Batalkan |
| handler=FOP | — | — |

\* `can_return_to_helpdesk` hanya syarat `handler=NOC`, jadi secara teknis muncul juga buat Helpdesk di window pending; tanpa efek berarti karena tiket memang balik ke dia sendiri.

## 8. RBAC Decision Tree — Pembatalan

```
                    Tiket mau dibatalkan
                            │
              ┌─────────────┴──────────────┐
        handler = helpdesk/noc        handler = fop
              │                             │
              ▼                             ▼
   POST /tickets/{id}/cancel      PUT /fop-tasks/{id} status=dibatalkan
              │                             │
   ├─ permission tickets.cancel?  ├─ category ∈ {SURVEY, PSB}?
   │     NO → 403                 │     YES → 422 (semua role, termasuk owner)
   │                              │
   ├─ authorizeTicketScope()      ├─ permission fop_tasks.cancel?
   │     NO → 403                 │     NO → 403
   │                              │
   ├─ reason kosong?              ├─ FopTask.status = DIBATALKAN
   │     YES → 422                │    + FopTaskStatusHistory (ditulis DI SINI,
   │                              │      di luar guard TaskObserver)
   ├─ assertActorOwnsTicket()     │
   ├─ assertTicketStillOpen()     ├─ punya task_id & Task masih aktif?
   │                              │    YES → TaskPolicy::cancelViaFopTask()
   ▼                              │          ├─ Task.task_type ∈ {SURVEY,PSB}? → 403
   status = CANCELLED             │          │   (dicek terhadap TASK, bukan
   + TicketHistory(dibatalkan)    │          │    FopTask.category — cegah tiket MTN
   [1 riwayat]                    │          │    jadi jalan pintas cancel SURVEY)
                                  │          └─ permission fop_tasks.cancel?
                                  │              (BUKAN task.cancel!) NO → 403
                                  │
                                  └─ FopTaskObserver::updated() terpicu
                                       └─ TicketHistory(dibatalkan, reason)
                                     [2 riwayat: FOP + Ticket]
```

## 9. Jalur Cancel dari `/tasks` (Cascade Naik)

```
Task dibatalkan langsung dari /tasks (TaskService::cancel())
│
├─ TaskObserver::updated() terpicu
│      ├─ FopTask terkait SUDAH 'dibatalkan'? → early return
│      │   (guard proteksi override manual — TIDAK berlaku di sini karena
│      │    FopTask BELUM dibatalkan saat Task yang duluan dibatalkan)
│      └─ Sync FopTask.status = dibatalkan + FopTaskStatusHistory
│
└─ FopTaskObserver::updated() terpicu
       └─ Ticket terkait → TicketHistory(action=DIBATALKAN)
```

## 10. Auto-Refresh Realtime

```
TicketService::<aksi>()  — DB::transaction { ... } COMMIT
│
└─ broadcast(new TicketQueueUpdated($popId))->toOthers()
   [SETELAH commit, bukan di dalam closure — gak boleh nembak kalau rollback]
        │
        ▼
   Channel private tickets.{popId}
   [otorisasi: EffectiveAccessService::hasAllPopAccess()/getAllowedPopIds()
    — jalur POP-scope yang BENAR, bukan $user->pops() legacy]
        │
        ├─ Panel List Task Ticketing (/tickets/new)
        │    └─ refreshWorksheet() → GET /api/tickets/worksheet-tasks
        │       → replace array `tasks` (+ worksheetTotalCount)
        │
        └─ Dashboard NOC (/noc/dashboard)
             └─ refetch window.location.href → DOMParser
                → innerHTML-swap per container
                  (stat-cards / active-tickets / activity-feed /
                   issue-stats / region-stats)

Kalau Reverb gak jalan (BROADCAST_CONNECTION ≠ reverb): sistem tetap normal,
auto-refresh diam-diam mati, fallback ke tombol "Refresh" manual.
```

---

**Last updated:** 2026-07-28
