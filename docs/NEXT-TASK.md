# NEXT-TASK.md — Daftar Pekerjaan Berlanjutan

**Tanggal analisa:** 2026-06-27  
**Status keseluruhan:** Sprint 8.4 - 8.9 DONE, Sprint 8.5 & 8.6 PARSIAL, Sprint 14-15 TODO  
**Branch:** dev

---

## 🔴 PRIORITAS TINGGI — Selesaikan Sekarang

### Sprint 8.5 — Design System Konsistensi UI

#### S8.5-T001 — Refactor `surveys/queue.blade.php` ke Design System
**Status:** ✅ SELESAI  
**Deskripsi:**
- Halaman antrean survey masih pakai Tailwind hardcoded (`bg-white`, `bg-slate-*`, `text-slate-*`)
- Harus migrasi ke CSS vars design system (`var(--color-surface)`, `var(--color-text-main)`, dll.)
- Ganti label status: "WAITING" → "Menunggu Survey", "IN PROGRESS" → "Proses Survey"
- SLA countdown: ganti placeholder dengan `<x-countdown-timer>` komponen yang benar
- Tabel di mobile: collapse ke card view (tidak bisa 9 kolom di HP)

**File yang diubah:**
- `resources/views/surveys/queue.blade.php`

**Acceptance Criteria:**
- [ ] Tidak ada `bg-slate-*` / `text-slate-*` tersisa
- [ ] Label status Bahasa Indonesia
- [ ] Countdown aktif & reactive (Alpine.js)
- [ ] Mobile-friendly (responsive)

---

#### S8.5-T002 — Refactor `customers/tabs/_survey.blade.php` ke Design System
**Status:** ✅ SELESAI (SUDAH SESUAI)  
**Deskripsi:**
- Tab survey di halaman detail pelanggan masih hardcoded slate colors
- Migrasi ke design system vars
- Localize badge status: `completed` → "Selesai", `failed` → "Tidak Layak", `pending` → "Menunggu"

**File yang diubah:**
- `resources/views/customers/tabs/_survey.blade.php`

**Acceptance Criteria:**
- [ ] Tidak ada hardcoded slate colors
- [ ] Badge status Bahasa Indonesia

---

#### S8.5-T003 — Refactor `customers/tabs/_installation.blade.php` ke Design System
**Status:** ✅ SELESAI (SUDAH SESUAI)  
**Deskripsi:**
- Tab pemasangan sama masalahnya dengan _survey.blade.php
- Migrasi ke design system vars
- Localize badge installation_status

**File yang diubah:**
- `resources/views/customers/tabs/_installation.blade.php`

**Acceptance Criteria:**
- [ ] Tidak ada hardcoded slate colors
- [ ] Badge status Bahasa Indonesia

---

#### S8.5-T004 — `capture="environment"` di Semua Form Foto
**Status:** ✅ SELESAI  
**Deskripsi:**
- Sekarang `capture="environment"` hanya di `tasks/own.blade.php`
- Semua input foto di app untuk teknisi lapangan harus punya atribut ini
- Tujuan: kamera langsung terbuka di mobile HP

**File yang diaudit & diupdate:**
- `customers/tabs/_survey.blade.php`
- `customers/tabs/_installation.blade.php`
- Form laporan survey (jika ada halaman terpisah)
- Form laporan pemasangan (jika ada halaman terpisah)

**Acceptance Criteria:**
- [ ] Semua `<input type="file">` untuk foto punya `capture="environment"`
- [ ] Di browser mobile, klik input foto langsung buka kamera belakang

---

### Sprint 8.6 — SLA Waiting Phase Countdown

#### S8.6-T003 — Overdue Indicator di FOP Stat Cards
**Status:** ❌ BELUM DIKERJAKAN  
**Deskripsi:**
- FOP perlu tahu berapa pelanggan yang sudah overdue SLA waiting untuk prioritaskan
- Tambah indikator di stat cards FOP Dashboard (atau badge merah di kolom antrean)
- Tampilkan jumlah overdue dengan warna error/merah

**File yang diubah:**
- `resources/views/fop/dashboard.blade.php` (stat cards section)
- `app/Http/Controllers/FopDashboardController.php` (kalkulasi overdue count)

**Acceptance Criteria:**
- [ ] Stat card menampilkan jumlah overdue (atau badge merah)
- [ ] Warna merah/error saat ada overdue
- [ ] FOP bisa langsung tahu prioritas tanpa scroll

---

### Verifikasi & Testing

#### VERIFY: S8.9-T004 — Teknisi Laporan Tidak Auto-Update Customer Status
**Status:** ⚠️ PERLU VERIFIKASI  
**Deskripsi:**
- S8.9-T004 claims teknisi laporan **tidak** auto-update customer status
- Sebaliknya: Task status = `selesai`, `fop_review_status = pending` (waiting FOP approve)
- FOP approve baru trigger customer transition
- **PERLU CEK DI KODE** apakah ini benar atau masih ada path yang auto-update

