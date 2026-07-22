# Design.md — WHUSNET Admin Payment
## ISP Billing Enterprise · Clean, Minimalist & Modern UI

**Project:** WHUSNET Admin Payment  
**Stack:** Laravel · Blade · Tailwind CSS · Alpine.js · Laravel Reverb · Filament  
**Design System Base:** Design-System-Enterprise-Grade-v3  
**Design Theme:** Modern Sky Blue & Slate Neutral (Light & Dark)  
**Version:** `v2.0.0`  
**Status:** Primary UI/UX Design Specification — sumber tunggal, menggantikan seluruh varian sebelumnya  
**Last Updated:** 2026-07-22
**Patch Tambahan**  Mencegah AI default ke "card-per-section"

---

## Filosofi Desain WHUSNET Admin Payment

WHUSNET Admin Payment adalah aplikasi billing dan manajemen pelanggan ISP yang digunakan setiap hari oleh tim Finance, NOC, dan Admin POP. Antarmuka tidak boleh terasa berat, kaku, atau melelahkan. Desain harus mencapai keseimbangan antara:

> **Enterprise-grade** (aman, presisi, auditable) + **Clean & Minimalist** (bersih, lega, cepat dibaca)

Empat prinsip utama:

1. **Calm Productivity** — UI tidak membuat user lelah setelah 8 jam penggunaan. Warna bersih, spacing konsisten, tidak ada dekorasi yang membuang perhatian.
2. **Clean & Breathable** — spacing seimbang (tidak terlalu rapat, tidak terlalu renggang), struktur visual bersih tanpa dekorasi berlebihan. Whitespace dan garis 1px adalah pembatas, bukan tumpukan box.
3. **Data Clarity First** — Angka rupiah, ID pelanggan, status jaringan harus terbaca seketika tanpa harus squinting atau zoom-in. *Tabular digits* dan *monospace* untuk semua nilai teknis.
4. **Action Confidence** — Setiap tombol, form, dan alur kerja dirancang sehingga user tahu persis apa yang akan terjadi sebelum mereka mengklik.

**Aksen warna:** *Sky Blue* (`#0284C7` / `#0EA5E9`) memberi kesan modern, ramah, dan profesional; dipadu *Slate* (`#0F172A` / `#F8FAFC`) yang tenang dan ramah mata untuk sesi kerja panjang.

---

## 1. Arsitektur Visual

### 1.1 Layout Shell

```
┌─────────────────────────────────────────────────────────────────┐
│ Topbar (64px) — Breadcrumb, Global Search, Tema, Notif, User    │
├──────────────────┬──────────────────────────────────────────────┤
│ Sidebar (256px)  │ Main Content Area                            │
│                  │ ┌─────────────────────────────────────────┐ │
│ OPERASIONAL      │ │ Alert Banner (conditional)               │ │
│  Dashboard       │ ├─────────────────────────────────────────┤ │
│  Pelanggan       │ │ Page Header (title + breadcrumb + CTA)  │ │
│  Tagihan         │ ├─────────────────────────────────────────┤ │
│  Pembayaran      │ │ Filter Bar / Tab Bar (conditional)      │ │
│                  │ ├─────────────────────────────────────────┤ │
│ JARINGAN         │ │ Content: Cards, Tables, Forms           │ │
│  POP & Node      │ │                                         │ │
│  Paket Layanan   │ │                                         │ │
│                  │ ├─────────────────────────────────────────┤ │
│ LAPORAN          │ │ Pagination / Footer                     │ │
│  Keuangan        │ └─────────────────────────────────────────┘ │
│  Audit Log       │                                              │
│                  │                                              │
│ PENGATURAN       │                                              │
│  Pengguna & RBAC │                                              │
│  Konfigurasi     │                                              │
└──────────────────┴──────────────────────────────────────────────┘
```

### 1.2 Ukuran & Proporsi

| Elemen | Ukuran | Catatan |
|---|---:|---|
| Topbar height | `64px` | Sticky, border-bottom |
| Sidebar expanded | `256px` | Bisa collapse ke `72px` |
| Sidebar collapsed | `72px` | Icon-only mode |
| Sidebar mobile drawer | `280px` | Overlay dari kiri |
| Content max-width (form) | `960px` | Create/edit page |
| Content max-width (detail) | `1180px` | Detail pelanggan |
| Content data table | `full width` | Billing, audit log |
| Page header height | `auto` | Min `80px` |

---

## 1.3 Page Type System & Card Budget

Setiap halaman di WHUSNET termasuk dalam salah satu dari 3 tipe.
Card budget adalah jumlah maksimum elemen yang boleh memiliki
kombinasi `background + border + border-radius` secara bersamaan.

### Tiga Tipe Halaman

```
TYPE A — DATA LIST PAGE
Halaman: Pelanggan, Tagihan, Pembayaran, POP, Pengguna, Audit Log

  [Page Header — naked, no card]
  [Summary Strip — flat bar, no card]       ← BUKAN 4 card terpisah
  [Filter Bar — naked, no card]
  ┌──────────────────────────────────┐       ← 1 card: TABLE PANEL
  │ [Table rows]                     │
  │ [Pagination]                     │
  └──────────────────────────────────┘
  Card Budget: 1 (hanya table panel)

────────────────────────────────────────────────────────────────────

TYPE B — RECORD DETAIL PAGE
Halaman: Detail Pelanggan, Detail Invoice, Detail POP

  [Page Header — naked, no card]
  ┌──────────────────────────────────┐       ← 1 card: DETAIL PANEL
  │ [Tab Bar]                        │
  │ ─────────────────────────────── │       divider, bukan border card
  │ [Metric Strip]                   │
  │ ─────────────────────────────── │       divider, bukan border card
  │ [Tab Content]                    │
  │   [Col Left]  │  [Col Right]     │       border-left, bukan 2 card
  │ ─────────────────────────────── │       divider, bukan border card
  │ [Chart / Full-width section]     │
  └──────────────────────────────────┘
  Card Budget: 1 (hanya detail panel)

────────────────────────────────────────────────────────────────────

TYPE C — AGGREGATE DASHBOARD PAGE
Halaman: Dashboard, Laporan Keuangan

  [Page Header — naked, no card]
  [Alert Banner — colored, conditional]
  ┌────┐ ┌────┐ ┌────┐ ┌────┐              ← 4 card: METRIC CARDS
  │KPI │ │KPI │ │KPI │ │KPI │
  └────┘ └────┘ └────┘ └────┘
  ┌─────────────────────┐ ┌──────────┐     ← card per chart
  │ [Line/Bar Chart]    │ │ [Donut]  │
  └─────────────────────┘ └──────────┘
  ┌──────────────────┐ ┌────────────┐      ← card per recent list
  │ [Recent Payments]│ │ [Activity] │
  └──────────────────┘ └────────────┘
  Card Budget: tidak terbatas (ini tujuannya)
```

### Aturan Universal (Berlaku Semua Tipe)

1. **Page Header selalu naked** — tidak pernah dibungkus card.
   Title, breadcrumb, subtitle, dan action buttons langsung di atas
   konten tanpa background/border.

2. **Filter bar selalu naked** — search input dan dropdown filter
   tidak punya card wrapper. Mereka langsung di background halaman.

3. **Summary/KPI strip di Type A adalah flat bar** — bukan grid card.
   Gunakan divider vertikal antar kolom, bukan card per KPI.

4. **Warna status hanya pada badge dan teks** — tidak pernah sebagai
   background warna pada card atau section container. Tidak ada
   `bg-orange-50 border-orange-200` untuk billing unpaid, tidak ada
   `bg-green-50` untuk status aktif di level section.

5. **Modal selalu card** — floating overlay butuh batas visual tegas.
   Ini tidak dihitung dalam card budget halaman.

6. **Alert Banner selalu card** — conditional banner gangguan di atas
   konten. Ini tidak dihitung dalam card budget halaman.

---

## 1.4 Universal Layout Layers

Setiap halaman dibangun dari layer-layer berikut (dari luar ke dalam):

```
LAYER 0 — APP SHELL (tidak berubah antar halaman)
  Topbar 64px + Sidebar 256px
  Background: var(--color-background) = #F8FAFC

LAYER 1 — PAGE HEADER (naked — tidak pernah ada card di sini)
  Breadcrumb
  Title row: [h1] + [status badge] + [ID teknis] + [action buttons]
  Selalu margin-bottom: 20px sebelum layer berikutnya

LAYER 2 — CONTEXTUAL BAR (optional, naked)
  Untuk Type A: filter bar
  Untuk Type C: alert banner
  Untuk Type B: tidak ada (metric strip ada di dalam detail panel)

LAYER 3 — PRIMARY CONTENT
  Untuk Type A: table panel (1 card)
  Untuk Type B: detail panel (1 card)
  Untuk Type C: grid of cards

LAYER 4 — SECONDARY CONTENT (optional)
  Untuk Type A: pagination (di dalam table panel)
  Untuk Type B: sudah di dalam detail panel
  Untuk Type C: chart panels, recent lists
```

---

## 1.5 Universal Prohibited Patterns

Daftar ini berlaku untuk SEMUA halaman, semua tipe.
AI yang menghasilkan salah satu pattern ini harus di-regenerate.

### ❌ DILARANG: Card untuk Page Header

```html
<!-- SALAH di semua tipe halaman -->
<div class="card p-6 mb-4">
  <h1>Pelanggan</h1>
  <p>Kelola data pelanggan...</p>
  <button>Tambah Pelanggan</button>
</div>

<!-- BENAR — naked header -->
<div class="page-header">
  <div class="page-header-left">
    <h1>Pelanggan</h1>
    <p class="text-muted">Kelola data pelanggan...</p>
  </div>
  <button class="btn-primary">Tambah Pelanggan</button>
</div>
```

### ❌ DILARANG: Card untuk Filter Bar

```html
<!-- SALAH — filter dibungkus card -->
<div class="card p-4 mb-4">
  <input placeholder="Cari...">
  <select>...</select>
</div>

<!-- BENAR — naked filter bar -->
<div class="filter-bar">
  <input class="search-input" placeholder="Cari nama / CID / HP">
  <select class="filter-select">...</select>
</div>
```

### ❌ DILARANG: KPI Strip jadi 4 card terpisah di Type A

```html
<!-- SALAH di halaman Tagihan, Pembayaran, POP -->
<div class="grid grid-cols-4 gap-4">
  <div class="card">Belum Dibayar: 87</div>
  <div class="card">Overdue: 15</div>
  <div class="card">Lunas: 23</div>
  <div class="card">Total: Rp 127.5jt</div>
</div>

<!-- BENAR — flat summary strip dengan divider -->
<div class="summary-strip">
  <div class="summary-col">
    <span class="summary-label">BELUM DIBAYAR</span>
    <span class="summary-value">87</span>
  </div>
  <div class="summary-col">
    <span class="summary-label">OVERDUE</span>
    <span class="summary-value error">15</span>
  </div>
  <div class="summary-col">
    <span class="summary-label">LUNAS HARI INI</span>
    <span class="summary-value success">23</span>
  </div>
  <div class="summary-col">
    <span class="summary-label">TOTAL BULAN INI</span>
    <span class="summary-value">Rp 127.5jt</span>
  </div>
</div>
```

### ❌ DILARANG: Radius campur-campur

Satu skala radius untuk seluruh aplikasi. Panel, card, tombol, input, select,
dan modal semuanya **8px** (`rounded-lg`). Pengecualian hanya dua:

| Elemen | Radius | Alasan |
|---|---|---|
| Panel, card, modal, tombol, select | `8px` | Skala dasar |
| Search input, badge, pill, chip | `9999px` | Bentuk pill memang disengaja |
| Avatar kotak, icon box | `8px` | Ikut skala dasar |

```html
<!-- SALAH — tiga radius berbeda dalam satu layar -->
<div class="rounded-2xl">   <!-- 16px panel -->
  <button class="rounded-xl">  <!-- 12px tombol -->
    <select class="rounded-lg">  <!-- 8px select -->

<!-- BENAR — satu skala -->
<div class="rounded-lg"><button class="rounded-lg"><select class="rounded-lg">
```

### ❌ DILARANG: Gradient sebagai warna elemen

Tombol utama, logo mark, dan badge memakai **warna solid** dari token.
Gradient (`bg-gradient-to-r from-… to-…`) tidak ada di design token dan
membuat warna primary punya dua nilai berbeda tergantung posisi piksel —
menyulitkan pengecekan kontras dan tidak bisa direplikasi di dark mode.

```html
<!-- SALAH -->
<button class="bg-gradient-to-r from-skybrand-500 to-skybrand-600 shadow-md shadow-skybrand-500/25">

<!-- BENAR -->
<button class="bg-skybrand-600 hover:bg-skybrand-700">
```

### ❌ DILARANG: Warna background pada section container

```html
<!-- SALAH di semua tipe halaman -->
<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
  Status: UNPAID · Rp 250.000
</div>

<div class="bg-green-50 border border-green-200 rounded-lg p-4">
  Status Layanan: AKTIF
</div>

<!-- BENAR — warna hanya pada badge/teks -->
<div class="detail-info-row">
  <span class="badge badge-warning">● UNPAID</span>
  <span class="currency text-warning">Rp 250.000</span>
</div>
```

### ❌ DILARANG: Double card (card dalam card)

```html
<!-- SALAH — card di dalam card -->
<div class="card">              <!-- outer card -->
  <div class="card-header">Detail Teknis</div>
  <div class="card p-4">        <!-- inner card — DILARANG -->
    MAC: A4:C3:...
  </div>
</div>

<!-- BENAR — flat rows dalam satu card -->
<div class="card">
  <div class="section-title">DETAIL TEKNIS</div>
  <div class="info-row">
    <span class="label">MAC Address</span>
    <span class="value mono">A4:C3:F0:B2:11:22</span>
  </div>
</div>
```

### ❌ DILARANG: Tabs dengan border+radius duplikat

```html
<!-- SALAH — tab content punya card sendiri (jadi double border) -->
<div class="tabs border-b">
  <button class="tab active">Overview</button>
</div>
<div class="card mt-0 rounded-t-none">  <!-- border duplikat -->
  Tab content...
</div>

<!-- BENAR — tabs dan content dalam satu panel -->
<div class="detail-panel">          <!-- 1 card saja -->
  <div class="tab-bar">...</div>    <!-- border-bottom internal -->
  <div class="tab-content">         <!-- no border, no radius -->
    Tab content...
  </div>
</div>
```

---

## 1.6 CSS Universal — Summary Strip (Type A)

KPI summary untuk halaman list (Tagihan, Pembayaran, POP, dll):

```css
/* ── Summary Strip — flat bar, bukan card grid ── */
.summary-strip {
  display: flex;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  margin-bottom: var(--space-4);
  overflow: hidden;
}

.summary-col {
  flex: 1;
  padding: 12px 20px;
  border-right: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.summary-col:last-child {
  border-right: none;
}

.summary-label {
  font-family: var(--font-ui);
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.summary-value {
  font-family: var(--font-data);
  font-size: 18px;
  font-weight: 600;
  color: var(--color-text-main);
  font-variant-numeric: tabular-nums;
  line-height: 1.2;
}

.summary-value.error   { color: var(--color-error); }
.summary-value.warning { color: var(--color-warning); }
.summary-value.success { color: var(--color-success); }

.summary-sub {
  font-family: var(--font-ui);
  font-size: 11px;
  color: var(--color-text-muted);
}
```

## 1.7 CSS Universal — Page Header

Berlaku untuk SEMUA halaman:

```css
/* ── Page Header — selalu naked, tidak pernah ada card ── */
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 20px;
  padding: 0;                    /* tidak ada padding card */
  background: transparent;       /* tidak ada background */
  border: none;                  /* tidak ada border */
  border-radius: 0;              /* tidak ada radius */
}

.page-header-left {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.page-header-title {
  font-family: var(--font-ui);
  font-size: 22px;
  font-weight: 700;
  color: var(--color-text-main);
  line-height: 1.2;
}

/* Untuk detail page: title inline dengan badge dan ID */
.page-header-title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.page-header-subtitle {
  font-family: var(--font-ui);
  font-size: 13px;
  color: var(--color-text-muted);
  font-weight: 400;
}

.page-header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}
```

## 1.8 CSS Universal — Filter Bar (Type A)

```css
/* ── Filter Bar — naked, tidak pernah dibungkus card ── */
.filter-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: var(--space-4);
  flex-wrap: wrap;
  padding: 0;                    /* tidak ada padding card */
  background: transparent;       /* tidak ada background */
  border: none;                  /* tidak ada border */
}

.filter-search {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 36px;
  padding: 0 12px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);   /* pill shape untuk search */
  flex: 1;
  max-width: 360px;
}

.filter-search:focus-within {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.12);   /* sky-600 @ 12% */
}

.filter-search input {
  border: none;
  background: transparent;
  font-size: 13px;
  color: var(--color-text-main);
  outline: none;
  flex: 1;
  font-family: var(--font-ui);
}

.filter-select {
  height: 36px;
  padding: 0 32px 0 12px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);     /* kotak, bukan pill */
  font-size: 13px;
  color: var(--color-text-main);
  font-family: var(--font-ui);
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,..."); /* chevron */
}

/* Active filter tags */
.filter-active-tags {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: 8px;
}

.filter-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  background: var(--color-primary-soft);
  color: var(--color-primary-hover);
  border-radius: var(--radius-full);
  font-size: 12px;
  font-weight: 500;
}

.filter-tag-remove {
  background: none;
  border: none;
  cursor: pointer;
  color: inherit;
  opacity: 0.7;
  padding: 0;
  line-height: 1;
}
```

