<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerInstallation;
use App\Models\Pop;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gejala: halaman Antrean Verifikasi Lapangan cuma punya satu tampilan (tabel
 * 9 kolom) sehingga di layar HP harus di-scroll horizontal dua arah. Sekarang
 * halaman merender DUA tampilan sekaligus — tabel (≥lg) dan kartu (<lg) —
 * dari partial yang sama.
 *
 * Yang dijaga test ini:
 *  1. Kedua tampilan benar-benar ikut terender, bukan cuma salah satu.
 *  2. Tombol aksi muncul di keduanya (kartu bukan tampilan baca-saja).
 *  3. Id countdown kartu ber-prefix 'card-' — kalau bentrok dengan id tabel,
 *     querySelector berhenti di elemen tabel dan timer di HP diam selamanya.
 */
class VerificationQueueMobileCardViewTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->pop = Pop::create([
            'code' => 'SMN-QCARD',
            'pop_code' => 'QCD',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP Queue Card Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function makeCustomer(string $status, string $name): Customer
    {
        return Customer::create([
            'customer_code' => 'QCD-'.rand(10000, 99999),
            'full_name' => $name,
            'primary_phone' => '081234500001',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    public function test_queue_renders_both_table_row_and_mobile_card_for_each_customer(): void
    {
        $owner = $this->loginAsAdmin();
        $customer = $this->makeCustomer('waiting_installation', 'Pelanggan Dua Tampilan');

        $response = $this->actingAs($owner)->get(route('verifications.queue'));

        $response->assertOk();
        $response->assertSee('customer-row-'.$customer->id, false);
        $response->assertSee('customer-card-'.$customer->id, false);

        // Slot yang dipakai JS realtime buat menempel hasil refetch di kartu.
        foreach (['status', 'live', 'action'] as $part) {
            $response->assertSee('customer-'.$part.'-cell-'.$customer->id, false);
            $response->assertSee('customer-'.$part.'-card-'.$customer->id, false);
        }
    }

    public function test_action_button_rendered_in_both_table_and_card(): void
    {
        $owner = $this->loginAsAdmin();
        $this->makeCustomer('waiting_installation', 'Pelanggan Menunggu Pasang');

        $response = $this->actingAs($owner)->get(route('verifications.queue'));

        $response->assertOk();
        $this->assertSame(
            2,
            substr_count($response->getContent(), 'Start Proses'),
            'Tombol aksi harus ada di tampilan tabel DAN kartu.'
        );
    }

    public function test_mobile_card_countdown_id_does_not_collide_with_table_countdown_id(): void
    {
        $owner = $this->loginAsAdmin();
        $customer = $this->makeCustomer('installation_in_progress', 'Pelanggan Sedang Pasang');

        CustomerInstallation::create([
            'customer_id' => $customer->id,
            'started_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($owner)->get(route('verifications.queue'));

        $response->assertOk();
        $response->assertSee('id="countdown-'.$customer->id.'"', false);
        $response->assertSee('id="countdown-card-'.$customer->id.'"', false);
    }

    /**
     * Pemilihan tabel vs kartu harus diukur dari lebar PANEL, bukan viewport.
     * Dengan breakpoint viewport (lg:), laptop 1024 yang sidebar-nya expanded
     * cuma punya ~728px tapi tetap merender 8 kolom — tabel kepotong. Dan
     * karena collapse sidebar tidak mengubah viewport, media query tidak pernah
     * ikut bereaksi saat sidebar dibuka/ditutup.
     */
    public function test_queue_switches_view_by_container_width_not_viewport(): void
    {
        $owner = $this->loginAsAdmin();
        $this->makeCustomer('waiting_installation', 'Pelanggan Container Query');

        $response = $this->actingAs($owner)->get(route('verifications.queue'));

        $response->assertOk();
        $response->assertSee('@container', false);
        $response->assertSee('@min-[52rem]:block', false);
        $response->assertSee('@min-[52rem]:hidden', false);
    }

    public function test_row_fragment_endpoint_still_returns_table_cells_only(): void
    {
        $owner = $this->loginAsAdmin();
        $customer = $this->makeCustomer('waiting_installation', 'Pelanggan Fragment');

        $response = $this->actingAs($owner)->get(route('verifications.row', $customer));

        $response->assertOk();
        $response->assertSee('customer-status-cell-'.$customer->id, false);
        $response->assertDontSee('customer-card-'.$customer->id, false);
    }
}
