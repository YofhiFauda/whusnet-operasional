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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FOP bikin Task MTN/C-REQ langsung dari halaman Task FOP — sekarang beneran
 * lewat alur Ticketing (TicketService::create()), bukan FopTaskController::store()
 * generik. Bedanya dari submit helpdesk: FOP boleh langsung pilih teknisi &
 * jadwal di form yang sama (skip status Draft) — lihat TicketController::store().
 */
class FopTaskCreateFollowsTicketingTest extends TestCase
{
    use RefreshDatabase;

    private User $fopUser;

    private User $helpdeskUser;

    private User $tech;

    private Customer $customer;

    private Pop $pop;

    private Village $village;

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
        $this->village = Village::create(['district_id' => $district->id, 'name' => 'Polorejo', 'postal_code' => '63491']);

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
            'village_id' => $this->village->id,
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

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'detail_keluhan' => 'Internet mati total, sudah dicek dari sisi pelanggan.',
            'catatan_teknis' => 'Redaman -27 dBm.',
            'priority' => 'High',
            'origin' => 'fop_tasks',
        ], $overrides);
    }

    public function test_fop_submitting_from_fop_tasks_page_without_technicians_creates_draft_ticket_and_redirects_to_fop_tasks(): void
    {
        $response = $this->actingAs($this->fopUser)->post(route('tickets.store'), $this->basePayload());

        $response->assertRedirect(route('fop-tasks.index'));

        $ticket = Ticket::first();
        $this->assertNotNull($ticket);
        $this->assertSame($this->fopUser->id, $ticket->created_by);
        $this->assertSame(TaskStatus::DRAFT, $ticket->fopTask->status);
        $this->assertNull($ticket->fopTask->task_id);
        $this->assertCount(0, $ticket->fopTask->technicians);
    }

    public function test_fop_submitting_with_technicians_and_task_date_immediately_schedules(): void
    {
        $taskDate = now()->addDay()->format('Y-m-d').' 09:00:00';

        $response = $this->actingAs($this->fopUser)->post(route('tickets.store'), $this->basePayload([
            'technicians' => [$this->tech->id],
            'task_date' => $taskDate,
        ]));

        $response->assertRedirect(route('fop-tasks.index'));

        $ticket = Ticket::first();
        $fopTask = $ticket->fopTask->refresh();

        $this->assertSame(TaskStatus::TERJADWAL, $fopTask->status);
        $this->assertNotNull($fopTask->task_id);
        $this->assertTrue($fopTask->technicians->contains($this->tech->id));

        $linkedTask = Task::find($fopTask->task_id);
        $this->assertSame(TaskStatus::TERJADWAL, $linkedTask->status);
        $this->assertTrue($linkedTask->teamMembers->pluck('user_id')->contains($this->tech->id));

        // Deskripsi Task eksekusi harus dari keluhan pelanggan, bukan issue generik.
        $this->assertStringContainsString('Internet mati total', $linkedTask->description);
    }

    public function test_creq_also_supported_via_fop_tasks_page(): void
    {
        $response = $this->actingAs($this->fopUser)->post(route('tickets.store'), $this->basePayload([
            'type' => TaskType::CREQ->value,
            'technicians' => [$this->tech->id],
            'task_date' => now()->format('Y-m-d').' 10:00:00',
        ]));

        $response->assertRedirect(route('fop-tasks.index'));
        $this->assertSame(TaskType::CREQ, Ticket::first()->type);
    }

    /**
     * Helpdesk gak punya fop_tasks.create — walau dia craft request dengan
     * technicians[] & origin=fop_tasks (misal lewat devtools), dua-duanya
     * WAJIB diabaikan diam-diam. Ticket tetap dibuat normal (Draft), gak
     * ke-assign, dan redirect tetap ke tickets.show (bukan fop-tasks.index).
     */
    public function test_helpdesk_cannot_self_assign_technicians_via_crafted_request(): void
    {
        $response = $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->basePayload([
            'technicians' => [$this->tech->id],
            'task_date' => now()->format('Y-m-d').' 09:00:00',
        ]));

        $ticket = Ticket::first();

        $response->assertRedirect(route('tickets.show', $ticket));

        $fopTask = $ticket->fopTask->refresh();
        $this->assertSame(TaskStatus::DRAFT, $fopTask->status);
        $this->assertNull($fopTask->task_id);
        $this->assertCount(0, $fopTask->technicians);
    }

    /**
     * Kembaran kasus di atas: origin=fop_tasks doang (tanpa technicians) dari
     * helpdesk juga gak boleh ngubah tujuan redirect.
     */
    public function test_helpdesk_origin_flag_alone_does_not_redirect_to_fop_tasks(): void
    {
        $response = $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $this->basePayload());

        $ticket = Ticket::first();
        $response->assertRedirect(route('tickets.show', $ticket));
    }

    /**
     * Regresi: submit TANPA origin (dari /tickets/new biasa) tetap jalan
     * persis seperti sebelumnya, gak kena pengaruh perubahan ini.
     */
    public function test_normal_ticketing_submission_without_origin_is_unaffected(): void
    {
        $payload = $this->basePayload();
        unset($payload['origin']);

        $response = $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), $payload);

        $ticket = Ticket::first();
        $response->assertRedirect(route('tickets.show', $ticket));
        $this->assertSame(TaskStatus::DRAFT, $ticket->fopTask->status);
    }

    /**
     * Non-FOP tetap gak bisa cancel/assign lewat jalur ini — batas RBAC lama
     * (fop_tasks.create) yang tetap jadi gerbang, bukan gerbang baru.
     */
    public function test_technician_assignment_requires_fop_tasks_create_permission_not_just_tickets_create(): void
    {
        $this->assertTrue($this->helpdeskUser->hasPermission('tickets.create'));
        $this->assertFalse($this->helpdeskUser->hasPermission('fop_tasks.create'));
        $this->assertTrue($this->fopUser->hasPermission('fop_tasks.create'));
    }

    /**
     * Kategori di luar MTN/C-REQ tetap lewat FopTaskController::store() lama
     * — gak kepengaruh perubahan ini sama sekali.
     */
    public function test_non_ticket_category_still_uses_generic_fop_task_store(): void
    {
        $response = $this->actingAs($this->fopUser)->post(route('fop-tasks.store'), [
            'category' => 'O-REQ',
            'task_date' => now()->format('Y-m-d').' 08:00:00',
            'tugas' => 'Perbaikan Office',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'Router kantor mati',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'technicians' => [$this->tech->id],
        ]);

        $response->assertRedirect(route('fop-tasks.index'));
        $this->assertSame(0, Ticket::count());
        $this->assertSame(1, FopTask::where('category', 'O-REQ')->count());
    }

    /**
     * Point 1 dari kebutuhan sinkronisasi: modal "Tambah Task FOP" mode
     * Ticketing (MTN/C-REQ) harus sama persis kapabilitasnya dengan /tickets/new
     * — termasuk Lampiran, yang sebelumnya gak ada endpoint/field-nya di form ini.
     */
    public function test_fop_can_attach_evidence_when_creating_ticket_from_fop_tasks_page(): void
    {
        Storage::fake('local');

        $response = $this->actingAs($this->fopUser)->post(route('tickets.store'), $this->basePayload([
            'attachments' => [UploadedFile::fake()->image('bukti.jpg')],
        ]));

        $response->assertRedirect(route('fop-tasks.index'));

        $ticket = Ticket::first();
        $attachment = $ticket->attachments->first();

        $this->assertNotNull($attachment);
        $this->assertSame('bukti.jpg', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->file_path);
    }
}
