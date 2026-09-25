<?php

namespace Tests\Feature;

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
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * QRIS dihapus dari PaymentMethod (permintaan user 2026-09-22, tidak pernah
 * dipakai operasional); metode Lainnya sekarang wajib menjelaskan metode apa
 * persisnya lewat field Catatan/keterangan.
 */
class PaymentMethodQrisRemovedAndLainnyaRequiresNoteTest extends TestCase
{
    use RefreshDatabase;

    private InternetPackage $package;

    private User $admin;

    private Pop $pop;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        // Tanggal bayar di tes ini hardcode Juni 2026. Sejak tutup buku otomatis
        // (ADHOC-96) bulan lewat terkunci, jadi waktu dibekukan di Juni.
        $this->travelTo(Carbon::parse('2026-06-20 10:00:00'));
        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();

        $role = Role::where('name', 'Admin')->firstOrFail();
        $this->admin = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $this->pop = $this->createPop('POP-QRIS-1', 'PQ1', 'POP QRIS Test');
        $this->admin->pops()->attach($this->pop->id);
        $scope = UserRoleScope::create(['user_id' => $this->admin->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->pop->id]);

        $this->invoice = $this->createInvoice($this->pop, 'INV-QRIS-001');
    }

    #[Test]
    public function qris_ditolak_sebagai_metode_pembayaran(): void
    {
        $response = $this->actingAs($this->admin)->post(route('invoices.payments.store', $this->invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'qris',
            'amount' => 50000,
        ]);

        $response->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('payments', 0);
    }

    #[Test]
    public function lainnya_tanpa_keterangan_ditolak(): void
    {
        $response = $this->actingAs($this->admin)->post(route('invoices.payments.store', $this->invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'lainnya',
            'amount' => 50000,
        ]);

        $response->assertSessionHasErrors('note');
        $this->assertDatabaseCount('payments', 0);
    }

    #[Test]
    public function lainnya_dengan_keterangan_tersimpan(): void
    {
        $response = $this->actingAs($this->admin)->post(route('invoices.payments.store', $this->invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'lainnya',
            'amount' => 50000,
            'note' => 'OVO an. Budi',
        ]);

        $response->assertRedirect(route('invoices.show', $this->invoice->id));
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $this->invoice->id,
            'payment_method' => 'lainnya',
            'note' => 'OVO an. Budi',
        ]);
    }

    #[Test]
    public function metode_selain_lainnya_tidak_wajib_catatan(): void
    {
        $response = $this->actingAs($this->admin)->post(route('invoices.payments.store', $this->invoice->id), [
            'payment_date' => '2026-06-13',
            'payment_method' => 'cash',
            'amount' => 50000,
        ]);

        $response->assertRedirect(route('invoices.show', $this->invoice->id));
        $this->assertDatabaseHas('payments', ['invoice_id' => $this->invoice->id, 'payment_method' => 'cash']);
    }

    protected function createPop(string $code, string $popCode, string $name): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $popCode,
            'registration_prefix' => substr($code, 0, 2),
            'cid_prefix' => substr($code, 0, 2),
            'name' => $name,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    protected function createInvoice(Pop $pop, string $invoiceNumber): Invoice
    {
        $customer = Customer::create([
            'customer_code' => str_replace('INV', 'C', $invoiceNumber),
            'full_name' => 'Pelanggan '.$invoiceNumber,
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Test QRIS',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Test QRIS',
            'village' => 'Desa Test',
            'district' => 'Kecamatan Test',
            'city' => 'Kota Test',
            'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => 'Paket Test',
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '10 Mbps',
            'monthly_price' => 150000,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => 150000,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return Invoice::create([
            'invoice_number' => $invoiceNumber,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
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
    }
}