---

## 1.9 Prompt Template Universal untuk AI Design Tools

Copy-paste ini sebagai prefix di semua prompt ke Stitch/Claude Design:

```
WHUSNET ISP Admin Panel — Design System Enforcement

Ikuti DESIGN.md di bawah dengan STRICT. Perhatikan khusus aturan ini:

PAGE TYPE RULES:
- Identifikasi dulu tipe halaman: A (list), B (detail), atau C (dashboard)
- Type A card budget = 1 (hanya table panel). Filter bar dan summary strip NAKED.
- Type B card budget = 1 (hanya detail panel). Semua section di dalam = flat rows.
- Type C card budget = bebas (ini dasarnya memang multi-card dashboard).

UNIVERSAL PROHIBITIONS:
1. Page header tidak pernah dibungkus card — selalu naked
2. Filter bar tidak pernah dibungkus card — selalu naked
3. Summary/KPI strip di Type A = flat bar dengan divider, BUKAN 4 card terpisah
4. Double card (card dalam card) = DILARANG di semua tipe
5. Tab content tidak punya card sendiri — ada di dalam panel yang sama dengan tabs
6. Warna warning/error/success hanya pada badge/teks — bukan background section
7. Section titles = 10px Inter uppercase #707881 — bukan heading besar
8. Satu skala radius: 8px (rounded-lg). Tidak ada gradient untuk tombol/logo/badge.
9. Status di baris tabel = badge BACA-SAJA. Tidak ada toggle/switch pengubah
   status layanan. Aksi destruktif lewat dropdown [···] + modal konfirmasi.

SHELL RULES:
- Breadcrumb selalu 3 ruas: Home > {item sidebar} > {halaman aktif}
- Zona tengah topbar = global search. Zona kanan = tema, bantuan, notif, user menu.
- Sub-item sidebar aktif tidak boleh lebih mencolok dari menu induknya

OPERATOR ERGONOMICS (halaman list, §6.10):
- Wajib checkbox seleksi + bulk action bar (naked, muncul saat ada seleksi)
- Select-all header hanya menyapu halaman aktif, bukan seluruh hasil filter
- Tabel wajib bisa dioperasikan tanpa mouse (arrow/Space/Enter/PgUp/PgDn)
- Baris aktif keyboard punya outline sendiri, bukan sekadar hover
- Font sel default 13px (longgar); 11px hanya mode "rapat" opt-in
- Paginasi nyata + Per Page 10/25/50/100, default 25

TYPOGRAPHY (non-negotiable):
- Inter: semua teks UI, navigasi, label, tombol, deskripsi
- JetBrains Mono: CID, IP, MAC, timestamp, currency, invoice number, uptime
- font-mono WAJIB di-remap ke JetBrains Mono di tailwind.config

DARK MODE (non-negotiable):
- darkMode: 'class'. SETIAP permukaan wajib punya pasangan dark:
- Dilarang menghasilkan bg-white / text-slate-900 / border-slate-200 telanjang
- Peta token ada di §18.4. Warna solid (tombol primary, badge) TIDAK dipetakan
- Kelas 'dark' dipasang script inline di <head>, sebelum body

[Paste DESIGN.md content here]
```


## 2. Design Token Reference

Seluruh nilai di dokumen ini mengacu pada token dari Design System Enterprise Grade v3. Tidak ada hardcoded value kecuali ada catatan eksplisit.

### 2.1 Warna Utama (Quick Reference)

| Token | Light Mode Hex | Dark Mode Hex | Penggunaan di WHUSNET |
|---|---:|---:|---|
| `--color-primary` | `#0284C7` | `#38BDF8` | Sky Blue 600 — Tombol utama, active nav, link aksi, active tab |
| `--color-primary-hover` | `#0369A1` | `#0284C7` | Sky Blue 700 — Hover state elemen interaktif utama |
| `--color-primary-soft` | `#F0F9FF` | `#0C4A6E` | Sky Blue 50 — Active sidebar item background, hover row lembut |
| `--color-primary-border`| `#BAE6FD` | `#0369A1` | Sky Blue 200 — Accent border untuk active state / focus |
| `--color-background` | `#F8FAFC` | `#0F172A` | Slate 50/900 — App background / main canvas |
| `--color-surface` | `#FFFFFF` | `#1E293B` | White / Slate 800 — Card, tabel, modal |
| `--color-surface-muted`| `#F1F5F9` | `#334155` | Slate 100/700 — Sidebar, input background, hover card |
| `--color-border` | `#E2E8F0` | `#334155` | Slate 200/700 — Garis pembatas halus (`dark:border-slate-700`) |
| `--color-border-strong`| `#CBD5E1` | `#475569` | Slate 300/600 — Garis pembatas tebal / focus border |
| `--color-text-main` | `#0F172A` | `#F8FAFC` | Teks utama |
| `--color-text-secondary`| `#334155` | `#CBD5E1` | Teks sekunder / body |
| `--color-text-muted` | `#64748B` | `#94A3B8` | Label, helper, metadata |
| `--color-text-disabled`| `#94A3B8` | `#475569` | Teks disabled / placeholder |
| `--color-success` | `#10B981` | `#34D399` | Aktif, Lunas, Online, UP |
| `--color-warning` | `#F59E0B` | `#FBBF24` | Pending, Terlambat, Scheduled |
| `--color-error` | `#EF4444` | `#F87171` | Overdue, Kritis, Gagal, LOS |
| `--color-purple` | `#7C3AED` | `#A78BFA` | Isolir, Admin Suspend |

### 2.2 Tipografi (Quick Reference)

| Font | Digunakan untuk |
|---|---|
| **Inter** | Semua teks UI: heading, label, tombol, deskripsi, navigasi |
| **JetBrains Mono** | ID Pelanggan, Nomor Invoice, Rupiah, IP/MAC, timestamp log |

```css
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap');

:root {
  --font-ui:   'Inter', system-ui, -apple-system, sans-serif;
  --font-data: 'JetBrains Mono', ui-monospace, monospace;
}
```

**JetBrains Mono adalah font mono default aplikasi.** Utility `font-mono` di Tailwind wajib
di-remap ke JetBrains Mono — kalau tidak, nilai teknis jatuh ke monospace bawaan OS
(Consolas di Windows, Menlo di macOS) dan tampilan jadi tidak konsisten antar mesin.

### 2.2.1 Tailwind Config

```js
theme: {
  extend: {
    fontFamily: {
      sans: ['Inter', 'sans-serif'],
      mono: ['JetBrains Mono', 'monospace'],   // wajib — jangan andalkan default OS
    },
    colors: {
      skybrand: {
        50:  '#f0f9ff',  100: '#e0f2fe',  200: '#bae6fd',
        300: '#7dd3fc',  400: '#38bdf8',  500: '#0ea5e9',
        600: '#0284c7',  700: '#0369a1',  800: '#075985',
        900: '#0c4a6e',
      },
    },
  },
}
```

`skybrand-600` = `--color-primary`. `skybrand-700` = hover. `skybrand-50` = soft/active background.
Slate dipakai apa adanya dari palet bawaan Tailwind.

### 2.3 Status Warna Operasional ISP

| Status | Light Mode Teks | Light Mode BG | Dark Mode Teks | Dark Mode BG | Kapan digunakan |
|---|---:|---:|---:|---:|---|
| Aktif / Lunas / Online | `#10B981` | `#ECFDF5` | `#34D399` | `#064E3B` | Pelanggan aktif, tagihan terbayar, node UP |
| Pre-aktif / Provisioning | `#2563EB` | `#EFF6FF` | `#60A5FA` | `#172554` | Onboarding, konfigurasi berlangsung |
| Pending / Terjadwal | `#F59E0B` | `#FFFBEB` | `#FBBF24` | `#451A03` | Menunggu instalasi, jatuh tempo segera |
| Overdue / LOS / Down | `#EF4444` | `#FEF2F2` | `#F87171` | `#450A0A` | Tagihan telat, signal hilang, kritis |
| Isolir / Suspend | `#7C3AED` | `#F5F3FF` | `#A78BFA` | `#2E1065` | Diblokir billing atau admin |
| Terminasi / Churn | `#64748B` | `#F8FAFC` | `#94A3B8` | `#0F172A` | Kontrak selesai, tidak aktif |

---

## 3. Navigasi Sidebar

### 3.1 Struktur Menu

```
WHUSNET                              [logo + collapse toggle]
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
OPERASIONAL
  🏠  Dashboard
  👥  Pelanggan
  📋  Tagihan
  💳  Pembayaran

JARINGAN
  📡  POP & Node
  📦  Paket Layanan

LAPORAN
  📊  Laporan Keuangan
  🔍  Audit Log

PENGATURAN
  👤  Pengguna & RBAC
  ⚙️   Konfigurasi Sistem
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[Avatar] Nama User
         Role · POP Scope
```

> **Catatan:** Gunakan ikon SVG dari Lucide Icons, bukan emoji di implementasi aktual.

### 3.1.1 Aturan Pengelompokan Menu

1. **Label grup tidak boleh sama dengan label item di dalamnya.**
   Grup `MASTER DATA` yang isinya item `Master Data` adalah redundansi —
   pembaca membaca kata yang sama dua kali tanpa dapat informasi tambahan.
   Kalau sebuah grup hanya berisi satu item bernama sama, hapus grupnya dan
   gabungkan item itu ke grup terdekat yang relevan.

2. **Item harus benar-benar milik grupnya.** `Laporan Keuangan` di dalam
   grup `MASTER DATA` salah — laporan adalah keluaran, bukan data master.
   Kalau ragu, tanya: "apakah semua item di grup ini menjawab pertanyaan
   yang sama?" Kalau tidak, pecah grupnya.

3. **Grup minimal 2 item**, kecuali grup itu memang akan bertambah.

### 3.1.2 Semantik Chevron

Satu bentuk = satu arti. Jangan dicampur:

| Ikon | Arti | Dipakai pada |
|---|---|---|
| `chevron-down` (rotate saat terbuka) | Item ini **punya submenu** yang bisa dibuka-tutup | `<button>` collapsible |
| `chevron-right` | Item ini **membuka halaman lain** | `<a>` navigasi biasa |
| *(tanpa chevron)* | Item daun, langsung ke halaman | `<a>` sederhana |

Menempelkan `chevron-right` pada item yang sebenarnya punya submenu (atau
sebaliknya) membuat user menebak-nebak apa yang terjadi saat diklik.

### 3.1.3 Hierarki Active State — Anak Tidak Boleh Melebihi Induk

Ini kesalahan yang paling sering muncul: sub-item aktif diberi pill solid
(`bg-primary text-white shadow`), sementara induknya yang juga aktif hanya
`bg-primary-soft`. Hasilnya **hierarki terbalik** — anak berteriak lebih
keras daripada induknya.

```
❌ SALAH                              ✅ BENAR
▼ Pelanggan      (soft, lembut)      ▼ Pelanggan      (soft)
  ┌──────────────────────────┐         │ List Pelanggan  (soft + border-left)
  │ List Pelanggan           │  ← solid biru, paling mencolok
  └──────────────────────────┘
```

Bobot visual harus menurun ke bawah, bukan naik:

| Level | Active state |
|---|---|
| Item induk | `bg-primary-soft` + `text-primary-hover` + `font-semibold` |
| Sub-item | `bg-primary-soft` + `text-primary-hover` + `font-semibold` + `border-left: 2px solid primary` |

Sub-item dibedakan lewat **garis kiri tipis**, bukan lewat background yang
lebih pekat. Tambahkan `aria-current="page"` pada item halaman aktif.

### 3.2 CSS Sidebar Item

```css
.sidebar {
  width: 256px;
  background: var(--color-surface);
  border-right: 1px solid var(--color-border);
  height: 100vh;
  position: sticky;
  top: 0;
  overflow-y: auto;
  scrollbar-width: thin;
  transition: width var(--duration-slow) var(--ease-standard);
}

.sidebar-group-label {
  padding: 16px 12px 6px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--color-text-muted);
  font-family: var(--font-ui);
}

.sidebar-item {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 40px;
  padding: 8px 12px;
  margin: 1px 8px;
  border-radius: var(--radius-sm);
  color: var(--color-text-secondary);
  font-family: var(--font-ui);
  font-size: var(--text-sm);
  font-weight: 400;
  cursor: pointer;
  text-decoration: none;
  transition: background var(--duration-fast) var(--ease-standard),
              color var(--duration-fast) var(--ease-standard);
}

.sidebar-item:hover {
  background: var(--color-surface-muted);
  color: var(--color-text-main);
}

.sidebar-item.active {
  background: var(--color-primary-soft);
  color: var(--color-primary-hover);
  font-weight: 600;
}

.sidebar-item .icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  stroke-width: 1.75;
}

/* Collapsed sidebar — icon only */
.sidebar.collapsed {
  width: 72px;
}
.sidebar.collapsed .sidebar-item span {
  display: none;
}
.sidebar.collapsed .sidebar-group-label {
  display: none;
}
.sidebar.collapsed .sidebar-item {
  justify-content: center;
  padding: 10px;
}
```

### 3.3 RBAC Visibility Rules

- Item menu yang tidak diizinkan untuk role user **disembunyikan** (`display: none`), bukan disabled.
- Super Admin melihat semua menu.
- Admin POP Cabang hanya melihat data dalam scope POP-nya.
- Finance hanya melihat menu Tagihan, Pembayaran, dan Laporan Keuangan.
- Teknisi hanya melihat POP & Node, Tiket, dan Pelanggan (view-only).

---

## 4. Topbar

### 4.1 Anatomi Topbar

```
[≡]  Home › Pelanggan › List Pelanggan  [🔍 Cari pelanggan, invoice, ID… /]  [🌙] [?] [🔔³] │ [SU Super Admin ▾]
└── kiri: toggle + breadcrumb ──┘       └──── tengah: global search ────┘    └───── kanan: aksi ─────┘
```

Tiga zona, urutannya tidak boleh ditukar:

| Zona | Isi | Catatan |
|---|---|---|
| Kiri | Toggle sidebar (mobile) + **breadcrumb** | Breadcrumb di topbar, bukan di page header — hemat ruang vertikal |
| Tengah | **Global search** | Wajib ada. Lihat §4.3 |
| Kanan | **Toggle tema** + **bantuan** + notifikasi + separator + **user menu** | Urutan tetap: tema → `?` → bell → garis → avatar |

Zona kanan urutannya dari yang paling sering dipakai ke paling jarang:
toggle tema (ikut cahaya ruangan, bisa beberapa kali sehari), bantuan
(sering di minggu-minggu awal, lalu jarang), notifikasi (dicek berkala),
user menu (jarang). Separator tipis memisahkan aksi cepat dari identitas
akun.

Ikon bantuan `?` di sini **tidak menggantikan** item "Bantuan & Pintasan"
di dalam user menu — keduanya wajib ada. Lihat §6.10.5.

**Global search wajib ada di setiap halaman.** Ini alat yang paling sering
dipakai di sistem dengan ribuan pelanggan lintas POP. Jangan diisi elemen
dekoratif (jam, tanggal, badge status) — itu merampas ruang paling berharga
di layar untuk sesuatu yang tidak bisa diklik.

Shortcut `/` memfokuskan global search dari mana saja, kecuali saat kursor
sedang berada di input lain.

---

### 4.1.1 Breadcrumb — Pola `Home › Menu › Sub Menu`

Breadcrumb selalu **tepat tiga ruas**, konsisten di seluruh aplikasi:

```
Home  ›  {Menu}  ›  {Sub Menu}
```

| Ruas | Isi | Interaksi |
|---|---|---|
| `Home` | Ikon rumah + teks "Home" | Link ke Dashboard |
| `{Menu}` | **Nama item induk di sidebar** | Link ke landing menu tersebut |
| `{Sub Menu}` | Halaman aktif | **Bukan link**, `aria-current="page"`, warna primary |

Aturan:

1. **Ruas tengah = label item sidebar, bukan label grup sidebar.**
   Sidebar punya grup (`OPERASIONAL`, `LAPORAN`) dan item (`Pelanggan`,
   `Tagihan`). Breadcrumb memakai **item**, karena itu yang bisa diklik
   dan punya halaman. Menulis `Home › Operasional › List Pelanggan` salah —
   "Operasional" hanya label kategori, tidak ada halamannya.
2. **Halaman tanpa sub-menu tetap tiga ruas** dengan ruas ketiga = nama
   halaman itu sendiri (`Home › Tagihan › Daftar Tagihan`).
3. **Halaman detail** memakai identitas record di ruas ketiga
   (`Home › Pelanggan › Budi Santoso`).
