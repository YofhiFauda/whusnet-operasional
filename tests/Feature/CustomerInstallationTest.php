<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerInstallationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_technician_can_fill_installation(): void
    {
        Storage::fake('public');

        $pop = $this->createPop('SMN');
        $technician = $this->createUserWithRole('Teknisi');

        $customer = Customer::create([
            'customer_code' => 'TEST-INST-001',
            'full_name' => 'Test Installation Customer',
            'primary_phone' => '0812345678',
            'status' => 'installation_in_progress',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $customer->installations()->create([
            'installation_status' => 'scheduled',
            'scheduled_date' => now()->format('Y-m-d'),
            'scheduled_time' => '09:00',
            'technician_id' => $technician->id,
        ]);

        // Guard assignment di store() (fix RBAC Issue 3 — semua non-full-access
        // wajib jadi anggota tim Task yang lagi jalan, gak cukup cuma modal
        // permission generik customers.detail.installation.update).
        $task = Task::create([
            'task_number' => 'TASK-TEST-INST-001',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan Test Installation Customer',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);

        $installationData = [
            'installation_status' => 'completed',
            'scheduled_date' => now()->format('Y-m-d'),
            'scheduled_time' => '09:00',
            'technician_id' => $technician->id,
            'finished_date' => now()->format('Y-m-d'),
            'installation_photo' => UploadedFile::fake()->image('installation.jpg'),
            'contract_photo' => UploadedFile::fake()->image('contract.jpg'),
            'signature_photo' => UploadedFile::fake()->image('signature.jpg'),
            'speedtest_photo' => UploadedFile::fake()->image('speedtest.jpg'),
            'installation_note' => 'Instalasi selesai dan koneksi normal.',
            'device_type' => 'ont',
        ];

        $response = $this->actingAs($technician)
            ->post(route('customers.installation.store', $customer->id), $installationData);

        if (session()->has('error')) {
            dump(session('error'));
        }
        $response->assertSessionHas('success');
        $response->assertStatus(302);
        $this->assertDatabaseHas('customer_installations', [
            'customer_id' => $customer->id,
            'installation_status' => 'completed',
            'technician_id' => $technician->id,
            'installation_note' => 'Instalasi selesai dan koneksi normal.',
        ]);

        $customer->refresh();
        $this->assertEquals('verification_admin', $customer->status);

        $installation = $customer->installations()->first();
        $this->assertNotNull($installation->installation_photo);
        $this->assertTrue(Storage::disk('public')->exists($installation->installation_photo));
    }

    public function test_installation_data_is_visible_on_customer_detail(): void
    {
        $pop = $this->createPop('SMN2');
        $technician = $this->createUserWithRole('Teknisi');

        $customer = Customer::create([
            'customer_code' => 'TEST-INST-002',
            'full_name' => 'Visible Installation Customer',
            'primary_phone' => '0812345679',
            'status' => 'waiting_installation',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $customer->installations()->create([
            'installation_status' => 'scheduled',
            'scheduled_date' => now()->format('Y-m-d'),
            'scheduled_time' => '13:00',
            'technician_id' => $technician->id,
            'installation_note' => 'Jadwal pemasangan siang.',
        ]);

        // Teknisi gak punya customers.detail.view (Detail Pelanggan diblok),
        // tab Pemasangan diakses lewat halaman terpisah customers.fieldwork
        // (lihat CustomerFieldworkController).
        $response = $this->actingAs($technician)
            ->get(route('customers.fieldwork', $customer->id));

        $response->assertStatus(200);
        $response->assertSee('Hasil Laporan Pemasangan Perangkat');
        $response->assertSee('Visible Installation Customer');
        $response->assertSee('Jadwal pemasangan siang.');
    }

    public function test_unauthorized_user_cannot_fill_installation(): void
    {
        $pop = $this->createPop('SMN3');
        $finance = $this->createUserWithRole('Helpdesk');

        $customer = Customer::create([
            'customer_code' => 'TEST-INST-003',
            'full_name' => 'Unauthorized Installation Customer',
            'primary_phone' => '0812345680',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $response = $this->actingAs($finance)
            ->post(route('customers.installation.store', $customer->id), [
                'installation_status' => 'completed',
                'scheduled_date' => now()->format('Y-m-d'),
            ]);

        $response->assertStatus(403);
    }

    private function createPop(string $code): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $code,
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function createUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::where('name', $roleName)->firstOrFail();
        $user->role_id = $role->id;
        $user->save();
        $user->load('role');

        $user->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP,
        ]);

        return $user;
    }
}
