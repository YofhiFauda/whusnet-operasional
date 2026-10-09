# 📋 Template Laporan UI/UX & Browser Audit

**Modul**: [Nama Modul]  
**Tanggal**: YYYY-MM-DD  
**Target URL**: `/path/ke/halaman`  
**Role Penguji**: `Superadmin` / `Admin POP` / `Teknisi` / `Finance`  
**Browser Engine**: Chromium via Playwright & Antigravity Agent  

---

## 1. Skor Ringkasan & Heuristik
| Kategori | Status | Skor (1-10) | Keterangan |
| :--- | :---: | :---: | :--- |
| **Visual Hierarchy & Typography** | 🟢 / 🟡 / 🔴 | -/10 | Font hierarchy, readability, spacing |
| **Form Usability & Validation** | 🟢 / 🟡 / 🔴 | -/10 | Feedback error, label, autofocus |
| **Accessibility (WCAG 2.1 AA)** | 🟢 / 🟡 / 🔴 | -/10 | Contrast ratio, aria-labels, alt-text |
| **Mobile & Responsive Layout** | 🟢 / 🟡 / 🔴 | -/10 | Table scroll, touch target, wrapping |
| **Performance & Console Cleanliness** | 🟢 / 🟡 / 🔴 | -/10 | Bebas JS errors, response time |

---

## 2. Temuan Masalah & Rekomendasi (Categorized)

### 🔴 High / Critical
- **[A11y/UX] Judul Issue**
  - **Lokasi Blade**: `resources/views/...`
  - **Deskripsi**: Penjelasan masalah
  - **Rekomendasi**: Solusi perbaikan kode

### 🟡 Medium
- **[Visual/Contrast] Judul Issue**
  - **Lokasi Blade**: `resources/views/...`
  - **Deskripsi**: Penjelasan masalah
  - **Rekomendasi**: Solusi perbaikan kode

### 🟢 Low / Polish
- **[Micro-interaction] Judul Issue**
  - **Rekomendasi**: Solusi perbaikan kode

---

## 3. Bukti Visual
- Desktop HD (1440x900): `artifacts/desktop.png`
- Mobile (375x812): `artifacts/mobile.png`

---

## 4. Rekomendasi Code Diff (Actionable)
```diff
- kode_lama
+ kode_baru
```
