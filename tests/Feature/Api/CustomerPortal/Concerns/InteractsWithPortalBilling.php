<?php

namespace Tests\Feature\Api\CustomerPortal\Concerns;

use App\Enums\CustomerBalanceMutationType;
use App\Models\Customer;
use App\Models\CustomerBalanceMutation;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;

/**
 * Seeder minimal buat data billing (invoice/payment/mutasi saldo) di test
 * Fase 3 portal — TANPA `DatabaseSeeder` penuh (mahal, nyeed seluruh RBAC
 * yang gak relevan di sini). Pola FK wajib (`customer_service_id`,
 * `internet_package_id`) diambil dari
 * `tests/Feature/InvoiceStatusUpdatedBroadcastTest.php::createInvoice()`.
 */
trait InteractsWithPortalBilling
{
    protected function seedInternetPackage(): InternetPackage
    {
        return InternetPackage::create([
            'package_code' => 'PKT-'.random_int(1000, 9999),
            'name' => 'Paket Uji 20 Mbps',
            'category' => 'rumahan',
            'package_group' => 'reguler',
            'bandwidth_label' => '20 Mbps',
            'monthly_price' => 150000,
        ]);
    }

    protected function seedCustomerService(Customer $customer, InternetPackage $package, float $monthlyPrice = 150000): CustomerService
    {
        return CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => $monthlyPrice,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $monthlyPrice,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);
    }

    protected function seedInvoice(Customer $customer, array $overrides = []): Invoice
    {
        $package = $this->seedInternetPackage();
        $service = $this->seedCustomerService($customer, $package, (float) ($overrides['total_amount'] ?? 150000));

        return Invoice::create(array_merge([
            'invoice_number' => 'INV-TEST-'.random_int(100000, 999999),
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $package->id,
            'billing_period' => '2026-06',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'subtotal' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 150000,
            'paid_amount' => 0,
            'remaining_amount' => 150000,
            'invoice_status' => 'belum_dibayar',
        ], $overrides));
    }

    protected function seedPayment(Invoice $invoice, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'payment_number' => 'PAY-TEST-'.random_int(100000, 999999),
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'pop_id' => $invoice->pop_id,
            'payment_date' => '2026-06-10',
            'payment_method' => 'cash',
            'amount' => 150000,
            'payment_status' => 'valid',
        ], $overrides));
    }

    protected function seedBalanceMutation(Customer $customer, array $overrides = []): CustomerBalanceMutation
    {
        return CustomerBalanceMutation::create(array_merge([
            'customer_id' => $customer->id,
            'type' => CustomerBalanceMutationType::CREDIT->value,
            'amount' => 50000,
            'pop_id' => $customer->pop_id,
            'note' => 'Lebih bayar dari PAY-TEST-000001',
        ], $overrides));
    }
}
