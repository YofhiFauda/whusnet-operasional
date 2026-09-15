<?php

namespace Tests\Feature;

use App\Models\PackageCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kategori paket sebagai master (`package_categories`), bukan lagi hardcode
 * di `InternetPackage::CATEGORIES`. Intinya: admin bisa nambah kategori dari
 * modal di form Master Paket tanpa deploy, dan paket baru bisa langsung
 * memakainya lewat validasi server.
 */
class PackageCategoryQuickCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_empat_kategori_bawaan_ditanam_migrasi(): void
    {
        // Ditanam migrasi, bukan seeder — lihat komentar migrasinya. Test ini
        // menjaga data existing (yang seedernya masih memakai empat nama ini)
        // tidak kehilangan padanan di master baru.
        $this->assertSame(4, PackageCategory::query()->count());

        foreach (['Paket Home Broadband', 'Paket Bisnis Broadband', 'Paket Bisnis UKM', 'Paket Bisnis Dedicated'] as $name) {
            $this->assertTrue(PackageCategory::where('name', $name)->exists(), "Kategori bawaan \"{$name}\" hilang.");
        }
    }

    public function test_kategori_baru_langsung_bisa_dipakai_membuat_paket(): void
    {
        $admin = $this->loginAsAdmin();

        // Inti fitur: tambah kategori lewat endpoint modal, tanpa ubah kode.
        $response = $this->actingAs($admin)
            ->postJson(route('master.paket.categories.store'), ['name' => 'Paket Home Fiber']);

        $response->assertCreated();
        $response->assertJsonPath('name', 'Paket Home Fiber');

        $this->assertTrue(PackageCategory::where('name', 'Paket Home Fiber')->exists());

        $store = $this->actingAs($admin)->post(route('master.paket.store'), [
            'package_code' => 'FBR100',
            'name' => 'Fiber 100',
            'category' => 'Paket Home Fiber',
            'package_group' => 'Reguler Broadband',
            'bandwidth_label' => '100 Mbps',
            'monthly_price' => '150000',
            'is_active' => 1,
        ]);

        $store->assertRedirect(route('master.paket.index'));
        $store->assertSessionHasNoErrors();
    }

    public function test_nama_kategori_wajib_dan_unik(): void
    {
        $admin = $this->loginAsAdmin();
        PackageCategory::create(['name' => 'Paket Home Broadband Duplikat']);

        $this->actingAs($admin)
            ->postJson(route('master.paket.categories.store'), ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->actingAs($admin)
            ->postJson(route('master.paket.categories.store'), ['name' => 'Paket Home Broadband Duplikat'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_kategori_yang_belum_terdaftar_ditolak_saat_membuat_paket(): void
    {
        $admin = $this->loginAsAdmin();

        $response = $this->actingAs($admin)->post(route('master.paket.store'), [
            'package_code' => 'FBR200',
            'name' => 'Fiber 200',
            'category' => 'Kategori Karangan',
            'package_group' => 'Reguler Broadband',
            'bandwidth_label' => '200 Mbps',
            'monthly_price' => '200000',
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors('category');
    }

    public function test_tambah_kategori_butuh_permission(): void
    {
        $this->seed(RoleSeeder::class);
        $teknisiRole = Role::where('name', 'Teknisi')->first();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);

        $this->actingAs($teknisi)
            ->postJson(route('master.paket.categories.store'), ['name' => 'Nekat'])
            ->assertForbidden();
    }
}
