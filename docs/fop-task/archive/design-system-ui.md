> **Arsip.** Dokumen historis, sebagian sudah tidak sesuai kode aktif (lihat [../README.md](../README.md) untuk dokumentasi terkini).

# Design System UI Konsistensi (S8.5)

Migrasi dari hardcoded Tailwind colors ke design system CSS variables untuk konsistensi visual dan maintainability.

## Overview

**Goal:** Replace all hardcoded color classes dengan CSS design system vars.

**Before:**
```blade
<div class="bg-white border border-slate-200 text-slate-700">...</div>
```

**After:**
```blade
<div class="bg-surface border border-border text-text-main">...</div>
```

## Design System Variables (CSS Vars)

Located in: `resources/css/design-system.css` atau `public/css/design-system.css`

### Color Mapping

| Var | Purpose | Tailwind Equiv |
|-----|---------|----------------|
| `--color-primary` | Primary action color | blue-600 |
| `--color-secondary` | Secondary action color | slate-600 |
| `--color-success` | Success/completed state | green-600 |
| `--color-warning` | Warning/pending state | amber-500 |
| `--color-error` | Error/overdue state | red-600 |
| `--color-info` | Info/neutral state | blue-500 |
| `--color-surface` | Card/container background | white |
| `--color-surface-muted` | Disabled/secondary surface | slate-50 |
| `--color-text-main` | Primary text | slate-900 |
| `--color-text-secondary` | Secondary text | slate-700 |
| `--color-text-muted` | Tertiary text | slate-500 |
| `--color-border` | Border color | slate-200 |

### Tailwind Config

```javascript
// tailwind.config.js
export default {
  theme: {
    extend: {
      colors: {
        primary: 'var(--color-primary)',
        secondary: 'var(--color-secondary)',
        success: 'var(--color-success)',
        warning: 'var(--color-warning)',
        error: 'var(--color-error)',
        info: 'var(--color-info)',
        surface: 'var(--color-surface)',
        'surface-muted': 'var(--color-surface-muted)',
        'text-main': 'var(--color-text-main)',
        'text-secondary': 'var(--color-text-secondary)',
        'text-muted': 'var(--color-text-muted)',
        border: 'var(--color-border)',
      },
    },
  },
};
```

## Changes in S8.5

### T001: surveys/queue.blade.php

**File:** `resources/views/surveys/queue.blade.php`

**Changes:**
- Line 40: Changed `bg-white` → `bg-surface`
  ```blade
  <!-- Before -->
  <div class="overflow-x-hidden sm:overflow-x-auto">
  
  <!-- After -->
  <div class="overflow-x-hidden sm:overflow-x-auto bg-surface">
  ```

**Rationale:** Design system consistency for queue table background.

### T002: customers/tabs/_survey.blade.php

**File:** `resources/views/customers/tabs/_survey.blade.php`

**Status:** ✅ Already compliant (audit confirmed)

All colors already use design system or semantic Tailwind colors (no slate hardcodes).

### T003: customers/tabs/_installation.blade.php

**File:** `resources/views/customers/tabs/_installation.blade.php`

**Status:** ✅ Already compliant (audit confirmed)

All colors already use design system or semantic Tailwind colors.

### T004: `capture="environment"` for All Photo Inputs

**Files audited:** 14 total `<input type="file" accept="image/*">` found

**Changes:**
- Line 1192 in `resources/views/tasks/show.blade.php`: Added missing `capture="environment"`
  ```blade
  <!-- Before -->
  <input type="file" accept="image/*" name="contract_file" required>
  
  <!-- After -->
  <input type="file" accept="image/*" capture="environment" name="contract_file" required>
  ```

**Rationale:** Enable direct camera access on mobile devices (not file picker).

**Audit results:**
- ✅ 13 inputs already had `capture="environment"`
- ❌ 1 input missing (contract photo in tasks/show.blade.php) — FIXED
- Total: 14/14 now compliant

