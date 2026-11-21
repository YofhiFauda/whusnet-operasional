<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\IsolationAdminDecision;
use App\Exceptions\IsolationExecutionException;
use App\Models\AuditLog;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\CustomerStatusLog;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\IsolationCase;
use App\Models\IsolationEvent;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\Village;
use App\Services\IsolationCaseService;
use App\Services\IsolationExecutionService;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PonorogoRegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubscriptionStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `IsolationExecutionService::suspendFromWebA()`
 * (docs/superpowers/specs/2026-11-20-webhook-isolir-web-a-design.md) —
 * berdiri sendiri dari `CustomerController::toggleSuspend()`, directional
 * (bukan toggle), idempotent.
 */
class IsolationExecutionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Pop $pop;

    protected InternetPackage $package;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SubscriptionStatusSeeder::class);
        $this->seed(InternetPackageSeeder::class);
        $this->seed(PonorogoRegionSeeder::class);

        $this->pop = Pop::create([
            'name' => 'POP Ponorogo Kota',
            'code' => 'PNO-01',
            'pop_code' => 'PNO',
            'cid_prefix' => 'P',
            'type' => 'branch',
            'status' => 'active',
        ]);

        $this->package = InternetPackage::first();

        $role = Role::where('name', 'Admin')->firstOrFail();
        $this->adminUser = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        UserRoleScope::create([
            'user_id' => $this->adminUser->id,
            'role_id' => $this->adminUser->role_id,
            'scope_type' => 'all_pop',
        ]);
    }

    protected function createCustomer(string $status = 'active'): Customer
    {
        $city = City::query()->where('name', 'Ponorogo')->firstOrFail();
        $district = District::query()->where('city_id', $city->id)->firstOrFail();
        $village = Village::query()->where('district_id', $district->id)->firstOrFail();

        static $seq = 0;
        $seq++;

        $customer = Customer::create([
            'customer_code' => 'RQ'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
            'cid' => 'P00RQ'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
            'full_name' => 'Pelanggan Eksekusi '.$seq,
            'gender' => 'Laki-laki',
            'primary_phone' => '081234567890',
            'registration_date' => '2026-06-01',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Jl. Diponegoro No. 10',
            'data_completeness_status' => 'siap_billing',
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Diponegoro No. 10',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'service_status' => $status === 'active' ? 'aktif' : 'isolir',
            'billing_status' => 'active',
            'monthly_price' => $this->package->monthly_price,
            'total_monthly_bill' => $this->package->monthly_price,
            'billing_cycle' => 'monthly',
        ]);

        return $customer;
    }

    protected function createOverdueInvoice(Customer $customer, string $periode = '2026-08'): Invoice
    {
        return Invoice::create([
            'invoice_number' => 'INV-PTG-'.$customer->id.'-'.$periode,
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $customer->pop_id,
            'customer_service_id' => $customer->customerService->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => $periode,
            'issue_date' => $periode.'-01',
            'due_date' => $periode.'-10',
            'subtotal' => 100000,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => 100000,
            'paid_amount' => 0,
            'remaining_amount' => 100000,
            'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
        ]);
    }

    protected function makeReadyCase(Customer $customer): IsolationCase
    {
        $this->createOverdueInvoice($customer, '2026-08');
        $this->artisan('isolir:sync-cases')->assertSuccessful();

        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();
        app(IsolationCaseService::class)->setAdminDecision($case, IsolationAdminDecision::EXECUTE, $this->adminUser, null);

        return $case->refresh();
    }

    #[Test]
    public function suspend_from_web_a_sukses_untuk_kasus_ready_for_isolation(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $case = $this->makeReadyCase($customer);

        $result = app(IsolationExecutionService::class)->suspendFromWebA($case);

        $this->assertFalse($result['already_suspended']);
        $this->assertSame('suspended', $customer->refresh()->status);
        $this->assertSame('isolir', $customer->customerService->refresh()->service_status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $customer->id,
            'action' => 'isolir',
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('isolation_events', [
            'event_type' => 'isolation_succeeded',
            'actor_type' => 'external_web_a',
            'actor_id' => null,
        ]);

        $this->travelBack();
    }

    #[Test]
    public function suspend_from_web_a_idempotent_kalau_sudah_suspended(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $case = $this->makeReadyCase($customer);

        app(IsolationExecutionService::class)->suspendFromWebA($case);
        $auditCountBefore = AuditLog::where('auditable_id', $customer->id)->count();
        $eventCountBefore = IsolationEvent::where('isolation_case_id', $case->id)->count();
        $statusLogCountBefore = CustomerStatusLog::where('customer_id', $customer->id)->count();

        $result = app(IsolationExecutionService::class)->suspendFromWebA($case->refresh());

        $this->assertTrue($result['already_suspended']);
        $this->assertSame($auditCountBefore, AuditLog::where('auditable_id', $customer->id)->count());
        $this->assertSame($eventCountBefore, IsolationEvent::where('isolation_case_id', $case->id)->count());
        $this->assertSame($statusLogCountBefore, CustomerStatusLog::where('customer_id', $customer->id)->count());

        $this->travelBack();
    }

    #[Test]
    public function suspend_from_web_a_menolak_kasus_belum_ready(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $this->createOverdueInvoice($customer, '2026-08');
        $this->artisan('isolir:sync-cases')->assertSuccessful();
        // Kasus masih WAITING_ISOLATION — belum diputuskan Admin.
        $case = IsolationCase::where('service_id', $customer->customerService->id)->first();

        $this->expectException(IsolationExecutionException::class);
        app(IsolationExecutionService::class)->suspendFromWebA($case);

        $this->travelBack();
    }

    #[Test]
    public function suspend_from_web_a_menolak_tanpa_customer_service(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $case = $this->makeReadyCase($customer);
        // Simulasikan layanan yang sudah tidak ada lagi (dihapus/dipindah)
        // tanpa melanggar FK restrictOnDelete sungguhan di skenario ini.
        $case->service_id = 999999;

        $this->expectException(IsolationExecutionException::class);
        app(IsolationExecutionService::class)->suspendFromWebA($case);

        $this->travelBack();
    }

    #[Test]
    public function suspend_from_web_a_menolak_case_number_basi_yang_sudah_dibatalkan(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $staleCase = $this->makeReadyCase($customer);

        // Pelanggan bayar sebelum diisolir — kasus dibatalkan, Web A tidak
        // pernah tahu (webhook sudah terkirim duluan, lalu basi).
        app(IsolationCaseService::class)->cancelCase($staleCase, $this->adminUser, 'Lunas sebelum isolir');

        $this->expectException(IsolationExecutionException::class);
        app(IsolationExecutionService::class)->suspendFromWebA($staleCase->refresh());

        $this->travelBack();
    }

    #[Test]
    public function suspend_from_web_a_menolak_case_number_basi_saat_sudah_ada_kasus_baru(): void
    {
        $this->travelTo(now()->parse('2026-09-25'));

        $customer = $this->createCustomer('active');
        $staleCase = $this->makeReadyCase($customer);

        app(IsolationCaseService::class)->cancelCase($staleCase, $this->adminUser, 'Lunas sebelum isolir');
        $this->createOverdueInvoice($customer, '2026-09');
        $this->artisan('isolir:sync-cases')->assertSuccessful();
        app(IsolationCaseService::class)->setAdminDecision(
            IsolationCase::where('service_id', $customer->customerService->id)->where('active_slot', 1)->first(),
            IsolationAdminDecision::EXECUTE,
            $this->adminUser,
            null
        );

        // Web A memutar ulang webhook kasus lama yang basi — harus ditolak,
        // bukan ikut mengeksekusi kasus baru yang aktif sekarang.
        $this->expectException(IsolationExecutionException::class);
        app(IsolationExecutionService::class)->suspendFromWebA($staleCase->refresh());

        $this->assertSame('active', $customer->refresh()->status);

        $this->travelBack();
    }
}
