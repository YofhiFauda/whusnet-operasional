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

        $permissionsByRole = [
            'owner' => ['*'], // Owner gets all permissions

            // Kolektor — sengaja tanpa `payments.create` (bayar invoice mana
            // pun) dan tanpa `customers.view` (daftar pelanggan penuh).
            // `kolektor.pay` cuma berlaku di rute worklist yang memaksa
            // `collector_id = auth()->id()`.
            //
            // Merevisi §B-8 no. 4 dokumen lama — lihat
            // docs/plan/kolektor/analisa-alur-kolektor-2.0.md §8.
            'kolektor' => [
                'kolektor.view',
                'kolektor.pay',
                'kolektor.deposit',
                'kolektor.visit',
                'qr_scan.view', // Scan QR Internal (2026-08-27) — shortcut catat pembayaran dari worklist sendiri
                'kolektor.qr.pay', // Catat pembayaran via QR → Portal (2026-08-29) — permission TERPISAH dari kolektor.pay, lihat QrFeatureSeeder
                // Lapor komplain via QR → Portal (2026-08-29, keputusan
                // eksplisit user) — kolektor ketemu pelanggan langsung di
                // lapangan pas nagih, sering dapet komplain di tempat.
                // Digabung dengan kolektor.qr.pay: begitu kolektor scan
                // pelanggan yang punya tagihan due, dispatch() otomatis
                // dual-eligible → tampil chooser "Tagih Pembayaran"/"Lapor
                // Komplain" (QrScanController::resolveEligibility()). TIDAK
                // ngerubah tickets.create dashboard — kolektor tetap gak
                // bisa buka Worksheet Helpdesk, cuma titik masuk QR ini.
                'tickets.qr.create',
            ],

            'atasan' => [
                'dashboard.view',
                'pops.view',
                'users.view',
                'roles.view',
                'packages.view',
                'sla_timeline.view',
                'customers.view',
                'customers.detail.view',
                'customers.terminated.view',
                'customers.failed.view',
                'customers.detail.survey.view',
                'customers.detail.installation.view',
                'customers.detail.devices.view',
                'invoices.view',
                'invoices.print',
                'payments.view',
                'payments.print',
                'reports.view',
                'reports.export',
                'audit_logs.view',
                'audit_logs.export', // assuming audit_logs has export
                'fop_tasks.view',
                'tickets.view', // Atasan cuma memantau — gak ikut ngirim tiket
                'tickets.selesai.view',
                'tickets.dibatalkan.view',
                'qr_scan_logs.view', // Dashboard anomali scan QR (docs/plan/qr-code/)
                'noc_dashboard.view', // Monitoring tracking NOC, gak akses Worksheet NOC (itu kerjaan NOC)
                // Setoran Kas: atasan MEMERIKSA, tidak menyetor. `create`
                // sengaja tak diberikan — atasan bukan pemegang kas, dan tanpa
                // saldo sendiri dia mustahil jadi penyetor sekaligus pemeriksa.
                // `approve` (menutup selisih) tetap Owner lewat wildcard `*`.
                'cash_deposit.view',
                'cash_deposit.validate',
                'master_wilayah.view',
                'master_distribusi.view',
                'master_status_pelanggan.view',
                // Gudang/Inventory (ADHOC-54) — atasan MEMANTAU, tidak
                // eksekusi transfer/issue (pola sama Setoran Kas: atasan
                // `cash_deposit.validate`, bukan `.create`). `.view` doang di
                // warehouse_transfer/warehouse_issue, TANPA `.create`/`.receive`.
                'warehouse.view',
                'warehouse_transfer.view',
                'warehouse_issue.view',
                'warehouse_custody.view',
                'warehouse_traceability.view',
                // Laporan agregat (Fase 2 P2) — persis kebutuhan atasan:
                // pantau tren pergerakan & kerugian lintas cabang tanpa
                // eksekusi transfer/issue.
                'warehouse_report.view',
                // Permintaan Stok — atasan MEMANTAU antrean, gak fulfill/tolak
                // (itu keputusan operasional admin gudang Pusat).
                'warehouse_stock_request.view',
            ],

            'admin' => [
                'dashboard.view',
                'pops.*',
                'users.*',
                'roles.*',
                'packages.*',
                'sla_timeline.*',
                'customers.view',
                'customers.create',
                'customers.update',
                'customers.delete',
                'customers.deactivate', // Terminasi langganan — permission baru, terpisah dari customers.update
                'customers.terminated.view', // List Pelanggan Putus — permission sendiri, bukan wildcard customers.detail.*
                'customers.failed.view', // List Pelanggan Gagal — permission sendiri, bukan wildcard customers.detail.*
                'customers.import.*',
                'customers.detail.*', // Access to all detail sections (termasuk customers.detail.view - Detail Pelanggan)
                'invoices.*', // Ex: view, create, update, delete, cancel, print
                'payments.*', // Ex: view, create, update, validate, reject, print
                // Halaman admin atas kolektor. SENGAJA bukan wildcard `*`:
                // `collector_worksheet.approve` (hapus buku selisih) khusus
                // Owner — admin yang menemukan selisih tak boleh sekaligus
                // menutup kerugiannya sendiri.
                'collector_worksheet.view',
                'collector_worksheet.assign',
                'collector_worksheet.validate',
                'collector_worksheet.print',
                'collector_worksheet.upload',
                // Setoran Kas: admin MENYETOR, tidak memeriksa. `validate` &
                // `approve` sengaja tidak diberikan — pemeriksa setoran kas
                // adalah Owner/atasan, dan admin yang memeriksa setorannya
                // sendiri membuat cross check jadi tanda tangan di atas kertas
                // sendiri.
                //
                // `view` juga TIDAK diberikan: halaman /cash-deposits adalah
                // pandangan PEMERIKSA — posisi kas admin mana pun lintas POP,
                // antrean pemeriksaan, dan rincian sampai tingkat pelanggan.
                // Admin cukup melihat kas & riwayat SETORANNYA SENDIRI, dan itu
                // sudah tersaji di Worksheet Admin lewat `create` (§10).
                'cash_deposit.create',
                'reports.*',
                'audit_logs.view',
                'audit_logs.export',
                'fop_tasks.*',
                'tickets.*',
                'noc_worksheet.*',
                'noc_dashboard.*',
                'task.lookup', // dipakai modal /fop-tasks (autocomplete pelanggan + cek konflik)
                'master_wilayah.*',
                'master_distribusi.*',
                'master_status_pelanggan.*',
                'task.manage',
                // QR Pelanggan (docs/plan/qr-code/) — admin penuh: lihat,
                // terbitkan, cabut, cetak.
                'customers.qr.view',
                'customers.qr.create',
                'customers.qr.cancel',
                'customers.qr.print',
                'qr_scan_logs.view',
                'qr_scan.view', // Scan QR Internal (2026-08-27) — resources/js/qr-scan.js
                // Gudang/Inventory (ADHOC-54) — admin akses penuh operasional
                // gudang (beda dari atasan yang cuma view/monitor di atas).
                'warehouse.view',
                'warehouse_transfer.*',
                'warehouse_issue.*',
                'warehouse_custody.view',
                'warehouse_traceability.view',
                'warehouse_adjustment.create',
                'warehouse_reassign.create',
                'warehouse_report.view', // Fase 2 P2 — laporan agregat, sama scope operasional penuh di atas.
                // Permintaan Stok — admin Pusat yang fulfill/tolak permintaan
                // cabang. `.create` juga diberi (admin bisa ajukan atas nama
                // cabang kalau perlu, walau biasanya pop_admin yang inisiasi).
                'warehouse_stock_request.view',
                'warehouse_stock_request.create',
                'warehouse_stock_request.approve',
                'warehouse_stock_request.reject',
            ],

            'noc' => [
                'dashboard.view',
                'pops.view',
                'packages.view',
                'sla_timeline.view',
                'customers.view',
                'customers.detail.view',
                'customers.terminated.view',
                'customers.failed.view',
                'customers.detail.identity.view',
                'customers.detail.address.view',
                'customers.detail.packages.view',
                'customers.detail.survey.view',
                'customers.detail.survey.update',
                'customers.detail.survey.validate',
                'customers.detail.survey.reject',
                'customers.detail.installation.view',
                'customers.detail.installation.update',
                'customers.detail.installation.validate',
                'customers.detail.installation.activate',
                'customers.detail.installation.reject',
                'customers.detail.devices.view',
                'customers.detail.devices.update',
                'customers.detail.devices.view_sensitive',
                'customers.detail.devices.update_sensitive',
                'customers.detail.devices.retrieve',
                'customers.detail.documents.view',
                'customers.detail.documents.download',
                'invoices.view',
                'invoices.print',
                'payments.print', // Based on matrix
                'tickets.*',
                'noc_worksheet.*',
                'noc_dashboard.*',
                'master_wilayah.view',
                'master_distribusi.view',
                'master_status_pelanggan.view',
            ],

            'helpdesk' => [
                'dashboard.view',
                'pops.view',
                'packages.view',
                'sla_timeline.view',
                'customers.view',
                'customers.detail.view',
                'customers.terminated.view',
                'customers.failed.view',
                'customers.create',
                'customers.update',
                'customers.detail.identity.view',
                'customers.detail.identity.update',
                'customers.detail.address.view',
                'customers.detail.address.update',
                'customers.detail.packages.view',
                'customers.detail.packages.update',
                'customers.detail.survey.view',
                'customers.detail.installation.view',
                'customers.detail.devices.view',
                'customers.detail.documents.view',
                'customers.detail.documents.upload',
                'customers.detail.documents.download',
                'invoices.view',
                'invoices.create',
                'invoices.print',
                'payments.view',
                'payments.create',
                'payments.print',
                'reports.view',
                'reports.export',
                'tickets.*',
                'master_wilayah.view',
                'master_distribusi.view',
                'master_status_pelanggan.view',
                'customers.qr.view', // Lihat status token QR pelanggan (docs/plan/qr-code/)
                'qr_scan.view', // Scan QR Internal (2026-08-27) — shortcut bikin tiket dari QR pelanggan
            ],

            'fop' => [
                'dashboard.view',
                'customers.view',
                'customers.detail.view',
                'customers.terminated.view',
                'customers.failed.view',
                'customers.detail.identity.view',
                'customers.detail.address.view',
                'customers.detail.packages.view',
                'customers.detail.survey.view',
                'customers.detail.survey.update',
                'customers.detail.survey.validate',
                'customers.detail.survey.reject',
                'customers.detail.installation.view',
                'customers.detail.installation.update',
                'customers.detail.installation.activate',
                'customers.detail.installation.reject',
                'customers.detail.devices.view',
                'customers.detail.devices.update',
                'customers.detail.devices.retrieve',
                'customers.detail.documents.view',
                'customers.detail.documents.upload',
                'customers.detail.documents.download',
                'fop_tasks.*',
                'tickets.*',
                'master_wilayah.view',
                'master_distribusi.view',
                'master_status_pelanggan.view',
                'customers.qr.view', // Lihat status token QR pelanggan (docs/plan/qr-code/)
                'tasks.qr_attendance.create', // Absen task via scan QR (Fase 3, diseed sekarang)
                'qr_scan.view', // Scan QR Internal (2026-08-27)
                // Gudang/Inventory (ADHOC-54) — read-only TERBATAS, BUKAN
                // akses data gudang mentah. FOP TIDAK dapat Dashboard/Transfer/
                // Issue/Ledger (rancangan-ui.md §1.3). Custody "lihat semua"
                // discope ke teknisi dalam wilayahnya lewat POP scope existing
                // (EffectiveAccessService), bukan permission terpisah.
                // "Analisa material per fop_task_id" (kebutuhan WAJIB FOP)
                // BUKAN permission baru — extend halaman Verifikasi Admin
                // (ADHOC-28) yang permission-nya udah ada, ditambah pas Fase
                // integrasi nanti.
                'warehouse_custody.view',
                'warehouse_traceability.view',
            ],

            'teknisi' => [
                'dashboard.view',
                // customers.view / customers.detail.view / customers.terminated.view /
                // customers.failed.view SENGAJA gak dikasih — teknisi cuma
                // boleh kerjain Survey/Pemasangan (queue + form lapor lewat
                // permission .survey.*/.installation.* di bawah), TAPI gak
                // boleh buka List Data Pelanggan, List Pelanggan Putus, List
                // Pelanggan Gagal, atau Detail Pelanggan (4 permission
                // terpisah sejak refactor — lihat routes/web.php,
                // CustomerController/CustomerTerminatedController/
                // CustomerFailedController).
                'customers.detail.identity.view',
                'customers.detail.address.view',
                'customers.detail.packages.view',
                'customers.detail.survey.view',
                'customers.detail.survey.update',
                'customers.detail.installation.view',
                'customers.detail.installation.update',
                'customers.detail.installation.activate',
                'customers.detail.devices.view',
                'customers.detail.devices.update',
                'customers.detail.devices.view_sensitive',
                'customers.detail.devices.update_sensitive',
                'customers.detail.documents.view',
                'customers.detail.documents.upload',
                'customers.detail.documents.download',
                'tasks.qr_attendance.create', // Absen task via scan QR (Fase 3, diseed sekarang)
                'qr_scan.view', // Scan QR Internal (2026-08-27)
                // task.view.own/task.execute SEHARUSNYA didaftarkan TaskFeatureSeeder
                // (DatabaseSeeder baris 29), TAPI RolePermissionSeeder dipanggil LAGI
                // sesudahnya (baris 39, buat sinkron permission ticket_*/warehouse*.*
                // ke owner) — sync() di bawah full-replace permission tiap role yang
                // terdaftar, jadi grant TaskFeatureSeeder ke Teknisi ke-wipe kalau
                // gak didaftarkan eksplisit juga di sini. Ditemukan pas verifikasi
                // ADHOC-54 (widget "Stok Saya" gak bisa diakses teknisi sama sekali).
                'task.view.own',
                'task.execute',
            ],

            'sales' => [
                'dashboard.view',
                'customers.view',
                'customers.detail.view',
                'customers.terminated.view',
                'customers.failed.view',
                'customers.create',
                'customers.update',
                // Skip Survey saat Registrasi — satu-satunya role yang dapat
                // default. Role lain butuh ditambahkan manual lewat Role Matrix.
                'customers.registration.skip_survey',
                'customers.detail.identity.view',
                'customers.detail.identity.update',
                'customers.detail.address.view',
                'customers.detail.address.update',
                'customers.detail.packages.view',
                'customers.detail.packages.update',
                'customers.detail.documents.view',
                'customers.detail.documents.upload',
                'customers.detail.documents.download',
                'tickets.*',
            ],

            'pop_admin' => [
                'dashboard.view',
                'pops.view',
                'packages.view',
                'sla_timeline.view',
                'customers.view',
                'customers.create',
                'customers.update',
                'customers.deactivate', // Terminasi langganan dalam scope POP-nya
                'customers.terminated.view', // List Pelanggan Putus — permission sendiri, bukan wildcard customers.detail.*
                'customers.failed.view', // List Pelanggan Gagal — permission sendiri, bukan wildcard customers.detail.*
                'customers.import.*',
                'customers.detail.*', // Except sensitive devices, we will subtract below (termasuk customers.detail.view - Detail Pelanggan)
                'invoices.view',
                'invoices.create',
                'invoices.print',
                'payments.view',
                'payments.create',
                'payments.validate',
                'payments.reject',
                'payments.print',
                'collector_worksheet.view', // Cross check kolektor DALAM scope POP-nya
                'collector_worksheet.assign',
                'collector_worksheet.validate',
                'collector_worksheet.print',
                'collector_worksheet.upload',
                // Sama seperti admin: pop_admin memegang kas cabangnya, jadi
                // menyetor — bukan memeriksa, dan tidak membuka pandangan
                // pemeriksa (§10).
                'cash_deposit.create',
                'reports.view',
                'reports.export',
                'tickets.*',
                'master_wilayah.view',
                'master_distribusi.view',
                'master_status_pelanggan.view',
                'customers.qr.view', // Lihat status token QR pelanggan (docs/plan/qr-code/)
                'customers.qr.print', // Cetak stiker QR — pop_admin cetak buat cabangnya sendiri
                // Gudang/Inventory (ADHOC-54) — pop_admin = "admin gudang
                // cabang" (bukan role baru, role existing yang tinggal
                // digrant). `warehouse_transfer` SENGAJA cuma `.view`+`.receive`
                // — pop_admin gak boleh BUAT transfer (`.create` cuma admin/
                // owner, Pusat yang inisiasi kirim), tapi WAJIB bisa konfirmasi
                // terima kiriman ke cabangnya sendiri. `warehouse_issue.*`
                // penuh — issue ke teknisi selalu terjadi di level cabang.
                'warehouse.view',
                'warehouse_transfer.view',
                'warehouse_transfer.receive',
                'warehouse_issue.*',
                'warehouse_custody.view',
                'warehouse_traceability.view',
                'warehouse_adjustment.create', // lapor rusak/hilang/opname cabangnya sendiri
                'warehouse_reassign.create', // reassign custody teknisi resign/cuti cabangnya sendiri
                'warehouse_report.view', // laporan agregat, discope EffectiveAccessService ke cabangnya sendiri
                // Permintaan Stok — pop_admin yang ajuin (cabangnya sendiri
                // kehabisan barang) + boleh batalin punya sendiri kalau salah
                // ketik. TANPA approve/reject — itu keputusan Pusat.
                'warehouse_stock_request.view',
                'warehouse_stock_request.create',
                'warehouse_stock_request.cancel',
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

            // Remove sensitive permissions for pop_admin based on matrix
            if ($roleCode === 'pop_admin') {
                $finalPermissionCodes = array_filter($finalPermissionCodes, function ($code) {
                    return ! in_array($code, [
                        'customers.detail.devices.view_sensitive',
                        'customers.detail.devices.update_sensitive',
                    ]);
                });
            }

            // Also for admin, matrix says no sensitive access, except if specified.
            if ($roleCode === 'admin') {
                $finalPermissionCodes = array_filter($finalPermissionCodes, function ($code) {
                    return ! in_array($code, [
                        'customers.detail.devices.view_sensitive',
                        'customers.detail.devices.update_sensitive',
                    ]);
                });
            }

            // FOP tidak boleh ubah Tipe Task lewat wildcard fop_tasks.* — harus di-grant eksplisit.
            if ($roleCode === 'fop') {
                $finalPermissionCodes = array_filter($finalPermissionCodes, function ($code) {
                    return $code !== 'fop_tasks.update_sensitive';
                });
            }

            $finalPermissionCodes = array_unique($finalPermissionCodes);
            $permissionIds = $allPermissions->whereIn('code', $finalPermissionCodes)->pluck('id')->toArray();

            // Sync efficiently
            $role->permissions()->sync($permissionIds);
        }

        // EffectiveAccessService cache permission per-user 1 jam (Cache::remember
        // "user.{id}.permissions") — kalau gak di-flush di sini, hasil sync di atas
        // gak kepakai sampai cache lama expire sendiri (bisa nunggu 1 jam), bikin
        // developer bingung tiap abis reset/reseed DB (permission "keliatan" gak
        // berubah padahal DB udah bener).
        Cache::flush();
    }
}
