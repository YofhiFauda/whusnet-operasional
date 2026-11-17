<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi: Admin Gudang Pusat (`admin_gudang`) cuma pegang permission modul
 * gudang — tanpa dashboard.view/customers.view/task.view.own. Redirect
 * default setelah login (`route('dashboard')` = `/`) belum punya fallback
 * untuk `warehouse.view`, jadi login sukses tapi langsung abort 403.
 * Ditemukan dari akun uji "TEST Gudang Pusat" (2026-11-17).
 */
class AdminGudangLoginRedirectsToWarehouseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function userWithRole(string $code): User
    {
        $role = Role::where('code', $code)->firstOrFail();

        return User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
    }

    #[Test]
    public function admin_gudang_mendarat_di_dashboard_gudang_bukan_403(): void
    {
        $adminGudang = $this->userWithRole('admin_gudang');

        // Kondisi persis penyebab 403: tanpa satu pun permission landing lama.
        $this->assertFalse($adminGudang->hasPermission('dashboard.view'));
        $this->assertFalse($adminGudang->hasPermission('task.view.own'));
        $this->assertFalse($adminGudang->hasPermission('customers.view'));
        $this->assertTrue($adminGudang->hasPermission('warehouse.view'));

        $response = $this->actingAs($adminGudang)->get(route('dashboard'));

        $response->assertRedirect(route('warehouse.index'));
    }

    #[Test]
    public function halaman_gudang_setelah_redirect_terbuka(): void
    {
        $adminGudang = $this->userWithRole('admin_gudang');

        $response = $this->actingAs($adminGudang)->get(route('dashboard'));
        $response->assertRedirect(route('warehouse.index'));

        $this->actingAs($adminGudang)
            ->get($response->headers->get('Location'))
            ->assertOk();
    }

    #[Test]
    public function teknisi_pic_gudang_tetap_mendarat_di_task_sendiri(): void
    {
        $picGudang = $this->userWithRole('pic_gudang');

        $this->assertTrue($picGudang->hasPermission('task.view.own'));
        $this->assertTrue($picGudang->hasPermission('warehouse.view'));

        $this->actingAs($picGudang)
            ->get(route('dashboard'))
            ->assertRedirect(route('tasks.own'));
    }
}
