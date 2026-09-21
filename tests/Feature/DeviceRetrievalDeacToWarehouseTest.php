<?php

namespace Tests\Feature;

use App\Enums\DeviceRetrievalOutcome;
use App\Enums\InventoryTransactionType;
use App\Enums\ItemCondition;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskDeviceRetrieval;
use App\Models\TaskTeam;
use App\Models\User;
use App\Services\InventoryIssueService;
use App\Services\InventoryReceiveService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Database\Seeders\WorkflowTransitionPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-86 — Ambil Modem (DEAC) → Gudang: form laporan khusus (SN fisik
 * dari teknisi), transit `RETURNED`, konfirmasi gudang ("Terima Retur"),
 * dan SN legacy yang tidak pernah tercatat di `inventory_serials`.
 * Rancangan: docs/plan/warehouse/analisa-ambil-modem-deac-ke-gudang.md.
 */
class DeviceRetrievalDeacToWarehouseTest extends TestCase
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

        $this->teknisi = User::factory()->create(['role_id' => $teknisiRole->id]);
        $this->teknisi->roleScopes()->create(['role_id' => $teknisiRole->id, 'scope_type' => ScopeType::ALL_POP->value]);

        $this->owner = User::factory()->create(['role_id' => $ownerRole->id]);
        $this->owner->roleScopes()->create(['role_id' => $ownerRole->id, 'scope_type' => ScopeType::ALL_POP->value]);

        $this->pusat = Pop::create(['code' => 'DR-PUSAT', 'pop_code' => 'DRP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Retur Test', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'DR-CABANG', 'pop_code' => 'DRC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Retur Test', 'type' => 'cabang', 'status' => 'active']);
        $this->miniPop = Pop::create(['code' => 'DR-MINI', 'pop_code' => 'DRM', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Mini POP Retur Test', 'type' => 'mini_pop', 'status' => 'active', 'parent_id' => $this->cabang->id]);

        $cat = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $this->modem = Item::create(['code' => 'DR-MODEM', 'name' => 'Modem Retur Test', 'item_category_id' => $cat->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);
        $this->modemLegacy = Item::create(['code' => 'MODEM-LEGACY', 'name' => 'Modem Legacy (Belum Teridentifikasi)', 'item_category_id' => $cat->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);

        // Task DEAC ber-POP mini_pop (kasus nyata) — gudang tujuan harus
        // ditelusuri naik ke cabang induknya.
        $this->customer = Customer::factory()->create(['pop_id' => $this->miniPop->id, 'status' => 'terminated']);
        CustomerDevice::create(['customer_id' => $this->customer->id, 'device_type' => 'ONT', 'brand' => 'ZTE', 'model' => 'F609']);

        $this->task = $this->makeDeacTask($this->customer);
    }

    private function makeDeacTask(Customer $customer, TaskStatus $status = TaskStatus::IN_PROGRESS): Task
    {
        $task = Task::create([
            'task_number' => 'TASK-2026-'.random_int(1000, 9999),
            'task_type' => TaskType::AMBIL_MODEM->value,
            'title' => 'Ambil Modem '.$customer->id,
            'pop_id' => $this->miniPop->id,
            'customer_id' => $customer->id,
            'status' => $status->value,
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submitReport(array $overrides = [], ?Task $task = null)
    {
        $payload = array_merge([
            'outcome' => DeviceRetrievalOutcome::DIAMBIL->value,
            'serials' => [['serial_number' => 'SN-DEFAULT', 'item_id' => $this->modem->id]],
            'condition_photo' => UploadedFile::fake()->image('kondisi.jpg'),
            'accessories' => ['adaptor'],
            'notes' => null,
        ], $overrides);

        return $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $task ?? $this->task), $payload);
    }

    #[Test]
    public function sn_terdaftar_masuk_transit_lalu_diterima_gudang_cabang(): void
    {
        $serial = $this->makeInstalledSerial('DR-SN-001');

        $this->submitReport(['serials' => [['serial_number' => 'DR-SN-001', 'item_id' => '']]])
            ->assertRedirect(route('tasks.show', $this->task))
            ->assertSessionHasNoErrors();

        // Transit: dipegang teknisi, BELUM stok gudang.
        $serial->refresh();
        $this->assertEquals(SerialStatus::RETURNED, $serial->status);
        $this->assertEquals($this->teknisi->id, $serial->current_technician_id);
        $this->assertNull($serial->current_pop_id);
        $this->assertFalse($serial->isClearedForIssue());
        $this->assertEquals(TaskStatus::SELESAI, $this->task->refresh()->status);
        $this->assertNotNull($this->customer->customerDevice->refresh()->device_retrieved_at);
        $this->assertEquals(DeviceRetrievalOutcome::DIAMBIL, $this->task->deviceRetrieval->outcome);
        $this->assertEquals(['adaptor'], $this->task->deviceRetrieval->accessories);

        // Gudang cabang menerima.
        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.receive.store', $serial), ['condition' => 'used_good'])
            ->assertRedirect(route('warehouse.returns.index'))
            ->assertSessionHas('success');

        $serial->refresh();
        $this->assertEquals(SerialStatus::AVAILABLE, $serial->status);
        $this->assertEquals($this->cabang->id, $serial->current_pop_id);
        $this->assertNull($serial->current_technician_id);
        $this->assertNull($serial->customer_id);
        $this->assertEquals(ItemCondition::USED_GOOD, $serial->condition);
        $this->assertNotNull($serial->condition_checked_at);
        $this->assertTrue($serial->isClearedForIssue());

        // Dua baris RETURN: pelanggan→teknisi (tanpa to_pop_id, jadi tidak
        // ikut dihitung stok) lalu teknisi→gudang (to_pop_id = cabang).
        $ledger = InventoryTransaction::where('serial_id', $serial->id)->where('type', InventoryTransactionType::RETURN->value)->orderBy('id')->get();
        $this->assertCount(2, $ledger);
        $this->assertNull($ledger[0]->to_pop_id);
        $this->assertEquals($this->teknisi->id, $ledger[0]->to_technician_id);
        $this->assertEquals($this->cabang->id, $ledger[1]->to_pop_id);
    }

    #[Test]
    public function sn_legacy_yang_belum_pernah_tercatat_didaftarkan_otomatis_dan_bisa_dikoreksi_gudang(): void
    {
        $this->assertDatabaseMissing('inventory_serials', ['serial_number' => 'LEGACY-777']);

        $this->submitReport(['serials' => [['serial_number' => 'LEGACY-777', 'item_id' => $this->modemLegacy->id]]])
            ->assertSessionHasNoErrors();

        $serial = InventorySerial::where('serial_number', 'LEGACY-777')->firstOrFail();
        $this->assertEquals(SerialStatus::RETURNED, $serial->status);
        $this->assertEquals($this->modemLegacy->id, $serial->item_id);
        $this->assertEquals($this->customer->id, $serial->customer_id);
        // Task ber-POP mini_pop → gudang tujuan = cabang induknya.
        $this->assertEquals($this->cabang->id, $serial->issued_from_pop_id);
        $this->assertStringContainsString('SN legacy', InventoryTransaction::where('serial_id', $serial->id)->value('notes'));
        $this->assertNotNull($this->customer->customerDevice->refresh()->device_retrieved_at);

        // Staf gudang mengoreksi model saat menerima.
        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.receive.store', $serial), ['condition' => 'used_damaged', 'item_id' => $this->modem->id])
            ->assertSessionHas('success');

        $serial->refresh();
        $this->assertEquals($this->modem->id, $serial->item_id);
        $this->assertEquals(ItemCondition::USED_DAMAGED, $serial->condition);
        $this->assertEquals(SerialStatus::AVAILABLE, $serial->status);
    }

    #[Test]
    public function sn_legacy_tanpa_model_ditolak_dan_task_tidak_setengah_selesai(): void
    {
        $this->submitReport(['serials' => [['serial_number' => 'LEGACY-888', 'item_id' => '']]])
            ->assertSessionHasErrors('serials');

        $this->assertDatabaseMissing('inventory_serials', ['serial_number' => 'LEGACY-888']);
        $this->assertEquals(TaskStatus::IN_PROGRESS, $this->task->refresh()->status);
        $this->assertNull($this->task->deviceRetrieval);
        $this->assertNull($this->customer->customerDevice->refresh()->device_retrieved_at);
    }

    #[Test]
    public function sn_milik_pelanggan_lain_ditolak_tanpa_menimpa_data(): void
    {
        $other = Customer::factory()->create(['pop_id' => $this->miniPop->id]);
        $serial = $this->makeInstalledSerial('DR-SN-OTHER', $other);

        $this->submitReport(['serials' => [['serial_number' => 'DR-SN-OTHER', 'item_id' => $this->modem->id]]])
            ->assertSessionHasErrors('serials');

        $serial->refresh();
        $this->assertEquals(SerialStatus::INSTALLED, $serial->status);
        $this->assertEquals($other->id, $serial->customer_id);
        $this->assertEquals(TaskStatus::IN_PROGRESS, $this->task->refresh()->status);
        $this->assertNull($this->task->deviceRetrieval);
    }

    #[Test]
    public function satu_sn_gagal_membatalkan_seluruh_laporan_termasuk_sn_yang_valid(): void
    {
        $ok = $this->makeInstalledSerial('DR-SN-OK');
        $other = Customer::factory()->create(['pop_id' => $this->miniPop->id]);
        $this->makeInstalledSerial('DR-SN-BAD', $other);

        $this->submitReport(['serials' => [
            ['serial_number' => 'DR-SN-OK', 'item_id' => ''],
            ['serial_number' => 'DR-SN-BAD', 'item_id' => ''],
        ]])->assertSessionHasErrors('serials');

        $this->assertEquals(SerialStatus::INSTALLED, $ok->refresh()->status);
        $this->assertEquals(0, InventoryTransaction::where('serial_id', $ok->id)->where('type', InventoryTransactionType::RETURN->value)->count());
    }

    #[Test]
    public function alat_tidak_ditemukan_selesai_tanpa_menandai_alat_diambil(): void
    {
        $serial = $this->makeInstalledSerial('DR-SN-STAY');

        $this->submitReport([
            'outcome' => DeviceRetrievalOutcome::TIDAK_DITEMUKAN->value,
            'serials' => [],
            'condition_photo' => null,
            'notes' => 'Pelanggan sudah pindah, alat tidak ada di rumah.',
        ])->assertSessionHasNoErrors();

        $this->assertEquals(TaskStatus::SELESAI, $this->task->refresh()->status);
        $this->assertEquals(DeviceRetrievalOutcome::TIDAK_DITEMUKAN, $this->task->deviceRetrieval->outcome);
        // Badge "Sudah Diambil" tidak boleh berbohong → tetap kosong, tombol Ambil Alat muncul lagi.
        $this->assertNull($this->customer->customerDevice->refresh()->device_retrieved_at);
        $this->assertEquals(SerialStatus::INSTALLED, $serial->refresh()->status);
    }

    #[Test]
    public function alat_tidak_diambil_wajib_beralasan(): void
    {
        $this->submitReport([
            'outcome' => DeviceRetrievalOutcome::DITOLAK->value,
            'serials' => [],
            'condition_photo' => null,
            'notes' => '',
        ])->assertSessionHasErrors('notes');

        $this->assertEquals(TaskStatus::IN_PROGRESS, $this->task->refresh()->status);
    }

    #[Test]
    public function alat_diambil_wajib_sn_dan_foto_kondisi(): void
    {
        $this->submitReport(['serials' => [], 'condition_photo' => null])
            ->assertSessionHasErrors(['serials', 'condition_photo']);
    }

    #[Test]
    public function sn_dobel_dalam_satu_laporan_ditolak(): void
    {
        $this->makeInstalledSerial('DR-SN-DUP');

        $this->submitReport(['serials' => [
            ['serial_number' => 'DR-SN-DUP', 'item_id' => ''],
            ['serial_number' => 'dr-sn-dup', 'item_id' => ''],
        ]])->assertSessionHasErrors('serials');
    }

    #[Test]
    public function task_deac_tidak_bisa_diselesaikan_lewat_endpoint_complete_tanpa_laporan(): void
    {
        $this->actingAs($this->teknisi)
            ->post(route('tasks.complete', $this->task))
            ->assertStatus(422);

        $this->assertEquals(TaskStatus::IN_PROGRESS, $this->task->refresh()->status);
        $this->assertNull($this->customer->customerDevice->refresh()->device_retrieved_at);
    }

    #[Test]
    public function form_maintenance_mengalihkan_task_deac_ke_form_khusus(): void
    {
        $this->actingAs($this->teknisi)
            ->get(route('tasks.maintenance.report', $this->task))
            ->assertRedirect(route('tasks.device-retrieval.report', $this->task));

        $this->actingAs($this->teknisi)
            ->post(route('tasks.maintenance.store', $this->task), [])
            ->assertRedirect(route('tasks.device-retrieval.report', $this->task));
    }

    #[Test]
    public function form_laporan_ambil_alat_menampilkan_sn_terpasang_sebagai_petunjuk(): void
    {
        $this->makeInstalledSerial('DR-SN-HINT');

        $this->actingAs($this->teknisi)
            ->get(route('tasks.device-retrieval.report', $this->task))
            ->assertOk()
            ->assertSee('DR-SN-HINT')
            ->assertDontSee('opm_photo');
    }

    #[Test]
    public function reject_fop_mencabut_tanda_alat_diambil_dan_kirim_ulang_laporan_idempoten(): void
    {
        $serial = $this->makeInstalledSerial('DR-SN-REJ');

        $this->submitReport(['serials' => [['serial_number' => 'DR-SN-REJ', 'item_id' => '']]])->assertSessionHasNoErrors();
        $this->assertNotNull($this->customer->customerDevice->refresh()->device_retrieved_at);

        $this->actingAs($this->owner)
            ->post(route('tasks.review', $this->task), ['action' => 'reject', 'reason' => 'Foto kurang jelas'])
            ->assertSessionHas('success');

        $this->assertEquals(TaskStatus::IN_PROGRESS, $this->task->refresh()->status);
        $this->assertNull($this->customer->customerDevice->refresh()->device_retrieved_at);
        // Modem memang ada di tangan teknisi → tidak dibalik.
        $this->assertEquals(SerialStatus::RETURNED, $serial->refresh()->status);

        // Teknisi kirim ulang laporan yang sama: sukses, tanpa ledger dobel.
        $this->submitReport(['serials' => [['serial_number' => 'DR-SN-REJ', 'item_id' => '']]])
            ->assertSessionHasNoErrors();

        $this->assertEquals(TaskStatus::SELESAI, $this->task->refresh()->status);
        $this->assertNotNull($this->customer->customerDevice->refresh()->device_retrieved_at);
        $this->assertEquals(1, InventoryTransaction::where('serial_id', $serial->id)->where('type', InventoryTransactionType::RETURN->value)->count());
        $this->assertEquals(1, TaskDeviceRetrieval::where('task_id', $this->task->id)->count());
    }

    #[Test]
    public function sn_transit_tidak_bisa_diissue_sebelum_diterima_gudang(): void
    {
        $serial = $this->makeInstalledSerial('DR-SN-TRANSIT');
        $this->submitReport(['serials' => [['serial_number' => 'DR-SN-TRANSIT', 'item_id' => '']]])->assertSessionHasNoErrors();

        $this->expectException(InvalidArgumentException::class);

        app(InventoryIssueService::class)->issue($this->cabang, User::factory()->create(), [
            ['item_id' => $this->modem->id, 'serial_numbers' => ['DR-SN-TRANSIT']],
        ], $this->owner);
    }

    #[Test]
    public function terima_retur_dua_kali_ditolak(): void
    {
        $serial = $this->makeInstalledSerial('DR-SN-TWICE');
        $this->submitReport(['serials' => [['serial_number' => 'DR-SN-TWICE', 'item_id' => '']]])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->post(route('warehouse.returns.receive.store', $serial), ['condition' => 'used_good'])->assertSessionHas('success');
        $this->actingAs($this->owner)->post(route('warehouse.returns.receive.store', $serial), ['condition' => 'used_good'])->assertSessionHas('error');

        $this->assertEquals(2, InventoryTransaction::where('serial_id', $serial->id)->where('type', InventoryTransactionType::RETURN->value)->count());
    }

    #[Test]
    public function terima_retur_menolak_kondisi_baru(): void
    {
        $serial = $this->makeInstalledSerial('DR-SN-NEW');
        $this->submitReport(['serials' => [['serial_number' => 'DR-SN-NEW', 'item_id' => '']]])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.receive.store', $serial), ['condition' => 'new'])
            ->assertSessionHasErrors('condition');

        $this->assertEquals(SerialStatus::RETURNED, $serial->refresh()->status);
    }

    #[Test]
    public function halaman_terima_retur_hanya_untuk_yang_punya_izin_gudang(): void
    {
        $this->actingAs($this->teknisi)->get(route('warehouse.returns.index'))->assertForbidden();

        $serial = $this->makeInstalledSerial('DR-SN-LIST');
        $this->submitReport(['serials' => [['serial_number' => 'DR-SN-LIST', 'item_id' => '']]])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->get(route('warehouse.returns.index'))
            ->assertOk()
            ->assertSee('DR-SN-LIST');

        $this->actingAs($this->owner)
            ->get(route('warehouse.returns.receive.create', $serial))
            ->assertOk()
            ->assertSee('Terima ke Gudang');
    }
}
