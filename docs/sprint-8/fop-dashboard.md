# FOP Dashboard (S8.1)

Dashboard overview untuk FOP (Field Operations Planner) dengan stat cards dan quick actions.

## Overview

FOP Dashboard adalah landing page untuk FOP user dengan ringkasan operasional:
- **Stat cards:** Total tasks, completed, pending, overdue (SLA)
- **Quick actions:** Kanban, Calendar, Reports
- **Active teams:** List teknisi dengan active status
- **Recent activity:** Latest task updates

## Routes

```
GET /fop
  Permission: task.view.all
  Return: view('fop.dashboard')
```

## Controller: FopDashboardController

**File:** `app/Http/Controllers/FopDashboardController.php`

### Methods

#### `index(Request $request): View`
Main dashboard view logic:
- Auth user (FOP)
- Get POP-scoped tasks
- Calculate 4 stat cards: total, completed, pending, overdue
- Get active teams with task assignments
- Get recent task updates (last 24 hours)
- Return: view('fop.dashboard') with all data

#### `calculateStats(User $user, Carbon $startDate, Carbon $endDate): array`
Calculate stat cards for date range (default: current week):
```php
return [
    'total'     => Task::applyUserScope($user)->whereBetween('scheduled_at', [$start, $end])->count(),
    'completed' => Task::applyUserScope($user)->where('status', 'selesai')->whereBetween(...)->count(),
    'pending'   => Task::applyUserScope($user)->where('status', 'pending')->count(),
    'overdue'   => Task::applyUserScope($user)->whereRaw('DATE_ADD(created_at, INTERVAL 1 DAY) < NOW()')->count(),
];
```

#### `getOverdueSurvey(User $user): int`
Count overdue survey tasks (SLA 1×24 hours):
```php
return Task::applyUserScope($user)
    ->join('customer_surveys', 'tasks.customer_id', '=', 'customer_surveys.customer_id')
    ->where('task_type', 'survey')
    ->where('status', '!=', 'selesai')
    ->whereRaw('DATE_ADD(customer_surveys.created_at, INTERVAL 1 DAY) < NOW()')
    ->count();
```

#### `getOverdueInstallation(User $user): int`
Count overdue installation tasks (SLA 3×24 hours from survey completion):
```php
return Task::applyUserScope($user)
    ->join('customer_surveys', 'tasks.customer_id', '=', 'customer_surveys.customer_id')
    ->where('task_type', 'pemasangan')
    ->where('status', '!=', 'selesai')
    ->whereRaw('DATE_ADD(customer_surveys.completed_at, INTERVAL 3 DAY) < NOW()')
    ->count();
```

## View: resources/views/fop/dashboard.blade.php

### Layout (12-column Grid)

**Top section:**
- Page title with icon
- Date range selector (week, month, custom)
- Refresh button

**Stat cards (4 cards, responsive):**
- **Total Tasks** — All tasks in period (white/neutral)
- **Completed** — Tasks dengan status selesai (green badge)
- **Pending** — Tasks menunggu aksi (yellow badge)
- **Overdue** — Tasks past SLA deadline (red badge) ⚠️

**Stat card structure:**
```blade
<div class="bg-surface border border-border rounded-lg p-4">
  <p class="text-xs font-semibold uppercase tracking-widest text-text-muted">{{ $label }}</p>
  <p class="text-3xl font-bold font-mono text-text-main mt-2">{{ $count }}</p>
  @if($badgeColor)
    <span class="inline-block mt-2 px-2 py-1 text-xs rounded" 
          style="background: {{ $badgeColor }}20; color: {{ $badgeColor }}">
      {{ $badge }}
    </span>
  @endif
</div>
```

**Middle section:**
- Quick action buttons:
  - "Kanban Task" → `/fop/kanban`
  - "Calendar" → `/fop/calendar`
  - "Reports" → `/reports/fop`

