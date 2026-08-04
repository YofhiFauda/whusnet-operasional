<?php

namespace Tests\Feature;

use App\Enums\ScopeType;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_technician_can_upload_customer_document(): void
    {
        // FileUploadService::uploadCustomerRegistrationDoc() dkk nulis ke disk
        // 'public', bukan 'local' — fake disk harus samain biar assertExists match.
        Storage::fake('public');

        $pop = $this->createPop('DOC1');
        $technician = $this->createUserWithRole('Teknisi');
        $technician->pops()->attach($pop->id);
        $this->grantPopScope($technician, $pop);
        $customer = $this->createCustomer($pop, 'TEST-DOC-001');

        $response = $this->actingAs($technician)
            ->from(route('customers.show', $customer->id))
            ->post(route('customers.documents.store', $customer->id), [
                'document_type' => 'ktp',
                'document_file' => UploadedFile::fake()->image('ktp.jpg'),
            ]);

        $response->assertRedirect(route('customers.show', $customer->id));

        $document = CustomerDocument::firstOrFail();
        $this->assertSame($customer->id, $document->customer_id);
        $this->assertSame('ktp', $document->document_type->value);
        $this->assertSame($technician->id, $document->uploaded_by);
        Storage::disk('public')->assertExists($document->file_path);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $technician->id,
            'module' => 'Dokumen Pelanggan',
            'action' => 'upload',
            'auditable_type' => CustomerDocument::class,
            'auditable_id' => $document->id,
        ]);

        $auditLog = AuditLog::where('auditable_type', CustomerDocument::class)
            ->where('auditable_id', $document->id)
            ->where('action', 'upload')
            ->firstOrFail();

        $this->assertNull($auditLog->old_values);
        $this->assertSame($customer->id, $auditLog->new_values['customer_id']);
        $this->assertSame('ktp', $auditLog->new_values['document_type']);
    }

    public function test_customer_document_is_visible_on_customer_detail_for_permitted_user(): void
    {
        Storage::fake('local');

        $pop = $this->createPop('DOC2');
        $user = $this->createUserWithRole('NOC');
        $user->pops()->attach($pop->id);
        $this->grantPopScope($user, $pop);
        $customer = $this->createCustomer($pop, 'TEST-DOC-002');

        $document = $this->createDocument($customer, $user, 'rumah');

        $response = $this->actingAs($user)
            ->get(route('customers.show', $customer->id));

        $response->assertStatus(200);
        $response->assertSee('LAMPIRAN DOKUMEN PENDUKUNG');
        $response->assertSee('Foto Rumah');
        $response->assertSee(route('customers.documents.show', $document->id));
    }

    public function test_user_without_document_permission_cannot_access_customer_document(): void
    {
        Storage::fake('local');

        $pop = $this->createPop('DOC3');
        $finance = $this->createUserWithRole('Atasan');
        $technician = $this->createUserWithRole('Teknisi');
        $customer = $this->createCustomer($pop, 'TEST-DOC-003');

        $document = $this->createDocument($customer, $technician, 'kontrak');

        $response = $this->actingAs($finance)
            ->get(route('customers.documents.show', $document->id));

        $response->assertStatus(403);
    }

    public function test_user_without_document_permission_does_not_see_documents_on_detail(): void
    {
        Storage::fake('local');

        $pop = $this->createPop('DOC4');
        $finance = $this->createUserWithRole('Atasan');
        $technician = $this->createUserWithRole('Teknisi');
        $customer = $this->createCustomer($pop, 'TEST-DOC-004');

        $this->createDocument($customer, $technician, 'survey');

        $response = $this->actingAs($finance)
            ->get(route('customers.show', $customer->id));

        $response->assertStatus(200);
        $response->assertSee('Akses dokumen dibatasi');
        $response->assertDontSee('Foto Survey');
        $response->assertDontSee('Upload Dokumen');
    }

    public function test_user_without_upload_permission_cannot_upload_customer_document(): void
    {
        Storage::fake('local');

        $pop = $this->createPop('DOC5');
        $customerService = $this->createUserWithRole('NOC');
        $customer = $this->createCustomer($pop, 'TEST-DOC-005');

        $response = $this->actingAs($customerService)
            ->post(route('customers.documents.store', $customer->id), [
                'document_type' => 'pemasangan',
                'document_file' => UploadedFile::fake()->image('installation.jpg'),
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('customer_documents', [
            'customer_id' => $customer->id,
        ]);
    }

    private function createPop(string $code): Pop
    {
        return Pop::create([
            'code' => $code,
            'pop_code' => $code,
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'status' => 'active',
        ]);
    }

    private function createCustomer(Pop $pop, string $code): Customer
    {
        return Customer::create([
            'customer_code' => $code,
            'full_name' => 'Customer Document '.$code,
            'primary_phone' => '0812345678',
            'pop_id' => $pop->id,
            'status' => 'installed',
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    private function createUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::where('name', $roleName)->firstOrFail();
        $user->role_id = $role->id;
        $user->save();

        return $user;
    }

    private function grantPopScope(User $user, Pop $pop): void
    {
        $scope = UserRoleScope::create([
            'user_id' => $user->id,
            'role_id' => $user->role_id,
            'scope_type' => ScopeType::SELECTED_POP,
        ]);
        UserRoleScopeTarget::create([
            'user_role_scope_id' => $scope->id,
            'pop_id' => $pop->id,
        ]);
    }

    private function createDocument(Customer $customer, User $uploader, string $type): CustomerDocument
    {
        $path = "customer-documents/{$customer->id}/{$type}.jpg";
        Storage::disk('local')->put($path, 'fake-document');

        return CustomerDocument::create([
            'customer_id' => $customer->id,
            'document_type' => $type,
            'file_path' => $path,
            'uploaded_by' => $uploader->id,
        ]);
    }
}
