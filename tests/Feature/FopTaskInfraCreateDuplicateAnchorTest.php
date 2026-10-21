<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi: membuat Task FOP O-REQ/INFR-REQ dengan teknisi dari papan FOP
 * dulu 500 (UNIQUE fop_tasks.task_id). `TaskService::create()` tanpa
 * `$sourceFopTask` membuatkan anchor FopTask KEDUA untuk task O-REQ/INFR,
 * lalu `store()` menulis task_id yang sama ke FopTask asli.
 */
class FopTaskInfraCreateDuplicateAnchorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function kategoriInfrastruktur(): array
    {
        return ['O-REQ' => ['O-REQ'], 'INFR REQ' => ['INFR REQ']];
    }

    #[Test]
    #[DataProvider('kategoriInfrastruktur')]
    public function membuat_task_fop_infrastruktur_dengan_teknisi_menghasilkan_satu_fop_task(string $category): void
    {
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $fop = User::factory()->create(['role_id' => Role::where('code', 'fop')->firstOrFail()->id]);
        $this->giveAllPopScope($fop);
        $technician = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->firstOrFail()->id, 'status' => 'active']);
        $pop = Pop::factory()->create(['type' => 'cabang']);
        $village = Village::create([
            'district_id' => District::create(['city_id' => City::create(['name' => 'Kota'])->id, 'name' => 'Kec'])->id,
            'name' => 'Desa', 'postal_code' => '63491',
        ]);

        $this->actingAs($fop)->post(route('fop-tasks.store'), [
            'category' => $category,
            'task_date' => now()->format('Y-m-d H:i:s'),
            'tugas' => 'Tarik kabel feeder',
            'village_id' => $village->id,
            'pop_id' => $pop->id,
            'issue' => 'Jalur baru',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'technicians' => [$technician->id],
        ])->assertRedirect(route('fop-tasks.index'));

        $this->assertSame(1, FopTask::count());
        $this->assertNotNull(FopTask::firstOrFail()->task_id);
    }
}
