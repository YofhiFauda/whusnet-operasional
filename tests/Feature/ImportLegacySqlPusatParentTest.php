<?php

namespace Tests\Feature;

use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gejala yang dijaga: `app:import-legacy-sql` sebelum fix ini bikin Cabang
 * baru (`type=cabang`) tanpa `parent_id` sama sekali — orphan, gak nempel ke
 * Pop `type=pusat` manapun — walau di Master POP hierarkinya sudah
 * Pusat > Cabang > Mini POP. Command harus wajib diberi `--pusat-code` dan
 * memasang `parent_id` Cabang ke Pusat itu.
 */
class ImportLegacySqlPusatParentTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturePath = 'tests/fixtures/legacy-mini.sql';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_gagal_tanpa_pusat_code(): void
    {
        $this->artisan('app:import-legacy-sql', [
            'file' => $this->fixturePath,
            '--branch-code' => 'C',
            '--branch-name' => 'Jetis',
        ])->assertExitCode(1);

        $this->assertDatabaseCount('pops', 0);
    }

    public function test_gagal_kalau_pusat_code_tidak_ditemukan(): void
    {
        $this->artisan('app:import-legacy-sql', [
            'file' => $this->fixturePath,
            '--branch-code' => 'C',
            '--branch-name' => 'Jetis',
            '--pusat-code' => 'TIDAK-ADA',
        ])->assertExitCode(1);

        $this->assertDatabaseMissing('pops', ['type' => 'cabang']);
    }

    public function test_cabang_baru_nempel_ke_pusat_yang_sudah_ada(): void
    {
        $pusat = Pop::create([
            'code' => 'PST',
            'pop_code' => 'PST',
            'name' => 'Pusat',
            'type' => 'pusat',
            'status' => 'active',
            'registration_prefix' => 'RQ',
            'cid_prefix' => 'P',
        ]);

        $this->artisan('app:import-legacy-sql', [
            'file' => $this->fixturePath,
            '--branch-code' => 'C',
            '--branch-name' => 'Jetis',
            '--pusat-code' => 'PST',
        ])->assertExitCode(0);

        $cabang = Pop::where('type', 'cabang')->where('pop_code', 'C')->firstOrFail();
        $this->assertSame($pusat->id, $cabang->parent_id, 'Cabang hasil import harus jadi anak Pop type=pusat, bukan orphan.');
    }
}
