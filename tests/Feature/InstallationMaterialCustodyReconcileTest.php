<?php

namespace Tests\Feature;

use App\Enums\FopTaskPriority;
use App\Enums\MaterialKind;
use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\InventoryBalance;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskMaterial;
use App\Models\TechnicianCustody;
use App\Models\User;
use App\Services\InventoryIssueService;
use App\Services\InventoryReceiveService;
use App\Services\InventoryTransferService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-54 Fase integrasi — `CustomerInstallationController::reconcileMaterialsAgainstCustody()`,
 * dipanggil dari `storeSpeedtest()` (SATU-SATUNYA titik penyelesaian
 * pemasangan, keputusan eksplisit user: custody TIDAK boleh dipotong di
 * storePemasangan() karena itu bisa disubmit berkali-kali). Lihat komentar
 * lengkap di titik pemanggilan.
 */
class InstallationMaterialCustodyReconcileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WarehouseFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ItemCategorySeeder::class);
    }

    private function setupInProgressInstallation(): array
    {
        $pusat = Pop::create(['code' => 'PUSAT-RC', 'pop_code' => 'PST', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Pusat Reconcile Test', 'type' => 'pusat', 'status' => 'active']);
        $pop = Pop::create(['code' => 'CABANG-RC', 'pop_code' => 'CBG', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'Cabang Reconcile Test', 'type' => 'cabang', 'status' => 'active']);

        $technician = User::factory()->create();
        $role = Role::where('name', 'Teknisi')->firstOrFail();
        $technician->role_id = $role->id;
        $technician->save();
        $technician->load('role');
        $technician->roleScopes()->create(['role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP]);

        $customer = Customer::create([
            'customer_code' => 'TEST-RECONCILE-001',
            'full_name' => 'Reconcile Test Customer',
            'primary_phone' => '0812340001',
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
            'task_number' => 'TASK-TEST-RECONCILE-001',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan Reconcile Test Customer',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $technician->id,
            'updated_by' => $technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $technician->id, 'role_in_task' => 'lead']);

        return [$customer, $technician, $task, $pusat, $pop];
    }

    private function basePemasanganPayload(User $technician, Pop $pusat, Pop $cabang): array
    {
        return [
            'device_type' => 'ont',
            'connection_mode' => 'pppoe',
            'wifi_ssid' => 'WHUSNET_RECONCILE_TEST',
            'wifi_password' => 'password123',
            // Fallback teks manual sudah dicabut (koreksi lanjutan ADHOC-54)
            // — SN wajib berasal dari custody teknisi, lihat issueActiveSerialTo().
            'selected_inventory_serial_id' => $this->issueActiveSerialTo($technician, $pusat, $cabang)->id,
            'odp_number' => 'ODP-01',
            'odp_port' => '1',
            'olt_number' => 'OLT-01',
            'olt_slot' => '1',
            'olt_port' => '1',
            'installation_photo' => UploadedFile::fake()->image('installation.jpg'),
            'contract_photo' => UploadedFile::fake()->image('contract.jpg'),
            'signature_photo' => UploadedFile::fake()->image('signature.jpg'),
        ];
    }

    /**
     * Terbitkan satu SN ONT ke custody teknisi (Receive Pusat → Transfer
     * Cabang → Issue Teknisi) — dipakai basePemasanganPayload() supaya field
     * SN yang sekarang wajib dropdown-custody punya nilai valid buat submit.
     */
    private function issueActiveSerialTo(User $technician, Pop $pusat, Pop $cabang): InventorySerial
    {
        $catAktif = ItemCategory::where('equipment_class', 'aktif')->firstOrFail();
        $ont = Item::create([
            'code' => 'ONT-RC-'.uniqid(), 'name' => 'ONT Reconcile Test', 'item_category_id' => $catAktif->id,
            'unit' => 'unit', 'tracking_type' => 'serialized', 'ownership_mode' => 'installable',
        ]);
        $sn = 'ZTERECONCILE-'.uniqid();
        $admin = User::factory()->create();

        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($pusat, $ont, [$sn], 250000, $admin);
        $transfer = app(InventoryTransferService::class)->createTransfer($pusat, $cabang, [['item_id' => $ont->id, 'serial_numbers' => [$sn]]], $admin);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [$sn], [], $admin);
        app(InventoryIssueService::class)->issue($cabang, $technician, [['item_id' => $ont->id, 'serial_numbers' => [$sn]]], $admin);

        return $serial->refresh();
    }

    /**
     * Koreksi lanjutan ADHOC-54 (2026-09-12): "Lainnya (isi manual)" DICABUT
     * dari Material Terpakai — dulu baris freeform (`item_id` null) sengaja
     * DIBOLEHKAN lolos (test ini sebelumnya bernama
     * material_freeform_tanpa_item_id_tetap_lolos_seperti_sebelumnya, isinya
     * PERSIS kebalikan assert di bawah). Sekarang barang yang dipakai WAJIB
     * dari master (custody Gudang), gak boleh lagi nama karangan tanpa dasar
     * sistem — lihat validasi `materials.*.item_id` di storePemasangan().
     */
    #[Test]
    public function material_freeform_tanpa_item_id_sekarang_ditolak_validasi(): void
    {
        Storage::fake('public');
        [$customer, $technician, , $pusat, $cabang] = $this->setupInProgressInstallation();

        $response = $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePemasanganPayload($technician, $pusat, $cabang) + [
            'materials' => [
                ['item_name' => 'Kabel Dropcore', 'item_type' => 'kabel_dropcore', 'qty' => 50, 'unit' => 'meter'],
            ],
        ]);

        $response->assertSessionHasErrors('materials.0.item_id');
        $customer->refresh();
        $this->assertEquals('installation_in_progress', $customer->status, 'validasi gagal — tidak ada apa pun yang tersimpan');
    }

    /**
     * Bug nyata (2026-09-14): estimasi Survey "Lainnya" (item_id null, qty
     * terisi) tetap ke-prefill diam-diam ke Material Terpakai halaman
     * Pemasangan, dropdown-nya (yang sekarang gak punya opsi "Lainnya")
     * render KOSONG/gak kepilih di layar tapi qty-nya TETAP ke-submit lewat
     * input yang masih ada di DOM — teknisi yang cuma isi device+ODP lalu
     * tekan Aktivasi (BELUM nyentuh section Material Terpakai sama sekali)
     * malah kena "materials.0.item_id wajib diisi bila terdapat
     * materials.0.qty.". Fix: baris freeform di-drop dari prefill
     * (CustomerInstallationController::report()), bukan diteruskan ke form.
     */
    #[Test]
    public function estimasi_survey_freeform_tidak_ikut_prefill_dan_tidak_menghalangi_aktivasi(): void
    {
        Storage::fake('public');
        [$customer, $technician, , $pusat, $cabang] = $this->setupInProgressInstallation();

        $surveyFopTask = FopTask::create([
            'task_number' => 'TFOP-RC-SURVEY-FREEFORM',
            'task_date' => now(),
            'category' => TaskType::SURVEY->value,
            'tugas' => 'Survey Reconcile Freeform Test',
            'pop_id' => $cabang->id,
            'customer_id' => $customer->id,
            'issue' => 'Test estimasi freeform',
            'status' => TaskStatus::DRAFT->value,
            'priority' => FopTaskPriority::MEDIUM->value,
        ]);

        TaskMaterial::create([
            'fop_task_id' => $surveyFopTask->id,
            'customer_id' => $customer->id,
            'kind' => MaterialKind::ESTIMASI->value,
            'item_id' => null,
            'item_name' => 'Klem Kabel Estimasi Freeform',
            'item_type' => 'lainnya',
            'qty' => 77.5,
            'unit' => 'pcs',
        ]);

        // Halaman report() TIDAK BOLEH nge-prefill qty baris freeform itu ke
        // Alpine (@js($initialRows) di material-rows.blade.php) — kalau masih
        // ke-prefill, angka qty spesifik ini bakal nongol di JSON halaman.
        $page = $this->actingAs($technician)->get(route('customers.installation.report', $customer->id));
        $page->assertOk();
        $page->assertDontSee('77.5', false);
        // Tetap dikasih tau, bukan hilang diam-diam.
        $page->assertSee('Klem Kabel Estimasi Freeform');

        // Teknisi cuma isi device+ODP (BELUM nyentuh Material Terpakai sama
        // sekali) lalu tekan Aktivasi — harus SUKSES, gak boleh nabrak
        // validasi materials.0.item_id gara-gara baris di atas.
        $response = $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePemasanganPayload($technician, $pusat, $cabang));

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');
    }

    #[Test]
    public function material_item_tracked_dengan_custody_cukup_direkonsiliasi_dan_memotong_custody(): void
    {
        Storage::fake('public');
        [$customer, $technician, , $pusat, $cabang] = $this->setupInProgressInstallation();

        $catPasif = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'KABEL-RC', 'name' => 'Dropcore Reconcile', 'item_category_id' => $catPasif->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        $admin = User::factory()->create();
        app(InventoryReceiveService::class)->receiveQuantity($pusat, $kabel, 100, 5000, null, $admin);
        $transfer = app(InventoryTransferService::class)->createTransfer($pusat, $cabang, [['item_id' => $kabel->id, 'qty' => 100]], $admin);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [], [$kabel->id => 100], $admin);
        app(InventoryIssueService::class)->issue($cabang, $technician, [['item_id' => $kabel->id, 'qty' => 80]], $admin);

        $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePemasanganPayload($technician, $pusat, $cabang) + [
            'materials' => [
                ['item_id' => $kabel->id, 'qty' => 30, 'unit' => 'meter'],
            ],
        ]);

        $response = $this->actingAs($technician)->post(route('customers.installation.speedtest', $customer->id), [
            'test_download' => 20,
            'test_upload' => 10,
            'speedtest_photo' => UploadedFile::fake()->image('speedtest.jpg'),
        ]);

        $response->assertSessionHas('success');
        $customer->refresh();
        $this->assertEquals('verification_admin', $customer->status);

        $custody = TechnicianCustody::where('technician_id', $technician->id)->where('item_id', $kabel->id)->firstOrFail();
        $this->assertEquals(50, $custody->qty_remaining, '80 diissue - 30 dipakai = 50 sisa custody');

        $balance = InventoryBalance::where('pop_id', $cabang->id)->where('item_id', $kabel->id)->where('lot_no', '')->firstOrFail();
        $this->assertEquals(20, $balance->qty, 'stok gudang cabang TIDAK ikut terpotong lagi saat reconcile (sudah kepotong pas ISSUE)');

        $material = TaskMaterial::where('item_id', $kabel->id)->firstOrFail();
        $this->assertEquals(5000, $material->unit_price_snapshot, 'harga tersalin dari custody, bukan kosong');
    }

    /**
     * Koreksi lanjutan ADHOC-54 (2026-09-14, revisi lagi): overclaim di step 5
     * SENGAJA TIDAK memblokir submit — sempat dicoba jadi hard block
     * (throw ValidationException, versi 2026-09-12), tapi itu SALAH & langsung
     * bikin bug nyata: Aktivasi (storePemasangan) jadi bisa gagal gara-gara
     * Material Terpakai, padahal aturan tegasnya "Aktivasi cuma butuh
     * Informasi Perangkat Aktif + Distribusi Jaringan — foto/material/alat itu
     * syarat BUKA STEP 6, bukan syarat submit step 5" (ditegaskan user, dan
     * validation error bikin redirect tanpa ?activated=1 → wizard keliatan
     * "reset ke step 1"). Sekarang overclaim di step 5 cuma nempel WARNING di
     * flash 'success' (submit tetap sukses, data tetap tersimpan) — penegakan
     * SUNGGUHAN tetap di storeSpeedtest() (InventoryService::consumeFromCustody(),
     * lock+FIFO beneran), dites di bagian speedtest test ini.
     */
    #[Test]
    public function material_item_tracked_dengan_custody_kurang_tetap_tersimpan_di_step5_tapi_ditolak_final_di_speedtest(): void
    {
        Storage::fake('public');
        [$customer, $technician, , $pusat, $cabang] = $this->setupInProgressInstallation();

        $catPasif = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'KABEL-RC2', 'name' => 'Dropcore Reconcile 2', 'item_category_id' => $catPasif->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        $admin = User::factory()->create();
        app(InventoryReceiveService::class)->receiveQuantity($pusat, $kabel, 100, 5000, null, $admin);
        $transfer = app(InventoryTransferService::class)->createTransfer($pusat, $cabang, [['item_id' => $kabel->id, 'qty' => 100]], $admin);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [], [$kabel->id => 100], $admin);
        // SENGAJA cuma issue 10m, tapi teknisi klaim 30m di laporan — overclaim.
        app(InventoryIssueService::class)->issue($cabang, $technician, [['item_id' => $kabel->id, 'qty' => 10]], $admin);

        $step5 = $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePemasanganPayload($technician, $pusat, $cabang) + [
            'materials' => [
                ['item_id' => $kabel->id, 'qty' => 30, 'unit' => 'meter'],
            ],
        ]);

        // Sukses (bukan error) — cuma dikasih peringatan sisa custody di
        // pesan yang sama, submit-nya TETAP jalan.
        $step5->assertSessionHasNoErrors();
        $step5->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Sisa custody tim mungkin tidak cukup') && str_contains($msg, 'Dropcore Reconcile 2'));
        $customer->refresh();
        $this->assertEquals('installation_in_progress', $customer->status, 'step 5 belum menyelesaikan task — status belum berubah, tapi BUKAN karena ditolak');

        $custody = TechnicianCustody::where('technician_id', $technician->id)->where('item_id', $kabel->id)->firstOrFail();
        $this->assertEquals(10, $custody->qty_remaining, 'custody belum kepotong di step 5 — potongnya di storeSpeedtest()');

        // Fase 6 kebuka (foto+material tersimpan) meski overclaim — gerbangnya
        // cuma "ada baris material", bukan "custody cukup".
        $page = $this->actingAs($technician)->get(route('customers.installation.report', $customer->id));
        $page->assertDontSee('Aktivasi Laporan Speedtest');

        // Penegakan SUNGGUHAN: storeSpeedtest() (titik penyelesaian) tetap
        // menolak — custody yang beneran cuma 10m gak bisa nutup klaim 30m.
        $step6 = $this->actingAs($technician)->post(route('customers.installation.speedtest', $customer->id), [
            'test_download' => 20,
            'test_upload' => 10,
            'speedtest_photo' => UploadedFile::fake()->image('speedtest.jpg'),
        ]);

        $step6->assertSessionHas('error');
        $customer->refresh();
        $this->assertEquals('installation_in_progress', $customer->status, 'overclaim ditolak final di speedtest — customer TIDAK boleh ikut pindah status');

        $custody->refresh();
        $this->assertEquals(10, $custody->qty_remaining, 'custody TETAP tidak kepotong — rollback bersih di titik penyelesaian');
    }

    /**
     * Race yang gak ketauan cek step 5 (itu snapshot SAAT submit, bukan lock):
     * custody CUKUP waktu storePemasangan() disubmit, tapi berkurang (dipakai
     * customer lain oleh tim yang sama) sebelum storeSpeedtest() beneran
     * commit. Penegakan final `InventoryService::consumeFromCustody()`
     * (lock+FIFO) tetap harus nolak — inilah yang dulu dites
     * material_item_tracked_dengan_custody_kurang_gagal_dan_tidak_menyelesaikan_task
     * sebelum berubah nama di atas.
     */
    #[Test]
    public function material_custody_berkurang_setelah_step5_tetap_ketolak_final_di_speedtest(): void
    {
        Storage::fake('public');
        [$customer, $technician, , $pusat, $cabang] = $this->setupInProgressInstallation();

        $catPasif = ItemCategory::where('code', 'kabel_dropcore')->firstOrFail();
        $kabel = Item::create(['code' => 'KABEL-RC3', 'name' => 'Dropcore Reconcile 3', 'item_category_id' => $catPasif->id, 'unit' => 'meter', 'tracking_type' => 'quantity']);

        $admin = User::factory()->create();
        app(InventoryReceiveService::class)->receiveQuantity($pusat, $kabel, 100, 5000, null, $admin);
        $transfer = app(InventoryTransferService::class)->createTransfer($pusat, $cabang, [['item_id' => $kabel->id, 'qty' => 100]], $admin);
        app(InventoryTransferService::class)->receiveTransfer($transfer, [], [$kabel->id => 100], $admin);
        // Custody CUKUP (30) pas step 5 disubmit.
        app(InventoryIssueService::class)->issue($cabang, $technician, [['item_id' => $kabel->id, 'qty' => 30]], $admin);

        $this->actingAs($technician)->post(route('customers.installation.pemasangan', $customer->id), $this->basePemasanganPayload($technician, $pusat, $cabang) + [
            'materials' => [
                ['item_id' => $kabel->id, 'qty' => 30, 'unit' => 'meter'],
            ],
        ]);

        // Setelah step 5 tersimpan, custody berkurang duluan (dipakai laporan
        // lain oleh tim yang sama) — snapshot step 5 sudah basi.
        TechnicianCustody::where('technician_id', $technician->id)->where('item_id', $kabel->id)->update(['qty_remaining' => 5]);

        $response = $this->actingAs($technician)->post(route('customers.installation.speedtest', $customer->id), [
            'test_download' => 20,
            'test_upload' => 10,
            'speedtest_photo' => UploadedFile::fake()->image('speedtest.jpg'),
        ]);

        $response->assertSessionHas('error');
        $customer->refresh();
        $this->assertEquals('installation_in_progress', $customer->status, 'overclaim ditolak — customer TIDAK boleh ikut pindah status (rollback penuh)');

        $custody = TechnicianCustody::where('technician_id', $technician->id)->where('item_id', $kabel->id)->firstOrFail();
        $this->assertEquals(5, $custody->qty_remaining, 'custody TIDAK ikut kepotong sebagian — rollback bersih');
    }
}