4. Separator memakai ikon `chevron-right` 10px `slate-300`, bukan karakter
   `/` atau `>` literal.
5. Ruas panjang di-`truncate`, bukan dibungkus ke baris kedua — topbar
   tingginya tetap 64px.

```html
<nav aria-label="Breadcrumb" class="flex items-center gap-1.5 text-sm text-slate-500">
  <a href="/dashboard" class="flex items-center gap-1.5 hover:text-slate-700">
    <x-lucide-house class="w-4 h-4 text-slate-400" />
    <span class="hidden sm:inline">Home</span>
  </a>
  <x-lucide-chevron-right class="w-2.5 h-2.5 text-slate-300" />
  <a href="/customers" class="hover:text-slate-700 truncate">Pelanggan</a>
  <x-lucide-chevron-right class="w-2.5 h-2.5 text-slate-300" />
  <span aria-current="page" class="font-semibold text-skybrand-600 truncate">List Pelanggan</span>
</nav>
```

---

### 4.1.2 User Menu (Zona Kanan)

Avatar bukan sekadar hiasan — ini pintu masuk ke profil, preferensi akun,
dan keluar sesi.

> **Toggle tema tidak di sini.** Ganti terang/gelap adalah aksi cepat yang
> mengikuti kondisi cahaya ruangan, bukan pengaturan akun — tempatnya
> tombol ikon tersendiri di topbar. Lihat §18.1.

```
[SU] Super Admin        ▾      ← avatar + nama + "Owner · Semua POP"
     Owner · Semua POP
     ┌────────────────────────┐
     │ Super Admin            │
     │ admin@whusnet.id       │
     ├────────────────────────┤
     │ 👤 Profil Saya         │
     │ ⚙  Preferensi          │
     ├────────────────────────┤
     │ ⏻  Keluar   (danger)   │
     └────────────────────────┘
```

Baris kedua avatar menampilkan **role + POP scope efektif**. Untuk sistem
multi-cabang, user harus bisa melihat sekali lihat sedang bekerja di scope
mana — salah scope berarti salah data.

**Hanya satu menu boleh terbuka pada satu waktu.** User menu dan row action
menu saling menutup; keduanya tutup saat klik di luar atau `Esc`.

### 4.1.3 Notifikasi

Bell menampilkan **angka** jumlah belum dibaca, bukan titik polos. Titik
memberi tahu "ada sesuatu" tapi tidak "seberapa mendesak" — untuk NOC yang
memantau gangguan, selisih antara 1 dan 23 menentukan prioritas.

### 4.2 Spesifikasi

```css
.topbar {
  height: 64px;
  background: var(--color-surface);
  border-bottom: 1px solid var(--color-border);
  padding: 0 24px;
  display: flex;
  align-items: center;
  gap: 16px;
  position: sticky;
  top: 0;
  z-index: var(--z-sticky);
}

.topbar-search {
  flex: 1;
  max-width: 480px;
}

.topbar-search input {
  width: 100%;
  height: 36px;
  padding: 0 12px 0 36px; /* icon prefix */
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);
  background: var(--color-background);
  font-size: var(--text-sm);
  font-family: var(--font-ui);
  color: var(--color-text-main);
  transition: border-color var(--duration-normal);
}

.topbar-search input:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
  background: var(--color-surface);
}
```

### 4.3 Global Search Scope

Global search menjangkau:
- Nama pelanggan dan nomor HP
- CID (Customer ID) — format `C00XXXXXX`
- Nomor Invoice — format `INV-YYYY-MM-NNNN`
- Nomor REG — format `REG-YYYYMMDD-NNNN`
- Nomor Tiket — format `TKT-YYYY-NNNN`
- IP Address dan MAC Address pelanggan

### 4.4 Notification Bell

Notifikasi real-time via Laravel Reverb:
- 🔴 Badge merah untuk notifikasi belum dibaca
- Klik buka dropdown notifikasi (max tampil 10 terbaru)
- Jenis notifikasi: pembayaran masuk, tagihan jatuh tempo, gangguan jaringan baru, tiket baru

---

## 5. Halaman Dashboard

### 5.1 Layout Dashboard

```
[Alert Banner — jika ada gangguan aktif]

Page Header:
  Dashboard Operasional
  Ringkasan kondisi bisnis dan jaringan per hari ini · Rabu, 24 Jun 2026

──────────────────────────────────────────────────────────────────

Metric Cards (4 kolom)
┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│ Total        │ │ Tagihan      │ │ Pembayaran   │ │ Tiket Aktif  │
│ Pelanggan    │ │ Belum Dibayar│ │ Hari Ini     │ │              │
│ Aktif        │ │              │ │              │ │              │
│ 1.284        │ │ 87           │ │ Rp 4.250.000 │ │ 12           │
│ ↑ 8.2% bulan │ │ Rp 21.7 jt   │ │ 23 transaksi │ │ 3 SLA breach │
└──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘

──────────────────────────────────────────────────────────────────

Operational Status Cards (4 kolom)
┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│ Critical     │ │ Pelanggan    │ │ Overdue      │ │ Node Down    │
│ Alarms  🔴   │ │ Isolir  🟣   │ │ >30 Hari  🔴 │ │ Hari Ini     │
│ 7            │ │ 34           │ │ 15 pelanggan │ │ 0            │
│ 3 POP OLT    │ │ Auto-suspend │ │ Rp 6.2 jt    │ │ Semua UP ✅  │
│ [Lihat]      │ │ [Lihat]      │ │ [Lihat]      │ │              │
└──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘

──────────────────────────────────────────────────────────────────

Charts & Insights (2 kolom: 2fr + 1fr)
┌────────────────────────────────────┐ ┌────────────────────┐
│ Tren Pembayaran 6 Bulan Terakhir   │ │ Paket Terlaris     │
│ [Line Chart]                       │ │ Fiber 50Mbps  42%  │
│                                    │ │ Fiber 30Mbps  28%  │
│                                    │ │ Fiber 100Mbps 18%  │
│                                    │ │ Business      12%  │
└────────────────────────────────────┘ └────────────────────┘

──────────────────────────────────────────────────────────────────

Recent Activity (2 kolom: 1fr + 1fr)
┌────────────────────────────────────┐ ┌────────────────────┐
│ Pembayaran Terbaru                 │ │ Aktivitas Terbaru  │
│ [Table: 5 baris terakhir]          │ │ [Timeline feed]    │
└────────────────────────────────────┘ └────────────────────┘
```

### 5.2 Metric Card Component

```css
.metric-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  position: relative;
  overflow: hidden;
}

/* Accent left border untuk operational status cards */
.metric-card.status-error {
  border-left: 3px solid var(--color-error);
}
.metric-card.status-warning {
  border-left: 3px solid var(--color-warning);
}
.metric-card.status-success {
  border-left: 3px solid var(--color-success);
}

.metric-card-label {
  font-family: var(--font-ui);
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--color-text-muted);
  display: flex;
  align-items: center;
  gap: 6px;
}

.metric-card-value {
  font-family: var(--font-data);
  font-size: var(--text-2xl);
  font-weight: 600;
  color: var(--color-text-main);
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
}

.metric-card-delta {
  font-family: var(--font-ui);
  font-size: var(--text-xs);
  display: flex;
  align-items: center;
  gap: 4px;
}
.metric-card-delta.up   { color: var(--color-success); }
.metric-card-delta.down { color: var(--color-error); }

.metric-card-action {
  margin-top: 4px;
}
```

---

## 6. Halaman Pelanggan

### 6.1 Daftar Pelanggan

Halaman **Type A** — card budget = 1 (hanya table panel).

```
Page Header — NAKED:
  Pelanggan
  Kelola data pelanggan, layanan aktif, status billing, dan dokumen.
                                      [Import Excel]  [+ Tambah Pelanggan]

Summary Strip — FLAT BAR, 1 border membungkus semua kolom:
  ┌────────────┬────────────┬────────────┬─────────────────┐
  │ TOTAL      │ AKTIF      │ ISOLIR     │ LEWAT TEMPO     │
  │ 1.488      │ 1.482      │ 6          │ 87              │
  │ +12,4% bln │ 99,6% up   │ blokir     │ Rp 21.300.000   │
  └────────────┴────────────┴────────────┴─────────────────┘

Filter Bar — NAKED:
  [ Semua | Aktif | Isolir ]              [🔍 Cari nama, CID, HP, desa…]
  [POP ▾] [Desa ▾] [Paket ▾] [Berkas ▾]                 [Reset Filter]

┌─ TABLE PANEL (1 card) ────────────────────────────────────────────────────┐
│ ID PELANGGAN │ NAMA        │ POP·DESA   │ … │ JATUH TEMPO │ TAGIHAN │STATUS│AKSI│
├──────────────┼─────────────┼────────────┼───┼─────────────┼─────────┼──────┼────┤
│ C00100042    │ Budi Santoso│ Jetis·Winong│  │ 10 Agu 2026 │Rp 165.000│●Aktif│[···]│
│              │             │            │   │             │ Belum   │      │    │
│ C00100043    │ Siti Rahayu │ Jetis·Jetis │  │ 10 Jul 2026 │Rp 198.000│●Aktif│[···]│
│              │             │            │   │ (merah)     │Lewat tempo│     │    │
│ C00100044    │ Ahmad Fauzi │ Sandya·Kauman│ │ 10 Jun 2026 │Rp 220.000│●Isolir│[···]│
├──────────────┴─────────────┴────────────┴───┴─────────────┴─────────┴──────┴────┤
│ Menampilkan 1–25 dari 1.488 pelanggan   [← Prev] [1][2][3]…[52] [Next →]        │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### 6.2 Spesifikasi Kolom Tabel Pelanggan

| Kolom | Font | Alignment | Catatan |
|---|---|---|---|
| ID Pelanggan (CID) | JetBrains Mono | Left | Link ke detail, warna primary |
| Nama Lengkap | Inter | Left | Avatar inisial + nama, truncate |
| POP · Desa | Inter | Left | **Satu kolom**, POP jadi chip + desa teks muted |
| Paket Internet | JetBrains Mono | Left | Kode paket + nama |
| No. Telepon | JetBrains Mono | Left | Link `wa.me`, ikon WhatsApp |
| Jatuh Tempo | JetBrains Mono | Left | **Merah + semibold** jika lewat tempo |
| Tagihan | JetBrains Mono | Right | Format Rp + sub-teks status bayar |
| Berkas | JetBrains Mono | Center | Persentase; hijau ≥80%, amber di bawahnya |
| Status | Inter (badge) | Center | **Baca-saja.** Lihat §6.2.2 |
| Aksi | — | Center | Tombol `[···]` → dropdown, lihat §6.3 |

#### 6.2.1 Kolom yang Dilarang di Halaman List

**❌ Nomor urut baris.** Angka 1–10 yang di-reset tiap halaman adalah
informasi palsu: baris "3" di halaman 2 bukan pelanggan ketiga. Tidak ada
yang bisa dilakukan dengan angka itu — ia hanya memakan kolom. Identitas
baris sudah dipegang CID.

**❌ Dot-meter untuk persentase.** Lima titik untuk mewakili kelengkapan
berkas terlihat rapi tapi tidak informatif — `round(83% × 5)` dan
`round(75% × 5)` sama-sama menghasilkan 4 titik, jadi dua nilai berbeda
tampil identik. Kalau angkanya sudah ditampilkan, dot-nya redundan; kalau
belum, dot-nya menyesatkan. Tulis persentasenya.

**❌ Kolom yang memecah satu konsep jadi dua.** POP dan Desa selalu dibaca
bersama (desa berada di bawah POP) — gabungkan jadi satu kolom.

#### 6.2.2 Data Billing Wajib Ada di List Pelanggan

Ini sistem billing. Pertanyaan pertama yang dibawa admin ke halaman daftar
pelanggan adalah **"siapa yang nunggak?"**. Halaman yang tidak bisa
menjawabnya gagal pada fungsi utamanya, seberapa pun rapi tampilannya.

Kolom `Jatuh Tempo` dan `Tagihan` **wajib** ada. Status tagihan diturunkan,
bukan disimpan sebagai kolom terpisah:

```
paid == true            → lunas    (hijau)
due  <  hari ini        → overdue  (merah, semibold)
selain itu              → belum    (netral)
```

Summary strip halaman ini mengikuti prioritas yang sama:
`Total · Aktif · Isolir · Lewat Tempo (jumlah + nominal)`.
Metrik "rata-rata kelengkapan berkas" bukan informasi yang dipakai untuk
mengambil keputusan harian — jangan menempati salah satu dari empat slot itu.

### 6.3 Action Dropdown per Row

```
┌────────────────────────┐
│ Budi Santoso           │  ← header: nama + CID, konfirmasi konteks
│ C00100042              │
├────────────────────────┤
│ 👁  Lihat Detail        │
│ ✎  Edit Data           │
├────────────────────────┤
│ ⎙  Cetak Tagihan       │
│ ⌾  Kirim WhatsApp      │
├────────────────────────┤
│ ⊘  Isolir Layanan      │  ← warning color, WAJIB modal konfirmasi
│ 🗑  Terminasi           │  ← danger color, WAJIB modal konfirmasi
└────────────────────────┘
```

Aturan teknis:

1. **Header menu menampilkan nama + CID.** Menu melayang jauh dari barisnya;
   tanpa header, user bisa salah menyangka sedang mengaksi baris lain.
2. **Satu elemen menu dipakai ulang untuk semua baris**, diposisikan
   `position: fixed` lewat JS. Dropdown `absolute` di dalam `<td>` akan
   terpotong oleh `overflow-x-auto` pada wrapper tabel.
3. Menu **membalik ke atas** kalau tidak muat di bawah viewport.
4. Tutup saat: klik di luar, `Esc`, atau membuka menu lain.

---

### 6.3.1 Perubahan Status Layanan — Larangan Kontrol Sekali Klik

**Status layanan di tabel adalah badge baca-saja. Titik.**

Dilarang menaruh toggle switch, checkbox, atau kontrol apa pun di baris
tabel yang langsung mengubah status layanan pelanggan.

```html
<!-- ❌ SALAH — memutus internet pelanggan dengan satu klik tak sengaja -->
<td>
  <button onclick="toggleConnection(i)" class="... rounded-full ...">
    <span class="... translate-x-4"></span>
  </button>
</td>

<!-- ✅ BENAR — badge baca-saja; perubahan lewat menu aksi + konfirmasi -->
<td class="text-center">
  <span class="badge badge-success"><span class="badge-dot"></span> Aktif</span>
</td>
```

Alasannya bukan estetika:

1. **Tidak ada konfirmasi.** Toggle dirancang untuk preferensi yang murah
   dibatalkan (dark mode, notifikasi), bukan untuk tindakan yang memutus
   layanan orang yang membayar.
2. **Tidak ada alasan tercatat.** Isolir wajib punya jejak: siapa, kapan,
   kenapa. Toggle tidak menyediakan tempat untuk itu.
3. **Tidak ada gerbang permission.** Kontrol yang tampil di setiap baris
   sulit di-gate per-role. Teknisi tidak boleh mengeksekusi aksi billing —
   di dalam dropdown, item-nya bisa disembunyikan per permission.
4. **Status layanan bukan boolean.** Ia mengikuti transisi status pelanggan
   yang punya aturan sendiri (aktif → isolir → terminasi, tidak sembarang
   arah). Flag `true/false` memodelkannya secara salah sejak awal.

Aksi yang membalik keadaan ke arah yang **menguntungkan** pelanggan
(buka isolir, aktifkan kembali) boleh tanpa modal — cukup toast. Yang
merugikan wajib modal.

### 6.4 Halaman Detail Pelanggan

#### Filosofi Layout: Clean Canvas

Halaman ini menggunakan pendekatan **"borderless document"** — bukan kumpulan card yang berjajar. Prinsipnya:

- **Satu permukaan putih bersih** sebagai kanvas utama. Tidak ada card-dalam-card atau border box di setiap blok informasi.
- **Divider horizontal tipis** (`1px solid var(--color-border)`) sebagai satu-satunya pembatas antar section.
- **Card hanya digunakan untuk 2 hal**: (1) Metric Strip layanan aktif di atas tab, dan (2) Payment History rows — karena keduanya adalah "benda" yang perlu diklik/diaksi.
- **Section title** pakai uppercase tracking kecil, muted — bukan heading besar.
- **Whitespace adalah pembatas**, bukan garis.

#### Layout Wireframe

```
┌─────────────────────────────────────────────────────────────────────────┐
│ TOPBAR                                                                  │
├──────────────┬──────────────────────────────────────────────────────────┤
│              │ Breadcrumb: Dashboard › Pelanggan › Budi Santoso          │
│   SIDEBAR    ├──────────────────────────────────────────────────────────┤
│              │                                                          │
│              │  Budi Santoso     ● AKTIF     CID: C00100042             │
│              │                              [✎ Edit]  [⎙ Cetak]        │
│              ├──────────────────────────────────────────────────────────┤
│              │                                                          │
│              │  ┌────────┬──────────┬──────────┬──────────┬──────────┐  │
│              │  │ PAKET  │IP ADDRESS│ SIGNAL   │ UPTIME   │ BILLING  │  │
│              │  │Fiber   │192.168.  │ -22 dBm  │ 12d      │Rp 250k   │  │
│              │  │50M     │1.42      │ ▪ NORMAL │ 04:22:11 │● UNPAID  │  │
│              │  └────────┴──────────┴──────────┴──────────┴──────────┘  │
│              │  (Metric strip — satu-satunya card di halaman ini)       │
│              │                                                          │
│              │  [Overview] [Layanan] [Tagihan] [Pembayaran] [Tiket]... │
│              │  ─────────────────────────────────────────────────────  │
│              │                                                          │
│              │  TAB: OVERVIEW                                           │
│              │                                                          │
│              │  ┌─────────────────────────────────────────────────┐    │
│              │  │                                   │              │    │
│              │  │  INFORMASI PELANGGAN  (col 5/12)  │  DETAIL      │    │
│              │  │                                   │  TEKNIS      │    │
│              │  │  [label-value rows, no borders]   │  (col 7/12)  │    │
│              │  │                                   │              │    │
│              │  │  ─────────────────────────────    │  ─────────── │    │
│              │  │                                   │              │    │
│              │  │  TIMELINE REGISTRASI              │  RIWAYAT     │    │
│              │  │  [horizontal stepper]             │  PEMBAYARAN  │    │
│              │  │                                   │  [rows only] │    │
│              │  └─────────────────────────────────────────────────┘    │
│              │                                                          │
│              │  ───────────────────────────────────────────────────    │
│              │                                                          │
│              │  ∿ REALTIME TRAFFIC (24H)          ● DOWNLOAD ○ UPLOAD  │
│              │  [Bar chart, full width, no card border]                 │
│              │                                                          │
└──────────────┴──────────────────────────────────────────────────────────┘
```

#### Page Header

```
Breadcrumb (14px, muted, dengan separator ›):
  Dashboard  ›  Pelanggan  ›  Budi Santoso

