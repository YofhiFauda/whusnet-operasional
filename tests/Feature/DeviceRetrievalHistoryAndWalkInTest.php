<?php

namespace Tests\Feature;

use App\Enums\DeviceRetrievalOutcome;
use App\Enums\DeviceRetrievalSource;
use App\Enums\InventoryTransactionType;
use App\Enums\ItemCondition;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\CustomerTechnicalDetail;
use App\Models\DeviceRetrievalLog;
use App\Models\FopTask;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskTeam;
use App\Models\User;
use App\Services\InventoryReceiveService;
use App\Services\LegacyDeviceHintService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-88 — riwayat pengambilan alat per SN (log teknisi), nilai taksiran
 * opsional, "Terima modem dari pelanggan" (tanpa task DEAC), petunjuk merek
 * dari data lama, dan reset `device_retrieved_at` saat Langganan Lagi.
 * Rancangan: docs/plan/warehouse/analisa-riwayat-dan-terima-modem-dari-pelanggan.md.
 */
class DeviceRetrievalHistoryAndWalkInTest extends TestCase
{
    use RefreshDatabase;

    private User $teknisi;

    private User $owner;

    private Pop $pusat;

    private Pop $cabang;

    private Pop $miniPop;

    private Item $modem;

    private Item $modemLegacy;

    private Customer $customer;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WarehouseFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(TaskFeatureSeeder::class);
        $this->seed(WorkflowTransitionPermissionSeeder::class);

        foreach (Permission::all() as $permission) {
            if ($permission->code) {
                Gate::define($permission->code, fn ($user) => $user->hasPermission($permission->code));
            }
        }

        $teknisiRole = Role::where('code', 'teknisi')->firstOrFail();
        $ownerRole = Role::where('code', 'owner')->firstOrFail();