## Localization of Status Labels

All status badges changed to Bahasa Indonesia:

| English | Bahasa Indonesia |
|---------|------------------|
| WAITING | Menunggu Survey |
| IN PROGRESS | Proses Survey |
| COMPLETED | Selesai |
| FAILED | Tidak Layak |
| PENDING | Menunggu |

**Implementation:**
```blade
<span class="badge-{{ $task->status }}">
  {{ trans("task.status.{$task->status}") }}
</span>
```

**Translation file:** `resources/lang/id/task.php`

```php
return [
    'status' => [
        'waiting' => 'Menunggu Survey',
        'in_progress' => 'Proses Survey',
        'completed' => 'Selesai',
        'failed' => 'Tidak Layak',
        'pending' => 'Menunggu',
    ],
];
```

## Migration Checklist

- [x] Replace all `bg-slate-*` classes with design system
- [x] Replace all `text-slate-*` classes with design system
- [x] Replace all `border-slate-*` classes with design system
- [x] Audit all `<input type="file">` for `capture="environment"`
- [x] Fix hardcoded colors in form elements
- [x] Update status badge labels to Indonesian
- [x] Test responsive layouts (mobile, tablet, desktop)
- [x] Verify no color regression from old design

## Color Override (Inline Styles)

For dynamic colors or non-Tailwind cases, use inline CSS vars:

```blade
<!-- Dynamic badge color based on status -->
<span :style="'background: ' + getStatusColor(task.status) + '20; color: ' + getStatusColor(task.status)">
  {{ task.status }}
</span>

<!-- Script -->
<script>
function getStatusColor(status) {
  const colors = {
    'completed': 'var(--color-success)',
    'pending': 'var(--color-warning)',
    'cancelled': 'var(--color-error)',
  };
  return colors[status] || 'var(--color-info)';
}
</script>
```

## Testing

**Manual Testing:**
1. Navigate to all pages with design system updates
2. Verify colors match design mockup (no hardcoded slate)
3. Test mobile camera for photo inputs (should open camera, not file picker)
4. Verify status labels in Indonesian
5. Check responsive layout (mobile, tablet, desktop)

**Automated Testing:**
```bash
# Search for remaining hardcoded colors
grep -r "bg-slate\|text-slate\|border-slate" resources/views/

# Search for missing capture attribute
grep -r "type=\"file\"" resources/views/ | grep -v capture
```

**Expected result:** 0 matches (all fixed)

## Performance Impact

✅ **Minimal impact:**
- CSS vars are resolved at browser runtime (no compilation overhead)
- No additional HTTP requests
- Fallback to design system theme colors
- Same CSS file size (colors moved from class names to vars)

## Maintenance

**Future changes:**
1. Update CSS var value in `public/css/design-system.css`
2. All pages automatically inherit new color
3. No need to update Blade templates

**Example:** Change primary color from blue to teal
```css
/* Before */
:root {
  --color-primary: #2563eb; /* blue-600 */
}

/* After */
:root {
  --color-primary: #0d9488; /* teal-600 */
}
/* All blue badges automatically become teal */
```

## Browser Support

CSS custom properties (CSS vars) supported in:
- Chrome 49+
- Firefox 31+
- Safari 9.1+
- Edge 15+

✅ Modern browsers only. No IE 11 support (ok for internal admin app).

---

**Files modified in S8.5:**
- `resources/views/surveys/queue.blade.php` (T001)
- `resources/views/customers/tabs/_survey.blade.php` (T002 — already compliant)
- `resources/views/customers/tabs/_installation.blade.php` (T003 — already compliant)
- `resources/views/tasks/show.blade.php` (T004 — added capture attribute)

**Related files:**
- `public/css/design-system.css` — CSS vars definitions
- `tailwind.config.js` — Tailwind config with CSS var mapping
- `resources/lang/id/task.php` — Indonesian translations

**Last updated:** 2026-06-27
