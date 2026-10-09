<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerBalanceMutation;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\CustomerBalanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Jalur batch (teknisi, kolektor, portal QR) harus memberi hasil yang sama
 * dengan form Bayar admin: transfer dengan rekening & pengirim, cicil,
 * lebih bayar masuk saldo, bayar pakai saldo, dan metode saldo penuh.
 * Semua jalur masuk memakai RecordsCollectorBatch + CollectorPaymentService,
 * jadi cukup diuji lewat endpoint teknisi.
 */
class CollectorBatchPaymentParityTest extends TestCase
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
            'code' => 'POP-PRT1',
            'pop_code' => 'PRT1',
            'registration_prefix' => 'CP1',
            'cid_prefix' => 'DP1',
            'name' => 'POP PRT1',
            'type' => 'cabang',
            'status' => 'active',
        ]);
        $this->teknisi = $this->createTeknisi();
    }

    private function createTeknisi(): User
    {
        $role = Role::where('code', 'teknisi')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->pop->id]);

        return $user;
    }

    private function createInvoice(string $code, float $total = 150000): Invoice
    {
        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. '.$code,
            'collector_id' => null,
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. '.$code,
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'monthly_price' => $total,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $total,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return Invoice::create([
            'invoice_number' => 'INV-'.$code,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => $total,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $total,
            'paid_amount' => 0,
            'remaining_amount' => $total,
            'invoice_status' => 'belum_dibayar',
        ]);
    }

    /**
     * @param  array<string, mixed>  $row  menimpa default baris
     */
    private function submit(string $key, array $row): TestResponse
    {
        return $this->actingAs($this->teknisi)->postJson(route('technician-payments.store'), [
            'idempotency_key' => $key,
            'rows' => [array_merge([
                'invoice_id' => $row['invoice_id'],
                'amount' => 150000,
                'payment_method' => 'cash',
                'collected_date' => '2026-06-20',
            ], $row)],
        ]);
    }

    #[Test]
    public function transfer_row_without_bank_account_is_rejected(): void
    {
        $invoice = $this->createInvoice('PRT-NOBANK');

        $this->submit('prt-nobank', ['invoice_id' => $invoice->id, 'payment_method' => 'transfer'])
            ->assertStatus(422)
            ->assertJsonFragment(['reason' => "{$invoice->invoice_number}: pilih rekening tujuan yang aktif untuk metode Transfer."]);

        $this->assertSame(0, Payment::query()->count());
    }

    #[Test]
    public function transfer_row_with_inactive_bank_account_is_rejected(): void
    {
        $invoice = $this->createInvoice('PRT-INACTIVE');
        $bank = BankAccount::factory()->inactive()->create();

        $this->submit('prt-inactive', [
            'invoice_id' => $invoice->id,
            'payment_method' => 'transfer',
            'bank_account_id' => $bank->id,
        ])->assertStatus(422);

        $this->assertSame(0, Payment::query()->count());
    }

    #[Test]
    public function transfer_row_snapshots_bank_account_and_sender_name(): void
    {
        $invoice = $this->createInvoice('PRT-TRF');
        $bank = BankAccount::factory()->create();

        $this->submit('prt-trf', [
            'invoice_id' => $invoice->id,
            'payment_method' => 'transfer',
            'bank_account_id' => $bank->id,
            'sender_name' => '  Budi Santoso  ',
        ])->assertOk()->assertJson(['success' => true]);

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('transfer', $payment->payment_method);
        $this->assertSame($bank->id, (int) $payment->bank_account_id);
        $this->assertSame($bank->bank_name, $payment->bank_name);
        $this->assertSame($bank->account_number, $payment->account_number);
        $this->assertSame('Budi Santoso', $payment->sender_name);
    }

    #[Test]
    public function cash_row_drops_bank_account_and_sender_name(): void
    {
        $invoice = $this->createInvoice('PRT-CASH');
        $bank = BankAccount::factory()->create();

        $this->submit('prt-cash', [
            'invoice_id' => $invoice->id,
            'payment_method' => 'cash',
            'bank_account_id' => $bank->id,
            'sender_name' => 'Budi',
        ])->assertOk();

        $payment = Payment::query()->firstOrFail();
        $this->assertNull($payment->bank_account_id);
        $this->assertNull($payment->bank_name);
        $this->assertNull($payment->sender_name);
    }

    #[Test]
    public function partial_payment_cicil_leaves_invoice_sebagian(): void
    {
        $invoice = $this->createInvoice('PRT-CICIL');

        $this->submit('prt-cicil', ['invoice_id' => $invoice->id, 'amount' => 50000])->assertOk();

        $invoice->refresh();
        $this->assertSame('sebagian', $invoice->invoice_status->value);
        $this->assertEqualsWithDelta(100000.0, (float) $invoice->remaining_amount, 0.001);
        $this->assertNull(Payment::query()->firstOrFail()->overpay_amount);
    }

    #[Test]
    public function overpayment_is_rejected_in_batch_so_it_is_recorded_only_through_admin(): void
    {
        $invoice = $this->createInvoice('PRT-LEBIH');

        $this->submit('prt-lebih', ['invoice_id' => $invoice->id, 'amount' => 200000])
            ->assertStatus(422)
            ->assertJsonFragment(['reason' => "{$invoice->invoice_number}: nominal tunai ditambah saldo melebihi sisa tagihan. Kelebihan hanya bisa dicatat lewat Tagihan admin."]);

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame('belum_dibayar', $invoice->fresh()->invoice_status->value);
        $this->assertEqualsWithDelta(0.0, app(CustomerBalanceService::class)->balance($invoice->customer), 0.001);
    }

    #[Test]
    public function full_balance_payment_without_cash_is_recorded_as_saldo(): void
    {
        $invoice = $this->createInvoice('PRT-SALDO');
        app(CustomerBalanceService::class)->creditWithoutPayment($invoice->customer, 150000, $this->pop->id, 'uji');

        $this->submit('prt-saldo', [
            'invoice_id' => $invoice->id,
            'amount' => 0,
            'use_balance_amount' => 150000,
        ])->assertOk();

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('saldo', $payment->payment_method);
        $this->assertSame(150000.0, (float) $payment->balance_used_amount);
        $this->assertSame('lunas', $invoice->fresh()->invoice_status->value);
        $this->assertEqualsWithDelta(0.0, app(CustomerBalanceService::class)->balance($invoice->customer), 0.001);
    }

    #[Test]
    public function balance_plus_cash_closes_invoice_and_debits_only_used_balance(): void
    {
        $invoice = $this->createInvoice('PRT-MIX');
        app(CustomerBalanceService::class)->creditWithoutPayment($invoice->customer, 50000, $this->pop->id, 'uji');

        $this->submit('prt-mix', [
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'use_balance_amount' => 50000,
        ])->assertOk();

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('cash', $payment->payment_method);
        $this->assertSame(150000.0, (float) $payment->amount);
        $this->assertSame(50000.0, (float) $payment->balance_used_amount);
        $this->assertNull($payment->overpay_amount);
        $this->assertSame('lunas', $invoice->fresh()->invoice_status->value);
        $this->assertEqualsWithDelta(0.0, app(CustomerBalanceService::class)->balance($invoice->customer), 0.001);
    }

    #[Test]
    public function balance_over_available_amount_rejects_whole_batch(): void
    {
        $invoice = $this->createInvoice('PRT-KURANG');
        app(CustomerBalanceService::class)->creditWithoutPayment($invoice->customer, 10000, $this->pop->id, 'uji');

        $this->submit('prt-kurang', [
            'invoice_id' => $invoice->id,
            'amount' => 0,
            'use_balance_amount' => 50000,
        ])->assertStatus(422);

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame('belum_dibayar', $invoice->fresh()->invoice_status->value);
    }

    #[Test]
    public function balance_used_on_one_invoice_cannot_exceed_its_remaining_amount(): void
    {
        $invoice = $this->createInvoice('PRT-CAP');
        app(CustomerBalanceService::class)->creditWithoutPayment($invoice->customer, 500000, $this->pop->id, 'uji');

        $this->submit('prt-cap', [
            'invoice_id' => $invoice->id,
            'amount' => 0,
            'use_balance_amount' => 200000,
        ])->assertStatus(422)
            ->assertJsonFragment(['reason' => "{$invoice->invoice_number}: nominal tunai ditambah saldo melebihi sisa tagihan. Kelebihan hanya bisa dicatat lewat Tagihan admin."]);

        $this->assertSame(0, Payment::query()->count());
    }

    #[Test]
    public function one_customer_balance_is_shared_across_invoices_in_the_same_batch(): void
    {
        $first = $this->createInvoice('PRT-SHARE');
        $second = $first->replicate(['invoice_number']);
        $second->invoice_number = 'INV-PRT-SHARE-2';
        // Satu periode tidak boleh punya dua tagihan bulanan per pelanggan (guard observer).
        $second->billing_period = '2026-05';
        $second->save();
        app(CustomerBalanceService::class)->creditWithoutPayment($first->customer, 100000, $this->pop->id, 'uji');

        $this->actingAs($this->teknisi)->postJson(route('technician-payments.store'), [
            'idempotency_key' => 'prt-share',
            'rows' => [
                ['invoice_id' => $first->id, 'amount' => 0, 'use_balance_amount' => 100000, 'payment_method' => 'cash', 'collected_date' => '2026-06-20'],
                ['invoice_id' => $second->id, 'amount' => 0, 'use_balance_amount' => 100000, 'payment_method' => 'cash', 'collected_date' => '2026-06-20'],
            ],
        ])->assertStatus(422)
            ->assertJsonFragment(['reason' => "{$second->invoice_number}: Saldo pelanggan tidak cukup. Saldo tersedia: Rp 0."]);

        $this->assertSame(0, Payment::query()->count());
    }

    #[Test]
    public function balances_for_customers_are_aggregated_in_one_pass(): void
    {
        $withBalance = $this->createInvoice('PRT-AGG-1');
        $withoutBalance = $this->createInvoice('PRT-AGG-2');
        $service = app(CustomerBalanceService::class);

        $service->creditWithoutPayment($withBalance->customer, 80000, $this->pop->id, 'uji');
        $service->creditWithoutPayment($withBalance->customer, 20000, $this->pop->id, 'uji');
        $balances = $service->balancesForCustomers([$withBalance->customer_id, $withoutBalance->customer_id]);

        $this->assertEqualsWithDelta(100000.0, $balances[$withBalance->customer_id], 0.001);
        $this->assertEqualsWithDelta(0.0, $balances[$withoutBalance->customer_id], 0.001);
    }

    #[Test]
    public function zero_cash_and_zero_balance_is_rejected(): void
    {
        $invoice = $this->createInvoice('PRT-NOL');

        $this->submit('prt-nol', ['invoice_id' => $invoice->id, 'amount' => 0])->assertStatus(422);

        $this->assertSame(0, Payment::query()->count());
    }

    /**
     * Dua baris untuk tagihan yang sama, masing-masing 80rb di tagihan 150rb:
     * tiap baris "muat" sendiri, tapi totalnya 160rb. Dulu validasi cuma cek
     * per baris → kelebihan 10rb diam-diam jadi saldo. Sekarang seluruh batch
     * ditolak, tak ada payment dan tak ada mutasi saldo.
     */
    #[Test]
    public function two_rows_for_same_invoice_cannot_exceed_remaining_amount(): void
    {
        $invoice = $this->createInvoice('PRT-DUA');

        $this->actingAs($this->teknisi)->postJson(route('technician-payments.store'), [
            'idempotency_key' => 'prt-dua-baris',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 80000, 'payment_method' => 'cash', 'collected_date' => '2026-06-20'],
                ['invoice_id' => $invoice->id, 'amount' => 80000, 'payment_method' => 'cash', 'collected_date' => '2026-06-20'],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, CustomerBalanceMutation::query()->count());
        $this->assertSame('belum_dibayar', $invoice->fresh()->invoice_status->value);
    }

    /**
     * Dua baris yang totalnya PAS sisa tagihan tetap sah — pembatasan ini
     * menolak kelebihan, bukan pembayaran terpisah untuk satu tagihan.
     */
    #[Test]
    public function two_rows_for_same_invoice_totalling_remaining_amount_are_accepted(): void
    {
        $invoice = $this->createInvoice('PRT-PAS');

        $this->actingAs($this->teknisi)->postJson(route('technician-payments.store'), [
            'idempotency_key' => 'prt-pas-baris',
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => 100000, 'payment_method' => 'cash', 'collected_date' => '2026-06-20'],
                ['invoice_id' => $invoice->id, 'amount' => 50000, 'payment_method' => 'cash', 'collected_date' => '2026-06-20'],
            ],
        ])->assertSuccessful();

        $this->assertSame(2, Payment::query()->count());
        $this->assertSame(0, CustomerBalanceMutation::query()->count());
        $this->assertSame('lunas', $invoice->fresh()->invoice_status->value);
    }
}
