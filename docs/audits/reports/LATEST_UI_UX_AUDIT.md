# 📋 Hasil Audit UI/UX & Browser Testing Otomatis

**Tanggal & Waktu**: 7/10/2026, 11.57.56
**Target Base URL**: `http://127.0.0.1:8000`
**Total Halaman Diaudit**: 6

## 1. Ringkasan Status Halaman

| Halaman | Load Time | A11y Violations | JS Console Errors | Status |
| :--- | :---: | :---: | :---: | :---: |
| **Dashboard Utama** (`/dashboard`) | 614ms | 0 issue | 1 error | 🟡 Review |
| **Gudang - Transfer Barang** (`/warehouse/transfers`) | 667ms | 4 issue | 0 error | 🟡 Review |
| **Gudang - Form Buat Transfer** (`/warehouse/transfers/create`) | 828ms | 3 issue | 0 error | 🟡 Review |
| **Gudang - Stok Barang** (`/warehouse/stock`) | 815ms | 3 issue | 0 error | 🟡 Review |
| **Gudang - Scan QR/Barcode** (`/warehouse/scan`) | 684ms | 2 issue | 0 error | 🟡 Review |
| **Gudang - Histori & Log** (`/warehouse/history`) | 1045ms | 4 issue | 0 error | 🟡 Review |

## 2. Detail Analisa & Accessibility (A11y)

### 📄 Dashboard Utama (`/dashboard`)
- **Load Time**: `614 ms`
- **Console Errors**: ⚠️ Failed to load resource: the server responded with a status of 404 (Not Found)
- **Accessibility**: ✅ Lolos standar WCAG 2.1 AA
- **Snapshot Desktop**: `docs/audits/reports/artifacts/dashboard_utama_desktop.png`
- **Snapshot Mobile**: `docs/audits/reports/artifacts/dashboard_utama_mobile.png`

### 📄 Gudang - Transfer Barang (`/warehouse/transfers`)
- **Load Time**: `667 ms`
- **Console Errors**: ✅ Bersih
- **Accessibility Warnings (4)**:
  - **[CRITICAL]** Buttons must have discernible text (`button-name`): Ensure buttons have discernible text
  - **[SERIOUS]** Elements must meet minimum color contrast ratio thresholds (`color-contrast`): Ensure the contrast between foreground and background colors meets WCAG 2 AA minimum contrast ratio thresholds
  - **[CRITICAL]** Form elements must have labels (`label`): Ensure every form element has a label
  - **[CRITICAL]** Select element must have an accessible name (`select-name`): Ensure select element has an accessible name
- **Snapshot Desktop**: `docs/audits/reports/artifacts/gudang___transfer_barang_desktop.png`
- **Snapshot Mobile**: `docs/audits/reports/artifacts/gudang___transfer_barang_mobile.png`

### 📄 Gudang - Form Buat Transfer (`/warehouse/transfers/create`)
- **Load Time**: `828 ms`
- **Console Errors**: ✅ Bersih
- **Accessibility Warnings (3)**:
  - **[CRITICAL]** Buttons must have discernible text (`button-name`): Ensure buttons have discernible text
  - **[SERIOUS]** Elements must meet minimum color contrast ratio thresholds (`color-contrast`): Ensure the contrast between foreground and background colors meets WCAG 2 AA minimum contrast ratio thresholds
  - **[CRITICAL]** Select element must have an accessible name (`select-name`): Ensure select element has an accessible name
- **Snapshot Desktop**: `docs/audits/reports/artifacts/gudang___form_buat_transfer_desktop.png`
- **Snapshot Mobile**: `docs/audits/reports/artifacts/gudang___form_buat_transfer_mobile.png`

### 📄 Gudang - Stok Barang (`/warehouse/stock`)
- **Load Time**: `815 ms`
- **Console Errors**: ✅ Bersih
- **Accessibility Warnings (3)**:
  - **[CRITICAL]** Required ARIA attributes must be provided (`aria-required-attr`): Ensure elements with ARIA roles have all required ARIA attributes
  - **[CRITICAL]** Buttons must have discernible text (`button-name`): Ensure buttons have discernible text
  - **[SERIOUS]** Elements must meet minimum color contrast ratio thresholds (`color-contrast`): Ensure the contrast between foreground and background colors meets WCAG 2 AA minimum contrast ratio thresholds
- **Snapshot Desktop**: `docs/audits/reports/artifacts/gudang___stok_barang_desktop.png`
- **Snapshot Mobile**: `docs/audits/reports/artifacts/gudang___stok_barang_mobile.png`

### 📄 Gudang - Scan QR/Barcode (`/warehouse/scan`)
- **Load Time**: `684 ms`
- **Console Errors**: ✅ Bersih
- **Accessibility Warnings (2)**:
  - **[CRITICAL]** Buttons must have discernible text (`button-name`): Ensure buttons have discernible text
  - **[SERIOUS]** Elements must meet minimum color contrast ratio thresholds (`color-contrast`): Ensure the contrast between foreground and background colors meets WCAG 2 AA minimum contrast ratio thresholds
- **Snapshot Desktop**: `docs/audits/reports/artifacts/gudang___scan_qr_barcode_desktop.png`
- **Snapshot Mobile**: `docs/audits/reports/artifacts/gudang___scan_qr_barcode_mobile.png`

### 📄 Gudang - Histori & Log (`/warehouse/history`)
- **Load Time**: `1045 ms`
- **Console Errors**: ✅ Bersih
- **Accessibility Warnings (4)**:
  - **[CRITICAL]** Elements must only use supported ARIA attributes (`aria-allowed-attr`): Ensure an element's role supports its ARIA attributes
  - **[CRITICAL]** Buttons must have discernible text (`button-name`): Ensure buttons have discernible text
  - **[SERIOUS]** Elements must meet minimum color contrast ratio thresholds (`color-contrast`): Ensure the contrast between foreground and background colors meets WCAG 2 AA minimum contrast ratio thresholds
  - **[CRITICAL]** Form elements must have labels (`label`): Ensure every form element has a label
- **Snapshot Desktop**: `docs/audits/reports/artifacts/gudang___histori___log_desktop.png`
- **Snapshot Mobile**: `docs/audits/reports/artifacts/gudang___histori___log_mobile.png`

