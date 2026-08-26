<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `customers:backfill-portal-login-id` — pengganti klaim asli untuk
 * provisioning akun portal `pending_claim` (docs/api/api-portal-pelanggan/,
 * Fase 2).
 */
class CustomersBackfillPortalLoginIdCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_tidak_menulis_apa_pun(): void
    {
        $pop = Pop::factory()->create(['registration_prefix' => 'PNG']);
        Customer::factory()->create(['pop_id' => $pop->id, 'customer_code' => 'RQ000631']);

        $this->artisan('customers:backfill-portal-login-id', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame(0, CustomerPortalAccount::count());
    }

    public function test_generate_login_id_untuk_pelanggan_tanpa_akun_portal(): void
    {
        $pop = Pop::factory()->create(['registration_prefix' => 'PNG']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'customer_code' => 'RQ000631']);

        $this->artisan('customers:backfill-portal-login-id')
            ->expectsConfirmation('1 akun portal di atas akan dibuat, lanjutkan?', 'yes')
            ->assertExitCode(0);

        $account = CustomerPortalAccount::where('customer_id', $customer->id)->first();

        $this->assertNotNull($account);
        $this->assertSame('PNG-RQ000631', $account->login_id);
        $this->assertSame('pending_claim', $account->status);
    }

    public function test_pelanggan_dengan_pop_tanpa_registration_prefix_dilewati(): void
    {
        $pop = Pop::factory()->create(['registration_prefix' => null]);
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);

        $this->artisan('customers:backfill-portal-login-id', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame(0, CustomerPortalAccount::where('customer_id', $customer->id)->count());
    }

    public function test_pelanggan_yang_sudah_punya_akun_portal_dilewati(): void
    {
        $pop = Pop::factory()->create(['registration_prefix' => 'PNG']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'customer_code' => 'RQ000631']);
        CustomerPortalAccount::create([
            'customer_id' => $customer->id,
            'login_id' => 'PNG-RQ000631',
            'password_hash' => bcrypt('sudah-ada'),
            'status' => 'active',
        ]);

        $this->artisan('customers:backfill-portal-login-id', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame(1, CustomerPortalAccount::count());
    }
}
