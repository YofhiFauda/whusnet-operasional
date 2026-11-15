<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use App\Notifications\AppPushNotification;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi ADHOC-297 (keputusan user 2026-11-14, opsi 2): payload Web Push
 * dulu cuma membawa ackUrl, jadi klik notifikasi "Jadwal Baru: TASK-…"
 * selalu membuka Dashboard (`/`), bukan task-nya. Sekarang AppPushNotification
 * membawa `url` (path relatif, sama dengan actionUrl lonceng) dan
 * public/sw.js membukanya.
 */
class KlikPushMembukaHalamanTujuanTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function payload_push_membawa_url_tujuan_dan_ack(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $payload = (new AppPushNotification('Jadwal Baru: TASK-1', 'Isi', '/tasks/12'))
            ->toWebPush($user, null)
            ->toArray();

        $this->assertSame('/tasks/12', $payload['data']['url']);
        $this->assertArrayHasKey('ackUrl', $payload['data']);
    }

    #[Test]
    public function tanpa_url_payload_tidak_membawa_kunci_url(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $payload = (new AppPushNotification('Judul', 'Isi'))->toWebPush($user, null)->toArray();

        $this->assertArrayNotHasKey('url', $payload['data']);
        $this->assertArrayHasKey('ackUrl', $payload['data']);
    }

    #[Test]
    public function push_jadwal_task_baru_menunjuk_halaman_task(): void
    {
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $fopUser = User::factory()->create(['role_id' => Role::where('code', 'fop')->first()->id]);
        $this->giveAllPopScope($fopUser);
        $technician = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->first()->id, 'status' => 'active']);

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Polorejo', 'postal_code' => '63491']);
        $pop = Pop::create(['name' => 'POP Polorejo', 'code' => 'POP-PLR', 'type' => 'branch', 'address' => 'Polorejo', 'status' => 'active', 'city_id' => $city->id]);

        Notification::fake();

        $this->actingAs($fopUser)->post(route('fop-tasks.store'), [
            'category' => 'MTN',
            'task_date' => now()->addHour()->format('Y-m-d H:i:s'),
            'tugas' => 'Gangguan LOS',
            'village_id' => $village->id,
            'pop_id' => $pop->id,
            'issue' => 'LOS',
            'status' => 'terjadwal',
            'priority' => 'High',
            'technicians' => [$technician->id],
        ])->assertSessionHasNoErrors();

        $taskId = FopTask::where('tugas', 'Gangguan LOS')->value('task_id');
        $this->assertNotNull($taskId);

        Notification::assertSentTo(
            $technician,
            AppPushNotification::class,
            fn (AppPushNotification $push) => $push->url === route('tasks.show', $taskId, false)
        );
    }

    #[Test]
    public function service_worker_membuka_url_payload_dalam_origin_sendiri(): void
    {
        $source = file_get_contents(public_path('sw.js'));

        // Path relatif di-resolve ke absolut — client.url selalu absolut.
        $this->assertStringContainsString('new URL(rawUrl, self.location.origin)', $source);
        // Link ke origin lain ditolak.
        $this->assertStringContainsString('resolved.origin === self.location.origin', $source);
        // Tab Whusnet lain TIDAK dipakai ulang lewat navigate() — itu melewati
        // beforeunload dan membuang isian yang belum dikirim (review
        // ADHOC-297 #2). Tab tujuan yang sudah terbuka cukup difokuskan.
        $this->assertStringNotContainsString('.navigate(', $source);
        $this->assertStringContainsString('client.url === targetUrl', $source);
        $this->assertStringContainsString('clients.openWindow(targetUrl)', $source);
    }
}
