<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Customer;
use App\Models\CustomerAcquisition;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\RestrictedPackage;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data demo modul Business Development (Skema 1-3, 2026-09-12) — dipakai
 * coba Restriksi Paket, Master Agent, dan Dashboard Omset Sales TANPA harus
 * isi manual dulu lewat UI. BUKAN rekomendasi buat data produksi (pola sama
 * SalesSeeder/TechnicianSeeder — user demo & scope all_pop/selected_pop
 * acak, cuma buat testing).
 *
 * Reuse role Sales (`SalesSeeder`) & Teknisi (`TechnicianSeeder`) yang
 * SUDAH ADA — jalankan seeder ini SETELAH keduanya. Idempotent (aman
 * dijalankan ulang, updateOrCreate/firstOrCreate di semua titik).
 *
 * Jalankan: php artisan db:seed --class=BusinessDevelopmentSeeder
 */
class BusinessDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $busdevRole = Role::where('code', 'business_development')->first();
        if (! $busdevRole) {
            $this->command->error('Role business_development tidak ditemukan. Pastikan RoleSeeder sudah dijalankan.');

            return;
        }

        $busdev = User::updateOrCreate(
            ['email' => 'busdev@whusnet.com'],
            [
                'name' => 'Business Development Demo',
                'phone' => '081200000099',
                'password' => Hash::make('password'),
                'status' => 'active',
                'role_id' => $busdevRole->id,
                'email_verified_at' => now(),
            ]
        );

        $scope = UserRoleScope::updateOrCreate(
            ['user_id' => $busdev->id, 'role_id' => $busdevRole->id],
            ['scope_type' => 'all_pop']
        );
        UserRoleScopeTarget::where('user_role_scope_id', $scope->id)->delete();

        // Skema 1 — Restriksi Paket per Role. Contoh persis dari permintaan
        // awal user: Sales & Teknisi cuma boleh pilih 8 paket ini (4 reguler
        // + 4 varian "Khusus"), kode paket ini SUDAH ADA di InternetPackageSeeder.
        $restrictedCodes = ['Net138', 'Net150', 'Net165', 'Net198', 'Net138 Khusus', 'Net150 Khusus', 'Net165 Khusus', 'Net198 Khusus'];
        $restrictedPackages = InternetPackage::whereIn('package_code', $restrictedCodes)->get();

        if ($restrictedPackages->count() < count($restrictedCodes)) {
            $this->command->warn('BusinessDevelopmentSeeder: sebagian paket contoh (Net138/150/165/198 + Khusus) belum ada — jalankan InternetPackageSeeder dulu. Restriksi diisi sebagian dari yang ketemu saja.');
        }

        foreach ($restrictedPackages as $package) {
            RestrictedPackage::firstOrCreate(['package_id' => $package->id]);
        }

        // Skema 3 — Master Agent contoh, dikelola Busdev.
        $agents = [
            ['code' => 'AGT-001', 'name' => 'Agent Ponorogo Kota', 'phone' => '081211110001'],
            ['code' => 'AGT-002', 'name' => 'Agent Jetis', 'phone' => '081211110002'],
        ];
        foreach ($agents as $agentData) {
            Agent::updateOrCreate(['code' => $agentData['code']], $agentData + ['is_active' => true]);
        }

        // Data pelanggan demo, diinput Sales & Teknisi demo yang SUDAH ADA
        // (SalesSeeder/TechnicianSeeder) — biar Dashboard Omset Sales &
        // filter "Diinput Oleh" (customer-acquisitions.index) langsung ada
        // isinya begitu seeder ini selesai, gak nunggu registrasi manual.
        $sales = User::where('email', 'sales@whusnet.com')->first();
        $teknisi = User::where('email', 'teknisi1@whusnet.com')->first();
        // `MasterPopSeeder` sengaja TIDAK dipanggil DatabaseSeeder (dikomentari
        // — POP diisi manual admin lewat UI di data produksi), jadi POP bisa
        // saja belum ada sama sekali di DB baru. Demo data ini butuh minimal
        // satu POP buat jalan — buat POP demo sendiri kalau memang kosong,
        // JANGAN diam-diam skip data demo Busdev cuma karena itu.
        $pop = Pop::first() ?? Pop::factory()->create(['name' => 'POP Demo Busdev']);
        $package = $restrictedPackages->first() ?? InternetPackage::first();

        if (! $package) {
            $this->command->warn('BusinessDevelopmentSeeder: belum ada paket internet sama sekali — data demo pelanggan dilewati.');

            $this->command->info('✅ BusinessDevelopmentSeeder: role, restriksi paket, dan master Agent selesai (tanpa data pelanggan demo).');

            return;
        }

        $demoCustomers = [];
        if ($sales) {
            $demoCustomers[] = ['name' => 'Pelanggan Demo Sales 1', 'sales_user_id' => $sales->id, 'bill' => 150000];
            $demoCustomers[] = ['name' => 'Pelanggan Demo Sales 2', 'sales_user_id' => $sales->id, 'bill' => 198000];
        }
        if ($teknisi) {
            // Teknisi BELUM punya akses registrasi pelanggan di UI (lihat
            // catatan docs/TASKS.md ADHOC-65) — baris ini SENGAJA dibuat
            // langsung lewat seeder (bukan simulasi form) supaya filter
            // Role "Teknisi" di halaman Busdev sudah bisa dicoba SEKARANG,
            // sebelum akses registrasi Teknisi benar-benar digarap.
            $demoCustomers[] = ['name' => 'Pelanggan Demo Teknisi 1', 'sales_user_id' => $teknisi->id, 'bill' => 165000];
        }

        foreach ($demoCustomers as $data) {
            // firstOrCreate biasa gak dipakai di sini — Customer punya
            // beberapa kolom NOT NULL (customer_code unik, registration_date)
            // yang cuma diisi otomatis lewat CustomerFactory, bukan array
            // atribut statis.
            $customer = Customer::where('full_name', $data['name'])->first()
                ?? Customer::factory()->create([
                    'full_name' => $data['name'],
                    'sales_user_id' => $data['sales_user_id'],
                    'pop_id' => $pop->id,
                    'internet_package_id' => $package->id,
                    'status' => 'active',
                ]);

            CustomerService::firstOrCreate(
                ['customer_id' => $customer->id],
                [
                    'internet_package_id' => $package->id,
                    'package_name_snapshot' => $package->name,
                    'monthly_price' => $data['bill'],
                    'total_monthly_bill' => $data['bill'],
                    'activation_date' => now()->subDays(5),
                    'service_status' => 'aktif',
                    'billing_status' => 'pending',
                ]
            );

            // Baris ini normalnya dibuat otomatis CustomerObserver saat status
            // pertama kali jadi ACTIVE — di seeder dibuat langsung (bypass
            // observer, pola sama CustomerAcquisitionFactory di test) biar
            // demo tidak bergantung urutan event Eloquent.
            CustomerAcquisition::firstOrCreate(
                ['customer_id' => $customer->id],
                ['periode' => now()->format('Y-m'), 'verified_at' => now()]
            );
        }

        $this->command->info('✅ BusinessDevelopmentSeeder selesai!');
        $this->command->info('Login Busdev: busdev@whusnet.com / password');
        $this->command->info('Restriksi Paket terisi: '.$restrictedPackages->pluck('package_code')->implode(', '));
        $this->command->info('Data demo pelanggan (Diinput Oleh Sales/Teknisi) siap dilihat di /customer-acquisitions dan /business-development/sales-omset.');
    }
}
