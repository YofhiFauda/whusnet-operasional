<?php

namespace Tests\Feature;

use App\Enums\DeviceRetrievalOutcome;
use App\Enums\InventoryTransactionType;
use App\Enums\ItemCondition;
use App\Enums\ScopeType;
use App\Enums\SerialStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TransferStatus;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransfer;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskTeam;
use App\Models\User;
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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TAHAP 2 & 3 (ADHOC-108) — Cabang kirim retur ke Pusat, Pusat konfirmasi
 * final. Menyusul Tahap 1 (`DeviceRetrievalDeacToWarehouseTest`). Rancangan:
 * docs/plan/warehouse/rancangan-retur-ke-pusat-dan-modem-rusak.md.
 */
class WarehouseReturnToPusatTest extends TestCase
{
    use RefreshDatabase;

    private User $teknisi;

    private User $owner;

    private Pop $pusat;

    private Pop $cabang;

    private Pop $miniPop;

    private Item $modem;

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

        $this->pusat = Pop::create(['code' => 'RTP-PUSAT', 'pop_code' => 'RTP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Retur Pusat Test', 'type' => 'pusat', 'status' => 'active']);
        $this->cabang = Pop::create(['code' => 'RTP-CABANG', 'pop_code' => 'RTC', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Retur Pusat Test', 'type' => 'cabang', 'status' => 'active']);
        $this->miniPop = Pop::create(['code' => 'RTP-MINI', 'pop_code' => 'RTM', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Mini POP Retur Pusat Test', 'type' => 'mini_pop', 'status' => 'active', 'parent_id' => $this->cabang->id]);

        $cat = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $this->modem = Item::create(['code' => 'RTP-MODEM', 'name' => 'Modem Retur Pusat Test', 'item_category_id' => $cat->id, 'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable']);

        $this->customer = Customer::factory()->create(['pop_id' => $this->miniPop->id, 'status' => 'terminated']);
        CustomerDevice::create(['customer_id' => $this->customer->id, 'device_type' => 'ONT', 'brand' => 'ZTE', 'model' => 'F609']);

        $this->task = Task::create([
            'task_number' => 'TASK-2026-'.random_int(1000, 9999),
            'task_type' => TaskType::AMBIL_MODEM->value,
            'title' => 'Ambil Modem '.$this->customer->id,
            'pop_id' => $this->miniPop->id,
            'customer_id' => $this->customer->id,
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $this->owner->id,
            'updated_by' => $this->owner->id,
        ]);
        TaskTeam::create(['task_id' => $this->task->id, 'user_id' => $this->teknisi->id, 'role_in_task' => 'lead']);
    }

    /**
     * SN `INSTALLED`, lapor DEAC, Cabang terima dari teknisi (Tahap 1) —
     * mendarat di state `RETURNED`, `current_pop_id` = Cabang, siap Tahap 2.
     */
    private function makeSerialAtCabangPendingDispatch(string $sn): InventorySerial
    {
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $this->modem, [$sn], 250000, $this->owner);
        $serial->update([
            'status' => SerialStatus::INSTALLED,
            'issued_from_pop_id' => $this->cabang->id,
            'current_pop_id' => null,
            'customer_id' => $this->customer->id,
        ]);

        $this->actingAs($this->teknisi)->post(route('tasks.device-retrieval.store', $this->task), [
            'outcome' => DeviceRetrievalOutcome::DIAMBIL->value,
            'serials' => [['serial_number' => $sn, 'item_id' => '']],
            'condition_photo' => UploadedFile::fake()->image('kondisi.jpg'),
            'accessories' => [],
            'notes' => null,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.receive.store', $serial->refresh()), ['condition' => 'used_good'])
            ->assertSessionHas('success');

        return $serial->refresh();
    }

    #[Test]
    public function cabang_kirim_retur_lalu_pusat_konfirmasi_jadi_available_dan_stok_pusat_bertambah(): void
    {
        $serial = $this->makeSerialAtCabangPendingDispatch('RTP-SN-001');
        $this->assertEquals(SerialStatus::RETURNED, $serial->status);

        // TAHAP 2 — Cabang kirim ke Pusat.
        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.dispatch.store'), [
                'serial_ids' => [$serial->id],
                'pusat_id' => $this->pusat->id,
            ])
            ->assertRedirect(route('warehouse.returns.dispatch.index'))
            ->assertSessionHas('success');

        $serial->refresh();
        $this->assertEquals(SerialStatus::TRANSFERRED, $serial->status);
        $this->assertNull($serial->current_pop_id);

        $transfer = InventoryTransfer::where('from_pop_id', $this->cabang->id)->where('to_pop_id', $this->pusat->id)->firstOrFail();
        $this->assertEquals(TransferStatus::IN_TRANSIT, $transfer->status);

        $dispatchLine = InventoryTransaction::where('serial_id', $serial->id)->where('type', InventoryTransactionType::TRANSFER->value)->firstOrFail();
        $this->assertEquals($this->cabang->id, $dispatchLine->from_pop_id);
        $this->assertNull($dispatchLine->to_pop_id);

        // TAHAP 3 — Pusat konfirmasi terima.
        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.pusat.store', $serial), ['condition' => 'used_good'])
            ->assertRedirect(route('warehouse.returns.pusat.index'))
            ->assertSessionHas('success');

        $serial->refresh();
        $this->assertEquals(SerialStatus::AVAILABLE, $serial->status);
        $this->assertEquals($this->pusat->id, $serial->current_pop_id);
        $this->assertEquals(ItemCondition::USED_GOOD, $serial->condition);
        $this->assertNotNull($serial->condition_checked_at);
        $this->assertTrue($serial->isClearedForIssue());

        $confirmLine = InventoryTransaction::where('serial_id', $serial->id)->where('type', InventoryTransactionType::TRANSFER->value)->where('to_pop_id', $this->pusat->id)->firstOrFail();
        $this->assertEquals($this->pusat->id, $confirmLine->to_pop_id);

        $this->assertEquals(TransferStatus::RECEIVED, $transfer->refresh()->status);
    }

    #[Test]
    public function sn_di_tengah_pengiriman_tidak_bisa_diissue(): void
    {
        $serial = $this->makeSerialAtCabangPendingDispatch('RTP-SN-TRANSIT');

        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.dispatch.store'), [
                'serial_ids' => [$serial->id],
                'pusat_id' => $this->pusat->id,
            ])->assertSessionHas('success');

        $this->expectException(\InvalidArgumentException::class);

        app(\App\Services\InventoryIssueService::class)->issue($this->pusat, User::factory()->create(), [
            ['item_id' => $this->modem->id, 'serial_numbers' => ['RTP-SN-TRANSIT']],
        ], $this->owner);
    }

