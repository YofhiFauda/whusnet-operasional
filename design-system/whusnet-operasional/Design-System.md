# Design System Master File

**Project:** Whusnet Operasional  
**Category:** Enterprise Analytics Dashboard  
**Theme:** Sky Blue & Slate Corporate Mode  

---

## Logic

When building a specific page, first check:

```txt
design-system/pages/[page-name].md
```

If that file exists, its rules override this master file.

If not, strictly follow the rules defined in this document.

---

# Global Rules

## Color Palette

The color system uses a modern **Sky Blue** and **Slate Blue-Gray** palette.

Slate grays have a subtle blue undertone that harmonizes with the Sky Blue primary color. This creates a more cohesive, premium, and enterprise-grade look compared to pure neutral grays.

### Core Colors

| Role | Hex | CSS Variable | Notes |
|---|---:|---|---|
| Primary (Sky Blue) | `#0284C7` | `--color-primary` | Main buttons, active states, highlights |
| Primary Hover | `#0369A1` | `--color-primary-hover` | Button hover states |
| Background (App) | `#F8FAFC` | `--color-background` | App body background, Slate 50 |
| Surface (Card) | `#FFFFFF` | `--color-surface` | Cards, modals, tables |
| Border | `#E2E8F0` | `--color-border` | Dividers, card outlines, Slate 200 |
| Text Main | `#0F172A` | `--color-text-main` | Headings, primary body text, Slate 900 |
| Text Muted | `#64748B` | `--color-text-muted` | Labels, helper text, secondary text, Slate 500 |

### Semantic Colors

| Role | Hex | CSS Variable | Background Pairing |
|---|---:|---|---|
| Success | `#16A34A` | `--color-success` | `bg-green-50` / `#F0FDF4` |
| Warning | `#D97706` | `--color-warning` | `bg-amber-50` / `#FFFBEB` |
| Error / Danger | `#DC2626` | `--color-error` | `bg-red-50` / `#FEF2F2` |

---

## Typography

Typography is optimized for **UI clarity**, **dashboard readability**, and **tabular alignment**.

| Usage | Font | Reason |
|---|---|---|
| UI, Headings, Paragraphs & General Text | Inter | Main font for dashboard UI, headings, body text, navigation, forms, labels, buttons, descriptions, and general content |
| Numbers, IDs & Tabular Data | JetBrains Mono | Monospaced font for numeric alignment, transaction IDs, billing amounts, counters, timestamps, codes, and analytics values |

**Mood:** modern, analytical, trustworthy, clean.

### Google Fonts

```css
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap');

:root {
  --font-ui: 'Inter', sans-serif;
  --font-data: 'JetBrains Mono', monospace;
}
```

### Typography Rules

- `Inter` is the primary UI font and must be used for almost all readable interface text.
- Use `Inter` for:
  - Page headings.
  - Section titles.
  - Card titles.
  - Paragraphs.
  - Descriptions.
  - Navigation menus.
  - Sidebar items.
  - Buttons.
  - Form labels.
  - Input text.
  - Helper text.
  - Table headers.
  - Table text labels.
  - Empty states.
  - Modal content.
  - Alert messages.
- `JetBrains Mono` is not for general text. It is specifically used for numeric and code-like operational data.
- Use `JetBrains Mono` only for:
  - Numbers.
  - Currency values.
  - Billing amounts.
  - Transaction IDs.
  - Customer IDs.
  - Registration IDs.
  - Invoice numbers.
  - Counters.
  - Analytics values.
  - Percentages.
  - IP addresses.
  - Coordinates.
  - Timestamps.
  - Dates when displayed inside data tables or logs.
  - System codes or reference codes.
- Do not use `JetBrains Mono` for headings, paragraphs, buttons, labels, menus, or long descriptions.
- Avoid mixing too many font weights.
- Recommended font weights:
  - `400` for regular body text.
  - `500` for buttons, labels, table headers, and data values.
  - `600` for card titles and section titles.
  - `700` for major dashboard headings only.

---

## Spacing & Borders