Row utama (space-between):
  LEFT:
    h1 "Budi Santoso"           — Inter 24px 700, color-text-main
    badge ● AKTIF               — inline, ml-3
    span "CID: C00100042"       — JetBrains Mono 13px, color-text-muted, ml-4

  RIGHT:
    [✎ Edit]   — btn-secondary
    [⎙ Cetak]  — btn-primary
```

#### Metric Strip (Satu-satunya Card di Halaman Ini)

Metric strip adalah satu card tunggal yang dibagi secara horizontal menjadi kolom-kolom, dengan divider vertikal tipis. Bukan 5 card terpisah.

```
┌─────────────────────────────────────────────────────────────────────┐
│  PAKET              │ IP ADDRESS       │ SIGNAL    │ UPTIME │BILLING │
│  Fiber Home 50M     │ 192.168.1.42     │ -22 dBm   │ 12d    │Rp 250k │
│                     │                  │ ▸ Normal  │04:22:11│● UNPAID│
└─────────────────────────────────────────────────────────────────────┘
```

Aturan metric strip:
- Background: `var(--color-surface)`, border: `1px solid var(--color-border)`, radius: `var(--radius-md)`
- Setiap kolom dipisah divider vertikal `1px solid var(--color-border)`
- Label: uppercase `11px`, `font-weight: 600`, `color-text-muted`, letter-spacing `0.07em`
- Value: sesuai tipe data (lihat §2.2)
- Kolom BILLING: jika UNPAID, value `Rp 250.000` warna `--color-warning`, badge `UNPAID` di bawahnya

#### Tab Navigation

```css
.detail-tabs {
  display: flex;
  gap: 0;
  border-bottom: 1px solid var(--color-border);
  margin-bottom: 0; /* tidak ada space di bawah tab — section langsung mulai */
}

.detail-tab {
  font-family: var(--font-ui);
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--color-text-muted);
  padding: 10px 16px;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  transition: color var(--duration-fast), border-color var(--duration-fast);
  white-space: nowrap;
}

.detail-tab:hover {
  color: var(--color-text-main);
}

.detail-tab.active {
  color: var(--color-primary);
  border-bottom-color: var(--color-primary);
  font-weight: 600;
}
```

#### TAB OVERVIEW — Layout 5/12 + 7/12

Dua kolom tanpa border card. Konten dibaca sebagai satu dokumen.

```
Kolom Kiri (5/12):
  INFORMASI PELANGGAN            ← section-title (uppercase, muted, 11px)

  Nama Lengkap                   Budi Santoso
  ─────────────────────────────────────────────
  Kontak                         +62 812-3456-7890
                                 budi.santoso@email.com
  ─────────────────────────────────────────────
  Lokasi                         Jl. Raya No. 12, Ponorogo, Jawa Timur
                                 🏢 POP Madiun              ← badge kecil
  ─────────────────────────────────────────────
  Identitas                      REG-MN-0042                ← link color
                                 ⚠ KTP & Lokasi GPS belum diverifikasi
  ─────────────────────────────────────────────


  (space 32px)


  TIMELINE REGISTRASI            ← section-title

  ● 12 Jan   ● 13 Jan   ● 15 Jan   ◉ 15 Jan
  Registrasi  Survey     Instalasi   Aktif
  (horizontal stepper — lihat §13.5 variant horizontal)


Kolom Kanan (7/12), dibatasi border-left tipis:
  DETAIL TEKNIS                  ← section-title          [Restart Session →]

  MAC Address                    A4:C3:F0:B2:11:22
  Perangkat OLT                  OLT-Madiun-01 (Port 4/12)
  ─────────────────────────────────────────────


  (space 32px)


  RIWAYAT PEMBAYARAN             ← section-title          [Invoice History →]

  ✓  Mei 2026                                  Rp 250.000
  ─────────────────────────────────────────────
  ✓  April 2026                                Rp 250.000
  ─────────────────────────────────────────────
  ✓  Maret 2026                                Rp 250.000
  ─────────────────────────────────────────────
  (Payment rows tidak punya border-box/card — hanya divider)
```

#### TAB OVERVIEW — Section Bawah (Full Width)

Langsung di bawah dua kolom, dipisah divider horizontal:

```
─────────────────────────────────────────────────────────────────

∿ REALTIME TRAFFIC (24H)                    ● Download   ○ Upload

[Bar chart — full content width, tanpa card wrapper, background transparan]
```

### 6.5 CSS Detail Pelanggan — Clean Canvas

```css
/* ── Layout Utama ── */
.customer-detail-body {
  background: var(--color-background);
  padding: var(--space-6) var(--space-8);
  max-width: 1180px;
}

/* ── Page Header ── */
.detail-page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: var(--space-5);
  /* tidak ada border-bottom di sini — metric strip langsung mengikuti */
}

.detail-page-header-left {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.detail-page-title {
  font-family: var(--font-ui);
  font-size: var(--text-2xl);
  font-weight: 700;
  color: var(--color-text-main);
  line-height: 1.2;
}

.detail-page-cid {
  font-family: var(--font-data);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  margin-left: var(--space-2);
}

/* ── Metric Strip ── */
.metric-strip {
  display: flex;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  margin-bottom: var(--space-5);
  overflow: hidden;
}

.metric-strip-col {
  flex: 1;
  padding: 14px 20px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  border-right: 1px solid var(--color-border);
}

.metric-strip-col:last-child {
  border-right: none;
}

.metric-strip-label {
  font-family: var(--font-ui);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.metric-strip-value {
  font-family: var(--font-data);
  font-size: var(--text-base);
  font-weight: 600;
  color: var(--color-text-main);
  font-variant-numeric: tabular-nums;
}

.metric-strip-value.is-ip    { color: var(--color-primary); }
.metric-strip-value.is-warn  { color: var(--color-warning); }
.metric-strip-value.is-ok    { color: var(--color-success); }
.metric-strip-value.is-error { color: var(--color-error); }

.metric-strip-sub {
  font-family: var(--font-ui);
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  display: flex;
  align-items: center;
  gap: 4px;
}

/* ── Tab Body ── */
.detail-tab-body {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-top: none; /* tab strip sudah memberi border atas */
  border-radius: 0 0 var(--radius-md) var(--radius-md);
  padding: var(--space-6) var(--space-7);
}

/* ── Two-column Grid ── */
.detail-overview-grid {
  display: grid;
  grid-template-columns: 5fr 7fr;
  gap: 0;
  min-height: 320px;
}

.detail-col-left {
  padding-right: var(--space-8);
  display: flex;
  flex-direction: column;
  gap: var(--space-8);
}

.detail-col-right {
  padding-left: var(--space-8);
  border-left: 1px solid var(--color-border);
  display: flex;
  flex-direction: column;
  gap: var(--space-8);
}

/* ── Section Title ── */
.detail-section-title {
  font-family: var(--font-ui);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--color-text-muted);
  margin-bottom: var(--space-4);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.detail-section-title a {
  font-size: var(--text-xs);
  font-weight: 500;
  letter-spacing: 0;
  text-transform: none;
  color: var(--color-primary);
  text-decoration: none;
}

.detail-section-title a:hover {
  text-decoration: underline;
}

/* ── Info Rows (tanpa card border) ── */
.detail-info-row {
  display: flex;
  align-items: flex-start;
  padding: var(--space-3) 0;
  border-bottom: 1px solid var(--color-border);
  gap: var(--space-4);
}

.detail-info-row:last-child {
  border-bottom: none;
}

.detail-info-label {
  font-family: var(--font-ui);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  min-width: 130px;
  flex-shrink: 0;
  padding-top: 1px; /* optical align dengan value multi-line */
}

.detail-info-value {
  font-family: var(--font-ui);
  font-size: var(--text-sm);
  color: var(--color-text-main);
  flex: 1;
  line-height: 1.5;
}

/* Nilai teknis gunakan JetBrains Mono */
.detail-info-value.is-data {
  font-family: var(--font-data);
  font-size: var(--text-xs);
  color: var(--color-text-secondary);
}

/* Link-style untuk REG ID */
.detail-info-value.is-link {
  color: var(--color-primary);
  cursor: pointer;
  font-family: var(--font-data);
  font-size: var(--text-sm);
}

/* Warning inline di bawah value */
.detail-info-warning {
  margin-top: var(--space-1);
  font-family: var(--font-ui);
  font-size: var(--text-xs);
  color: var(--color-warning);
  display: flex;
  align-items: center;
  gap: 4px;
}

/* ── Payment History Rows ── */
/* Bukan card, hanya row dengan divider */
.payment-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-3) 0;
  border-bottom: 1px solid var(--color-border);
}

.payment-row:last-child {
  border-bottom: none;
}

.payment-row-label {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-family: var(--font-ui);
  font-size: var(--text-sm);
  color: var(--color-text-main);
}

.payment-row-label .icon-check {
  color: var(--color-success);
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

.payment-row-amount {
  font-family: var(--font-data);
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--color-success);
  font-variant-numeric: tabular-nums;
}

/* ── Full-width Sections (di bawah 2-col grid) ── */
.detail-section-divider {
  height: 1px;
  background: var(--color-border);
  margin: var(--space-7) 0;
}

.detail-section-full {
  /* tidak ada background, tidak ada border — content langsung di kanvas */
}

/* ── Traffic Chart Area ── */
.traffic-chart-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-4);
}

.traffic-chart-title {
  font-family: var(--font-ui);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--color-text-muted);
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.traffic-chart-legend {
  display: flex;
  gap: var(--space-4);
  align-items: center;
}

.traffic-legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-family: var(--font-ui);
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.traffic-legend-dot {
  width: 8px;
  height: 8px;
  border-radius: 2px;
}
.traffic-legend-dot.download { background: var(--color-primary); }
.traffic-legend-dot.upload   { background: #94A3B8; }

/* Chart container — no card wrapper */
.traffic-chart-canvas {
  width: 100%;
  height: 160px;
  /* Chart library (Chart.js / ApexCharts) render di sini */
}
```

#### Aturan Penggunaan Card di Halaman Detail

| Elemen | Menggunakan Card (border+bg)? | Alasan |
|---|:---:|---|
| Metric Strip (paket, IP, signal, uptime, billing) | ✅ Ya | Satu unit interaktif, bisa diklik per kolom untuk detail |
| Informasi Pelanggan | ❌ Tidak | Konten dokumen murni, cukup row + divider |
| Detail Teknis | ❌ Tidak | Sama seperti di atas |
| Timeline Registrasi | ❌ Tidak | Stepper horizontal tanpa border |
| Riwayat Pembayaran | ❌ Tidak | Row list dengan divider, link ke invoice history |
| Realtime Traffic Chart | ❌ Tidak | Canvas langsung di background page |
| Modal Konfirmasi | ✅ Ya | Floating di atas page, butuh batas visual jelas |
| Alert Banner | ✅ Ya (colored) | Butuh perhatian langsung, perlu batas tegas |

#### HTML Blueprint — TAB OVERVIEW

```html
{{-- Tab Content: Overview --}}
<div class="detail-tab-body">

  {{-- 2-Column Grid --}}
  <div class="detail-overview-grid">

    {{-- Kolom Kiri --}}
    <div class="detail-col-left">

      {{-- Section: Informasi Pelanggan --}}
      <div>
        <div class="detail-section-title">
          <span>
            <x-lucide-user class="w-3.5 h-3.5 inline mr-1" />
            Informasi Pelanggan
          </span>
        </div>

        <div class="detail-info-row">
          <span class="detail-info-label">Nama Lengkap</span>
          <span class="detail-info-value">Budi Santoso</span>
        </div>

        <div class="detail-info-row">
          <span class="detail-info-label">Kontak</span>
          <span class="detail-info-value">
            +62 812-3456-7890<br>
            <span class="text-muted text-xs">budi.santoso@email.com</span>
          </span>
        </div>

        <div class="detail-info-row">
          <span class="detail-info-label">Lokasi</span>
          <span class="detail-info-value">
            Jl. Raya No. 12, Ponorogo, Jawa Timur
            <span class="badge badge-info badge-sm mt-1 block w-fit">
              POP Madiun
            </span>
          </span>
        </div>

        <div class="detail-info-row">
          <span class="detail-info-label">Identitas</span>
          <span class="detail-info-value">
            <span class="detail-info-value is-link">REG-MN-0042</span>
            <span class="detail-info-warning">
              <x-lucide-alert-circle class="w-3.5 h-3.5" />
              KTP & Lokasi GPS belum diverifikasi
            </span>
          </span>
        </div>
      </div>

      {{-- Section: Timeline Registrasi --}}
      <div>
        <div class="detail-section-title">
          <span>Timeline Registrasi</span>
        </div>
        {{-- Gunakan horizontal stepper dari §13.5 --}}
        <x-timeline-horizontal :steps="$registrationSteps" />
      </div>

    </div>{{-- /col-left --}}

    {{-- Kolom Kanan --}}
    <div class="detail-col-right">

      {{-- Section: Detail Teknis --}}
      <div>
        <div class="detail-section-title">
          <span>
            <x-lucide-cpu class="w-3.5 h-3.5 inline mr-1" />
            Detail Teknis
          </span>
          <button class="btn-ghost btn-xs">Restart Session</button>
        </div>

        <div class="detail-info-row">
          <span class="detail-info-label">MAC Address</span>
          <span class="detail-info-value is-data">
            <x-id-display value="A4:C3:F0:B2:11:22" />
          </span>
        </div>

        <div class="detail-info-row">
          <span class="detail-info-label">Perangkat OLT</span>
          <span class="detail-info-value">
            OLT-Madiun-01
            <span class="text-muted text-xs">(Port 4/12)</span>
          </span>
        </div>
      </div>

      {{-- Section: Riwayat Pembayaran --}}
      <div>
        <div class="detail-section-title">
          <span>Riwayat Pembayaran</span>
          <a href="{{ route('customers.payments', $customer) }}">Invoice History →</a>
        </div>

        @foreach($recentPayments as $payment)
        <div class="payment-row">
          <span class="payment-row-label">
            <x-lucide-check-circle class="icon-check" />
            {{ $payment->period_label }}
          </span>
          <span class="payment-row-amount">
            Rp {{ number_format($payment->amount, 0, ',', '.') }}
          </span>
        </div>
        @endforeach
      </div>

    </div>{{-- /col-right --}}

  </div>{{-- /overview-grid --}}

  {{-- Divider --}}
  <div class="detail-section-divider"></div>

  {{-- Full-width: Realtime Traffic --}}
  <div class="detail-section-full">
    <div class="traffic-chart-header">
      <div class="traffic-chart-title">
        <x-lucide-activity class="w-3.5 h-3.5" />
        Realtime Traffic (24H)
      </div>
      <div class="traffic-chart-legend">
        <span class="traffic-legend-item">
          <span class="traffic-legend-dot download"></span> Download
        </span>
        <span class="traffic-legend-item">
          <span class="traffic-legend-dot upload"></span> Upload
        </span>
      </div>
    </div>
    <div class="traffic-chart-canvas"
         id="traffic-chart"
         wire:ignore
         data-customer-id="{{ $customer->id }}">
    </div>
  </div>

</div>{{-- /tab-body --}}
```

#### Timeline Horizontal (Registrasi Stepper)

Khusus untuk timeline registrasi pelanggan di halaman detail — berbeda dengan `.timeline` vertikal di section §13.5.

```css
/* Horizontal Stepper — Timeline Registrasi */
.timeline-h {
  display: flex;
  align-items: flex-start;
  gap: 0;
  position: relative;
  padding-top: 4px;
}

/* Garis connector antar step */
.timeline-h-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
  position: relative;
}

