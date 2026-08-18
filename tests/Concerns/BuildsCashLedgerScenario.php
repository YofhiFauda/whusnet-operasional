<?php

namespace Tests\Concerns;

use App\Enums\PaymentStatus;
use App\Enums\ScopeType;
use App\Models\CollectorDeposit;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\AdminCashBalanceService;
use App\Services\CollectorDepositService;

/**
 * Bahan baku skenario kas: POP, kolektor, admin, tagihan, penagihan, setoran.
 *
 * Dipakai bersama oleh test saldo, verifikasi, dan titik nol supaya ketiganya
 * berangkat dari data yang identik — kalau tiap file menyusun datanya sendiri,
 * perbedaan kecil di antaranya menyamarkan bug alih-alih menemukannya.
 */
trait BuildsCashLedgerScenario
{
    protected InternetPackage $package;

    protected Pop $pop;

    protected User $kolektor;

    protected User $admin;

    protected function bootCashLedgerScenario(string $popCode): void
    {
        $this->package = InternetPackage::query()->firstOrFail();
        $this->pop = $this->createPop($popCode);
        $this->kolektor = $this->createUser('kolektor', $this->pop);
        $this->admin = $this->createUser('pop_admin', $this->pop);
    }

    protected function createPop(string $code): Pop
    {
        return Pop::create([
            'code' => 'POP-'.$code,
            'pop_code' => $code,
            'registration_prefix' => 'K'.substr($code, -1),
            'cid_prefix' => 'S'.substr($code, -1),
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    protected function createUser(string $roleCode, ?Pop $pop): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        if ($pop) {
            $scope = UserRoleScope::create([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'scope_type' => ScopeType::SELECTED_POP,
            ]);
            UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);
            $user->pops()->attach($pop->id);
        }

        return $user;
    }

    /**
     * Owner — pemeriksa setoran kas. Dibuat sekali per test lalu dipakai ulang:
     * beberapa test butuh pandangan PEMERIKSA (`cash_deposit.view`), yang
     * sengaja TIDAK dimiliki admin penyetor (§10).
     */
    protected function owner(): User
    {
        return $this->ownerUser ??= User::factory()->create([
            'role_id' => Role::where('code', 'owner')->firstOrFail()->id,
            'status' => 'active',
        ]);
    }

    protected ?User $ownerUser = null;

    protected function createInvoice(Pop $pop, string $code, float $total, ?User $collector = null): Invoice
    {
        $customer = Customer::create([
            'customer_code' => $code,
            'full_name' => 'Pelanggan '.$code,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. '.$code,
            'collector_id' => $collector?->id,
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
            'pop_id' => $pop->id,
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

    /** Kolektor menagih lewat jalur resminya. */
    protected function collect(string $code, float $amount): Invoice
    {
        $invoice = $this->createInvoice($this->pop, $code, $amount, $this->kolektor);

        $this->actingAs($this->kolektor)->postJson(route('collector-worklist.pay'), [
            'idempotency_key' => 'pay-'.$code,
            'rows' => [
                ['invoice_id' => $invoice->id, 'amount' => $amount, 'payment_method' => 'cash', 'collected_date' => '2026-08-05'],
            ],
        ])->assertOk();

        return $invoice;
    }

    /**
     * Pembayaran manual di loket — tak pernah lewat kolektor.
     *
     * `$pemilikRute` cuma menentukan `customers.collector_id`; pembayarannya
     * tetap manual (`collected_by` null). Dipakai test yang perlu pelanggannya
     * TIDAK muncul di panel "belum di-assign" Worksheet Admin.
     */
    protected function payAtOffice(string $code, float $amount, string $method = 'cash', ?User $penerima = null, ?Pop $pop = null, ?User $pemilikRute = null): Payment
    {
        $pop ??= $this->pop;
        $invoice = $this->createInvoice($pop, $code, $amount, $pemilikRute);

        return Payment::create([
            'payment_number' => 'PAY-'.$code,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $pop->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => $method,
            'amount' => $amount,
            'received_by' => ($penerima ?? $this->admin)->id,
            'payment_status' => PaymentStatus::VALID->value,
        ]);
    }

    /** Kolektor setor, admin memverifikasi dengan nominal fisik tertentu. */
    protected function setorDanVerifikasi(float $uangFisik, ?string $note = null, ?User $verifikator = null): CollectorDeposit
    {
        $deposit = app(CollectorDepositService::class)->submit($this->kolektor->fresh());

        return app(CollectorDepositService::class)->verify(
            $deposit,
            ($verifikator ?? $this->admin)->fresh(),
            $uangFisik,
            null,
            0.0,
            $note,
        );
    }

    protected function saldo(?User $admin = null): float
    {
        return app(AdminCashBalanceService::class)->tunaiBelumDisetor(($admin ?? $this->admin)->fresh());
    }
}
