<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * `customers:portal-set-password-for-testing` — DEV ONLY, dipakai selama
 * `/auth/claim` masih stub. Guard environment WAJIB nolak production.
 */
class CustomersPortalSetPasswordForTestingCommandTest extends TestCase
{
    use RefreshDatabase;

    private function seedPendingAccount(): CustomerPortalAccount
    {
        $pop = Pop::factory()->create(['registration_prefix' => 'PNG']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'customer_code' => 'RQ000631']);

        return CustomerPortalAccount::create([
            'customer_id' => $customer->id,
            'login_id' => 'PNG-RQ000631',
            'password_hash' => Hash::make('placeholder-tidak-terpakai'),
            'status' => 'pending_claim',
        ]);
    }

    public function test_command_ditolak_di_environment_production(): void
    {
        $account = $this->seedPendingAccount();
        $this->app['env'] = 'production';

        $this->artisan('customers:portal-set-password-for-testing', [
            'login_id' => $account->login_id,
            'password' => 'Password-Testing-99',
        ])->assertExitCode(1);

        $this->assertSame('pending_claim', $account->fresh()->status);
    }

    public function test_command_mengaktifkan_akun_di_environment_testing(): void
    {
        $account = $this->seedPendingAccount();

        $this->artisan('customers:portal-set-password-for-testing', [
            'login_id' => $account->login_id,
            'password' => 'Password-Testing-99',
        ])->assertExitCode(0);

        $fresh = $account->fresh();
        $this->assertSame('active', $fresh->status);
        $this->assertNotNull($fresh->claimed_at);
        $this->assertTrue(Hash::check('Password-Testing-99', $fresh->password_hash));
    }
}
