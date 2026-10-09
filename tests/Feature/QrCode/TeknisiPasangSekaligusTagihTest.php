<?php

namespace Tests\Feature\QrCode;

use App\Enums\ScopeType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Models\UserRoleScopeTarget;
use App\Services\CustomerQrTokenService;
use App\Services\TaskService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\CustomerPortal\Concerns\InteractsWithPortalAuth;
use Tests\TestCase;

/**
 * Simulasi end-to-end: satu teknisi memasang sekaligus menagih pelanggan yang
 * sama pada hari yang sama. Urutannya mengikuti yang dilakukan teknisi di
 * lapangan:
 *
 *   1. scan QR pelanggan   → diarahkan ke ABSEN (task pemasangan terjadwal hari ini)
 *   2. absen dengan GPS    → task jadi in_progress
 *   3. scan QR lagi        → diarahkan ke PORTAL teknisi (tagihan), bawa staff_token
 *   4. worklist + bayar    → pembayaran tercatat sebagai teknisi, token dikonsumsi
 *
 * Data pelanggan dibuat seperti data asli (nama, kode, alamat, paket, tagihan
 * bulan berjalan), tapi hanya di database test yang di-reset setiap kali.
 */
class TeknisiPasangSekaligusTagihTest extends TestCase
{
    use InteractsWithPortalAuth;
    use RefreshDatabase;

    private const CUSTOMER_CODE = 'C1X4ARQ000631';

    private const CUSTOMER_NAME = 'Masudah Yuni Fitri';

    private Pop $pop;

    private Customer $customer;

    private User $owner;

    private User $teknisi;

    private InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPortalClientSecret();
        config(['qr.secret' => 'test-qr-hmac-simulasi-teknisi', 'qr.portal_base_url' => 'https://portal.test']);

        $this->seed(DatabaseSeeder::class);
        $this->package = InternetPackage::query()->firstOrFail();

        $this->pop = Pop::create([
            'code' => 'POP-SIM', 'pop_code' => 'SIM', 'registration_prefix' => 'C', 'cid_prefix' => 'S',
            'name' => 'POP Simulasi Teknisi', 'type' => 'cabang', 'status' => 'active',
        ]);

