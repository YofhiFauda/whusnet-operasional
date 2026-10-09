<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Services\RoleManagementService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bug (2026-10): admin mencentang kolektor.pay / kolektor.deposit / kolektor.visit
 * lalu mencabut kolektor.view (Worklist) dan menyimpan. Setelah disimpan,
 * kolektor.view kembali tercentang karena auto-grant `view`.
 */
class KolektorViewTidakIkutAutoGrantTest extends TestCase
{
    use RefreshDatabase;

    private function idPermission(string $code): int
    {
        return Permission::where('code', $code)->firstOrFail()->id;
    }

    #[Test]
    public function mencentang_pembayaran_dan_setoran_tidak_mencentang_worklist(): void
    {
        $this->seed(DatabaseSeeder::class);

        $hasil = app(RoleManagementService::class)->withAutoViewGrants([
            $this->idPermission('kolektor.pay'),
            $this->idPermission('kolektor.deposit'),
            $this->idPermission('kolektor.visit'),
        ]);

        $this->assertNotContains($this->idPermission('kolektor.view'), $hasil, 'kolektor.view tidak boleh ikut tercentang otomatis.');
        $this->assertContains($this->idPermission('kolektor.pay'), $hasil);
    }

    #[Test]
    public function worklist_tetap_bisa_dicentang_eksplisit(): void
    {
        $this->seed(DatabaseSeeder::class);

        $hasil = app(RoleManagementService::class)->withAutoViewGrants([
            $this->idPermission('kolektor.view'),
            $this->idPermission('kolektor.pay'),
        ]);

        $this->assertContains($this->idPermission('kolektor.view'), $hasil);
    }
}
