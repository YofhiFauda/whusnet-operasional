<?php

namespace Tests\Feature;

use App\Notifications\TestWebPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebPushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function vapid_public_key_endpoint_returns_configured_key(): void
    {
        $this->loginAsAdmin();

        config(['webpush.vapid.public_key' => 'test-public-key']);

        $response = $this->getJson(route('webpush.vapid-key'));

        $response->assertOk()->assertJson(['publicKey' => 'test-public-key']);
    }

    #[Test]
    public function subscribe_stores_subscription_for_authenticated_user(): void
    {
        $user = $this->loginAsAdmin();

        $response = $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 13; SM-A135F) Chrome/120.0'])
            ->postJson(route('webpush.subscribe'), [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
                'keys' => [
                    'p256dh' => 'public-key-value',
                    'auth' => 'auth-token-value',
                ],
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_type' => $user->getMorphClass(),
            'subscribable_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'device_name' => 'Samsung',
        ]);
    }

    #[Test]
    public function subscribe_rejects_request_without_endpoint(): void
    {
        $this->loginAsAdmin();

        $response = $this->postJson(route('webpush.subscribe'), [
            'keys' => ['p256dh' => 'x', 'auth' => 'y'],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function unsubscribe_removes_subscription_for_current_user_only(): void
    {
        $user = $this->loginAsAdmin();
        $otherUser = $this->loginAsAdmin();
        $this->loginAsAdmin($user);

        $user->updatePushSubscription('https://endpoint-a', 'pk', 'auth');
        $otherUser->updatePushSubscription('https://endpoint-b', 'pk', 'auth');

        $this->postJson(route('webpush.unsubscribe'), [
            'endpoint' => 'https://endpoint-a',
        ])->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://endpoint-a']);
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://endpoint-b']);
    }

    #[Test]
    public function subscriptions_lists_only_subscriptions_owned_by_current_user(): void
    {
        $user = $this->loginAsAdmin();
        $otherUser = $this->loginAsAdmin();
        $this->loginAsAdmin($user);

        $user->updatePushSubscription('https://endpoint-mine', 'pk', 'auth');
        $otherUser->updatePushSubscription('https://endpoint-other', 'pk', 'auth');

        $response = $this->getJson(route('webpush.subscriptions'));

        $response->assertOk();
        $endpoints = collect($response->json('subscriptions'))->pluck('endpoint');

        $this->assertContains('https://endpoint-mine', $endpoints);
        $this->assertNotContains('https://endpoint-other', $endpoints);
    }

    #[Test]
    public function guest_cannot_access_push_subscription_endpoints(): void
    {
        $this->getJson(route('webpush.vapid-key'))->assertUnauthorized();
        $this->postJson(route('webpush.subscribe'), [])->assertUnauthorized();
    }

    #[Test]
    public function send_test_dispatches_notification_when_device_registered(): void
    {
        Notification::fake();

        $user = $this->loginAsAdmin();
        $user->updatePushSubscription('https://endpoint-test', 'pk', 'auth');

        $response = $this->postJson(route('webpush.test'));

        $response->assertOk();
        Notification::assertSentTo($user, TestWebPushNotification::class);
    }

    #[Test]
    public function send_test_rejected_without_registered_device(): void
    {
        Notification::fake();

        $this->loginAsAdmin();

        $response = $this->postJson(route('webpush.test'));

        $response->assertStatus(422);
        Notification::assertNothingSent();
    }
}