**Bottom section:**
- Active teams table (pagination)
- Recent activity log (latest task updates)

### Design System Variables

All colors use CSS vars:
- `--color-surface` — card background
- `--color-border` — border color
- `--color-text-main` — primary text
- `--color-text-muted` — secondary text
- `--color-success` — green (completed)
- `--color-warning` — yellow (pending)
- `--color-error` — red (overdue)
- `--color-info` — blue (info)

## SLA Calculation (Overdue Logic)

### Survey SLA: 1×24 Hours

**Rule:** From survey created_at, must complete within 24 hours

**Query:**
```sql
SELECT COUNT(*) 
FROM tasks t
JOIN customer_surveys cs ON t.customer_id = cs.customer_id
WHERE t.task_type = 'survey'
  AND t.status != 'selesai'
  AND DATE_ADD(cs.created_at, INTERVAL 1 DAY) < NOW()
```

**Example:**
- Survey created: 2026-06-26 10:00
- SLA deadline: 2026-06-27 10:00
- Check time: 2026-06-27 11:00 → OVERDUE ⚠️

### Installation SLA: 3×24 Hours

**Rule:** From survey completed_at, must complete installation within 3 days

**Query:**
```sql
SELECT COUNT(*) 
FROM tasks t
JOIN customer_surveys cs ON t.customer_id = cs.customer_id
WHERE t.task_type = 'pemasangan'
  AND t.status != 'selesai'
  AND DATE_ADD(cs.completed_at, INTERVAL 3 DAY) < NOW()
```

**Example:**
- Survey completed: 2026-06-24 14:00
- SLA deadline: 2026-06-27 14:00
- Check time: 2026-06-27 15:00 → OVERDUE ⚠️

## Data Flow

```
1. GET /fop
   ↓
2. FopDashboardController::index()
   ├─ auth()->user() → FOP user
   ├─ calculateStats() → [total, completed, pending, overdue]
   ├─ getOverdueSurvey() → int count
   ├─ getOverdueInstallation() → int count
   ├─ Get active teams
   ├─ Get recent activity (last 24h)
   └─ Return view with all data
   ↓
3. Blade render dashboard.blade.php
   ├─ Display 4 stat cards
   ├─ Render quick action buttons
   ├─ Show active teams table
   ├─ Show recent activity feed
   └─ All using design system colors
```

## Access Control

- **Permission:** `task.view.all` (only FOP can access)
- **POP scope:** Only tasks from user's assigned POP(s)
- **Middleware:** `CheckPermission::class` checks `task.view.all`

## Performance

1. **Stat calculation:** Cached for 5 minutes (optional)
2. **Query optimization:**
   - Use select() to fetch only needed columns
   - Index on `scheduled_at`, `status`, `created_at`
   - Use with('relationships') for eager loading
3. **N+1 prevention:** Load teams + tasks in single query with with()

## Testing

**Manual:**
1. Login as FOP user
2. Navigate to `/fop`
3. Verify 4 stat cards visible and correct counts
4. Check overdue badge color (red) if overdue > 0
5. Click quick action buttons → should navigate to correct pages
6. Verify responsive layout on mobile

**Unit Tests:**
- `calculateStats()` returns correct counts for date range
- `getOverdueSurvey()` calculates SLA correctly (1×24 hours)
- `getOverdueInstallation()` calculates SLA correctly (3×24 hours)
- Overdue count > 0 → red badge displayed

## Related Features

- [Kanban Task Scheduler](kanban-task-scheduler.md) — Linked from quick actions
- [Calendar Scheduler](calendar-scheduler.md) — Linked from quick actions
- [Overdue Indicator](overdue-indicator.md) — Detailed overdue logic

---

**Files:**
- `app/Http/Controllers/FopDashboardController.php`
- `resources/views/fop/dashboard.blade.php`
- `routes/web.php` — GET /fop route
- `app/Models/Task.php` — applyUserScope() query builder

**Last updated:** 2026-06-27