**File yang perlu diaudit:**
- `app/Http/Controllers/TaskSurveyReportController.php` — method `store()`
- `app/Http/Controllers/TaskInstallationReportController.php` — method `store()`
- `app/Services/CustomerWorkflowService.php` — method `transition()`

**Acceptance Criteria:**
- [ ] Teknisi submit laporan → Task status = selesai, customer status TETAP (tidak berubah)
- [ ] Tidak ada path yang auto-update customer status langsung
- [ ] FOP review baru trigger customer transition

---

#### CLARIFY: S8.7 — Delete Kalender FOP atau Tetap?
**Status:** ⚠️ DECISION PENDING  
**Deskripsi:**
- S8.7 (Kalender Scheduler FOP) sudah ada route `/fop/calendar`
- S8.9-T001 membuat `/tasks` (List Task) yang menggantikan fungsi calendar
- User request: "hapus Kalender Jadwal FOP karena sudah ada (namun Kanban Task Scheduler masih ada)"
- **ACTION**: Clarify user intent:
  - Option A: Delete `/fop/calendar` route + controller + view sepenuhnya
  - Option B: Refactor calendar menjadi list view (redirect ke `/tasks`)
  - Option C: Tetap kedua (redundant tapi OK)

**File yang mungkin didelete:**
- `routes/web.php` — route `/fop/calendar` dan `/fop/calendar/events`
- `app/Http/Controllers/FopCalendarController.php`
- `resources/views/fop/calendar.blade.php`
- Sidebar link ke FOP calendar (app.blade.php)

---


## 📋 SUMMARY TABEL

| Sprint | Task | Status | Prioritas | Owner | Est. Hari |
|--------|------|--------|-----------|-------|-----------|
| S8.5 | T001: Design system surveys/queue | ❌ | 🔴 HIGH | - | 2-3 hari |
| S8.5 | T002: Design system _survey tab | ❌ | 🔴 HIGH | - | 1-2 hari |
| S8.5 | T003: Design system _installation tab | ❌ | 🔴 HIGH | - | 1-2 hari |
| S8.5 | T004: capture="environment" all forms | ❌ | 🔴 HIGH | - | 1 hari |
| S8.6 | T003: Overdue indicator stat cards | ❌ | 🔴 HIGH | - | 1 hari |
| S8.7 | CLARIFY: Delete calendar atau tetap? | ⚠️ | 🟡 MEDIUM | USER | — |
| S8.9 | VERIFY: T004 No auto-update flow | ⚠️ | 🟡 MEDIUM | DEV | 1 hari |


**Total effort estimate (bulan ini):**
- Sprint 8.5: 5-6 hari
- Sprint 8.6: 1 hari
- Sprint 8.7: PENDING (clarify)
- Verifikasi S8.9: 1 hari
- **Subtotal: ~7-8 hari**

**Total effort (sampai MVP siap produksi):**
- Sprint 8.5-8.7: ~8 hari

- **Total: ~8 hari**

---

## 🚀 RECOMMENDED NEXT STEPS

1. **Today/Tomorrow (Immediate):**
   - [ ] Clarify dengan user: delete `/fop/calendar` atau tetap? (S8.7 decision)
   - [ ] Verify S8.9-T004 tidak ada auto-update di TaskSurveyReportController & TaskInstallationReportController
   - [ ] Start S8.5-T001 (design system surveys/queue)

2. **This Week (Sprint 8.5):**
   - [ ] S8.5-T001: surveys/queue design system + countdown
   - [ ] S8.5-T002: _survey tab design system
   - [ ] S8.5-T003: _installation tab design system
   - [ ] S8.5-T004: capture="environment" audit & fix

3. **Next Week (Sprint 8.6+):**
   - [ ] S8.6-T003: Overdue indicator FOP dashboard
   - [ ] Close S8.7 (delete or keep calendar)
   - [ ] Run full test suite & npm build
   - [ ] Commit progress to dev branch


---

## 📝 NOTES

- Semua sprint 8.1-8.4 dan 8.9 **DONE**. Implementasi sesuai brief.
- Sprint 8.5 dan 8.6 partial (mostly checklist unchecked, tidak semua feature selesai).
- Sprint 8.7 (kalender) **marked DONE** tapi perlu clarify delete atau tetap.
- S8.9-T004 perlu verifikasi di kode (claim sudah DONE tapi perlu ditest).
- Gap kritis tidak ada setelah S8.9 completion; implementasi sudah sesuai brief.

---

**Last updated:** 2026-06-27  
**By:** Claude Code (Analysis)  
**Revision:** 1.0
