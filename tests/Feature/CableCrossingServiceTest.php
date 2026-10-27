<?php

namespace Tests\Feature;

use App\Enums\CableCrossingStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerCableCrossing;
use App\Models\CustomerCableCrossingPoint;
use App\Models\CustomerInstallation;
use App\Models\Pop;
use App\Models\Task;
use App\Models\User;
use App\Services\CableCrossingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-205 — sinkronisasi rute dari form Pemasangan dan invariant penyelesaian
 * di Deact, diuji langsung di service tanpa lewat HTTP.
 */
class CableCrossingServiceTest extends TestCase
{
    use RefreshDatabase;

    private CableCrossingService $service;

    private Customer $customer;

    private CustomerInstallation $installation;

    private User $recorder;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->service = app(CableCrossingService::class);
        $this->customer = Customer::factory()->create(['pop_id' => Pop::factory()->create()->id]);
        $this->installation = CustomerInstallation::create([
            'customer_id' => $this->customer->id,
            'installation_status' => 'in_progress',
        ]);
        $this->recorder = User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rute(string $namaJalan = 'Jl. Raya Pemasangan', array $overrides = []): array
    {
        return array_merge([
            'nama_jalan' => $namaJalan,
            'jenis_jalan' => 'Jalan raya',
            'meter_terpakai' => '35',
            'foto' => UploadedFile::fake()->image('bukti.jpg'),
            'points' => [
                ['label' => 'Tiang TR-12', 'latitude' => '-7.8711000', 'longitude' => '111.4620000'],
                ['label' => '', 'latitude' => '-7.8720000', 'longitude' => '111.4630000'],
                ['label' => 'Rumah pelanggan', 'latitude' => '-7.8730000', 'longitude' => '111.4640000'],
            ],
        ], $overrides);
    }

    private function makeDeactTask(): Task
    {
        return Task::create([
            'task_number' => 'TASK-CCS-'.random_int(1000, 9999),
            'task_type' => TaskType::AMBIL_MODEM->value,
            'title' => 'Ambil Modem Test',
            'pop_id' => $this->customer->pop_id,
            'customer_id' => $this->customer->id,
            'status' => TaskStatus::IN_PROGRESS->value,
            'created_by' => $this->recorder->id,
            'updated_by' => $this->recorder->id,
        ]);
    }

    #[Test]
    public function sinkron_pemasangan_menyimpan_rute_dengan_titik_berurutan(): void
    {
        $this->service->syncFromPemasangan($this->installation, $this->customer, $this->recorder, true, [$this->rute()], null);

        $crossing = CustomerCableCrossing::sole();
        $this->assertEquals(CableCrossingStatus::TERPASANG, $crossing->status);
        $this->assertEquals($this->installation->id, $crossing->customer_installation_id);
        $this->assertEquals($this->recorder->id, $crossing->dicatat_oleh);
        $this->assertEquals('Jl. Raya Pemasangan', $crossing->nama_jalan);
        Storage::disk('public')->assertExists($crossing->foto_bukti);

        $this->assertEquals([1, 2, 3], $crossing->points->pluck('urutan')->all());
        $this->assertEquals('Tiang TR-12', $crossing->points->first()->label);
        $this->assertEquals('-7.8730000', (string) $crossing->points->last()->latitude);
    }

    #[Test]
    public function sinkron_ulang_tidak_menggandakan_rute_dan_mengganti_titiknya(): void
    {
        $this->service->syncFromPemasangan($this->installation, $this->customer, $this->recorder, true, [$this->rute()], null);
        $crossing = CustomerCableCrossing::sole();

        // Form dikirim ulang dengan id rute yang sama, foto tidak diganti, titik dikurangi.
        $this->service->syncFromPemasangan($this->installation, $this->customer, $this->recorder, true, [
            $this->rute('Jl. Raya Revisi', [
                'id' => $crossing->id,
                'foto' => null,
                'points' => [
                    ['label' => 'Tiang baru', 'latitude' => '-7.8800000', 'longitude' => '111.4700000'],
                    ['label' => 'Rumah pelanggan', 'latitude' => '-7.8810000', 'longitude' => '111.4710000'],
                ],
            ]),
        ], null);

        $this->assertEquals(1, CustomerCableCrossing::count());
        $crossing->refresh();
        $this->assertEquals('Jl. Raya Revisi', $crossing->nama_jalan);
        $this->assertEquals(2, $crossing->points()->count());
        $this->assertEquals('Tiang baru', $crossing->points->first()->label);
        // Pencatat pertama tetap, sinkron ulang tidak menggantinya.
        $this->assertEquals($this->recorder->id, $crossing->dicatat_oleh);
    }

