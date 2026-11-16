<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bug 2026-11-16: tombol "Upload Dokumen Baru" + modalnya di Detail Pelanggan
 * digerbangi `upload_customer_documents` (PermissionSeeder lama, di luar Role
 * Matrix), padahal route customers.documents.store digerbangi
 * `customers.detail.documents.upload`. Role yang diberi Upload Dokumen di
 * matrix (mis. sales) malah gak lihat tombolnya.
 */
class TombolUploadDokumenIkutPermissionRoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $pop = Pop::create([
            'code' => 'SMN-EDTBTN',
            'pop_code' => 'EDB',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Tombol Edit',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'customer_code' => 'EDB-'.rand(10000, 99999),
            'full_name' => 'Pelanggan Tombol Edit',
            'primary_phone' => '081234567890',
            'status' => 'active',
            'pop_id' => $pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    /**
     * Attach langsung (bukan syncPermissions) supaya auto-grant chain tidak
     * menambah permission yang sedang diuji ketiadaannya.
     */
    private function userDengan(string ...$kode): User
    {
        $role = Role::create([
            'code' => 'tombol_upload_test',
            'name' => 'Tombol Upload Test',
            'is_system' => false,
        ]);
        $role->permissions()->attach(Permission::whereIn('code', $kode)->pluck('id'));

        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $user->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }

    #[Test]
    public function role_matrix_dengan_upload_dokumen_melihat_tombol_dan_modal_upload(): void
    {
        $user = $this->userDengan('customers.detail.view', 'customers.detail.documents.view', 'customers.detail.documents.upload');

        $response = $this->actingAs($user)->get(route('customers.show', $this->customer));

        $response->assertOk();
        $response->assertSee('Upload Dokumen Baru');
        $response->assertSee('action="'.route('customers.documents.store', ['customer' => $this->customer->id]).'"', false);
    }

    #[Test]
    public function role_matrix_tanpa_upload_dokumen_tidak_melihat_tombol_dan_modal_upload(): void
    {
        $user = $this->userDengan('customers.detail.view', 'customers.detail.documents.view');

        $response = $this->actingAs($user)->get(route('customers.show', $this->customer));

        $response->assertOk();
        $response->assertDontSee('Upload Dokumen Baru');
        $response->assertDontSee('action="'.route('customers.documents.store', ['customer' => $this->customer->id]).'"', false);
    }
}
