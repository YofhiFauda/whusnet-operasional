<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use App\Models\User;
use App\Models\UserTablePreference;
use App\Services\CustomerListColumnService;
use App\Support\CustomerListColumns;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kolom List Pelanggan bisa dipilih + diurutkan per user (docs/plan/
 * rancangan-kolom-list-pelanggan.md). Preferensi disimpan di DB, bukan
 * localStorage, dan hanya berlaku di List Pelanggan utama — Survey/Verifikasi
 * tetap kolom tetap (K3).
 */
class CustomerListColumnPreferenceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CustomerListColumnService
    {
        return app(CustomerListColumnService::class);
    }

    private function seedPop(): Pop
    {
        return Pop::firstOrCreate(['pop_code' => 'C'], [
            'code' => 'C', 'name' => 'Jetis', 'type' => 'cabang', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'C',
        ]);
    }

    public function test_tanpa_preferensi_user_dapat_kolom_default_sesuai_katalog(): void
    {
        $user = User::factory()->create();

        $this->assertSame(
            CustomerListColumns::defaultKeys(),
            array_keys($this->service()->columnsFor($user)),
        );
        $this->assertSame(
            ['cid', 'nama', 'pop_desa', 'paket', 'telepon', 'jatuh_tempo', 'tagihan', 'berkas', 'status'],
            CustomerListColumns::defaultKeys(),
        );
    }

    public function test_simpan_preferensi_mengikuti_urutan_dan_pilihan_kolom(): void
    {
        $user = User::factory()->create();

        $this->service()->save($user, ['nama', 'email', 'cid']);

        $this->assertSame(['nama', 'email', 'cid'], array_keys($this->service()->columnsFor($user)));
    }

    public function test_preferensi_satu_user_tidak_memengaruhi_user_lain(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->service()->save($a, ['nik']);

        $this->assertSame(['nik'], array_keys($this->service()->columnsFor($a)));
        $this->assertSame(CustomerListColumns::defaultKeys(), array_keys($this->service()->columnsFor($b)));
    }

    public function test_simpan_dua_kali_memperbarui_satu_baris_saja(): void
    {
        $user = User::factory()->create();

        $this->service()->save($user, ['cid']);
        $this->service()->save($user, ['nama', 'cid']);

        $this->assertSame(1, UserTablePreference::where('user_id', $user->id)->count());
        $this->assertSame(['nama', 'cid'], array_keys($this->service()->columnsFor($user)));
    }

    public function test_kunci_usang_di_preferensi_diabaikan_dan_semua_usang_jatuh_ke_default(): void
    {
        $user = User::factory()->create();

        UserTablePreference::create([
            'user_id' => $user->id,
            'table_key' => CustomerListColumns::TABLE_KEY,
            'columns' => ['kolom_yang_sudah_dihapus', 'nama'],
        ]);
        $this->assertSame(['nama'], array_keys($this->service()->columnsFor($user)));

        UserTablePreference::where('user_id', $user->id)->update(['columns' => ['hantu']]);
        $this->assertSame(CustomerListColumns::defaultKeys(), array_keys($this->service()->columnsFor($user)));
    }

    public function test_reset_menghapus_preferensi_kembali_ke_default(): void
    {
        $user = User::factory()->create();
        $this->service()->save($user, ['cid']);

        $this->service()->reset($user);

        $this->assertDatabaseMissing('user_table_preferences', ['user_id' => $user->id]);
        $this->assertSame(CustomerListColumns::defaultKeys(), array_keys($this->service()->columnsFor($user)));
    }

    public function test_endpoint_simpan_menyimpan_preferensi_user_yang_login(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->loginAsAdmin();

        $this->putJson(route('customers.list-columns.update'), ['columns' => ['nama', 'gender']])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(['nama', 'gender'], array_keys($this->service()->columnsFor($admin)));
    }

    public function test_endpoint_simpan_menolak_kunci_tak_dikenal_duplikat_dan_kosong(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $this->putJson(route('customers.list-columns.update'), ['columns' => ['nama', 'drop_table']])
            ->assertStatus(422)->assertJsonValidationErrors('columns.1');
        $this->putJson(route('customers.list-columns.update'), ['columns' => ['nama', 'nama']])
            ->assertStatus(422);
        $this->putJson(route('customers.list-columns.update'), ['columns' => []])
            ->assertStatus(422);
        $this->putJson(route('customers.list-columns.update'), [])
            ->assertStatus(422);
    }

    public function test_endpoint_reset_menghapus_preferensi(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->loginAsAdmin();
        $this->service()->save($admin, ['cid']);

        $this->deleteJson(route('customers.list-columns.destroy'))->assertOk();

        $this->assertDatabaseMissing('user_table_preferences', ['user_id' => $admin->id]);
    }

    public function test_endpoint_butuh_login(): void
    {
        $this->putJson(route('customers.list-columns.update'), ['columns' => ['nama']])
            ->assertStatus(401);
    }

    public function test_tabel_dirender_sesuai_kolom_pilihan_dan_aksi_selalu_terakhir(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->loginAsAdmin();
        $pop = $this->seedPop();
        Customer::factory()->create([
            'full_name' => 'Budi Santoso', 'status' => 'active', 'pop_id' => $pop->id,
            'email' => 'budi@contoh.test',
        ]);

        $this->service()->save($admin, ['email', 'nama']);

        $html = $this->get(route('customers.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-col="email".*data-col="nama".*data-col="aksi"/s', $html);
        $this->assertStringNotContainsString('data-col="cid"', $html);
        $this->assertStringContainsString('budi@contoh.test', $html);
    }

    public function test_survey_dan_verifikasi_tetap_kolom_tetap_tanpa_preferensi(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->loginAsAdmin();
        $this->service()->save($admin, ['email']);

        $html = $this->get(route('customers.index', ['status_group' => 'survey']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-col="cid"', $html);
        $this->assertStringContainsString('data-col="status"', $html);
        $this->assertStringNotContainsString('data-col="email"', $html);
    }

    public function test_panel_kolom_dan_target_simpan_dirender_server_hanya_di_list_utama(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $utama = $this->get(route('customers.index'))->assertOk()->getContent();
        $this->assertStringContainsString(route('customers.list-columns.update'), $utama);
        $this->assertStringContainsString('data-columns-save-url="'.route('customers.list-columns.update').'"', $utama);
        $this->assertMatchesRegularExpression('/<th data-col="cid"\s+draggable="true"/', $utama);

        $survey = $this->get(route('customers.index', ['status_group' => 'survey']))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('customers.list-columns.update'), $survey);
        $this->assertDoesNotMatchRegularExpression('/<th data-col="cid"\s+draggable="true"/', $survey);
    }
}
