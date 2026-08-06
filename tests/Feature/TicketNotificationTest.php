<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\ScopeType;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Village;
use App\Notifications\AppNotification;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * In-app notification buat transisi tiket (escalateToNoc/escalateToFop/
 * close/cancel/returnToHelpdesk) — sebelumnya nol notifikasi sama sekali
 * (docs/plan/analisa-status-implementasi-notifikasi.md §5).
 */
class TicketNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $helpdeskUser;

    private User $nocUser;

    private User $fopUser;

    private Customer $customer;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(TicketFeatureSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->helpdeskUser = $this->makeUserWithAllPopScope('helpdesk');
        $this->nocUser = $this->makeUserWithAllPopScope('noc');
        $this->fopUser = $this->makeUserWithAllPopScope('fop');

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Polorejo', 'postal_code' => '63491']);

        $this->pop = Pop::create([
            'name' => 'POP Polorejo',
            'code' => 'POP-PLR',
            'type' => 'branch',
            'address' => 'Polorejo',
            'status' => 'active',
            'city_id' => $city->id,
        ]);

        $this->customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'village_id' => $village->id,
            'full_name' => 'Budi Santoso',
        ]);
    }

    private function makeUserWithAllPopScope(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->first();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $user->roleScopes()->create([
            'role_id' => $role->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        return $user;
    }

    private function submitTicket(User $actor): Ticket
    {
        $this->actingAs($actor)->post(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'detail_keluhan' => 'Internet mati total sejak pagi.',
            'priority' => 'High',
        ])->assertRedirect();

        return Ticket::latest('id')->firstOrFail();
    }

    public function test_escalate_to_noc_notifies_noc_users(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        Notification::fake();

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertOk();

        Notification::assertSentTo(
            $this->nocUser,
            AppNotification::class,
            fn ($notification) => $notification->type === NotificationType::INFO
                && str_contains($notification->title, $ticket->ticket_number)
        );
    }

    public function test_escalate_to_fop_notifies_fop_users(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        Notification::fake();

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertOk();

        Notification::assertSentTo(
            $this->fopUser,
            AppNotification::class,
            fn ($notification) => str_contains($notification->title, $ticket->fresh()->fopTask->task_number)
        );
    }

    public function test_close_by_noc_notifies_original_helpdesk_creator(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);
        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertOk();

        Notification::fake();

        $this->actingAs($this->nocUser)
            ->postJson(route('tickets.close', $ticket), ['reason' => 'Sudah beres dari sisi NOC.'])
            ->assertOk();

        Notification::assertSentTo(
            $this->helpdeskUser,
            AppNotification::class,
            fn ($notification) => $notification->type === NotificationType::SUCCESS
        );
    }

    public function test_close_by_self_does_not_notify_self(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);

        Notification::fake();

        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.close', $ticket))
            ->assertOk();

        Notification::assertNothingSentTo($this->helpdeskUser);
    }

    public function test_cancel_notifies_original_creator_with_error_type(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);
        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertOk();

        Notification::fake();

        $this->actingAs($this->nocUser)
            ->postJson(route('tickets.cancel', $ticket), ['reason' => 'Ternyata bukan gangguan.'])
            ->assertOk();

        Notification::assertSentTo(
            $this->helpdeskUser,
            AppNotification::class,
            fn ($notification) => $notification->type === NotificationType::ERROR
        );
    }

    public function test_return_to_helpdesk_notifies_original_creator_with_warning_type(): void
    {
        $ticket = $this->submitTicket($this->helpdeskUser);
        $this->actingAs($this->helpdeskUser)
            ->postJson(route('tickets.escalate', $ticket), ['target' => 'noc'])
            ->assertOk();

        Notification::fake();

        $this->actingAs($this->nocUser)
            ->postJson(route('tickets.return-to-helpdesk', $ticket), ['reason' => 'Salah kirim.'])
            ->assertOk();

        Notification::assertSentTo(
            $this->helpdeskUser,
            AppNotification::class,
            fn ($notification) => $notification->type === NotificationType::WARNING
        );
    }
}