    #[Test]
    public function rute_yang_dihapus_dari_form_ikut_terhapus_dan_centang_dilepas_menghapus_semua(): void
    {
        $this->service->syncFromPemasangan($this->installation, $this->customer, $this->recorder, true, [
            $this->rute('Jl. A'),
            $this->rute('Jl. B'),
        ], null);
        $this->assertEquals(2, CustomerCableCrossing::count());

        $keep = CustomerCableCrossing::where('nama_jalan', 'Jl. A')->firstOrFail();
        $this->service->syncFromPemasangan($this->installation, $this->customer, $this->recorder, true, [
            $this->rute('Jl. A', ['id' => $keep->id, 'foto' => null]),
        ], null);
        $this->assertEquals(['Jl. A'], CustomerCableCrossing::pluck('nama_jalan')->all());
        $this->assertEquals(0, CustomerCableCrossingPoint::where('customer_cable_crossing_id', '!=', $keep->id)->count());

        $this->service->syncFromPemasangan($this->installation, $this->customer, $this->recorder, false, [], null);
        $this->assertEquals(0, CustomerCableCrossing::count());
    }

    #[Test]
    public function rute_baru_tanpa_foto_ditolak_sebelum_upload(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->assertInstallationPhotos($this->installation, [$this->rute('Jl. Tanpa Foto', ['foto' => null])]);
    }

    #[Test]
    public function rute_lama_boleh_tanpa_foto_baru(): void
    {
        $this->service->syncFromPemasangan($this->installation, $this->customer, $this->recorder, true, [$this->rute()], null);
        $crossing = CustomerCableCrossing::sole();

        $this->service->assertInstallationPhotos($this->installation, [
            $this->rute('Jl. Raya Pemasangan', ['id' => $crossing->id, 'foto' => null]),
        ]);

        $this->assertEquals(1, CustomerCableCrossing::count());
    }

    #[Test]
    public function deact_menolak_kalau_ada_rute_yang_belum_punya_hasil(): void
    {
        $this->service->syncFromPemasangan($this->installation, $this->customer, $this->recorder, true, [$this->rute()], null);
        $task = $this->makeDeactTask();

        $this->expectException(ValidationException::class);

        $this->service->resolveForDeact($this->customer, $task, []);
    }

    #[Test]
    public function deact_yang_menyelesaikan_rute_tidak_menyentuh_rute_pelanggan_lain(): void
    {
        $this->service->syncFromPemasangan($this->installation, $this->customer, $this->recorder, true, [$this->rute()], null);
        $crossing = CustomerCableCrossing::sole();

        $otherCustomer = Customer::factory()->create();
        $otherCrossing = CustomerCableCrossing::factory()->create(['customer_id' => $otherCustomer->id]);

        $task = $this->makeDeactTask();
        $this->service->resolveForDeact($this->customer, $task, [
            $crossing->id => ['status' => CableCrossingStatus::TIDAK_BISA_DITARIK->value, 'alasan' => 'Tertanam di aspal'],
        ]);

        $this->assertEquals(CableCrossingStatus::TIDAK_BISA_DITARIK, $crossing->refresh()->status);
        $this->assertEquals(CableCrossingStatus::TERPASANG, $otherCrossing->refresh()->status);
    }

    #[Test]
    public function hitung_terbuka_mencakup_tidak_bisa_ditarik_tapi_bukan_yang_sudah_ditarik(): void
    {
        CustomerCableCrossing::factory()->create(['customer_id' => $this->customer->id]);
        CustomerCableCrossing::factory()->tidakBisaDitarik()->create(['customer_id' => $this->customer->id]);
        CustomerCableCrossing::factory()->ditarik()->create(['customer_id' => $this->customer->id]);

        $this->assertEquals(2, $this->service->countOpenFor($this->customer));
    }
}
