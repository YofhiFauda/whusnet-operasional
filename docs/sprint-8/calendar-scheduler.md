# Calendar Scheduler (S8.7)

Weekly calendar grid view untuk FOP task scheduling dan team management.

## Overview

**Purpose:** Alternative view to Kanban untuk lihat tasks per hari dalam format kalender.

**Features:**
- 7-hari grid (Senin-Minggu)
- Task cards grouped by day
- Sidebar dengan tim aktif + task count
- Detail panel untuk task info + checklist progress
- Next/prev week navigation
- Summary stat cards (total, completed, pending, cancelled)

**Layout:**
```
┌─────────────────────────────────────────┐
│  Page Header (Title + Navigation)       │
├─────────────────────────────────────────┤
│  Summary Stats (4 cards)                │
├──────────────┬────────────────────┬────┐
│  Tim Aktif   │  Calendar Grid     │Det │
│  (Sidebar)   │  (7 columns)       │ail │
│              │                    │Pan │
│              │  Sen Sel Rab Kam   │ el │
│              │  [T] [T] [T] [T]   │    │
│              │  ... ... ... ...    │    │
└──────────────┴────────────────────┴────┘
│  Legend (Task Type Colors)              │
└─────────────────────────────────────────┘
```

## Routes

```
GET /fop/calendar
  Permission: task.view.all
  Params: start_date (optional, default: today)
  Return: view('fop.calendar') dengan calendar data
```

## Controller: FopCalendarController

**File:** `app/Http/Controllers/FopCalendarController.php`

### Methods

#### `index(Request $request): View`
Main calendar view logic:
- Auth user (FOP)
- Parse start_date param (default: now().startOfWeek(MONDAY))
- Calculate week end date
- Get tasks scheduled in week, grouped by day
- Calculate stat cards (total, completed, pending, cancelled)
- Get active teams with task counts
- Return view with calendar data

#### `getTeamsWithTaskCount(User $user, array $allowedPopIds, Carbon $start, Carbon $end): Collection`
Private helper to get active technicians:
- Query all tasks in date range
- Extract unique users from task team members
- Count tasks per team member
- Check if member has in_progress task (active status)
- Return: collection with [id, name, initials, taskCount, status, activeTask]

#### `initials(string $name): string`
Private helper to extract initials:
```php
// "Budi Santoso" → "BS"
// "John Doe Smith" → "JDS"
```

## View: resources/views/fop/calendar.blade.php

### Layout (12-column Grid)

**Top section:**
```blade
<div class="flex items-center justify-between">
  <div>
    <h1>FOP — Task Scheduler</h1>
    <p>{{ $startDate->translatedFormat('d M Y') }} — {{ $endDate->translatedFormat('d M Y') }}</p>
  </div>
  <div>
    <!-- Previous week button -->
    <a href="{{ route('fop.calendar', ['start_date' => $startDate->subWeek()->toDateString()]) }}">
      <svg>← Minggu lalu</svg>
    </a>
    
    <!-- Week range display -->
    <span>{{ $startDate->format('d M') }} — {{ $endDate->format('d M') }}</span>
    
    <!-- Next week button -->
    <a href="{{ route('fop.calendar', ['start_date' => $startDate->addWeek()->toDateString()]) }}">
      <svg>Minggu depan →</svg>
    </a>
  </div>
</div>
```

**Summary stat cards:**
```blade
<div class="grid grid-cols-2 md:grid-cols-4 gap-3">
  <!-- Total, Completed, Pending, Cancelled cards -->
  <!-- Same as FOP Dashboard style -->
</div>
```

**Main grid (col-span-12 → 12 columns total):**
- **Sidebar (col-span-2 on md):**
  - Header: "TIM AKTIF"
  - List teams with button click handler
  - Show team initials, name, status dot, task count
  - Selected team: ring-2 ring-primary

- **Calendar (col-span-7 on md):**
  - Header row: 7 day names (Sen, Sel, Rab, Kam, Jum, Sab, Min)
  - Body: 7 columns grid with tasks
  - Task cards: clickable to select detail panel
  - Card shows: task number, title, customer name, task type badge
  - Empty day: shows "—"

- **Detail panel (col-span-3 on md):**
  - Hidden by default ("Pilih task untuk melihat detail")
  - Visible when task selected
  - Shows: checklist progress bar, team names, schedule time, POP, customer
  - Close button

**Legend:**
```blade
<div class="flex items-center gap-6 text-xs">
  <!-- Task type colors: survey (blue), pemasangan (amber), maintenance (green) -->
</div>
```

### Task Card Structure

