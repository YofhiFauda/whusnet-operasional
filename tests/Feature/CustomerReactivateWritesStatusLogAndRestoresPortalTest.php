<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use App\Models\CustomerQrToken;
use App\Models\CustomerService;
use App\Models\CustomerStatusLog;
use App\Models\CustomerTerminationReason;
use App\Models\Pop;
use App\Services\CustomerQrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Langganan Lagi" dulu update() status langsung: tidak masuk
 * customer_status_logs, dan akun portal yang di-`disabled` CustomerObserver
 * saat terminate tidak pernah pulih (claim() menolak `disabled`, token QR
 * sudah dicabut) — pelanggan terkunci permanen dari portal.
 */
class CustomerReactivateWritesStatusLogAndRestoresPortalTest extends TestCase
{
    use RefreshDatabase;

    private function terminatedCustomer(array $overrides = []): Customer
    {
        $pop = Pop::factory()->create(['cid_prefix' => 'PNG']);

        return Customer::factory()->create(array_merge([
            'pop_id' => $pop->id,
            'status' => 'terminated',
            'terminated_at' => now()->subMonth()->startOfSecond(),
        ], $overrides));
    }

    #[Test]
    public function langganan_lagi_menulis_status_log_dan_menjaga_terminated_at(): void
    {
        $user = $this->loginAsAdmin();
        $terminatedAt = now()->subMonth()->startOfSecond();
        $customer = $this->terminatedCustomer(['terminated_at' => $terminatedAt]);
        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'service_status' => 'berhenti',
            'billing_cycle' => 'monthly',
            'package_name_snapshot' => 'Paket Test',
            'package_price_snapshot' => 150000,
            'monthly_price' => 150000,
            'total_monthly_bill' => 150000,
        ]);

        $this->post(route('customers.reactivate', $customer))->assertSessionHas('success');

        $customer->refresh();
        $this->assertSame('active', $customer->status);
        $this->assertSame('aktif', $service->fresh()->service_status);

        $this->assertDatabaseHas('customer_status_logs', [
            'customer_id' => $customer->id,
            'from_status' => 'terminated',
            'to_status' => 'active',
            'changed_by' => $user->id,
            'note' => 'Langganan Lagi',
        ]);

        // Churn periode lalu (DashboardController::growthStats) dibaca dari
        // terminated_at — dikosongkan = churn bulan itu ikut terhapus.
        $this->assertEquals($terminatedAt, $customer->terminated_at);
    }

    #[Test]
    public function langganan_lagi_ditolak_kalau_pelanggan_tidak_terminated(): void
    {
        $this->loginAsAdmin();
        $customer = $this->terminatedCustomer(['status' => 'active', 'terminated_at' => null]);

        $this->post(route('customers.reactivate', $customer))->assertSessionHas('error');

        $this->assertSame(0, CustomerStatusLog::where('customer_id', $customer->id)->count());
    }

    #[Test]
    public function langganan_lagi_memulihkan_akun_portal_ke_pending_claim_dan_menerbitkan_qr_baru(): void
    {
        $this->loginAsAdmin();
        $customer = $this->terminatedCustomer(['status' => 'active', 'terminated_at' => null]);
        $customer->update(['customer_code' => 'RQ003001']);

        $account = CustomerPortalAccount::create([
            'customer_id' => $customer->id,
            'login_id' => $customer->portal_login_id,
            'password_hash' => Hash::make('Kuda-Nil-Rajin-88'),
            'status' => 'active',
            'claimed_at' => now(),
        ]);
        $oldHash = $account->password_hash;
        $oldToken = app(CustomerQrTokenService::class)->issue($customer);

        // Terminasi lewat jalur resmi → observer menonaktifkan akun & mencabut QR.
        $reason = CustomerTerminationReason::create(['name' => 'Pindah']);
        $this->post(route('customers.terminate', $customer), ['termination_reason_id' => $reason->id, 'penalty_amount' => 0])->assertSessionHas('success');
        $this->assertSame('disabled', $account->fresh()->status);
        $this->assertTrue($oldToken->fresh()->isRevoked());

        $this->post(route('customers.reactivate', $customer))->assertSessionHas('success');

        $account->refresh();
        $this->assertSame('pending_claim', $account->status);
        $this->assertFalse(Hash::check('Kuda-Nil-Rajin-88', $account->password_hash), 'Password lama tidak boleh hidup lagi.');
        $this->assertNotSame($oldHash, $account->password_hash);

        // Kartu lama mati; token baru + PIN baru siap dicetak.
        $this->assertTrue($oldToken->fresh()->isRevoked());
        $newToken = CustomerQrToken::where('customer_id', $customer->id)->whereNull('revoked_at')->first();
        $this->assertNotNull($newToken);
        $this->assertNotSame($oldToken->id, $newToken->id);
        $this->assertNotNull($newToken->pin_hash);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Portal Pelanggan',
            'action' => 'account_restored_after_reactivation',
            'auditable_id' => $account->id,
        ]);
    }

    #[Test]
    public function langganan_lagi_pelanggan_legacy_tanpa_akun_portal_dibuatkan_akun_pending_claim(): void
    {
        $this->loginAsAdmin();
        $customer = $this->terminatedCustomer(['customer_code' => 'RQ003002']);
        $this->assertNull($customer->portalAccount);

        $this->post(route('customers.reactivate', $customer))->assertSessionHas('success');

        $account = CustomerPortalAccount::where('customer_id', $customer->id)->first();
        $this->assertNotNull($account);
        $this->assertSame('pending_claim', $account->status);
    }
}
