<?php

namespace Tests\Feature;

use App\Events\TaskScheduled;
use App\Models\City;
use App\Models\District;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use App\Notifications\AppNotification;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi ADHOC-297 bug #1 (laporan user 2026-11-13): FOP menjadwalkan SATU
 * task, halaman Tasks Saya teknisi menampilkan 3 notifikasi + 3 kartu.
 *
 * Sisi server dikunci di sini: satu aksi jadwal = tepat satu AppNotification
 * + satu broadcast TaskScheduled per teknisi. Kalau angka ini naik, dobelnya
 * dari server, bukan dari listener klien.
 *
 * Sisi klien (penyebab aslinya) dikunci di bawah lewat sumber view: dengan
 * `<body hx-boost="true">`, tiap navigasi ke /tasks-saya membuat komponen
 * Alpine BARU dan dulu tiap komponen memanggil `Echo.private(...).listen()`
 * lagi di channel yang sama — buka halaman 3× = 3 handler = 3 toast; ketiganya
 * lolos cek `getElementById` sebelum `await fetch()` selesai = 3 kartu.
 */
class TaskSayaNotifikasiDanKartuTidakDobelTest extends TestCase
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
        $this->technician = User::factory()->create(['role_id' => Role::where('code', 'teknisi')->first()->id, 'status' => 'active']);

        $city = City::create(['name' => 'Ponorogo']);
        $district = District::create(['city_id' => $city->id, 'name' => 'Babadan']);
        $this->village = Village::create(['district_id' => $district->id, 'name' => 'Polorejo', 'postal_code' => '63491']);
        $this->pop = Pop::create(['name' => 'POP Polorejo', 'code' => 'POP-PLR', 'type' => 'branch', 'address' => 'Polorejo', 'status' => 'active', 'city_id' => $city->id]);
    }

    #[Test]
    public function fop_buat_task_terjadwal_kirim_satu_notifikasi_dan_satu_broadcast(): void
    {
        Notification::fake();
        Event::fake([TaskScheduled::class]);

        $this->actingAs($this->fopUser)->post(route('fop-tasks.store'), [
            'category' => 'C-REQ',
            'task_date' => now()->addHour()->format('Y-m-d H:i:s'),
            'tugas' => 'Pindah titik router',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'Request pelanggan',
            'status' => 'terjadwal',
            'priority' => 'Medium',
            'technicians' => [$this->technician->id],
        ])->assertSessionHasNoErrors();

        $this->assertSatuNotifikasiDanSatuBroadcast();
    }

    #[Test]
    public function fop_jadwalkan_task_draft_kirim_satu_notifikasi_dan_satu_broadcast(): void
    {
        $fopTask = FopTask::create([
            'task_number' => 'TFOP-DOBEL-0001',
            'task_date' => now()->addHour(),
            'category' => 'MTN',
            'tugas' => 'Gangguan LOS',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'LOS',
            'status' => 'draft',
            'priority' => 'High',
        ]);

        Notification::fake();
        Event::fake([TaskScheduled::class]);

        $this->actingAs($this->fopUser)->put(route('fop-tasks.update', $fopTask->id), [
            'category' => 'MTN',
            'task_date' => now()->addHour()->format('Y-m-d H:i:s'),
            'tugas' => 'Gangguan LOS',
            'village_id' => $this->village->id,
            'pop_id' => $this->pop->id,
            'issue' => 'LOS',
            'status' => 'terjadwal',
            'priority' => 'High',
            'technicians' => [$this->technician->id],
        ])->assertSessionHasNoErrors();

        $this->assertSatuNotifikasiDanSatuBroadcast();
    }

    #[Test]
    public function listener_echo_tasks_saya_dipasang_sekali_per_dokumen(): void
    {
        $source = $this->viewSource('tasks/own.blade.php');

        // Handler Echo dipasang sekali (flag global) dan meneruskan event ke
        // komponen yang SEDANG tampil — bukan menangkap `this` komponen lama.
        $this->assertStringContainsString('window.__technicianEchoBound', $source);
        $this->assertStringContainsString('window.__technicianNotifier', $source);

        // Guard in-flight: event ganda untuk task yang sama gak boleh fetch +
        // sisip kartu dua kali.
        $this->assertStringContainsString('pendingCards', $source);
    }

    #[Test]
    public function listener_echo_lonceng_notifikasi_dipasang_sekali_per_dokumen(): void
    {
        $source = $this->viewSource('components/notification-dropdown.blade.php');

        $this->assertStringContainsString('window.__notificationEchoBound', $source);
    }

    private function assertSatuNotifikasiDanSatuBroadcast(): void
    {
        Notification::assertSentToTimes($this->technician, AppNotification::class, 1);

        $toTechnician = Event::dispatched(TaskScheduled::class, function (TaskScheduled $event) {
            return collect($event->broadcastOn())
                ->contains(fn ($channel) => $channel->name === 'private-teknisi.'.$this->technician->id);
        });

        $this->assertCount(1, $toTechnician, 'Satu aksi jadwal FOP harus menyiarkan tepat satu TaskScheduled ke teknisi.');
    }

    private function viewSource(string $path): string
    {
        return file_get_contents(resource_path('views/'.$path));
    }
}