.timeline-h-item:not(:last-child)::after {
  content: '';
  position: absolute;
  top: 14px;
  left: 50%;
  width: 100%;
  height: 2px;
  background: var(--color-border);
  z-index: 0;
}

.timeline-h-item.done:not(:last-child)::after {
  background: var(--color-success);
}

.timeline-h-dot {
  width: 28px;
  height: 28px;
  border-radius: var(--radius-full);
  background: var(--color-border);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  z-index: 1;
  flex-shrink: 0;
}

.timeline-h-item.done .timeline-h-dot {
  background: var(--color-success);
  color: #fff;
}

.timeline-h-item.active .timeline-h-dot {
  background: var(--color-primary);
  color: #fff;
  box-shadow: 0 0 0 3px var(--color-primary-soft);
}

.timeline-h-item.active .timeline-h-dot svg {
  animation: timeline-pulse 2s ease-in-out infinite;
}

@keyframes timeline-pulse {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.6; }
}

.timeline-h-meta {
  text-align: center;
  margin-top: var(--space-2);
}

.timeline-h-date {
  font-family: var(--font-data);
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  display: block;
}

.timeline-h-label {
  font-family: var(--font-ui);
  font-size: var(--text-xs);
  font-weight: 600;
  color: var(--color-text-main);
  display: block;
  margin-top: 2px;
}

.timeline-h-item.pending .timeline-h-dot {
  background: var(--color-surface-muted);
  border: 2px dashed var(--color-border-strong);
  color: var(--color-text-muted);
}
.timeline-h-item.pending .timeline-h-date,
.timeline-h-item.pending .timeline-h-label {
  color: var(--color-text-muted);
}
```

```html
{{-- Horizontal Timeline Registrasi --}}
<div class="timeline-h">
  <div class="timeline-h-item done">
    <div class="timeline-h-dot">
      <x-lucide-check class="w-3.5 h-3.5" />
    </div>
    <div class="timeline-h-meta">
      <span class="timeline-h-date">12 Jan</span>
      <span class="timeline-h-label">Registrasi</span>
    </div>
  </div>

  <div class="timeline-h-item done">
    <div class="timeline-h-dot">
      <x-lucide-check class="w-3.5 h-3.5" />
    </div>
    <div class="timeline-h-meta">
      <span class="timeline-h-date">13 Jan</span>
      <span class="timeline-h-label">Survey</span>
    </div>
  </div>

  <div class="timeline-h-item done">
    <div class="timeline-h-dot">
      <x-lucide-check class="w-3.5 h-3.5" />
    </div>
    <div class="timeline-h-meta">
      <span class="timeline-h-date">15 Jan</span>
      <span class="timeline-h-label">Instalasi</span>
    </div>
  </div>

  <div class="timeline-h-item active">
    <div class="timeline-h-dot">
      <x-lucide-zap class="w-3.5 h-3.5" />
    </div>
    <div class="timeline-h-meta">
      <span class="timeline-h-date">15 Jan</span>
      <span class="timeline-h-label">Aktif</span>
    </div>
  </div>
</div>

```


---

## 6.6 Container Architecture — Halaman Detail Pelanggan

Ini adalah aturan PALING PENTING di halaman detail. Seluruh konten
halaman detail pelanggan (dari tab bar sampai traffic chart) terbungkus
dalam SATU panel tunggal bernama `.customer-panel`. Tidak ada card
tambahan di dalam panel ini.

### Struktur Visual (Wajib Diikuti Persis)

```
┌─────────────────────────────────────────────────────────────────────┐  ← .customer-panel
│  [Overview] [Layanan] [Tagihan] [Pembayaran] [Tiket] [Audit Log]   │  ← Tab bar
│─────────────────────────────────────────────────────────────────────│  ← border-bottom 1px
│  PAKET      │ IP ADDRESS   │ SIGNAL      │ UPTIME  │ BILLING  │ ... │  ← Metric strip
│─────────────────────────────────────────────────────────────────────│  ← border-bottom 1px
│                                                                     │  ← Tab content area
│  ┌─────────────────────────────┬───────────────────────────────┐   │
│  │  INFORMASI PELANGGAN        │  DETAIL TEKNIS          [Aksi] │   │
│  │                             │─────────────────────────────── │   │
│  │  [flat info rows]           │  [flat info rows]              │   │
│  │                             │                                │   │
│  │─────────────────────────────│  TIMELINE REGISTRASI          │   │  ← border-top 1px
│  │  TIMELINE REGISTRASI        │─────────────────────────────── │   │
│  │  [horizontal stepper]       │  RIWAYAT PEMBAYARAN    [Link]  │   │  ← border-top 1px
│  │                             │  [flat payment rows]           │   │
│  └─────────────────────────────┴───────────────────────────────┘   │
│  (kiri: border-right 1px ke kolom kanan — bukan dua card terpisah) │
│─────────────────────────────────────────────────────────────────────│  ← border-top 1px
│  ∿ REALTIME TRAFFIC (24H)                  ● Download  ○ Upload    │
│  [chart canvas — no wrapper]                                        │
└─────────────────────────────────────────────────────────────────────┘
```

### CSS `.customer-panel` (Satu-satunya Card di Halaman Detail)

```css
/* ── Satu-satunya container di halaman detail pelanggan ── */
.customer-panel {
  background: var(--color-surface);       /* #FFFFFF */
  border: 1px solid var(--color-border);  /* #E2E8F0 */
  border-radius: var(--radius-md);        /* 8px — BUKAN 16px */
  overflow: hidden;
  margin-bottom: 0;
}

/* ── Tab bar di dalam panel ── */
.customer-panel .detail-tabs {
  display: flex;
  border-bottom: 1px solid var(--color-border);
  padding: 0 4px;
  background: var(--color-surface);
}

/* ── Metric strip di dalam panel ── */
.customer-panel .metric-strip {
  /* Tidak punya border sendiri — hanya border-bottom untuk pisah dari content */
  border: none;
  border-bottom: 1px solid var(--color-border);
  border-radius: 0;
  background: var(--color-surface);
  margin-bottom: 0;
}

/* ── Area konten tab ── */
.customer-panel .detail-tab-body {
  padding: var(--space-6) var(--space-7);
  border: none;           /* TIDAK ada border sendiri */
  border-radius: 0;       /* TIDAK ada radius sendiri */
  background: transparent; /* transparan — ambil bg dari .customer-panel */
}

/* ── Two-column grid di dalam tab body ── */
.customer-panel .detail-overview-grid {
  display: grid;
  grid-template-columns: 5fr 7fr;
  gap: 0;
}

/* ── Kolom kanan: border-left sebagai pemisah, BUKAN card baru ── */
.customer-panel .detail-col-right {
  border-left: 1px solid var(--color-border);
  padding-left: var(--space-7);
}

/* ── Section di dalam kolom kanan: dipisah border-top, BUKAN card baru ── */
.customer-panel .detail-col-right .detail-section + .detail-section {
  border-top: 1px solid var(--color-border);
  padding-top: var(--space-6);
  margin-top: var(--space-6);
}

/* ── Pemisah antara overview grid dan chart ── */
.customer-panel .detail-section-divider {
  height: 1px;
  background: var(--color-border);
  margin: var(--space-6) calc(-1 * var(--space-7)); /* bleeding ke edge panel */
}

/* ── Area chart: di dalam panel, tanpa wrapper tambahan ── */
.customer-panel .traffic-section {
  padding: var(--space-5) var(--space-7) var(--space-6);
}
```

---

## 6.7 Card Budget Rule — Larangan Mutlak

Halaman Detail Pelanggan memiliki **Card Budget = 1**.

Artinya: hanya ada SATU elemen yang boleh memiliki kombinasi
`background + border + border-radius` sekaligus, yaitu `.customer-panel` itu sendiri.

### Tabel Card Budget

| Elemen | Boleh Punya Card? | Alasan |
|---|:---:|---|
| `.customer-panel` | ✅ Satu-satunya | Ini container utama halaman |
| Kolom Informasi Pelanggan | ❌ | Flat rows dengan divider |
| Kolom Detail Teknis | ❌ | Flat rows dengan divider |
| Timeline Registrasi | ❌ | Stepper tanpa wrapper |
| Riwayat Pembayaran | ❌ | Flat rows, bukan list card |
| Status Layanan | ❌ | Ada di metric strip, bukan card sendiri |
| Tagihan Bulan Ini | ❌ | **DILARANG keras** membuat card billing di tab Overview |
| Traffic Chart | ❌ | Canvas langsung, tanpa wrapper |
| Modal Konfirmasi | ✅ | Floating di atas page, beda context |
| Alert Banner | ✅ | Di luar `.customer-panel`, beda context |

### Aturan Internal Section

Di dalam `.customer-panel`, section dipisahkan HANYA dengan:
- `border-bottom: 1px solid var(--color-border)` — antara rows horizontal
- `border-left: 1px solid var(--color-border)` — antara kolom kiri dan kanan
- `border-top: 1px solid var(--color-border)` — antara sub-section dalam kolom

**TIDAK BOLEH** menggunakan `border + background-color + border-radius`
bersamaan pada elemen apapun di dalam `.customer-panel`.

---

## 6.8 Prohibited Patterns — Larangan Eksplisit untuk AI

Daftar ini adalah anti-pattern yang WAJIB dihindari. Jika AI menghasilkan
salah satu pattern di bawah ini, output harus ditolak dan di-regenerate.

### ❌ Prohibited: Card Billing di Tab Overview

```html
<!-- SALAH — Jangan pernah buat ini di tab Overview -->
<div class="card border-orange-200 bg-orange-50">
  <div>TAGIHAN BULAN INI · UNPAID</div>
  <div class="text-3xl">Rp 250.000</div>
  <button>Bayar Sekarang</button>
</div>

<!-- BENAR — Status billing cukup di metric strip -->
<div class="metric-strip-col">
  <div class="metric-strip-label">BILLING</div>
  <div class="metric-strip-value is-warn">Rp 250k</div>
  <span class="badge badge-warning badge-sm">UNPAID</span>
</div>
```

### ❌ Prohibited: Card untuk Status Layanan

```html
<!-- SALAH — "Status Layanan" bukan card tersendiri -->
<div class="card border-green-200">
  <div class="card-header">Status Layanan</div>
  <div>Paket: Fiber Home 50Mbps</div>
  <div>IP: 192.168.1.42</div>
  <div>Signal: -22 dBm</div>
</div>

<!-- BENAR — Data layanan ada di metric strip, bukan card terpisah -->
<div class="metric-strip">
  <div class="metric-strip-col">...</div> <!-- Paket -->
  <div class="metric-strip-col">...</div> <!-- IP -->
  <div class="metric-strip-col">...</div> <!-- Signal -->
</div>
```

### ❌ Prohibited: Dua Card Berjajar di Overview Grid

```html
<!-- SALAH — Kolom kiri dan kanan bukan dua card terpisah -->
<div class="grid grid-cols-2 gap-4">
  <div class="card">Informasi Pelanggan...</div>  <!-- SALAH -->
  <div class="card">Detail Teknis...</div>        <!-- SALAH -->
</div>

<!-- BENAR — Satu grid di dalam satu panel -->
<div class="customer-panel">
  ...
  <div class="detail-tab-body">
    <div class="detail-overview-grid">
      <div class="detail-col-left">...</div>
      <div class="detail-col-right">...</div> <!-- border-left saja -->
    </div>
  </div>
</div>
```

### ❌ Prohibited: Card untuk Riwayat Pembayaran

```html
<!-- SALAH — Riwayat Pembayaran bukan card tersendiri -->
<div class="card">
  <div class="card-header">Riwayat Pembayaran</div>
  <div class="card-body">
    <div class="flex justify-between p-3 bg-white border rounded">
      Mei 2026 — Rp 250.000
    </div>
  </div>
</div>

<!-- BENAR — Flat rows dengan divider saja -->
<div class="detail-section-title">Riwayat Pembayaran <a>Invoice History →</a></div>
<div class="payment-row">
  <span><icon-check /> Mei 2026</span>
  <span class="currency">Rp 250.000</span>
</div>
<div class="payment-row">...</div>
```

### ❌ Prohibited: Colored Background untuk Status Card

```html
<!-- SALAH — Background warna pada container section -->
<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
  Status: UNPAID
</div>

<!-- BENAR — Warna hanya pada badge/teks, background selalu putih -->
<div class="detail-info-row">
  <span class="detail-info-label">Status Billing</span>
  <span class="detail-info-value">
    <span class="badge badge-warning">● UNPAID</span>
    <span class="currency is-warn font-data">Rp 250.000</span>
  </span>
</div>
```

### ❌ Prohibited: Tab Content Tanpa Unified Panel

```html
<!-- SALAH — Tab content punya border dan radius sendiri (jadi double-card) -->
<div class="tabs">...</div>
<div class="tab-content border border-gray-200 rounded-lg mt-4 p-6">
  ...
</div>

<!-- BENAR — Tab content ada di dalam .customer-panel yang sama -->
<div class="customer-panel">
  <div class="detail-tabs">...</div>
  <div class="metric-strip">...</div>
  <div class="detail-tab-body">    <!-- tidak ada border/radius sendiri -->
    ...
  </div>
</div>
```

---

## 6.9 Section Title Specification (label-caps)

Section title di dalam halaman detail BUKAN heading. Ini adalah label
navigasi kecil yang membantu scan, bukan pembagi konten yang membutuhkan
visual weight besar.

### Spesifikasi Tepat

```css
.detail-section-title {
  /* Ukuran — harus kecil, bukan h3 */
  font-size: 10px;              /* BUKAN 12px, 14px, atau 16px */
  font-weight: 600;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  font-family: var(--font-ui); /* Inter */

  /* Warna — muted, bukan bold */
  color: #707881;              /* Bukan hitam, bukan primary */

  /* Layout */
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-4);

  /* Tidak ada: border-bottom, background, padding besar */
}

/* Ikon di kiri section title */
.detail-section-title .section-icon {
  width: 14px;
  height: 14px;
  color: var(--color-primary);  /* #0284C7 */
  margin-right: 6px;
}

/* Link aksi di kanan (misal: "Invoice History →", "Restart Session") */
.detail-section-title a,
.detail-section-title button.ghost {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-primary);
  text-decoration: none;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
}
```

### Visual yang Diharapkan

```
✅ BENAR:
  [icon] INFORMASI PELANGGAN                    (10px, uppercase, muted gray)
  ──────────────────────────────────
  Nama Lengkap    Budi Santoso
  ...

❌ SALAH (terlalu besar / terlalu bold):
  ## Informasi Pelanggan                        (heading-sized)
  ──────────────────────────────────
  Nama Lengkap    Budi Santoso

❌ SALAH (ada background/border di section title):
  ┌─────────────────────────────────┐
  │ 👤 Informasi Pelanggan          │   ← card header look
  ├─────────────────────────────────┤
  │ Nama Lengkap    Budi Santoso    │
  └─────────────────────────────────┘
```



---

## 6.10 Ergonomi Operator — Shift 8–9 Jam

Bagian ini mengatur hal-hal yang tidak terlihat di screenshot tapi menentukan
apakah aplikasi ini enak dipakai seharian. Staf billing dan admin POP membuka
halaman list selama satu shift penuh, memproses ratusan baris. Desain yang
"terlihat rapi" tapi memaksa klik berulang, memaksa tangan bolak-balik ke
mouse, atau memaksa mata membaca teks 11px selama 9 jam adalah desain yang
gagal — kegagalannya cuma tidak muncul di demo lima menit.

Empat aturan berikut wajib untuk **semua halaman Type A**.

---

### 6.10.1 Seleksi & Aksi Massal (Wajib)

**Setiap halaman list wajib punya kolom checkbox dan bulk action bar.**

Tanpa itu, pekerjaan berulang jadi beban fisik: menagih 50 pelanggan lewat
tempo lewat menu per-baris berarti 50× buka menu + 50× pilih item + 50×
konfirmasi = **150+ klik untuk satu aksi yang sama**. Dengan seleksi massal,
alurnya jadi: filter → pilih semua → satu klik.

```
[ ✓ ] ← checkbox header (select-all halaman ini)
┌──────────────────────────────────────────────────────────────┐
│ ✓ 12 baris dipilih   Batalkan   [Space] pilih baris aktif    │
│                    [⎙ Cetak] [⌾ WhatsApp] [⊘ Isolir]         │
└──────────────────────────────────────────────────────────────┘
   ↑ naked bar, muncul hanya saat ada seleksi — bukan card,
     supaya card budget halaman tetap 1
