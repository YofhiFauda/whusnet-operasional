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

/**
 * Wizard Laporan Pemasangan (installations.report) dipecah jadi DUA submit
 * terpisah: Laporan Pemasangan & Perangkat (step 5, storePemasangan — TIDAK
 * menyelesaikan task/workflow) lalu Laporan Speedtest (step 6, storeSpeedtest
 * — SATU-SATUNYA titik penyelesaian). Step 6 wajib terkunci sampai step 5
 * lengkap & tombol "Aktivasi" ditekan.
 */
class InstallationSpeedtestActivationGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_speedtest_report_locked_until_pemasangan_saved(): void
    {
        [$customer, $technician] = $this->setupInProgressInstallation();

        $response = $this->actingAs($technician)
            ->get(route('customers.installation.report', $customer->id));

        $response->assertStatus(200);
        $response->assertSee('Aktivasi Laporan Speedtest');
        $response->assertDontSee('Simpan &amp; Selesaikan Pemasangan');
    }

    public function test_store_speedtest_rejected_when_pemasangan_not_complete(): void
    {
        [$customer, $technician] = $this->setupInProgressInstallation();

        $response = $this->actingAs($technician)
            ->post(route('customers.installation.speedtest', $customer->id), [
                'test_download' => 20,
                'test_upload' => 10,
                'speedtest_photo' => UploadedFile::fake()->image('speedtest.jpg'),
            ]);

        $response->assertStatus(422);

        $customer->refresh();
        $this->assertEquals('installation_in_progress', $customer->status);
    }

    public function test_store_pemasangan_unlocks_speedtest_without_completing_task(): void
    {
        Storage::fake('public');

        [$customer, $technician, $task] = $this->setupInProgressInstallation();

        $response = $this->actingAs($technician)
            ->post(route('customers.installation.pemasangan', $customer->id), [
                'device_type' => 'ont',
                'wifi_ssid' => 'WHUSNET_GATE_TEST',
                'serial_number' => 'ZTEGC1234567',
                'installation_photo' => UploadedFile::fake()->image('installation.jpg'),
                'contract_photo' => UploadedFile::fake()->image('contract.jpg'),
                'signature_photo' => UploadedFile::fake()->image('signature.jpg'),
                'materials' => [
                    [
                        'item_name' => 'Kabel Dropcore',
                        'item_type' => 'kabel_dropcore',
                        'qty' => 50,
                        'unit' => 'meter',
                    ],
                ],
            ]);

        $response->assertSessionHas('success');
        $response->assertStatus(302);

        $customer->refresh();
        // Belum selesai — status pelanggan & task TIDAK ikut berubah.
        $this->assertEquals('installation_in_progress', $customer->status);
        $task->refresh();
        $this->assertEquals(TaskStatus::IN_PROGRESS, $task->status);

        $installation = $customer->installations()->latest()->first();
        $this->assertEquals('in_progress', $installation->installation_status);
        $this->assertNotNull($installation->installation_photo);

        // Step 6 sekarang terbuka, DAN data step 5 yang barusan diisi tidak hilang.
        $page = $this->actingAs($technician)
            ->get(route('customers.installation.report', $customer->id));
        $page->assertDontSee('Aktivasi Laporan Speedtest');
        $page->assertSee('WHUSNET_GATE_TEST');
        // Bug 2026-08-19: file input gak bisa di-prefill browser — tanpa
        // state "Sudah Tersimpan", ketiga foto wajib keliatan reset ke
        // "Belum ada file" begitu balik/reload ke step 5 padahal udah kesimpen.
        $page->assertSee('Sudah Tersimpan');
        $page->assertSee('Ganti Foto Pemasangan');
        $page->assertDontSee('Pilih Foto Pemasangan');
        $page->assertSee('ZTEGC1234567');
    }

    public function test_store_speedtest_completes_task_and_transitions_workflow(): void
    {
        Storage::fake('public');

        [$customer, $technician, $task] = $this->setupInProgressInstallation();

        $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), [
            'device_type' => 'ont',
            'installation_photo' => UploadedFile::fake()->image('installation.jpg'),
            'contract_photo' => UploadedFile::fake()->image('contract.jpg'),
            'signature_photo' => UploadedFile::fake()->image('signature.jpg'),
            'materials' => [
                [
                    'item_name' => 'Kabel Dropcore',
                    'item_type' => 'kabel_dropcore',
                    'qty' => 50,
                    'unit' => 'meter',
                ],
            ],
        ]);

        $response = $this->actingAs($technician)
            ->post(route('customers.installation.speedtest', $customer->id), [
                'test_download' => 20,
                'test_upload' => 10,
                'speedtest_photo' => UploadedFile::fake()->image('speedtest.jpg'),
            ]);

        $response->assertSessionHas('success');

        $customer->refresh();
        $this->assertEquals('verification_admin', $customer->status);

        $task->refresh();
        $this->assertEquals(TaskStatus::SELESAI, $task->status);

        $installation = $customer->installations()->latest()->first();
        $this->assertEquals('completed', $installation->installation_status);
    }

    /**
     * @return array{0: Customer, 1: User, 2: Task}
     */
    private function setupInProgressInstallation(): array
    {
        $pop = Pop::create([
            'code' => 'GATE',
            'pop_code' => 'GATE',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP GATE',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $technician = User::factory()->create();
        $role = Role::where('name', 'Teknisi')->firstOrFail();
        $technician->role_id = $role->id;
        $technician->save();
        $technician->load('role');
        $technician->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP,
        ]);

        $customer = Customer::create([
            'customer_code' => 'TEST-GATE-001',
            'full_name' => 'Gate Speedtest Customer',
            'primary_phone' => '0812340000',
            'status' => 'installation_in_progress',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $customer->installations()->create([
            'installation_status' => 'in_progress',
            'started_at' => now(),
            'start_time' => now()->toTimeString(),
            'scheduled_date' => now()->format('Y-m-d'),
            'scheduled_time' => '09:00',
            'technician_id' => $technician->id,
        ]);

        $task = Task::create([
            'task_number' => 'TASK-TEST-GATE-001',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan Gate Speedtest Customer',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);

        return [$customer, $technician, $task];
    }
}
