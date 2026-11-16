<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Laporan user 2026-11-16: di Role Matrix beberapa checkbox berlabel kode
 * mentah — `network_nodes.register_infra`, `manuals.publish`,
 * `customers.detail.packages.change`, `customers.isolir`. Penyebabnya:
 * ActionCode-nya (register_infra/publish/change/isolir) tak terdaftar di
 * `$actionMetaMap` roles/matrix.blade.php, dan `permissions.name` di DB null
 * → label jatuh ke `$perm->code`.
 *
 * Test kedua sengaja GENERIK (semua permission hasil DatabaseSeeder), supaya
 * ActionCode baru yang lupa didaftarkan langsung ketahuan, bukan nunggu
 * laporan user berikutnya.
 */
class RoleMatrixLabelTidakTampilKodeMentahTest extends TestCase
{
    use RefreshDatabase;

    private function htmlMatrix(): string
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $role = Role::where('code', 'fop')->firstOrFail();
        $response = $this->get(route('roles.matrix', $role));
        $response->assertOk();

        return $response->getContent();
    }

    #[Test]
    public function empat_permission_yang_dilaporkan_tampil_dengan_label_ramah(): void
    {
        $labels = $this->labelPerKode($this->htmlMatrix());

        $this->assertStringStartsWith('registrasi aset lama', $labels['network_nodes.register_infra']);
        $this->assertStringStartsWith('terbitkan', $labels['manuals.publish']);
        $this->assertStringStartsWith('ganti', $labels['customers.detail.packages.change']);
        $this->assertStringStartsWith('isolir / buka isolir', $labels['customers.isolir']);
    }

    #[Test]
    public function tidak_ada_permission_yang_labelnya_kode_mentah(): void
    {
        $labels = $this->labelPerKode($this->htmlMatrix());

        $this->assertNotEmpty($labels);

        $mentah = array_keys(array_filter(
            $labels,
            fn (string $label, string $code) => str_starts_with($label, strtolower($code)),
            ARRAY_FILTER_USE_BOTH,
        ));

        $this->assertSame([], $mentah, 'Permission berlabel kode mentah — daftarkan ActionCode-nya di $actionMetaMap roles/matrix.blade.php.');
    }

    /**
     * Peta kode permission → isi `data-perm-name` (label + deskripsi,
     * lowercase) dari markup checkbox matrix.
     *
     * @return array<string, string>
     */
    private function labelPerKode(string $html): array
    {
        preg_match_all('/data-perm-code="([^"]+)"\s+data-perm-name="([^"]*)"/', $html, $m, PREG_SET_ORDER);

        $labels = [];
        foreach ($m as [, $code, $name]) {
            $labels[$code] = html_entity_decode($name, ENT_QUOTES);
        }

        return $labels;
    }
}
