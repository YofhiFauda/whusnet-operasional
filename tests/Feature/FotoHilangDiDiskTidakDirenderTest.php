<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
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
use Tests\TestCase;

/**
 * Data foto lama menyimpan nama file hash telanjang tanpa folder (skema
 * sebelum FileUploadService, mis. `d481de7d….jpg`) dan file fisiknya sudah
 * tidak ada di `storage/app/public`. View dulu cuma mengecek kolom DB terisi,
 * jadi tetap merender <img src="/storage/d481de7d….jpg"> — hasilnya ikon rusak
 * di halaman Laporan Pemasangan plus 404 di console, padahal cabang @else
 * "tidak ada foto" yang sudah tersedia di view justru yang benar.
 *
 * Penjaganya `foto_publik()`: path yang filenya hilang diperlakukan sama
 * dengan path kosong — termasuk untuk label tombol unggah, supaya teknisi
 * diminta mengunggah ulang alih-alih mengira fotonya masih tersimpan.
 */
class FotoHilangDiDiskTidakDirenderTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'SMN-FOT',
            'pop_code' => 'FOT',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Foto Hilang Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $teknisiRole = Role::where('code', 'teknisi')->first();
        $this->technician = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);
    }

    /**
     * Siapkan pelanggan yang sedang dipasang + task PEMASANGAN yang
     * teknisinya adalah $this->technician (guard assignment di controller).
     */
    private function makeCustomerSiapLapor(string $suffix): Customer
    {
        $customer = Customer::create([
            'customer_code' => 'FOT-'.$suffix,
            'full_name' => 'Pelanggan Foto Hilang',
            'primary_phone' => '081234500001',
            'status' => 'installation_in_progress',
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);

        $task = Task::create([
            'task_number' => 'TASK-FOT-'.$suffix,
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan Foto Hilang Test',
            'status' => TaskStatus::IN_PROGRESS->value,
            'started_at' => now(),
            'created_by' => $this->technician->id,
            'updated_by' => $this->technician->id,
        ]);
        $task->teamMembers()->create(['user_id' => $this->technician->id, 'role_in_task' => 'lead']);

        $customer->installations()->create([
            'installation_status' => 'in_progress',
            'started_at' => now(),
            'technician_id' => $this->technician->id,
        ]);

        return $customer;
    }

    public function test_foto_survey_yang_filenya_hilang_tidak_dirender_sebagai_img(): void
    {
        Storage::fake('public');

        $customer = $this->makeCustomerSiapLapor('ORPHAN');

        // Persis bentuk data lama: nama file hash telanjang, tanpa folder,
        // dan tidak ada file fisiknya di disk.
        $customer->surveys()->create([
            'survey_status' => 'completed',
            'started_at' => now(),
            'technician_id' => $this->technician->id,
            'house_photo' => 'd481de7dcd43872310db5a3d80bf07e7.jpg',
            'survey_photo' => 'a1b2c3d4e5f60718293a4b5c6d7e8f90.jpg',
        ]);

        $response = $this->actingAs($this->technician)
            ->get(route('customers.installation.report', $customer->id));

        $response->assertOk();
        $response->assertDontSee('d481de7dcd43872310db5a3d80bf07e7.jpg', false);
        $response->assertDontSee('a1b2c3d4e5f60718293a4b5c6d7e8f90.jpg', false);

        // Jatuh ke cabang @else, bukan <img> rusak.
        $response->assertSee('Tidak ada di survey', false);
    }

    public function test_foto_survey_yang_filenya_ada_tetap_dirender(): void
    {
        Storage::fake('public');

        $customer = $this->makeCustomerSiapLapor('ADA');

        $housePath = UploadedFile::fake()->image('rumah.jpg')->store('surveys/rumah', 'public');
        $odpPath = UploadedFile::fake()->image('odp.jpg')->store('surveys/odp', 'public');

        $customer->surveys()->create([
            'survey_status' => 'completed',
            'started_at' => now(),
            'technician_id' => $this->technician->id,
            'house_photo' => $housePath,
            'survey_photo' => $odpPath,
        ]);

        $response = $this->actingAs($this->technician)
            ->get(route('customers.installation.report', $customer->id));

        $response->assertOk();
        $response->assertSee(asset('storage/'.$housePath), false);
        $response->assertSee(asset('storage/'.$odpPath), false);
    }

    public function test_foto_pemasangan_yang_filenya_hilang_dianggap_belum_diunggah(): void
    {
        Storage::fake('public');

        $customer = $this->makeCustomerSiapLapor('UPLOAD');

        // Kolom terisi tapi file hilang: teknisi tidak boleh dikasih tahu
        // "Sudah Tersimpan", karena yang tersimpan tinggal path-nya.
        $customer->installations()->latest()->first()->update([
            'installation_photo' => 'ffeeddccbbaa99887766554433221100.jpg',
        ]);

        $response = $this->actingAs($this->technician)
            ->get(route('customers.installation.report', $customer->id));

        $response->assertOk();
        $response->assertDontSee('ffeeddccbbaa99887766554433221100.jpg', false);
        $response->assertDontSee('Sudah Tersimpan', false);
        $response->assertDontSee('data-has-existing="true"', false);
    }
}