```blade
<button @click="openTaskDetail({{ $task->id }})"
        class="w-full text-left p-2 rounded-md border border-border bg-background hover:shadow-sm">
  <div class="text-[10px] font-mono font-semibold text-text-muted">
    {{ $task->task_number }}
  </div>
  <p class="text-[11px] font-semibold text-text-main truncate mt-0.5">
    {{ $task->title }}
  </p>
  <p class="text-[10px] text-text-muted truncate">
    {{ $task->customer?->full_name ?? '—' }}
  </p>
  <div class="flex items-center gap-1 mt-1 pt-1 border-t border-border/50">
    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase"
          :style="'background:' + getTaskTypeColor('{{ $task->task_type->value }}') + '20; color:' + getTaskTypeColor('{{ $task->task_type->value }}')">
      {{ $task->task_type->value }}
    </span>
  </div>
</button>
```

## Alpine.js Interactivity

### Data Store

```javascript
function fopCalendarHandler() {
    return {
        selectedTeamId: null,
        selectedTaskId: null,
        selectedTask: null,

        selectTeam(teamId) {
            this.selectedTeamId = teamId;
        },

        openTaskDetail(taskId) {
            this.selectedTaskId = taskId;
            // Optional: fetch via AJAX for dynamic data
            // fetch(`/api/tasks/${taskId}`).then(r => r.json()).then(data => { this.selectedTask = data; });
        },

        getTaskTypeColor(type) {
            const colors = {
                'survey': 'var(--color-info)',
                'pemasangan': 'var(--color-warning)',
                'maintenance': 'var(--color-success)',
            };
            return colors[type] || 'var(--color-primary)';
        }
    };
}
```

### Interactive Elements

- **Team selection:** `selectTeam(id)` highlights selected team
- **Task detail:** `openTaskDetail(id)` shows task in detail panel
- **Color helper:** `getTaskTypeColor(type)` returns CSS color var

## Data Flow

```
1. GET /fop/calendar?start_date=2026-06-23
   ↓
2. FopCalendarController::index()
   ├─ Parse start_date → Monday of week
   ├─ Calculate endDate (Sunday)
   ├─ For each day (Mon-Sun):
   │  └─ Query tasks scheduled on that day
   ├─ Get teams with task counts
   ├─ Calculate stats
   └─ Return view with: days[], activeTeams[], stats, startDate, endDate
   ↓
3. Blade render calendar.blade.php
   ├─ Display header with week navigation
   ├─ Display 4 stat cards
   ├─ Initialize Alpine.js handler
   ├─ Loop 7 days, render task cards
   ├─ Loop teams, render sidebar
   └─ Detail panel ready for interaction
   ↓
4. Alpine.js (Client)
   ├─ Click team → selectTeam() → highlight
   ├─ Click task → openTaskDetail() → show in panel
   └─ Color task badge via getTaskTypeColor()
```

## Responsive Design

**Mobile (< md breakpoint):**
```blade
<div class="grid grid-cols-12 gap-4">
  <div class="col-span-12 md:col-span-2">Sidebar</div>
  <div class="col-span-12 md:col-span-7">Calendar</div>
  <div class="col-span-12 md:col-span-3">Detail</div>
</div>
```

- Stacked on mobile (all 12 cols)
- Side-by-side on desktop (2+7+3 cols)

## Performance

1. **Task loading:** Eager load `customer`, `pop`, `teamMembers.user` with with()
2. **Query:** Single query per day + team aggregation query
3. **Caching:** Optional cache day/week tasks (30 minute TTL)
4. **Pagination:** If > 50 tasks per day, paginate within day

## Testing

**Manual:**
1. Navigate to `/fop/calendar`
2. Verify week range displayed correctly
3. Check prev/next week navigation
4. Verify 7 columns rendered (Mon-Sun)
5. Click task → detail panel appears
6. Click team → team highlighted
7. Verify stat cards totals

**Unit Tests:**
```php
public function test_calendar_index_returns_7_days()
{
    $response = $this->get(route('fop.calendar'));
    $this->assertCount(7, $response['days']);
}

public function test_calendar_groups_tasks_by_day()
{
    // Create tasks on different days
    $response = $this->get(route('fop.calendar'));
    $this->assertCount(2, $response['days']['2026-06-24']['tasks']);
}
```

## Related Features

- [FOP Dashboard](fop-dashboard.md) — Link to calendar
- [Kanban Task Scheduler](kanban-task-scheduler.md) — Alternative view
- [Overdue Indicator](overdue-indicator.md) — SLA display

---

**Files:**
- `app/Http/Controllers/FopCalendarController.php`
- `resources/views/fop/calendar.blade.php`
- `routes/web.php` — GET /fop/calendar route

**Last updated:** 2026-06-27
