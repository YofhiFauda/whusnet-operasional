<?php

namespace Tests\Feature;

use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\Task;
use App\Enums\TaskType;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class NotificationDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $fopUser;
    protected User $fopUser2;
    protected User $techUser;
    protected Pop $pop1;
    protected Pop $pop2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\FeatureSeeder::class);
        $this->seed(\Database\Seeders\ActionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\TaskFeatureSeeder::class);
        $this->seed(\Database\Seeders\WorkflowTransitionPermissionSeeder::class);

        $this->pop1 = Pop::create([
            'code' => 'PON1',
            'pop_code' => 'PON1',
            'registration_prefix' => 'A',
            'cid_prefix' => 'B',
            'name' => 'POP Ponorogo 1',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $this->pop2 = Pop::create([
            'code' => 'PON2',
            'pop_code' => 'PON2',
            'registration_prefix' => 'X',
            'cid_prefix' => 'Y',
            'name' => 'POP Ponorogo 2',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $fopRole = Role::where('code', 'fop')->first();
        $techRole = Role::where('code', 'teknisi')->first();

        // FOP User 1 (POP 1)
        $this->fopUser = User::factory()->create();
        $this->fopUser->role_id = $fopRole->id;
        $this->fopUser->save();
        $scope1 = $this->fopUser->roleScopes()->create([
            'role_id' => $fopRole->id,
            'scope_type' => \App\Enums\ScopeType::SELECTED_POP->value,
        ]);
        $scope1->targets()->create([
            'pop_id' => $this->pop1->id,
        ]);

        // FOP User 2 (POP 2)
        $this->fopUser2 = User::factory()->create();
        $this->fopUser2->role_id = $fopRole->id;
        $this->fopUser2->save();
        $scope2 = $this->fopUser2->roleScopes()->create([
            'role_id' => $fopRole->id,
            'scope_type' => \App\Enums\ScopeType::SELECTED_POP->value,
        ]);
        $scope2->targets()->create([
            'pop_id' => $this->pop2->id,
        ]);

        // Technician User (POP 1)
        $this->techUser = User::factory()->create();
        $this->techUser->role_id = $techRole->id;
        $this->techUser->save();
        $scopeTech = $this->techUser->roleScopes()->create([
            'role_id' => $techRole->id,
            'scope_type' => \App\Enums\ScopeType::SELECTED_POP->value,
        ]);
        $scopeTech->targets()->create([
            'pop_id' => $this->pop1->id,
        ]);
    }

    public function test_user_can_access_notifications_dashboard(): void
    {
        $response = $this->actingAs($this->fopUser)
            ->get('/notifications');

        $response->assertStatus(200);
        $response->assertViewIs('notifications.index');
    }

    public function test_fop_can_only_see_notifications_within_their_pop_scope(): void
    {
        // Notification 1: for tech in POP 1
        $this->techUser->notify(new \App\Notifications\AppNotification(
            title: 'Notif POP 1',
            message: 'Ini Notif POP 1',
            type: 'info'
        ));

        // Notification 2: for FOP 2 in POP 2
        $this->fopUser2->notify(new \App\Notifications\AppNotification(
            title: 'Notif POP 2',
            message: 'Ini Notif POP 2',
            type: 'error'
        ));

        // FOP User (POP 1) should see Notif POP 1 but NOT Notif POP 2
        $response = $this->actingAs($this->fopUser)->get('/notifications');
        $response->assertSee('Notif POP 1');
        $response->assertDontSee('Notif POP 2');

        // FOP User 2 (POP 2) should see Notif POP 2 but NOT Notif POP 1
        $response2 = $this->actingAs($this->fopUser2)->get('/notifications');
        $response2->assertSee('Notif POP 2');
        $response2->assertDontSee('Notif POP 1');
    }

    public function test_notifications_can_be_filtered(): void
    {
        // Create an info notification
        $this->fopUser->notify(new \App\Notifications\AppNotification(
            title: 'Info Alert',
            message: 'Message 1',
            type: 'info'
        ));

        // Create a success notification
        $this->fopUser->notify(new \App\Notifications\AppNotification(
            title: 'Success Alert',
            message: 'Message 2',
            type: 'success'
        ));

        // Test filter by type = info
        $response = $this->actingAs($this->fopUser)->get('/notifications?type=info');
        $response->assertSee('Info Alert');
        $response->assertViewHas('notifications', fn ($notifs) => $notifs->count() === 1 && $notifs->first()->data['title'] === 'Info Alert');

        // Test filter by type = success
        $response = $this->actingAs($this->fopUser)->get('/notifications?type=success');
        $response->assertSee('Success Alert');
        $response->assertViewHas('notifications', fn ($notifs) => $notifs->count() === 1 && $notifs->first()->data['title'] === 'Success Alert');
    }

    public function test_can_toggle_notification_read_unread_status(): void
    {
        $this->fopUser->notify(new \App\Notifications\AppNotification(
            title: 'Unread Alert',
            message: 'Test Message',
            type: 'info'
        ));

        $notification = DatabaseNotification::first();
        $this->assertNull($notification->read_at);

        // Mark as read
        $response = $this->actingAs($this->fopUser)
            ->post("/notifications/{$notification->id}/read");

        $response->assertStatus(200);
        $this->assertNotNull($notification->refresh()->read_at);

        // Mark as unread
        $response = $this->actingAs($this->fopUser)
            ->post("/notifications/{$notification->id}/unread");

        $response->assertStatus(200);
        $this->assertNull($notification->refresh()->read_at);
    }
}