```

Aturan:

1. **Kolom checkbox paling kiri**, sebelum kolom identitas.
2. **Bulk bar muncul hanya saat ada baris terpilih**, menampilkan jumlah
   terpilih + tombol batalkan + aksi massal.
3. **Select-all header hanya menyapu baris di halaman yang sedang tampil.**
   Ini bukan keterbatasan — ini disengaja. Centang yang diam-diam memilih
   1.488 baris tak terlihat terlalu berbahaya untuk aksi seperti Isolir.
   Kalau user memang perlu memilih lintas halaman, naikkan dulu Per Page.
4. **Checkbox header punya state `indeterminate`** saat sebagian baris
   terpilih — bukan sekadar checked/unchecked.
5. **Aksi massal destruktif tetap wajib modal konfirmasi**, dan jumlah target
   dihitung ulang di dalam modal (yang sudah berstatus Isolir dikecualikan,
   dan judulnya menyebut angka: "Isolir 12 Layanan?").
6. **Seleksi dikosongkan setelah aksi selesai**, supaya tidak ada sisa
   pilihan yang tidak terlihat lalu tereksekusi lagi.

---

### 6.10.2 Navigasi Keyboard (Wajib)

Operator yang memproses ratusan baris tidak boleh dipaksa memindahkan tangan
ke mouse untuk tiap baris. Selain lambat, klik berulang sepanjang shift
adalah penyumbang nyata **Repetitive Strain Injury** di pergelangan tangan.

Kontrak pintasan — sama di semua halaman list:

| Tombol | Aksi | Cakupan |
|---|---|---|
| `↑` `↓` | Pindah baris aktif | Tabel |
| `Home` `End` | Baris pertama / terakhir | Tabel |
| `PgUp` `PgDn` | Halaman sebelum / sesudah | Tabel |
| `Space` | Pilih / lepas baris aktif | Tabel |
| `Enter` | Buka menu aksi baris aktif | Tabel |
| `/` | Fokus global search | Global |
| `Alt` + `N` | Tambah record baru | Global |
| `Esc` | Tutup menu / modal | Global |
| `?` | Buka panel bantuan | Global |

Aturan:

1. **Baris aktif wajib punya penanda visual sendiri** (`outline: 2px solid
   primary`, `outline-offset: -2px`) — tidak boleh hanya mengandalkan hover,
   karena hover mengikuti mouse yang justru sedang tidak dipakai.
2. **Baris aktif ikut ter-scroll** ke dalam viewport (`scrollIntoView`
   dengan `block: 'nearest'`).
3. **Pintasan mati saat user sedang mengetik** (`INPUT`, `TEXTAREA`,
   `SELECT`) atau saat modal terbuka. Pengecualian: `Alt`+`N` berlaku di
   mana saja karena tidak bertabrakan dengan pengetikan.
4. **Baris aktif direset** setiap tabel dirender ulang (filter, ganti
   halaman, ganti per-page).

---

### 6.10.3 Kerapatan Tabel (Density)

Satu ukuran font tidak melayani semua orang. Data-density tinggi enak untuk
screenshot, menyiksa untuk 9 jam menatap nominal rupiah dan tanggal jatuh
tempo — apalagi di monitor resolusi rendah.

| Mode | Font sel | Padding vertikal | Untuk |
|---|---:|---:|---|
| **Longgar** (default) | `13px` | `16px` | Kerja harian, shift panjang |
| Rapat | `11px` | `10px` | Menyapu banyak baris sekaligus |

**Default wajib Longgar.** Rapat adalah opt-in, bukan sebaliknya —
kenyamanan mata adalah kondisi normal, kepadatan adalah pengecualian.

Pilihan disimpan di `localStorage` dan dipulihkan saat halaman dibuka.

Implementasi memakai CSS custom property pada selektor **ID**, bukan utility
per-sel:

```css
#customerTable          { --cell-py: 16px; --cell-fs: 13px; --cell-sub-fs: 11px; }
html.density-compact
#customerTable          { --cell-py: 10px; --cell-fs: 11px; --cell-sub-fs: 10px; }

#customerTable tbody td { padding-block: var(--cell-py); font-size: var(--cell-fs); }
#customerTable tbody
  .cell-sub             { font-size: var(--cell-sub-fs); }
```

Selektor ID punya specificity `1-0-1`, mengalahkan utility Tailwind `0-1-0`
tanpa perlu `!important`. Konsekuensinya: **jangan menulis `text-[11px]` atau
`py-3.5` pada `<td>`** — ukuran sel milik density, bukan milik markup baris.

---

### 6.10.4 Paginasi & Per Page

**Paginasi wajib nyata, bukan tombol hardcoded.** Deret `1 2 3 … 149` yang
tidak terhubung ke data adalah kebohongan UI: tombolnya bisa diklik tapi
tidak memindahkan apa pun.

| Elemen | Aturan |
|---|---|
| Per Page | Dropdown `10 / 25 / 50 / 100`, **default 25**, disimpan di `localStorage` |
| Info | `Menampilkan X–Y dari Z` — ketiganya dihitung dari data, mono |
| Deret halaman | Elipsis di tengah: `1 … 9 [10] 11 … 20` |
| Prev / Next | `disabled` di ujung, bukan disembunyikan |
| Ganti filter | Selalu kembali ke halaman 1 |
| Ganti per-page | Kembali ke halaman 1, seleksi dipertahankan |

Default 25 (bukan 10) karena staf yang memproses ratusan baris lebih butuh
melihat banyak sekaligus daripada menekan Next berulang kali.

---

### 6.10.5 Panel Bantuan

Pintasan yang hanya hidup di dokumentasi tidak akan pernah dihafal. Panel
bantuan wajib punya **minimal tiga pintu masuk**:

1. **Ikon `?` di topbar** — untuk ditemukan tanpa dicari
2. **Item di user menu** (di bawah Preferensi) — untuk yang refleksnya
   mencari di menu akun
3. **Tombol `?` di keyboard** — untuk yang sudah tahu

Isi panel dibagi dua tab di dalam satu panel yang sama (tab bar dipisah
`border-bottom`, bukan card baru):

| Tab | Isi |
|---|---|
| **Pintasan** | Tabel pintasan §6.10.2 + catatan kapan pintasan tidak aktif |
| **Tombol Aksi** | Arti tiap item menu `[···]`, aturan aksi massal, cara membaca warna kolom tagihan |

Tab "Tombol Aksi" bukan pelengkap — ikon `⊘` dan `🗑` di dropdown tidak
menjelaskan dirinya sendiri. Yang wajib dijelaskan eksplisit:

- **Beda Isolir dan Terminasi** disebut di judulnya sendiri:
  *"Isolir Layanan — bisa dibatalkan"* vs *"Terminasi — permanen"*,
  lengkap dengan cara mengembalikan isolir.
- **Arti warna kolom tagihan** (hijau lunas / netral belum / merah lewat
  tempo). Warna adalah konvensi yang dipelajari sekali.

**Petunjuk in-context wajib menyertai.** Panel bantuan jarang dibuka dua
kali, jadi pintasan juga ditempel di tempat aksinya terjadi:

- Footer row menu: `↑↓ pindah · Enter buka` + tautan "Apa ini?" yang membuka
  panel langsung ke tab Tombol Aksi
- Bulk bar: `Space pilih baris aktif`
- Item user menu: `kbd` `?` di sisi kanan

Orang membaca petunjuk ini sambil mengerjakan tugasnya, bukan saat sedang
mencari bantuan — itu yang membuatnya menempel.

---

## 7. Halaman Tagihan

### 7.1 Daftar Tagihan

```
Page Header:
  Tagihan
  Kelola tagihan bulanan, status pembayaran, dan pengiriman invoice.
  [Generate Tagihan]  [Export Excel]

──────────────────────────────────────────────────────────────────

Summary Strip (mini cards horizontal):
 Belum Dibayar: 87   |   Overdue: 15   |   Lunas Hari Ini: 23   |   Total Bulan Ini: Rp 127.5 jt

──────────────────────────────────────────────────────────────────

Filter Bar:
  [🔍 No Invoice / CID / Nama]  [Status ▾]  [Bulan ▾]  [POP ▾]  [Export ▾]

Tabel:
  ┌──────────────────┬────────────┬───────────────┬──────────┬────────────┬──────────┬────────┐
  │ No Invoice       │ CID        │ Nama          │ Status   │ Jatuh Tempo│ Total    │ Aksi   │
  ├──────────────────┼────────────┼───────────────┼──────────┼────────────┼──────────┼────────┤
  │ INV-2026-06-0001 │ C00100042  │ Budi Santoso  │ ● Lunas  │ 10 Jun 26  │ Rp 250rb │ [···]  │
  │ INV-2026-06-0002 │ C00100043  │ Siti Rahayu   │ ● Overdue│ 05 Jun 26  │ Rp 200rb │ [···]  │
  │ INV-2026-06-0003 │ C00100044  │ Ahmad Fauzi   │ ● Belum  │ 15 Jun 26  │ Rp 850rb │ [···]  │
  └──────────────────┴────────────┴───────────────┴──────────┴────────────┴──────────┴────────┘
```

### 7.2 Detail Invoice / View Invoice

```
Breadcrumb: Dashboard / Tagihan / INV-2026-06-0042

Page Header:
  INV-2026-06-0042                            [Cetak PDF]  [Kirim WA]  [Konfirmasi Bayar]
  Tagihan bulan Juni 2026 · Dibuat 01 Jun 2026

──────────────────────────────────────────────────────────────────

┌──────────────────────────────────────────────────────────┐
│ INVOICE                                  WHUSNET          │
│                                          ISP Ponorogo     │
│ No: INV-2026-06-0042                                      │
│ Tanggal: 01 Jun 2026                                      │
│ Jatuh Tempo: 10 Jun 2026                                  │
│                                                           │
│ Kepada:                                                   │
│ Budi Santoso                                              │
│ CID: C00100042                                            │
│ Jl. Raya No. 12, Ponorogo                                 │
│                                                           │
│ ┌─────────────────────┬────────────┬───────────┐         │
│ │ Deskripsi           │ Periode    │ Jumlah    │         │
│ ├─────────────────────┼────────────┼───────────┤         │
│ │ Paket Fiber 50Mbps  │ Jun 2026   │ Rp 250.000│         │
│ │ Biaya Admin         │ —          │ Rp   5.000│         │
│ ├─────────────────────┼────────────┼───────────┤         │
│ │ TOTAL               │            │ Rp 255.000│         │
│ └─────────────────────┴────────────┴───────────┘         │
│                                                           │
│ Pembayaran: Transfer BCA 1234567890 a.n. WHUSNET          │
│ Kirim bukti bayar ke WA: 0812-xxxx-xxxx                   │
│                                                           │
│ Status: ● BELUM DIBAYAR                                   │
└──────────────────────────────────────────────────────────┘
```

---

## 8. Halaman Pembayaran

### 8.1 Daftar Pembayaran / Konfirmasi

```
Page Header:
  Pembayaran
  Konfirmasi pembayaran, rekonsiliasi, dan riwayat transaksi.
  [Konfirmasi Manual]  [Export Laporan]

──────────────────────────────────────────────────────────────────

Tabs: [Menunggu Konfirmasi (12)] [Sudah Dikonfirmasi] [Semua Transaksi]

Tab: Menunggu Konfirmasi
──────────────────────────────────────────────────────────────────

Tabel (dengan bulk action):
  [☐] [Konfirmasi Dipilih]

  ┌──┬──────────┬──────────────────┬──────────────┬──────────┬─────────────┬──────────┬────────┐
  │☐ │ Tgl & Jam│ No Invoice       │ Nama         │ Metode   │ Jumlah      │ Bukti    │ Aksi   │
  ├──┼──────────┼──────────────────┼──────────────┼──────────┼─────────────┼──────────┼────────┤
  │☐ │24/06 14:32│ INV-2026-06-0001│ Budi Santoso │ Transfer │ Rp 255.000  │ [Lihat]  │ [···]  │
  │☐ │24/06 13:15│ INV-2026-06-0005│ Dewi Lestari │ Cash     │ Rp 200.000  │ —        │ [···]  │
  └──┴──────────┴──────────────────┴──────────────┴──────────┴─────────────┴──────────┴────────┘
```

### 8.2 Modal Konfirmasi Pembayaran

```
Modal (medium · 560px):
┌─────────────────────────────────────────────────────┐
│ Konfirmasi Pembayaran                          [×]  │
├─────────────────────────────────────────────────────┤
│                                                     │
│ Invoice:   INV-2026-06-0042                         │
│ Pelanggan: Budi Santoso (C00100042)                 │
│ Total:     Rp 255.000                               │
│                                                     │
│ ──────────────────────────────────────────────────  │
│                                                     │
│ Tanggal Bayar *                                     │
│ [24/06/2026              📅]                        │
│                                                     │
│ Metode Pembayaran *                                 │
│ [Transfer Bank       ▾]                             │
│                                                     │
│ Jumlah Diterima *                                   │
│ [Rp 255.000                ]                        │
│                                                     │
│ Catatan (opsional)                                  │
│ [                          ]                        │
│                                                     │
├─────────────────────────────────────────────────────┤
│ [Batal]                           [✓ Konfirmasi]    │
└─────────────────────────────────────────────────────┘
```

---

## 9. Halaman POP & Node Jaringan

### 9.1 Daftar POP

```
Page Header:
  POP & Node Jaringan
  Pantau status seluruh titik distribusi dan perangkat aktif.
  [Tambah POP]

──────────────────────────────────────────────────────────────────

Summary Cards (4 kolom):
┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐
│ Total POP   │ │ POP Aktif   │ │ POP Alert   │ │ Node Down   │
│ 12          │ │ 11 ● Sehat  │ │ 1 △ Warning │ │ 0 ✓ Semua UP│
└─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘

──────────────────────────────────────────────────────────────────

Tabel POP:
  ┌────────────────┬───────────────┬───────┬────────────┬──────────┬────────────┬────────┐
  │ Nama POP       │ Tipe          │ Level │ Pelanggan  │ Status   │ Uptime     │ Aksi   │
  ├────────────────┼───────────────┼───────┼────────────┼──────────┼────────────┼────────┤
  │ POP Pusat      │ POP Pusat     │ L1    │ —          │ ● Aktif  │ 99.98%     │ [···]  │
  │ POP Madiun     │ Cabang POP    │ L2    │ 428        │ ● Aktif  │ 99.95%     │ [···]  │
  │ Mini POP Dolopo│ Mini POP      │ L3    │ 87         │ △ Warning│ 98.21%     │ [···]  │
  └────────────────┴───────────────┴───────┴────────────┴──────────┴────────────┴────────┘
```

### 9.2 Detail POP

```
Tabs: [Overview] [Node & Perangkat] [Pelanggan] [Riwayat Gangguan] [Topologi]

TAB: NODE & PERANGKAT
Tabel node dengan kolom:
  Nama | Tipe (OLT/Router/Switch) | IP Address | MAC | Status | Uptime | Signal Avg
```

---

## 10. Halaman Laporan Keuangan

### 10.1 Layout Laporan

```
Page Header:
  Laporan Keuangan
  Analisis pendapatan, tunggakan, dan rekonsiliasi per periode.
  [Export PDF]  [Export Excel]

──────────────────────────────────────────────────────────────────

Filter: [Periode: Jun 2026 ▾]  [POP: Semua ▾]  [Paket: Semua ▾]  [Terapkan]

──────────────────────────────────────────────────────────────────

Summary Row (5 kolom):
┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────┐
│ Total      │ │ Terkumpul  │ │ Tunggakan  │ │ Pembayaran │ │ Rata-rata  │
│ Tagihan    │ │ Bulan Ini  │ │ Aktif      │ │ Hari Ini   │ │ per Pelanggan│
│ Rp 127.5jt │ │ Rp 106.2jt │ │ Rp 21.3jt  │ │ Rp 4.25jt  │ │ Rp 250.000 │
│ 512 invoice│ │ 83.4%      │ │ 87 invoice │ │ 23 txn     │ │            │
└────────────┘ └────────────┘ └────────────┘ └────────────┘ └────────────┘

Chart: Tren Pendapatan vs Tunggakan (6 bulan)
[Bar chart dengan dua series: Terkumpul vs Total Tagihan]

Tabel Ringkasan per POP:
  POP | Total Tagihan | Terkumpul | Tunggakan | % Koleksi
```

---

## 11. Halaman Audit Log

### 11.1 Layout Audit Log

Halaman ini digunakan admin dan Finance untuk menelusuri siapa melakukan apa dan kapan.

```
Page Header:
  Audit Log
  Rekam jejak seluruh aktivitas pengguna di sistem.
  [Export]

──────────────────────────────────────────────────────────────────

Filter Bar:
  [🔍 Cari aksi / user / ID]  [Pengguna ▾]  [Modul ▾]  [Tipe Aksi ▾]  [Rentang Tanggal ▾]

──────────────────────────────────────────────────────────────────