        $this->teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'name' => 'Teknisi Budi']);
        $this->teknisi->roleScopes()->create(['role_id' => $teknisiRole->id, 'scope_type' => ScopeType::ALL_POP->value]);

        $this->owner = User::factory()->create(['role_id' => $ownerRole->id, 'name' => 'Owner Sari']);
        $this->owner->roleScopes()->create(['role_id' => $ownerRole->id, 'scope_type' => ScopeType::ALL_POP->value]);

        $this->pusat = Pop::create(['code' => 'HS-PUSAT', 'pop_code' => 'HSP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Riwayat Test', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'HS-CABANG', 'pop_code' => 'HSC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Riwayat Test', 'type' => 'cabang', 'status' => 'active']);
        $this->miniPop = Pop::create(['code' => 'HS-MINI', 'pop_code' => 'HSM', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Mini POP Riwayat Test', 'type' => 'mini_pop', 'status' => 'active', 'parent_id' => $this->cabang->id]);

        $cat = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $this->modem = Item::create(['code' => 'HS-MODEM', 'name' => 'Modem Riwayat Test', 'item_category_id' => $cat->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);
        $this->modemLegacy = Item::create(['code' => 'MODEM-PELANGGAN-LAMA', 'name' => 'Modem Pelanggan Lama (Belum Teridentifikasi)', 'item_category_id' => $cat->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);

        $this->customer = $this->makeTerminatedCustomer('Siti Aminah');
        $this->task = $this->makeDeacTask($this->customer);
    }

    private function makeTerminatedCustomer(string $name): Customer
    {
        $customer = Customer::factory()->create(['pop_id' => $this->miniPop->id, 'status' => 'terminated', 'full_name' => $name]);
        CustomerDevice::create(['customer_id' => $customer->id, 'device_type' => 'ONT', 'brand' => 'ZTE', 'model' => 'F609']);

        return $customer;
    }

    private function makeDeacTask(Customer $customer): Task
    {
        $task = Task::create([
            'task_number' => 'TASK-2026-'.random_int(1000, 9999),
            'task_type' => TaskType::AMBIL_MODEM->value,
            'title' => 'Ambil Modem '.$customer->id,
            'pop_id' => $this->miniPop->id,
            'customer_id' => $customer->id,
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $this->owner->id,
            'updated_by' => $this->owner->id,
        ]);

        TaskTeam::create(['task_id' => $task->id, 'user_id' => $this->teknisi->id, 'role_in_task' => 'lead']);

        return $task;
    }

    private function makeInstalledSerial(string $sn, ?Customer $customer = null): InventorySerial
    {
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, [$sn], 250000, $this->owner);

        $serial->update([
            'status' => SerialStatus::INSTALLED,
            'issued_from_pop_id' => $this->cabang->id,
            'current_pop_id' => null,
            'customer_id' => ($customer ?? $this->customer)->id,
        ]);

        return $serial;
    }

    private function deacPickup(string $sn): void
    {
        $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $this->task), [
            'outcome' => DeviceRetrievalOutcome::DIAMBIL->value,
            'serials' => [['serial_number' => $sn, 'item_id' => '']],
            'condition_photo' => UploadedFile::fake()->image('kondisi.jpg'),
        ])->assertSessionHasNoErrors();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function walkInPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'cabang_pop_id' => $this->cabang->id,
            'serials' => [['serial_number' => 'WI-SN-001', 'item_id' => $this->modem->id]],
            'condition' => 'used_good',
            'condition_photo' => UploadedFile::fake()->image('serah.jpg'),
            'accessories' => ['adaptor'],
            'estimated_value' => null,
            'notes' => null,
        ], $overrides);
    }

    // ── Log teknisi + nilai taksiran (jalur DEAC) ───────────────────────

    #[Test]
    public function pengambilan_deac_menulis_log_teknisi_lalu_dilengkapi_saat_gudang_menerima(): void
    {
        $serial = $this->makeInstalledSerial('HS-SN-001');
        $this->deacPickup('HS-SN-001');

        $log = DeviceRetrievalLog::where('serial_id', $serial->id)->firstOrFail();
        $this->assertEquals($this->customer->id, $log->customer_id);
        $this->assertEquals('HS-SN-001', $log->serial_number);
        $this->assertEquals(DeviceRetrievalSource::DEAC, $log->source);
        $this->assertEquals($this->teknisi->id, $log->retrieved_by);
        $this->assertEquals($this->task->id, $log->task_id);
        $this->assertEquals($this->cabang->id, $log->warehouse_pop_id);
        $this->assertFalse($log->isReceived());
        $this->assertNotNull($log->photoPath(), 'foto kondisi dibaca dari laporan task');

        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.receive.store', $serial), ['condition' => 'used_damaged', 'estimated_value' => '150.000'])
            ->assertSessionHas('success');

        $log->refresh();
        $this->assertTrue($log->isReceived());
        $this->assertEquals($this->owner->id, $log->received_by);
        $this->assertEquals(ItemCondition::USED_DAMAGED, $log->condition);
        $this->assertEquals('150000.00', $log->estimated_value);
        // Pelaku pengambilan tetap teknisi — tidak tertimpa penerima gudang.
        $this->assertEquals($this->teknisi->id, $log->retrieved_by);
    }

    #[Test]
    public function nilai_taksiran_kosong_diterima_dan_tidak_menulis_harga_di_ledger(): void
    {
        $serial = $this->makeInstalledSerial('HS-SN-002');
        $this->deacPickup('HS-SN-002');

        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.receive.store', $serial), ['condition' => 'used_good', 'estimated_value' => ''])
            ->assertSessionHasNoErrors();

        $received = InventoryTransaction::where('serial_id', $serial->id)
            ->where('type', InventoryTransactionType::RETURN->value)
            ->whereNotNull('to_pop_id')
            ->firstOrFail();

        $this->assertNull($received->unit_price_snapshot);
    }

    #[Test]
    public function nilai_taksiran_disimpan_di_ledger_baris_penerimaan(): void
    {
        $serial = $this->makeInstalledSerial('HS-SN-003');
        $this->deacPickup('HS-SN-003');

        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.receive.store', $serial), ['condition' => 'used_good', 'estimated_value' => '1.250.000'])
            ->assertSessionHasNoErrors();

        // Tipe RETURN saja — baris RECEIVE pengadaan awal SN juga ber-`to_pop_id`.
        $received = InventoryTransaction::where('serial_id', $serial->id)
            ->where('type', InventoryTransactionType::RETURN->value)
            ->whereNotNull('to_pop_id')
            ->firstOrFail();
        $this->assertEquals(1250000.0, (float) $received->unit_price_snapshot);
    }

    #[Test]
    public function nilai_taksiran_negatif_ditolak(): void
    {
        $serial = $this->makeInstalledSerial('HS-SN-004');
        $this->deacPickup('HS-SN-004');

        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.receive.store', $serial), ['condition' => 'used_good', 'estimated_value' => '-5000'])
            ->assertSessionHasErrors('estimated_value');

        $this->assertEquals(SerialStatus::RETURNED, $serial->refresh()->status);
    }

    // ── Halaman Riwayat Pengambilan Alat ────────────────────────────────

    #[Test]
    public function halaman_riwayat_menampilkan_teknisi_pelanggan_dan_sn_serta_bisa_difilter(): void
    {
        $this->makeInstalledSerial('HS-SN-100');
        $this->deacPickup('HS-SN-100');

        $this->actingAs($this->owner)
            ->get(route('warehouse.retrievals.index'))
            ->assertOk()
            ->assertSee('HS-SN-100')
            ->assertSee('Teknisi Budi')
            ->assertSee('Siti Aminah')
            ->assertSee('Transit (di teknisi)');

        $this->actingAs($this->owner)
            ->get(route('warehouse.retrievals.index', ['technician' => $this->owner->id]))
            ->assertOk()
            ->assertDontSee('HS-SN-100');

        $this->actingAs($this->owner)
            ->get(route('warehouse.retrievals.index', ['status' => 'diterima']))
            ->assertDontSee('HS-SN-100');

        $this->actingAs($this->owner)
            ->get(route('warehouse.retrievals.index', ['status' => 'transit', 'q' => 'Aminah']))
            ->assertSee('HS-SN-100');
    }

    #[Test]
    public function halaman_riwayat_tunduk_pada_scope_pop_gudang(): void
    {
        $this->makeInstalledSerial('HS-SN-101');
        $this->deacPickup('HS-SN-101');

        $otherCabang = Pop::create(['code' => 'HS-CAB2', 'pop_code' => 'HS2', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Lain', 'type' => 'cabang', 'status' => 'active']);

        // pop_admin punya izin gudang tapi terikat scope POP (owner/atasan
        // selalu akses semua POP, jadi tidak cocok untuk menguji scope) —
        // di sini HANYA boleh melihat "Cabang Lain".
        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $scoped = User::factory()->create(['role_id' => $popAdminRole->id]);
        $scope = $scoped->roleScopes()->create(['role_id' => $popAdminRole->id, 'scope_type' => ScopeType::SELECTED_POP->value]);
        $scope->targets()->create(['pop_id' => $otherCabang->id]);

        $this->actingAs($scoped)
            ->get(route('warehouse.retrievals.index'))
            ->assertOk()
            ->assertDontSee('HS-SN-101');

        // Kontrol positif: scope yang mencakup gudang tujuannya melihat baris ini.
        $insideScope = User::factory()->create(['role_id' => $popAdminRole->id]);
        $inside = $insideScope->roleScopes()->create(['role_id' => $popAdminRole->id, 'scope_type' => ScopeType::SELECTED_POP->value]);
        $inside->targets()->create(['pop_id' => $this->cabang->id]);

        $this->actingAs($insideScope)
            ->get(route('warehouse.retrievals.index'))
            ->assertOk()
            ->assertSee('HS-SN-101');
    }

    #[Test]
    public function modem_diantar_di_luar_scope_pop_ditolak(): void
    {
        $otherCabang = Pop::create(['code' => 'HS-CAB3', 'pop_code' => 'HS3', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Lain 3', 'type' => 'cabang', 'status' => 'active']);

        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();
        $scoped = User::factory()->create(['role_id' => $popAdminRole->id]);
        $scope = $scoped->roleScopes()->create(['role_id' => $popAdminRole->id, 'scope_type' => ScopeType::SELECTED_POP->value]);
        $scope->targets()->create(['pop_id' => $otherCabang->id]);

        // Pelanggan berada di mini POP di bawah cabang lain → di luar scope aktor.
        $this->actingAs($scoped)
            ->post(route('warehouse.returns.from-customer.store'), $this->walkInPayload(['cabang_pop_id' => $otherCabang->id]))
            ->assertForbidden();

        $this->assertEquals(0, DeviceRetrievalLog::count());
    }

    #[Test]
    public function halaman_riwayat_tidak_bisa_dibuka_tanpa_izin_gudang(): void
    {
        $this->actingAs($this->teknisi)->get(route('warehouse.retrievals.index'))->assertForbidden();
    }

    #[Test]
    public function nama_pelanggan_tampil_di_halaman_terima_retur(): void
    {
        $serial = $this->makeInstalledSerial('HS-SN-102');
        $this->deacPickup('HS-SN-102');

        // Regresi: kolom nama pelanggan adalah `full_name`; `->name` menghasilkan kosong.
        $this->actingAs($this->owner)->get(route('warehouse.returns.index'))->assertSee('Siti Aminah');
        $this->actingAs($this->owner)->get(route('warehouse.returns.receive.create', $serial))->assertSee('Siti Aminah');
    }

    // ── Detail Pelanggan + Langganan Lagi ───────────────────────────────

    #[Test]
    public function detail_pelanggan_menampilkan_riwayat_pengambilan_alat(): void
    {
        $this->makeInstalledSerial('HS-SN-200');
        $this->deacPickup('HS-SN-200');

        $this->actingAs($this->owner)
            ->get(route('customers.show', $this->customer))
            ->assertOk()
            ->assertSee('Riwayat Pengambilan Alat')
            ->assertSee('HS-SN-200')
            ->assertSee('Teknisi Budi');
    }

    #[Test]
    public function langganan_lagi_mereset_flag_alat_diambil_tetapi_riwayat_tetap_utuh(): void
    {
        $this->makeInstalledSerial('HS-SN-201');
        $this->deacPickup('HS-SN-201');
        $this->assertNotNull($this->customer->customerDevice->refresh()->device_retrieved_at);

        $this->actingAs($this->owner)
            ->post(route('customers.reactivate', $this->customer))
            ->assertSessionHas('success');

        $this->assertNull($this->customer->customerDevice->refresh()->device_retrieved_at);
        $this->assertEquals(1, DeviceRetrievalLog::where('customer_id', $this->customer->id)->count());

        // Riwayat masih tampil di Detail Pelanggan setelah reaktivasi.
        $this->actingAs($this->owner)
            ->get(route('customers.show', $this->customer))
            ->assertSee('HS-SN-201');
    }

    // ── Terima modem dari pelanggan (tanpa task) ────────────────────────

    #[Test]
    public function pencarian_hanya_menampilkan_pelanggan_yang_sudah_putus(): void
    {
        Customer::factory()->create(['pop_id' => $this->miniPop->id, 'status' => 'active', 'full_name' => 'Siti Masih Aktif']);

        $this->actingAs($this->owner)
            ->get(route('warehouse.returns.from-customer.create', ['q' => 'Siti']))
            ->assertOk()
            ->assertSee('Siti Aminah')
            ->assertDontSee('Siti Masih Aktif');
    }

    #[Test]
    public function modem_diantar_pelanggan_langsung_masuk_stok_gudang_dengan_riwayat_dan_flag_alat(): void
    {
        $serial = $this->makeInstalledSerial('WI-SN-001');

        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.from-customer.store'), $this->walkInPayload(['estimated_value' => '200.000', 'notes' => 'Diantar sendiri']))
            ->assertRedirect(route('warehouse.retrievals.index'))
            ->assertSessionHasNoErrors();

        $serial->refresh();
        $this->assertEquals(SerialStatus::AVAILABLE, $serial->status);
        $this->assertEquals($this->cabang->id, $serial->current_pop_id);
        $this->assertNull($serial->customer_id);
        $this->assertEquals(ItemCondition::USED_GOOD, $serial->condition);
        $this->assertTrue($serial->isClearedForIssue());

        $ledger = InventoryTransaction::where('serial_id', $serial->id)->where('type', InventoryTransactionType::RETURN->value)->firstOrFail();
        $this->assertEquals($this->cabang->id, $ledger->to_pop_id);
        $this->assertEquals(200000.0, (float) $ledger->unit_price_snapshot);

        $log = DeviceRetrievalLog::where('serial_id', $serial->id)->firstOrFail();
        $this->assertEquals(DeviceRetrievalSource::WALK_IN, $log->source);
        $this->assertEquals($this->owner->id, $log->retrieved_by);
        $this->assertEquals($this->owner->id, $log->received_by);
        $this->assertTrue($log->isReceived());
        $this->assertNotNull($log->condition_photo);
        Storage::disk('public')->assertExists($log->condition_photo);

        $this->assertNotNull($this->customer->customerDevice->refresh()->device_retrieved_at);
    }

    #[Test]
    public function modem_diantar_yang_belum_pernah_tercatat_didaftarkan_dengan_model_pilihan(): void
    {
        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.from-customer.store'), $this->walkInPayload([
                'serials' => [['serial_number' => 'LEGACY-WI-9', 'item_id' => $this->modemLegacy->id]],
            ]))
            ->assertSessionHasNoErrors();

        $serial = InventorySerial::where('serial_number', 'LEGACY-WI-9')->firstOrFail();
        $this->assertEquals(SerialStatus::AVAILABLE, $serial->status);
        $this->assertEquals($this->modemLegacy->id, $serial->item_id);
        $this->assertStringContainsString('SN pelanggan lama', (string) InventoryTransaction::where('serial_id', $serial->id)->value('notes'));
    }

    #[Test]
    public function modem_diantar_tanpa_model_untuk_sn_baru_ditolak(): void
    {
        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.from-customer.store'), $this->walkInPayload([
                'serials' => [['serial_number' => 'LEGACY-WI-8', 'item_id' => '']],
            ]))
            ->assertSessionHasErrors('serials');

        $this->assertDatabaseMissing('inventory_serials', ['serial_number' => 'LEGACY-WI-8']);
        $this->assertEquals(0, DeviceRetrievalLog::count());
    }

    #[Test]
    public function satu_sn_konflik_membatalkan_seluruh_penerimaan_dan_foto_tidak_tertinggal(): void
    {
        $other = $this->makeTerminatedCustomer('Pelanggan Lain');
        $this->makeInstalledSerial('WI-BAD', $other);

        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.from-customer.store'), $this->walkInPayload([
                'serials' => [
                    ['serial_number' => 'WI-OK-NEW', 'item_id' => $this->modem->id],
                    ['serial_number' => 'WI-BAD', 'item_id' => $this->modem->id],
                ],
            ]))
            ->assertSessionHasErrors('serials');

        $this->assertDatabaseMissing('inventory_serials', ['serial_number' => 'WI-OK-NEW']);
        $this->assertEquals(SerialStatus::INSTALLED, InventorySerial::where('serial_number', 'WI-BAD')->value('status'));
        $this->assertEquals(0, DeviceRetrievalLog::count());
        $this->assertSame([], Storage::disk('public')->allFiles('device-retrieval'));
    }

    #[Test]
    public function modem_diantar_ditolak_kalau_task_ambil_alat_masih_berjalan(): void
    {
        FopTask::create([
            'task_number' => 'TFOP-2026-8801',
            'task_date' => now(),
            'category' => TaskType::AMBIL_MODEM->value,
            'tugas' => 'Ambil Modem',
            'pop_id' => $this->miniPop->id,
            'customer_id' => $this->customer->id,
            'issue' => 'Pengambilan alat pelanggan putus langganan.',
            'status' => TaskStatus::DRAFT->value,
            'priority' => 'Medium',
        ]);

        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.from-customer.store'), $this->walkInPayload())
            ->assertSessionHasErrors('customer_id');

        $this->assertEquals(0, DeviceRetrievalLog::count());
    }

    #[Test]
    public function modem_diantar_ditolak_untuk_pelanggan_yang_belum_putus(): void
    {
        $aktif = Customer::factory()->create(['pop_id' => $this->miniPop->id, 'status' => 'active']);

        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.from-customer.store'), $this->walkInPayload(['customer_id' => $aktif->id]))
            ->assertForbidden();
    }

    #[Test]
    public function modem_diantar_wajib_sn_dan_foto_kondisi(): void
    {
        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.from-customer.store'), $this->walkInPayload(['serials' => [], 'condition_photo' => null]))
            ->assertSessionHasErrors(['serials', 'condition_photo']);
    }

    #[Test]
    public function modem_diantar_tidak_bisa_dicatat_teknisi_tanpa_izin_gudang(): void
    {
        $this->actingAs($this->teknisi)->get(route('warehouse.returns.from-customer.create'))->assertForbidden();
        $this->actingAs($this->teknisi)->post(route('warehouse.returns.from-customer.store'), $this->walkInPayload())->assertForbidden();
    }

    // ── Detail Laporan task DEAC (halaman Task) ─────────────────────────

    #[Test]
    public function detail_task_deac_menampilkan_format_laporan_pengambilan_alat_bukan_format_maintenance(): void
    {
        $serial = $this->makeInstalledSerial('HS-SN-300');

        $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $this->task), [
            'outcome' => DeviceRetrievalOutcome::DIAMBIL->value,
            'serials' => [['serial_number' => 'HS-SN-300', 'item_id' => '']],
            'condition_photo' => UploadedFile::fake()->image('kondisi.jpg'),
            'accessories' => ['adaptor', 'patchcord'],
            'notes' => 'Casing modem lecet',
        ])->assertSessionHasNoErrors();

        $html = $this->actingAs($this->owner)
            ->get(route('tasks.show', $this->task))
            ->assertOk()
            ->assertSee('Laporan Pengambilan Alat')
            ->assertSee('Alat berhasil diambil')
            ->assertSee('HS-SN-300')
            ->assertSee('Modem Riwayat Test')
            ->assertSee('Adaptor / power')
            ->assertSee('Patchcord')
            ->assertSee('Casing modem lecet')
            ->assertSee('Foto Kondisi Alat')
            ->assertSee('Transit — masih dipegang teknisi')
            ->assertSee('Teknisi Budi')
            // Format laporan Maintenance/Survey/Pemasangan TIDAK boleh muncul untuk DEAC.
            ->assertDontSee('Laporan Pekerjaan Teknisi')
            ->assertDontSee('Foto OPM')
            ->assertDontSee('Kendala &amp; Solusi', false)
            // Instruksi sebelum laporan digantikan oleh laporan yang sudah masuk.
            ->assertDontSee('Kelengkapan Standar')
            ->getContent();

        $this->assertStringNotContainsString('Material Terpakai', $html);

        // Setelah gudang menerima, kartu SN berubah dari transit menjadi diterima.
        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.receive.store', $serial), ['condition' => 'used_good'])
            ->assertSessionHas('success');

        $this->actingAs($this->owner)
            ->get(route('tasks.show', $this->task))
            ->assertSee('Sudah diterima gudang')
            ->assertSee('Owner Sari')
            ->assertDontSee('Transit — masih dipegang teknisi');
    }

    #[Test]
    public function detail_task_deac_tanpa_hasil_pengambilan_menampilkan_alasan_bukan_daftar_modem(): void
    {
        $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $this->task), [
            'outcome' => DeviceRetrievalOutcome::DITOLAK->value,
            'notes' => 'Pelanggan tidak mau menyerahkan modem',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->get(route('tasks.show', $this->task))
            ->assertOk()
            ->assertSee('Laporan Pengambilan Alat')
            ->assertSee('Pelanggan menolak / tidak bisa ditemui')
            ->assertSee('Alasan Alat Tidak Diambil')
            ->assertSee('Pelanggan tidak mau menyerahkan modem')
            ->assertDontSee('Modem yang Dibawa')
            ->assertDontSee('Laporan Pekerjaan Teknisi');
    }

    #[Test]
    public function detail_task_deac_sebelum_laporan_masih_menampilkan_instruksi_bukan_laporan(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tasks.show', $this->task))
            ->assertOk()
            ->assertSee('Kelengkapan Standar')
            ->assertDontSee('Laporan Pengambilan Alat')
            ->assertDontSee('Laporan Pekerjaan Teknisi');
    }

    // ── Tab "Return dari Pelanggan" di Barang di Tangan Teknisi ─────────

    #[Test]
    public function tab_return_di_barang_di_tangan_teknisi_berisi_modem_transit_saja(): void
    {
        $this->makeInstalledSerial('HS-SN-301');
        $this->deacPickup('HS-SN-301');

        // Barang yang DIBAWA teknisi (ISSUED) tetap di tab Perangkat Serial Number, bukan di tab return.
        [$issued] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, ['HS-SN-ISSUED'], 250000, $this->owner);
        $issued->update([
            'status' => SerialStatus::ISSUED,
            'current_pop_id' => null,
            'current_technician_id' => $this->teknisi->id,
            'issued_from_pop_id' => $this->cabang->id,
        ]);

        $this->actingAs($this->owner)
            ->get(route('warehouse.custody.index'))
            ->assertOk()
            ->assertSee('Return dari Pelanggan')
            ->assertSee('Terima Retur')
            ->assertSee('Siti Aminah')
            ->assertViewHas('returned', fn ($rows) => $rows->pluck('serial_number')->all() === ['HS-SN-301'])
            ->assertViewHas('serials', fn ($rows) => $rows->pluck('serial_number')->all() === ['HS-SN-ISSUED']);
    }

    #[Test]
    public function tab_return_mengikuti_filter_teknisi_dan_kosong_setelah_gudang_menerima(): void
    {
        $serial = $this->makeInstalledSerial('HS-SN-302');
        $this->deacPickup('HS-SN-302');

        $this->actingAs($this->owner)
            ->get(route('warehouse.custody.index', ['technician_id' => $this->teknisi->id]))
            ->assertViewHas('returned', fn ($rows) => $rows->count() === 1);

        $this->actingAs($this->owner)
            ->get(route('warehouse.custody.index', ['technician_id' => $this->owner->id]))
            ->assertViewHas('returned', fn ($rows) => $rows->isEmpty());

        $this->actingAs($this->owner)
            ->get(route('warehouse.custody.index', ['search' => 'Aminah']))
            ->assertViewHas('returned', fn ($rows) => $rows->count() === 1);

        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.receive.store', $serial), ['condition' => 'used_good'])
            ->assertSessionHas('success');

        $this->actingAs($this->owner)
            ->get(route('warehouse.custody.index'))
            ->assertViewHas('returned', fn ($rows) => $rows->isEmpty())
            ->assertViewHas('serials', fn ($rows) => $rows->isEmpty());
    }

    // ── Riwayat Task FOP (/fop-tasks/history/{id}) ──────────────────────

    /**
     * FopTask yang tertaut ke `$this->task` — Riwayat Task FOP membaca laporan
     * lewat `fop_tasks.task_id`.
     */
    private function makeLinkedFopTask(): FopTask
    {
        return FopTask::create([
            'task_number' => 'TFOP-2026-'.random_int(1000, 9999),
            'task_id' => $this->task->id,
            'task_date' => now(),
            'category' => TaskType::AMBIL_MODEM->value,
            'tugas' => 'Ambil Modem Siti Aminah',
            'pop_id' => $this->miniPop->id,
            'customer_id' => $this->customer->id,
            'issue' => 'Pengambilan alat pelanggan putus langganan.',
            'status' => TaskStatus::SELESAI->value,
            'priority' => 'Medium',
        ]);
    }

    #[Test]
    public function riwayat_task_fop_ambil_modem_menampilkan_laporan_dan_foto_terlampir(): void
    {
        $fopTask = $this->makeLinkedFopTask();
        $this->makeInstalledSerial('HS-SN-400');

        $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $this->task), [
            'outcome' => DeviceRetrievalOutcome::DIAMBIL->value,
            'serials' => [['serial_number' => 'HS-SN-400', 'item_id' => '']],
            'condition_photo' => UploadedFile::fake()->image('kondisi.jpg'),
            'accessories' => ['adaptor', 'kabel_lan'],
            'notes' => 'Kabel LAN ikut dibawa',
        ])->assertSessionHasNoErrors();

        $photoPath = $this->task->refresh()->deviceRetrieval->condition_photo;
        Storage::disk('public')->assertExists($photoPath);

        $this->actingAs($this->owner)
            ->get(route('fop-tasks.history.show', $fopTask->id))
            ->assertOk()
            ->assertSee('Hasil di Lapangan')
            ->assertSee('Alat berhasil diambil')
            ->assertSee('Teknisi Budi')
            ->assertSee('HS-SN-400')
            ->assertSee('Modem Riwayat Test')
            ->assertSee('Transit — di teknisi')
            ->assertSee('Adaptor / power')
            ->assertSee('Kabel LAN')
            ->assertSee('Kabel LAN ikut dibawa')
            // Foto kondisi terlampir: thumbnail + tautan ke file di disk public.
            ->assertSee('Foto Kondisi Alat')
            ->assertSee('<img src="'.asset('storage/'.$photoPath).'"', false)
            // Bukan lagi fallback "tidak punya laporan" maupun format Maintenance.
            ->assertDontSee('Tipe task ini tidak punya laporan lapangan terstruktur')
            ->assertDontSee('Foto OPM')
            ->assertDontSee('Kendala Teknis');

        // Setelah gudang menerima, baris SN di riwayat berubah jadi "Diterima gudang".
        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.receive.store', InventorySerial::where('serial_number', 'HS-SN-400')->firstOrFail()), ['condition' => 'used_good'])
            ->assertSessionHas('success');

        $this->actingAs($this->owner)
            ->get(route('fop-tasks.history.show', $fopTask->id))
            ->assertSee('Diterima gudang')
            ->assertSee('Owner Sari')
            ->assertDontSee('Transit — di teknisi');
    }

    #[Test]
    public function riwayat_task_fop_ambil_modem_tanpa_hasil_menampilkan_alasan_tanpa_daftar_modem(): void
    {
        $fopTask = $this->makeLinkedFopTask();

        $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $this->task), [
            'outcome' => DeviceRetrievalOutcome::TIDAK_DITEMUKAN->value,
            'notes' => 'Rumah kosong, pelanggan sudah pindah',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->get(route('fop-tasks.history.show', $fopTask->id))
            ->assertOk()
            ->assertSee('Alat tidak ditemukan')
            ->assertSee('Alasan Alat Tidak Diambil')
            ->assertSee('Rumah kosong, pelanggan sudah pindah')
            ->assertDontSee('Modem yang Dibawa')
            ->assertDontSee('Tipe task ini tidak punya laporan lapangan terstruktur');
    }

    #[Test]
    public function riwayat_task_fop_ambil_modem_sebelum_ada_laporan_menyatakan_belum_ada_laporan(): void
    {
        $fopTask = $this->makeLinkedFopTask();

        $this->actingAs($this->owner)
            ->get(route('fop-tasks.history.show', $fopTask->id))
            ->assertOk()
            ->assertSee('Belum ada laporan pengambilan alat.')
            ->assertDontSee('Tipe task ini tidak punya laporan lapangan terstruktur');
    }

    #[Test]
    public function riwayat_task_fop_menyebut_foto_hilang_bukan_gambar_rusak_kalau_file_tidak_ada(): void
    {
        $fopTask = $this->makeLinkedFopTask();
        $this->makeInstalledSerial('HS-SN-401');
        $this->deacPickup('HS-SN-401');

        Storage::disk('public')->delete($this->task->refresh()->deviceRetrieval->condition_photo);

        $this->actingAs($this->owner)
            ->get(route('fop-tasks.history.show', $fopTask->id))
            ->assertOk()
            ->assertSee('filenya tidak ditemukan di penyimpanan')
            ->assertDontSee('<img src', false);
    }

    // ── Petunjuk merek dari data lama ───────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function labelMerek(): array
    {
        return [
            'label jelas F609' => ['ZTE F609', 'HS-ONT-F609'],
            'huruf kecil F660' => ['ZTE f660', 'HS-ONT-F660'],
            'generik router GPON' => ['router GPON', null],
            'terlalu pendek ZTE' => ['ZTE', null],
            'sampah angka 1' => ['1', null],
            'ambigu F6' => ['ZTE F6', null],
        ];
    }

    #[Test]
    #[DataProvider('labelMerek')]
    public function label_merek_data_lama_dipetakan_ke_model_hanya_kalau_pasti(string $label, ?string $expectedCode): void
    {
        $cat = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        Item::create(['code' => 'HS-ONT-F609', 'name' => 'Modem ONT ZTE F609', 'item_category_id' => $cat->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);
        Item::create(['code' => 'HS-ONT-F660', 'name' => 'Modem ONT ZTE F660', 'item_category_id' => $cat->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);

        CustomerTechnicalDetail::create([
            'customer_id' => $this->customer->id,
            'router_or_ont_serial' => 'ZTEGLEGACY01',
            'note' => "Perangkat: {$label} (dari data aset migrasi)",
        ]);

        $hint = app(LegacyDeviceHintService::class)->forCustomer($this->customer->refresh());

        $this->assertSame('ZTEGLEGACY01', $hint['serial']);
        $this->assertSame($label, $hint['label']);
        $this->assertSame($expectedCode, $hint['item']?->code);
    }

    #[Test]
    public function tanpa_catatan_merek_petunjuk_hanya_berisi_sn(): void
    {
        CustomerTechnicalDetail::create(['customer_id' => $this->customer->id, 'router_or_ont_serial' => 'ZTEGONLY01', 'note' => 'catatan biasa']);

        $hint = app(LegacyDeviceHintService::class)->forCustomer($this->customer->refresh());

        $this->assertSame('ZTEGONLY01', $hint['serial']);
        $this->assertNull($hint['label']);
        $this->assertNull($hint['item']);
    }

    #[Test]
    public function form_ambil_alat_menampilkan_merek_dari_data_lama_sebagai_petunjuk(): void
    {
        CustomerTechnicalDetail::create([
            'customer_id' => $this->customer->id,
            'router_or_ont_serial' => 'ZTEGFORM01',
            'note' => 'Perangkat: ZTE F609 (dari data aset migrasi)',
        ]);

        $this->actingAs($this->teknisi)
            ->get(route('tasks.device-retrieval.report', $this->task))
            ->assertOk()
            ->assertSee('ZTEGFORM01')
            ->assertSee('ZTE F609');
    }
}
