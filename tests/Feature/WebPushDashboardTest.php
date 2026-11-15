<?php

namespace Tests\Feature;

use App\Enums\PushDeliveryStatus;
use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\PushDelivery;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADHOC-288 Tahap 8 — dashboard delivery rate per merek HP/OS. `push_deliveries`
 * tercatat per NOTIFIABLE (bukan per device), jadi dashboard ini APPROXIMATE:
 * sebuah delivery diatribusikan ke SEMUA merek device yang dipunyai notifiable
 * itu saat ini, bukan device spesifik yang beneran ack.
 */
class WebPushDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function subscriptionModel(): string
    {
        return Config::string('webpush.model');
    }

    #[Test]
    public function user_without_permission_gets_forbidden(): void
    {
        $this->loginAsAdmin(); // auto-seed RoleSeeder
        $role = Role::where('code', 'teknisi')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $user->roleScopes()->create(['role_id' => $role->id, 'scope_type' => ScopeType::ALL_POP->value]);

        $this->actingAs($user)->get(route('webpush.dashboard'))->assertForbidden();
    }

    #[Test]
    public function owner_sees_delivery_rate_grouped_by_single_brand(): void
    {
        $owner = $this->loginAsAdmin();

        $owner->updatePushSubscription('https://endpoint-owner', 'pk', 'auth');
        $this->subscriptionModel()::where('endpoint', 'https://endpoint-owner')
            ->update(['device_name' => 'Samsung']);

        PushDelivery::create([
            'notifiable_type' => $owner->getMorphClass(),
            'notifiable_id' => $owner->id,
            'notification_class' => 'App\\Notifications\\AppPushNotification',
            'status' => PushDeliveryStatus::Delivered->value,
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        PushDelivery::create([
            'notifiable_type' => $owner->getMorphClass(),
            'notifiable_id' => $owner->id,
            'notification_class' => 'App\\Notifications\\AppPushNotification',
            'status' => PushDeliveryStatus::Escalated->value,
            'sent_at' => now(),
            'escalated_at' => now(),
        ]);

        $response = $this->get(route('webpush.dashboard'));

        $response->assertOk();
        $response->assertSee('Samsung');
        $response->assertViewHas('rows', function ($rows) {
            $row = $rows->firstWhere('brand', 'Samsung');

            return $row['sent'] === 2 && $row['delivered'] === 1 && $row['escalated'] === 1;
        });
    }

    #[Test]
    public function delivery_is_attributed_to_all_brands_owned_by_notifiable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $pop = Pop::create(['code' => 'SMN', 'pop_code' => 'SMN', 'registration_prefix' => 'C', 'cid_prefix' => 'D', 'name' => 'POP Sooko', 'type' => 'cabang', 'status' => 'active']);
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);

        $customer->updatePushSubscription('https://endpoint-a', 'pk', 'auth');
        $customer->updatePushSubscription('https://endpoint-b', 'pk2', 'auth2');
        $this->subscriptionModel()::where('endpoint', 'https://endpoint-a')->update(['device_name' => 'Xiaomi/Redmi/POCO']);
        $this->subscriptionModel()::where('endpoint', 'https://endpoint-b')->update(['device_name' => 'OPPO/realme']);

        PushDelivery::create([
            'notifiable_type' => $customer->getMorphClass(),
            'notifiable_id' => $customer->id,
            'notification_class' => 'App\\Notifications\\AppPushNotification',
            'status' => PushDeliveryStatus::Delivered->value,
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        $owner = $this->loginAsAdmin();
        $response = $this->get(route('webpush.dashboard'));

        $response->assertOk();
        $response->assertViewHas('rows', function ($rows) {
            $xiaomi = $rows->firstWhere('brand', 'Xiaomi/Redmi/POCO');
            $oppo = $rows->firstWhere('brand', 'OPPO/realme');

            return $xiaomi['delivered'] === 1 && $oppo['delivered'] === 1;
        });
    }
}