Tabel (compact, JetBrains Mono untuk ID dan timestamp):
  ┌──────────────────────┬─────────────┬─────────────────────────────┬───────────┬────────┐
  │ Waktu                │ Pengguna    │ Aksi                        │ Target    │ IP     │
  ├──────────────────────┼─────────────┼─────────────────────────────┼───────────┼────────┤
  │ 2026-06-24 14:32:05  │ admin@whs   │ payment.confirmed           │ INV-0042  │ 10.0.1.5│
  │ 2026-06-24 14:28:11  │ noc@whs     │ customer.status.isolated    │ C00100043 │ 10.0.1.8│
  │ 2026-06-24 13:55:22  │ finance@whs │ invoice.pdf.exported        │ INV-0039  │ 10.0.1.6│
  └──────────────────────┴─────────────┴─────────────────────────────┴───────────┴────────┘
```

### 11.2 Aturan Tabel Audit Log

- Timestamp menggunakan **JetBrains Mono**, format `YYYY-MM-DD HH:mm:ss WIB`.
- Target ID (Invoice, CID, User ID) menggunakan **JetBrains Mono**.
- Aksi menggunakan format `modul.aksi` dalam `JetBrains Mono` dengan warna:
  - Aksi `create`, `confirmed`, `activated` → success color
  - Aksi `update`, `edit`, `exported` → muted/default
  - Aksi `delete`, `isolated`, `terminated`, `rejected` → error color
- Tidak ada aksi edit/delete di halaman ini. Audit log bersifat **immutable**.
- Pagination default: 50 baris per halaman.

---

## 12. Pengaturan Pengguna & RBAC

### 12.1 Daftar Pengguna

```
Page Header:
  Pengguna & Hak Akses
  Kelola akun pengguna, role, dan scope akses POP.
  [Tambah Pengguna]

──────────────────────────────────────────────────────────────────

Tabel:
  ┌──────────────────┬────────────┬──────────────┬────────────┬──────────┬────────┐
  │ Nama & Email     │ Role       │ Scope POP    │ Login Terakhir│ Status│ Aksi   │
  ├──────────────────┼────────────┼──────────────┼────────────┼──────────┼────────┤
  │ Admin Utama      │ Super Admin│ Semua        │ 24 Jun, 14:30│ ● Aktif│ [···]  │
  │ Siti Keuangan    │ Finance    │ Semua        │ 24 Jun, 13:05│ ● Aktif│ [···]  │
  │ Bowo NOC         │ NOC        │ POP Madiun   │ 24 Jun, 10:22│ ● Aktif│ [···]  │
  │ Adi Teknis       │ Teknisi    │ Mini POP Dolopo│23 Jun, 16:45│ ● Aktif│ [···]  │
  └──────────────────┴────────────┴──────────────┴────────────┴──────────┴────────┘
```

### 12.2 Form Tambah / Edit Pengguna

```
Form (max-width 960px, comfortable density):

Informasi Akun
  Nama Lengkap *          [                      ]
  Email *                 [                      ]
  Password *              [                      ]  Klik kanan: generate
  Konfirmasi Password *   [                      ]

Hak Akses
  Role *                  [Pilih Role          ▾]
  Scope POP *             [Pilih POP yang dapat diakses...]

  ☑ Dashboard
  ☑ Pelanggan (read)
  □ Pelanggan (create/edit)
  ☑ Tagihan (read)
  □ Tagihan (create/edit)
  ☑ Pembayaran (confirm)
  ...

[Batal]                                    [Simpan Pengguna →]
```

---

## 13. Komponen UI Spesifik WHUSNET

### 13.1 Badge Status Pelanggan

```html
<!-- Aktif -->
<span class="badge badge-success">
  <span class="badge-dot"></span> Aktif
</span>

<!-- Overdue -->
<span class="badge badge-error">
  <span class="badge-dot"></span> Overdue
</span>

<!-- Isolir -->
<span class="badge badge-purple">
  <span class="badge-dot"></span> Isolir
</span>

<!-- Pending -->
<span class="badge badge-warning">
  <span class="badge-dot"></span> Pending
</span>
```

```css
.badge-dot {
  width: 6px;
  height: 6px;
  border-radius: var(--radius-full);
  background: currentColor;
  flex-shrink: 0;
}

/* Untuk Isolir / Suspend */
.badge-purple {
  color: #7C3AED;
  background: #F5F3FF;
  border-color: #DDD6FE;
}
```

### 13.2 ID Display Component

Semua ID teknis (CID, INV, REG, IP, MAC) harus menggunakan komponen ini agar konsisten:

```html
<!-- CID inline dengan copy button -->
<span class="id-display">
  <span class="id-text">C00100042</span>
  <button class="id-copy" aria-label="Salin CID" title="Salin ke clipboard">
    <!-- copy icon 14px -->
  </button>
</span>
```

```css
.id-display {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-family: var(--font-data);
  font-size: var(--text-xs);
  color: var(--color-text-secondary);
}

.id-copy {
  opacity: 0;
  border: none;
  background: transparent;
  cursor: pointer;
  color: var(--color-text-muted);
  padding: 2px;
  border-radius: var(--radius-xs);
  transition: opacity var(--duration-fast);
}

.id-display:hover .id-copy {
  opacity: 1;
}

.id-copy:hover {
  color: var(--color-primary);
  background: var(--color-primary-soft);
}
```

### 13.3 Currency Display

```css
.currency {
  font-family: var(--font-data);
  font-variant-numeric: tabular-nums;
  font-weight: 500;
  color: var(--color-text-main);
}

.currency-large {
  font-size: var(--text-xl);
  font-weight: 600;
}

/* Tunggakan / overdue */
.currency.is-overdue {
  color: var(--color-error);
}

/* Terbayar */
.currency.is-paid {
  color: var(--color-success);
}
```

Format Rupiah Indonesia:
```js
// Gunakan Intl.NumberFormat untuk konsistensi
const formatRupiah = (amount) =>
  new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
// Output: "Rp 250.000"
```

### 13.4 Signal Quality Indicator (OLT/ONT)

```html
<span class="signal-indicator signal-normal">
  <span class="signal-icon"><!-- wifi icon --></span>
  <span class="signal-value">-22 dBm</span>
  <span class="signal-label">Normal</span>
</span>
```

```css
.signal-indicator {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-family: var(--font-data);
  font-size: var(--text-xs);
}

.signal-normal  { color: var(--color-success); }
.signal-warning { color: var(--color-warning); }  /* -26 s/d -28 dBm */
.signal-critical { color: var(--color-error); }   /* < -28 dBm */
```

### 13.5 Timeline Aktivitas (Dashboard & Detail)

```css
.timeline {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.timeline-item {
  display: flex;
  gap: 12px;
  padding: 10px 0;
  position: relative;
}

/* Garis vertikal penghubung */
.timeline-item:not(:last-child)::before {
  content: '';
  position: absolute;
  left: 14px;
  top: 28px;
  bottom: 0;
  width: 1px;
  background: var(--color-border);
}

.timeline-dot {
  width: 28px;
  height: 28px;
  border-radius: var(--radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: 2px;
}

.timeline-dot.success { background: var(--color-success-bg); color: var(--color-success); }
.timeline-dot.error   { background: var(--color-error-bg);   color: var(--color-error); }
.timeline-dot.info    { background: var(--color-info-bg);    color: var(--color-info); }
.timeline-dot.warning { background: var(--color-warning-bg); color: var(--color-warning); }

.timeline-body {
  flex: 1;
}

.timeline-title {
  font-family: var(--font-ui);
  font-size: var(--text-sm);
  color: var(--color-text-main);
  font-weight: 500;
}

.timeline-meta {
  font-family: var(--font-data);
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  margin-top: 2px;
}
```

---

## 14. State System

### 14.1 Loading State

```html
<!-- Skeleton untuk tabel -->
<table>
  <thead>...</thead>
  <tbody>
    @for($i = 0; $i < 8; $i++)
    <tr>
      <td><div class="skeleton skeleton-text-sm" style="width:80px"></div></td>
      <td><div class="skeleton skeleton-text-sm" style="width:140px"></div></td>
      <td><div class="skeleton skeleton-text-sm" style="width:100px"></div></td>
      <td><div class="skeleton skeleton-badge"></div></td>
      <td><div class="skeleton skeleton-text-sm" style="width:90px"></div></td>
      <td><div class="skeleton skeleton-btn" style="width:60px"></div></td>
    </tr>
    @endfor
  </tbody>
</table>
```

### 14.2 Empty State

```html
<div class="empty-state">
  <!-- Ilustrasi SVG sederhana, bukan foto/gambar eksternal -->
  <svg class="empty-state-icon"><!-- inbox / search icon --></svg>
  <h3 class="empty-state-title">Tidak Ada Data Ditemukan</h3>
  <p class="empty-state-desc">
    Tidak ada pelanggan yang cocok dengan filter yang dipilih.
    Coba ubah filter atau reset pencarian.
  </p>
  <button class="btn-secondary">Reset Filter</button>
</div>
```

```css
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--space-12) var(--space-6);
  text-align: center;
  gap: var(--space-3);
}

.empty-state-icon {
  width: 48px;
  height: 48px;
  color: var(--color-text-muted);
  opacity: 0.6;
}

.empty-state-title {
  font-family: var(--font-ui);
  font-size: var(--text-lg);
  font-weight: 600;
  color: var(--color-text-main);
}

.empty-state-desc {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  max-width: 360px;
  line-height: 1.6;
}
```

### 14.3 Error State (Tabel / Data Fetch Gagal)

```html
<div class="error-state">
  <svg class="error-state-icon"><!-- alert-circle icon --></svg>
  <h3>Gagal Memuat Data</h3>
  <p>Terjadi kesalahan saat mengambil data tagihan. Periksa koneksi dan coba lagi.</p>
  <button class="btn-secondary" onclick="window.location.reload()">Coba Lagi</button>
</div>
```

### 14.4 Confirmation Dialog (Aksi Destruktif)

```html
<!-- Modal konfirmasi sebelum isolir / terminasi -->
<div class="modal-sm">
  <div class="modal-header">
    <div class="modal-icon error">
      <!-- alert-triangle icon -->
    </div>
    <h2 class="modal-title">Isolir Layanan?</h2>
  </div>
  <div class="modal-body">
    <p>Anda akan mengisolir layanan milik <strong>Budi Santoso (C00100042)</strong>.</p>
    <p>Pelanggan tidak dapat menggunakan internet sampai isolir dicabut.</p>
  </div>
  <div class="modal-footer">
    <button class="btn-secondary">Batal</button>
    <button class="btn-danger">Ya, Isolir Sekarang</button>
  </div>
</div>
```

---

## 15. Real-time Updates (Laravel Reverb)

WHUSNET menggunakan Laravel Reverb untuk broadcast event real-time.

### 15.1 Event yang Di-broadcast

| Event | Channel | Update UI |
|---|---|---|
| `PaymentConfirmed` | `billing.{pop_id}` | Badge notifikasi, refresh tabel tagihan |
| `CustomerIsolated` | `operations` | Toast warning, update badge status |
| `NetworkAlertFired` | `network.{pop_id}` | Alert Banner muncul otomatis |
| `NodeStatusChanged` | `network.{node_id}` | Update status card POP |
| `InvoiceGenerated` | `billing` | Toast info, refresh counter tagihan |

### 15.2 Toast Notifikasi Pembayaran Masuk

```html
<!-- Toast muncul di pojok kanan bawah saat PaymentConfirmed event tiba -->
<div class="toast toast-success" role="status" aria-live="polite">
  <svg class="toast-icon"><!-- check-circle icon --></svg>
  <div class="toast-content">
    <p class="toast-title">Pembayaran Dikonfirmasi</p>
    <p class="toast-message">INV-2026-06-0042 · Budi Santoso · Rp 255.000</p>
  </div>
  <button class="toast-dismiss" aria-label="Tutup"><!-- x icon --></button>
</div>
```

### 15.3 Alert Banner Gangguan Jaringan (Auto-appear)

```html
<!-- Muncul otomatis ketika NetworkAlertFired event diterima -->
<div class="alert-banner alert-banner-error" 
     role="alert" 
     aria-live="assertive"
     x-show="hasNetworkAlert"
     x-transition>
  <!-- error icon -->
  <div class="alert-banner-body">
    <p class="alert-banner-title">Gangguan Jaringan Aktif</p>
    <p class="alert-banner-message">
      POP Madiun mengalami gangguan sejak 08:42 WIB. 47 pelanggan terdampak.
    </p>
    <div class="alert-banner-actions">
      <a href="/tickets/INC-2026-0042" class="btn-secondary btn-sm">Lihat Detail Gangguan</a>
    </div>
  </div>
</div>
```

---

## 16. Responsive Design Rules

### 16.1 Breakpoint Behavior

| Breakpoint | Sidebar | Tabel | Cards |
|---|---|---|---|
| `xl` 1280px+ | Expanded (256px) | Full, semua kolom | 4 kolom |
| `lg` 1024px | Collapsed (72px) | Full, semua kolom | 3 kolom |
| `md` 768px | Hidden (drawer) | Scroll horizontal | 2 kolom |
| `sm` 640px | Hidden (drawer) | Scroll horizontal | 1 kolom |

### 16.2 Mobile-specific Rules

- Sidebar berubah jadi drawer yang muncul dari kiri dengan overlay.
- Tombol aksi utama di page header menjadi floating action button (FAB) di mobile.
- Tabel pada mobile: scroll horizontal, sticky kolom pertama (Nama/ID).
- Filter bar collapse menjadi tombol `[Filter ▾]` yang membuka bottom sheet.
- Metric cards menjadi 2 kolom, bukan 4.
- Dashboard charts disembunyikan di mobile, tampilkan angka saja.

---

## 17. Accessibility Checklist

Setiap halaman wajib memenuhi:

- [ ] Semua tombol interaktif punya `aria-label` jika tidak ada teks.
- [ ] Semua form input punya `<label>` dengan `for` yang benar.
- [ ] Semua badge status punya teks (bukan hanya dot/warna).
- [ ] Error message tidak hanya bergantung pada warna merah.
- [ ] Modal trap focus dan tutup dengan `Esc`.
- [ ] Tabel punya `<th scope="col">` untuk setiap kolom header.
- [ ] Toast notification punya `role="status"` atau `role="alert"`.
- [ ] Alert Banner punya `role="alert"` dan `aria-live`.
- [ ] Keyboard navigation berfungsi di semua dropdown dan select.
- [ ] Rasio kontras warna minimal 4.5:1 untuk teks biasa.
- [ ] Rasio kontras warna minimal 3:1 untuk teks besar (18px+).
- [ ] `prefers-reduced-motion` dihormati untuk semua animasi.

---

## 18. Dark Mode NOC

WHUSNET mendukung dark mode untuk operator NOC yang bekerja di lingkungan low-light.

### 18.1 Penempatan Toggle

Toggle tema adalah **tombol ikon tersendiri di Topbar**, bersebelahan dengan
bell notifikasi — bukan item tersembunyi di dalam user menu. Mengganti tema
adalah aksi yang sering dilakukan (ikut kondisi cahaya ruangan, bukan ikut
identitas user), jadi tidak boleh butuh dua klik.

**Ikon menunjukkan tujuan, bukan keadaan.** Saat mode terang aktif, tombol
menampilkan ikon `moon` ("klik untuk gelap"); saat gelap, ikon `sun`.
Menampilkan ikon mode yang sedang aktif membuat user ragu apakah tombol itu
indikator atau saklar.

### 18.2 Resolusi Tema

```
localStorage['whusnet-theme']  →  ada?  pakai itu           (pilihan user menang)
                                  tidak? ikuti prefers-color-scheme OS
```

Setelah user menekan toggle sekali, pilihannya tersimpan permanen dan
perubahan setting OS **tidak lagi menimpanya**. Listener `prefers-color-scheme`
tetap dipasang, tapi langsung keluar kalau `localStorage` sudah terisi.

### 18.3 Anti-Flash (Wajib)

Kelas `dark` harus dipasang **sebelum** body dirender. Kalau menunggu
Alpine/DOMContentLoaded, akan ada kedipan putih sekejap tiap kali membuka
halaman dalam mode gelap — sangat mengganggu di ruangan gelap, yang justru
alasan dark mode ada.

```html
<head>
  <script>
    (function () {
      const saved = localStorage.getItem('whusnet-theme');
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (saved === 'dark' || (!saved && prefersDark)) {
        document.documentElement.classList.add('dark');
      }
    })();
  </script>
  <!-- baru setelah ini: CSS, Tailwind, dst -->
