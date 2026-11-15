<?php

namespace Tests\Feature\QrCode;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\CustomerQrTokenService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\QrFeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Cetak QR" (revisi user 2026-11-15) — stiker tempel modem berisi QR +
 * CID saja. Gejala yang dijaga: PIN/Login ID bocor ke stiker yang
 * terpajang permanen di rumah pelanggan.
 */
class CetakQrStikerModemTanpaPinTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private Customer $customer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['qr.secret' => 'label-secret']);

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(QrFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'LBL-A', 'pop_code' => 'LBA', 'registration_prefix' => 'C', 'cid_prefix' => 'D',
            'name' => 'POP Label', 'type' => 'cabang', 'status' => 'active',
        ]);
        $this->customer = Customer::factory()->create(['pop_id' => $this->pop->id, 'customer_code' => 'RQ888001']);

        $role = Role::where('code', 'admin')->firstOrFail();
        $this->admin = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        UserRoleScope::create(['user_id' => $this->admin->id, 'role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP]);
    }

    #[Test]
    public function stiker_modem_berisi_qr_dan_display_id_tanpa_pin_dan_login_id(): void
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer, $this->admin);
        $plainPin = $service->issuePin($token, $this->admin);

        $response = $this->actingAs($this->admin)->get(route('customers.qr.print-label', $this->customer));

        $response->assertOk();
        $response->assertSee('data:image', false);
        $response->assertSee($this->customer->fresh()->display_id);
        $response->assertDontSee($plainPin);
        $response->assertDontSee('Login ID');
        $response->assertDontSee('PIN Aktivasi');
    }

    #[Test]
    public function tombol_cetak_qr_muncul_di_samping_qr_kirim_ke_pelanggan(): void
    {
        app(CustomerQrTokenService::class)->issue($this->customer, $this->admin);

        $response = $this->actingAs($this->admin)->get(route('customers.qr.show', $this->customer));

        $response->assertOk();
        $response->assertSeeInOrder(['QR Kirim ke Pelanggan', 'Cetak QR']);
        $response->assertSee(route('customers.qr.print-label', $this->customer), false);
    }

    #[Test]
    public function stiker_modem_404_kalau_token_belum_terbit(): void
    {
        $this->actingAs($this->admin)
            ->get(route('customers.qr.print-label', $this->customer))
            ->assertNotFound();
    }

    #[Test]
    public function stiker_modem_403_untuk_pelanggan_di_luar_pop_scope(): void
    {
        app(CustomerQrTokenService::class)->issue($this->customer, $this->admin);

        $otherPop = Pop::create([
            'code' => 'LBL-B', 'pop_code' => 'LBB', 'registration_prefix' => 'E', 'cid_prefix' => 'F',
            'name' => 'POP Lain', 'type' => 'cabang', 'status' => 'active',
        ]);
        $role = Role::where('code', 'admin')->firstOrFail();
        $scopedUser = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $scopedUser->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        $scope->targets()->create(['pop_id' => $otherPop->id]);

        $this->actingAs($scopedUser)
            ->get(route('customers.qr.print-label', $this->customer))
            ->assertForbidden();
    }
}
