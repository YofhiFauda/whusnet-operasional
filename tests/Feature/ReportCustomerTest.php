<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\SimpleExcel\SimpleExcelReader;
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
        // Teknisi has no report permission by default
        $csRole = Role::where('name', 'Teknisi')->firstOrFail();
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
        $adminCabangRole = Role::where('name', 'POP Admin')->firstOrFail();
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
        $scope = $user->roleScopes()->create([
            'role_id' => $user->role_id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        $scope->targets()->create(['pop_id' => $popA->id]);

        Customer::query()->delete();

        // Customer in assigned popA
        $customerA = Customer::create([
            'full_name' => 'Pelanggan SDA',
            'customer_code' => 'C-SDA-000001',
            'primary_phone' => '081234567890',
            'gender' => 'Laki-laki',
            'pop_id' => $popA->id,
            'status' => 'registered',
            'data_completeness_status' => 'draft',
            'registration_date' => '2026-06-01',
        ]);

        // Customer in unassigned popB
        $customerB = Customer::create([
            'full_name' => 'Pelanggan SBY',
            'customer_code' => 'C-SBY-000001',
            'primary_phone' => '081234567891',
            'gender' => 'Laki-laki',
            'pop_id' => $popB->id,
            'status' => 'registered',
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
        $adminCabangRole = Role::where('name', 'POP Admin')->firstOrFail();
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
        $scope = $user->roleScopes()->create([
            'role_id' => $user->role_id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        $scope->targets()->create(['pop_id' => $popA->id]);

        Customer::query()->delete();

        // Customer in POP A
        Customer::create([
            'full_name' => 'Export Pelanggan SDA',
            'customer_code' => 'C-SDA-000001',
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
        $responseUnauthorizedExport = $this->actingAs($user)->get('/reports/customers/export?pop_id='.$popB->id);
        $responseUnauthorizedExport->assertStatus(403);
    }

    public function test_export_xlsx_follows_page_filters_status_pop_and_registration_date(): void
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
            'cid_prefix' => 'E',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        Customer::query()->delete();

        $base = ['primary_phone' => '081234567890', 'gender' => 'Laki-laki', 'data_completeness_status' => 'draft'];

        Customer::create($base + ['full_name' => 'Putus SDA Juni', 'customer_code' => 'RQ000021', 'pop_id' => $popA->id, 'status' => 'terminated', 'registration_date' => '2026-06-10']);
        Customer::create($base + ['full_name' => 'Aktif SDA Juni', 'customer_code' => 'RQ000022', 'pop_id' => $popA->id, 'status' => 'active', 'registration_date' => '2026-06-10']);
        Customer::create($base + ['full_name' => 'Putus SBY Juni', 'customer_code' => 'RQ000023', 'pop_id' => $popB->id, 'status' => 'terminated', 'registration_date' => '2026-06-10']);
        Customer::create($base + ['full_name' => 'Putus SDA Mei', 'customer_code' => 'RQ000024', 'pop_id' => $popA->id, 'status' => 'terminated', 'registration_date' => '2026-05-10']);

        $names = function (string $query) use ($user): array {
            $response = $this->actingAs($user)->get('/reports/customers/export-xlsx'.$query);
            $response->assertStatus(200);

            return collect(SimpleExcelReader::create($response->baseResponse->getFile()->getPathname(), 'xlsx')
                ->getRows()
                ->toArray())->pluck('Nama Lengkap')->sort()->values()->all();
        };

        // Tanpa filter → semua pelanggan.
        $this->assertCount(4, $names(''));

        // Status = putus (terminated) saja.
        $this->assertSame(
            ['Putus SBY Juni', 'Putus SDA Juni', 'Putus SDA Mei'],
            $names('?status=terminated'),
        );

        // Status putus + POP A + rentang tanggal Juni.
        $this->assertSame(
            ['Putus SDA Juni'],
            $names('?status=terminated&pop_id='.$popA->id.'&start_date=2026-06-01&end_date=2026-06-30'),
        );
    }

    public function test_export_xlsx_customer_id_prefers_cid_then_pop_req_then_req(): void
    {
        $ownerRole = Role::where('name', 'Owner')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        $popWithPrefix = Pop::create([
            'name' => 'POP Sidoarjo',
            'code' => 'SDA',
            'pop_code' => 'SDA',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $popWithoutPrefix = Pop::create([
            'name' => 'POP Tanpa Prefix',
            'code' => 'TNP',
            'pop_code' => 'TNP',
            'registration_prefix' => 'C',
            'cid_prefix' => null,
            'type' => 'cabang',
            'status' => 'active',
        ]);

        Customer::query()->delete();

        Customer::create([
            'full_name' => 'Punya CID',
            'customer_code' => 'RQ000011',
            'cid' => 'D2X6CRQ000011',
            'primary_phone' => '081234567890',
            'gender' => 'Laki-laki',
            'pop_id' => $popWithPrefix->id,
            'status' => 'active',
            'data_completeness_status' => 'draft',
            'registration_date' => '2026-06-01',
        ]);

        // Putus/terminate → REQ ID murni walaupun CID sudah ada (sama dengan Pop::resolveDisplayId()).
        Customer::create([
            'full_name' => 'Putus Ada CID',
            'customer_code' => 'RQ000013',
            'cid' => 'D2X6CRQ000013',
            'primary_phone' => '081234567892',
            'gender' => 'Laki-laki',
            'pop_id' => $popWithPrefix->id,
            'status' => 'terminated',
            'data_completeness_status' => 'draft',
            'registration_date' => '2026-06-01',
        ]);

        Customer::create([
            'full_name' => 'Tanpa Prefix',
            'customer_code' => 'RQ000012',
            'primary_phone' => '081234567891',
            'gender' => 'Laki-laki',
            'pop_id' => $popWithoutPrefix->id,
            'status' => 'registered',
            'data_completeness_status' => 'draft',
            'registration_date' => '2026-06-01',
        ]);

        $response = $this->actingAs($user)->get('/reports/customers/export-xlsx');
        $response->assertStatus(200);

        $rows = collect(SimpleExcelReader::create($response->baseResponse->getFile()->getPathname(), 'xlsx')
            ->getRows()
            ->toArray())->keyBy('Nama Lengkap');

        $this->assertSame('D2X6CRQ000011', $rows['Punya CID']['ID Pelanggan']);
        $this->assertSame('RQ000012', $rows['Tanpa Prefix']['ID Pelanggan']);
        $this->assertSame('RQ000013', $rows['Putus Ada CID']['ID Pelanggan']);
    }

    public function test_export_xlsx_enforces_pop_boundaries_and_writes_requested_columns(): void
    {
        $adminCabangRole = Role::where('name', 'POP Admin')->firstOrFail();
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
        $scope = $user->roleScopes()->create([
            'role_id' => $user->role_id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        $scope->targets()->create(['pop_id' => $popA->id]);

        Customer::query()->delete();

        Customer::create([
            'full_name' => 'Excel Pelanggan SDA',
            'customer_code' => 'RQ000010',
            'identity_number' => '3502180101900010',
            'primary_phone' => '081234567890',
            'gender' => 'Laki-laki',
            'pop_id' => $popA->id,
            'status' => 'registered',
            'data_completeness_status' => 'draft',
            'registration_date' => '2026-06-01',
            'address' => 'Jl. Merdeka No. 10',
            'latitude' => -7.8712,
            'longitude' => 111.4623,
        ]);

        Customer::create([
            'full_name' => 'Excel Pelanggan SBY',
            'customer_code' => 'C-SBY-000010',
            'primary_phone' => '081234567891',
            'gender' => 'Laki-laki',
            'pop_id' => $popB->id,
            'status' => 'registered',
            'data_completeness_status' => 'draft',
            'registration_date' => '2026-06-01',
        ]);

        $response = $this->actingAs($user)->get('/reports/customers/export-xlsx');
        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));

        $rows = SimpleExcelReader::create($response->baseResponse->getFile()->getPathname(), 'xlsx')
            ->getRows()
            ->toArray();

        $this->assertCount(1, $rows);
        // Belum ada CID → fallback POP + REQ ID ({cid_prefix}00{RQ######}).
        $this->assertSame('D00RQ000010', $rows[0]['ID Pelanggan']);
        $this->assertSame('Excel Pelanggan SDA', $rows[0]['Nama Lengkap']);
        $this->assertSame('3502180101900010', (string) $rows[0]['Nomor Identitas (NIK KTP)']);
        $this->assertSame('Jl. Merdeka No. 10', $rows[0]['Alamat Instalasi Lengkap']);
        $this->assertSame('POP Sidoarjo', $rows[0]['POP Cabang']);
        $this->assertArrayHasKey('Masa Kontrak (Bulan)', $rows[0]);
        $this->assertArrayHasKey('ID Sales/ID Agent', $rows[0]);

        $responseUnauthorized = $this->actingAs($user)->get('/reports/customers/export-xlsx?pop_id='.$popB->id);
        $responseUnauthorized->assertStatus(403);
    }
}