| Token | Value | Usage |
|---|---:|---|
| `--space-xs` | `4px` | Tight gaps, such as icon and text spacing |
| `--space-sm` | `8px` | Small gaps, compact input padding |
| `--space-md` | `16px` | Standard component padding for cards, buttons, forms |
| `--space-lg` | `24px` | Section spacing inside cards or dashboard blocks |
| `--border-radius` | `6px` or `8px` | Keep corners relatively sharp and enterprise-like |

### Spacing Rules

- Use consistent spacing between related elements.
- Do not use random spacing values when a token already exists.
- Prefer compact spacing for data-heavy dashboard pages.
- Use larger spacing only for section separation or empty-state screens.

### Border Radius Rules

- Use `6px` for buttons, inputs, badges, dropdowns, and compact UI components.
- Use `8px` for cards, modals, tables, and dashboard panels.
- Avoid overly rounded corners because the dashboard theme should feel professional and analytical.

---

# Component Specs

## Buttons

### Primary Button

Use primary buttons for the main action on a screen, such as:

- Save
- Submit
- Create
- Import
- Confirm
- Process

```css
.btn-primary {
  background: var(--color-primary);
  color: #FFFFFF;
  padding: 8px 16px;
  border-radius: 6px;
  font-family: var(--font-ui);
  font-weight: 500;
  font-size: 14px;
  border: none;
  transition: all 200ms ease;
  cursor: pointer;
}

.btn-primary:hover {
  background: var(--color-primary-hover);
}

.btn-primary:focus {
  outline: none;
  box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.25);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
```

### Secondary Button

Use secondary buttons for supporting actions, such as:

- Cancel
- Back
- Reset
- Filter
- Export
- View Detail

```css
.btn-secondary {
  background: #FFFFFF;
  color: var(--color-text-main);
  padding: 8px 16px;
  border-radius: 6px;
  font-family: var(--font-ui);
  font-weight: 500;
  font-size: 14px;
  border: 1px solid var(--color-border);
  transition: all 200ms ease;
  cursor: pointer;
}

.btn-secondary:hover {
  background: #F8FAFC;
}

.btn-secondary:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.18);
}

.btn-secondary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
```

---

## Form Inputs

Use form inputs for text fields, search fields, filters, select boxes, and editable data.

```css
.input {
  font-family: var(--font-ui);
  font-size: 14px;
  padding: 8px 12px;
  border: 1px solid var(--color-border);
  border-radius: 6px;
  color: var(--color-text-main);
  background-color: var(--color-surface);
  transition: all 150ms ease;
}

.input::placeholder {
  color: var(--color-text-muted);
}

.input:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.2);
}

.input:disabled {
  background-color: #F1F5F9;
  color: var(--color-text-muted);
  cursor: not-allowed;
}
```

### Input Rules

- Inputs must always have a visible focus state.
- Placeholder text must be muted, not dominant.
- Use `Inter` for all input text.
- Use `JetBrains Mono` only when the input is specifically for IDs, codes, numeric data, or transaction references.

---

## Data & Number Styling

Use `JetBrains Mono` for analytics values, currency, counters, IDs, transaction codes, and timestamps.

```css
.data-text {
  font-family: var(--font-data);
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text-main);
  font-variant-numeric: tabular-nums;
}
```

Recommended usage:

```html
<span class="data-text">INV-20260609-0001</span>
<span class="data-text">Rp 1.250.000</span>
<span class="data-text">08:30</span>
```

---

## Cards

Cards are used for dashboard widgets, statistics, tables, summaries, and grouped content.

```css
.card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 16px;
}
```

### Card Rules

- Cards must use white surface color.
- Cards must have subtle borders.
- Avoid heavy shadows unless the page needs strong elevation.
- Use spacing consistently inside cards.
- Keep dashboard cards compact and readable.

---

## Tables

Tables are used for operational data, billing records, customer lists, tickets, logs, and analytics summaries.

