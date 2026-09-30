<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsPindahPopScenario;
use Tests\TestCase;

/**
 * Celah ADHOC-107 T2: modal "Atur Mini POP & Distribusi" mensyaratkan
 * `customers.detail.installation.validate`, tapi Edit Pelanggan (cukup
 * `customers.update`) bisa memilih Mini POP/Distribusi — dan dengan itu CID —
 * saat pindah Cabang. Gerbangnya sekarang sama (R2): tanpa izin atur jaringan,
 * user tetap boleh pindah Cabang, tapi Mini POP & Distribusi cuma dilepas;
 * yang baru diatur pemegang izin lewat modal.
 */
class CustomerEditJaringanButuhIzinValidasiTest extends TestCase
{
    use BuildsPindahPopScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->setUpPindahPop();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function admin(string $code, array $permissions): User
    {
        $user = $this->userBerScope($code, [$this->jetis->id, $this->sandya->id]);
        $user->role->permissions()->attach(Permission::whereIn('code', $permissions)->pluck('id'));
        app(EffectiveAccessService::class)->clearCache($user);

        return $user->fresh();
    }

    #[Test]
    public function tanpa_izin_atur_jaringan_dropdown_dikunci_dengan_alasan(): void
    {
        $user = $this->admin('uji_tanpa_izin_form', ['customers.update']);
        $this->assertFalse($user->hasPermission('customers.detail.installation.validate'));
        $customer = $this->pelangganAktifDiJetis();

        $this->actingAs($user)
            ->get(route('customers.edit', $customer->id))
            ->assertOk()
            ->assertSee('id="mini_pop_id" disabled', false)
            ->assertSee('berhak mengatur jaringan pelanggan');
    }

    #[Test]
    public function tanpa_izin_atur_jaringan_tidak_bisa_memilih_mini_pop_lewat_put_manual(): void
    {
        $user = $this->admin('uji_tanpa_izin_put', ['customers.update']);
        $customer = $this->pelangganAktifDiJetis();

        $this->actingAs($user)
            ->put(route('customers.update', $customer->id), $this->payloadEdit($customer, [
                'pop_id' => $this->sandya->id,
                'mini_pop_id' => $this->miniSandya->id,
                'distribution_id' => $this->distSandya->id,
            ]))->assertSessionHasErrors(['mini_pop_id', 'distribution_id']);

        $customer->refresh();
        $this->assertSame($this->jetis->id, (int) $customer->pop_id);
        $this->assertSame('C1X4ARQ000631', $customer->cid);
    }

    #[Test]
    public function tanpa_izin_atur_jaringan_tetap_boleh_pindah_cabang_dan_jaringan_lama_dilepas(): void
    {
        $user = $this->admin('uji_tanpa_izin_pindah', ['customers.update']);
        $customer = $this->pelangganAktifDiJetis();

        $this->actingAs($user)
            ->put(route('customers.update', $customer->id), $this->payloadEdit($customer, [
                'pop_id' => $this->sandya->id,
                'mini_pop_id' => null,
                'distribution_id' => null,
            ]))->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame($this->sandya->id, (int) $customer->pop_id);
        $this->assertNull($customer->mini_pop_id);
        $this->assertNull($customer->distribution_id);
        $this->assertSame('D00RQ000631', $customer->cid);
    }

    #[Test]
    public function pemegang_izin_atur_jaringan_bisa_memilih_mini_pop_dan_distribusi_saat_pindah(): void
    {
        $user = $this->admin('uji_dengan_izin', ['customers.update', 'customers.detail.installation.validate']);
        $customer = $this->pelangganAktifDiJetis();

        $this->actingAs($user)
            ->get(route('customers.edit', $customer->id))
            ->assertOk()
            ->assertDontSee('id="mini_pop_id" disabled', false);

        $this->actingAs($user)
            ->put(route('customers.update', $customer->id), $this->payloadEdit($customer, [
                'pop_id' => $this->sandya->id,
                'mini_pop_id' => $this->miniSandya->id,
                'distribution_id' => $this->distSandya->id,
            ]))->assertSessionHasNoErrors();

        $this->assertSame('D2Y6BRQ000631', $customer->fresh()->cid);
    }
}
