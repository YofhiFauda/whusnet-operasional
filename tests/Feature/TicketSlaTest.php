<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\PackageSlaSetting;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketIssueCategory;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Target SLA Ticketing — snapshot `sla_hours`/`sla_deadline_at` dihitung di
 * titik tiket lahir (bukan cuma di FopTask), dan diwariskan utuh saat
 * dieskalasi ke FOP. Lihat docs/plan/analisa-target-sla-ticketing.md.
 */
class TicketSlaTest extends TestCase
{
    use RefreshDatabase;

    private User $helpdeskUser;

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

        $helpdeskRole = Role::where('code', 'helpdesk')->first();
        $this->helpdeskUser = User::factory()->create(['role_id' => $helpdeskRole->id]);
        $this->helpdeskUser->roleScopes()->create([
            'role_id' => $helpdeskRole->id,
            'scope_type' => ScopeType::ALL_POP->value,
        ]);

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $village = Village::create([
            'district_id' => $district->id,
            'name' => 'Polorejo',
            'postal_code' => '63491',
        ]);

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

    private function makeInternetPackage(): InternetPackage
    {
        static $seq = 0;
        $seq++;

        return InternetPackage::create([
            'package_code' => 'PKG-SLA-'.$seq,
            'name' => 'Paket Uji SLA '.$seq,
            'category' => 'Home',
            'package_group' => 'Broadband',
            'bandwidth_label' => '20 Mbps',
            'monthly_price' => 150000,
            'is_active' => true,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'detail_keluhan' => 'Internet mati total sejak pagi.',
            'priority' => 'High',
        ], $overrides);
    }

    /**
     * Jalur 'paket' (default kalau kategori kosong) — sla_hours ikut Master
     * Timeline SLA paket pelanggan, fallback default global kalau paket
     * belum diatur di sana.
     */
    public function test_ticket_without_category_gets_sla_from_default_handling_hours(): void
    {
        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.store'), $this->validPayload())
            ->assertRedirect();

        $ticket = Ticket::firstOrFail();

        $this->assertSame(TaskType::MAINTENANCE->defaultHandlingSlaHours(), $ticket->sla_hours);
        $this->assertNotNull($ticket->sla_deadline_at);
        $this->assertTrue(
            $ticket->sla_deadline_at->equalTo($ticket->created_at->copy()->addHours($ticket->sla_hours))
        );
    }

    /**
     * Jalur 'paket' dengan Master Timeline SLA diset admin — angka paket
     * MENGALAHKAN default global (InternetPackage::getHandlingSla()).
     */
    public function test_ticket_sla_follows_package_master_timeline_when_set(): void
    {
        $package = $this->makeInternetPackage();
        PackageSlaSetting::create([
            'internet_package_id' => $package->id,
            'task_type' => TaskType::MAINTENANCE,
            'sla_duration' => 6,
            'sla_unit' => 'hour',
            'is_active' => true,
        ]);
        $this->customer->update(['internet_package_id' => $package->id]);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.store'), $this->validPayload())
            ->assertRedirect();

        $this->assertSame(6, Ticket::firstOrFail()->sla_hours);
    }

    /**
     * Jalur 'prioritas' — sebelumnya dead config (sla_source cuma teks info
     * di form, gak pernah dibaca backend). Sekarang FopTaskPriority::slaHours()
     * yang dipakai, BUKAN paket, walau pelanggan punya paket internet.
     */
    public function test_ticket_sla_follows_priority_when_category_sla_source_is_prioritas(): void
    {
        $package = $this->makeInternetPackage();
        PackageSlaSetting::create([
            'internet_package_id' => $package->id,
            'task_type' => TaskType::MAINTENANCE,
            'sla_duration' => 48,
            'sla_unit' => 'hour',
            'is_active' => true,
        ]);
        $this->customer->update(['internet_package_id' => $package->id]);

        $category = TicketIssueCategory::create([
            'name' => 'Gangguan Urgent',
            'default_priority' => 'Urgent',
            'sla_source' => 'prioritas',
            'is_active' => true,
        ]);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.store'), $this->validPayload([
                'issue_category_id' => $category->id,
                'priority' => 'Urgent',
            ]))
            ->assertRedirect();

        // Urgent = 4 jam (FopTaskPriority::slaHours()) — bukan 48 jam dari paket.
        $this->assertSame(4, Ticket::firstOrFail()->sla_hours);
    }

    /**
     * Tiket yang gak pernah dieskalasi ke FOP (ditutup langsung Helpdesk)
     * TETAP punya SLA terukur — ini gap utama yang diperbaiki (sebelumnya
     * SLA cuma "hidup" begitu FopTask kebentuk).
     */
    public function test_closed_ticket_without_fop_still_has_sla_and_badge(): void
    {
        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.store'), $this->validPayload())
            ->assertRedirect();

        $ticket = Ticket::firstOrFail();
        $this->assertNull($ticket->fop_task_id);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.close', $ticket))
            ->assertRedirect();

        $ticket->refresh();

        $this->assertNotNull($ticket->resolved_at);
        $this->assertFalse($ticket->isSlaBreached());
        $this->assertNotNull($ticket->slaBadgeLabel());
        $this->assertStringContainsString('Selesai', $ticket->slaBadgeLabel());
    }

    /**
     * Eskalasi ke FOP mewarisi `sla_hours` yang SAMA persis dari tiket, gak
     * dihitung ulang oleh FopTask::booted() — clock-nya satu, gak reset di
     * titik handoff.
     */
    public function test_escalating_to_fop_inherits_same_sla_hours_not_recalculated(): void
    {
        $package = $this->makeInternetPackage();
        // Kalau FopTask::booted() diam-diam hitung ulang dari paket (bukan
        // warisan ticket->sla_hours), test ini bakal gagal karena 6 != nilai
        // default TaskType::MAINTENANCE->defaultHandlingSlaHours() (24).
        PackageSlaSetting::create([
            'internet_package_id' => $package->id,
            'task_type' => TaskType::MAINTENANCE,
            'sla_duration' => 6,
            'sla_unit' => 'hour',
            'is_active' => true,
        ]);
        $this->customer->update(['internet_package_id' => $package->id]);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.store'), $this->validPayload())
            ->assertRedirect();

        $ticket = Ticket::firstOrFail();
        $this->assertSame(6, $ticket->sla_hours);

        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertRedirect();

        $fopTask = $ticket->fresh()->fopTask;

        $this->assertNotNull($fopTask);
        $this->assertSame(6, $fopTask->handling_sla_hours);
    }

    /**
     * Breach-check: `now()` sudah lewat `sla_deadline_at`, tiket masih open →
     * isSlaBreached() true meski belum resolved.
     */
    public function test_open_ticket_past_deadline_is_flagged_breached(): void
    {
        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.store'), $this->validPayload())
            ->assertRedirect();

        $ticket = Ticket::firstOrFail();
        $ticket->forceFill(['sla_deadline_at' => now()->subHour()])->save();

        $this->assertTrue($ticket->fresh()->isSlaBreached());
    }
}
