<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\City;
use App\Models\Customer;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Bug: FopTask Draft (biasanya dari Ticketing) yang di-assign teknisi lewat
 * modal Edit tabel /fop-tasks (bukan form Create) sebelumnya gak pernah ikut
 * naik status ke Terjadwal — modal Edit ngirim 'status' hidden = nilai lama,
 * jadi Task eksekusi kebuat tapi FopTask.status nyangkut 'draft' selamanya,
 * dan Ticket terkait macet di bucket "Ticket Masuk" walau udah dijadwalkan.
 */
class FopTaskDraftAutoScheduleOnAssignTest extends TestCase
{
    use RefreshDatabase;

    private User $fopUser;

    private User $helpdeskUser;

    private User $tech;

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

        $this->fopUser = $this->makeUserWithAllPopScope('fop');
        $this->helpdeskUser = $this->makeUserWithAllPopScope('helpdesk');
        $this->tech = $this->makeUserWithAllPopScope('teknisi');

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Polorejo', 'postal_code' => '63491']);

        $this->pop = Pop::create([
            'name' => 'POP Polorejo', 'code' => 'POP-PLR', 'type' => 'branch',
            'address' => 'Polorejo', 'status' => 'active', 'city_id' => $city->id,
        ]);

        $this->customer = Customer::factory()->create([
            'pop_id' => $this->pop->id,
            'village_id' => $village->id,
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

    private function createDraftTicket(): Ticket
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'detail_keluhan' => 'Internet mati total.',
            'priority' => 'High',
        ])->assertRedirect();

