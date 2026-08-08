<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\User;
use App\Models\Village;
use App\Notifications\AppNotification;
use Database\Seeders\ActionSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PonorogoRegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubscriptionStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Notif transisi besar Customer Lifecycle (active/terminated) ke pendaftar
 * asli (`created_by`) — sebelumnya nol notif sama sekali (docs/plan/analisa-
 * status-implementasi-notifikasi.md §5).
 */
class CustomerLifecycleNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_verify_notifies_original_registrar_with_success_type(): void
    {
        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SubscriptionStatusSeeder::class);
        $this->seed(InternetPackageSeeder::class);
        $this->seed(PonorogoRegionSeeder::class);

        $registrar = User::factory()->create(['status' => 'active']);
        $admin = $this->loginAsAdmin();

        $pop = Pop::create([
            'code' => 'POP-LIFE1',
            'pop_code' => 'LIFE1',
            'registration_prefix' => 'CL',
            'cid_prefix' => 'DL',
            'name' => 'POP Lifecycle Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $package = InternetPackage::query()->firstOrFail();
        $city = City::query()->where('name', 'Ponorogo')->firstOrFail();
        $district = District::query()->where('city_id', $city->id)->firstOrFail();
        $village = Village::query()->where('district_id', $district->id)->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'D00C000101',
            'full_name' => 'Lifecycle Active Customer',
            'primary_phone' => '081234500101',
            'registration_date' => '2026-06-01',
            'status' => 'installed',
            'pop_id' => $pop->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'internet_package_id' => $package->id,
            'address' => 'Jl. Lifecycle No. 1',
            'created_by' => $registrar->id,
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Jl. Lifecycle No. 1',
            'city_id' => $city->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'province' => 'Jawa Timur',
            'city' => 'Ponorogo',
            'district' => $district->name,
            'village' => $village->name,
        ]);

        CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'download_speed_snapshot' => '20 Mbps',
            'upload_speed_snapshot' => '20 Mbps',
            'monthly_price' => $package->monthly_price,
            'discount' => 0.00,
            'ppn' => 11.00,
            'total_monthly_bill' => $package->monthly_price * 1.11,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-07-01',
            'service_status' => 'menunggu_pemasangan',
            'billing_status' => 'pending',
        ]);

        Notification::fake();

        $this->from('/verifications/queue')->post(route('customers.verification.final', $customer->id), [
            'issue_date' => '2026-06-01',
        ])->assertRedirect('/verifications/queue');

        Notification::assertSentTo(
            $registrar,
            AppNotification::class,
            fn ($notification) => $notification->type === NotificationType::SUCCESS
                && str_contains($notification->title, $customer->full_name)
        );

        // Admin yang eksekusi verifikasi gak notif diri sendiri.
        Notification::assertNotSentTo($admin, AppNotification::class);
    }

    public function test_termination_notifies_original_registrar_with_error_type(): void
    {
        $this->seed(DatabaseSeeder::class);

        $registrar = User::factory()->create(['status' => 'active']);
        $admin = $this->loginAsAdmin();

        $pop = Pop::first() ?? Pop::create([
            'code' => 'POP-LIFE2',
            'pop_code' => 'LIFE2',
            'registration_prefix' => 'CL2',
            'cid_prefix' => 'DL2',
            'name' => 'POP Lifecycle Terminate Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-LIFE-001',
            'full_name' => 'Lifecycle Terminate Customer',
            'primary_phone' => '081234500102',
            'registration_date' => '2026-06-15',
            'pop_id' => $pop->id,
            'status' => 'active',
            'created_by' => $registrar->id,
        ]);

        Notification::fake();

        $this->post(route('customers.terminate', $customer), [
            'reason' => 'Pelanggan pindah rumah',
        ])->assertRedirect();

        Notification::assertSentTo(
            $registrar,
            AppNotification::class,
            fn ($notification) => $notification->type === NotificationType::ERROR
                && str_contains($notification->title, $customer->full_name)
        );

        Notification::assertNotSentTo($admin, AppNotification::class);
    }

    public function test_termination_by_registrar_themselves_does_not_self_notify(): void
    {
        $this->seed(DatabaseSeeder::class);

        $registrar = $this->loginAsAdmin();

        $pop = Pop::first() ?? Pop::create([
            'code' => 'POP-LIFE3',
            'pop_code' => 'LIFE3',
            'registration_prefix' => 'CL3',
            'cid_prefix' => 'DL3',
            'name' => 'POP Lifecycle Self Test',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'customer_code' => 'C-LIFE-002',
            'full_name' => 'Lifecycle Self Terminate Customer',
            'primary_phone' => '081234500103',
            'registration_date' => '2026-06-15',
            'pop_id' => $pop->id,
            'status' => 'active',
            'created_by' => $registrar->id,
        ]);

        Notification::fake();

        $this->post(route('customers.terminate', $customer), [
            'reason' => 'Test self.',
        ])->assertRedirect();

        Notification::assertNothingSentTo($registrar);
    }
}
