---
name: Precision Operations
colors:
  surface: '#FFFFFF'
  surface-dim: '#d8dadc'
  surface-bright: '#f7f9fb'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f2f4f6'
  surface-container: '#eceef0'
  surface-container-high: '#e6e8ea'
  surface-container-highest: '#e0e3e5'
  on-surface: '#191c1e'
  on-surface-variant: '#3f4850'
  inverse-surface: '#2d3133'
  inverse-on-surface: '#eff1f3'
  outline: '#707881'
  outline-variant: '#bfc7d2'
  surface-tint: '#006398'
  primary: '#006194'
  on-primary: '#ffffff'
  primary-container: '#007bb9'
  on-primary-container: '#fdfcff'
  inverse-primary: '#93ccff'
  secondary: '#505f76'
  on-secondary: '#ffffff'
  secondary-container: '#d0e1fb'
  on-secondary-container: '#54647a'
  tertiary: '#712ae2'
  on-tertiary: '#ffffff'
  tertiary-container: '#8a4cfc'
  on-tertiary-container: '#fffbff'
  error: '#DC2626'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#cce5ff'
  primary-fixed-dim: '#93ccff'
  on-primary-fixed: '#001d31'
  on-primary-fixed-variant: '#004b73'
  secondary-fixed: '#d3e4fe'
  secondary-fixed-dim: '#b7c8e1'
  on-secondary-fixed: '#0b1c30'
  on-secondary-fixed-variant: '#38485d'
  tertiary-fixed: '#eaddff'
  tertiary-fixed-dim: '#d2bbff'
  on-tertiary-fixed: '#25005a'
  on-tertiary-fixed-variant: '#5a00c6'
  background: '#f7f9fb'
  on-background: '#191c1e'
  surface-variant: '#e0e3e5'
  success: '#16A34A'
  warning: '#D97706'
  border: '#E2E8F0'
  text-main: '#0F172A'
  primary-hover: '#0369A1'
  primary-soft: '#E0F2FE'
typography:
  display-lg:
    fontFamily: JetBrains Mono
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
    letterSpacing: -0.02em
  display-lg-mobile:
    fontFamily: JetBrains Mono
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-md:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 18px
  data-md:
    fontFamily: JetBrains Mono
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  data-sm:
    fontFamily: JetBrains Mono
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
  label-caps:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.05em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  topbar-height: 64px
  sidebar-width: 256px
  sidebar-collapsed: 72px
  container-max: 1180px
  form-max: 960px
  gutter: 1.25rem
  base-unit: 0.25rem
---

## Brand & Style

The design system is engineered for high-stakes ISP management and billing operations. The brand personality is **Professional, Technical, and Reliable**, prioritizing "Calm Productivity" for administrators who interact with the interface for extended periods. 

The aesthetic follows a **Corporate / Modern** style with a focus on **Information Density vs. Comfort**. It utilizes a "Data-First" philosophy where technical identifiers are visually separated from functional UI controls to prevent cognitive fatigue. The visual language is disciplined, clean, and utilitarian, ensuring that critical status changes (like payment failures or network isolations) are immediately perceptible without being visually overwhelming.

## Colors

The color palette is rooted in "Sky Blue & Slate," optimized for enterprise legibility. 

- **Primary**: Used for core actions, active navigation states, and primary CTAs.
- **Secondary (Slate)**: Used for metadata, secondary actions, and unselected UI elements to reduce visual noise.
- **Tertiary (Purple)**: Specifically reserved for the "Isolir" (Suspended) customer status, providing a unique visual signature for this critical ISP state.
- **Semantic Mapping**:
    - **Success**: Active, Paid, or Online status.
    - **Warning**: Pending, Scheduled, or Grace Period.
    - **Error**: Overdue, Critical, or Failed operations.

In **Dark Mode**, the surface tiers shift to deeper slates (`#1E293B`) to maintain contrast while reducing eye strain in low-light environments.

## Typography

This design system employs a strict dual-font strategy:

