# Task Management Page Overrides

> **PROJECT:** Billing ISP FOP Dashboard
> **Generated:** 2026-06-27 09:56:28
> **Page Type:** Product Detail

> ⚠️ **IMPORTANT:** Rules in this file **override** the Master file (`design-system/MASTER.md`).
> Only deviations from the Master are documented here. For all other rules, refer to the Master.

---

## Page-Specific Rules

### Layout Overrides

- **Max Width:** 1200px (standard)
- **Layout:** Full-width sections, centered content
- **Sections:** 1. Hero, 2. Step 1 (problem), 3. Step 2 (solution), 4. Step 3 (action), 5. CTA progression

### Spacing Overrides

- No overrides — use Master spacing

### Typography Overrides

- No overrides — use Master typography

### Color Overrides

- **Strategy:** Step colors: 1 (Red/Problem), 2 (Orange/Process), 3 (Green/Solution). CTA: Brand color

### Component Overrides

- Avoid: Use arbitrary large z-index values
- Avoid: No indication of progress
- Avoid: No caching strategy

---

## Page-Specific Components

- No unique components for this page

---

## Recommendations

- Effects: Deal movement animations, metric updates, leaderboard ranking changes, gauge needle movements, status change highlights
- Layout: Define z-index scale system (10 20 30 50)
- Feedback: Step indicators or progress bar
- Performance: Set appropriate cache headers
- CTA Placement: Each step: mini-CTA. Final: main CTA