```css
.table {
  width: 100%;
  border-collapse: collapse;
  font-family: var(--font-ui);
  font-size: 14px;
  color: var(--color-text-main);
}

.table th {
  font-weight: 500;
  color: var(--color-text-muted);
  background: #F8FAFC;
  border-bottom: 1px solid var(--color-border);
  padding: 12px;
  text-align: left;
}

.table td {
  border-bottom: 1px solid var(--color-border);
  padding: 12px;
}

.table .data-cell {
  font-family: var(--font-data);
  font-variant-numeric: tabular-nums;
}
```

### Table Rules

- Use `Inter` for labels and regular text.
- Use `JetBrains Mono` for numeric values, IDs, billing amounts, and timestamps.
- Keep row height compact but readable.
- Header text should use muted color.
- Avoid excessive borders; use subtle dividers only.

---

## Badges

Badges are used for status indicators such as active, pending, paid, overdue, failed, or completed.

```css
.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  border-radius: 6px;
  font-family: var(--font-ui);
  font-size: 12px;
  font-weight: 500;
}

.badge-success {
  color: var(--color-success);
  background: #F0FDF4;
}

.badge-warning {
  color: var(--color-warning);
  background: #FFFBEB;
}

.badge-error {
  color: var(--color-error);
  background: #FEF2F2;
}
```

---

# Layout Rules

## App Background

The main application background must use Slate 50.

```css
body {
  background: var(--color-background);
  color: var(--color-text-main);
  font-family: var(--font-ui);
}
```

## Surface Hierarchy

Use this hierarchy consistently:

1. App background: `#F8FAFC`
2. Cards, modals, tables: `#FFFFFF`
3. Borders and dividers: `#E2E8F0`
4. Primary actions: `#0284C7`
5. Main text: `#0F172A`
6. Muted text: `#64748B`

---

# Accessibility Rules

## Focus States

All interactive elements must have visible focus states.

Interactive elements include:

- Buttons
- Links
- Inputs
- Selects
- Textareas
- Dropdowns
- Tabs
- Pagination items
- Sidebar menu items
- Table action buttons

## Cursor Rules

All clickable elements must use:

```css
cursor: pointer;
```

Disabled clickable elements must use:

```css
cursor: not-allowed;
```

## Contrast Rules

- Minimum contrast ratio must be `4.5:1` for normal text.
- Do not use muted text for important information.
- Use semantic colors with light background pairing for readability.
- Error messages must be clearly visible and not rely on color alone.

---

# CSS Variables

Use these global CSS variables as the base token system.

```css
:root {
  /* Colors */
  --color-primary: #0284C7;
  --color-primary-hover: #0369A1;
  --color-background: #F8FAFC;
  --color-surface: #FFFFFF;
  --color-border: #E2E8F0;
  --color-text-main: #0F172A;
  --color-text-muted: #64748B;

  /* Semantic Colors */
  --color-success: #16A34A;
  --color-warning: #D97706;
  --color-error: #DC2626;

  /* Typography */
  --font-ui: 'Inter', sans-serif;
  --font-data: 'JetBrains Mono', monospace;

  /* Spacing */
  --space-xs: 4px;
  --space-sm: 8px;
  --space-md: 16px;
  --space-lg: 24px;

  /* Radius */
  --border-radius: 6px;
}
```

---

# Pre-Delivery Checklist

Before delivering any page or component, verify the following:

- [ ] Typography split is respected: `Inter` for UI, headings, paragraphs, labels, buttons, and general text; `JetBrains Mono` only for numbers, IDs, currency, timestamps, and tabular data.
- [ ] Primary app background uses Slate 50: `#F8FAFC`.
- [ ] Cards, modals, and tables use White: `#FFFFFF`.
- [ ] Colors use the Slate and Sky Blue combinations consistently.
- [ ] `cursor: pointer` is present on all clickable elements.
- [ ] Disabled clickable elements use `cursor: not-allowed`.
- [ ] Focus states are visible on all inputs, buttons, links, and interactive components.
- [ ] Minimum contrast ratio `4.5:1` is maintained for all text elements.
- [ ] Tables use proper tabular alignment for IDs, numbers, timestamps, and currency values.
- [ ] Semantic colors are paired with their proper light background colors.
- [ ] Components remain compact, clean, and suitable for an enterprise analytics dashboard.
