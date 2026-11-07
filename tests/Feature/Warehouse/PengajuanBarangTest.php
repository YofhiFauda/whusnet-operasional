<?php

namespace Tests\Feature\Warehouse;

use App\Enums\PengajuanBarangStatus;
use App\Enums\ScopeType;
use App\Models\PengajuanBarang;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WarehouseFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Surat Pengajuan Barang (gap #2 dari 3 feature report 2026-11-05) — barang
 * yang BELUM ada di gudang, approval tunggal oleh FOP. Lihat docblock
 * migration `create_pengajuan_barang_table`.
 */
class PengajuanBarangTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private Pop $popLain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(WarehouseFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create(['code' => 'PGJ-POP', 'pop_code' => 'PGJP', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'POP Bandar Test', 'type' => 'cabang', 'status' => 'active']);
        $this->popLain = Pop::create(['code' => 'PGJ-POP-2', 'pop_code' => 'PGJ2', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'POP Lain Test', 'type' => 'cabang', 'status' => 'active']);
    }

    private function makeUserWithRole(string $roleCode, ?Pop $scopedTo = null): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        if ($scopedTo) {
            $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => 'selected_pop']);
            $scope->targets()->create(['pop_id' => $scopedTo->id]);
        } else {
            // NOC/FOP/Atasan/Admin Gudang Pusat beroperasi lintas cabang —
            // sama pola makeUserWithAllPopScope() di test lain (mis.
            // NocDashboardTest).
            $user->roleScopes()->create(['role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP->value]);
        }

        return $user;
    }

    /**
     * Dari 4 FOP ALL_POP, cuma 1 yang ditunjuk approver (keputusan user
     * 2026-11-07) — Scope POP gak bisa membedakan ini, jadi dites eksplisit
     * lewat flag `is_pengajuan_barang_approver`.
     */
    private function makeDesignatedFopApprover(): User
    {
        $fop = $this->makeUserWithRole('fop');
        $fop->update(['is_pengajuan_barang_approver' => true]);

        return $fop;
    }

    private function sampleLines(): array
    {
        return [
            ['nama_barang' => 'OLT Gpon ZTE C320', 'spesifikasi' => null, 'jumlah' => 2, 'satuan' => 'Unit', 'keterangan_link' => null],
            ['nama_barang' => 'Kabel 12 Core', 'spesifikasi' => '12 Core', 'jumlah' => 2000, 'satuan' => 'Meter', 'keterangan_link' => 'https://vendor.example.com/kabel-12core'],
        ];
    }

    #[Test]
    public function noc_bisa_mengajukan_barang_dan_nomor_terformat_benar(): void
    {
        $noc = $this->makeUserWithRole('noc');

        $response = $this->actingAs($noc)->post(route('warehouse.pengajuan-barang.store'), [
            'pop_id' => $this->pop->id,
            'nama_kegiatan' => 'Penambahan POP Bandar, Pacitan (Baru)',
            'lines' => $this->sampleLines(),
        ]);

        $pengajuan = PengajuanBarang::firstOrFail();
        $response->assertRedirect(route('warehouse.pengajuan-barang.show', $pengajuan));

        $this->assertMatchesRegularExpression('/^SPB-\d{8}-\d{6}$/', $pengajuan->nomor_request);
        $this->assertSame($noc->id, $pengajuan->pengaju_id);
        $this->assertSame($this->pop->id, $pengajuan->pop_id);
        $this->assertSame(PengajuanBarangStatus::MENUNGGU_FOP, $pengajuan->status);
        $this->assertCount(2, $pengajuan->items);
        $this->assertSame('https://vendor.example.com/kabel-12core', $pengajuan->items->last()->keterangan_link);
    }

    #[Test]
    public function teknisi_tidak_bisa_mengajukan_barang(): void
    {
        $teknisi = $this->makeUserWithRole('teknisi');

        $response = $this->actingAs($teknisi)->post(route('warehouse.pengajuan-barang.store'), [
            'pop_id' => $this->pop->id,
            'nama_kegiatan' => 'Coba-coba',
            'lines' => $this->sampleLines(),
        ]);

        $response->assertForbidden();
        $this->assertSame(0, PengajuanBarang::count());
    }

    #[Test]
    public function pengajuan_harus_punya_minimal_1_baris_barang(): void
    {
        $noc = $this->makeUserWithRole('noc');

        $response = $this->actingAs($noc)->post(route('warehouse.pengajuan-barang.store'), [
            'pop_id' => $this->pop->id,
            'nama_kegiatan' => 'Tanpa barang',
            'lines' => [],
        ]);

        $response->assertSessionHasErrors('lines');
        $this->assertSame(0, PengajuanBarang::count());
    }

    #[Test]
    public function fop_bisa_menyetujui_pengajuan(): void
    {
        $noc = $this->makeUserWithRole('noc');
        $this->actingAs($noc)->post(route('warehouse.pengajuan-barang.store'), [
            'pop_id' => $this->pop->id,
            'nama_kegiatan' => 'Penambahan POP Bandar',
            'lines' => $this->sampleLines(),
        ]);
        $pengajuan = PengajuanBarang::firstOrFail();

        $fop = $this->makeDesignatedFopApprover();
        $response = $this->actingAs($fop)->post(route('warehouse.pengajuan-barang.approve', $pengajuan));

        $response->assertRedirect(route('warehouse.pengajuan-barang.show', $pengajuan));
        $pengajuan->refresh();
        $this->assertSame(PengajuanBarangStatus::DISETUJUI, $pengajuan->status);
        $this->assertSame($fop->id, $pengajuan->decided_by);
        $this->assertNotNull($pengajuan->decided_at);
    }

    #[Test]
    public function fop_yang_bukan_approver_tidak_bisa_menyetujui_atau_menolak(): void
    {
        $noc = $this->makeUserWithRole('noc');
        $this->actingAs($noc)->post(route('warehouse.pengajuan-barang.store'), [
            'pop_id' => $this->pop->id,
            'nama_kegiatan' => 'Penambahan POP Bandar',
            'lines' => $this->sampleLines(),
        ]);
        $pengajuan = PengajuanBarang::firstOrFail();

        // 4 FOP ALL_POP, tapi cuma 1 yang ditunjuk approver — FOP lain
        // (role + permission approve lengkap, cuma BUKAN approver yang
        // ditunjuk) tetap ditolak di level Service.
        $fopBiasa = $this->makeUserWithRole('fop');

        $approveResponse = $this->actingAs($fopBiasa)->post(route('warehouse.pengajuan-barang.approve', $pengajuan));
        $approveResponse->assertSessionHas('error');
        $this->assertSame(PengajuanBarangStatus::MENUNGGU_FOP, $pengajuan->refresh()->status);

        $rejectResponse = $this->actingAs($fopBiasa)->post(route('warehouse.pengajuan-barang.reject', $pengajuan), [
            'reason' => 'Coba tolak walau bukan approver',
        ]);
        $rejectResponse->assertSessionHas('error');
        $this->assertSame(PengajuanBarangStatus::MENUNGGU_FOP, $pengajuan->refresh()->status);
    }

    #[Test]
    public function fop_bisa_menolak_pengajuan_dengan_alasan(): void
    {
        $noc = $this->makeUserWithRole('noc');
        $this->actingAs($noc)->post(route('warehouse.pengajuan-barang.store'), [
            'pop_id' => $this->pop->id,
            'nama_kegiatan' => 'Penambahan POP Bandar',
            'lines' => $this->sampleLines(),
        ]);
        $pengajuan = PengajuanBarang::firstOrFail();

        $fop = $this->makeDesignatedFopApprover();
        $response = $this->actingAs($fop)->post(route('warehouse.pengajuan-barang.reject', $pengajuan), [
            'reason' => 'Anggaran belum cukup bulan ini.',
        ]);

        $response->assertRedirect(route('warehouse.pengajuan-barang.show', $pengajuan));
        $pengajuan->refresh();
        $this->assertSame(PengajuanBarangStatus::DITOLAK, $pengajuan->status);
        $this->assertSame('Anggaran belum cukup bulan ini.', $pengajuan->rejection_reason);
    }

    #[Test]
    public function reject_wajib_isi_alasan(): void
    {
        $noc = $this->makeUserWithRole('noc');
        $this->actingAs($noc)->post(route('warehouse.pengajuan-barang.store'), [
            'pop_id' => $this->pop->id,
            'nama_kegiatan' => 'Penambahan POP Bandar',
            'lines' => $this->sampleLines(),
        ]);
        $pengajuan = PengajuanBarang::firstOrFail();

        $fop = $this->makeUserWithRole('fop');
        $response = $this->actingAs($fop)->post(route('warehouse.pengajuan-barang.reject', $pengajuan), []);

        $response->assertSessionHasErrors('reason');
        $this->assertSame(PengajuanBarangStatus::MENUNGGU_FOP, $pengajuan->refresh()->status);
    }

    #[Test]
    public function pengajuan_yang_sudah_diputuskan_tidak_bisa_diputuskan_ulang(): void
    {
        $noc = $this->makeUserWithRole('noc');
        $this->actingAs($noc)->post(route('warehouse.pengajuan-barang.store'), [
            'pop_id' => $this->pop->id,
            'nama_kegiatan' => 'Penambahan POP Bandar',
            'lines' => $this->sampleLines(),
        ]);
        $pengajuan = PengajuanBarang::firstOrFail();

        $fop = $this->makeDesignatedFopApprover();
        $this->actingAs($fop)->post(route('warehouse.pengajuan-barang.approve', $pengajuan));

        $response = $this->actingAs($fop)->post(route('warehouse.pengajuan-barang.reject', $pengajuan), [
            'reason' => 'Coba tolak setelah disetujui',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(PengajuanBarangStatus::DISETUJUI, $pengajuan->refresh()->status);
    }

    #[Test]
    public function pop_admin_di_luar_scope_tidak_bisa_lihat_pengajuan_cabang_lain(): void
    {
        $noc = $this->makeUserWithRole('noc');
        $this->actingAs($noc)->post(route('warehouse.pengajuan-barang.store'), [
            'pop_id' => $this->pop->id,
            'nama_kegiatan' => 'Penambahan POP Bandar',
            'lines' => $this->sampleLines(),
        ]);
        $pengajuan = PengajuanBarang::firstOrFail();

        $popAdminLain = $this->makeUserWithRole('pop_admin', $this->popLain);

        $response = $this->actingAs($popAdminLain)->get(route('warehouse.pengajuan-barang.show', $pengajuan));

        $response->assertForbidden();
    }

    #[Test]
    public function pdf_bisa_diakses_dan_merender_link_keterangan(): void
    {
        $noc = $this->makeUserWithRole('noc');
        $this->actingAs($noc)->post(route('warehouse.pengajuan-barang.store'), [
            'pop_id' => $this->pop->id,
            'nama_kegiatan' => 'Penambahan POP Bandar',
            'lines' => $this->sampleLines(),
        ]);
        $pengajuan = PengajuanBarang::firstOrFail();

        $response = $this->actingAs($noc)->get(route('warehouse.pengajuan-barang.pdf', $pengajuan));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }
}
