<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PonorogoRegionSeeder::class);
        $this->call(MadiunRegionSeeder::class);
        $this->call(SubscriptionStatusSeeder::class);
        $this->call(InternetPackageSeeder::class);
        $this->call(FeatureSeeder::class);
        $this->call(ActionSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(SlaTimelineFeatureSeeder::class);
        $this->call(RolePermissionSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(WorkflowTransitionPermissionSeeder::class);
        $this->call(TaskFeatureSeeder::class);
        $this->call(PackageSlaSettingSeeder::class); // UNTUK MENGATUR DURASI SLA TIAP PACKAGE
        $this->call(SlaTimelineFeatureSeeder::class); // UNTUK MENGATUR Timeline SLA TIAP TUGAS / RBAC
        $this->call(TicketFeatureSeeder::class); // Ticketing + Worksheet/Dashboard NOC
        $this->call(TicketIssueCategoryFeatureSeeder::class);
        $this->call(ItemFeatureSeeder::class); // Master Barang/Material
        $this->call(ItemCategoryFeatureSeeder::class); // Master Kategori Barang
        $this->call(WorkToolFeatureSeeder::class); // Master Alat Kerja
        $this->call(QrFeatureSeeder::class); // QR Pelanggan (Fase 1) Permission QR
        $this->call(WarehouseFeatureSeeder::class); // Gudang/Inventory (ADHOC-54, Fase 1)
        $this->call(FopAnalyticsFeatureSeeder::class); // Dashboard Analitik FOP
        $this->call(CustomerAcquisitionFeatureSeeder::class); // Customer Acquisition (Busdev) - Pelanggan Aktif < 30 Hari
        $this->call(BusinessDevelopmentVerificationFeatureSeeder::class); // Antrean Menunggu Verifikasi BD (pelanggan Bisnis pra-ACTIVE)
        $this->call(CustomerRegistrationVerificationFeatureSeeder::class); // Antrean Verifikasi Registrasi (ADHOC-73)
        $this->call(BusinessDevelopmentFeatureSeeder::class); // agents/package_restrictions/sales_omset_dashboard (Skema 1-3, 2026-09-12)
        $this->call(RolePermissionSeeder::class); // re-run biar permission ticket_*/items.*/item_categories.*/work_tools.*/customers.qr.*/qr_scan_logs.*/warehouse*.*/fop_analytics.*/customer_acquisitions.*/agents.*/package_restrictions.*/sales_omset_dashboard.*/customer_registration_verification.* ke-sync ke owner
        $this->call(TicketIssueCategorySeeder::class); // DATA CONTOH — ganti sebelum go-live
        $this->call(ItemCategorySeeder::class); // Kategori tambahan non-system (modem_ont, router_gateway) — sebelum ItemSeeder, dirujuk barangnya
        $this->call(ItemSeeder::class); // Isi awal master barang — tambah sisanya lewat Master Data
        $this->call(WorkToolSeeder::class); // Isi awal master alat kerja

        // $this->call(CustomerSeeder::class);
        // $this->call(MasterPopSeeder::class);
        $this->call(TechnicianSeeder::class);
        $this->call(SalesSeeder::class); // User demo role Sales — buat coba Skip Survey saat Registrasi
        $this->call(BusinessDevelopmentSeeder::class); // Data demo Busdev: Restriksi Paket, Master Agent, Omset Sales (Skema 1-3) — reuse Sales/Teknisi di atas, WAJIB setelah keduanya
        $this->call(FopSeeder::class);
        $this->call(KolektorSeeder::class);
        $this->call(AdminGudangPusatSeeder::class);
        $this->call(AdminGudangCabangSeeder::class);
        $this->call(NocSeeder::class);
        $this->call(HelpdeskSeeder::class);

        // User::factory(10)->create();

        // $ownerRole = \App\Models\Role::where('name', 'Owner')->first();
        // $adminRole = \App\Models\Role::where('name', 'Admin')->first();

        // User::updateOrCreate([
        //     'email' => 'owner@whusnet.net',
        // ], [
        //     'name' => 'Owner Whusnet',
        //     'email_verified_at' => now(),
        //     'phone' => '081234567890',
        //     'password' => bcrypt('password'),
        //     'status' => 'active',
        //     'role_id' => $ownerRole ? $ownerRole->id : null,
        // ]);

        // User::updateOrCreate([
        //     'email' => 'admin@whusnet.net',
        // ], [
        //     'name' => 'Admin Whusnet',
        //     'email_verified_at' => now(),
        //     'phone' => '081234567890',
        //     'password' => bcrypt('password'),
        //     'status' => 'active',
        //     'role_id' => $adminRole ? $adminRole->id : null,
        // ]);
    }
}
