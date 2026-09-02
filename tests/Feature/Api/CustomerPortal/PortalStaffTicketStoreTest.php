<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\Role;
use App\Models\StaffPortalToken;
use App\Models\TicketIssueCategory;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\QrFeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * `POST /api/customer-portal/tickets` (docs/plan/qr-code/
 * analisa-unifikasi-qr-staff-portal.md §2) — create tiket dari staf yang
 * masuk lewat token `StaffPortalToken` (one-shot, purpose `tickets`),
 * bukan lagi `qr.ticket.create` internal.
 */
class PortalStaffTicketStoreTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(TicketFeatureSeeder::class);
        $this->seed(QrFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function staffWithTicketQrPermission(): User
    {
        $role = Role::where('code', 'helpdesk')->firstOrFail();
        $staff = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $staff->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP,
        ]);

        return $staff;
    }

    private function issueTokenFor(User $staff, Customer $customer, string $purpose = 'tickets'): string
    {
        return StaffPortalToken::issue($staff->id, $customer->id, $purpose, 15, '127.0.0.1')['plaintext'];
    }

    private function postTicket(string $plaintext, array $payload)
    {
        return $this->withHeaders(array_merge($this->portalClientHeaders(), [
            'Authorization' => "Bearer {$plaintext}",
        ]))->postJson('/api/customer-portal/tickets', $payload);
    }

    #[Test]
    public function staf_dengan_token_valid_berhasil_bikin_tiket(): void
    {
        $staff = $this->staffWithTicketQrPermission();
        $pop = $this->seedPop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $plaintext = $this->issueTokenFor($staff, $customer);

        $response = $this->postTicket($plaintext, [
            'type' => 'MTN',
            'detail_keluhan' => 'Internet mati total.',
            'priority' => 'Medium',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('tickets', [
            'customer_id' => $customer->id,
            'created_by' => $staff->id,
        ]);

        // Konsumsi setelah sukses — token tidak bisa dipakai submit dua kali.
        $this->assertNotNull(StaffPortalToken::first()->consumed_at);
    }

    #[Test]
    public function token_yang_sudah_dikonsumsi_ditolak_401(): void
    {
        $staff = $this->staffWithTicketQrPermission();
        $pop = $this->seedPop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $plaintext = $this->issueTokenFor($staff, $customer);

        $this->postTicket($plaintext, [
            'type' => 'MTN',
            'detail_keluhan' => 'Internet mati total.',
            'priority' => 'Medium',
        ])->assertCreated();

        $second = $this->postTicket($plaintext, [
            'type' => 'MTN',
            'detail_keluhan' => 'Coba lagi.',
            'priority' => 'Medium',
        ]);

        $second->assertUnauthorized();
    }

    #[Test]
    public function token_purpose_kolektor_ditolak_di_endpoint_tiket(): void
    {
        $staff = $this->staffWithTicketQrPermission();
        $pop = $this->seedPop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $plaintext = $this->issueTokenFor($staff, $customer, purpose: 'kolektor');

        $response = $this->postTicket($plaintext, [
            'type' => 'MTN',
            'detail_keluhan' => 'Internet mati total.',
            'priority' => 'Medium',
        ]);

        $response->assertUnauthorized();
    }

    #[Test]
    public function tiket_terbuka_yang_masih_ada_ditolak_409_tanpa_konfirmasi(): void
    {
        $staff = $this->staffWithTicketQrPermission();
        $pop = $this->seedPop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);

        $firstToken = $this->issueTokenFor($staff, $customer);
        $this->postTicket($firstToken, [
            'type' => 'MTN',
            'detail_keluhan' => 'Internet mati total.',
            'priority' => 'Medium',
        ])->assertCreated();

        $secondToken = $this->issueTokenFor($staff, $customer);
        $response = $this->postTicket($secondToken, [
            'type' => 'MTN',
            'detail_keluhan' => 'Internet mati lagi, kejadian kedua.',
            'priority' => 'Medium',
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseCount('tickets', 1);
        // Ditolak dedup guard — token TIDAK ikut terkonsumsi, staf bisa
        // submit ulang dengan confirmed_duplicate=true pakai token yang sama.
        $this->assertNull(StaffPortalToken::where('token_hash', hash('sha256', $secondToken))->first()->consumed_at);
    }

    #[Test]
    public function tiket_terbuka_dengan_confirmed_duplicate_tetap_dibuat(): void
    {
        $staff = $this->staffWithTicketQrPermission();
        $pop = $this->seedPop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);

        $firstToken = $this->issueTokenFor($staff, $customer);
        $this->postTicket($firstToken, [
            'type' => 'MTN',
            'detail_keluhan' => 'Internet mati total.',
            'priority' => 'Medium',
        ])->assertCreated();

        $secondToken = $this->issueTokenFor($staff, $customer);
        $response = $this->postTicket($secondToken, [
            'type' => 'MTN',
            'detail_keluhan' => 'Internet mati lagi, kejadian kedua — dikonfirmasi tetap baru.',
            'priority' => 'Medium',
            'confirmed_duplicate' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('tickets', 2);
    }

    #[Test]
    public function staf_di_luar_pop_scope_pelanggan_ditolak_403(): void
    {
        $staff = $this->staffWithTicketQrPermission();
        // Persempit scope staf ke POP lain — customer di POP berbeda.
        UserRoleScope::query()->where('user_id', $staff->id)->update(['scope_type' => ScopeType::SELECTED_POP]);
        $scope = UserRoleScope::where('user_id', $staff->id)->firstOrFail();
        $popLain = $this->seedPop('LNQ');
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $popLain->id]);

        $pop = $this->seedPop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $plaintext = $this->issueTokenFor($staff, $customer);

        $response = $this->postTicket($plaintext, [
            'type' => 'MTN',
            'detail_keluhan' => 'Internet mati total.',
            'priority' => 'Medium',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function tanpa_bearer_token_401(): void
    {
        $response = $this->withHeaders($this->portalClientHeaders())
            ->postJson('/api/customer-portal/tickets', [
                'type' => 'MTN',
                'detail_keluhan' => 'Internet mati total.',
                'priority' => 'Medium',
            ]);

        $response->assertUnauthorized();
    }

    // ================= GET /tickets/options =================

    #[Test]
    public function staf_bisa_ambil_opsi_form_tanpa_mengonsumsi_token(): void
    {
        TicketIssueCategory::create([
            'name' => 'Internet Lambat',
            'default_priority' => 'High',
            'sla_source' => 'paket',
            'is_active' => true,
        ]);
        TicketIssueCategory::create([
            'name' => 'Kategori Nonaktif',
            'default_priority' => 'low',
            'sla_source' => 'prioritas',
            'is_active' => false,
        ]);

        $staff = $this->staffWithTicketQrPermission();
        $pop = $this->seedPop();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);
        $plaintext = $this->issueTokenFor($staff, $customer);

        $response = $this->withHeaders(array_merge($this->portalClientHeaders(), [
            'Authorization' => "Bearer {$plaintext}",
        ]))->getJson('/api/customer-portal/tickets/options');

        $response->assertOk();
        $response->assertJsonPath('data.types.0.value', 'MTN');
        $response->assertJsonPath('data.types.1.value', 'C-REQ');
        $this->assertContains('Medium', $response->json('data.priorities'));
        $this->assertSame(['Internet Lambat'], collect($response->json('data.issue_categories'))->pluck('name')->all());

        // Baca opsi TIDAK boleh menghanguskan token — masih bisa dipakai submit.
        $this->assertNull(StaffPortalToken::first()->consumed_at);

        $this->postTicket($plaintext, [
            'type' => 'MTN',
            'detail_keluhan' => 'Internet mati total.',
            'priority' => 'Medium',
        ])->assertCreated();
    }

    #[Test]
    public function opsi_form_tanpa_bearer_token_401(): void
    {
        $this->withHeaders($this->portalClientHeaders())
            ->getJson('/api/customer-portal/tickets/options')
            ->assertUnauthorized();
    }
}
