<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Modal Tambah Task FOP (jalur manual, non-Ticketing) dulu mewajibkan pilih
 * teknisi. FOP sekarang boleh bikin task dulu tanpa teknisi lalu
 * menjadwalkan teknisinya belakangan lewat Edit.
 */
class FopTaskCreateTanpaTeknisiTest extends TestCase
{
    use RefreshDatabase;

    private User $fopUser;

    private User $technician;

    private Village $village;

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->fopUser = User::factory()->create(['role_id' => Role::where('code', 'fop')->first()->id]);
        $this->giveAllPopScope($this->fopUser);
        $this->technician = User::factory()->create([
            'role_id' => Role::where('code', 'teknisi')->first()->id,
            'status' => 'active',
        ]);

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
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category' => 'C-REQ',
            'task_date' => now()->format('Y-m-d H:i:s'),
            'tugas' => 'Request Tanpa Teknisi',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'Request khusus',
            'status' => 'terjadwal',
            'priority' => 'Medium',
        ], $overrides);
    }

    #[Test]
    public function task_fop_manual_boleh_dibuat_tanpa_teknisi_dan_jadi_draft(): void
    {
        $this->actingAs($this->fopUser)
            ->post(route('fop-tasks.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('fop-tasks.index'));

        $fopTask = FopTask::where('tugas', 'Request Tanpa Teknisi')->firstOrFail();

        // Tanpa teknisi belum ada yang "terjadwal" — status jujur = draft,
        // dan Task teknisi belum boleh kebentuk.
        $this->assertSame('draft', $fopTask->status->value);
        $this->assertNull($fopTask->task_id);
        $this->assertCount(0, $fopTask->technicians);
        $this->assertSame(0, Task::count());
    }

    #[Test]
    public function task_pending_tanpa_teknisi_tetap_pending(): void
    {
        $this->actingAs($this->fopUser)
            ->post(route('fop-tasks.store'), $this->payload([
                'status' => 'pending',
                'pending_reason' => 'Menunggu konfirmasi pelanggan',
                'client_request_date' => now()->addDays(3)->toDateString(),
            ]))
            ->assertSessionHasNoErrors();

        $fopTask = FopTask::where('tugas', 'Request Tanpa Teknisi')->firstOrFail();
        $this->assertSame('pending', $fopTask->status->value);
        $this->assertNull($fopTask->task_id);
    }

    #[Test]
    public function teknisi_dijadwalkan_belakangan_lewat_edit_membentuk_task(): void
    {
        $this->actingAs($this->fopUser)->post(route('fop-tasks.store'), $this->payload());
        $fopTask = FopTask::where('tugas', 'Request Tanpa Teknisi')->firstOrFail();

        $this->actingAs($this->fopUser)
            ->put(route('fop-tasks.update', $fopTask), [
                'task_date' => now()->addDay()->format('Y-m-d H:i:s'),
                'status' => 'draft',
                'technicians' => [$this->technician->id],
            ])
            ->assertSessionHasNoErrors();

        $fopTask->refresh();
        $this->assertSame('terjadwal', $fopTask->status->value);
        $this->assertNotNull($fopTask->task_id);
        $this->assertCount(1, $fopTask->technicians);
    }
}
