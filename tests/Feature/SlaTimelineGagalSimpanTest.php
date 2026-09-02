<?php

namespace Tests\Feature;

use App\Models\InternetPackage;
use App\Models\PackageSlaSetting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gejala yang dijaga: matriks SLA menulis "Tersimpan." untuk penyimpanan yang
 * sebenarnya gagal.
 *
 * Halaman itu menyimpan otomatis tiap kali nilai berubah — tak ada tombol
 * Simpan yang bisa dipandangi. Versi lama tidak memeriksa `res.ok` maupun
 * memasang `.catch`, jadi 422/403/500 pun berakhir dengan pesan sukses, dan
 * admin menutup halaman yakin SLA paket sudah berubah padahal tidak.
 *
 * Perbaikan utamanya di sisi JS, tapi JS itu bergantung pada BENTUK response:
 * status non-2xx plus `message`/`errors` yang bisa ditampilkan. Test ini
 * mengunci kontrak tersebut — kalau endpoint kelak diam-diam mengembalikan 200
 * untuk input tak valid, pesan sukses palsu itu hidup lagi tanpa ada yang
 * menyadari.
 */
class SlaTimelineGagalSimpanTest extends TestCase
{
    use RefreshDatabase;

    private InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
    }

    public function test_input_tidak_valid_dijawab_422_dengan_pesan_yang_bisa_ditampilkan(): void
    {
        $this->loginAsAdmin();

        // Seeder sudah mengisi sebagian setelan, jadi yang dijaga adalah
        // "tidak berubah", bukan "tabelnya kosong".
        $sebelum = PackageSlaSetting::where('internet_package_id', $this->package->id)
            ->pluck('sla_duration', 'task_type');

        $response = $this->putJson(route('master.sla-timeline.update', $this->package), [
            'task_type' => 'PSB',
            'sla_duration' => 0,
            'sla_unit' => 'day',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['sla_duration']]);

        $sesudah = PackageSlaSetting::where('internet_package_id', $this->package->id)
            ->pluck('sla_duration', 'task_type');

        $this->assertEquals($sebelum->all(), $sesudah->all());
    }

    public function test_satuan_di_luar_pilihan_juga_ditolak(): void
    {
        $this->loginAsAdmin();

        $this->putJson(route('master.sla-timeline.update', $this->package), [
            'task_type' => 'PSB',
            'sla_duration' => 3,
            'sla_unit' => 'minggu',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['sla_unit']]);
    }

    public function test_input_valid_tersimpan(): void
    {
        $this->loginAsAdmin();

        $this->putJson(route('master.sla-timeline.update', $this->package), [
            'task_type' => 'PSB',
            'sla_duration' => 3,
            'sla_unit' => 'day',
        ])
            ->assertOk();

        $this->assertDatabaseHas('package_sla_settings', [
            'internet_package_id' => $this->package->id,
            'task_type' => 'PSB',
            'sla_duration' => 3,
            'sla_unit' => 'day',
        ]);
    }
}
