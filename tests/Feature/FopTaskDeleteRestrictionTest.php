<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
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
use Tests\TestCase;

/**
 * Task SRV, PSB, dan MTN/C-REQ yang asalnya dari Ticketing gak boleh dihapus
 * dari /fop-tasks. MTN/C-REQ yang dibuat manual langsung di /fop-tasks
 * (toleransi salah input) tetap boleh — sesuai sebelumnya.
 */
class FopTaskDeleteRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private User $fopUser;

    private User $helpdeskUser;

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

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $this->village = Village::create(['district_id' => $district->id, 'name' => 'Polorejo', 'postal_code' => '63491']);

        $this->pop = Pop::create([
            'name' => 'POP Polorejo', 'code' => 'POP-PLR', 'cid_prefix' => 'C',
            'type' => 'branch', 'address' => 'Polorejo', 'status' => 'active', 'city_id' => $city->id,
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

    private function makeSurveyTask(): FopTask
    {
        $task = Task::create([
            'task_number' => 'TASK-SRV-DEL-0001',
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::SURVEY->value,
            'title' => 'Survey: '.$this->customer->full_name,
            'status' => 'terjadwal',
            'scheduled_at' => now(),
            'sla_minutes' => 120,
            'created_by' => $this->fopUser->id,
            'updated_by' => $this->fopUser->id,
        ]);

        return FopTask::create([
            'task_number' => 'TFOP-SRV-DEL-0001',
            'task_date' => now(),
            'category' => 'SURVEY',
            'tugas' => 'Survey Pelanggan: '.$this->customer->full_name,
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'customer_id' => $this->customer->id,
            'issue' => 'Survey',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'task_id' => $task->id,
        ]);
    }

    public function test_survey_task_cannot_be_deleted(): void
    {
        $fopTask = $this->makeSurveyTask();

        $this->actingAs($this->fopUser)
            ->delete(route('fop-tasks.destroy', $fopTask))
            ->assertStatus(422);

        $this->assertDatabaseHas('fop_tasks', ['id' => $fopTask->id]);
    }

    public function test_pemasangan_task_cannot_be_deleted(): void
    {
        $fopTask = $this->makeSurveyTask();
        $fopTask->update(['category' => 'PSB']);

        $this->actingAs($this->fopUser)
            ->delete(route('fop-tasks.destroy', $fopTask))
            ->assertStatus(422);

        $this->assertDatabaseHas('fop_tasks', ['id' => $fopTask->id]);
    }

    public function test_mtn_task_from_ticketing_cannot_be_deleted(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), [
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->customer->id,
            'detail_keluhan' => 'Internet mati.',
            'priority' => 'High',
        ])->assertRedirect();

        $ticket = Ticket::first();

        // FopTask gak lagi auto-dibuat pas submit — eskalasi eksplisit ke FOP.
        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertRedirect();

        $ticket->refresh();

        $this->actingAs($this->fopUser)
            ->delete(route('fop-tasks.destroy', $ticket->fop_task_id))
            ->assertStatus(422);

        $this->assertDatabaseHas('fop_tasks', ['id' => $ticket->fop_task_id]);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
    }

    /**
     * Toleransi salah input tetap ada: MTN/C-REQ yang dibuat MANUAL langsung
     * di /fop-tasks (bukan lewat Ticketing) tetap boleh dihapus.
     */
    public function test_manually_created_mtn_task_can_still_be_deleted(): void
    {
        $this->actingAs($this->fopUser)->post(route('fop-tasks.store'), [
            'category' => 'MTN',
            'task_date' => now()->format('Y-m-d').' 08:00:00',
            'tugas' => 'Salah input, mau dihapus',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'Test',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'technicians' => [$this->makeUserWithAllPopScope('teknisi')->id],
        ])->assertRedirect();

        $fopTask = FopTask::where('tugas', 'Salah input, mau dihapus')->firstOrFail();

        $this->actingAs($this->fopUser)
            ->delete(route('fop-tasks.destroy', $fopTask))
            ->assertRedirect(route('fop-tasks.index'));

        $this->assertDatabaseMissing('fop_tasks', ['id' => $fopTask->id]);
    }

    /**
     * Regresi: kategori lain (O-REQ dkk) tetap boleh dihapus seperti biasa —
     * gak kesenggol perubahan ini.
     */
    public function test_other_category_task_can_still_be_deleted(): void
    {
        $this->actingAs($this->fopUser)->post(route('fop-tasks.store'), [
            'category' => 'O-REQ',
            'task_date' => now()->format('Y-m-d').' 08:00:00',
            'tugas' => 'Perbaikan Office',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'Router mati',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'technicians' => [$this->makeUserWithAllPopScope('teknisi')->id],
        ])->assertRedirect();

        $fopTask = FopTask::where('category', 'O-REQ')->firstOrFail();

        $this->actingAs($this->fopUser)
            ->delete(route('fop-tasks.destroy', $fopTask))
            ->assertRedirect(route('fop-tasks.index'));

        $this->assertDatabaseMissing('fop_tasks', ['id' => $fopTask->id]);
    }

    // ── UI: tombol Hapus disembunyikan/disable, tombol Detail Task ada ──

    /**
     * Yang dicek: form Hapus-nya tidak dirender — BUKAN sekadar URL-nya tidak muncul.
     * `fop-tasks.destroy` dan `fop-tasks.update` berbagi URI yang sama (beda verb:
     * DELETE vs PUT), dan sejak ADHOC-20 langkah 3 tombol Edit ikut merender URL
     * update itu di markup (target PUT harus datang dari server, bukan dirakit di
     * JS). Jadi assertDontSee(route('fop-tasks.destroy')) sekarang salah sasaran:
     * ia akan gagal karena tombol EDIT, bukan karena tombol Hapus.
     */
    public function test_table_disables_delete_button_for_survey_and_hides_form(): void
    {
        $fopTask = $this->makeSurveyTask();

        $response = $this->actingAs($this->fopUser)->get(route('fop-tasks.index'));

        $response->assertOk();
        $response->assertDontSee('Apakah Anda yakin ingin menghapus Task FOP ini?', false);
        $response->assertDontSee('name="_method" value="DELETE"', false);
        // URL update TETAP boleh (dan harus) ada — itu tombol Edit.
        $response->assertSee(route('fop-tasks.update', $fopTask), false);
    }

    public function test_table_disables_delete_button_for_ticket_origin_task(): void
    {
        $this->actingAs($this->helpdeskUser)->post(route('tickets.store'), [
            'type' => TaskType::CREQ->value,
            'customer_id' => $this->customer->id,
            'detail_keluhan' => 'Minta pindah lokasi ONT.',
            'priority' => 'Medium',
        ])->assertRedirect();

        $ticket = Ticket::first();

        // FopTask gak lagi auto-dibuat pas submit — eskalasi eksplisit ke FOP.
        $this->actingAs($this->helpdeskUser)
            ->post(route('tickets.escalate', $ticket), ['target' => 'fop'])
            ->assertRedirect();

        $ticket->refresh();

        $response = $this->actingAs($this->fopUser)->get(route('fop-tasks.index'));

        $response->assertOk();
        $response->assertSee('batalkan lewat Cancel', false);
        // Lihat catatan di test Survey di atas: destroy & update berbagi URI, jadi
        // yang dicek adalah markup form Hapus-nya, bukan URL-nya.
        $response->assertDontSee('Apakah Anda yakin ingin menghapus Task FOP ini?', false);
        $response->assertDontSee('name="_method" value="DELETE"', false);
    }

    public function test_table_shows_detail_task_link(): void
    {
        $fopTask = $this->makeSurveyTask();

        $this->actingAs($this->fopUser)
            ->get(route('fop-tasks.index'))
            ->assertOk()
            ->assertSee(route('fop-tasks.history.show', $fopTask->id), false);
    }

    // ── Format tugas "{CID}_{Nama}" berlaku juga buat SURVEY/PSB ──

    public function test_survey_auto_sync_uses_cid_and_name_format(): void
    {
        $this->customer->update([
            'status' => 'calon_pelanggan',
            'full_name' => 'Masudah Yuni Fitri',
        ]);

        // Trigger autoSyncAndCalculatePriority() lewat GET index (dipanggil di awal method).
        $this->actingAs($this->fopUser)->get(route('fop-tasks.index'))->assertOk();

        $fopTask = FopTask::where('category', 'SURVEY')->where('customer_id', $this->customer->id)->first();

        $this->assertNotNull($fopTask);
        $this->assertSame($this->customer->fresh()->display_id.'_Masudah Yuni Fitri', $fopTask->tugas);
    }
}
