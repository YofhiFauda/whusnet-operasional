<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Pop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi: pelanggan "Gagal" (rejected) yang ditekan tombol "Kembalikan" tidak
 * boleh menghilang dari semua daftar.
 *
 * restoreFromFailed mengembalikan status ke status sebelum penolakan (mis.
 * verification_admin). Dulu grup daftar di CustomerController::index() hanya
 * memetakan waiting_survey/surveyed (survey) & waiting_installation/installed
 * (verification), sehingga status seperti verification_admin tidak muncul di tab
 * mana pun DAN bukan default (active/suspended) — pelanggan lenyap. Test ini
 * mengunci: status intermediate tetap terlihat di tab yang sesuai, dan restore
 * mendarat di halaman detail.
 */
class RestoredFailedCustomerStaysVisibleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    private function makeRejectedCustomer(string $previousStatus): Customer
    {
        $pop = Pop::firstOrCreate(['pop_code' => 'C'], [
            'code' => 'C', 'name' => 'Jetis', 'type' => 'cabang', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'C',
        ]);

        $customer = Customer::factory()->create([
            'status' => 'rejected',
            'pop_id' => $pop->id,
        ]);

        // Jejak penolakan yang dibaca restoreFromFailed untuk tahu status asal.
        AuditLog::create([
            'user_id' => null,
            'module' => 'Customer Workflow',
            'action' => 'status_transition',
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
            'old_values' => ['status' => $previousStatus],
            'new_values' => ['status' => 'rejected', 'note' => 'Ditolak: uji'],
            'created_at' => now(),
        ]);

        return $customer;
    }

    public function test_restore_lands_on_detail_and_customer_reappears_in_verification_tab(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        // verification_admin dulu tidak terpetakan ke grup mana pun.
        $customer = $this->makeRejectedCustomer('verification_admin');

        $response = $this->post(route('customers.restore-from-failed', $customer->id));

        $response->assertRedirect(route('customers.show', $customer->id));
        $response->assertSessionHas('success');

        $this->assertSame('verification_admin', $customer->fresh()->status);

        // Harus muncul di tab Verifikasi (bukan lenyap).
        $verificationList = $this->get('/customers?status_group=verification');
        $verificationList->assertStatus(200);
        $verificationList->assertSee($customer->full_name);

        // Dan tidak lagi nyangkut di daftar Gagal.
        $failedList = $this->get('/customers?status_group=failed');
        $failedList->assertDontSee($customer->full_name);
    }

    public function test_intermediate_survey_statuses_are_listed_in_survey_tab(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $pop = Pop::firstOrCreate(['pop_code' => 'C'], [
            'code' => 'C', 'name' => 'Jetis', 'type' => 'cabang', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'C',
        ]);

        $inProgress = Customer::factory()->create(['status' => 'survey_in_progress', 'pop_id' => $pop->id]);
        $waitingAcc = Customer::factory()->create(['status' => 'waiting_acc', 'pop_id' => $pop->id]);

        $surveyList = $this->get('/customers?status_group=survey');
        $surveyList->assertStatus(200);
        $surveyList->assertSee($inProgress->full_name);
        $surveyList->assertSee($waitingAcc->full_name);
    }
}
