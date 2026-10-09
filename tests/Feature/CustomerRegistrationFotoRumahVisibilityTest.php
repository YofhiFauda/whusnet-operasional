<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Foto Rumah di Registrasi (`foto_rumah`) HANYA ada di blok Skip Survey dan
 * wajib di sana. Jalur registrasi biasa tidak merender field-nya, dan upload
 * yang nyasar ditolak server-side (`prohibited_unless:skip_survey,1`).
 * Lihat resources/views/customers/create.blade.php & CustomerRegistrationRequest.
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

    public function test_foto_rumah_hidden_from_registration_without_skip_survey_permission(): void
    {
        $role = Role::where('code', 'helpdesk')->firstOrFail();
        $user = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);
        $this->giveAllPopScope($user);

        $this->assertFalse(
            $user->hasPermission('customers.registration.skip_survey'),
            'Setup test keliru: role helpdesk semestinya tidak dapat permission customers.registration.skip_survey secara default.'
        );

        $this->actingAs($user);

        $response = $this->get(route('customers.create'));

        $response->assertOk();
        // Foto Rumah cuma ada di blok Skip Survey — jalur registrasi biasa tidak punya field-nya.
        $response->assertDontSee('name="foto_rumah"', false);
        $response->assertDontSee('Pilih Foto Rumah');
        $response->assertDontSee('Skip Survey — Input Data Survey Langsung');
    }

    public function test_foto_rumah_rejected_on_regular_registration(): void
    {
        $role = Role::where('code', 'sales')->firstOrFail();
        $sales = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);
        $this->giveAllPopScope($sales);
        $this->actingAs($sales);

        $response = $this->post(route('customers.store'), [
            'foto_rumah' => UploadedFile::fake()->image('rumah.jpg'),
        ]);

        $response->assertSessionHasErrors('foto_rumah');
    }

    public function test_foto_rumah_upload_still_visible_for_sales_with_skip_survey_permission(): void
    {
        $role = Role::where('code', 'sales')->firstOrFail();
        $sales = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);
        $this->giveAllPopScope($sales);
        $this->actingAs($sales);

        $response = $this->get(route('customers.create'));

        $response->assertOk();
        $response->assertSee('name="foto_rumah"', false);
        $response->assertSee('Skip Survey — Input Data Survey Langsung');
        // accept harus image/* (bukan daftar MIME+ekstensi) supaya pilihan kamera muncul di HP.
        $response->assertSee('name="foto_rumah" id="foto_rumah" accept="image/*"', false);
    }
}
