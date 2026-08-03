<?php

namespace Tests\Feature;

use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Footer Modal Hub punya dua versi: bar ikon 6 aksi untuk mobile/tablet (<lg)
 * dan deretan tombol berlabel untuk desktop (>=lg). Tombol berlabel penuh di
 * layar kecil makan dua baris dan memotong isi modal — footer ini shrink-0,
 * jadi ruang yang dia ambil langsung mengurangi area scroll.
 *
 * Tidak ada aksi yang disembunyikan di balik menu: keenam aksi (Detail, Edit,
 * WA, Cetak Struk, Isolir, Putus) tetap terlihat, dan tetap tunduk permission
 * yang sama dengan versi desktop.
 *
 * Baris "aksi cepat" (WA/Isolir/Cetak Struk) di tab Ringkasan sengaja hanya
 * tampil di >=lg (hidden lg:flex) — di layar sempit ketiga aksi itu sudah ada
 * di bar footer ini, menampilkan keduanya berarti tombol yang sama dobel.
 */
class CustomerHubModalFooterResponsiveTest extends TestCase
{
    use RefreshDatabase;

    private function seedPop(): Pop
    {
        return Pop::firstOrCreate(['pop_code' => 'FTR1'], [
            'code' => 'POP-FTR-1',
            'name' => 'POP Footer',
            'type' => 'cabang',
            'status' => 'active',
            'registration_prefix' => 'RQ',
            'cid_prefix' => 'C',
        ]);
    }

    public function test_footer_keeps_all_actions_visible_on_small_screens(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();
        $this->seedPop();

        $response = $this->get(route('customers.index'));

        $response->assertStatus(200);
        // Bar aksi <lg — keenam aksi ada, tidak ada yang dilipat ke dalam menu.
        $response->assertSee('id="btn-hub-footer-toggle-text"', false);
        $response->assertSee('onclick="focusWaTemplates()"', false);
        $response->assertSee('title="Cetak struk pembayaran terakhir"', false);
        $response->assertSee('title="Detail Full"', false);
        $response->assertSee('title="Edit Data Pelanggan"', false);
        $response->assertSee('title="Putus Langganan"', false);
        // Versi desktop tetap ada dan hanya muncul di >=lg.
        $response->assertSee('hidden lg:flex', false);
        // Quick action row (WA/Isolir/Struk) di tab Ringkasan disembunyikan di
        // <lg supaya tidak dobel dengan bar footer ini.
        $response->assertSee('hidden lg:flex lg:flex-wrap', false);
    }

    public function test_footer_actions_hidden_when_user_lacks_permission(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seedPop();

        // NOC boleh lihat list pelanggan, tapi tidak mengedit master data
        // maupun memutus langganan.
        $role = Role::where('code', 'noc')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $response = $this->actingAs($user)->get(route('customers.index'));

        $response->assertStatus(200);
        $response->assertSee('onclick="focusWaTemplates()"', false);
        $response->assertDontSee('onclick="triggerEdit()"', false);
        $response->assertDontSee('onclick="triggerTerminate()"', false);
    }
}
