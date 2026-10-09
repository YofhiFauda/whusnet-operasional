<?php

namespace Tests\Feature;

use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketIssueCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard Owner Fase 2 (docs/plan/analisa-dashboard-owner-statistik.md
 * §6 Fase 2): Funnel Akuisisi & Retensi (Pilar 3), Bucket Tiket & SLA
 * Compliance (Pilar 4).
 */
class DashboardFunnelTicketingTest extends TestCase
{
    use RefreshDatabase;

    private Pop $pop;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->pop = Pop::create([
            'name' => 'POP Dashboard Fase2',
            'code' => 'DSH2',
            'pop_code' => 'DSH2',
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $ownerRole = Role::where('name', 'Owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);
    }

    private function makeCustomer(string $code, string $status, array $extra = []): Customer
    {
        return Customer::create(array_merge([
            'full_name' => 'Pelanggan '.$code,
            'customer_code' => $code,
            'primary_phone' => '0812'.str_pad((string) crc32($code), 8, '0', STR_PAD_LEFT),
            'gender' => 'Laki-laki',
            'pop_id' => $this->pop->id,
            'status' => $status,
            'data_completeness_status' => 'siap_billing',
            'registration_date' => '2020-01-01',
        ], $extra));
    }

    private function makeTicket(string $number, array $extra = []): Ticket
    {
        return Ticket::create(array_merge([
            'ticket_number' => $number,
            'type' => TaskType::MAINTENANCE->value,
            'customer_id' => $this->makeCustomer($number.'-CUST', 'active')->id,
            'pop_id' => $this->pop->id,
            'detail_keluhan' => 'Keluhan '.$number,
            'priority' => 'High',
            'handler' => 'helpdesk',
            'status' => 'open',
            'created_by' => $this->owner->id,
            'created_at' => now(),
        ], $extra));
    }

    /**
     * Pengelompokan status HARUS pakai nilai `WorkflowTransition` yang beneran
     * ada di enum — `waiting_acc` menggabung survey_in_progress/surveyed/
     * waiting_acc jadi satu tahap "Menunggu ACC Admin".
     */
    public function test_funnel_menghitung_pelanggan_per_tahap_status(): void
    {
        $this->makeCustomer('FUN-001', 'registered');
        $this->makeCustomer('FUN-002', 'waiting_survey');
        $this->makeCustomer('FUN-003', 'surveyed');
        $this->makeCustomer('FUN-004', 'waiting_acc');
        $this->makeCustomer('FUN-005', 'waiting_installation');
        $this->makeCustomer('FUN-006', 'verification_admin');
        $this->makeCustomer('FUN-007', 'rejected', ['rejected_at' => now()]);
        $this->makeCustomer('FUN-008', 'suspended');

        $stats = $this->actingAs($this->owner)->get('/')->viewData('stats');

        $this->assertSame(2, $stats['funnel_waiting_survey']);
        $this->assertSame(2, $stats['funnel_waiting_acc']);
        $this->assertSame(1, $stats['funnel_waiting_installation']);
        $this->assertSame(1, $stats['funnel_verification_admin']);
        $this->assertSame(1, $stats['rejected_customers']);
        $this->assertSame(1, $stats['suspended_customers']);
    }

    public function test_distribusi_bucket_tiket_sesuai_handler_dan_status(): void
    {
        $this->makeTicket('TKB-MASUK', ['handler' => 'helpdesk', 'status' => 'open']);
        $this->makeTicket('TKB-DIPROSES', ['handler' => 'noc', 'status' => 'open']);
        $this->makeTicket('TKB-SELESAI', ['handler' => 'helpdesk', 'status' => 'closed']);
        $this->makeTicket('TKB-BATAL', ['handler' => 'noc', 'status' => 'cancelled']);

        $stats = $this->actingAs($this->owner)->get('/')->viewData('stats');

        $this->assertSame(1, $stats['ticket_bucket_masuk']);
        $this->assertSame(1, $stats['ticket_bucket_diproses']);
        $this->assertSame(1, $stats['ticket_bucket_selesai']);
        $this->assertSame(1, $stats['ticket_bucket_dibatalkan']);
    }

    /**
     * Dua cara breach: sudah ditutup TAPI telat (`resolved_at` > deadline),
     * atau masih jalan dan deadline-nya sudah lewat `now()`. Tiket yang
     * masih longgar (deadline di masa depan) TIDAK BOLEH ikut kehitung —
     * ini yang menjaga query OR-nya tidak bocor lintas syarat.
     */
    public function test_breach_sla_menghitung_yang_telat_dan_yang_masih_berjalan_lewat_deadline(): void
    {
        $this->makeTicket('TKS-RESOLVED-LATE', [
            'handler' => 'helpdesk',
            'status' => 'closed',
            'sla_deadline_at' => now()->subDay(),
            'resolved_at' => now(),
        ]);

        $this->makeTicket('TKS-STILL-OVERDUE', [
            'handler' => 'helpdesk',
            'status' => 'open',
            'sla_deadline_at' => now()->subHour(),
        ]);

        $this->makeTicket('TKS-ON-TIME', [
            'handler' => 'helpdesk',
            'status' => 'open',
            'sla_deadline_at' => now()->addDay(),
        ]);

        $stats = $this->actingAs($this->owner)->get('/')->viewData('stats');

        $this->assertSame(2, $stats['ticket_sla_breach_count']);
    }

    public function test_top_kategori_gangguan_diurutkan_dari_yang_terbanyak(): void
    {
        $dropcore = TicketIssueCategory::create(['name' => 'Dropcore Putus', 'default_priority' => 'High']);
        $redaman = TicketIssueCategory::create(['name' => 'Redaman Tinggi', 'default_priority' => 'Medium']);

        $this->makeTicket('TKC-1', ['issue_category_id' => $dropcore->id]);
        $this->makeTicket('TKC-2', ['issue_category_id' => $dropcore->id]);
        $this->makeTicket('TKC-3', ['issue_category_id' => $redaman->id]);

        $response = $this->actingAs($this->owner)->get('/');
        $topCategories = $response->viewData('topIssueCategories');

        $this->assertSame('Dropcore Putus', $topCategories->first()->issueCategory->name);
        $this->assertSame(2, $topCategories->first()->total);
    }

    /**
     * `teknisi` sengaja dipilih: punya `dashboard.view` TAPI TIDAK punya
     * permission `tickets.*` sama sekali — beda dari Helpdesk/pop_admin/NOC/
     * admin/sales yang semuanya kebagian `tickets.*`, jadi otomatis lolos
     * wildcard `tickets.history.view`.
     */
    public function test_user_tanpa_tickets_history_view_tidak_melihat_blok_ticketing(): void
    {
        $teknisiRole = Role::where('name', 'Teknisi')->firstOrFail();
        $teknisi = User::factory()->create(['role_id' => $teknisiRole->id, 'status' => 'active']);

        $response = $this->actingAs($teknisi)->get('/');

        $response->assertOk();
        $stats = $response->viewData('stats');
        $this->assertArrayNotHasKey('ticket_bucket_masuk', $stats);
        $response->assertDontSee('Kualitas Layanan & Gangguan', false);
    }
}
