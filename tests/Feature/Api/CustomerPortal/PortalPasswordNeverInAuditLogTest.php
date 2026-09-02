<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * Ganti password TIDAK PERNAH menulis hash (lama maupun baru) ke
 * `audit_logs` — penjaga langsung atas alasan kenapa kredensial portal
 * tidak ditaruh di `customers` (database-schema.md §1: `RecordsAuditLogs`
 * menulis `getChanges()` mentah, `$hidden` tidak menolong jalur itu).
 * `CustomerPortalAccount` sengaja TIDAK memakai trait itu — audit di sini
 * ditulis manual, cuma metadata.
 */
class PortalPasswordNeverInAuditLogTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
    }

    public function test_ganti_password_tidak_menulis_hash_ke_audit_logs(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $session = $this->loginAndGetTokens($seed['login_id']);

        $this->withHeaders($this->authenticatedHeaders($session['access_token']))
            ->putJson('/api/customer-portal/me/password', [
                'current_password' => self::PORTAL_TEST_PASSWORD,
                'new_password' => 'Gajah-Terbang-Malam-77',
            ])->assertOk();

        $log = AuditLog::where('module', 'Portal Pelanggan')->where('action', 'password_changed')->first();

        $this->assertNotNull($log);
        $this->assertNull($log->old_values);
        $this->assertArrayNotHasKey('password', $log->new_values ?? []);
        $this->assertArrayNotHasKey('password_hash', $log->new_values ?? []);

        // Payload log gak memuat string password lama/baru dalam bentuk apa pun.
        $payload = json_encode($log->new_values);
        $this->assertStringNotContainsString(self::PORTAL_TEST_PASSWORD, (string) $payload);
        $this->assertStringNotContainsString('Gajah-Terbang-Malam-77', (string) $payload);
    }
}
