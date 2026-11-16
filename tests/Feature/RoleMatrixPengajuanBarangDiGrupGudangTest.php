<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Laporan user 2026-11-16 ("Pengajuan barang apakah belum ada di RBAC??"):
 * feature `warehouse_pengajuan_barang` sudah ada di DB + route, tapi lupa
 * didaftarkan di grup "Gudang & Inventory" Role Matrix → kebuang ke
 * "Modul Tambahan Lainnya" di paling bawah tanpa deskripsi, kelihatan
 * seperti belum ada. Pola sama RoleMatrixBusinessDevelopmentCategoryTest.
 */
class RoleMatrixPengajuanBarangDiGrupGudangTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function surat_pengajuan_barang_tampil_di_grup_gudang_bukan_modul_lainnya(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(WarehouseFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->loginAsAdmin();

        $role = Role::where('code', 'fop')->firstOrFail();
        $response = $this->get(route('roles.matrix', $role));

        $response->assertOk();

        $html = $response->getContent();
        $blokGudang = $this->blokKategori($html, 'group_warehouse');
        $blokLainnya = $this->blokKategori($html, 'group_other');

        $this->assertStringContainsString('Surat Pengajuan Barang', $blokGudang);
        $this->assertStringContainsString('BELUM ADA di katalog/gudang', $blokGudang);
        $this->assertStringContainsString('ditunjuk sebagai Approver Pengajuan Barang', $blokGudang);
        $this->assertStringNotContainsString('Surat Pengajuan Barang', $blokLainnya);
    }

    /**
     * Potongan HTML satu blok kategori: dari `data-category-key="…"` sampai
     * blok kategori berikutnya (atau akhir dokumen). String kosong kalau
     * blok itu tidak dirender (kategori tanpa item disembunyikan view).
     */
    private function blokKategori(string $html, string $key): string
    {
        $start = strpos($html, 'data-category-key="'.$key.'"');
        if ($start === false) {
            return '';
        }

        $next = strpos($html, 'data-category-key="', $start + 1);

        return $next === false ? substr($html, $start) : substr($html, $start, $next - $start);
    }
}
