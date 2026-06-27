# Overdue Indicator (S8.6-T003)

Display jumlah overdue tasks di FOP Dashboard stat cards untuk priority management.

## Overview

**Problem:** FOP tidak tahu berapa banyak tasks yang sudah melampaui SLA, harus scroll/filter untuk lihat.

**Solution:** Add overdue indicator (stat card dengan badge merah) di dashboard FOP.

**Result:** FOP langsung lihat berapa overdue tasks tanpa perlu aksi tambahan.

## SLA Rules

### Survey Task: 1×24 Hours

**Timeline:**
- Survey created: T0
- SLA deadline: T0 + 1 day (24 hours)
- Status overdue: Now > T0 + 1 day

**Query:**
```sql
SELECT COUNT(*) FROM tasks t
JOIN customer_surveys cs ON t.customer_id = cs.customer_id
WHERE t.task_type = 'survey'
  AND t.status NOT IN ('selesai', 'batal')
  AND DATE_ADD(cs.created_at, INTERVAL 1 DAY) < NOW()
```

**Example Timeline:**
```
Created: 2026-06-26 10:00
Deadline: 2026-06-27 10:00
Now: 2026-06-27 10:01
Status: OVERDUE ⚠️ (1 minute late)
```

### Installation Task: 3×24 Hours

**Timeline:**
- Survey completed: T1
- SLA deadline: T1 + 3 days (72 hours)
- Status overdue: Now > T1 + 3 days

**Query:**
```sql
SELECT COUNT(*) FROM tasks t
JOIN customer_surveys cs ON t.customer_id = cs.customer_id
WHERE t.task_type = 'pemasangan'
  AND t.status NOT IN ('selesai', 'batal')
  AND DATE_ADD(cs.completed_at, INTERVAL 3 DAY) < NOW()
```

**Example Timeline:**
```
Survey completed: 2026-06-24 14:00
Deadline: 2026-06-27 14:00
Now: 2026-06-27 14:01
Status: OVERDUE ⚠️ (1 minute late)
```

## Implementation

### FopDashboardController

**File:** `app/Http/Controllers/FopDashboardController.php`

**Lines 164-181: Overdue calculation**

```php
private function getOverdueSurvey(User $user): int
{
    return Task::applyUserScope($user)
        ->join('customer_surveys', 'tasks.customer_id', '=', 'customer_surveys.customer_id')
        ->where('task_type', 'survey')
        ->whereNotIn('status', ['selesai', 'batal'])
        ->whereRaw('DATE_ADD(customer_surveys.created_at, INTERVAL 1 DAY) < NOW()')
        ->count();
}

private function getOverdueInstallation(User $user): int
{
    return Task::applyUserScope($user)
        ->join('customer_surveys', 'tasks.customer_id', '=', 'customer_surveys.customer_id')
        ->where('task_type', 'pemasangan')
        ->whereNotIn('status', ['selesai', 'batal'])
        ->whereRaw('DATE_ADD(customer_surveys.completed_at, INTERVAL 3 DAY) < NOW()')
        ->count();
}

// In index() method:
$stats = [
    'total'                 => $totalTasks,
    'completed'             => $completedTasks,
    'pending'               => $pendingTasks,
    'cancelled'             => $cancelledTasks,
    'overdue_survey'        => $this->getOverdueSurvey($user),
    'overdue_installation'  => $this->getOverdueInstallation($user),
];
```

### Dashboard View

**File:** `resources/views/fop/dashboard.blade.php`

**Stat card with overdue indicator:**

```blade
<!-- Overdue Indicator Card -->
<div class="bg-surface border border-border rounded-lg px-4 py-3">
    <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Overdue</p>
    <p class="text-2xl font-bold font-mono text-text-main mt-1">
        {{ $stats['overdue_survey'] + $stats['overdue_installation'] }}
    </p>
    @if(($stats['overdue_survey'] + $stats['overdue_installation']) > 0)
        <span class="inline-block mt-2 px-2 py-1 text-xs rounded font-semibold" 
              style="background: var(--color-error)20; color: var(--color-error)">
            ⚠️ {{ $stats['overdue_survey'] }} Survey, {{ $stats['overdue_installation'] }} Installation
        </span>
    @endif
</div>
```

