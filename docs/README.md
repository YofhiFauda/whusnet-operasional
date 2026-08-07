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
| `docs/fop-task/` | Dokumentasi modul FOP Task (tiket kerja lapangan, team harian, dashboard FOP). |
| `docs/ticketing/` | Dokumentasi modul Ticketing (tiket internal perusahaan MTN/C-REQ, auto-sync ke FOP Task). |
| `docs/billing-pembayaran/` | Dokumentasi modul tagihan (Invoice) dan pembayaran (Payment). |
| `docs/rbac/` | Dokumentasi RBAC (Role, Permission, Feature/Action, Scope POP). |
| `docs/customer-lifecycle/` | Dokumentasi Customer Verifikasi & Onboarding Lifecycle (registrasi→survey→pemasangan→aktivasi). |
| `docs/task-teknisi/` | Dokumentasi Task Teknisi (eksekusi lapangan: checklist, evidence, review FOP). |
| `docs/master/sla-timeline/` | Dokumentasi Master Timeline SLA (batas waktu wajib ditangani per jenis tiket, per paket internet). |
| `docs/plan/qr-code/` | **RANCANGAN (belum diimplementasi)** — QR Code pelanggan untuk pembayaran, absen teknisi, ticketing, dan login pelanggan (+ PIN). |

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

## FOP Task & Billing — Dokumentasi Modul

| Modul | Dokumentasi |
|-------|-------------|
| FOP Task (tiket kerja, team harian, dashboard) | [docs/fop-task/README.md](fop-task/README.md) |
| Ticketing (tiket internal perusahaan MTN/C-REQ, auto-sync ke FOP Task) | [docs/ticketing/README.md](ticketing/README.md) |
| Billing & Pembayaran (invoice, payment) | [docs/billing-pembayaran/README.md](billing-pembayaran/README.md) |
| RBAC (role, permission, scope POP) | [docs/rbac/README.md](rbac/README.md) |
| Customer Verifikasi & Onboarding Lifecycle | [docs/customer-lifecycle/README.md](customer-lifecycle/README.md) |
| Task Teknisi (eksekusi lapangan) | [docs/task-teknisi/README.md](task-teknisi/README.md) |
| Master Timeline SLA (batas waktu wajib ditangani per paket) | [docs/master/sla-timeline/README.md](master/sla-timeline/README.md) |
| QR Code Pelanggan + PIN — **RANCANGAN, belum ada kodenya** | [docs/plan/qr-code/rancangan-qr-pelanggan.md](plan/qr-code/rancangan-qr-pelanggan.md) |

---

## Catatan Scope Implementasi Saat Ini

Aplikasi sudah menyediakan fondasi operasional ISP untuk registrasi dan monitoring pelanggan. Modul Onboarding (Survey, Verifikasi, Pemasangan, Aktivasi) sudah mengimplementasikan State Machine (`CustomerWorkflowService`) dengan menyimpan histori dan data teknis pada tabel transaksi terpisah seperti `customer_surveys`, `customer_installations`, dan `customer_technical_details`.

Modul Task Management FOP (dulu Sprint 8, arsitektur kanban/kalender awal sudah diganti — lihat [docs/fop-task/README.md](fop-task/README.md#konsep-inti) & `archive/`) sekarang jalan dengan auto-team formation, status real-time ter-derive dari Task eksekusi (`TaskObserver`), Riwayat Lengkap + SLA dual-cycle per tiket (Task 10), dan workflow approval gates yang memastikan tidak ada auto-transition customer status tanpa aksi eksplisit FOP/Admin (approve/reject/revisi). Perubahan/fix terbaru per modul dicatat di README masing-masing folder — jangan cari "status sprint" di sini, cek `Last updated` di README tiap modul.
