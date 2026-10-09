<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Services\PermissionGeneratorService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Dynamically generate permissions first from config/rbac.php
        app(PermissionGeneratorService::class)->generate();

        // Salinan PERSIS konfigurasi Role Matrix yang diatur lewat UI
        // (snapshot DB 2026-10-02) — seeder ini sekarang mengikuti UI, bukan
        // sebaliknya. Sengaja daftar kode eksplisit, bukan wildcard `x.*`:
        // wildcard otomatis menyapu permission BARU ke role yang di UI justru
        // sengaja dipangkas (mis. Admin). Permission baru dari feature seeder
        // wajib didaftarkan manual ke role yang butuh, di sini.
        //
        // Beberapa keputusan UI yang membalik asumsi lama (disengaja, jangan
        // "dibetulkan" tanpa konfirmasi user):
        // - Admin dapat `customers.detail.devices.view_sensitive` &
        //   `collector_worksheet.approve`, tapi tidak lagi pegang roles/users/
        //   tickets/fop_tasks/warehouse operasional, maupun `dashboard.view`.
        // - NOC & FOP sekarang pegang administrasi `task.*` penuh (approve,
        //   assign.team, manage, dll) plus NOC tambahan pegang roles/users/
        //   pops/master_distribusi/master_wilayah/packages CRUD dan
        //   `warehouse_stock_request.*` (approve/reject persetujuan FOP).
        //   FOP sebaliknya lepas `tickets.cancel/create/update` dasar.
        // - Helpdesk sekarang ikut pegang `creq_billing_verification.*` &
        //   `customer_registration_verification.*` (dulu cuma
        //   `customer_service`) plus `customers.create/deactivate` — irisan
        //   sengaja besar dengan role `customer_service`.
        // - Business Development dapat `customer_acquisitions.installation_fee.update`
        //   langsung (dulu sengaja lewat jalur role Master Kategori Paket saja).
        // - Sales & Teknisi tanpa `dashboard.view`; Sales tanpa `tickets.*`.
        // - Role `customer_service` (dibuat lewat UI) — lihat RoleSeeder.
        // - Role `admin_gudang` (BARU, dibuat lewat UI 2026-10-02) — operator
        //   gudang Pusat: satu-satunya role selain Owner yang pegang
        //   `warehouse_transfer.create` (kirim antar gudang) &
        //   `warehouse_stock_request.approve/.reject` (persetujuan Pusat atas
        //   permintaan cabang) serta `warehouse_transfer_invoice.view` (harga
        //   satuan). Lihat RoleSeeder untuk definisi role-nya.
        //
        // Role per cabang (mis. "PIC Gudang Jetis") sengaja TIDAK diseed:
        // role global, cabang dibatasi lewat POP scope (CLAUDE.md RBAC).
        $permissionsByRole = [
            'owner' => [
                '*',
            ],

            'atasan' => [
                'agents.view',
                'audit_logs.export', // assuming audit_logs has export
                'audit_logs.view',
                'business_customers.view',
                'business_development_verification.view',
                'cash_deposit.validate',
                'cash_deposit.view',
                'collector_payment_report.export',
                'collector_payment_report.view',
                'collector_report.export',
                'collector_report.view',
                'customer_acquisitions.view',
                'customers.detail.devices.view',
                'customers.detail.installation.view',
                'customers.detail.survey.view',
                'customers.detail.view',
                'customers.failed.view',
                'customers.terminated.view',
                'customers.view',
                'dashboard.view',
                'fop_analytics.view',
                'fop_tasks.view',
                'invoices.print',
                'invoices.view',
                'master_distribusi.view',
                'master_status_pelanggan.view',
                'master_wilayah.view',
                'noc_dashboard.performance.view', // Atasan evaluasi performa individu Helpdesk/NOC
                'noc_dashboard.view', // Monitoring tracking NOC, gak akses Worksheet NOC (itu kerjaan NOC)
                'package_restrictions.view',
                'packages.view',
                'payments.view',
                'pops.view',
                'qr_scan_logs.view', // Dashboard anomali scan QR (docs/plan/qr-code/)
                'reports.export',
                'reports.view',
                'roles.view',
                'sales_omset_dashboard.view',
                'sla_timeline.view',
                'tickets.dibatalkan.view',
                'tickets.selesai.view',
                'tickets.view', // Atasan cuma memantau — gak ikut ngirim tiket
                'users.view',
                'warehouse.view',
                'warehouse_custody.view',
                'warehouse_issue.view',
                'warehouse_report.view',
                'warehouse_stock_request.view',
                'warehouse_traceability.view',
                'warehouse_transfer.view',
            ],

            'admin' => [
                'billing_waivers.create',
                'billing_waivers.delete',
                'cash_deposit.create',
                'collector_payment_report.export',
                'collector_payment_report.view',
                'collector_report.export',
                'collector_report.view',
                'collector_worksheet.approve',
                'collector_worksheet.assign',
                'collector_worksheet.deposit',
                'collector_worksheet.print',
                'collector_worksheet.upload',
                'collector_worksheet.validate',
                'collector_worksheet.view',
                'customer_balance.view', // Saldo Pelanggan (ADHOC-92) — read-only, keuangan
                'customers.detail.address.view',
                'customers.detail.devices.view',
                'customers.detail.devices.view_sensitive',
                'customers.detail.documents.download',
                'customers.detail.documents.view',
                'customers.detail.identity.view',
                'customers.detail.packages.view',
                'customers.detail.view',
                'customers.failed.view', // List Pelanggan Gagal — permission sendiri, bukan wildcard customers.detail.*
                'customers.qr.create',
                'customers.qr.print',
                'customers.qr.view',
                'customers.terminated.view', // List Pelanggan Putus — permission sendiri, bukan wildcard customers.detail.*
                'customers.view',
                'invoices.approve',
                'invoices.create',
                'invoices.delete',
                'invoices.print',
                'invoices.update',
                'invoices.view',
                'master_distribusi.view',
                'master_rekening.create',
                'master_rekening.update',
                'master_rekening.view',
                'master_status_pelanggan.view',
                'master_wilayah.view',
                'packages.view',
                'payments.approve',
                'payments.create',
                'payments.delete',
                'payments.reject',
                'payments.update',
                'payments.validate',
                'payments.view',
                'pops.update',
                'pops.view',
                'reports.export',
                'reports.print',
                'reports.view',
                'termination_reasons.create',
                'termination_reasons.delete',
                'termination_reasons.update',
                'termination_reasons.view',
                'tickets.qr.create',
            ],

            // NOC sekarang (2026-10-02) juga pegang administrasi task.* penuh
            // (sinkron sama FOP) plus roles/users/pops/master_distribusi/
            // master_wilayah/packages CRUD & warehouse_stock_request
            // approve/reject (persetujuan atas pengajuan FOP) — keputusan UI,
            // bukan salah ketik.
            'noc' => [
                'customers.create',
                'customers.deactivate',
                'customers.detail.address.update',
                'customers.detail.address.view',
                'customers.detail.devices.retrieve',
                'customers.detail.devices.update',
                'customers.detail.devices.update_sensitive',
                'customers.detail.devices.view',
                'customers.detail.devices.view_sensitive',
                'customers.detail.documents.download',
                'customers.detail.documents.upload',
                'customers.detail.documents.view',
                'customers.detail.identity.update',
                'customers.detail.identity.view',
                'customers.detail.installation.activate',
                'customers.detail.installation.reject',
                'customers.detail.installation.update',
                'customers.detail.installation.validate',
                'customers.detail.installation.view',
                'customers.detail.packages.update',
                'customers.detail.packages.view',
                'customers.detail.survey.reject',
                'customers.detail.survey.update',
                'customers.detail.survey.validate',
                'customers.detail.survey.view',
                'customers.detail.view',
                'customers.failed.view',
                'customers.terminated.view',
                'customers.update',
                'customers.view',
                'fop_tasks.cancel',
                'fop_tasks.create',
                'fop_tasks.delete',
                'fop_tasks.update',
                'fop_tasks.update_sensitive',
                'fop_tasks.view',
                'invoices.view',
                'master_distribusi.create',
                'master_distribusi.delete',
                'master_distribusi.update',
                'master_distribusi.view',
                'master_status_pelanggan.view',
                'master_wilayah.create',
                'master_wilayah.update',
                'master_wilayah.view',
                'noc_dashboard.performance.view',
                'noc_dashboard.view',
                'noc_worksheet.view',
                'packages.create',
                'packages.update',
                'packages.view',
                'payments.view',
                'pops.create',
                'pops.delete',
                'pops.update',
                'pops.view',
                'roles.create',
                'roles.delete',
                'roles.update',
                'roles.view',
                'sla_timeline.view',
                'task.approve',
                'task.assign.team',
                'task.cancel',
                'task.conflict.override',
                'task.edit.type',
                'task.execute',
                'task.lookup',
                'task.manage',
                'task.reject',
                'task.view.all',
                'task.view.own',
                'ticket_issue_categories.create',
                'ticket_issue_categories.delete',
                'ticket_issue_categories.update',
                'ticket_issue_categories.view',
                'tickets.cancel',
                'tickets.create',
                'tickets.dibatalkan.view',
                'tickets.history.export',
                'tickets.history.view',
                'tickets.qr.create',
                'tickets.selesai.view',
                'tickets.update',
                'tickets.view',
                'users.create',
                'users.delete',
                'users.update',
                'users.view',
                'warehouse_stock_request.approve',
                'warehouse_stock_request.cancel',
                'warehouse_stock_request.create',
                'warehouse_stock_request.reject',
                'warehouse_stock_request.view',
            ],

            // Helpdesk sekarang (2026-10-02) ikut pegang verifikasi registrasi
            // & verifikasi biaya C-REQ bareng `customer_service` (irisan
            // besar sengaja, keputusan UI) plus customers.create/deactivate.
            'helpdesk' => [
                'creq_billing_verification.approve',
                'creq_billing_verification.reject',
                'creq_billing_verification.view',
                'customer_registration_verification.approve',
                'customer_registration_verification.reject',
                'customer_registration_verification.view',
                'customers.create',
                'customers.deactivate',
                'customers.detail.address.update',
                'customers.detail.address.view',
                'customers.detail.devices.update_sensitive',
                'customers.detail.devices.view',
                'customers.detail.devices.view_sensitive',
                'customers.detail.documents.download',
                'customers.detail.documents.upload',
                'customers.detail.documents.view',
                'customers.detail.identity.update',
                'customers.detail.identity.view',
                'customers.detail.installation.view',
                'customers.detail.packages.update',
                'customers.detail.packages.view',
                'customers.detail.survey.view',
                'customers.detail.view',
                'customers.failed.view',
                'customers.qr.view', // Lihat status token QR pelanggan (docs/plan/qr-code/)
                'customers.terminated.view',
                'customers.update',
                'customers.view',
                'invoices.print',
                'invoices.view',
                'master_distribusi.view',
                'packages.view',
                'payments.view',
                'pops.view',
                'qr_scan.view', // Scan QR Internal (2026-08-27) — shortcut bikin tiket dari QR pelanggan
                'task.execute',
                'task.view.own',
                'tickets.cancel',
                'tickets.create',
                'tickets.dibatalkan.view',
                'tickets.history.export',
                'tickets.history.view',
                'tickets.qr.create',
                'tickets.selesai.view',
                'tickets.update',
                'tickets.view',
            ],

            'customer_service' => [
                'creq_billing_verification.approve',
                'creq_billing_verification.reject',
                'creq_billing_verification.view',
                'customer_registration_verification.approve',
                'customer_registration_verification.reject',
                'customer_registration_verification.view',
                'customers.create',
                'customers.deactivate',
                'customers.detail.address.update',
                'customers.detail.address.view',
                'customers.detail.devices.retrieve',
                'customers.detail.devices.update',
                'customers.detail.devices.update_sensitive',
                'customers.detail.devices.view',
                'customers.detail.devices.view_sensitive',
                'customers.detail.documents.delete',
                'customers.detail.documents.download',
                'customers.detail.documents.upload',
                'customers.detail.documents.view',
                'customers.detail.identity.update',
                'customers.detail.identity.view',
                'customers.detail.installation.activate',
                'customers.detail.installation.reject',
                'customers.detail.installation.update',
                'customers.detail.installation.validate',
                'customers.detail.installation.view',
                'customers.detail.packages.change',
                'customers.detail.packages.update',
                'customers.detail.packages.view',
                'customers.detail.survey.reject',
                'customers.detail.survey.update',
                'customers.detail.survey.validate',
                'customers.detail.survey.view',
                'customers.detail.view',
                'customers.failed.view',
                'customers.qr.cancel',
                'customers.qr.create',
                'customers.qr.print',
                'customers.qr.view',
                'customers.terminated.view',
                'customers.update',
                'customers.view',
                'master_distribusi.view',
                'packages.view',
                'qr_scan.view',
                'termination_reasons.create',
                'termination_reasons.delete',
                'termination_reasons.update',
                'termination_reasons.view',
                'users.create',
                'users.update',
                'users.view',
            ],

            // FOP sekarang (2026-10-02) pegang administrasi task.* penuh
            // (sinkron sama NOC) tapi lepas tickets.cancel/create/update
            // dasar — tiket dasar sekarang domain NOC/Helpdesk, FOP fokus ke
            // eksekusi task & FopTask.
            'fop' => [
                'customers.detail.address.update',
                'customers.detail.address.view',
                'customers.detail.devices.retrieve',
                'customers.detail.devices.update',
                'customers.detail.devices.update_sensitive',
                'customers.detail.devices.view',
                'customers.detail.devices.view_sensitive',
                'customers.detail.documents.download',
                'customers.detail.documents.upload',
                'customers.detail.documents.view',
                'customers.detail.identity.update',
                'customers.detail.identity.view',
                'customers.detail.installation.activate',
                'customers.detail.installation.reject',
                'customers.detail.installation.update',
                'customers.detail.installation.view',
                'customers.detail.packages.update',
                'customers.detail.packages.view',
                'customers.detail.survey.reject',
                'customers.detail.survey.update',
                'customers.detail.survey.validate',
                'customers.detail.survey.view',
                'customers.detail.view',
                'customers.failed.view',
                'customers.qr.create',
                'customers.qr.print',
                'customers.qr.view', // Lihat status token QR pelanggan (docs/plan/qr-code/)
                'customers.terminated.view',
                'customers.view',
                'fop_analytics.view',
                'fop_tasks.cancel',
                'fop_tasks.create',
                'fop_tasks.delete',
                'fop_tasks.update',
                'fop_tasks.view',
                'master_distribusi.create',
                'master_distribusi.update',
                'master_distribusi.view',
                'master_status_pelanggan.view',
                'master_wilayah.view',
                'pops.create',
                'pops.update',
                'pops.view',
                'qr_scan.view', // Scan QR Internal (2026-08-27)
                'task.approve',
                'task.assign.team',
                'task.cancel',
                'task.conflict.override',
                'task.edit.type',
                'task.lookup',
                'task.manage',
                'task.reject',
                'task.view.all',
                'tasks.qr_attendance.create', // Absen task via scan QR (Fase 3, diseed sekarang)
                'tickets.history.view',
                'tickets.qr.create',
                'tickets.view',
                'warehouse_custody.view',
                'warehouse_traceability.view',
            ],

            'teknisi' => [
                'customers.create',
                'customers.detail.installation.activate',
                'customers.detail.installation.update',
                'customers.detail.installation.view',
                'customers.detail.survey.update',
                'customers.detail.survey.view',
                // Catat pembayaran & setor saldo di lapangan (rancangan-pembayaran-teknisi, ADHOC-122).
                // Pelanggan dibatasi POP scope; permission kolektor.view TIDAK diberikan (worklist kolektor tetap milik kolektor).
                'kolektor.deposit',
                'kolektor.pay',
                'kolektor.qr.pay', // ADHOC-122 — bayar via scan QR → Portal
                'qr_scan.view', // Scan QR Internal (2026-08-27)
                'task.execute',
                'task.view.own',
                'tasks.qr_attendance.create', // Absen task via scan QR (Fase 3, diseed sekarang)
                'tickets.qr.create',
            ],

            // Teknisi yang merangkap PIC gudang cabang (ADHOC-120,
            // docs/plan/warehouse/rancangan-teknisi-pic-gudang-cabang.md).
            // Bagian "tugas teknisi" WAJIB SAMA PERSIS dengan role 'teknisi'
            // di atas (dijaga test sinkron) — ditambah hak gudang cabang.
            'pic_gudang' => [
                // — Tugas teknisi —
                'customers.create',
                'customers.detail.installation.activate',
                'customers.detail.installation.update',
                'customers.detail.installation.view',
                'customers.detail.survey.update',
                'customers.detail.survey.view',
                'kolektor.deposit', // ADHOC-122 — sama dengan role teknisi (dijaga test sinkron)
                'kolektor.pay',     // ADHOC-122
                'kolektor.qr.pay',  // ADHOC-122 — bayar via scan QR → Portal
                'qr_scan.view',
                'task.execute',
                'task.view.own',
                'tasks.qr_attendance.create',
                'tickets.qr.create',

                // — PIC gudang cabang (pelaksana; POP Admin pemeriksa, §7
                // dokumen rancangan). TANPA warehouse_transfer.create (kirim
                // antar gudang = keputusan Pusat), TANPA
                // warehouse_stock_request.approve/.reject (persetujuan tetap
                // Pusat), TANPA warehouse_transfer_invoice.view (harga satuan
                // cuma sisi Pusat). Issue/reassign ke DIRI SENDIRI ditolak di
                // service (InventoryIssueService/InventoryReassignService),
                // bukan lewat pencabutan permission — barang untuk PIC
                // dikeluarkan POP Admin.
                'warehouse.view',
                'warehouse_transfer.view',
                'warehouse_transfer.receive',
                'warehouse_issue.view',
                'warehouse_issue.create',
                'warehouse_custody.view',
                'warehouse_traceability.view',
                'warehouse_reassign.create',
                'warehouse_report.view',
                'warehouse_stock_request.view',
                'warehouse_stock_request.create',
                'warehouse_stock_request.cancel',
                // Lapor rusak/hilang/opname — DIBERIKAN (keputusan user
                // 2026-09-30), DIPANTAU Pusat: laporan gudang menandai baris
                // yang dilaporkan oleh pemegang custody-nya sendiri
                // (kontrol-anti-manipulasi.md §1).
                'warehouse_adjustment.create',
            ],

            // Operator gudang Pusat (BARU, dibuat lewat UI 2026-10-02) — lawan
            // dari pic_gudang: satu-satunya role selain Owner yang pegang
            // warehouse_transfer.create (kirim antar gudang) &
            // warehouse_stock_request.approve/.reject (persetujuan Pusat atas
            // pengajuan cabang) + warehouse_transfer_invoice.view (harga
            // satuan). Lihat RoleSeeder untuk definisi role-nya.
            'admin_gudang' => [
                'warehouse.view',
                'warehouse_adjustment.create',
                'warehouse_custody.view',
                'warehouse_issue.create',
                'warehouse_issue.view',
                'warehouse_reassign.create',
                'warehouse_report.view',
                'warehouse_stock_request.approve',
                'warehouse_stock_request.cancel',
                'warehouse_stock_request.create',
                'warehouse_stock_request.reject',
                'warehouse_stock_request.view',
                'warehouse_traceability.view',
                'warehouse_transfer.create',
                'warehouse_transfer.receive',
                'warehouse_transfer.view',
                'warehouse_transfer_invoice.view',
            ],

            'sales' => [
                'customers.create',
                'customers.detail.address.update',
                'customers.detail.address.view',
                'customers.detail.documents.download',
                'customers.detail.documents.upload',
                'customers.detail.documents.view',
                'customers.detail.identity.update',
                'customers.detail.identity.view',
                'customers.detail.packages.update',
                'customers.detail.packages.view',
                'customers.detail.view',
                'customers.failed.view',
                'customers.registration.skip_survey',
                'customers.terminated.view',
                'customers.update',
                'customers.view',
                'tickets.qr.create',
            ],

            'business_development' => [
                'agents.create',
                'agents.update',
                'agents.view',
                'business_customers.view',
                'business_development_verification.view',
                'customer_acquisitions.installation_fee.update',
                'customer_acquisitions.view',
                'customers.create',
                'customers.detail.address.update',
                'customers.detail.address.view',
                'customers.detail.devices.update',
                'customers.detail.devices.update_sensitive',
                'customers.detail.devices.view',
                'customers.detail.devices.view_sensitive',
                'customers.detail.documents.download',
                'customers.detail.documents.upload',
                'customers.detail.documents.view',
                'customers.detail.identity.update',
                'customers.detail.identity.view',
                'customers.detail.packages.update',
                'customers.detail.packages.view',
                'customers.detail.view',
                'customers.failed.view',
                'customers.qr.create',
                'customers.qr.print',
                'customers.qr.view',
                'customers.terminated.view',
                'customers.update',
                'customers.view',
                'dashboard.view',
                'master_wilayah.create',
                'master_wilayah.update',
                'master_wilayah.view',
                'package_restrictions.update',
                'package_restrictions.view',
                'packages.create',
                'packages.update',
                'packages.view',
                'sales_omset_dashboard.view',
                'termination_reasons.create',
                'termination_reasons.delete',
                'termination_reasons.update',
                'termination_reasons.view',
            ],

            'pop_admin' => [
                'billing_waivers.create',
                'billing_waivers.delete',
                'cash_deposit.create',
                'collector_payment_report.export',
                'collector_payment_report.view',
                'collector_report.export',
                'collector_report.view',
                'collector_worksheet.assign',
                'collector_worksheet.deposit', // Setor atas nama kolektor yang tak bisa akses aplikasinya
                'collector_worksheet.print',
                'collector_worksheet.upload',
                'collector_worksheet.validate',
                'collector_worksheet.view', // Cross check kolektor DALAM scope POP-nya
                'customer_balance.view', // Saldo Pelanggan (ADHOC-92) DALAM scope POP-nya
                'customers.create',
                'customers.deactivate', // Terminasi langganan dalam scope POP-nya
                'customers.detail.address.update',
                'customers.detail.address.view',
                'customers.detail.devices.retrieve',
                'customers.detail.devices.update',
                'customers.detail.devices.view',
                'customers.detail.documents.delete',
                'customers.detail.documents.download',
                'customers.detail.documents.upload',
                'customers.detail.documents.view',
                'customers.detail.identity.update',
                'customers.detail.identity.view',
                'customers.detail.installation.activate',
                'customers.detail.installation.reject',
                'customers.detail.installation.update',
                'customers.detail.installation.validate',
                'customers.detail.installation.view',
                'customers.detail.packages.change',
                'customers.detail.packages.update',
                'customers.detail.packages.view',
                'customers.detail.survey.reject',
                'customers.detail.survey.update',
                'customers.detail.survey.validate',
                'customers.detail.survey.view',
                'customers.detail.view',
                'customers.failed.view', // List Pelanggan Gagal — permission sendiri, bukan wildcard customers.detail.*
                'customers.import.import',
                'customers.import.view',
                'customers.qr.print', // Cetak stiker QR — pop_admin cetak buat cabangnya sendiri
                'customers.qr.view', // Lihat status token QR pelanggan (docs/plan/qr-code/)
                'customers.terminated.view', // List Pelanggan Putus — permission sendiri, bukan wildcard customers.detail.*
                'customers.update',
                'customers.view',
                'dashboard.view',
                'fop_analytics.view',
                'invoices.create',
                'invoices.print',
                'invoices.view',
                'master_distribusi.view',
                'master_status_pelanggan.view',
                'master_wilayah.view',
                'packages.view',
                'payments.create',
                'payments.reject',
                'payments.validate',
                'payments.view',
                'pops.view',
                'reports.export',
                'reports.view',
                'sla_timeline.view',
                'termination_reasons.view', // Isi dropdown alasan di form putus — tidak berhak CRUD master-nya
                'tickets.cancel',
                'tickets.create',
                'tickets.dibatalkan.view',
                'tickets.history.export',
                'tickets.history.view',
                'tickets.qr.create',
                'tickets.selesai.view',
                'tickets.update',
                'tickets.view',
                'warehouse.view',
                'warehouse_adjustment.create', // lapor rusak/hilang/opname cabangnya sendiri
                'warehouse_custody.view',
                'warehouse_issue.create',
                'warehouse_issue.view',
                'warehouse_reassign.create', // reassign custody teknisi resign/cuti cabangnya sendiri
                'warehouse_report.view', // laporan agregat, discope EffectiveAccessService ke cabangnya sendiri
                'warehouse_stock_request.cancel',
                'warehouse_stock_request.create',
                'warehouse_stock_request.view',
                'warehouse_traceability.view',
                'warehouse_transfer.receive',
                'warehouse_transfer.view',
            ],

            'kolektor' => [
                'kolektor.deposit',
                'kolektor.pay',
                'kolektor.qr.pay', // Catat pembayaran via QR → Portal (2026-08-29) — permission TERPISAH dari kolektor.pay, lihat QrFeatureSeeder
                'kolektor.view',
                'kolektor.visit',
                'qr_scan.view', // Scan QR Internal (2026-08-27) — shortcut catat pembayaran dari worklist sendiri
                'tickets.qr.create',
            ],
        ];

        // Retrieve all available permissions grouped by feature
        $allPermissions = Permission::all();
        $allPermissionCodes = $allPermissions->pluck('code')->toArray();

        foreach ($permissionsByRole as $roleCode => $wantedPermissions) {
            $role = Role::where('code', $roleCode)->first();
            if (! $role) {
                continue;
            }

            $finalPermissionCodes = [];

            if (in_array('*', $wantedPermissions)) {
                $finalPermissionCodes = $allPermissionCodes;
            } else {
                foreach ($wantedPermissions as $wp) {
                    if (str_ends_with($wp, '.*')) {
                        $prefix = substr($wp, 0, -2);
                        // Add all permissions starting with prefix
                        foreach ($allPermissionCodes as $ap) {
                            if (str_starts_with($ap, $prefix)) {
                                $finalPermissionCodes[] = $ap;
                            }
                        }
                    } else {
                        if (in_array($wp, $allPermissionCodes)) {
                            $finalPermissionCodes[] = $wp;
                        } else {
                            Log::warning("Seeder: Permission {$wp} not found in database for role {$roleCode}");
                        }
                    }
                }
            }

            $finalPermissionCodes = array_unique($finalPermissionCodes);
            $permissionIds = $allPermissions->whereIn('code', $finalPermissionCodes)->pluck('id')->toArray();

            // Hanya MENAMBAH izin yang ada di seeder, TIDAK mencabut yang lain.
            // Dulu sync() menimpa semua izin tiap db:seed, sehingga matrix yang
            // sudah diedit lewat UI (termasuk pencabutan) kembali ke snapshot
            // seeder setiap deploy. Pencabutan dilakukan lewat Role Matrix saja.
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }

        // EffectiveAccessService cache permission per-user 1 jam (Cache::remember
        // "user.{id}.permissions") — kalau gak di-flush di sini, hasil sync di atas
        // gak kepakai sampai cache lama expire sendiri (bisa nunggu 1 jam), bikin
        // developer bingung tiap abis reset/reseed DB (permission "keliatan" gak
        // berubah padahal DB udah bener).
        Cache::flush();
    }
}
