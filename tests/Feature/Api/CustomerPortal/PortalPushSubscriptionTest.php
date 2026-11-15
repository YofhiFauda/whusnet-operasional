<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Models\Customer;
use App\Notifications\TestWebPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * `GET /push/vapid-key`, `POST /push/subscribe`, `POST /push/unsubscribe`,
 * `GET /push/subscriptions` (ADHOC-288 Tahap 7, Web Push Fase 3 portal
 * pelanggan). Identitas pelanggan selalu dari token, bukan dari request —
 * sama aturan dengan endpoint /me/* lain.
 */
class PortalPushSubscriptionTest extends TestCase
{
    use InteractsWithPortalAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPortalClientSecret();
        config(['webpush.vapid.public_key' => 'test-vapid-public-key']);
    }

    #[Test]
    public function vapid_key_returns_configured_public_key_for_authenticated_customer(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $tokens = $this->loginAndGetTokens($seed['login_id']);

        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->getJson('/api/customer-portal/push/vapid-key');

        $response->assertOk();
        $response->assertJsonPath('publicKey', 'test-vapid-public-key');
    }

    #[Test]
    public function push_endpoints_reject_request_without_access_token(): void
    {
        $this->withHeaders($this->portalClientHeaders())
            ->getJson('/api/customer-portal/push/vapid-key')
            ->assertUnauthorized();

        $this->withHeaders($this->portalClientHeaders())
            ->postJson('/api/customer-portal/push/subscribe', [])
            ->assertUnauthorized();
    }

    #[Test]
    public function subscribe_persists_subscription_under_authenticated_customer(): void
    {
        $seed = $this->seedActivePortalCustomer();
        $tokens = $this->loginAndGetTokens($seed['login_id']);

        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->postJson('/api/customer-portal/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/customer-endpoint-1',
                'keys' => ['p256dh' => 'public-key-value', 'auth' => 'auth-token-value'],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_type' => Customer::class,
            'subscribable_id' => $seed['customer']->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/customer-endpoint-1',
        ]);
    }

    #[Test]
    public function unsubscribe_removes_only_own_subscription(): void
    {
        $seedA = $this->seedActivePortalCustomer();
        $seedB = $this->seedActivePortalCustomer();
        $tokensA = $this->loginAndGetTokens($seedA['login_id']);
        $tokensB = $this->loginAndGetTokens($seedB['login_id']);

        $this->withHeaders($this->authenticatedHeaders($tokensA['access_token']))
            ->postJson('/api/customer-portal/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/endpoint-a',
                'keys' => ['p256dh' => 'key-a', 'auth' => 'token-a'],
            ])->assertOk();

        $this->withHeaders($this->authenticatedHeaders($tokensB['access_token']))
            ->postJson('/api/customer-portal/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/endpoint-b',
                'keys' => ['p256dh' => 'key-b', 'auth' => 'token-b'],
            ])->assertOk();

        $this->withHeaders($this->authenticatedHeaders($tokensA['access_token']))
            ->postJson('/api/customer-portal/push/unsubscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/endpoint-a',
            ])->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/endpoint-a']);
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/endpoint-b']);
    }

    #[Test]
    public function subscriptions_list_does_not_leak_other_customers(): void
    {
        $seedA = $this->seedActivePortalCustomer();
        $seedB = $this->seedActivePortalCustomer();
        $tokensA = $this->loginAndGetTokens($seedA['login_id']);
        $tokensB = $this->loginAndGetTokens($seedB['login_id']);

        $this->withHeaders($this->authenticatedHeaders($tokensA['access_token']))
            ->postJson('/api/customer-portal/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/endpoint-a',
                'keys' => ['p256dh' => 'key-a', 'auth' => 'token-a'],
            ])->assertOk();

        $this->withHeaders($this->authenticatedHeaders($tokensB['access_token']))
            ->postJson('/api/customer-portal/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/endpoint-b',
                'keys' => ['p256dh' => 'key-b', 'auth' => 'token-b'],
            ])->assertOk();

        $response = $this->withHeaders($this->authenticatedHeaders($tokensA['access_token']))
            ->getJson('/api/customer-portal/push/subscriptions');

        $response->assertOk();
        $this->assertCount(1, $response->json('subscriptions'));
        $this->assertSame('https://fcm.googleapis.com/fcm/send/endpoint-a', $response->json('subscriptions.0.endpoint'));
    }

    #[Test]
    public function send_test_dispatches_notification_when_device_registered(): void
    {
        Notification::fake();

        $seed = $this->seedActivePortalCustomer();
        $tokens = $this->loginAndGetTokens($seed['login_id']);

        $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->postJson('/api/customer-portal/push/subscribe', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/endpoint-test',
                'keys' => ['p256dh' => 'key', 'auth' => 'token'],
            ])->assertOk();

        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->postJson('/api/customer-portal/push/test');

        $response->assertOk();
        Notification::assertSentTo($seed['customer'], TestWebPushNotification::class);
    }

    #[Test]
    public function send_test_rejected_without_registered_device(): void
    {
        Notification::fake();

        $seed = $this->seedActivePortalCustomer();
        $tokens = $this->loginAndGetTokens($seed['login_id']);

        $response = $this->withHeaders($this->authenticatedHeaders($tokens['access_token']))
            ->postJson('/api/customer-portal/push/test');

        $response->assertStatus(422);
        Notification::assertNothingSent();
    }
}
