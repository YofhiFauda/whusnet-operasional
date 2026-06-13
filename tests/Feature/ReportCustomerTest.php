<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Role;
use App\Models\SubscriptionStatus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed initial data
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/reports/customers');
        $response->assertRedirect('/login');

        $responseExport = $this->get('/reports/customers/export');
        $responseExport->assertRedirect('/login');
    }

    public function test_user_without_permission_cannot_access_reports(): void
    {
        // Customer Service has no report permission by default
        $csRole = Role::where('name', 'Customer Service')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $csRole->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/reports/customers');
        $response->assertStatus(403);

        $responseExport = $this->actingAs($user)->get('/reports/customers/export');
        $responseExport->assertStatus(403);
    }

    public function test_owner_can_access_reports_and_see_all_pops(): void
    {
        $ownerRole = Role::where('name', 'Owner')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        $popA = Pop::create([
            'name' => 'POP Sidoarjo',
            'code' => 'SDA',
            'pop_code' => 'SDA',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $popB = Pop::create([
            'name' => 'POP Surabaya',
            'code' => 'SBY',
            'pop_code' => 'SBY',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/reports/customers');
        $response->assertStatus(200);
        $response->assertSee('POP Sidoarjo');
        $response->assertSee('POP Surabaya');
    }

    public function test_admin_cabang_only_sees_assigned_pop_in_filters_and_data(): void
    {
        $adminCabangRole = Role::where('name', 'Admin Cabang')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $adminCabangRole->id,
            'status' => 'active',
        ]);

        $popA = Pop::create([
            'name' => 'POP Sidoarjo',
            'code' => 'SDA',
            'pop_code' => 'SDA',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $popB = Pop::create([
            'name' => 'POP Surabaya',
            'code' => 'SBY',
            'pop_code' => 'SBY',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        // Assign popA only
        $user->pops()->attach($popA->id);

        Customer::query()->delete();

        // Customer in assigned popA
        $customerA = Customer::create([
            'full_name' => 'Pelanggan SDA',
            'customer_code' => 'C-SDA-000001',
            'phone' => '081234567890',
            'primary_phone' => '081234567890',
            'gender' => 'Laki-laki',
            'pop_id' => $popA->id,
            'status' => 'registered',
            'customer_status' => 'calon_pelanggan',
            'data_completeness_status' => 'draft',
            'registration_date' => '2026-06-01',
        ]);

        // Customer in unassigned popB
        $customerB = Customer::create([
            'full_name' => 'Pelanggan SBY',
            'customer_code' => 'C-SBY-000001',
            'phone' => '081234567891',
            'primary_phone' => '081234567891',
            'gender' => 'Laki-laki',
            'pop_id' => $popB->id,
            'status' => 'registered',
            'customer_status' => 'calon_pelanggan',
            'data_completeness_status' => 'draft',
            'registration_date' => '2026-06-01',
        ]);

        $response = $this->actingAs($user)->get('/reports/customers');
        $response->assertStatus(200);

        // Should see popA but not popB in filters/tables
        $response->assertSee('POP Sidoarjo');
        $response->assertDontSee('POP Surabaya');
        $response->assertSee('Pelanggan SDA');
        $response->assertDontSee('Pelanggan SBY');
    }

    public function test_customer_report_filtering(): void
    {
        $ownerRole = Role::where('name', 'Owner')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        $pop = Pop::create([
            'name' => 'POP Sidoarjo',
            'code' => 'SDA',
            'pop_code' => 'SDA',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        Customer::flushEventListeners();
        Customer::query()->delete();

        // 1. Completeness: draft, Status: registered, date: 2026-06-01
        Customer::create([
            'full_name' => 'Pelanggan Satu',
            'customer_code' => 'C-000001',
            'phone' => '081234567890',
            'primary_phone' => '081234567890',
            'gender' => 'Laki-laki',
            'pop_id' => $pop->id,
            'status' => 'registered',
            'data_completeness_status' => 'draft',
            'registration_date' => '2026-06-01',
        ]);

        // 2. Completeness: siap_billing, Status: active, date: 2026-06-10
        Customer::create([
            'full_name' => 'Pelanggan Dua',
            'customer_code' => 'C-000002',
            'phone' => '081234567892',
            'primary_phone' => '081234567892',
            'gender' => 'Laki-laki',
            'pop_id' => $pop->id,
            'status' => 'active',
            'data_completeness_status' => 'siap_billing',
            'registration_date' => '2026-06-10',
        ]);

        // Filter completeness_status = siap_billing
        $responseCompleteness = $this->actingAs($user)->get('/reports/customers?completeness_status=siap_billing');
        $responseCompleteness->assertSee('Pelanggan Dua');
        $responseCompleteness->assertDontSee('Pelanggan Satu');

        // Filter status = registered
        $responseStatus = $this->actingAs($user)->get('/reports/customers?status=registered');
        $responseStatus->assertSee('Pelanggan Satu');
        $responseStatus->assertDontSee('Pelanggan Dua');

        // Filter date range: 2026-06-05 to 2026-06-15
        $responseDate = $this->actingAs($user)->get('/reports/customers?start_date=2026-06-05&end_date=2026-06-15');
        $responseDate->assertSee('Pelanggan Dua');
        $responseDate->assertDontSee('Pelanggan Satu');
    }

    public function test_export_csv_enforces_pop_boundaries_for_admin_cabang(): void
    {
        $adminCabangRole = Role::where('name', 'Admin Cabang')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $adminCabangRole->id,
            'status' => 'active',
        ]);

        $popA = Pop::create([
            'name' => 'POP Sidoarjo',
            'code' => 'SDA',
            'pop_code' => 'SDA',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $popB = Pop::create([
            'name' => 'POP Surabaya',
            'code' => 'SBY',
            'pop_code' => 'SBY',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $user->pops()->attach($popA->id);

        Customer::query()->delete();

        // Customer in POP A
        Customer::create([
            'full_name' => 'Export Pelanggan SDA',
            'customer_code' => 'C-SDA-000001',
            'phone' => '081234567890',
            'primary_phone' => '081234567890',
            'gender' => 'Laki-laki',
            'pop_id' => $popA->id,
            'status' => 'registered',
            'data_completeness_status' => 'draft',
            'registration_date' => '2026-06-01',
        ]);

        // Customer in POP B
        Customer::create([
            'full_name' => 'Export Pelanggan SBY',
            'customer_code' => 'C-SBY-000001',
            'phone' => '081234567891',
            'primary_phone' => '081234567891',
            'gender' => 'Laki-laki',
            'pop_id' => $popB->id,
            'status' => 'registered',
            'data_completeness_status' => 'draft',
            'registration_date' => '2026-06-01',
        ]);

        // Export without specific pop_id parameter (should only return POP A data)
        $response = $this->actingAs($user)->get('/reports/customers/export');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        
        $content = $response->streamedContent();
        $this->assertStringContainsString('Export Pelanggan SDA', $content);
        $this->assertStringNotContainsString('Export Pelanggan SBY', $content);

        // Export specifically POP B (which they don't have access to) -> should return 403
        $responseUnauthorizedExport = $this->actingAs($user)->get('/reports/customers/export?pop_id=' . $popB->id);
        $responseUnauthorizedExport->assertStatus(403);
    }
}