**Alternative: Separate overdue cards**

```blade
<!-- Overdue Survey Card -->
<div class="bg-surface border border-border rounded-lg px-4 py-3">
    <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Overdue Survey</p>
    <p class="text-2xl font-bold font-mono mt-1" style="color:var(--color-error)">
        {{ $stats['overdue_survey'] }}
    </p>
</div>

<!-- Overdue Installation Card -->
<div class="bg-surface border border-border rounded-lg px-4 py-3">
    <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted">Overdue Installation</p>
    <p class="text-2xl font-bold font-mono mt-1" style="color:var(--color-error)">
        {{ $stats['overdue_installation'] }}
    </p>
</div>
```

## Visual Hierarchy

**Color coding for urgency:**
- **Green** (success): Completed tasks (target achieved)
- **Yellow** (warning): Pending tasks (in progress, within SLA)
- **Red** (error): Overdue tasks (SLA breached) ⚠️

## Data Flow

```
1. GET /fop
   ↓
2. FopDashboardController::index()
   ├─ auth()->user() → FOP
   ├─ calculateStats()
   ├─ getOverdueSurvey() → Query with DATE_ADD
   ├─ getOverdueInstallation() → Query with DATE_ADD
   └─ Return overdue counts
   ↓
3. Blade render dashboard.blade.php
   ├─ Display overdue stat card
   ├─ Show badge if overdue > 0
   ├─ Color: red (var(--color-error))
   └─ Show breakdown: X Survey, Y Installation
```

## Performance

**Query optimization:**

1. **Index needed:** `customer_surveys(customer_id, created_at, completed_at)`
2. **Index needed:** `tasks(customer_id, task_type, status, scheduled_at)`

**Query time:** ~50ms for 10k tasks (with index)

**Caching:** Optional 5-minute cache to reduce query frequency

```php
$overdueCount = Cache::remember('fop.overdue.' . $user->id, 300, function () use ($user) {
    return $this->getOverdueSurvey($user) + $this->getOverdueInstallation($user);
});
```

## Testing

**Manual Testing:**

1. Create survey task with created_at > 24 hours ago
   - Expected: Shows in overdue_survey count
2. Create survey task with created_at = now
   - Expected: Does NOT show in overdue count
3. Complete survey → create installation task with completed_at > 72 hours ago
   - Expected: Shows in overdue_installation count
4. Dashboard stat card shows overdue badge in red
5. Badge text shows breakdown: "X Survey, Y Installation"

**Unit Tests:**

```php
public function test_overdue_survey_counted_after_24_hours()
{
    $survey = CustomerSurvey::factory()->create([
        'created_at' => now()->subDay()->subHour(),
    ]);
    
    $count = $this->controller->getOverdueSurvey($user);
    $this->assertEquals(1, $count);
}

public function test_overdue_installation_counted_after_72_hours()
{
    $survey = CustomerSurvey::factory()->create([
        'completed_at' => now()->subDays(3)->subHour(),
    ]);
    
    $count = $this->controller->getOverdueInstallation($user);
    $this->assertEquals(1, $count);
}
```

## Alert System (Optional Enhancement)

Auto-alert FOP when overdue count increases:

```php
// In dashboard load
if ($stats['overdue_survey'] + $stats['overdue_installation'] > $user->last_overdue_count) {
    // Send notification or toast message
    session()->flash('warning', 'New overdue tasks detected!');
}

// Update user's cached overdue count
$user->last_overdue_count = $stats['overdue_survey'] + $stats['overdue_installation'];
$user->save();
```

## Related Features

- [FOP Dashboard](fop-dashboard.md) — Parent feature
- [Kanban Task Scheduler](kanban-task-scheduler.md) — Shows all tasks including overdue
- [Calendar Scheduler](calendar-scheduler.md) — Filtered view of tasks by date

---

**Files:**
- `app/Http/Controllers/FopDashboardController.php` (lines 164-181)
- `resources/views/fop/dashboard.blade.php` (stat cards section)

**Database indexes:**
- `customer_surveys(customer_id, created_at, completed_at)`
- `tasks(customer_id, task_type, status)`

**Last updated:** 2026-06-27
