<?php

namespace Tests\Feature;

use App\Enums\CashDepositStatus;
use App\Enums\DepositStatus;
use App\Enums\PengajuanBarangStatus;
use App\Enums\StockRequestStatus;
use App\Models\CashDeposit;
use App\Models\CollectorDeposit;
use App\Models\PengajuanBarang;
use App\Models\Pop;
use App\Models\Role;
use App\Models\StockRequest;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\ActionQueueBadgeService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionQueueBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('code', $role)->firstOrFail()->id]);
    }

    private function pop(string $code): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $code,
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'Cabang '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function scope(User $user, Pop $pop): void
    {
        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $user->role_id,
            'scope_type' => 'selected_pop',
        ]);
        $scope->targets()->create(['pop_id' => $pop->id]);
    }

    public function test_stock_badge_counts_open_requests_and_disappears_after_fulfillment(): void
    {
        $owner = $this->user('owner');
        $branch = $this->pop('AQB1');
        $pending = StockRequest::create(['reference_number' => 'PST-AQB-1', 'cabang_pop_id' => $branch->id, 'status' => StockRequestStatus::PENDING, 'requested_by' => $owner->id]);
        $partial = StockRequest::create(['reference_number' => 'PST-AQB-2', 'cabang_pop_id' => $branch->id, 'status' => StockRequestStatus::PARTIAL, 'requested_by' => $owner->id]);

        $queue = app(ActionQueueBadgeService::class);
        $this->assertSame(2, $queue->stockRequests($owner)->count());
        $this->actingAs($owner)->get(route('warehouse.stock-requests.index', ['status' => 'open']))
            ->assertOk()->assertSee('data-badge="stock-request"', false)
            ->assertSee('PST-AQB-1')->assertSee('PST-AQB-2');

        $pending->update(['status' => StockRequestStatus::FULFILLED]);
        $partial->update(['status' => StockRequestStatus::FULFILLED]);
        $this->assertSame(0, $queue->stockRequests($owner)->count());
        $this->actingAs($owner)->get(route('warehouse.stock-requests.index', ['status' => 'open']))
            ->assertDontSee('data-badge="stock-request"', false);
    }

    public function test_financial_badges_follow_action_permission_pop_scope_and_self_check(): void
    {
        $owner = $this->user('owner');
        $admin = $this->user('pop_admin');
        $branch = $this->pop('AQB2');
        $other = $this->pop('AQB3');
        $this->scope($admin, $branch);

        CollectorDeposit::create(['deposit_number' => 'SET-AQB-1', 'collector_id' => $owner->id, 'pop_id' => $branch->id, 'status' => DepositStatus::MENUNGGU_VERIFIKASI, 'submitted_at' => now()]);
        CollectorDeposit::create(['deposit_number' => 'SET-AQB-2', 'collector_id' => $owner->id, 'pop_id' => $other->id, 'status' => DepositStatus::MENUNGGU_VERIFIKASI, 'submitted_at' => now()]);
        CollectorDeposit::create(['deposit_number' => 'SET-AQB-3', 'collector_id' => $admin->id, 'pop_id' => $branch->id, 'status' => DepositStatus::MENUNGGU_VERIFIKASI, 'submitted_at' => now()]);

        CashDeposit::create(['deposit_number' => 'KAS-AQB-1', 'depositor_id' => $admin->id, 'pop_id' => $branch->id, 'status' => CashDepositStatus::MENUNGGU_VERIFIKASI]);
        CashDeposit::create(['deposit_number' => 'KAS-AQB-2', 'depositor_id' => $owner->id, 'pop_id' => $branch->id, 'status' => CashDepositStatus::MENUNGGU_VERIFIKASI]);

        $queue = app(ActionQueueBadgeService::class);
        $this->assertSame(1, $queue->collectorDeposits($admin)->count());
        $this->assertSame(0, $queue->cashDeposits($admin)->count());
        $this->assertSame(1, $queue->collectorDeposits($owner)->count());
        $this->assertSame(1, $queue->cashDeposits($owner)->count());

        $this->actingAs($admin)->get(route('collector-worksheet.index', ['pending_deposits' => 1]))
            ->assertOk()->assertSee('data-badge="collector-deposit"', false)
            ->assertSee('SET-AQB-1')->assertDontSee('SET-AQB-2');
        $this->actingAs($owner)->get(route('cash-deposits.index', ['status' => 'menunggu']))
            ->assertOk()->assertSee('data-badge="cash-deposit"', false)
            ->assertSee('KAS-AQB-1')->assertDontSee('KAS-AQB-2');
    }

    public function test_pengajuan_barang_badge_counts_menunggu_fop_and_respects_pop_scope(): void
    {
        $fop = $this->user('fop');
        $fop->update(['is_pengajuan_barang_approver' => true]);
        $branch = $this->pop('AQB4');
        $other = $this->pop('AQB5');
        $this->scope($fop, $branch);

        $menunggu = PengajuanBarang::create(['nomor_request' => 'SPB-AQB-1', 'pengaju_id' => $fop->id, 'nama_kegiatan' => 'Uji badge', 'pop_id' => $branch->id, 'status' => PengajuanBarangStatus::MENUNGGU_FOP]);
        PengajuanBarang::create(['nomor_request' => 'SPB-AQB-2', 'pengaju_id' => $fop->id, 'nama_kegiatan' => 'Di luar scope', 'pop_id' => $other->id, 'status' => PengajuanBarangStatus::MENUNGGU_FOP]);
        PengajuanBarang::create(['nomor_request' => 'SPB-AQB-3', 'pengaju_id' => $fop->id, 'nama_kegiatan' => 'Sudah diputuskan', 'pop_id' => $branch->id, 'status' => PengajuanBarangStatus::DISETUJUI, 'decided_by' => $fop->id, 'decided_at' => now()]);

        $queue = app(ActionQueueBadgeService::class);
        $this->assertSame(1, $queue->pengajuanBarang($fop)->count());
        $this->assertTrue($queue->pengajuanBarang($fop)->pluck('id')->contains($menunggu->id));

        $this->actingAs($fop)->get(route('warehouse.pengajuan-barang.index'))
            ->assertOk()->assertSee('data-badge="pengajuan-barang"', false);
    }
}
