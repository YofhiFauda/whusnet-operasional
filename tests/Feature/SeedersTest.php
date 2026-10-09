<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminGudangCabangSeeder;
use Database\Seeders\AdminGudangPusatSeeder;
use Database\Seeders\FopSeeder;
use Database\Seeders\HelpdeskSeeder;
use Database\Seeders\KolektorSeeder;
use Database\Seeders\NocSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_fop_seeder_creates_three_users_with_fop_role(): void
    {
        $this->seed(FopSeeder::class);

        $fopRole = Role::where('code', 'fop')->first();
        $this->assertNotNull($fopRole);

        $users = User::where('role_id', $fopRole->id)->get();
        $this->assertCount(3, $users);

        for ($i = 1; $i <= 3; $i++) {
            $user = User::where('email', "fop{$i}@whusnet.com")->first();
            $this->assertNotNull($user);
            $this->assertEquals("FOP {$i}", $user->name);
            $this->assertEquals($fopRole->id, $user->role_id);
            $this->assertNotNull($user->roleScopes()->first());
        }
    }

    public function test_kolektor_seeder_creates_three_users_with_kolektor_role(): void
    {
        $this->seed(KolektorSeeder::class);

        $kolektorRole = Role::where('code', 'kolektor')->first();
        $this->assertNotNull($kolektorRole);

        $users = User::where('role_id', $kolektorRole->id)->get();
        $this->assertCount(3, $users);

        for ($i = 1; $i <= 3; $i++) {
            $user = User::where('email', "kolektor{$i}@whusnet.com")->first();
            $this->assertNotNull($user);
            $this->assertEquals("Kolektor {$i}", $user->name);
            $this->assertEquals($kolektorRole->id, $user->role_id);
            $this->assertNotNull($user->roleScopes()->first());
        }
    }

    public function test_admin_gudang_pusat_seeder_creates_three_users_with_admin_role_and_all_pop_scope(): void
    {
        $this->seed(AdminGudangPusatSeeder::class);

        $adminRole = Role::where('code', 'admin')->first();
        $this->assertNotNull($adminRole);

        for ($i = 1; $i <= 3; $i++) {
            $user = User::where('email', "admingudangpusat{$i}@whusnet.com")->first();
            $this->assertNotNull($user);
            $this->assertEquals("Admin Gudang Pusat {$i}", $user->name);
            $this->assertEquals($adminRole->id, $user->role_id);

            $scope = $user->roleScopes()->first();
            $this->assertNotNull($scope);
            $this->assertEquals('all_pop', $scope->scope_type->value);
        }
    }

    public function test_admin_gudang_cabang_seeder_creates_three_users_with_pop_admin_role(): void
    {
        $this->seed(AdminGudangCabangSeeder::class);

        $popAdminRole = Role::where('code', 'pop_admin')->first();
        $this->assertNotNull($popAdminRole);

        for ($i = 1; $i <= 3; $i++) {
            $user = User::where('email', "admingudangcabang{$i}@whusnet.com")->first();
            $this->assertNotNull($user);
            $this->assertEquals("Admin Gudang Cabang {$i}", $user->name);
            $this->assertEquals($popAdminRole->id, $user->role_id);
            $this->assertNotNull($user->roleScopes()->first());
        }
    }

    public function test_noc_seeder_creates_three_users_with_noc_role_and_all_pop_scope(): void
    {
        $this->seed(NocSeeder::class);

        $nocRole = Role::where('code', 'noc')->first();
        $this->assertNotNull($nocRole);

        $users = User::where('role_id', $nocRole->id)->get();
        $this->assertCount(3, $users);

        for ($i = 1; $i <= 3; $i++) {
            $user = User::where('email', "noc{$i}@whusnet.com")->first();
            $this->assertNotNull($user);
            $this->assertEquals("NOC {$i}", $user->name);
            $this->assertEquals($nocRole->id, $user->role_id);

            $scope = $user->roleScopes()->first();
            $this->assertNotNull($scope);
            $this->assertEquals('all_pop', $scope->scope_type->value);
        }
    }

    public function test_helpdesk_seeder_creates_three_users_with_helpdesk_role_and_all_pop_scope(): void
    {
        $this->seed(HelpdeskSeeder::class);

        $helpdeskRole = Role::where('code', 'helpdesk')->first();
        $this->assertNotNull($helpdeskRole);

        $users = User::where('role_id', $helpdeskRole->id)->get();
        $this->assertCount(3, $users);

        for ($i = 1; $i <= 3; $i++) {
            $user = User::where('email', "helpdesk{$i}@whusnet.com")->first();
            $this->assertNotNull($user);
            $this->assertEquals("Helpdesk {$i}", $user->name);
            $this->assertEquals($helpdeskRole->id, $user->role_id);

            $scope = $user->roleScopes()->first();
            $this->assertNotNull($scope);
            $this->assertEquals('all_pop', $scope->scope_type->value);
        }
    }
}
