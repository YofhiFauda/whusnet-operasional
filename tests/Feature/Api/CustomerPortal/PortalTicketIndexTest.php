<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Ticket;
use App\Models\TicketIssueCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * `GET /me/tickets` (docs/api/api-portal-pelanggan/business-logic.md §4).
 */
class PortalTicketIndexTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    private function seedTicketFor(Customer $customer, array $overrides = []): Ticket
    {
        $staff = User::factory()->create();

        return Ticket::create(array_merge([
            'ticket_number' => 'TKT-IDX-'.random_int(100000, 999999),
            'type' => 'MTN',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'detail_keluhan' => 'Internet mati total.',
            'catatan_teknis' => 'Redaman -28 dBm, indikasi FO putus.',
            'priority' => 'High',
            'created_by' => $staff->id,
            'customer_latitude' => -7.123456,
            'customer_longitude' => 111.123456,
            'customer_device' => 'ONT ZTE F609',
        ], $overrides));
    }

    public function test_daftar_tiket_hanya_milik_pelanggan_sendiri(): void
    {
        $seedA = $this->seedActivePortalCustomer();
        $seedB = $this->seedActivePortalCustomer();
        $this->seedTicketFor($seedA['customer']);
        $this->seedTicketFor($seedB['customer']);

        $tokensA = $this->loginAndGetTokens($seedA['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokensA['access_token']))
            ->getJson('/api/customer-portal/me/tickets');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_kolom_haram_tidak_muncul(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $this->seedTicketFor($seed['customer']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/me/tickets');

        $item = $response->json('data.0');
        // 'status' SENGAJA gak masuk daftar ini — Resource memang punya key
        // 'status', tapi isinya objek {value,label} hasil TicketPortalStatusPresenter,
        // bukan kolom mentah. Yang haram itu `handler` mentah, dicek terpisah.
        foreach ([
            'id', 'catatan_teknis', 'detail_keluhan', 'handler', 'fop_task_id', 'pop_id',
            'created_by', 'priority', 'type', 'customer_name', 'customer_address',
            'customer_phone', 'customer_odp', 'customer_package', 'customer_device',
            'customer_latitude', 'customer_longitude',
        ] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $item);
        }
    }

    public function test_issue_category_tampil_sebagai_nama_bukan_id(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $category = TicketIssueCategory::create(['name' => 'Internet Lambat', 'default_priority' => 'Medium']);
        $this->seedTicketFor($seed['customer'], ['issue_category_id' => $category->id]);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/me/tickets');

        $this->assertSame('Internet Lambat', $response->json('data.0.issue_category'));
        $this->assertArrayNotHasKey('issue_category_id', $response->json('data.0'));
    }

    public function test_status_selalu_berbentuk_value_dan_label(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $this->seedTicketFor($seed['customer']);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/me/tickets');

        $response->assertJsonPath('data.0.status.value', 'diterima');
        $response->assertJsonPath('data.0.status.label', 'Diterima');
    }

    /**
     * Regresi: `index()` sempat gak eager-load `fopTask`, padahal
     * `Ticket::resolveStatus()` (dipanggil `TicketPortalStatusPresenter`)
     * mengaksesnya begitu `handler` = FOP — meledak
     * `LazyLoadingViolationException` (500) di halaman Tiket Portal Pelanggan.
     */
    public function test_daftar_tiket_yang_sudah_sampai_fop_tidak_meledak_lazy_loading(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $fopTask = FopTask::create([
            'task_number' => 'TFOP-IDX-'.random_int(100000, 999999),
            'category' => 'MTN',
            'tugas' => 'Uji lazy loading index',
            'issue' => 'Lemot',
            'status' => 'draft',
        ]);
        $this->seedTicketFor($seed['customer'], ['handler' => 'fop', 'fop_task_id' => $fopTask->id]);

        $tokens = $this->loginAndGetTokens($seed['login_id']);
        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/me/tickets');

        $response->assertOk();
    }
}