        $this->customer = $this->buatPelanggan();
        $this->owner = $this->userDenganScope('owner');
        $this->teknisi = $this->userDenganScope('teknisi');
    }

    private function userDenganScope(string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $scope = UserRoleScope::create(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => ScopeType::SELECTED_POP]);
        UserRoleScopeTarget::create(['user_role_scope_id' => $scope->id, 'pop_id' => $this->pop->id]);

        return $user;
    }

    private function buatPelanggan(): Customer
    {
        $customer = Customer::create([
            'customer_code' => self::CUSTOMER_CODE,
            'full_name' => self::CUSTOMER_NAME,
            'primary_phone' => '081330445566',
            'registration_date' => now()->subDays(3)->toDateString(),
            'status' => 'installation_in_progress',
            'data_completeness_status' => 'siap_billing',
            'pop_id' => $this->pop->id,
            'internet_package_id' => $this->package->id,
            'address' => 'Dusun Krajan RT 02 RW 01',
            'latitude' => -7.5000000,
            'longitude' => 111.5000000,
        ]);

        CustomerAddress::create([
            'customer_id' => $customer->id,
            'full_address' => 'Dusun Krajan RT 02 RW 01',
            'village' => 'Krajan', 'district' => 'Jetis', 'city' => 'Ponorogo', 'province' => 'Jawa Timur',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => $this->package->name,
            'monthly_price' => 150000,
            'discount' => 0, 'ppn' => 0, 'total_monthly_bill' => 150000,
            'activation_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'service_status' => 'aktif', 'billing_status' => 'active',
        ]);

        // Tagihan bulan berjalan: periode sudah berjalan, jadi boleh ditagih.
        Invoice::create([
            'invoice_number' => 'INV-SIM-'.now()->format('Ym').'-000631',
            'invoice_type' => 'bulanan',
            'customer_id' => $customer->id,
            'pop_id' => $this->pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => now()->format('Y-m'),
            'issue_date' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'subtotal' => 150000, 'discount' => 0, 'ppn' => 0, 'total_amount' => 150000,
            'paid_amount' => 0, 'remaining_amount' => 150000, 'invoice_status' => 'belum_dibayar',
        ]);

        return $customer;
    }

    /**
     * Task PEMASANGAN terjadwal hari ini, dengan teknisi sebagai anggota tim.
     */
    private function taskPemasanganHariIni(): Task
    {
        return app(TaskService::class)->create([
            'customer_id' => $this->customer->id,
            'pop_id' => $this->pop->id,
            'task_type' => TaskType::PEMASANGAN->value,
            'title' => 'Pemasangan '.self::CUSTOMER_NAME,
            'team_member_ids' => [$this->teknisi->id],
            'scheduled_at' => now()->toDateTimeString(),
        ], $this->owner);
    }

    /**
     * Kode QR pelanggan dalam format `TOKEN.SIGNATURE` yang dipakai URL /q1/{code}.
     */
    private function kodeQr(): string
    {
        $service = app(CustomerQrTokenService::class);
        $token = $service->issue($this->customer);
        $signature = $service->signature($this->pop->id, $this->customer->customer_code, $token->token);

        return "{$token->token}.{$signature}";
    }

    /**
     * Ambil `staff_token` dari URL redirect ke Portal.
     */
    private function staffTokenDari(TestResponse $response): string
    {
        $location = $response->headers->get('Location');
        $this->assertNotNull($location, 'Redirect ke Portal harus punya Location.');

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('staff_token', $query, 'Redirect ke Portal harus membawa staff_token.');

        return $query['staff_token'];
    }

    #[Test]
    public function teknisi_pasang_lalu_tagih_pelanggan_yang_sama_di_hari_yang_sama(): void
    {
        $task = $this->taskPemasanganHariIni();
        $kode = $this->kodeQr();

        // 1. Scan pertama: task pemasangan terjadwal hari ini → ke halaman absen.
        $scanPertama = $this->actingAs($this->teknisi)->get("/q1/{$kode}");
        $scanPertama->assertRedirect(route('qr.attendance.show', ['code' => $kode]));

        // Tagihan belum ditawarkan sebelum teknisi absen di lokasi.
        $this->assertSame(0, Payment::query()->count());

        // 2. Absen di lokasi pelanggan (koordinat sama dengan titik pelanggan).
        $absen = $this->actingAs($this->teknisi)->post(route('qr.attendance.store', ['code' => $kode]), [
            'latitude' => -7.5000000,
            'longitude' => 111.5000000,
            'accuracy' => 8,
        ]);
        $absen->assertRedirect(route('tasks.show', $task));

        $task->refresh();
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->status);
        $this->assertSame('qr_scan', $task->started_via);

        // 3. Scan kedua: task sudah in_progress, jadi tidak lagi "terjadwal hari ini".
        //    Teknisi juga punya tickets.qr.create, jadi dua jalur eligible → halaman
        //    pilihan (bukan langsung Portal). Teknisi memilih "kolektor" (tagih).
        $scanKedua = $this->actingAs($this->teknisi)->get("/q1/{$kode}");
        $scanKedua->assertRedirect(route('qr.scan.choose', ['code' => $kode]));

        $pilih = $this->actingAs($this->teknisi)->post(route('qr.scan.choose.confirm', ['code' => $kode]), [
            'action' => 'kolektor',
        ]);

        $staffToken = $this->staffTokenDari($pilih);
        $this->assertStringContainsString('/teknisi', (string) $pilih->headers->get('Location'));

        // 4a. Portal membaca worklist: tagihan bulan berjalan pelanggan ini muncul.
        $worklist = $this->withHeaders($this->portalClientHeaders() + ['Authorization' => "Bearer {$staffToken}"])
            ->getJson("/api/customer-portal/teknisi/worklist/{$kode}");
        $worklist->assertOk();
        $worklist->assertJsonPath('data.customer.full_name', self::CUSTOMER_NAME);
        $this->assertCount(1, $worklist->json('data.invoices'));

        // 4b. Teknisi mencatat pembayaran tunai.
        $invoice = Invoice::where('customer_id', $this->customer->id)->firstOrFail();
        $bayar = $this->withHeaders($this->portalClientHeaders() + ['Authorization' => "Bearer {$staffToken}"])
            ->postJson('/api/customer-portal/teknisi/payments', [
                'idempotency_key' => 'sim-pasang-tagih-000631',
                'rows' => [
                    ['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => now()->toDateString()],
                ],
            ]);

        $bayar->assertOk()->assertJson(['success' => true, 'processed' => 1]);

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('teknisi', $payment->collected_by_role);
        $this->assertSame($this->teknisi->id, (int) $payment->collected_by);
        $this->assertSame('lunas', $invoice->fresh()->invoice_status->value ?? $invoice->fresh()->invoice_status);

        // Token staf sekali pakai: setelah bayar tidak bisa dipakai lagi.
        $this->withHeaders($this->portalClientHeaders() + ['Authorization' => "Bearer {$staffToken}"])
            ->postJson('/api/customer-portal/teknisi/payments', [
                'idempotency_key' => 'sim-pasang-tagih-ulang',
                'rows' => [
                    ['invoice_id' => $invoice->id, 'amount' => 150000, 'payment_method' => 'cash', 'collected_date' => now()->toDateString()],
                ],
            ])
            ->assertUnauthorized();

        $this->assertSame(1, Payment::query()->count());
    }
}
