<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\FopTask;
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

class ScheduleDebugTest extends TestCase
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

        $tech = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->first()->id, 'status' => 'active']);

        $city = City::create(['name' => 'X']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Y']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Z', 'postal_code' => '1']);
        $pop = Pop::create(['name' => 'P', 'code' => 'P1', 'type' => 'branch', 'address' => 'A', 'status' => 'active', 'city_id' => $city->id]);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'village_id' => $village->id, 'full_name' => 'Budi']);

        $fopTask = FopTask::create([
            'task_number' => 'TFOP-DEBUG-0001',
            'task_date' => now(),
            'category' => TaskType::SURVEY->value,
            'tugas' => 'C1_Budi',
            'village_id' => $village->id,
            'pop_id' => $pop->id,
            'customer_id' => $customer->id,
            'issue' => 'Survey baru',
            'status' => TaskStatus::DRAFT->value,
            'priority' => 'Medium',
        ]);

        // Payload PERSIS seperti browser kirim kalau pop_id/village_id disabled
        // (dikecualikan), customer_id hidden input tetap ngirim modal.data.customer_id.
        $payload = [
            'category' => 'SURVEY',
            'task_date' => $fopTask->task_date->format('Y-m-d\TH:i'),
            'tugas' => $fopTask->tugas,
            'issue' => $fopTask->issue,
            'status' => 'terjadwal',
            'priority' => $fopTask->priority->value,
            'customer_id' => $fopTask->customer_id,
            'technicians' => [$tech->id],
        ];

        $response = $this->actingAs($fopUser)->put(route('fop-tasks.update', $fopTask), $payload);

        fwrite(STDERR, 'status: '.$response->getStatusCode().PHP_EOL);
        fwrite(STDERR, 'content: '.$response->getContent().PHP_EOL);

        $this->assertTrue(true);
    }
}