</head>
```

Tambahkan juga `color-scheme` supaya kontrol bawaan browser (scrollbar,
dropdown select, autofill) ikut gelap:

```css
html       { color-scheme: light; }
html.dark  { color-scheme: dark; }
```

### 18.4 Peta Token Terang → Gelap

Tailwind memakai strategi `darkMode: 'class'`. Setiap permukaan wajib punya
pasangan; jangan menyisakan satu pun `bg-white` tanpa `dark:`.

| Peran | Light | Dark |
|---|---|---|
| App background | `bg-slate-50` | `dark:bg-slate-900` |
| Surface (panel, card, modal, sidebar) | `bg-white` | `dark:bg-slate-800` |
| Surface muted (footer, header tabel) | `bg-slate-50/50`, `bg-slate-100/70` | `dark:bg-slate-900/40`, `dark:bg-slate-900/50` |
| Border halus | `border-slate-200` | `dark:border-slate-700` |
| Border sangat halus | `border-slate-100` | `dark:border-slate-700/60` |
| Teks utama | `text-slate-900` | `dark:text-slate-50` |
| Teks sekunder | `text-slate-600` | `dark:text-slate-300` |
| Teks muted / label | `text-slate-500` | `dark:text-slate-400` |
| Ikon muted | `text-slate-400` | `dark:text-slate-500` |
| Primary teks / link | `text-skybrand-600` | `dark:text-skybrand-400` |
| Primary soft background | `bg-skybrand-50` | `dark:bg-skybrand-900/30` |
| Badge status (bg) | `bg-{warna}-50` | `dark:bg-{warna}-900/30` |
| Badge status (teks) | `text-{warna}-700` | `dark:text-{warna}-300` |
| Badge status (border) | `border-{warna}-200` | `dark:border-{warna}-800` |
| Ring pemisah avatar/dot | `ring-white` | `dark:ring-slate-800` |
| Overlay modal | `bg-slate-900/50` | `dark:bg-slate-950/70` |

**Header tabel harus lebih gelap dari panelnya.** Di light, `bg-slate-100/70`
lebih gelap dari `bg-white`. Di dark, memetakannya ke `slate-800` membuatnya
menyatu dengan panel — pakai `dark:bg-slate-900/50` supaya tetap terbaca
sebagai header.

**Warna solid tidak dipetakan.** Tombol primary (`bg-skybrand-600`), badge
notifikasi (`bg-rose-500`), dan teks di atasnya (`text-white`) sudah cukup
kontras di kedua mode — jangan diberi varian `dark:` yang menggeser warnanya.

### 18.5 Warna di Luar Tailwind

Nilai warna yang hidup di blok `<style>` (bukan utility) tidak ikut terbawa
`darkMode: 'class'` dan harus ditulis manual:

```css
.glass-header      { background: rgba(255, 255, 255, 0.85); }
.dark .glass-header{ background: rgba(30, 41, 59, 0.85); }   /* slate-800 */

.dark ::-webkit-scrollbar-track      { background: #0f172a; }
.dark ::-webkit-scrollbar-thumb      { background: #475569; }
.dark ::-webkit-scrollbar-thumb:hover{ background: #64748b; }
```

### 18.6 CSS Variables Dark

```css
.dark {
  --color-background: #0F172A;
  --color-surface: #1E293B;
  --color-surface-muted: #334155;
  --color-border: #334155;
  --color-border-strong: #475569;
  --color-text-main: #F8FAFC;
  --color-text-secondary: #CBD5E1;
  --color-text-muted: #94A3B8;
  --color-text-disabled: #475569;
  --color-primary: #38BDF8;
  --color-primary-hover: #0284C7;
  --color-primary-soft: #0C4A6E;
  --color-primary-border: #0369A1;
  --color-success-bg: #052E16;
  --color-warning-bg: #2D1B00;
  --color-error-bg: #2D0A0A;
  --color-info-bg: #0A1929;
}
```

Nilai di atas adalah sumber kebenaran untuk peta utility di §18.4 —
`--color-surface: #1E293B` itulah yang jadi `dark:bg-slate-800`, dan
`--color-background: #0F172A` jadi `dark:bg-slate-900`. Kalau salah satu
diubah, peta di §18.4 wajib ikut diperbarui.

Catatan tambahan:
- Metric card values tetap mudah dibaca di dark mode.
- Badge text tidak berubah, hanya background lebih gelap (`{warna}-900/30`).
- Grafik chart tidak perlu adjustment khusus — warna chart sudah cukup kontras.
- Tabel row hover memakai tint primary (`dark:hover:bg-skybrand-900/20`),
  bukan abu-abu netral — supaya baris aktif tetap terbaca sebagai "sedang
  disorot", sama seperti di light mode.

---

## 19. Print & PDF Spesifikasi

### 19.1 Halaman yang Harus Bisa Dicetak

| Halaman | Format | Library |
|---|---|---|
| Invoice Tagihan | PDF A4 Portrait | `barryvdh/laravel-dompdf` |
| Laporan Keuangan Bulanan | PDF A4 Landscape | `barryvdh/laravel-dompdf` |
| Surat Peringatan Tunggakan | PDF A4 Portrait | `barryvdh/laravel-dompdf` |
| Berita Acara Pemasangan | PDF A4 Portrait | `barryvdh/laravel-dompdf` |
| Berita Acara Pemutusan | PDF A4 Portrait | `barryvdh/laravel-dompdf` |

### 19.2 Invoice PDF Template

```
┌─────────────────────────────────────────────────────────┐
│  [LOGO WHUSNET]                         INVOICE         │
│  Whusnet ISP · Ponorogo                                 │
│  Jl. Raya Ponorogo · Telp. 0352-xxxxxx                 │
│                                                         │
│  No Invoice: INV-2026-06-0042                           │
│  Tanggal   : 01 Juni 2026                               │
│  Jatuh Tempo: 10 Juni 2026                              │
├─────────────────────────────────────────────────────────┤
│  Kepada:                                                │
│  Budi Santoso                                           │
│  CID: C00100042 · REG: REG-20240112-0042               │
│  Jl. Raya No. 12, Ponorogo, Jawa Timur                 │
├─────────────────────────────────────────────────────────┤
│  Deskripsi              Periode         Jumlah          │
│  Paket Fiber 50Mbps     Juni 2026       Rp 250.000      │
│  Biaya Administrasi     —               Rp   5.000      │
├─────────────────────────────────────────────────────────┤
│  TOTAL                                  Rp 255.000      │
├─────────────────────────────────────────────────────────┤
│  Cara Pembayaran:                                       │
│  Transfer ke: BCA 1234567890 a.n. WHUSNET              │
│  Kirim bukti transfer ke WhatsApp: 0812-xxxx-xxxx      │
│                                                         │
│  Terima kasih atas kepercayaan Anda.                   │
├─────────────────────────────────────────────────────────┤
│  Dicetak otomatis · Whusnet Operasional · 24/06/2026   │
└─────────────────────────────────────────────────────────┘
```

### 19.3 Laravel PDF Route

```php
// routes/web.php
Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'exportPdf'])
    ->middleware(['auth', 'verified', 'can:view,invoice'])
    ->name('invoices.pdf');

// app/Http/Controllers/InvoiceController.php
public function exportPdf(Invoice $invoice): Response
{
    $this->authorize('view', $invoice);

    $pdf = Pdf::loadView('pdf.invoice', [
            'invoice'  => $invoice,
            'customer' => $invoice->customer,
            'items'    => $invoice->items,
        ])
        ->setPaper('a4', 'portrait')
        ->setOption('dpi', 150)
        ->setOption('defaultFont', 'DejaVu Sans');

    AuditLog::record('invoice.pdf.exported', $invoice);

    return $pdf->download("invoice-{$invoice->number}.pdf");
}
```

---

## 20. Implementation Priority

Urutan implementasi yang disarankan berdasarkan nilai bisnis:

### Fase 1 — Core Foundation (Sprint 1–2)
1. App Shell (Sidebar + Topbar + Layout)
2. Design Token & CSS Variables
3. Komponen dasar: Button, Badge, Card, Input, Table
4. Halaman Login & Error Pages (403, 404, 500)

### Fase 2 — Data Pages (Sprint 3–5)
5. Dashboard dengan Metric Cards dan Summary Cards
6. Halaman Daftar Pelanggan + Filter
7. Detail Pelanggan (tabs)
8. Daftar Tagihan + Status Badge

### Fase 3 — Transaksi (Sprint 6–8)
9. Konfirmasi Pembayaran + Modal
10. Generate Invoice + Print PDF
11. Halaman POP & Node
12. Laporan Keuangan

### Fase 4 — Advanced (Sprint 9–13)
13. RBAC dinamis + Manajemen Pengguna
14. Audit Log dengan filter advanced
15. Real-time Reverb notifications
16. Dark Mode toggle
17. Mobile responsive polish

---

## 21. Quick Reference: Do & Don't

### ✅ DO — Lakukan Ini

- Gunakan `JetBrains Mono` untuk semua angka, ID, IP, MAC, timestamp.
- Gunakan badge berwarna + teks untuk setiap status, jangan hanya dot/icon.
- Tampilkan skeleton loading saat data sedang dimuat dari server.
- Konfirmasi modal untuk semua aksi destruktif (isolir, terminasi, hapus).
- Format Rupiah selalu dengan `Rp` prefix dan pemisah titik (Rp 250.000).
- Sembunyikan menu RBAC yang tidak diizinkan (bukan disabled).
- Gunakan Alert Banner untuk gangguan jaringan, bukan Toast.
- Catat semua aksi user ke Audit Log.

### ❌ DON'T — Hindari Ini

- Jangan gunakan emoji sebagai ikon antarmuka.
- Jangan hardcode warna — selalu gunakan CSS variable.
- Jangan tampilkan data sensitif (password, API key) di UI.
- Jangan buat konfirmasi di tombol merah saja tanpa teks modal.
- Jangan gunakan warna merah untuk hal yang bukan error/kritis.
- Jangan nested card lebih dari 1 level.
- Jangan tampilkan error state saat loading state masih aktif.
- Jangan gunakan font Inter untuk nilai rupiah, IP, atau ID.
- Jangan andalkan `font-mono` default OS — remap ke JetBrains Mono di tailwind.config.
- Jangan pakai warna aksen selain Sky Blue (`skybrand`) untuk elemen interaktif.
- Jangan buat 4 card KPI terpisah di halaman list — pakai flat summary strip.
- Jangan taruh toggle pengubah status layanan di baris tabel.
- Jangan buat halaman list tanpa checkbox seleksi dan bulk action bar.
- Jangan bikin select-all yang menyapu seluruh hasil filter, bukan halaman aktif.
- Jangan bikin tabel yang hanya bisa dioperasikan dengan mouse.
- Jangan tandai baris aktif keyboard hanya dengan style hover.
- Jangan jadikan 11px sebagai ukuran font tabel default.
- Jangan tulis `text-[11px]` atau `py-3.5` di `<td>` — itu wilayah density.
- Jangan pasang deret tombol paginasi hardcoded yang tidak terhubung data.
- Jangan sembunyikan pintasan hanya di panel bantuan.
- Jangan pakai gradient untuk tombol, logo, atau badge.
- Jangan campur radius (16px / 12px / 8px) dalam satu layar.
- Jangan isi zona tengah topbar dengan elemen dekoratif — itu milik global search.
- Jangan tulis label grup sidebar di breadcrumb; pakai label item.
- Jangan beri sub-item aktif bobot visual lebih besar dari menu induknya.
- Jangan buat pagination tanpa menampilkan total record.

---

## 22. ## Ringkasan Patch — Aturan Satu Kalimat untuk AI Prompt

Jika kamu memberikan DESIGN.md ke AI design tool (Stitch, Claude Design,
atau tools lain), tambahkan instruksi berikut di awal prompt:

```
CRITICAL RULES — NEVER VIOLATE:

1. SINGLE PANEL: Seluruh konten halaman detail pelanggan (tabs, metric 
   strip, overview grid, chart) ada dalam SATU .customer-panel. 
   Card budget = 1. Tidak ada card di dalam card.

2. NO SECTION CARDS: Informasi Pelanggan, Detail Teknis, Timeline, 
   Riwayat Pembayaran, dan Traffic Chart TIDAK punya card wrapper sendiri.
   Gunakan hanya divider (border-bottom/left/top 1px) sebagai pemisah.

3. NO BILLING CARD IN OVERVIEW: Status dan jumlah tagihan hanya ada di 
   metric strip. Tidak ada card billing, tidak ada tombol Bayar di Overview.

4. SECTION TITLES ARE 10PX: Label section (INFORMASI PELANGGAN, DETAIL 
   TEKNIS, dll) adalah 10px uppercase Inter 600 #707881. Bukan heading.

5. METRIC STRIP IS FLAT: Metric strip adalah baris flat dengan divider 
   vertikal. Bukan 5-6 card terpisah. Satu border wraps semua kolom.

6. TABS + METRIC = UNIFIED: Tab bar dan metric strip berada dalam satu 
   container yang sama. Tab di atas, metric di bawah tab, dipisah 
   border-bottom. Tidak ada gap/margin antara keduanya.

7. RIGHT COLUMN IS ONE PANEL: Kolom kanan (Detail Teknis + Timeline + 
   Riwayat Pembayaran) adalah satu kolom yang menyambung. Section dalam 
   kolom dipisah border-top, bukan card baru.

8. NO COLORED BACKGROUNDS: Warna warning/error/success hanya pada badge 
   teks dan ikon. Background selalu var(--color-surface) = #FFFFFF.

9. SKY BLUE ONLY: Aksen interaksi = #0284C7 (skybrand-600), hover #0369A1,
   soft #F0F9FF. Bukan indigo, bukan violet, bukan cobalt. Semua lewat
   CSS variable / token Tailwind — tidak ada hex hardcode di markup.

10. JETBRAINS MONO IS THE DEFAULT MONO: font-mono harus di-remap ke
    JetBrains Mono di tailwind.config. CID, invoice, rupiah, IP, MAC,
    dan timestamp TIDAK BOLEH pakai Inter maupun monospace bawaan OS.

11. TYPE A KPI IS A FLAT STRIP: Halaman list (Pelanggan, Tagihan,
    Pembayaran, POP, Audit Log) punya card budget = 1 (table panel).
    Ringkasan KPI = satu bar flat dengan divider vertikal, BUKAN 4 card
    terpisah. Filter bar dan page header selalu naked.

12. STATUS IS READ-ONLY IN TABLES: Dilarang menaruh toggle/switch/checkbox
    di baris tabel yang mengubah status layanan. Status = badge baca-saja.
    Semua perubahan lewat dropdown [···]; yang merugikan pelanggan
    (isolir, terminasi) WAJIB modal konfirmasi.

13. BREADCRUMB IS ALWAYS Home › Menu › Sub Menu: Tepat tiga ruas. Ruas
    tengah = label ITEM sidebar (Pelanggan), bukan label GRUP sidebar
    (Operasional). Ruas terakhir bukan link, pakai aria-current="page".

14. GLOBAL SEARCH IS MANDATORY: Zona tengah topbar milik global search.
    Jangan diisi jam, tanggal, atau badge dekoratif. Zona kanan urutannya
    tetap: toggle tema → notifikasi → separator → avatar/user menu.

15. ONE RADIUS SCALE, NO GRADIENTS: Semua panel/tombol/input/modal 8px
    (rounded-lg); hanya search input, badge, dan pill yang boleh full-round.
    Warna elemen selalu solid dari token — tidak ada bg-gradient-to-*.

16. BILLING DATA BELONGS IN THE CUSTOMER LIST: Kolom Jatuh Tempo dan
    Tagihan wajib ada. Dilarang kolom nomor urut baris dan dot-meter
    persentase.

17. EVERY SURFACE NEEDS A DARK PAIR: darkMode 'class'. Tidak boleh ada
    bg-white / text-slate-900 / border-slate-200 tanpa pasangan dark:.
    Kelas 'dark' dipasang lewat script inline di <head> SEBELUM body
    dirender — kalau tidak, ada flash putih. Toggle tema = tombol ikon
    tersendiri di topbar, ikonnya menunjukkan tujuan (moon saat terang).

18. BULK SELECTION IS MANDATORY ON LIST PAGES: Kolom checkbox + bulk action
    bar (naked, muncul saat ada seleksi). Select-all header HANYA menyapu
    halaman yang sedang tampil, tidak pernah seluruh hasil filter. State
    indeterminate wajib. Aksi massal destruktif tetap lewat modal.

19. TABLE MUST BE USABLE WITHOUT A MOUSE: ↑↓ pindah baris, Home/End,
    PgUp/PgDn, Space pilih, Enter buka menu aksi, / cari, Alt+N tambah,
    Esc tutup, ? bantuan. Baris aktif wajib punya outline sendiri — tidak
    boleh hanya mengandalkan hover. Pintasan mati saat mengetik/modal
    terbuka, kecuali Alt+N.

20. DENSITY DEFAULTS TO COMFORTABLE: Font sel 13px default, 11px hanya
    sebagai mode "rapat" opt-in. Ukuran sel diatur CSS var di selektor ID
    (#table), BUKAN utility text-[11px]/py-3.5 di <td>. Disimpan di
    localStorage.

21. PAGINATION MUST BE REAL: Dilarang deret tombol hardcoded. Per Page
    10/25/50/100 default 25. Info "Menampilkan X–Y dari Z" dihitung dari
    data. Ganti filter → kembali ke halaman 1.

22. SHORTCUTS NEED THREE DOORS: Panel bantuan wajib bisa dibuka dari ikon
    ? di topbar, item di user menu, DAN tombol ?. Petunjuk pintasan juga
    ditempel in-context (footer row menu, bulk bar) — panel bantuan saja
    tidak cukup untuk membuat orang hafal.
```