    #[Test]
    public function dispatch_menolak_sn_yang_tidak_valid(): void
    {
        $serial = $this->makeSerialAtCabangPendingDispatch('RTP-SN-WRONG');

        $this->actingAs($this->owner)
            ->post(route('warehouse.returns.dispatch.store'), [
                'serial_ids' => [$serial->id, 999999],
                'pusat_id' => $this->pusat->id,
            ])
            ->assertSessionHasErrors('serial_ids.1');

        $this->assertEquals(SerialStatus::RETURNED, $serial->refresh()->status);
    }

    #[Test]
    public function pusat_konfirmasi_dua_kali_ditolak(): void
    {
        $serial = $this->makeSerialAtCabangPendingDispatch('RTP-SN-TWICE');

        $this->actingAs($this->owner)->post(route('warehouse.returns.dispatch.store'), [
            'serial_ids' => [$serial->id],
            'pusat_id' => $this->pusat->id,
        ])->assertSessionHas('success');

        $this->actingAs($this->owner)->post(route('warehouse.returns.pusat.store', $serial), ['condition' => 'used_good'])->assertSessionHas('success');
        $this->actingAs($this->owner)->post(route('warehouse.returns.pusat.store', $serial), ['condition' => 'used_good'])->assertSessionHas('error');
    }

    #[Test]
    public function halaman_kirim_ke_pusat_dan_terima_di_pusat_butuh_izin_gudang(): void
    {
        $serial = $this->makeSerialAtCabangPendingDispatch('RTP-SN-PERM');

        $this->actingAs($this->teknisi)->get(route('warehouse.returns.dispatch.index'))->assertForbidden();
        $this->actingAs($this->teknisi)->get(route('warehouse.returns.pusat.index'))->assertForbidden();

        $this->actingAs($this->owner)
            ->get(route('warehouse.returns.dispatch.index'))
            ->assertOk()
            ->assertSee('RTP-SN-PERM');
    }
}
