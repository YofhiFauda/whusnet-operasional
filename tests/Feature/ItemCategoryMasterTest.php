<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\MaterialKind;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskMaterial;
use App\Models\User;
use App\Services\TaskMaterialService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kategori material sebagai master, bukan enum.
 *
 * Inti yang dijaga: admin bisa menambah kategori dan langsung memakainya di
 * laporan lapangan TANPA deploy — itu alasan kategori dipindah dari enum
 * `MaterialType` ke tabel. Sisanya menjaga supaya kepindahan itu tidak
 * merusak data lama, yang risikonya justru lebih besar daripada fiturnya.
 */
class ItemCategoryMasterTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Customer $customer;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $ownerRole = Role::where('name', 'Owner')->first();
        $this->actor = User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);

        $this->pop = Pop::create([
            'code' => 'KTG',
            'pop_code' => 'KTG',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Kategori',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'customer_code' => 'KTG-001',
            'full_name' => 'Pelanggan Kategori',
            'primary_phone' => '0812345678',
            'status' => 'survey_in_progress',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        Task::create([
            'task_number' => 'TASK-KTG-001',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey Kategori',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->actor->id,
            'updated_by' => $this->actor->id,
        ]);

        FopTask::create([
            'task_number' => 'TFOP-2026-KTG-1',
            'task_date' => now(),
            'category' => TaskType::SURVEY->value,
            'tugas' => 'Survey Kategori',
            'village_id' => null,
            'pop_id' => $this->pop->id,
            'customer_id' => $this->customer->id,
            'issue' => 'Auto-Sync dari antrean survey',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);
    }

    public function test_tujuh_kategori_bawaan_ditanam_migrasi_dan_terkunci(): void
    {
        // Ditanam migrasi (bukan seeder) karena backfill kolom kategori
        // bergantung padanya — seeder jalan terlalu telat.
        $this->assertSame(7, ItemCategory::where('is_system', true)->count());

        foreach (['splitter_odp', 'kabel_dropcore', 'patch_cord', 'media_converter', 'antena_radio', 'aksesoris_pasang', 'lainnya'] as $code) {
            $this->assertTrue(ItemCategory::where('code', $code)->exists(), "Kategori bawaan {$code} hilang.");
        }

        $this->assertSame('meter', ItemCategory::where('code', 'kabel_dropcore')->value('default_unit'));
    }

    public function test_kategori_baru_langsung_bisa_dipakai_di_laporan_survey(): void
    {
        // Inti fitur: tanpa ubah kode, tanpa deploy.
        $this->actingAs($this->actor)
            ->post(route('master.item-categories.store'), [
                'code' => 'kabel_feeder',
                'name' => 'Kabel Feeder',
                'default_unit' => 'meter',
                'sort_order' => 100,
                'is_active' => 1,
            ])
            ->assertRedirect(route('master.item-categories.index'));

        $response = $this->actingAs($this->actor)
            ->post(route('customers.survey.store', $this->customer->id), [
                'survey_status' => 'pending',
                'nearest_odp' => 'ODP-01',
                'cable_estimation_meter' => 0,
                'materials' => [
                    ['item_id' => null, 'item_name' => 'Feeder 12 Core', 'item_type' => 'kabel_feeder', 'qty' => 250, 'unit' => ''],
                ],
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $row = TaskMaterial::where('customer_id', $this->customer->id)->estimasi()->first();

        $this->assertSame('kabel_feeder', $row->item_type);
        $this->assertSame(ItemCategory::where('code', 'kabel_feeder')->value('id'), $row->item_category_id);
        // Satuan kosong jatuh ke default_unit kategori, bukan 'pcs' hardcode.
        $this->assertSame('meter', $row->unit);
    }

    public function test_kategori_nonaktif_ditolak_saat_menyimpan_laporan(): void
    {
        ItemCategory::where('code', 'antena_radio')->update(['is_active' => false]);

        $response = $this->actingAs($this->actor)
            ->post(route('customers.survey.store', $this->customer->id), [
                'survey_status' => 'pending',
                'nearest_odp' => 'ODP-01',
                'cable_estimation_meter' => 0,
                'materials' => [
                    ['item_id' => null, 'item_name' => 'Radio Bekas', 'item_type' => 'antena_radio', 'qty' => 1, 'unit' => 'pcs'],
                ],
            ]);

        $response->assertSessionHasErrors('materials.0.item_type');
    }

    public function test_baris_material_lama_tetap_terbaca_walau_kategorinya_dihapus(): void
    {
        // Ini alasan `item_type` disimpan sebagai string snapshot DAN cast enum
        // dilepas: kategori yang hilang tidak boleh bikin halaman yang cuma
        // menampilkan riwayat jadi error.
        $category = ItemCategory::create([
            'code' => 'kategori_sementara',
            'name' => 'Kategori Sementara',
            'default_unit' => 'pcs',
            'is_active' => true,
        ]);

        $fopTask = FopTask::where('customer_id', $this->customer->id)->first();

        app(TaskMaterialService::class)->sync($fopTask, MaterialKind::ESTIMASI, [
            ['item_id' => null, 'item_name' => 'Barang Sementara', 'item_type' => 'kategori_sementara', 'qty' => 3, 'unit' => 'pcs'],
        ]);

        $category->delete();
        ItemCategory::flushLabelCache();

        $row = TaskMaterial::where('customer_id', $this->customer->id)->estimasi()->first();

        $this->assertSame('kategori_sementara', $row->item_type);
        // FK di-null-kan (nullOnDelete), snapshot code bertahan.
        $this->assertNull($row->fresh()->item_category_id);
        // Label jatuh ke code mentah, bukan melempar exception.
        $this->assertSame('kategori_sementara', $row->category_label);
    }

    public function test_kode_kategori_bawaan_terkunci_saat_diubah(): void
    {
        $dropcore = ItemCategory::where('code', ItemCategory::CODE_KABEL_DROPCORE)->first();

        $this->actingAs($this->actor)
            ->put(route('master.item-categories.update', $dropcore), [
                'code' => 'dropcore_baru',
                'name' => 'Dropcore Diganti Nama',
                'default_unit' => 'meter',
                'sort_order' => 20,
                'is_active' => 1,
            ])
            ->assertRedirect(route('master.item-categories.index'));

        $dropcore->refresh();

        // Nama boleh berubah — laporan lama ikut menampilkan nama baru.
        $this->assertSame('Dropcore Diganti Nama', $dropcore->name);
        // Kode TIDAK boleh: alur estimasi kabel otomatis & baris lama pakai ini.
        $this->assertSame(ItemCategory::CODE_KABEL_DROPCORE, $dropcore->code);
    }

    public function test_kategori_lainnya_tidak_bisa_dinonaktifkan(): void
    {
        $lainnya = ItemCategory::where('code', ItemCategory::CODE_LAINNYA)->first();

        $this->actingAs($this->actor)
            ->post(route('master.item-categories.toggle', $lainnya))
            ->assertSessionHas('error');

        $this->assertTrue($lainnya->fresh()->is_active);
    }

    public function test_kode_kategori_wajib_slug_dan_unik(): void
    {
        $this->actingAs($this->actor)
            ->post(route('master.item-categories.store'), [
                'code' => 'Kabel Feeder',
                'name' => 'Kabel Feeder',
                'default_unit' => 'meter',
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('code');

        $this->actingAs($this->actor)
            ->post(route('master.item-categories.store'), [
                'code' => 'kabel_dropcore',
                'name' => 'Duplikat',
                'default_unit' => 'meter',
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_kategori_master_barang_menang_atas_kiriman_form(): void
    {
        $item = Item::create([
            'code' => 'SPL-1X8',
            'name' => 'Splitter 1:8',
            'item_category_id' => ItemCategory::where('code', 'splitter_odp')->value('id'),
            'unit' => 'pcs',
            'is_active' => true,
        ]);

        $fopTask = FopTask::where('customer_id', $this->customer->id)->first();

        app(TaskMaterialService::class)->sync($fopTask, MaterialKind::ESTIMASI, [
            // Kategori dikarang — POST yang dirakit tangan tidak boleh menang.
            ['item_id' => $item->id, 'item_type' => 'kabel_dropcore', 'qty' => 4, 'unit' => 'meter'],
        ]);

        $row = TaskMaterial::where('customer_id', $this->customer->id)->estimasi()->first();

        $this->assertSame('splitter_odp', $row->item_type);
        $this->assertSame('pcs', $row->unit);
    }

    public function test_jenis_perangkat_pasif_pelanggan_menerima_kategori_buatan_admin(): void
    {
        // Dulu daftar ini disalin literal di CustomerDeviceController dan wajib
        // dijaga sinkron dengan enum secara manual.
        ItemCategory::create([
            'code' => 'pigtail',
            'name' => 'Pigtail',
            'default_unit' => 'pcs',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->actor)
            ->post(route('customers.device.store', $this->customer->id), [
                'device_type' => 'ont',
                'passive_device' => 'Pigtail SC/UPC',
                'passive_device_type' => 'pigtail',
            ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame('pigtail', $this->customer->fresh()->customerTechnicalDetail->passive_device_type);
    }

    public function test_halaman_master_kategori_butuh_permission(): void
    {
        $teknisiRole = Role::where('name', 'Teknisi')->first();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);

        $this->actingAs($teknisi)
            ->get(route('master.item-categories.index'))
            ->assertForbidden();

        $this->actingAs($teknisi)
            ->post(route('master.item-categories.store'), [
                'code' => 'nekat',
                'name' => 'Nekat',
                'default_unit' => 'pcs',
                'is_active' => 1,
            ])
            ->assertForbidden();
    }
}
