<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Upload Foto Rumah di Registrasi (`foto_rumah`) opsional untuk SEMUA
 * paket & SEMUA actor — sebelumnya field ini cuma dirender di dalam blok
 * `@can('customers.registration.skip_survey')`, jadi actor tanpa permission
 * itu (mis. role `admin`, cuma role `sales` yang dapat default) sama sekali
 * gak punya cara upload Foto Rumah walau aturannya "opsional", bukan
 * "gak ada". Field-nya sekarang dirender selalu; jadi wajib (asterisk +
 * required_if) cuma kalau Skip Survey aktif. Lihat
 * resources/views/customers/create.blade.php & CustomerRegistrationRequest.
 */
class CustomerRegistrationFotoRumahVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_foto_rumah_upload_visible_even_without_skip_survey_permission(): void
    {
        $role = Role::where('code', 'admin')->firstOrFail();
        $admin = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);

        $this->assertFalse(
            $admin->hasPermission('customers.registration.skip_survey'),
            'Setup test keliru: role admin semestinya tidak dapat permission customers.registration.skip_survey secara default.'
        );

        $this->actingAs($admin);

        $response = $this->get(route('customers.create'));

        $response->assertOk();
        $response->assertSee('name="foto_rumah"', false);
        $response->assertSee('Pilih Foto Rumah');
        // Blok Skip Survey sendiri tetap gak boleh muncul buat role ini.
        $response->assertDontSee('Skip Survey — Input Data Survey Langsung');
    }

    public function test_foto_rumah_upload_still_visible_for_sales_with_skip_survey_permission(): void
    {
        $role = Role::where('code', 'sales')->firstOrFail();
        $sales = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);
        $this->actingAs($sales);

        $response = $this->get(route('customers.create'));

        $response->assertOk();
        $response->assertSee('name="foto_rumah"', false);
        $response->assertSee('Skip Survey — Input Data Survey Langsung');
    }
}
