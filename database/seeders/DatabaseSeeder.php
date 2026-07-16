<?php

namespace Database\Seeders;

use App\Http\Controllers\Master\SlaTimelineController;
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
        $this->call(PackageSlaSettingSeeder::class); //UNTUK MENGATUR DURASI SLA TIAP PACKAGE
        $this->call(SlaTimelineFeatureSeeder::class); //UNTUK MENGATUR Timeline SLA TIAP TUGAS / RBAC
        
        $this->call(CustomerSeeder::class);
        $this->call(MasterPopSeeder::class);
        $this->call(TechnicianSeeder::class);

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