        return Ticket::first();
    }

    /**
     * Simulasi persis payload yang dikirim modal Edit: 'status' hidden input
     * = nilai lama (edit modal gak punya pilihan ubah status manual).
     */
    private function assignTechnicianViaEditModal(FopTask $fopTask, array $technicianIds): TestResponse
    {
        return $this->actingAs($this->fopUser)->putJson(route('fop-tasks.update', $fopTask), [
            'category' => $fopTask->category->value,
            'task_date' => $fopTask->task_date->format('Y-m-d H:i:s'),
            'tugas' => $fopTask->tugas,
            'village_id' => $fopTask->village_id,
            'pop_id' => $fopTask->pop_id,
            'issue' => $fopTask->issue,
            'priority' => $fopTask->priority->value,
            'status' => $fopTask->status->value,
            'technicians' => $technicianIds,
        ]);
    }

    public function test_assigning_technician_to_draft_fop_task_bumps_status_to_terjadwal(): void
    {
        $ticket = $this->createDraftTicket();
        $fopTask = $ticket->fopTask;

        $this->assertSame(TaskStatus::DRAFT, $fopTask->status);
        $this->assertNull($fopTask->task_id);

        $this->assignTechnicianViaEditModal($fopTask, [$this->tech->id])->assertOk();

        $fopTask->refresh();
        $this->assertSame(TaskStatus::TERJADWAL, $fopTask->status);
        $this->assertNotNull($fopTask->task_id);
        $this->assertSame(TaskStatus::TERJADWAL, Task::find($fopTask->task_id)->status);
    }

    /**
     * Inti keluhan user: Ticket yang tadinya nyangkut di bucket "Ticket Masuk"
     * sekarang otomatis pindah ke "Ticket di Proses" begitu teknisinya di-assign.
     */
    public function test_ticket_moves_from_masuk_to_diproses_bucket_after_assignment(): void
    {
        $ticket = $this->createDraftTicket();

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.bucket', 'masuk'))
            ->assertSee($ticket->ticket_number);

        $this->assignTechnicianViaEditModal($ticket->fopTask, [$this->tech->id])->assertOk();

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.bucket', 'diproses'))
            ->assertSee($ticket->ticket_number);

        $this->actingAs($this->helpdeskUser)
            ->get(route('tickets.bucket', 'masuk'))
            ->assertDontSee($ticket->ticket_number);
    }

    public function test_status_transition_is_recorded_in_fop_task_history(): void
    {
        $ticket = $this->createDraftTicket();
        $fopTask = $ticket->fopTask;

        $this->assignTechnicianViaEditModal($fopTask, [$this->tech->id])->assertOk();

        $history = $fopTask->refresh()->statusHistories()->latest('changed_at')->first();

        $this->assertNotNull($history);
        $this->assertSame('draft', $history->from_status);
        $this->assertSame('terjadwal', $history->to_status);
        $this->assertSame($this->fopUser->id, $history->changed_by);
    }

    /**
     * Regresi: FopTask yang UDAH Terjadwal (bukan Draft) dan cuma diedit ganti
     * teknisi — status gak boleh ke-reset atau keganggu sama fix ini.
     */
    public function test_editing_technicians_on_already_scheduled_task_does_not_change_status(): void
    {
        $ticket = $this->createDraftTicket();
        $fopTask = $ticket->fopTask;
        $this->assignTechnicianViaEditModal($fopTask, [$this->tech->id])->assertOk();
        $fopTask->refresh();
        $this->assertSame(TaskStatus::TERJADWAL, $fopTask->status);

        $tech2 = $this->makeUserWithAllPopScope('teknisi');
        $this->assignTechnicianViaEditModal($fopTask, [$tech2->id])->assertOk();

        $fopTask->refresh();
        $this->assertSame(TaskStatus::TERJADWAL, $fopTask->status);

        // Cuma satu entri transisi draft->terjadwal — gak dobel nulis history
        // pas ganti teknisi kedua kalinya.
        $this->assertCount(
            1,
            $fopTask->statusHistories()->where('to_status', 'terjadwal')->get()
        );
    }

    /**
     * Regresi: FopTask Draft tanpa teknisi yang BUKAN dari Ticketing (mis. hasil
     * auto-sync SURVEY/PSB — satu-satunya jalur lain yang bisa bikin FopTask
     * Draft tanpa teknisi; /fop-tasks store() sendiri mewajibkan minimal 1
     * teknisi jadi gak bisa bikin Draft kosong lewat situ) tetap kena logic
     * yang sama — fix ini generik di kolom status, bukan spesifik tiket
     * Ticketing.
     */
    public function test_non_ticket_draft_fop_task_also_gets_bumped(): void
    {
        $village = Village::first();

        $fopTask = FopTask::create([
            'task_number' => 'TFOP-TEST-0001',
            'task_date' => now(),
            'category' => 'SURVEY',
            'tugas' => 'Survey Pelanggan: '.$this->customer->full_name,
            'village_id' => $village->id,
            'pop_id' => $this->pop->id,
            'customer_id' => $this->customer->id,
            'issue' => 'Auto-Sync dari antrean survey',
            'status' => 'draft',
            'priority' => 'Medium',
        ]);

        $this->assignTechnicianViaEditModal($fopTask, [$this->tech->id])->assertOk();

        $fopTask->refresh();
        $this->assertSame(TaskStatus::TERJADWAL, $fopTask->status);
        $this->assertNotNull($fopTask->task_id);
    }

    /**
     * Edit field LAIN (bukan teknisi) di tiket Draft — gak nyentuh key
     * 'technicians' sama sekali di request — status gak boleh ikut ke-bump.
     * Ini kasus "gak ada perubahan penugasan", beda dari submit array kosong
     * (yang emang ditolak validasi min:1 di endpoint ini).
     */
    public function test_editing_other_fields_without_touching_technicians_does_not_bump_status(): void
    {
        $ticket = $this->createDraftTicket();
        $fopTask = $ticket->fopTask;

        $this->actingAs($this->fopUser)->putJson(route('fop-tasks.update', $fopTask), [
            'notes' => 'Catatan tambahan dari FOP.',
        ])->assertOk();

        $this->assertSame(TaskStatus::DRAFT, $fopTask->refresh()->status);
        $this->assertNull($fopTask->task_id);
    }
}
