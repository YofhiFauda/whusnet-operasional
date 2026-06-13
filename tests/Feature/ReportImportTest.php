<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/reports/imports');
        $response->assertRedirect('/login');

        $responseDetail = $this->get('/reports/imports/1');
        $responseDetail->assertRedirect('/login');

        $responseExport = $this->get('/reports/imports/1/export');
        $responseExport->assertRedirect('/login');
    }

    public function test_user_without_permission_cannot_access_reports(): void
    {
        // Customer Service has no report permission by default
        $csRole = Role::where('name', '=', 'Customer Service', 'and')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $csRole->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/reports/imports');
        $response->assertStatus(403);

        $responseDetail = $this->actingAs($user)->get('/reports/imports/1');
        $responseDetail->assertStatus(403);

        $responseExport = $this->actingAs($user)->get('/reports/imports/1/export');
        $responseExport->assertStatus(403);
    }

    public function test_owner_can_access_reports_and_see_all(): void
    {
        $ownerRole = Role::where('name', '=', 'Owner', 'and')->firstOrFail();
        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        $otherAdmin = User::factory()->create([
            'role_id' => Role::where('name', '=', 'Admin Pusat', 'and')->value('id'),
            'status' => 'active',
        ]);

        $batch1 = ImportBatch::create([
            'batch_number' => 'IMP-20260613-0001',
            'file_name' => 'owner-upload.xlsx',
            'uploaded_by' => $owner->id,
            'total_rows' => 10,
            'valid_rows' => 9,
            'invalid_rows' => 1,
            'imported_rows' => 9,
            'status' => 'imported',
        ]);

        $batch2 = ImportBatch::create([
            'batch_number' => 'IMP-20260613-0002',
            'file_name' => 'other-upload.xlsx',
            'uploaded_by' => $otherAdmin->id,
            'total_rows' => 20,
            'valid_rows' => 18,
            'invalid_rows' => 2,
            'imported_rows' => 18,
            'status' => 'imported',
        ]);

        $response = $this->actingAs($owner)->get('/reports/imports');
        $response->assertStatus(200);
        $response->assertSee('owner-upload.xlsx');
        $response->assertSee('other-upload.xlsx');
    }

    public function test_admin_cabang_only_sees_own_uploaded_batches(): void
    {
        $adminCabangRole = Role::where('name', '=', 'Admin Cabang', 'and')->firstOrFail();
        $userA = User::factory()->create([
            'role_id' => $adminCabangRole->id,
            'status' => 'active',
        ]);
        $userB = User::factory()->create([
            'role_id' => $adminCabangRole->id,
            'status' => 'active',
        ]);

        $batchA = ImportBatch::create([
            'batch_number' => 'IMP-20260613-0001',
            'file_name' => 'branch-a-upload.xlsx',
            'uploaded_by' => $userA->id,
            'total_rows' => 10,
            'valid_rows' => 9,
            'invalid_rows' => 1,
            'imported_rows' => 9,
            'status' => 'imported',
        ]);

        $batchB = ImportBatch::create([
            'batch_number' => 'IMP-20260613-0002',
            'file_name' => 'branch-b-upload.xlsx',
            'uploaded_by' => $userB->id,
            'total_rows' => 20,
            'valid_rows' => 18,
            'invalid_rows' => 2,
            'imported_rows' => 18,
            'status' => 'imported',
        ]);

        $response = $this->actingAs($userA)->get('/reports/imports');
        $response->assertStatus(200);
        $response->assertSee('branch-a-upload.xlsx');
        $response->assertDontSee('branch-b-upload.xlsx');

        // Detailed view check
        $responseDetailA = $this->actingAs($userA)->get('/reports/imports/' . $batchA->id);
        $responseDetailA->assertStatus(200);

        $responseDetailB = $this->actingAs($userA)->get('/reports/imports/' . $batchB->id);
        $responseDetailB->assertStatus(403);
    }

    public function test_import_report_filtering(): void
    {
        $ownerRole = Role::where('name', '=', 'Owner', 'and')->firstOrFail();
        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        // Create imports with different dates
        $batch1 = ImportBatch::create([
            'batch_number' => 'IMP-20260613-0001',
            'file_name' => 'import-june.xlsx',
            'uploaded_by' => $owner->id,
            'total_rows' => 10,
            'valid_rows' => 9,
            'invalid_rows' => 1,
            'imported_rows' => 9,
            'status' => 'imported',
        ]);
        $batch1->created_at = '2026-06-01 10:00:00';
        $batch1->save();

        $batch2 = ImportBatch::create([
            'batch_number' => 'IMP-20260713-0001',
            'file_name' => 'import-july.xlsx',
            'uploaded_by' => $owner->id,
            'total_rows' => 5,
            'valid_rows' => 0,
            'invalid_rows' => 5,
            'imported_rows' => 0,
            'status' => 'failed',
        ]);
        $batch2->created_at = '2026-07-10 10:00:00';
        $batch2->save();

        // 1. Filter by Search Query
        $responseSearch = $this->actingAs($owner)->get('/reports/imports?search=june');
        $responseSearch->assertSee('import-june.xlsx');
        $responseSearch->assertDontSee('import-july.xlsx');

        // 2. Filter by Status
        $responseStatus = $this->actingAs($owner)->get('/reports/imports?status=failed');
        $responseStatus->assertSee('import-july.xlsx');
        $responseStatus->assertDontSee('import-june.xlsx');

        // 3. Filter by Date Range
        $responseDate = $this->actingAs($owner)->get('/reports/imports?start_date=2026-07-01&end_date=2026-07-15');
        $responseDate->assertSee('import-july.xlsx');
        $responseDate->assertDontSee('import-june.xlsx');
    }

    public function test_detail_and_export_functionality(): void
    {
        $ownerRole = Role::where('name', '=', 'Owner', 'and')->firstOrFail();
        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        $batch = ImportBatch::create([
            'batch_number' => 'IMP-20260613-0001',
            'file_name' => 'test-with-errors.xlsx',
            'uploaded_by' => $owner->id,
            'total_rows' => 3,
            'valid_rows' => 2,
            'invalid_rows' => 1,
            'imported_rows' => 2,
            'status' => 'imported',
        ]);

        $error = ImportError::create([
            'import_batch_id' => $batch->id,
            'row_number' => 2,
            'field_name' => 'primary_phone',
            'error_message' => 'Nomor HP sudah terdaftar di database.',
            'raw_data' => ['full_name' => 'John Doe', 'primary_phone' => '0812345'],
        ]);

        // Test Detail Page
        $response = $this->actingAs($owner)->get('/reports/imports/' . $batch->id);
        $response->assertStatus(200);
        $response->assertSee('test-with-errors.xlsx');
        $response->assertSee('primary_phone');
        $response->assertSee('Nomor HP sudah terdaftar di database.');

        // Test Export CSV Page
        $responseExport = $this->actingAs($owner)->get('/reports/imports/' . $batch->id . '/export');
        $responseExport->assertStatus(200);
        $responseExport->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        
        $content = $responseExport->streamedContent();
        $this->assertStringContainsString('test-with-errors.xlsx', $content);
        $this->assertStringContainsString('primary_phone', $content);
        $this->assertStringContainsString('Nomor HP sudah terdaftar di database.', $content);
    }
}
