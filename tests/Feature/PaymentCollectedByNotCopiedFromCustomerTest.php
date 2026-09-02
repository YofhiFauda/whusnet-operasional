<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
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
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `payments.collected_by` TIDAK boleh disalin otomatis dari
 * `customers.collector_id` — diisi sesuai JALUR MASUK pembayaran, bukan
 * rute permanen pelanggan. Contoh kritis (docs/plan/analisa-billing-
 * tagihan-pembayaran-kolektor.md §B-3): pelanggan rutinnya ditagih kolektor
 * A, tapi bulan ini bayar transfer sendiri lewat halaman Tagihan —
 * payment itu `collected_by = null`, BUKAN A. Kalau disalin buta, laporan
 * setoran A jadi bohong (mencatat uang yang tak pernah dia tagih).
 */
class PaymentCollectedByNotCopiedFromCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_customer_with_assigned_collector_paying_directly_has_null_collected_by(): void
    {
        $package = InternetPackage::query()->firstOrFail();
        $pop = Pop::create([
            'code' => 'POP-CB1',
            'pop_code' => 'CB1',
            'registration_prefix' => 'CC',
            'cid_prefix' => 'DC',
            'name' => 'POP Collected By Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $kolektorRole = Role::where('code', 'kolektor')->firstOrFail();
        $kolektorA = User::factory()->create(['role_id' => $kolektorRole->id, 'status' => 'active']);

        $customer = Customer::create([
            'customer_code' => 'C-CB-0001',
            'full_name' => 'Pelanggan Bayar Sendiri',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Collected By Test',
            'collector_id' => $kolektorA->id, // rute permanen: rutin ditagih A
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Collected By Test',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-CB-0001',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
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
        ]);

        $adminRole = Role::where('name', 'POP Admin')->firstOrFail();
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => 'active']);
        $admin->pops()->attach($pop->id);
        $scope = UserRoleScope::create([
            'user_id' => $admin->id,
            'role_id' => $adminRole->id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $pop->id]);

        // Pelanggan bayar TRANSFER SENDIRI lewat halaman Tagihan (jalur
        // non-kolektor) — bulan ini bukan A yang menagih, meski A adalah
        // kolektor rutinnya.
        $response = $this->actingAs($admin)->post(route('invoices.payments.store', $invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'transfer',
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'amount' => 150000,
        ]);

        $response->assertRedirect(route('invoices.show', $invoice->id));

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();

        // Titik kritis: collected_by TIDAK ikut collector_id customer.
        $this->assertNull($payment->collected_by);
        $this->assertNotEquals($kolektorA->id, $payment->collected_by);

        // Rute permanen pelanggan tetap utuh, tak ikut berubah.
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'collector_id' => $kolektorA->id]);
    }
}
