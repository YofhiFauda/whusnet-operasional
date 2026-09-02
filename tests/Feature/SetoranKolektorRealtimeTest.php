<?php

namespace Tests\Feature;

use App\Enums\DepositStatus;
use App\Enums\ScopeType;
use App\Events\CollectorDepositUpdated;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\CollectorDepositService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Gejala yang dijaga: dua sisi saling menunggu tanpa saling tahu.
 *
 * Admin tidak tahu kolektor mana yang baru menyetor sampai dia memuat ulang
 * Worksheet; kolektor tidak tahu setorannya sudah diperiksa atau belum sampai
 * dia membuka Worklist — sementara saldonya bisa berubah kapan saja. Yang
 * menutup jarak itu `CollectorDepositUpdated`, disiarkan ke DUA audiens
 * sekaligus di setiap transisi.
 *
 * Sengaja menguji sampai ke daftar channel: satu event yang lupa satu sisi
 * bukan bug yang kelihatan — layarnya cuma diam, persis seperti sebelum fitur
 * ini ada.
 */
class SetoranKolektorRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private User $kolektor;

    private User $admin;

    private InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();
        $this->pop = $this->buatPop();
        $this->kolektor = $this->buatUser('kolektor');
        $this->admin = $this->buatUser('pop_admin');
    }

    public function test_setoran_diajukan_disiarkan_ke_admin_dan_kolektor(): void
    {
        Event::fake([CollectorDepositUpdated::class]);

        $this->buatPembayaran();

        app(CollectorDepositService::class)->submit($this->kolektor);

        Event::assertDispatched(CollectorDepositUpdated::class, function (CollectorDepositUpdated $event) {
            $channels = array_map(
                fn (PrivateChannel $c) => $c->name,
                $event->broadcastOn()
            );

            return $event->aksi === 'diajukan'
                && in_array('private-collector-activity.'.$this->pop->id, $channels, true)
                && in_array('private-App.Models.User.'.$this->kolektor->id, $channels, true);
        });
    }

    public function test_hasil_verifikasi_disiarkan_dengan_status_akhirnya(): void
    {
        $payment = $this->buatPembayaran();
        $deposit = app(CollectorDepositService::class)->submit($this->kolektor);

        Event::fake([CollectorDepositUpdated::class]);

        app(CollectorDepositService::class)->verify(
            $deposit,
            $this->admin,
            (float) $payment->amount,
        );

        Event::assertDispatched(CollectorDepositUpdated::class, function (CollectorDepositUpdated $event) {
            return $event->aksi === 'diverifikasi'
                && $event->deposit->status === DepositStatus::TERVERIFIKASI;
        });
    }

    public function test_payload_tidak_membawa_saldo(): void
    {
        // Saldo adalah angka TURUNAN (§11.2). Menyiarkannya lewat payload
        // berarti dua sumber kebenaran yang gampang menyimpang — klien harus
        // memuat ulang dan menghitung dari sumbernya.
        $payment = $this->buatPembayaran();
        $deposit = app(CollectorDepositService::class)->submit($this->kolektor);

        $payload = (new CollectorDepositUpdated($deposit, $this->kolektor, 'diajukan'))->broadcastWith();

        $this->assertArrayNotHasKey('balance', $payload);
        $this->assertArrayNotHasKey('saldo', $payload);
        $this->assertSame($deposit->deposit_number, $payload['deposit_number']);
        $this->assertSame($this->kolektor->name, $payload['collector_name']);
        $this->assertSame((float) $payment->amount, $payload['recorded_amount']);
    }

    public function test_hapus_buku_memberi_tahu_kolektor(): void
    {
        // Hapus buku menutup kewajiban kolektor — kabar yang menyangkut dirinya
        // langsung. Sebelumnya satu-satunya cara dia tahu adalah kebetulan
        // membuka Worklist dan melihat angkanya berubah sendiri.
        $payment = $this->buatPembayaran();
        $deposit = app(CollectorDepositService::class)->submit($this->kolektor);

        app(CollectorDepositService::class)->verify(
            $deposit,
            $this->admin,
            (float) $payment->amount - 50000,
            null,
            0.0,
            'Kurang setor, uang dipakai dulu.',
        );

        $owner = $this->loginAsAdmin();

        Event::fake([CollectorDepositUpdated::class]);

        app(CollectorDepositService::class)->writeOff($deposit->refresh(), $owner, 'Ditanggung kantor.');

        $this->assertSame(DepositStatus::DIHAPUS_BUKU, $deposit->refresh()->status);

        Event::assertDispatched(CollectorDepositUpdated::class, fn (CollectorDepositUpdated $e) => $e->aksi === 'dihapus_buku');

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->kolektor->id,
        ]);
    }

    private function buatPop(): Pop
    {
        return Pop::create([
            'code' => 'POP-RT1',
            'pop_code' => 'RT1',
            'registration_prefix' => 'R',
            'cid_prefix' => 'T',
            'name' => 'POP Realtime',
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function buatUser(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->pop->id]);
        $user->pops()->attach($this->pop->id);

        return $user;
    }

    private function buatPembayaran(float $amount = 150000): Payment
    {
        $customer = Customer::factory()->create([
            'status' => 'active',
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
            'collector_id' => $this->kolektor->id,
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'monthly_price' => $amount,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $amount,
            'activation_date' => '2026-01-01',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-RT-'.random_int(1000, 9999),
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => '2026-08',
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-10',
            'subtotal' => $amount,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $amount,
            'paid_amount' => $amount,
            'remaining_amount' => 0,
            'invoice_status' => 'lunas',
        ]);

        return Payment::create([
            'payment_number' => 'PAY-RT-'.random_int(1000, 9999),
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'payment_date' => now()->format('Y-m-d'),
            'collected_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
            'amount' => $amount,
            'received_by' => $this->kolektor->id,
            'collected_by' => $this->kolektor->id,
            'payment_status' => 'valid',
        ]);
    }
}