1.  **Inter**: Used for all interface elements, navigation, labels, and descriptive text. It provides a friendly yet professional humanist touch to the UI.
2.  **JetBrains Mono**: Used exclusively for technical data, including Customer IDs, IP Addresses, MAC Addresses, Timestamps, and Currency values. The monospaced nature ensures that characters like '0' and 'O' or '1' and 'l' are never confused, which is vital for network administration.

**Hierarchy Rules:**
- Large numerical displays on dashboard cards must use **JetBrains Mono** at the `display-lg` level.
- Sidebar group headers use `label-caps` to provide clear structural scaffolding.
- Technical tables should default to `data-sm` for high-density information display.

## Layout & Spacing

The layout utilizes a **Fixed Grid** model for the primary application shell, ensuring a stable environment for complex data management. 

- **The Shell**: A persistent left sidebar (256px) and topbar (64px) create the primary navigation frame. The sidebar can collapse to an icon-only view (72px) to maximize workspace on smaller desktop screens.
- **Content Areas**: Main dashboard content is housed in a container with a 1180px max-width, while focused form entries are restricted to 960px to maintain line-length readability.
- **Rhythm**: Spacing is based on a 4px (0.25rem) scale. Standard card padding is set to 20px (5 units) to provide a "Comfortable" density that avoids a cramped appearance despite the high data volume.
- **Responsive Behavior**: On mobile, the sidebar transitions into a 280px drawer, and the 20px margins scale down to 16px to conserve horizontal real estate.

## Elevation & Depth

Visual hierarchy is established primarily through **Tonal Layers** and **Low-Contrast Outlines** rather than aggressive shadows. This "flat-plus" approach minimizes visual clutter during long-term use.

- **Surface Levels**: The background uses a soft slate (`#F8FAFC`), while primary containers like cards and tables use a pure white surface (`#FFFFFF`).
- **Borders**: All containers use a 1px hairline border (`#E2E8F0`). 
- **Focus States**: High-precision focus is communicated via a subtle outer glow: `0 0 0 3px rgba(2, 132, 199, 0.15)`.
- **Status Accents**: Special "Metric Cards" use a 3px solid left-edge border colored by their semantic status (Success, Error, etc.) to provide immediate scannability without requiring a full-color background.

## Shapes

The design system uses a **Rounded** shape language to soften the technical nature of the data.

- **Cards & Containers**: Use `rounded-lg` (16px / 1rem) to frame data sections clearly.
- **Buttons & Inputs**: Use the base `rounded` (8px / 0.5rem) for a standard professional feel.
- **Status Badges & Search**: Use `rounded-full` (Pill-shaped) to distinguish these interactive or status-indicating elements from structural containers.
- **Technical Utilities**: Smaller elements like "Copy ID" buttons use a tighter `rounded-sm` (4px) to fit within compact data rows.

## Components

### Metric Cards
Dashboard cards displaying key performance indicators.
- **Style**: White surface, 1px border, 20px padding.
- **Indicator**: A 3px vertical accent bar on the far-left edge corresponding to the metric's status.
- **Value**: Large `display-lg` JetBrains Mono text for numbers/currency.

### Status Badges
Compact indicators for customer or payment states.
- **Style**: Pill-shaped with a light-tint background and dark-text variant of the semantic color.
- **Dot**: Includes a 6px x 6px solid circle (`badge-dot`) on the left of the text for enhanced visual recognition.

### Sidebar & Topbar
- **Sidebar**: Dark text on a white surface, with active states using `primary-soft` background and `primary` text/icon color. Group labels are in `label-caps`.
- **Topbar**: Fixed at 64px height, containing breadcrumbs, global search (pill-shaped), and user profile.

### Input Fields
- **Style**: 8px rounded corners, 1px slate border.
- **Typography**: `body-md` for labels, `data-md` for inputs containing technical values (IPs, IDs).

### Data Tables
- **Style**: Minimalist borders (horizontal only), `data-sm` typography for technical rows, and `body-sm` for UI labels within the table. High-density padding to maximize information visibility.