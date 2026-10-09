<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketIssueCategory;
use App\Models\User;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketIssueCategoryFeatureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketIssueCategoryCRUDTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(TicketIssueCategoryFeatureSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/master/issue-categories');
        $response->assertRedirect('/login');
    }

    public function test_user_without_permission_gets_403(): void
    {
        $restrictedRole = Role::create([
            'name' => 'Restricted Role',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create([
            'role_id' => $restrictedRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->get('/master/issue-categories');
        $response->assertStatus(403);

        $response = $this->get('/master/issue-categories/create');
        $response->assertStatus(403);
    }

    /**
     * Sesuai rancangan: sementara cuma Owner yang dapat akses CRUD master
     * ini (lewat wildcard `*`) — role lain (mis. Admin) belum di-grant.
     */
    public function test_non_owner_role_gets_403(): void
    {
        $adminRole = Role::where('code', 'admin')->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->get('/master/issue-categories');
        $response->assertStatus(403);
    }

    public function test_authorized_owner_can_perform_full_crud(): void
    {
        $this->loginAsAdmin(); // Authenticates as Owner

        // 1. View index
        $response = $this->get('/master/issue-categories');
        $response->assertStatus(200);

        // 2. View create page
        $response = $this->get('/master/issue-categories/create');
        $response->assertStatus(200);

        // 3. Store
        $data = [
            'name' => 'Lemot',
            'default_priority' => 'low',
            'sla_source' => 'prioritas',
            'is_active' => '1',
        ];

        $response = $this->post('/master/issue-categories', $data);
        $response->assertRedirect('/master/issue-categories');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_issue_categories', [
            'name' => 'Lemot',
            'default_priority' => 'low',
            'sla_source' => 'prioritas',
            'is_active' => true,
        ]);

        $category = TicketIssueCategory::where('name', 'Lemot')->firstOrFail();

        // 4. View edit page
        $response = $this->get("/master/issue-categories/{$category->id}/edit");
        $response->assertStatus(200);
        $response->assertSee('Lemot');

        // 5. Update
        $response = $this->put("/master/issue-categories/{$category->id}", [
            'name' => 'Lemot Berat',
            'default_priority' => 'Medium',
            'sla_source' => 'paket',
            'is_active' => '1',
        ]);
        $response->assertRedirect('/master/issue-categories');

        $this->assertDatabaseHas('ticket_issue_categories', [
            'id' => $category->id,
            'name' => 'Lemot Berat',
            'default_priority' => 'Medium',
            'sla_source' => 'paket',
        ]);

        // 6. Toggle status to inactive
        $response = $this->post("/master/issue-categories/{$category->id}/toggle");
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ticket_issue_categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);

        // 7. Toggle back to active
        $this->post("/master/issue-categories/{$category->id}/toggle");
        $this->assertDatabaseHas('ticket_issue_categories', [
            'id' => $category->id,
            'is_active' => true,
        ]);
    }

    /**
     * `is_batch` (revisi Worksheet Helpdesk — tiket batch Parent/Child) cuma
     * bisa diaktifkan lewat form ini. Checkbox HTML gak ngirim apa pun kalau
     * gak dicentang — pastikan uncheck beneran nge-reset ke false, bukan
     * dibiarkan (lihat TicketIssueCategoryController::validateCategory()).
     */
    public function test_is_batch_checkbox_can_be_toggled_on_and_off(): void
    {
        $this->loginAsAdmin();

        $response = $this->post('/master/issue-categories', [
            'name' => 'ODP LOS',
            'default_priority' => 'High',
            'sla_source' => 'prioritas',
            'is_active' => '1',
            'is_batch' => '1',
        ]);
        $response->assertRedirect('/master/issue-categories');

        $category = TicketIssueCategory::where('name', 'ODP LOS')->firstOrFail();
        $this->assertTrue($category->is_batch);

        // Uncheck (field gak dikirim sama sekali, kayak submit form browser
        // beneran) — is_batch WAJIB balik false, bukan tetap true.
        $response = $this->put("/master/issue-categories/{$category->id}", [
            'name' => 'ODP LOS',
            'default_priority' => 'High',
            'sla_source' => 'prioritas',
            'is_active' => '1',
        ]);
        $response->assertRedirect('/master/issue-categories');

        $this->assertFalse($category->fresh()->is_batch);
    }

    public function test_name_uniqueness_validation(): void
    {
        $this->loginAsAdmin();

        TicketIssueCategory::create([
            'name' => 'LOS',
            'default_priority' => 'Medium',
            'sla_source' => 'prioritas',
            'is_active' => true,
        ]);

        $response = $this->post('/master/issue-categories', [
            'name' => 'LOS',
            'default_priority' => 'High',
            'sla_source' => 'prioritas',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    /**
     * Toggle status (nonaktifkan) tidak boleh menghapus riwayat tiket lama
     * yang sudah memakai kategori tsb — issue_category_id tetap nullOnDelete,
     * bukan cascade delete, dan toggle bukan hard delete sama sekali.
     */
    public function test_toggle_status_does_not_remove_history_of_customer_tickets(): void
    {
        $owner = $this->loginAsAdmin();

        $category = TicketIssueCategory::create([
            'name' => 'Backbone CUT',
            'default_priority' => 'High',
            'sla_source' => 'prioritas',
            'is_active' => true,
        ]);

        $pop = Pop::factory()->create();
        $customer = Customer::factory()->create(['pop_id' => $pop->id]);

        $ticket = Ticket::create([
            'ticket_number' => 'TKT-2026-0001',
            'type' => 'MTN',
            'issue_category_id' => $category->id,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'detail_keluhan' => 'Backbone putus di jalur utama.',
            'priority' => 'High',
            'created_by' => $owner->id,
        ]);

        $this->post("/master/issue-categories/{$category->id}/toggle");

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'issue_category_id' => $category->id,
        ]);
    }
}
