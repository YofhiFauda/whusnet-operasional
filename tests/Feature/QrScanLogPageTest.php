<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\QrScanLog;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\QrFeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaskFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QrScanLogPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(TaskFeatureSeeder::class);
        $this->seed(QrFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function owner_atasan_dan_fop_memiliki_izin_log_qr_tetapi_sales_tidak(): void
    {
        foreach (['owner', 'atasan', 'fop'] as $roleCode) {
            $user = User::factory()->create(['role_id' => Role::where('code', $roleCode)->firstOrFail()->id]);
            $this->assertTrue($user->hasPermission('qr_scan_logs.view'), $roleCode);
        }

        $sales = User::factory()->create(['role_id' => Role::where('code', 'sales')->firstOrFail()->id]);
        $this->assertFalse($sales->hasPermission('qr_scan_logs.view'));
        $this->actingAs($user)->get(route('qr-scan-logs.index'))->assertSee('Riwayat Scan QR');
        $this->actingAs($sales)->get(route('qr-scan-logs.index'))->assertForbidden();
    }

    #[Test]
    public function fop_hanya_melihat_log_dan_filter_teknisi_dari_pop_scope_nya(): void
    {
        $allowedPop = Pop::create(['code' => 'QR-A', 'name' => 'POP QR A', 'type' => 'cabang', 'status' => 'active']);
        $otherPop = Pop::create(['code' => 'QR-B', 'name' => 'POP QR B', 'type' => 'cabang', 'status' => 'active']);
        $allowedCustomer = Customer::factory()->create(['pop_id' => $allowedPop->id]);
        $otherCustomer = Customer::factory()->create(['pop_id' => $otherPop->id]);
        $fopRole = Role::where('code', 'fop')->firstOrFail();
        $fop = User::factory()->create(['role_id' => $fopRole->id]);
        $scope = UserRoleScope::create([
            'user_id' => $fop->id,
            'role_id' => $fopRole->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $allowedPop->id]);

        $localTechnician = User::factory()->create(['name' => 'Teknisi Lokal']);
        $foreignTechnician = User::factory()->create(['name' => 'Teknisi Asing']);
        QrScanLog::create([
            'customer_id' => $allowedCustomer->id,
            'user_id' => $localTechnician->id,
            'purpose' => 'attendance',
            'result' => 'out_of_radius',
            'reason' => 'Jarak terlalu jauh',
            'latitude' => -7.870001,
            'longitude' => 111.460001,
            'scanned_at' => now(),
        ]);
        QrScanLog::create([
            'customer_id' => $otherCustomer->id,
            'user_id' => $foreignTechnician->id,
            'purpose' => 'attendance',
            'result' => 'bad_signature',
            'reason' => 'Log luar POP',
            'scanned_at' => now(),
        ]);

        $response = $this->actingAs($fop)->get(route('qr-scan-logs.index'));
        $response->assertOk();
        $response->assertSee('Teknisi Lokal');
        $response->assertSee('Jarak terlalu jauh');
        $response->assertSee('Lihat lokasi');
        $response->assertSee('https://www.google.com/maps?q=-7.8700010,111.4600010', false);
        $response->assertDontSee('Teknisi Asing');
        $response->assertDontSee('Log luar POP');
        $response->assertDontSee('bad_signature');
    }
}
