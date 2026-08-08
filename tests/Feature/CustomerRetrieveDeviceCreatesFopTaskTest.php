<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Task;
use App\Models\Village;
use App\Services\TaskService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Ambil Alat" di List Putus Langganan sekarang bikin Task FOP kategori Ambil
 * Modem (DEAC) — bukan langsung tandai device_retrieved_at sekali klik. Alat
 * baru ditandai diambil otomatis setelah Task-nya diselesaikan teknisi, sama
 * kayak alur MTN/C-REQ. Lihat CustomerController::retrieveDevice(),
 * TicketService::createDeviceRetrievalTask(), TaskService::complete().
 */
class CustomerRetrieveDeviceCreatesFopTaskTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $city = City::firstOrCreate(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan Test']);
        $this->village = Village::create(['district_id' => $district->id, 'name' => 'Polorejo Test', 'postal_code' => '63491']);

        $this->pop = Pop::create([
            'name' => 'POP Polorejo Test',
            'code' => 'POP-PLR-TST',
            'pop_code' => 'PLT',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'type' => 'branch',
            'address' => 'Polorejo',
            'status' => 'active',
            'city_id' => $city->id,
        ]);
    }

    private function makeTerminatedCustomerWithDevice(): Customer
    {
        $customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'village_id' => $this->village->id,
            'full_name' => 'Budi Santoso',
            'status' => 'terminated',
        ]);

        CustomerDevice::create([
            'customer_id' => $customer->id,
            'device_type' => 'ONT',
            'brand' => 'Huawei',
            'model' => 'HG8245H',
        ]);

        return $customer->refresh();
    }

    public function test_retrieve_device_creates_draft_fop_task_ambil_modem(): void
    {
        $this->loginAsAdmin();
        $customer = $this->makeTerminatedCustomerWithDevice();

        $response = $this->post(route('customers.retrieve-device', $customer));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('fop_tasks', [
            'customer_id' => $customer->id,
            'category' => TaskType::AMBIL_MODEM->value,
            'status' => TaskStatus::DRAFT->value,
        ]);

        // Alat belum ditandai diambil — nunggu teknisi selesaikan task-nya.
        $this->assertNull($customer->customerDevice->refresh()->device_retrieved_at);
    }

    public function test_retrieve_device_blocked_if_already_retrieved(): void
    {
        $this->loginAsAdmin();
        $customer = $this->makeTerminatedCustomerWithDevice();
        $customer->customerDevice->update(['device_retrieved_at' => now()]);

        $response = $this->post(route('customers.retrieve-device', $customer));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('fop_tasks', ['customer_id' => $customer->id]);
    }

    public function test_retrieve_device_blocked_if_open_task_already_exists(): void
    {
        $this->loginAsAdmin();
        $customer = $this->makeTerminatedCustomerWithDevice();

        FopTask::create([
            'task_number' => 'TFOP-2026-9001',
            'task_date' => now(),
            'category' => TaskType::AMBIL_MODEM->value,
            'tugas' => 'Existing Ambil Modem',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'issue' => 'Pengambilan alat pelanggan putus langganan.',
            'status' => TaskStatus::DRAFT->value,
            'priority' => 'Medium',
        ]);

        $response = $this->post(route('customers.retrieve-device', $customer));

        $response->assertSessionHas('error');
        $this->assertDatabaseCount('fop_tasks', 1);
    }

    public function test_completing_ambil_modem_task_marks_device_retrieved(): void
    {
        $actor = $this->loginAsAdmin();
        $customer = $this->makeTerminatedCustomerWithDevice();

        $task = Task::create([
            'task_number' => 'TASK-2026-9001',
            'task_type' => TaskType::AMBIL_MODEM->value,
            'title' => 'FOP: Ambil Modem Budi Santoso',
            'pop_id' => $this->pop->id,
            'customer_id' => $customer->id,
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        app(TaskService::class)->complete($task, $actor);

        $this->assertNotNull($customer->customerDevice->refresh()->device_retrieved_at);
    }
}
