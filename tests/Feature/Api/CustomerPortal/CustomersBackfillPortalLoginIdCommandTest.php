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
        $pop = Pop::factory()->create(['cid_prefix' => 'PNG']);
        Customer::factory()->create(['pop_id' => $pop->id, 'customer_code' => 'RQ000631']);

        $this->artisan('customers:backfill-portal-login-id', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame(0, CustomerPortalAccount::count());
    }

    public function test_generate_login_id_untuk_pelanggan_tanpa_akun_portal(): void
    {
        $pop = Pop::factory()->create(['cid_prefix' => 'PNG']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'customer_code' => 'RQ000631']);

        $this->artisan('customers:backfill-portal-login-id')
            ->expectsConfirmation('1 akun portal di atas akan dibuat, lanjutkan?', 'yes')
            ->assertExitCode(0);

        $account = CustomerPortalAccount::where('customer_id', $customer->id)->first();

        $this->assertNotNull($account);
        $this->assertSame('PNG00RQ000631', $account->login_id);
        $this->assertSame('pending_claim', $account->status);
    }

    public function test_pelanggan_dengan_pop_tanpa_cid_prefix_dilewati(): void
    {
        $pop = Pop::factory()->create(['cid_prefix' => null]);
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);

        $this->artisan('customers:backfill-portal-login-id', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame(0, CustomerPortalAccount::where('customer_id', $customer->id)->count());
    }

    public function test_pelanggan_yang_sudah_punya_akun_portal_dilewati(): void
    {
        $pop = Pop::factory()->create(['cid_prefix' => 'PNG']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'customer_code' => 'RQ000631']);
        CustomerPortalAccount::create([
            'customer_id' => $customer->id,
            'login_id' => 'PNG00RQ000631',
            'password_hash' => bcrypt('sudah-ada'),
            'status' => 'active',
        ]);

        $this->artisan('customers:backfill-portal-login-id', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame(1, CustomerPortalAccount::count());
    }

    /**
     * `--resync` (2026-08-26) — ditulis SETELAH bug-nya ketauan lewat
     * verifikasi HTTP manual terhadap dev DB sungguhan (bukan sqlite test),
     * bukan sebelumnya. Test lain di file ini SELALU bikin `login_id` pakai
     * accessor TERKINI (`$customer->portal_login_id`) — makanya skenario
     * "baris lama pakai formula lama" itu gak pernah ke-exercise walau
     * seluruh test hijau. Kasus nyata: 99/100 akun `customer_portal_accounts`
     * di DB dev ternyata masih format `registration_prefix-customer_code`
     * (formula LAMA, sebelum direvisi ke `cid_prefix`), sementara kartu yang
     * dicetak SEKARANG nunjukin format baru — `/auth/claim` gagal kalau
     * dicoba pakai login_id yang BENERAN tercetak.
     */
    public function test_resync_memperbarui_login_id_basi_pada_akun_pending_claim(): void
    {
        $pop = Pop::factory()->create(['registration_prefix' => 'RQ', 'cid_prefix' => 'PNG']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'customer_code' => 'RQ000631']);

        // Baris DIBUAT MANUAL pakai formula LAMA — mensimulasikan baris yang
        // sudah ada di DB SEBELUM revisi formula, bukan lewat command ini
        // (command SEKARANG selalu pakai accessor terkini, gak bisa
        // memproduksi baris basi lagi — makanya harus disuntik manual).
        $account = CustomerPortalAccount::create([
            'customer_id' => $customer->id,
            'login_id' => 'RQ-RQ000631', // formula lama, BUKAN portal_login_id sekarang
            'password_hash' => bcrypt('placeholder'),
            'status' => 'pending_claim',
        ]);

        $this->assertNotSame($customer->portal_login_id, $account->login_id);

        $this->artisan('customers:backfill-portal-login-id', ['--resync' => true])
            ->expectsConfirmation('1 akun pending_claim di atas akan di-resync ke login_id formula terkini, lanjutkan?', 'yes')
            ->assertExitCode(0);

        $this->assertSame($customer->portal_login_id, $account->fresh()->login_id);
    }

    /**
     * Akun `active` (SUDAH pernah diklaim) TIDAK BOLEH ikut di-resync —
     * pelanggan itu mungkin sudah tahu & pernah login pakai login_id LAMA;
     * menimpanya diam-diam berarti mengunci akun yang sudah aktif.
     */
    public function test_resync_tidak_menyentuh_akun_yang_sudah_active(): void
    {
        $pop = Pop::factory()->create(['registration_prefix' => 'RQ', 'cid_prefix' => 'PNG']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'customer_code' => 'RQ000631']);

        $account = CustomerPortalAccount::create([
            'customer_id' => $customer->id,
            'login_id' => 'RQ-RQ000631',
            'password_hash' => bcrypt('sudah-diklaim'),
            'status' => 'active',
            'claimed_at' => now(),
        ]);

        $this->artisan('customers:backfill-portal-login-id', ['--resync' => true, '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame('RQ-RQ000631', $account->fresh()->login_id);
    }

    public function test_resync_dry_run_tidak_menulis_apa_pun(): void
    {
        $pop = Pop::factory()->create(['registration_prefix' => 'RQ', 'cid_prefix' => 'PNG']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id, 'customer_code' => 'RQ000631']);
        $account = CustomerPortalAccount::create([
            'customer_id' => $customer->id,
            'login_id' => 'RQ-RQ000631',
            'password_hash' => bcrypt('placeholder'),
            'status' => 'pending_claim',
        ]);

        $this->artisan('customers:backfill-portal-login-id', ['--resync' => true, '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame('RQ-RQ000631', $account->fresh()->login_id);
    }
}
