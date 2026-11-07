<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\ScopeType;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AppNotification;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tahap 3 (docs/plan/notifications/urutan-pengerjaan-notifikasi.md) —
 * GenerateMonthlyInvoicesCommand sebelumnya nol notif sama sekali setelah
 * generate massal selesai. Finance (`invoices.approve`, global) dapat
 * ringkasan lintas-POP; pop_admin (`invoices.create`, di-scope) cuma dapat
 * ringkasan POP-nya sendiri — dan cuma kalau POP itu BENERAN kebagian
 * aktivitas di run ini.
 */
class GenerateMonthlyInvoicesNotifiesSummaryTest extends TestCase
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
        $this->seed(InternetPackageSeeder::class);
    }

    /**
     * @return array{0: Pop, 1: Customer}
     */
    private function popWithBillableCustomer(string $customerCode, string $popCode): array
    {
        $pop = Pop::create([
            'code' => $popCode,
            'pop_code' => $popCode,
            'registration_prefix' => 'C',
            'cid_prefix' => 'D',
            'name' => 'POP '.$popCode,
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'customer_code' => $customerCode,
            'full_name' => 'Pelanggan '.$customerCode,
            'primary_phone' => '0811'.substr($customerCode, -7),
            'registration_date' => now()->subYears(2),
            'pop_id' => $pop->id,
            'status' => 'active',
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => InternetPackage::first()->id,
            'service_status' => 'aktif',
            'billing_cycle' => 'monthly',
            'package_name_snapshot' => 'Paket Test',
            'monthly_price' => 150000,
            'total_monthly_bill' => 150000,
            'activation_date' => now()->subYears(2),
        ]);

        return [$pop, $customer];
    }

    #[Test]
    public function generate_sukses_notif_finance_global_dan_pop_admin_yang_scope_nya_cocok(): void
    {
        [$pop] = $this->popWithBillableCustomer('C-NOTIF-01', 'NOTIF1');

        $adminRole = Role::where('code', 'admin')->firstOrFail();
        $financeUser = User::factory()->create(['role_id' => $adminRole->id, 'status' => 'active']);

        $popAdminRole = Role::where('code', 'pop_admin')->firstOrFail();

        $popAdminUser = User::factory()->create(['role_id' => $popAdminRole->id, 'status' => 'active']);
        $popAdminUser->roleScopes()->create([
            'role_id' => $popAdminRole->id,
            'scope_type' => ScopeType::SELECTED_POP->value,
        ])->targets()->create(['pop_id' => $pop->id]);

        // pop_admin di POP lain yang nihil aktivitas — TIDAK boleh ikut notif,
        // buktiin per-POP scoping beneran jalan (bukan broadcast semua pop_admin).
        $otherPop = Pop::create([
            'code' => 'NOTIF2',
            'pop_code' => 'NOTIF2',
            'registration_prefix' => 'E',
            'cid_prefix' => 'E',
            'name' => 'POP NOTIF2',
            'type' => 'cabang',
            'status' => 'active',
        ]);
        $popAdminOther = User::factory()->create(['role_id' => $popAdminRole->id, 'status' => 'active']);
        $popAdminOther->roleScopes()->create([
            'role_id' => $popAdminRole->id,
            'scope_type' => ScopeType::SELECTED_POP->value,
        ])->targets()->create(['pop_id' => $otherPop->id]);

        $targetPeriod = now()->addMonth()->format('Y-m');

        Notification::fake();

        $this->artisan('billing:generate-monthly-invoices', ['--period' => $targetPeriod])
            ->assertExitCode(0);

        Notification::assertSentTo(
            $financeUser,
            AppNotification::class,
            fn ($notification) => $notification->type === NotificationType::SUCCESS
                && str_contains($notification->message, '1 tagihan terbit')
        );

        Notification::assertSentTo(
            $popAdminUser,
            AppNotification::class,
            fn ($notification) => str_contains($notification->message, $pop->name)
        );

        Notification::assertNotSentTo($popAdminOther, AppNotification::class);
    }

    #[Test]
    public function dry_run_tidak_mengirim_notif_apa_pun(): void
    {
        $this->popWithBillableCustomer('C-NOTIF-03', 'NOTIF4');

        $adminRole = Role::where('code', 'admin')->firstOrFail();
        $financeUser = User::factory()->create(['role_id' => $adminRole->id, 'status' => 'active']);

        $targetPeriod = now()->addMonth()->format('Y-m');

        Notification::fake();

        $this->artisan('billing:generate-monthly-invoices', ['--period' => $targetPeriod, '--dry-run' => true])
            ->assertExitCode(0);

        Notification::assertNothingSentTo($financeUser);
    }

    #[Test]
    public function tidak_ada_aktivitas_sama_sekali_tidak_mengirim_notif(): void
    {
        $adminRole = Role::where('code', 'admin')->firstOrFail();
        $financeUser = User::factory()->create(['role_id' => $adminRole->id, 'status' => 'active']);

        // Tidak ada pelanggan sama sekali — generate selesai tanpa aktivitas.
        $targetPeriod = now()->addMonth()->format('Y-m');

        Notification::fake();

        $this->artisan('billing:generate-monthly-invoices', ['--period' => $targetPeriod])
            ->assertExitCode(0);

        Notification::assertNothingSentTo($financeUser);
    }
}
