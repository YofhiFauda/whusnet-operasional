<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\MaterialKind;
use App\Enums\MaterialType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Item;
use App\Models\Pop;
use App\Models\Village;
use App\Services\TaskMaterialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penulisan baris material task: normalisasi baris, snapshot dari master,
 * pemisahan fase estimasi/terpakai, dan perhitungan selisih.
 */
class TaskMaterialTest extends TestCase
{
    use RefreshDatabase;

    private TaskMaterialService $service;

    private Customer $customer;

    private FopTask $surveyTask;

    private FopTask $installTask;

    private Item $dropcore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TaskMaterialService::class);

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $village = Village::create([
            'district_id' => $district->id,
            'name' => 'Polorejo',
            'postal_code' => '63491',
        ]);

        $pop = Pop::create([
            'name' => 'POP Polorejo',
            'code' => 'POP-PLR',
            'type' => 'branch',
            'address' => 'Polorejo',
            'status' => 'active',
            'city_id' => $city->id,
        ]);

        $this->customer = Customer::create([
            'customer_code' => 'MAT-0001',
            'full_name' => 'Pelanggan Material',
            'primary_phone' => '081200001',
            'status' => 'waiting_installation',
            'pop_id' => $pop->id,
            'village_id' => $village->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $this->dropcore = Item::create([
            'code' => 'DC-1C',
            'name' => 'Kabel Dropcore 1 Core',
            'type' => MaterialType::KABEL_DROPCORE->value,
            'unit' => 'meter',
            'is_active' => true,
        ]);

        $this->surveyTask = $this->makeTask(TaskType::SURVEY, 'TFOP-2026-MAT-0001');
        $this->installTask = $this->makeTask(TaskType::PEMASANGAN, 'TFOP-2026-MAT-0002');
    }

    private function makeTask(TaskType $category, string $number): FopTask
    {
        return FopTask::create([
            'task_number' => $number,
            'task_date' => now(),
            'category' => $category->value,
            'tugas' => 'Task Material',
            'village_id' => $this->customer->village_id,
            'pop_id' => $this->customer->pop_id,
            'customer_id' => $this->customer->id,
            'issue' => 'Test material',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);
    }

    public function test_baris_dari_master_mengambil_nama_tipe_dan_satuan_master(): void
    {
        $this->service->sync($this->surveyTask, MaterialKind::ESTIMASI, [
            // Nama & satuan sengaja diisi salah — master harus menang.
            ['item_id' => $this->dropcore->id, 'item_name' => 'DC 1C ngasal', 'item_type' => 'lainnya', 'qty' => 120, 'unit' => 'pcs'],
        ]);

        $row = $this->surveyTask->materials()->estimasi()->first();

        $this->assertSame('Kabel Dropcore 1 Core', $row->item_name);
        $this->assertSame(MaterialType::KABEL_DROPCORE, $row->item_type);
        $this->assertSame('meter', $row->unit);
        $this->assertSame($this->dropcore->id, $row->item_id);
    }

    public function test_baris_lainnya_tanpa_master_tetap_tersimpan(): void
    {
        $this->service->sync($this->installTask, MaterialKind::TERPAKAI, [
            ['item_id' => null, 'item_name' => 'Tray Kabel Custom', 'item_type' => MaterialType::AKSESORIS_PASANG->value, 'qty' => 2, 'unit' => ''],
        ]);

        $row = $this->installTask->materials()->terpakai()->first();

        $this->assertNull($row->item_id);
        $this->assertSame('Tray Kabel Custom', $row->item_name);
        // unit kosong → jatuh ke defaultUnit() tipe-nya.
        $this->assertSame('pcs', $row->unit);
    }

    public function test_baris_kosong_dan_qty_nol_dibuang(): void
    {
        $this->service->sync($this->surveyTask, MaterialKind::ESTIMASI, [
            ['item_id' => $this->dropcore->id, 'qty' => 100],
            ['item_id' => null, 'item_name' => '', 'qty' => 0],
            ['item_id' => null, 'item_name' => 'Klem', 'qty' => 0],
        ]);

        $this->assertSame(1, $this->surveyTask->materials()->count());
    }

    public function test_sync_mengganti_bukan_menggandakan(): void
    {
        $rows = [['item_id' => $this->dropcore->id, 'qty' => 100]];

        $this->service->sync($this->surveyTask, MaterialKind::ESTIMASI, $rows);
        $this->service->sync($this->surveyTask, MaterialKind::ESTIMASI, $rows);

        $this->assertSame(1, $this->surveyTask->materials()->estimasi()->count());
    }

    public function test_sync_satu_fase_tidak_menghapus_fase_lain(): void
    {
        $this->service->sync($this->surveyTask, MaterialKind::ESTIMASI, [
            ['item_id' => $this->dropcore->id, 'qty' => 100],
        ]);
        $this->service->sync($this->surveyTask, MaterialKind::TERPAKAI, [
            ['item_id' => $this->dropcore->id, 'qty' => 130],
        ]);

        $this->assertSame(1, $this->surveyTask->materials()->estimasi()->count());
        $this->assertSame(1, $this->surveyTask->materials()->terpakai()->count());
    }

    public function test_estimasi_pelanggan_terbaca_lintas_task(): void
    {
        // Estimasi menempel di task SURVEY, tapi form Pemasangan harus tetap
        // menemukannya lewat customer_id.
        $this->service->sync($this->surveyTask, MaterialKind::ESTIMASI, [
            ['item_id' => $this->dropcore->id, 'qty' => 100],
        ]);

        $estimates = $this->service->estimatesForCustomer($this->customer);

        $this->assertCount(1, $estimates);
        $this->assertSame('100.00', $estimates->first()->qty);
    }

    public function test_selisih_estimasi_vs_terpakai(): void
    {
        $this->service->sync($this->surveyTask, MaterialKind::ESTIMASI, [
            ['item_id' => $this->dropcore->id, 'qty' => 100],
            ['item_id' => null, 'item_name' => 'Klem Kabel', 'item_type' => MaterialType::AKSESORIS_PASANG->value, 'qty' => 10],
        ]);

        $this->service->sync($this->installTask, MaterialKind::TERPAKAI, [
            ['item_id' => $this->dropcore->id, 'qty' => 130],
            // Beda kapitalisasi — harus tetap dikelompokkan jadi satu baris.
            ['item_id' => null, 'item_name' => 'klem kabel', 'item_type' => MaterialType::AKSESORIS_PASANG->value, 'qty' => 8],
        ]);

        $variance = collect($this->service->varianceForCustomer($this->customer))->keyBy('label');

        $this->assertCount(2, $variance);
        $this->assertSame(30.0, $variance['Kabel Dropcore 1 Core']['selisih']);
        $this->assertSame(-2.0, $variance['Klem Kabel']['selisih']);
    }

    public function test_resolve_task_mengabaikan_task_dibatalkan(): void
    {
        $this->installTask->update(['status' => TaskStatus::DIBATALKAN->value]);

        $this->assertNull($this->service->resolveTaskFor($this->customer, TaskType::PEMASANGAN));
    }
}
