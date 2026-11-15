<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\OwnershipMode;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerAdditionalDevice;
use App\Models\CustomerService;
use App\Models\FopTask;
use App\Models\InternetPackage;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-190 task 4 — langkah "Perangkat Tambahan" di Laporan Pemasangan:
 * teknisi memilih SN dari custody tim yang dipasang ke pelanggan (sejak
 * 2026-11-14 tanpa dropdown Status, dan semua perangkat ber-SN — bukan cuma
 * AP/STB), dengan jalur kekurangan + alasan wajib sebagai pengecualian (spec §4.2).
 */
class InstallationAdditionalDeviceStepTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private User $technician;

    private Customer $customer;

    private FopTask $fopTask;

    private Item $apItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create(['code' => 'CABANG-ADS', 'pop_code' => 'ADS', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Additional Step', 'type' => 'cabang', 'status' => 'active']);

        $role = Role::where('code', 'teknisi')->firstOrFail();
        $this->technician = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $this->technician->load('role');
        $this->technician->roleScopes()->create(['role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP]);

        $this->customer = Customer::create([
            'customer_code' => 'TEST-ADS-001',
            'full_name' => 'Pelanggan Additional Step',
            'primary_phone' => '0812340090',
            'status' => 'installation_in_progress',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $this->customer->installations()->create([
            'installation_status' => 'in_progress',
            'started_at' => now(),
            'start_time' => now()->toTimeString(),
            'scheduled_date' => now()->format('Y-m-d'),
            'scheduled_time' => '09:00',
            'technician_id' => $this->technician->id,
        ]);

        $task = Task::create([
            'task_number' => 'TASK-TEST-ADS-001',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan Additional Step',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        $this->fopTask = FopTask::create([
            'task_number' => 'TFOP-TEST-ADS-001',
            'task_date' => now(),
            'category' => TaskType::PEMASANGAN->value,
            'tugas' => 'Pemasangan Additional Step',
            'pop_id' => $this->pop->id,
            'customer_id' => $this->customer->id,
            'task_id' => $task->id,
            'issue' => 'Uji langkah perangkat tambahan',
            'status' => TaskStatus::IN_PROGRESS->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);

        $apCategory = ItemCategory::create(['code' => 'ap_step_test', 'name' => 'Access Point', 'default_unit' => 'pcs', 'is_active' => true, 'is_customer_extra_device' => true]);
        $this->apItem = Item::create([
            'code' => 'AP-STEP-TEST', 'name' => 'AP Step Test', 'brand' => 'TP-Link', 'model' => 'EAP110', 'unit' => 'pcs',
            'item_category_id' => $apCategory->id, 'tracking_type' => 'serialized', 'ownership_mode' => OwnershipMode::INSTALLABLE->value,
        ]);
    }

    #[Test]
    public function submit_rejected_when_decisions_incomplete_and_no_shortage_reason(): void
    {
        $this->givePackage(2);
        $ap1 = $this->issueAp('SN-ADS-AP-001');
        $this->issueAp('SN-ADS-AP-002');

        $response = $this->actingAs($this->technician)
            ->from(route('customers.installation.report', $this->customer))
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [
                    ['inventory_serial_id' => $ap1->id],
                ],
            ]);

        $response->assertRedirect(route('customers.installation.report', $this->customer));
        $response->assertSessionHasErrors('decisions');
        $this->assertDatabaseCount('customer_additional_devices', 0);
        $this->assertSame(SerialStatus::ISSUED, $ap1->refresh()->status);
    }

    #[Test]
    public function submit_allowed_with_shortage_reason_when_fewer_devices_decided_than_required(): void
    {
        $this->givePackage(2);
        $ap1 = $this->issueAp('SN-ADS-AP-011');

        $response = $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [
                    ['inventory_serial_id' => $ap1->id],
                ],
                'kekurangan_perangkat' => '1',
                'kekurangan_perangkat_alasan' => 'AP kedua pecah di jalan sebelum sampai lokasi.',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertSame([$ap1->id], $this->customer->installations()->latest()->first()->extra_device_serial_ids);

        $installation = $this->customer->installations()->latest()->first();
        $this->assertTrue($installation->extra_device_shortage);
        $this->assertSame('AP kedua pecah di jalan sebelum sampai lokasi.', $installation->extra_device_shortage_reason);
    }

    #[Test]
    public function speedtest_installs_selected_serial_without_retiring_primary_ont(): void
    {
        Storage::fake('public');
        $this->givePackage(1);
        $this->makeReadyForSpeedtest();

        // ONT utama sudah INSTALLED ke pelanggan ini (ownership default COMPANY).
        $ontCategory = ItemCategory::create(['code' => 'ont_step_test', 'name' => 'ONT Step Test', 'default_unit' => 'pcs', 'is_active' => true]);
        $ontItem = Item::create([
            'code' => 'ONT-STEP-TEST', 'name' => 'ONT Step Test', 'unit' => 'pcs',
            'item_category_id' => $ontCategory->id, 'tracking_type' => 'serialized', 'ownership_mode' => OwnershipMode::INSTALLABLE->value,
        ]);
        $ont = InventorySerial::create([
            'item_id' => $ontItem->id,
            'serial_number' => 'SN-ADS-ONT-001',
            'status' => SerialStatus::INSTALLED->value,
            'customer_id' => $this->customer->id,
            'installed_at' => now(),
        ]);

        $ap = $this->issueAp('SN-ADS-AP-021');

        $response = $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [
                    ['inventory_serial_id' => $ap->id],
                ],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Simpan langkah ini cuma pilihan — belum terpasang.
        $this->assertSame(SerialStatus::ISSUED, $ap->refresh()->status);
        $this->assertDatabaseCount('customer_additional_devices', 0);

        $this->actingAs($this->technician)
            ->post(route('customers.installation.speedtest', $this->customer), $this->speedtestPayload())
            ->assertSessionHasNoErrors();

        $ap->refresh();
        $this->assertSame(SerialStatus::INSTALLED, $ap->status);
        $this->assertSame($this->customer->id, $ap->customer_id);

        $device = CustomerAdditionalDevice::where('customer_id', $this->customer->id)->sole();
        $this->assertSame($ap->id, $device->inventory_serial_id);
        $this->assertSame('terpasang', $device->status);
        $this->assertSame('SN-ADS-AP-021', $device->serial_number);
        $this->assertSame('Access Point', $device->device_type);

        // Asersi terpenting task ini: ONT utama TIDAK ikut di-retur.
        $ont->refresh();
        $this->assertSame(SerialStatus::INSTALLED, $ont->status, 'ONT utama tidak boleh di-RETURN saat AP dipasang');
        $this->assertSame($this->customer->id, $ont->customer_id);

        // Pointer dikosongkan setelah dieksekusi — revisi gak meng-install ulang.
        $this->assertNull($this->customer->installations()->latest()->first()->extra_device_serial_ids);
    }

    /**
     * Dropdown Status dihapus (keputusan user 2026-11-14): SN yang dipilih di
     * langkah ini PSB = akan dipasang. Simpan cuma mencatat pilihan (draft
     * pointer), pemasangan sungguhan di Laporan Speedtest.
     */
    #[Test]
    public function sn_yang_dipilih_tersimpan_sebagai_pilihan_tanpa_pilih_status(): void
    {
        $this->givePackage(1);
        $ap = $this->issueAp('SN-ADS-AP-031');

        $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [['inventory_serial_id' => $ap->id]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(SerialStatus::ISSUED, $ap->refresh()->status);
        $this->assertSame([$ap->id], $this->customer->installations()->latest()->first()->extra_device_serial_ids);
        $this->assertDatabaseCount('customer_additional_devices', 0);

        $this->actingAs($this->technician)
            ->get(route('customers.installation.report', $this->customer))
            ->assertOk()
            ->assertDontSee('— Pilih status —')
            ->assertDontSee('Kembali ke Gudang');
    }

    #[Test]
    public function serial_outside_team_custody_is_rejected(): void
    {
        $this->givePackage(1);
        $otherTechnician = User::factory()->create();
        $foreignAp = $this->issueAp('SN-ADS-AP-041', $otherTechnician);

        $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [
                    ['inventory_serial_id' => $foreignAp->id],
                ],
            ])
            ->assertSessionHasErrors('decisions.0.inventory_serial_id');

        $this->assertSame(SerialStatus::ISSUED, $foreignAp->refresh()->status);
    }

    #[Test]
    public function extra_device_serial_is_not_accepted_as_primary_device_in_step_five(): void
    {
        $this->givePackage(1);
        $ap = $this->issueAp('SN-ADS-AP-051');

        $this->actingAs($this->technician)
            ->post(route('customers.installation.pemasangan', $this->customer), [
                'device_type' => 'ont',
                'connection_mode' => 'pppoe',
                'wifi_ssid' => 'WHUSNET_ADS',
                'wifi_password' => 'password123',
                'odp_number' => 'ODP-01',
                'odp_port' => '1',
                'selected_inventory_serial_id' => $ap->id,
            ])
            ->assertSessionHasErrors('selected_inventory_serial_id');
    }

    #[Test]
    public function perangkat_untuk_pelanggan_lain_cukup_tidak_dipilih(): void
    {
        $this->givePackage(1);
        $mine = $this->issueAp('SN-ADS-AP-081');
        $otherCustomers = $this->issueAp('SN-ADS-AP-082');

        $response = $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [['inventory_serial_id' => $mine->id]],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertSame([$mine->id], $this->customer->installations()->latest()->first()->extra_device_serial_ids);
        $this->assertSame(SerialStatus::ISSUED, $otherCustomers->refresh()->status);
        $this->assertSame($this->technician->id, $otherCustomers->current_technician_id);
    }

    /**
     * Permintaan user 2026-11-14: daftar Perangkat Tambahan memuat SEMUA
     * perangkat ber-SN di custody tim, bukan cuma kategori AP/STB.
     */
    #[Test]
    public function perangkat_ber_sn_non_ap_ikut_tampil_dan_bisa_dipasang(): void
    {
        $this->givePackage(1);
        $router = $this->issueSerialOfCategory('router_step_test', 'Router', 'SN-ADS-RTR-401');

        $this->actingAs($this->technician)
            ->get(route('customers.installation.report', $this->customer))
            ->assertOk()
            ->assertSee('SN-ADS-RTR-401');

        $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [['inventory_serial_id' => $router->id]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([$router->id], $this->customer->installations()->latest()->first()->extra_device_serial_ids);
    }

    /**
     * SN yang sudah dipilih jadi perangkat UTAMA di Step 5 baru di-install di
     * Laporan Speedtest — kalau boleh dipasang duluan sebagai perangkat
     * tambahan, Speedtest gagal karena SN-nya sudah INSTALLED.
     */
    #[Test]
    public function sn_perangkat_utama_step_5_tidak_bisa_dipilih_jadi_perangkat_tambahan(): void
    {
        $this->givePackage(1);
        $ont = $this->issueSerialOfCategory('ont_step_test', 'ONT', 'SN-ADS-ONT-501');
        $this->customer->installations()->latest()->first()->update(['selected_inventory_serial_id' => $ont->id]);

        $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [['inventory_serial_id' => $ont->id]],
            ])
            ->assertSessionHasErrors('decisions.0.inventory_serial_id');

        $this->assertSame(SerialStatus::ISSUED, $ont->refresh()->status);
    }

    /**
     * Permintaan user 2026-11-14: pilihan Perangkat Tambahan yang sudah
     * disimpan harus tetap tampil setelah refresh dan bisa diganti — sama
     * Barang Dipasang Step 5. Dulu SN langsung terpasang begitu disimpan.
     */
    #[Test]
    public function pilihan_tersimpan_tampil_lagi_dan_bisa_diganti_dengan_simpan_ulang(): void
    {
        $this->givePackage(1);
        $first = $this->issueAp('SN-ADS-AP-601');
        $second = $this->issueAp('SN-ADS-AP-602');

        $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [['inventory_serial_id' => $first->id]],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->technician)
            ->get(route('customers.installation.report', $this->customer))
            ->assertOk()
            // @js() meng-encode kutip jadi \u0022 — baris prefill membawa SN tersimpan.
            ->assertSee('\u0022serial\u0022:\u0022'.$first->id.'\u0022', false);

        $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [['inventory_serial_id' => $second->id]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([$second->id], $this->customer->installations()->latest()->first()->extra_device_serial_ids);
        $this->assertSame(SerialStatus::ISSUED, $first->refresh()->status);
        $this->assertSame(SerialStatus::ISSUED, $second->refresh()->status);
    }

    /**
     * Pilihan Perangkat Tambahan belum terpasang (SN masih ISSUED), jadi SN
     * yang sama bisa muncul di dropdown modem utama Step 5. Kalau lolos,
     * Speedtest meng-install SN yang sama dua kali.
     */
    #[Test]
    public function sn_pilihan_perangkat_tambahan_ditolak_jadi_modem_utama_step_5(): void
    {
        $this->givePackage(1);
        $router = $this->issueSerialOfCategory('router_step_test', 'Router', 'SN-ADS-RTR-701');

        $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [['inventory_serial_id' => $router->id]],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->technician)
            ->get(route('customers.installation.report', $this->customer))
            ->assertViewHas('eligibleSerials', fn ($serials) => ! $serials->pluck('id')->contains($router->id));

        $response = $this->actingAs($this->technician)
            ->post(route('customers.installation.pemasangan', $this->customer), [
                'device_type' => 'ont',
                'connection_mode' => 'pppoe',
                'wifi_ssid' => 'WHUSNET_ADS',
                'wifi_password' => 'password123',
                'odp_number' => 'ODP-01',
                'odp_port' => '1',
                'selected_inventory_serial_id' => $router->id,
            ]);

        $response->assertSessionHasErrors('selected_inventory_serial_id');
        $this->assertStringContainsString('Perangkat Tambahan', session('errors')->first('selected_inventory_serial_id'));
    }

    #[Test]
    public function speedtest_ditolak_kalau_sn_pilihan_sudah_keluar_dari_custody_tim(): void
    {
        Storage::fake('public');
        $this->givePackage(1);
        $ap = $this->issueAp('SN-ADS-AP-801');
        $this->makeReadyForSpeedtest();

        $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [['inventory_serial_id' => $ap->id]],
            ])
            ->assertSessionHasNoErrors();

        // Gudang mengalihkan SN ke teknisi lain setelah pilihan disimpan.
        $ap->update(['current_technician_id' => User::factory()->create()->id]);

        $this->actingAs($this->technician)
            ->post(route('customers.installation.speedtest', $this->customer), $this->speedtestPayload())
            ->assertSessionHasErrors('extra_devices');

        $this->assertSame('installation_in_progress', $this->customer->refresh()->status);
        $this->assertDatabaseCount('customer_additional_devices', 0);
    }

    private function issueSerialOfCategory(string $categoryCode, string $categoryName, string $serialNumber): InventorySerial
    {
        $category = ItemCategory::create(['code' => $categoryCode, 'name' => $categoryName, 'default_unit' => 'pcs', 'is_active' => true, 'is_customer_extra_device' => false]);
        $item = Item::create([
            'code' => strtoupper($categoryCode), 'name' => $categoryName.' Step Test', 'unit' => 'pcs',
            'item_category_id' => $category->id, 'tracking_type' => 'serialized', 'ownership_mode' => OwnershipMode::INSTALLABLE->value,
        ]);

        return InventorySerial::create([
            'item_id' => $item->id, 'serial_number' => $serialNumber, 'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id, 'issued_from_pop_id' => $this->pop->id,
        ]);
    }

    /**
     * Review fix 1 — spec §4.2 berlaku ke Laporan Pemasangan utuh, titik
     * selesainya storeSpeedtest(): langkah Perangkat Tambahan gak boleh
     * dilewati begitu saja.
     */
    #[Test]
    public function speedtest_rejected_when_extra_device_step_never_submitted(): void
    {
        $this->givePackage(1);
        $this->issueAp('SN-ADS-AP-091');
        $this->makeReadyForSpeedtest();

        $response = $this->actingAs($this->technician)
            ->post(route('customers.installation.speedtest', $this->customer), $this->speedtestPayload());

        $response->assertSessionHasErrors('extra_devices');
        $this->assertSame('installation_in_progress', $this->customer->refresh()->status);
    }

    #[Test]
    public function speedtest_passes_extra_device_gate_once_step_was_submitted(): void
    {
        Storage::fake('public');
        $this->givePackage(1);
        $ap = $this->issueAp('SN-ADS-AP-101');
        $this->makeReadyForSpeedtest();

        $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), [
                'decisions' => [['inventory_serial_id' => $ap->id]],
            ])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($this->customer->installations()->latest()->first()->extra_devices_reported_at);

        $response = $this->actingAs($this->technician)
            ->post(route('customers.installation.speedtest', $this->customer), $this->speedtestPayload());

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');
        $this->assertSame('verification_admin', $this->customer->refresh()->status);
        $this->assertSame(SerialStatus::INSTALLED, $ap->refresh()->status);
        $this->assertSame('terpasang', CustomerAdditionalDevice::where('customer_id', $this->customer->id)->sole()->status);
    }

    #[Test]
    public function speedtest_not_gated_when_package_has_no_extra_devices(): void
    {
        Storage::fake('public');
        $this->givePackage(0);
        $this->makeReadyForSpeedtest();

        $response = $this->actingAs($this->technician)
            ->post(route('customers.installation.speedtest', $this->customer), $this->speedtestPayload());

        $response->assertSessionHasNoErrors();
        $this->assertSame('verification_admin', $this->customer->refresh()->status);
    }

    /**
     * Jatah perangkat tambahan cuma dihitung TOTAL (`max_additional_devices`),
     * bukan per kategori — teknisi bebas penuhi dengan jenis barang apa pun
     * yang dibawa (AP, STB, dll), karena ketentuan jenis barang bisa beda di
     * kemudian hari sedangkan jumlahnya tetap.
     */
    #[Test]
    public function requirement_counts_any_extra_device_type_not_just_access_points(): void
    {
        $this->givePackage(3);

        $stbCategory = ItemCategory::create(['code' => 'stb_step_test', 'name' => 'Set Top Box', 'default_unit' => 'pcs', 'is_active' => true, 'is_customer_extra_device' => true]);
        $stbItem = Item::create([
            'code' => 'STB-STEP-TEST', 'name' => 'STB Step Test', 'unit' => 'pcs',
            'item_category_id' => $stbCategory->id, 'tracking_type' => 'serialized', 'ownership_mode' => OwnershipMode::INSTALLABLE->value,
        ]);
        $stb = InventorySerial::create([
            'item_id' => $stbItem->id,
            'serial_number' => 'SN-ADS-STB-301',
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => $this->technician->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);

        $aps = [
            $this->issueAp('SN-ADS-AP-301'),
            $this->issueAp('SN-ADS-AP-302'),
        ];

        $report = $this->actingAs($this->technician)->get(route('customers.installation.report', $this->customer));
        $report->assertOk()
            ->assertSee('Wajib 3 Perangkat')
            ->assertDontSee('Kebutuhan paket:')
            ->assertSee('SN-ADS-AP-301')
            ->assertSee('SN-ADS-STB-301');

        $mixedDecisions = [
            ['inventory_serial_id' => $aps[0]->id],
            ['inventory_serial_id' => $aps[1]->id],
        ];

        // 2 dari 3 barang (2 AP + 0 STB) tanpa kekurangan → ditolak, cuma soal total.
        $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), ['decisions' => $mixedDecisions])
            ->assertSessionHasErrors('decisions');
        $this->assertDatabaseCount('customer_additional_devices', 0);

        // 2 AP + 1 STB = 3 barang campuran → lolos, jenis barang tidak dibatasi.
        $mixedDecisions[] = ['inventory_serial_id' => $stb->id];
        $this->actingAs($this->technician)
            ->post(route('customers.installation.additional-devices.store', $this->customer), ['decisions' => $mixedDecisions])
            ->assertSessionHasNoErrors();
        $this->assertCount(3, $this->customer->installations()->latest()->first()->extra_device_serial_ids);
        $this->assertSame(SerialStatus::ISSUED, $stb->refresh()->status);
    }

    #[Test]
    public function report_page_shows_step_only_when_package_requires_extra_devices(): void
    {
        $this->givePackage(1);
        $this->issueAp('SN-ADS-AP-061');

        $this->actingAs($this->technician)
            ->get(route('customers.installation.report', $this->customer))
            ->assertOk()
            ->assertSee('form-additional-devices', false)
            ->assertSee('SN-ADS-AP-061');
    }

    /**
     * ADHOC-297 lanjutan (permintaan user 2026-11-13): picker perangkat
     * tambahan pakai pola Kategori → Nama Barang seperti "Barang Dipasang"
     * Step 5, bukan <select> tunggal "Kategori — Nama · SN" yang gak bisa
     * dicari. Field yang dikirim ke server tetap decisions[i][inventory_serial_id].
     */
    #[Test]
    public function picker_perangkat_tambahan_pakai_kategori_lalu_nama_barang(): void
    {
        $this->givePackage(1);
        $this->issueAp('SN-ADS-AP-081');

        $response = $this->actingAs($this->technician)
            ->get(route('customers.installation.report', $this->customer))
            ->assertOk();

        // @js() meng-escape kutip jadi 0022 — dibuka supaya JSON opsi bisa dibaca.
        $html = str_replace('\u0022', '"', html_entity_decode($response->getContent(), ENT_QUOTES));

        $this->assertStringContainsString('categoryOptionsFor(index)', $html);
        $this->assertStringContainsString('serialOptionsFor(index)', $html);
        $this->assertStringContainsString('"category_name":"Access Point"', $html);
        $this->assertStringContainsString('SN-ADS-AP-081', $html);
        $this->assertStringContainsString('`decisions[${index}][inventory_serial_id]`', $html);
        $this->assertStringNotContainsString('x-model="row.serial"', $html);
    }

    #[Test]
    public function report_page_hides_step_when_package_limit_is_zero(): void
    {
        $this->givePackage(0);
        $this->issueAp('SN-ADS-AP-071');

        $this->actingAs($this->technician)
            ->get(route('customers.installation.report', $this->customer))
            ->assertOk()
            ->assertDontSee('form-additional-devices', false);
    }

    /**
     * Gerbang foto storeSpeedtest() cukup dicek dari kolom tersimpan —
     * path diisi langsung, file fisiknya tidak dibaca di jalur ini.
     */
    private function makeReadyForSpeedtest(): void
    {
        $this->customer->installations()->latest()->first()->update([
            'installation_photo' => 'installations/foto.jpg',
            'contract_photo' => 'installations/kontrak.jpg',
            'signature_photo' => 'installations/ttd.jpg',
        ]);
    }

    private function speedtestPayload(): array
    {
        return [
            'test_upload' => 20,
            'test_download' => 20,
            'speedtest_photo' => UploadedFile::fake()->image('speedtest.jpg'),
        ];
    }

    private function givePackage(int $maxAdditionalDevices): void
    {
        $package = InternetPackage::create([
            'package_code' => 'ADS-PKT-'.$maxAdditionalDevices,
            'name' => 'Paket ADS '.$maxAdditionalDevices,
            'category' => 'Bisnis',
            'package_group' => 'Basic',
            'bandwidth_label' => '20 Mbps',
            'download_speed_mbps' => 20,
            'upload_speed_mbps' => 20,
            'monthly_price' => 300000,
            'max_additional_devices' => $maxAdditionalDevices,
        ]);

        CustomerService::create([
            'customer_id' => $this->customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'download_speed_snapshot' => $package->bandwidth_label,
            'upload_speed_snapshot' => $package->bandwidth_label,
            'monthly_price' => $package->monthly_price,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $package->monthly_price,
            'activation_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'billing_cycle' => 'monthly',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);
    }

    private function issueAp(string $serialNumber, ?User $holder = null): InventorySerial
    {
        return InventorySerial::create([
            'item_id' => $this->apItem->id,
            'serial_number' => $serialNumber,
            'status' => SerialStatus::ISSUED->value,
            'current_technician_id' => ($holder ?? $this->technician)->id,
            'issued_from_pop_id' => $this->pop->id,
        ]);
    }
}
