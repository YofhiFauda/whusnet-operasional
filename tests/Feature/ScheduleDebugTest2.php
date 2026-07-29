<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleDebugTest2 extends TestCase
{
    use RefreshDatabase;

    public function test_debug(): void
    {
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('code', 'fop')->first();
        $fopUser = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $fopUser->roleScopes()->create(['role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP->value]);

        $city = City::create(['name' => 'X']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Y']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Z', 'postal_code' => '1']);
        $pop = Pop::create(['name' => 'P', 'code' => 'P1', 'type' => 'branch', 'address' => 'A', 'status' => 'active', 'city_id' => $city->id]);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'village_id' => $village->id, 'full_name' => 'Budi', 'status' => 'registered']);

        $response = $this->actingAs($fopUser)->get(route('fop-tasks.index'));
        $html = $response->getContent();

        // Cari blok openEditModal buat task customer ini
        $pos = strpos($html, 'openEditModal(');
        fwrite(STDERR, 'found openEditModal at: '.var_export($pos, true).PHP_EOL);
        if ($pos !== false) {
            fwrite(STDERR, 'snippet: '.substr($html, $pos, 800).PHP_EOL);
        }

        $this->assertTrue(true);
    }
}
