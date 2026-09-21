<?php

namespace Tests\Feature;

use App\Enums\DeviceRetrievalOutcome;
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

    public function test_retrieve_device_works_for_legacy_customer_without_device_row(): void
    {
        // Regresi laporan uji manual 2026-09-21: pelanggan legacy tanpa baris
        // customer_devices mentok di "Data alat pelanggan tidak ditemukan."
        // padahal baris itu cuma tempat menyimpan device_retrieved_at.
        $this->loginAsAdmin();
        $customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'village_id' => $this->village->id,
            'status' => 'terminated',
        ]);
        $this->assertNull($customer->customerDevice);

        $this->post(route('customers.retrieve-device', $customer))
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionMissing('error');

        $this->assertDatabaseHas('fop_tasks', [
            'customer_id' => $customer->id,
            'category' => TaskType::AMBIL_MODEM->value,
            'status' => TaskStatus::DRAFT->value,
        ]);

        $device = $customer->refresh()->customerDevice;
        $this->assertNotNull($device);
        $this->assertSame(CustomerDevice::LEGACY_DEVICE_TYPE, $device->device_type);
        $this->assertNull($device->device_retrieved_at, 'belum diambil sampai teknisi melapor');
    }

    public function test_retrieve_device_for_legacy_customer_does_not_duplicate_existing_device_row(): void
    {
        $this->loginAsAdmin();
        $customer = $this->makeTerminatedCustomerWithDevice();

        $this->post(route('customers.retrieve-device', $customer))->assertSessionHas('success');

        $this->assertSame(1, CustomerDevice::where('customer_id', $customer->id)->count());
        $this->assertSame('ONT', $customer->refresh()->customerDevice->device_type, 'data perangkat asli tidak ditimpa placeholder');
    }

    public function test_terminated_list_shows_sedang_diproses_badge_once_retrieval_task_exists(): void
    {
        $this->loginAsAdmin();
        $customer = $this->makeTerminatedCustomerWithDevice();

        // Form tombol dikenali dari URL aksinya (teks "Ambil Alat" juga bisa muncul di tempat lain).
        $retrieveUrl = route('customers.retrieve-device', $customer);

        // Belum ada task → "Belum Diambil" dan tombol Ambil Alat tampil.
        $this->get(route('customers.terminated'))
            ->assertOk()
            ->assertSee('Belum Diambil')
            ->assertDontSee('Sedang Diproses')
            ->assertSee($retrieveUrl, false);

        // Ambil Alat ditekan → task dibuat → "Sedang Diproses" dan tombolnya HILANG.
        $this->post($retrieveUrl)->assertSessionHas('success');

        $this->get(route('customers.terminated'))
            ->assertOk()
            ->assertSee('Sedang Diproses')
            ->assertDontSee('Belum Diambil')
            ->assertDontSee($retrieveUrl, false);
    }

    public function test_sedang_diproses_badge_is_per_customer_and_hidden_once_task_is_finished_or_cancelled(): void
    {
        $this->loginAsAdmin();
        $processed = $this->makeTerminatedCustomerWithDevice();
        $untouched = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'village_id' => $this->village->id,
            'full_name' => 'Pelanggan Belum Dijadwalkan',
            'status' => 'terminated',
        ]);
        CustomerDevice::create(['customer_id' => $untouched->id, 'device_type' => 'ONT']);

        $this->post(route('customers.retrieve-device', $processed))->assertSessionHas('success');

        // Satu halaman memuat dua pelanggan: hanya yang punya task berjalan bertanda "Sedang Diproses".
        $html = $this->get(route('customers.terminated'))->assertOk()->getContent();
        // Tiap pelanggan tampil di dua layout (tabel desktop + kartu mobile) → 2 kemunculan per badge.
        $this->assertSame(2, substr_count($html, 'Sedang Diproses'), 'hanya pelanggan yang dijadwalkan');
        $this->assertSame(2, substr_count($html, 'Belum Diambil'), 'pelanggan lain tetap Belum Diambil');

        // Tombol Ambil Alat: hilang untuk yang diproses, tetap ada untuk yang belum dijadwalkan.
        $this->assertStringNotContainsString(route('customers.retrieve-device', $processed), $html);
        $this->assertStringContainsString(route('customers.retrieve-device', $untouched), $html);

        // Task dibatalkan → penanda hilang, kembali "Belum Diambil" dan tombol muncul lagi (bisa dijadwalkan ulang).
        FopTask::where('customer_id', $processed->id)->update(['status' => TaskStatus::DIBATALKAN->value]);
        $this->get(route('customers.terminated'))
            ->assertDontSee('Sedang Diproses')
            ->assertSee(route('customers.retrieve-device', $processed), false);

        // Alat sudah diambil → "Sudah Diambil" menang atas apa pun, tombol hilang.
        $processed->customerDevice->update(['device_retrieved_at' => now()]);
        FopTask::where('customer_id', $processed->id)->update(['status' => TaskStatus::DRAFT->value]);
        $this->get(route('customers.terminated'))
            ->assertSee('Sudah Diambil')
            ->assertDontSee('Sedang Diproses')
            ->assertDontSee(route('customers.retrieve-device', $processed), false);
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

        // ADHOC-86: DEAC tidak bisa selesai tanpa laporan alat; "diambil"
        // barulah yang mengisi device_retrieved_at.
        $task->deviceRetrieval()->create(['outcome' => DeviceRetrievalOutcome::DIAMBIL]);

        app(TaskService::class)->complete($task, $actor);

        $this->assertNotNull($customer->customerDevice->refresh()->device_retrieved_at);
    }
}
