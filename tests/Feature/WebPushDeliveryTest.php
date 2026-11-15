<?php

namespace Tests\Feature;

use App\Enums\PushDeliveryStatus;
use App\Jobs\EscalateIfNotDeliveredJob;
use App\Models\PushDelivery;
use App\Models\User;
use App\Services\WebPushDeliveryTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebPushDeliveryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tracker_start_creates_sent_delivery_and_dispatches_escalation_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $delivery = app(WebPushDeliveryTracker::class)->start($user, 'App\\Notifications\\TestWebPushNotification');

        $this->assertDatabaseHas('push_deliveries', [
            'id' => $delivery->id,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'status' => PushDeliveryStatus::Sent->value,
        ]);

        Queue::assertPushed(EscalateIfNotDeliveredJob::class, function ($job) use ($delivery) {
            return $job->pushDeliveryId === $delivery->id;
        });
    }

    #[Test]
    public function ack_endpoint_marks_delivery_as_delivered_with_valid_signature(): void
    {
        $user = User::factory()->create();
        $delivery = PushDelivery::create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'notification_class' => 'App\\Notifications\\TestWebPushNotification',
            'status' => PushDeliveryStatus::Sent,
            'sent_at' => now(),
        ]);

        $url = app(WebPushDeliveryTracker::class)->ackUrl($delivery);

        $response = $this->get($url);

        $response->assertNoContent();

        $delivery->refresh();
        $this->assertEquals(PushDeliveryStatus::Delivered, $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
    }

    #[Test]
    public function ack_endpoint_marks_already_escalated_delivery_as_delivered_when_ack_arrives_late(): void
    {
        $user = User::factory()->create();
        $delivery = PushDelivery::create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'notification_class' => 'App\\Notifications\\TestWebPushNotification',
            'status' => PushDeliveryStatus::Escalated,
            'sent_at' => now()->subMinutes(6),
            'escalated_at' => now()->subMinute(),
        ]);

        $url = app(WebPushDeliveryTracker::class)->ackUrl($delivery);

        $response = $this->get($url);

        $response->assertNoContent();

        $delivery->refresh();
        $this->assertEquals(PushDeliveryStatus::Delivered, $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
    }

    #[Test]
    public function ack_endpoint_rejects_tampered_signature(): void
    {
        $user = User::factory()->create();
        $delivery = PushDelivery::create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'notification_class' => 'App\\Notifications\\TestWebPushNotification',
            'status' => PushDeliveryStatus::Sent,
            'sent_at' => now(),
        ]);

        $url = app(WebPushDeliveryTracker::class)->ackUrl($delivery).'&tampered=1';

        $response = $this->get($url);

        $response->assertForbidden();

        $delivery->refresh();
        $this->assertEquals(PushDeliveryStatus::Sent, $delivery->status);
    }

    #[Test]
    public function escalate_job_marks_delivery_as_escalated_when_not_yet_delivered(): void
    {
        $user = User::factory()->create();
        $delivery = PushDelivery::create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'notification_class' => 'App\\Notifications\\TestWebPushNotification',
            'status' => PushDeliveryStatus::Sent,
            'sent_at' => now(),
        ]);

        (new EscalateIfNotDeliveredJob($delivery->id))->handle();

        $delivery->refresh();
        $this->assertEquals(PushDeliveryStatus::Escalated, $delivery->status);
        $this->assertNotNull($delivery->escalated_at);
    }

    #[Test]
    public function escalate_job_does_nothing_when_already_delivered(): void
    {
        $user = User::factory()->create();
        $delivery = PushDelivery::create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'notification_class' => 'App\\Notifications\\TestWebPushNotification',
            'status' => PushDeliveryStatus::Delivered,
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        (new EscalateIfNotDeliveredJob($delivery->id))->handle();

        $delivery->refresh();
        $this->assertEquals(PushDeliveryStatus::Delivered, $delivery->status);
        $this->assertNull($delivery->escalated_at);
    }
}
