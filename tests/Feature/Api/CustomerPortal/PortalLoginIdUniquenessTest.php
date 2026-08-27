<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Models\Customer;
use App\Models\CustomerPortalAccount;
use App\Models\Pop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * `login_id` = `{cid_prefix}00{bare_registration_id}` — dua pelanggan beda
 * POP dengan `customer_code` sama HARUS punya `login_id` beda, dan login
 * masing-masing HARUS resolve ke pelanggan yang benar (docs/api/api-portal-
 * pelanggan/business-logic.md §Autentikasi: kenapa bukan `cid`).
 *
 * Pakai `cid_prefix`, BUKAN `registration_prefix` — field itu SENGAJA boleh
 * sama di banyak POP (ID_NUMBERING_RULES.md §4, tanpa constraint unique),
 * jadi gak beneran menjamin `login_id` unik global (bug ditemukan &
 * diperbaiki 2026-08-26, lihat `Customer::getPortalLoginIdAttribute()`).
 */
class PortalLoginIdUniquenessTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    public function test_dua_pelanggan_beda_pop_customer_code_sama_login_id_berbeda_dan_resolve_benar(): void
    {
        $popA = Pop::factory()->create(['cid_prefix' => 'PNG']);
        $popB = Pop::factory()->create(['cid_prefix' => 'SIM']);

        $customerA = Customer::factory()->create(['pop_id' => $popA->id, 'customer_code' => 'RQ000631']);
        $customerB = Customer::factory()->create(['pop_id' => $popB->id, 'customer_code' => 'RQ000631']);

        $accountA = CustomerPortalAccount::create([
            'customer_id' => $customerA->id,
            'login_id' => 'PNG00RQ000631',
            'password_hash' => Hash::make('Password-A-Kuat-99'),
            'status' => 'active',
        ]);

        $accountB = CustomerPortalAccount::create([
            'customer_id' => $customerB->id,
            'login_id' => 'SIM00RQ000631',
            'password_hash' => Hash::make('Password-B-Kuat-99'),
            'status' => 'active',
        ]);

        $this->assertNotSame($accountA->login_id, $accountB->login_id);

        $responseA = $this->loginJson('PNG00RQ000631', 'Password-A-Kuat-99');
        $responseA->assertOk();

        $responseB = $this->loginJson('SIM00RQ000631', 'Password-B-Kuat-99');
        $responseB->assertOk();

        // Token A gak boleh bisa baca profil B.
        $meA = $this->withHeaders($this->authenticatedHeaders($responseA->json('access_token')))
            ->getJson('/api/customer-portal/me');
        $meA->assertOk();
        $meA->assertJsonPath('data.login_id', 'PNG00RQ000631');

        $meB = $this->withHeaders($this->authenticatedHeaders($responseB->json('access_token')))
            ->getJson('/api/customer-portal/me');
        $meB->assertOk();
        $meB->assertJsonPath('data.login_id', 'SIM00RQ000631');
    }
}
