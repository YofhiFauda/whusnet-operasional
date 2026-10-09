<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD master wilayah. Yang dijaga: nama unik di induknya, induk tidak bisa
 * diubah, dan hapus DITOLAK selama wilayah masih dipakai (pelanggan di
 * dalamnya atau wilayah anak). Wilayah tidak punya status, jadi tidak ada
 * jalur "nonaktifkan".
 */
class RegionMasterCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_tambah_kota_berhasil_dan_nama_harus_unik(): void
    {
        $this->loginAsAdmin();

        $this->post(route('master.wilayah.store'), ['level' => 'kota', 'name' => 'Ponorogo'])
            ->assertRedirect(route('master.wilayah.kelola', ['level' => 'kota']));
        $this->assertDatabaseHas('cities', ['name' => 'Ponorogo']);

        $this->post(route('master.wilayah.store'), ['level' => 'kota', 'name' => 'Ponorogo'])
            ->assertSessionHasErrors('name');
    }

    public function test_nama_kecamatan_unik_hanya_di_dalam_satu_kota(): void
    {
        $this->loginAsAdmin();
        $ponorogo = City::create(['name' => 'Ponorogo']);
        $madiun = City::create(['name' => 'Madiun']);
        District::create(['city_id' => $ponorogo->id, 'name' => 'Sukorejo']);

        $this->post(route('master.wilayah.store'), ['level' => 'kecamatan', 'city_id' => $ponorogo->id, 'name' => 'Sukorejo'])
            ->assertSessionHasErrors('name');

        $this->post(route('master.wilayah.store'), ['level' => 'kecamatan', 'city_id' => $madiun->id, 'name' => 'Sukorejo'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('districts', 2);
    }

    public function test_tambah_desa_dengan_kode_pos(): void
    {
        $this->loginAsAdmin();
        $kota = City::create(['name' => 'Ponorogo']);
        $kecamatan = District::create(['city_id' => $kota->id, 'name' => 'Jetis']);

        $this->post(route('master.wilayah.store'), [
            'level' => 'desa',
            'district_id' => $kecamatan->id,
            'name' => 'Jetis Lor',
            'postal_code' => '63491',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('villages', ['district_id' => $kecamatan->id, 'name' => 'Jetis Lor', 'postal_code' => '63491']);
    }

    public function test_ubah_nama_kota_dan_induk_kecamatan_terkunci(): void
    {
        $this->loginAsAdmin();
        $kota = City::create(['name' => 'Ponorogo']);
        $lainnya = City::create(['name' => 'Madiun']);
        $kecamatan = District::create(['city_id' => $kota->id, 'name' => 'Jetis']);

        $this->put(route('master.wilayah.update', ['level' => 'kota', 'id' => $kota->id]), ['name' => 'Ponorogo Kab'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cities', ['id' => $kota->id, 'name' => 'Ponorogo Kab']);

        // Induk dikirim ulang pun tidak mengubah kecamatan.
        $this->put(route('master.wilayah.update', ['level' => 'kecamatan', 'id' => $kecamatan->id]), [
            'name' => 'Jetis',
            'city_id' => $lainnya->id,
        ]);
        $this->assertDatabaseHas('districts', ['id' => $kecamatan->id, 'city_id' => $kota->id]);
    }

    public function test_hapus_kota_yang_masih_punya_kecamatan_ditolak(): void
    {
        $this->loginAsAdmin();
        $kota = City::create(['name' => 'Ponorogo']);
        District::create(['city_id' => $kota->id, 'name' => 'Jetis']);

        $this->delete(route('master.wilayah.destroy', ['level' => 'kota', 'id' => $kota->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('cities', ['id' => $kota->id]);
    }

    public function test_hapus_desa_yang_dipakai_pelanggan_ditolak(): void
    {
        $this->loginAsAdmin();
        $pop = Pop::factory()->create();
        $kota = City::create(['name' => 'Ponorogo']);
        $kecamatan = District::create(['city_id' => $kota->id, 'name' => 'Jetis']);
        $desa = Village::create(['district_id' => $kecamatan->id, 'name' => 'Jetis Lor']);

        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $customer->forceFill(['city_id' => $kota->id, 'district_id' => $kecamatan->id, 'village_id' => $desa->id])->save();

        $this->delete(route('master.wilayah.destroy', ['level' => 'desa', 'id' => $desa->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('villages', ['id' => $desa->id]);
    }

    public function test_hapus_desa_kosong_berhasil(): void
    {
        $this->loginAsAdmin();
        $kota = City::create(['name' => 'Ponorogo']);
        $kecamatan = District::create(['city_id' => $kota->id, 'name' => 'Jetis']);
        $desa = Village::create(['district_id' => $kecamatan->id, 'name' => 'Jetis Lor']);

        $this->delete(route('master.wilayah.destroy', ['level' => 'desa', 'id' => $desa->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('villages', ['id' => $desa->id]);
    }

    public function test_teknisi_tidak_bisa_menambah_wilayah(): void
    {
        $teknisi = User::factory()->create([
            'role_id' => Role::where('code', 'teknisi')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $this->actingAs($teknisi)->get(route('master.wilayah.create', ['level' => 'kota']))->assertForbidden();
    }
}
