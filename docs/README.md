# Dokumentasi Project WHUSNET Operasional

Dokumentasi ini menjelaskan fitur yang sudah tersedia pada aplikasi WHUSNET Operasional. Struktur dibuat per fitur agar pengembangan berikutnya lebih mudah dilacak.

## Struktur Dokumentasi

| Folder / File | Isi |
| --- | --- |
| `docs/database-schema.md` | Schema database aktual berdasarkan migration Laravel. |
| `docs/flowchart-system.md` | Flowchart sistem utama dari dashboard, master, pelanggan, dan API penunjang. |
| `docs/data-pelanggan/` | Dokumentasi fitur daftar, registrasi, edit, detail, dan import pelanggan. |
| `docs/master/` | Dokumentasi master wilayah, paket layanan, dan status langganan. |
| `docs/dashboard/` | Dokumentasi dashboard operasional. |
| `docs/penunjang/` | Dokumentasi API dependent dropdown dan import pelanggan. |

## Fitur Utama

1. Dashboard operasional.
2. Data Pelanggan:
   - Daftar pelanggan.
   - Registrasi pelanggan.
   - Detail pelanggan 12 tab operasional.
   - Edit pelanggan.
   - Import batch pelanggan.
3. Master:
   - Master wilayah.
   - Master paket layanan.
   - Master status langganan.
4. Penunjang:
   - API kota ke kecamatan.
   - API kecamatan ke desa.
   - Seeder data master.

## Komponen Kode Terkait

| Area | File utama |
| --- | --- |
| Route | `routes/web.php` |
| Dashboard | `app/Http/Controllers/DashboardController.php`, `resources/views/dashboard.blade.php` |
| Pelanggan | `app/Http/Controllers/CustomerController.php`, `app/Models/Customer.php`, `resources/views/customers/*` |
| Master Paket | `app/Http/Controllers/Master/InternetPackageController.php`, `app/Models/InternetPackage.php` |
| Master Status | `app/Http/Controllers/Master/SubscriptionStatusController.php`, `app/Models/SubscriptionStatus.php` |
| Master Wilayah | `app/Http/Controllers/Master/RegionController.php`, `app/Models/City.php`, `app/Models/District.php`, `app/Models/Village.php` |

## Sprint 8 — FOP Task Management & Design System

Sprint 8 menambahkan modul task scheduling dan management untuk FOP dengan real-time updates dan design system konsistensi:

| Sprint | Fitur | Dokumentasi |
|--------|-------|------------|
| S8.1 | FOP Dashboard Overview | [FOP Dashboard](sprint-8/fop-dashboard.md) |
| S8.2-S8.3 | Kanban Task Scheduler | [Kanban](sprint-8/kanban-task-scheduler.md) |
| S8.5 | Design System UI | [Design System](sprint-8/design-system-ui.md) |
| S8.6 | Overdue Indicator | [Overdue](sprint-8/overdue-indicator.md) |
| S8.7 | Calendar Scheduler | [Calendar](sprint-8/calendar-scheduler.md) |
| S8.9 | Task Workflow & Approvals | [Workflow](sprint-8/task-workflow.md) |

**Overview lengkap:** [Sprint 8 README](sprint-8/README.md)

---

## Catatan Scope Implementasi Saat Ini

Aplikasi sudah menyediakan fondasi operasional ISP untuk registrasi dan monitoring pelanggan. Modul Onboarding (Survey, Verifikasi, Pemasangan, Aktivasi) sudah mengimplementasikan State Machine (`CustomerWorkflowService`) dengan menyimpan histori dan data teknis pada tabel transaksi terpisah seperti `customer_surveys`, `customer_installations`, dan `customer_technical_details`.

Sprint 8 menambahkan modul Task Management untuk FOP dengan real-time updates, task scheduler dalam format kanban dan kalender, design system konsistensi UI, dan workflow approval gates yang memastikan tidak ada auto-transition customer status (hanya FOP approval yang trigger transition).
