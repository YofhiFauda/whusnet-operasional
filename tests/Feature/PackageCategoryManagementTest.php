<?php

namespace Tests\Feature;

use App\Models\InternetPackage;
use App\Models\PackageCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\CustomerAcquisitionFeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Master Kategori Paket — CRUD penuh (index/create/store/edit/update/
 * destroy). Satu-satunya tempat admin memilih ROLE (nama biasa, bukan kode
 * permission mentah) yang wajib validasi Biaya Instalasi Busdev per
 * kategori.
 */
class PackageCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(CustomerAcquisitionFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_create_a_new_category(): void
    {
        $this->loginAsAdmin();
        $bdRole = Role::where('code', 'business_development')->firstOrFail();

        $response = $this->post(route('master.package-categories.store'), [
            'name' => 'Paket Bisnis Korporat',
            'is_active' => '1',
            'installation_fee_approval_role_id' => $bdRole->id,
        ]);

        $response->assertRedirect(route('master.package-categories.index'));
        $this->assertDatabaseHas('package_categories', [
            'name' => 'Paket Bisnis Korporat',
            'installation_fee_approval_role_id' => $bdRole->id,
        ]);
    }

    public function test_duplicate_category_name_is_rejected(): void
    {
        $this->loginAsAdmin();
        // "Paket Home Broadband" sudah ditanam migration `package_categories`
        // (4 kategori bawaan, ADHOC-63) — cukup pakai itu, gak perlu bikin lagi.
        $this->assertDatabaseHas('package_categories', ['name' => 'Paket Home Broadband']);

        $response = $this->post(route('master.package-categories.store'), [
            'name' => 'Paket Home Broadband',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_admin_can_assign_installation_fee_role_to_a_category(): void
    {
        $this->loginAsAdmin();
        $bdRole = Role::where('code', 'business_development')->firstOrFail();

        $category = PackageCategory::firstOrCreate(['name' => 'Paket Bisnis UKM']);

        $response = $this->put(route('master.package-categories.update', $category), [
            'name' => $category->name,
            'is_active' => '1',
            'installation_fee_approval_role_id' => $bdRole->id,
        ]);

        $response->assertRedirect(route('master.package-categories.index'));
        $this->assertSame($bdRole->id, $category->fresh()->installation_fee_approval_role_id);
    }

    public function test_admin_can_clear_the_role_back_to_no_special_validation(): void
    {
        $this->loginAsAdmin();

        // Sudah dapat default mapping dari CustomerAcquisitionFeatureSeeder.
        $category = PackageCategory::firstOrCreate(['name' => 'Paket Bisnis UKM']);
        $this->assertNotNull($category->installation_fee_approval_role_id);

        $response = $this->put(route('master.package-categories.update', $category), [
            'name' => $category->name,
            'is_active' => '1',
            'installation_fee_approval_role_id' => '',
        ]);

        $response->assertRedirect();
        $this->assertNull($category->fresh()->installation_fee_approval_role_id);
    }

    public function test_unknown_role_id_is_rejected(): void
    {
        $this->loginAsAdmin();

        $category = PackageCategory::firstOrCreate(['name' => 'Paket Bisnis UKM']);
        $before = $category->installation_fee_approval_role_id;

        $response = $this->put(route('master.package-categories.update', $category), [
            'name' => $category->name,
            'is_active' => '1',
            'installation_fee_approval_role_id' => 999999,
        ]);

        $response->assertSessionHasErrors('installation_fee_approval_role_id');
        $this->assertSame($before, $category->fresh()->installation_fee_approval_role_id);
    }

    public function test_role_without_packages_update_permission_is_forbidden(): void
    {
        $salesRole = Role::where('code', 'sales')->firstOrFail();
        $user = User::factory()->create(['status' => 'active', 'role_id' => $salesRole->id]);
        $this->actingAs($user);

        $category = PackageCategory::firstOrCreate(['name' => 'Paket Bisnis UKM']);

        $response = $this->put(route('master.package-categories.update', $category), [
            'name' => $category->name,
            'is_active' => '1',
        ]);

        $response->assertForbidden();
    }

    public function test_category_not_used_by_any_package_can_be_deleted(): void
    {
        $this->loginAsAdmin();
        $category = PackageCategory::factory()->create(['name' => 'Kategori Belum Dipakai']);

        $response = $this->delete(route('master.package-categories.destroy', $category));

        $response->assertRedirect(route('master.package-categories.index'));
        $this->assertDatabaseMissing('package_categories', ['id' => $category->id]);
    }

    public function test_category_used_by_a_package_cannot_be_deleted(): void
    {
        $this->loginAsAdmin();
        $category = PackageCategory::firstOrCreate(['name' => 'Paket Home Broadband']);
        InternetPackage::create([
            'package_code' => 'PKG-'.uniqid(),
            'name' => 'Home 20 Mbps',
            'category' => $category->name,
            'package_group' => $category->name,
            'bandwidth_label' => '20 Mbps',
            'monthly_price' => 200000,
            'is_active' => true,
        ]);

        $response = $this->delete(route('master.package-categories.destroy', $category));

        $response->assertRedirect(route('master.package-categories.index'));
        $this->assertDatabaseHas('package_categories', ['id' => $category->id]);
    }
}
