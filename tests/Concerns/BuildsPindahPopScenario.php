<?php

namespace Tests\Concerns;

use App\Enums\InvoiceStatus;
use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Distribution;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\EffectiveAccessService;
use Illuminate\Support\Carbon;

/**
 * Data bantu skenario pindah Cabang (ADHOC-107,
 * docs/plan/rancangan-pindah-pop-lanjutan.md): JETIS (C) & SANDYA (D) masing-
 * masing dengan satu Mini POP + satu Distribusi, pelanggan aktif di JETIS,
 * tagihan & pembayaran per periode.
 *
 * Waktu dikunci di 2026-10-15 (bulan berjalan = 2026-10) supaya batas
 * "piutang" (billing_period < bulan berjalan) pasti dan test tidak berubah
 * arti saat bulan kalender berganti.
 */
trait BuildsPindahPopScenario
{
    protected Pop $jetis;

    protected Pop $miniJetis;

    protected Distribution $distJetis;

    protected Pop $sandya;

    protected Pop $miniSandya;

    protected Distribution $distSandya;

    protected const BULAN_INI = '2026-10';

    protected const BULAN_LALU = '2026-09';

    private int $invoiceSeq = 0;

    protected function setUpPindahPop(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 10:00:00'));

        $this->jetis = Pop::factory()->create(['pop_code' => 'C', 'cid_prefix' => 'C', 'name' => 'JETIS']);
        $this->miniJetis = Pop::factory()->create([
            'pop_code' => 'C1X', 'cid_prefix' => 'C', 'type' => 'mini_pop', 'parent_id' => $this->jetis->id, 'name' => 'Mini JETIS',
        ]);
        $this->distJetis = Distribution::create(['pop_id' => $this->miniJetis->id, 'code' => '4A', 'name' => 'Dist Jetis']);

        $this->sandya = Pop::factory()->create(['pop_code' => 'D', 'cid_prefix' => 'D', 'name' => 'SANDYA']);
        $this->miniSandya = Pop::factory()->create([
            'pop_code' => 'D2Y', 'cid_prefix' => 'D', 'type' => 'mini_pop', 'parent_id' => $this->sandya->id, 'name' => 'Mini SANDYA',
        ]);
        $this->distSandya = Distribution::create(['pop_id' => $this->miniSandya->id, 'code' => '6B', 'name' => 'Dist Sandya']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function pelangganAktifDiJetis(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'customer_code' => 'RQ000631',
            'cid' => 'C1X4ARQ000631',
            'full_name' => 'Pelanggan Pindah',
            'primary_phone' => '081234567891',
            'registration_date' => '2026-08-01',
            'status' => 'active',
            'pop_id' => $this->jetis->id,
            'mini_pop_id' => $this->miniJetis->id,
            'distribution_id' => $this->distJetis->id,
            'address' => 'Jl. Pindah No. 1',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payloadEdit(Customer $customer, array $overrides = []): array
    {
        return array_merge([
            'full_name' => $customer->full_name,
            'primary_phone' => $customer->primary_phone,
            'registration_date' => $customer->registration_date->toDateString(),
            'pop_id' => $customer->pop_id,
            'mini_pop_id' => $customer->mini_pop_id,
            'distribution_id' => $customer->distribution_id,
            'status' => $customer->status,
        ], $overrides);
    }

    protected function layanan(Customer $customer): CustomerService
    {
        $package = InternetPackage::firstOrCreate(['package_code' => 'PKT-PINDAH-107'], [
            'name' => 'Paket Pindah',
            'category' => 'Home Broadband',
            'package_group' => 'Net',
            'bandwidth_label' => '20 Mbps',
            'monthly_price' => 150000,
            'is_active' => true,
        ]);

        return CustomerService::firstOrCreate(['customer_id' => $customer->id], [
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '10 Mbps',
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-08-01',
            'due_date' => '2026-08-10',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);
    }

    protected function tagihan(Customer $customer, string $periode, InvoiceStatus $status, float $sisa = 150000): Invoice
    {
        $service = $this->layanan($customer);

        return Invoice::create([
            'invoice_number' => 'INV-107-'.(++$this->invoiceSeq),
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $service->internet_package_id,
            'billing_period' => $periode,
            'issue_date' => $periode.'-01',
            'due_date' => $periode.'-10',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 150000 - $sisa,
            'remaining_amount' => $sisa,
            'invoice_status' => $status->value,
        ]);
    }

    protected function pembayaran(Invoice $invoice, float $nominal, string $status = 'valid'): Payment
    {
        return Payment::create([
            'payment_number' => 'PAY-107-'.$invoice->id.'-'.random_int(1000, 9999),
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
            'amount' => $nominal,
            'payment_status' => $status,
        ]);
    }

    /**
     * User dengan role kustom ber-scope ke POP yang disebut, tanpa permission
     * apa pun — permission diberikan test lewat matrix role.
     *
     * @param  list<int>  $popIds
     */
    protected function userBerScope(string $roleCode, array $popIds): User
    {
        $role = Role::create(['code' => $roleCode, 'name' => 'Uji '.$roleCode, 'guard_name' => 'web']);
        $user = User::factory()->create(['status' => 'active', 'role_id' => $role->id]);
        $scope = UserRoleScope::create([
            'user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP->value,
        ]);
        foreach ($popIds as $popId) {
            $scope->targets()->create(['pop_id' => $popId]);
        }
        app(EffectiveAccessService::class)->clearCache($user);

        return $user;
    }
}
