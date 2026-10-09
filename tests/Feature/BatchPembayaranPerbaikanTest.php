<?php

namespace Tests\Feature;

use App\Enums\CollectorRole;
use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\CollectorPaymentService;
use App\Services\NumberSequenceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Perbaikan dari review batch pembayaran:
 *  1. baris yang gagal tidak memakan kuota tagihan/saldo baris berikutnya;
 *  2. tagihan periode mendatang ditolak di semua jalur (bukan cuma Portal);
 *  3. race idempotensi: error SQL tidak bocor ke klien;
 *  4. urutan auto-saldo diambil atomik dari NumberSequenceService.
 */
class BatchPembayaranPerbaikanTest extends TestCase
{
    use RefreshDatabase;

    private InternetPackage $package;

    private Pop $pop;

    private User $teknisi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();

        $this->pop = Pop::create([
            'code' => 'POP-BPP', 'pop_code' => 'BPP', 'registration_prefix' => 'C', 'cid_prefix' => 'B',
            'name' => 'POP Batch Perbaikan', 'type' => 'cabang', 'status' => 'active',
        ]);

        $role = Role::where('code', 'teknisi')->firstOrFail();
        $this->teknisi = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $this->teknisi->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->pop->id]);
    }

    private function tagihan(string $kode, float $total = 150000, string $periode = '2026-06'): Invoice
    {
        $customer = Customer::create([
            'customer_code' => $kode, 'full_name' => 'Pelanggan '.$kode, 'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01', 'status' => 'active', 'data_completeness_status' => 'siap_billing',
            'pop_id' => $this->pop->id, 'internet_package_id' => $this->package->id, 'address' => 'Jl. '.$kode,
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id, 'full_address' => 'Jl. '.$kode,
            'village' => 'Desa Test', 'district' => 'Kecamatan Test', 'city' => 'Kota Test', 'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id, 'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name, 'monthly_price' => $total, 'discount' => 0, 'ppn' => 0,
            'total_monthly_bill' => $total, 'activation_date' => '2026-06-01', 'due_date' => '2026-06-15',
            'service_status' => 'aktif', 'billing_status' => 'active',
        ]);

        return Invoice::create([
            'invoice_number' => 'INV-'.$kode, 'invoice_type' => 'bulanan',
            'customer_id' => $customer->id, 'pop_id' => $this->pop->id,
            'customer_service_id' => $service->id, 'internet_package_id' => $this->package->id,
            'billing_period' => $periode, 'issue_date' => '2026-06-01', 'due_date' => '2026-06-15',
            'subtotal' => $total, 'discount' => 0, 'ppn' => 0, 'total_amount' => $total,
            'paid_amount' => 0, 'remaining_amount' => $total, 'invoice_status' => 'belum_dibayar',
        ]);
    }

    #[Test]
    public function baris_gagal_tidak_memakan_kuota_tagihan_baris_berikutnya(): void
    {
        $invoice = $this->tagihan('BPP-KUOTA');

        $failures = app(CollectorPaymentService::class)->validateRows($this->teknisi, [
            // Gagal: transfer tanpa rekening aktif.
            ['invoice_id' => $invoice->id, 'amount' => 100000, 'payment_method' => 'transfer', 'collected_date' => '2026-06-20'],
            // Valid: sisa 150.000 harus masih utuh untuk baris ini.
            ['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => '2026-06-20'],
        ], $this->teknisi, CollectorRole::TEKNISI);

        $this->assertCount(1, $failures, 'Hanya baris transfer yang gagal.');
        $this->assertStringContainsString('rekening', $failures[0]['reason']);
    }

    #[Test]
    public function tagihan_periode_mendatang_ditolak_di_jalur_teknisi(): void
    {
        $invoice = $this->tagihan('BPP-FUTURE', 150000, now()->addMonth()->format('Y-m'));

        $failures = app(CollectorPaymentService::class)->validateRows($this->teknisi, [
            ['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => now()->toDateString()],
        ], $this->teknisi, CollectorRole::TEKNISI);

        $this->assertCount(1, $failures);
        $this->assertStringContainsString('periode tagihan belum berjalan', $failures[0]['reason']);
    }

    #[Test]
    public function error_sql_tidak_bocor_ke_klien_saat_batch_gagal_tersimpan(): void
    {
        $invoice = $this->tagihan('BPP-SQL');

        $this->partialMock(CollectorPaymentService::class, function ($mock) {
            $mock->shouldReceive('record')->andThrow(new QueryException(
                'mysql',
                'insert into `payments` (`idempotency_key`) values (?)',
                ['x'],
                new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry'),
            ));
        });

        $response = $this->actingAs($this->teknisi)->postJson(route('technician-payments.store'), [
            'idempotency_key' => 'bpp-race-001',
            'rows' => [['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => now()->toDateString()]],
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('insert into', $response->getContent());
        $this->assertStringNotContainsString('Duplicate', $response->getContent());
    }

    /**
     * Wildcard `%` di pencarian teknisi dibuang. Dulu `%%%` cocok dengan hampir
     * semua pelanggan dalam POP scope.
     */
    #[Test]
    public function pencarian_teknisi_tidak_terpengaruh_wildcard(): void
    {
        $this->tagihan('BPP-WILD');

        $this->actingAs($this->teknisi)
            ->getJson(route('technician-payments.search', ['q' => '%%%']))
            ->assertOk()
            ->assertJsonPath('customers', []);

        $this->actingAs($this->teknisi)
            ->getJson(route('technician-payments.search', ['q' => 'Pelanggan BPP-WILD']))
            ->assertOk()
            ->assertJsonCount(1, 'customers');
    }

    #[Test]
    public function urutan_auto_saldo_naik_atomik_per_invoice(): void
    {
        $invoice = $this->tagihan('BPP-SEQ');
        $numbers = app(NumberSequenceService::class);

        $this->assertSame(1, $numbers->autoSaldoOrdinal($invoice));
        $this->assertSame(2, $numbers->autoSaldoOrdinal($invoice));

        $lainnya = $this->tagihan('BPP-SEQ2');
        $this->assertSame(1, $numbers->autoSaldoOrdinal($lainnya), 'Urutan dihitung per invoice.');
    }
}
