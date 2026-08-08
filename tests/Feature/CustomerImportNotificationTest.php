<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\City;
use App\Models\District;
use App\Models\ImportBatch;
use App\Models\InternetPackage;
use App\Models\Pop;
use App\Models\Village;
use App\Notifications\AppNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * CustomerController::confirmImport() — sebelumnya nol notif hasil import
 * (docs/plan/analisa-status-implementasi-notifikasi.md §5).
 */
class CustomerImportNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_successful_import_notifies_uploader_with_success_type(): void
    {
        $this->seed(DatabaseSeeder::class);
        $uploader = $this->loginAsAdmin();

        $city = City::firstOrFail();
        $district = District::where('city_id', $city->id)->firstOrFail();
        $village = Village::where('district_id', $district->id)->firstOrFail();

        $pop = Pop::create([
            'code' => 'POP-IMPNOTIF',
            'pop_code' => 'IMPN',
            'registration_prefix' => 'CI',
            'cid_prefix' => 'DI',
            'name' => 'POP Import Notif',
            'type' => 'cabang',
            'status' => 'active',
        ]);

        $sheets = [
            'customers' => [
                [
                    'original_no' => '1',
                    'status_row' => 'valid',
                    'old_customer_id' => 'CUST-NOTIF-1',
                    'full_name' => 'Import Notif Customer',
                    'primary_phone' => '087700001001',
                    'village_id' => $village->id,
                    'district_id' => $district->id,
                    'city_id' => $city->id,
                    'pop_id' => $pop->id,
                ],
            ],
            'packages' => [],
            'services' => [],
            'technical_details' => [],
            'invoices' => [],
            'payments' => [],
        ];

        Notification::fake();

        $response = $this->post('/customers/import/confirm', [
            'sheets' => json_encode($sheets),
            'file_name' => 'notif-test.xlsx',
        ]);

        $response->assertRedirect('/customers');

        $batch = ImportBatch::where('file_name', 'notif-test.xlsx')->firstOrFail();

        Notification::assertSentTo(
            $uploader,
            AppNotification::class,
            fn ($notification) => $notification->type === NotificationType::SUCCESS
                && str_contains($notification->title, $batch->batch_number)
                && str_contains($notification->message, '1 baris')
        );
    }

    public function test_failed_import_notifies_uploader_with_error_type(): void
    {
        $this->seed(DatabaseSeeder::class);
        $uploader = $this->loginAsAdmin();

        // Sheet packages dikirim dengan baris yang bakal bikin proses gagal
        // (kolom wajib gak ada), memicu jalur catch(\Exception) di
        // confirmImport() — cukup buat mastiin notif ERROR terkirim, bukan
        // ngetes detail error-nya.
        $sheets = [
            'customers' => [],
            'packages' => [
                [
                    'status_row' => 'valid',
                    'old_package_id' => 'PKG-FAIL',
                    // 'name' & 'monthly_price' sengaja dihilangkan biar
                    // InternetPackage::create() gagal constraint NOT NULL.
                ],
            ],
            'services' => [],
            'technical_details' => [],
            'invoices' => [],
            'payments' => [],
        ];

        Notification::fake();

        $this->post('/customers/import/confirm', [
            'sheets' => json_encode($sheets),
            'file_name' => 'notif-fail-test.xlsx',
        ])->assertRedirect('/customers/import');

        $batch = ImportBatch::where('file_name', 'notif-fail-test.xlsx')->firstOrFail();
        $this->assertSame('failed', $batch->status);

        Notification::assertSentTo(
            $uploader,
            AppNotification::class,
            fn ($notification) => $notification->type === NotificationType::ERROR
                && str_contains($notification->title, $batch->batch_number)
        );
    }
}
