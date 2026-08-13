<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Events\CollectorActivityUpdated;
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
use Database\Seeders\DatabaseSeeder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Gejala yang dijaga: aktivitas kas DI LUAR siklus setoran mengubah angka
 * orang lain tanpa suara.
 *
 * Tiga kejadian, semuanya pernah senyap:
 *   - kolektor mencatat pembayaran → saldonya NAIK, Worksheet admin diam;
 *   - pembayaran ditolak → saldonya TURUN, Worklist diam;
 *   - pelanggan di-assign/dilepas → rutenya berubah, TANPA notifikasi sama
 *     sekali. Yang terakhir paling berbahaya: pelanggan yang dilepas setelah
 *     kolektor berangkat berarti dia menagih orang yang bukan tanggungannya.
 */
class AktivitasKasKolektorRealtimeTest extends TestCase
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
        $this->pop = Pop::create([
            'code' => 'POP-AK1', 'pop_code' => 'AK1', 'registration_prefix' => 'A',
            'cid_prefix' => 'K', 'name' => 'POP Aktivitas', 'type' => 'cabang', 'status' => 'active',
        ]);
        $this->kolektor = $this->buatUser('kolektor');
        $this->admin = $this->buatUser('admin');
    }

    public function test_kolektor_mencatat_pembayaran_disiarkan_ke_dua_sisi(): void
    {
        $invoice = $this->buatTagihan();

        Event::fake([CollectorActivityUpdated::class]);

        $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'uji-aktivitas-1',
            'rows' => [[
                'invoice_id' => $invoice->id,
                'amount' => 150000,
                'payment_method' => 'cash',
                'collected_date' => now()->format('Y-m-d'),
            ]],
        ])->assertOk();

        Event::assertDispatched(CollectorActivityUpdated::class, function (CollectorActivityUpdated $e) {
            $channels = array_map(fn (PrivateChannel $c) => $c->name, $e->broadcastOn());

            return $e->aksi === 'pembayaran_dicatat'
                && $e->jumlah === 1
                && (int) $e->total === 150000
                && in_array('private-collector-activity.'.$this->pop->id, $channels, true)
                && in_array('private-App.Models.User.'.$this->kolektor->id, $channels, true);
        });
    }

    public function test_assign_pelanggan_memberi_tahu_kolektor(): void
    {
        $customer = $this->buatPelanggan();

        Event::fake([CollectorActivityUpdated::class]);

        $this->actingAs($this->admin)
            ->post(route('collector-worksheet.assign', $this->kolektor), ['customer_ids' => [$customer->id]])
            ->assertRedirect();

        Event::assertDispatched(
            CollectorActivityUpdated::class,
            fn (CollectorActivityUpdated $e) => $e->aksi === 'pelanggan_diassign' && $e->keterangan === $customer->full_name
        );

        // Notifikasi in-app WAJIB ikut ada — siaran realtime cuma sampai ke
        // layar yang sedang terbuka, sementara kolektor biasanya sedang di jalan.
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->kolektor->id]);
    }

    public function test_lepas_pelanggan_memberi_tahu_kolektor(): void
    {
        $customer = $this->buatPelanggan();
        $customer->update(['collector_id' => $this->kolektor->id]);

        Event::fake([CollectorActivityUpdated::class]);

        $this->actingAs($this->admin)
            ->post(route('collector-worksheet.release', ['collector' => $this->kolektor, 'customer' => $customer]))
            ->assertRedirect();

        Event::assertDispatched(
            CollectorActivityUpdated::class,
            fn (CollectorActivityUpdated $e) => $e->aksi === 'pelanggan_dilepas' && $e->keterangan === $customer->full_name
        );

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->kolektor->id]);
    }

    public function test_pembayaran_ditolak_disiarkan_ke_kolektornya(): void
    {
        $invoice = $this->buatTagihan();

        $payment = Payment::create([
            'payment_number' => 'PAY-AK-'.random_int(1000, 9999),
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $this->pop->id,
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
            'amount' => 150000,
            'received_by' => $this->kolektor->id,
            'collected_by' => $this->kolektor->id,
            'payment_status' => 'valid',
        ]);

        Event::fake([CollectorActivityUpdated::class]);

        $this->loginAsAdmin();

        $this->post(route('payments.reject', $payment), ['reject_reason' => 'Uang tidak pernah sampai kantor.'])
            ->assertRedirect();

        Event::assertDispatched(
            CollectorActivityUpdated::class,
            fn (CollectorActivityUpdated $e) => $e->aksi === 'pembayaran_ditolak'
                && $e->collector->id === $this->kolektor->id
                && $e->keterangan === $payment->payment_number
        );
    }

    public function test_pembayaran_non_kolektor_tidak_menyiarkan_apa_pun(): void
    {
        // Pembayaran yang diterima langsung di kantor tak punya `collected_by`,
        // jadi tak ada saldo kolektor yang berubah — menyiarkannya cuma bikin
        // bising di layar orang yang tidak berkepentingan.
        $invoice = $this->buatTagihan();

        $payment = Payment::create([
            'payment_number' => 'PAY-AK-'.random_int(1000, 9999),
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $this->pop->id,
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
            'amount' => 150000,
            'received_by' => $this->admin->id,
            'payment_status' => 'valid',
        ]);

        Event::fake([CollectorActivityUpdated::class]);

        $this->loginAsAdmin();
        $this->post(route('payments.reject', $payment), ['reject_reason' => 'Salah input.'])->assertRedirect();

        Event::assertNotDispatched(CollectorActivityUpdated::class);
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

    private function buatPelanggan(): Customer
    {
        return Customer::factory()->create([
            'status' => 'active',
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
        ]);
    }

    private function buatTagihan(): Invoice
    {
        $customer = $this->buatPelanggan();
        $customer->update(['collector_id' => $this->kolektor->id]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-01-01',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return Invoice::create([
            'invoice_number' => 'INV-AK-'.random_int(1000, 9999),
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => '2026-08',
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-10',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ]);
    }
}
