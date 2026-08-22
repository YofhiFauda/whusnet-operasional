<?php

namespace Tests\Feature;

use App\Events\CashDepositUpdated;
use App\Models\Role;
use App\Models\User;
use App\Services\CashDepositService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BuildsCashLedgerScenario;
use Tests\TestCase;

/**
 * Setoran Kas Admin → Owner/Bank sebelumnya gak punya broadcast realtime sama
 * sekali — halaman Setoran Kas (`cash-deposits/index.blade.php`) butuh reload
 * manual buat lihat setoran baru/hasil pemeriksaan. `CashDepositUpdated`
 * menutup gap itu, pola sama persis `CollectorDepositUpdated` (satu tingkat
 * di bawahnya, kolektor→admin).
 */
class CashDepositBroadcastingTest extends TestCase
{
    use BuildsCashLedgerScenario;
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->bootCashLedgerScenario('BRD1');
        $this->owner = User::factory()->create([
            'role_id' => Role::where('code', 'owner')->firstOrFail()->id,
            'status' => 'active',
        ]);
    }

    public function test_submit_broadcasts_to_cash_deposits_and_depositor_channel(): void
    {
        Event::fake([CashDepositUpdated::class]);

        $this->payAtOffice('BRD1', 100000);
        $admin = $this->admin->fresh();

        $deposit = app(CashDepositService::class)->submit($admin, ['channel' => 'tunai_brankas']);

        Event::assertDispatched(CashDepositUpdated::class, function ($event) use ($deposit, $admin) {
            $channelNames = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();

            return $event->deposit->id === $deposit->id
                && $event->aksi === 'diajukan'
                && in_array('private-cash-deposits', $channelNames, true)
                && in_array('private-App.Models.User.'.$admin->id, $channelNames, true);
        });
    }

    public function test_verify_broadcasts_updated_status(): void
    {
        Event::fake([CashDepositUpdated::class]);

        $this->payAtOffice('BRD1', 100000);
        $deposit = app(CashDepositService::class)->submit($this->admin->fresh(), ['channel' => 'tunai_brankas']);

        app(CashDepositService::class)->verify($deposit, $this->owner, 100000);

        Event::assertDispatched(CashDepositUpdated::class, function ($event) use ($deposit) {
            return $event->deposit->id === $deposit->id
                && $event->aksi === 'diverifikasi'
                && $event->broadcastWith()['status'] === 'terverifikasi';
        });
    }

    public function test_write_off_broadcasts_to_depositor(): void
    {
        Event::fake([CashDepositUpdated::class]);

        $this->payAtOffice('BRD1', 100000);
        $deposit = app(CashDepositService::class)->submit($this->admin->fresh(), ['channel' => 'tunai_brankas']);
        $verified = app(CashDepositService::class)->verify($deposit, $this->owner, 80000, 'Kurang setor, dikonfirmasi hilang.');

        app(CashDepositService::class)->writeOff($verified, $this->owner, 'Kerugian diakui, tidak bisa ditagih ke admin.');

        Event::assertDispatched(CashDepositUpdated::class, function ($event) use ($deposit) {
            return $event->deposit->id === $deposit->id && $event->aksi === 'ditutup_selisih';
        });
    }
}
