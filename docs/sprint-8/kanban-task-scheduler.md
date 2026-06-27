# Kanban Task Scheduler (S8.2-S8.3)

Pipeline task 5-kolom dengan real-time updates untuk FOP task management.

## Overview

Kanban board menampilkan task workflow dalam 5 status kolom:

1. **Antrean** — Task baru, belum dijadwalkan
2. **Terjadwal** — Task punya scheduled_at, belum mulai
3. **Berjalan** — Task in_progress (teknisi sudah mulai)
4. **Selesai** — Task completed, menunggu FOP review
5. **Perlu Aksi FOP** — Task pending FOP approval/reject

Setiap card menampilkan:
- Task number & title
- Customer name
- SLA countdown timer (real-time via Alpine.js)
- Task type badge (survey/pemasangan/maintenance)
- Team member initials

## Routes

```
GET /fop/kanban
  Permission: task.view.all
  Return: view('fop.kanban') dengan tasks grouped by status
```

## Controller: FopKanbanController

**File:** `app/Http/Controllers/FopKanbanController.php`

### Methods

#### `index(Request $request): View`
- Fetch all tasks (POP-scoped via `applyUserScope()`)
- Group by status (5 kolom)
- Calculate SLA remaining time per task
- Count active team members
- Return: tasks, stats, pops, startDate, endDate

#### `getTasksGroupedByStatus(User $user, ?string $statusFilter): array`
- Private helper
- Query tasks with relationships (customer, pop, teamMembers.user)
- Group by `status` enum
- Calculate `sla_remaining_minutes` for countdown timer
- Return: [ANTREAN => [...], TERJADWAL => [...], IN_PROGRESS => [...], SELESAI => [...], PENDING_FOP_REVIEW => [...]]

#### `getTaskTypeColor(string $type): string`
- Return hex color for badge: survey (#3b82f6), pemasangan (#f59e0b), maintenance (#10b981)

## View: resources/views/fop/kanban.blade.php

### Layout

**Grid 12-kolom responsive:**
- Top: Page header + navigation (prev/next week)
- Top: Summary stat cards (4 cards: Total, Completed, Pending, Cancelled)
- Main: 5-kolom kanban pipeline (Sen-Min workflow)
- Right sidebar: Active teams + POP filter

### Components

#### Task Cards
```blade
<div class="bg-background border-l-4 rounded-md p-3">
  <div class="text-xs font-mono text-text-muted">{{ $task->task_number }}</div>
  <p class="text-sm font-semibold text-text-main">{{ $task->title }}</p>
  <p class="text-xs text-text-muted">{{ $task->customer->full_name }}</p>
  
  <!-- SLA Countdown Timer (Alpine.js) -->
  <div class="mt-2 text-xs font-mono" x-show="remainingMinutes({{ $task->id }}) > 0">
    <span class="text-warning">{{ remainingMinutes }} min</span>
  </div>
  
  <!-- Task Type Badge -->
  <span class="inline-block mt-2 px-2 py-1 text-xs rounded" 
        :style="'background: ' + getTaskTypeColor('{{ $task->task_type->value }}') + '20'">
    {{ $task->task_type->value }}
  </span>
</div>
```

#### Stat Cards
- Total tasks in period
- Completed (green badge)
- Pending (yellow badge)
- Cancelled (red badge)

#### SLA Countdown
- Real-time via Alpine.js `setInterval()`
- Show minutes remaining before SLA threshold
- Color: warning (yellow) if < 4 hours, error (red) if < 1 hour, success (green) if OK

## Data Flow

```
1. GET /fop/kanban
   ↓
2. FopKanbanController::index()
   ├─ auth()->user() → get current FOP
   ├─ Query tasks filtered by user POP scope
   ├─ Group by status
   ├─ Calculate SLA remaining for each task
   └─ Return view with: tasks, stats, pops
   ↓
3. Blade render kanban.blade.php
   ├─ Render 5 columns
   ├─ Loop tasks per status
   ├─ Display task cards with Alpine.js x-data
   └─ Initialize countdown timer
   ↓
4. Alpine.js (Client-side)
   ├─ setInterval() to update countdown every 10s
   ├─ Change color based on remaining time
   ├─ Optional: Drag-drop task between columns
   └─ Optional: Real-time updates via Reverb
```

## Real-time Updates (Reverb Broadcasting)

### TaskStatusChanged Event

```php
// app/Events/TaskStatusChanged.php
class TaskStatusChanged implements ShouldBroadcast
{
    public function __construct(
        public Task $task,
        public string $oldStatus,
        public string $newStatus
    ) {}
    
    public function broadcastOn(): Channel
    {
        return new Channel("pop.{$this->task->pop_id}");
    }
}
```

### Echo.js Listener (kanban.blade.php)

```javascript
// Listen to Reverb channel
Echo.channel(`pop.${currentPopId}`)
    .listen('TaskStatusChanged', (e) => {
        console.log(`Task ${e.task.id} moved: ${e.oldStatus} → ${e.newStatus}`);
        // Re-fetch kanban data or update card position
        location.reload(); // Simple refresh for now
    });
```

## Alpine.js Interactivity

### Data Store

```javascript
function kanbanHandler() {
    return {
        tasks: @json($tasks),
        selectedTeamId: null,
        currentPop: @json(auth()->user()->pop_id),
        
        // Methods
        getTaskTypeColor(type) { ... },
        remainingMinutes(taskId) { ... },
        selectTeam(teamId) { ... },
        filterByTeam() { ... },
    };
}
```

### Timer Update

```javascript
setInterval(() => {
    // Recalculate all task SLA remaining times
    this.tasks = this.tasks.map(task => {
        task.sla_remaining_minutes = Math.floor(
            (new Date(task.sla_deadline) - new Date()) / 60000
        );
        return task;
    });
}, 10000); // Update every 10 seconds
```

## Drag-Drop (Optional Enhancement)

Use Alpine Dragable library or native HTML5 drag-drop API:

```blade
<div class="kanban-column" 
     @drop="handleDrop($event)" 
     @dragover="allowDrop($event)">
    @foreach($tasks as $task)
        <div draggable="true" 
             @dragstart="draggedTaskId = {{ $task->id }}">
            <!-- task card -->
        </div>
    @endforeach
</div>
```

## Performance Considerations

1. **Real-time updates:** Reverb broadcasts only to user's POP scope (not all POP users)
2. **SLA calculation:** Done in Controller once, then refreshed every 10s client-side
3. **Task count:** Lazy load if > 100 tasks per column (pagination per column)
4. **Caching:** Cache POP teams list (updated when team added/removed)

## Testing

**Manual Testing:**
1. Navigate to `/fop/kanban`
2. Verify 5 columns rendered with tasks grouped correctly
3. Check SLA countdown timer updates every 10s
4. Test next/prev week navigation
5. Test team filter sidebar
6. Verify stat cards totals match task counts

**Unit Tests:**
- `FopKanbanController::getTasksGroupedByStatus()` returns 5 keys
- Task count per status accurate
- SLA calculation: created_at + 1 day (survey), survey completed_at + 3 days (installation)

---

**Files:**
- `app/Http/Controllers/FopKanbanController.php`
- `resources/views/fop/kanban.blade.php`
- `routes/web.php` — GET /fop/kanban route
- `app/Events/TaskStatusChanged.php` (optional Reverb)

**Last updated:** 2026-06-27
