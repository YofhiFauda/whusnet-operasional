<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Enums\ScopeType;
use App\Enums\WorkflowTransition;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerStatusLog;
use App\Models\CustomerSurvey;
use App\Models\District;
use App\Models\FopTask;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\ActionSeeder;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bypass Pelanggan (2026-11-15) — pelanggan legacy yang terlewat migrasi
 * (sudah terpasang, administrasi beres di luar sistem) didaftarkan lewat
 * `/bypass` dan LANGSUNG aktif: tanpa verifikasi registrasi, survey,
 * pemasangan, maupun Tagihan Awal. Tagihan bulanan baru jalan periode
 * berikutnya. Gerbang: `customers.registration.bypass` (Owner & CS).
 */
class CustomerBypassTest extends TestCase
{
    use RefreshDatabase;

    private City $city;

    private District $district;

    private Village $village;

    private Pop $pop;

    private InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FeatureSeeder::class);
        $this->seed(ActionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->city = City::create(['name' => 'Ponorogo']);
        $this->district = District::create(['city_id' => $this->city->id, 'name' => 'Babadan']);
        $this->village = Village::create(['district_id' => $this->district->id, 'name' => 'Babadan']);
        $this->pop = $this->makePop('BBD', 'BBD-01');
        $this->package = InternetPackage::create([
            'name' => 'Paket 1',
            'package_code' => 'PKT1',
            'category' => 'Home',
            'package_group' => 'Basic',
            'bandwidth_label' => '10 Mbps',
            'monthly_price' => 150000,
        ]);
    }

    private function makePop(string $code, string $cidPrefix): Pop
    {
        return Pop::create([
            'name' => 'POP '.$code,
            'type' => 'cabang',
            'code' => $code,
            'cid_prefix' => $cidPrefix,
            'registration_prefix' => 'REG-'.$code,
        ]);
    }

    private function makeCs(?Pop $scopePop = null): User
    {
        $role = Role::where('code', 'customer_service')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        if ($scopePop) {
            $user->roleScopes()->create([
                'role_id' => $role->id,
                'scope_type' => ScopeType::SELECTED_POP->value,
            ])->targets()->create(['pop_id' => $scopePop->id]);
        } else {
            $user->roleScopes()->create([
                'role_id' => $role->id,
                'scope_type' => ScopeType::ALL_POP->value,
            ]);
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Pelanggan Legacy Terlewat',
            'identity_number' => '3502181010900001',
            'gender' => 'Laki-laki',
            'primary_phone' => '08123456789',
            'registration_date' => '2026-11-10',
            'pop_id' => $this->pop->id,
            'address' => 'Jl. Lama No. 1',
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'village_id' => $this->village->id,
            'internet_package_id' => $this->package->id,
            'contract_period_months' => 12,
        ], $overrides);
    }

    #[Test]
    public function bypass_langsung_aktif_tanpa_task_survey_dan_tagihan_awal(): void
    {
        $cs = $this->makeCs();

        $response = $this->actingAs($cs)->post(route('customers.bypass.store'), $this->payload());

        $customer = Customer::where('full_name', 'Pelanggan Legacy Terlewat')->firstOrFail();
        $response->assertRedirect(route('customers.show', $customer->id));

        $this->assertSame(WorkflowTransition::ACTIVE->value, $customer->status);
        $this->assertNotNull($customer->cid);
        $this->assertStringStartsWith('BBD-01', $customer->cid);
        $this->assertNull($customer->mini_pop_id);
        $this->assertNull($customer->distribution_id);

        $service = $customer->customerService;
        $this->assertSame('aktif', $service->service_status);
        $this->assertSame('active', $service->billing_status);
        $this->assertSame('2026-11-10', $service->activation_date->format('Y-m-d'));
        $this->assertSame($cs->id, $service->activated_by_user_id);

        $this->assertSame(0, Task::count());
        $this->assertSame(0, FopTask::count());
        $this->assertSame(0, CustomerSurvey::count());
        $this->assertSame(0, Invoice::count());

        $log = CustomerStatusLog::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(WorkflowTransition::ACTIVE->value, $log->to_status);
        $this->assertSame('Aktif lewat Bypass (data migrasi terlewat)', $log->note);
        $this->assertSame($cs->id, $log->changed_by);
    }

    #[Test]
    public function role_tanpa_permission_bypass_ditolak(): void
    {
        $sales = User::factory()->create(['role_id' => Role::where('code', 'sales')->firstOrFail()->id]);

        $this->actingAs($sales)->get(route('customers.bypass.create'))->assertForbidden();
        $this->actingAs($sales)->post(route('customers.bypass.store'), $this->payload())->assertForbidden();

        $this->assertSame(0, Customer::count());
    }

    #[Test]
    public function pop_di_luar_scope_ditolak(): void
    {
        $otherPop = $this->makePop('SIM', 'SIM-01');
        $cs = $this->makeCs($this->pop);

        $this->actingAs($cs)
            ->post(route('customers.bypass.store'), $this->payload(['pop_id' => $otherPop->id]))
            ->assertSessionHasErrors('pop_id');

        $this->assertSame(0, Customer::count());
    }

    #[Test]
    public function field_survey_dikirim_ke_bypass_diabaikan(): void
    {
        Storage::fake('public');
        $cs = $this->makeCs();

        $this->actingAs($cs)->post(route('customers.bypass.store'), $this->payload([
            'skip_survey' => 1,
            'nearest_odp' => 'ODP-01',
            'cable_estimation_meter' => 50,
            'difficulty_level' => 'MUDAH',
            'survey_photo' => UploadedFile::fake()->image('odp.jpg'),
        ]))->assertSessionHasNoErrors();

        $customer = Customer::firstOrFail();
        $this->assertSame(WorkflowTransition::ACTIVE->value, $customer->status);
        $this->assertSame(0, CustomerSurvey::count());
        $this->assertSame(0, Task::count());
    }

    #[Test]
    public function tagihan_bulanan_mulai_periode_berikutnya(): void
    {
        $cs = $this->makeCs();
        $this->actingAs($cs)->post(route('customers.bypass.store'), $this->payload());

        $this->artisan('billing:generate-monthly-invoices', ['--period' => '2026-11']);
        $this->assertSame(0, Invoice::count());

        $this->artisan('billing:generate-monthly-invoices', ['--period' => '2026-12']);
        $this->assertSame(1, Invoice::where('invoice_type', InvoiceType::BULANAN->value)->count());
    }

    /**
     * Di Registrasi biasa tanggal ini cuma tanggal daftar (ditimpa saat
     * aktivasi). Di Bypass dia JADI activation_date — tanggal masa depan
     * bikin generator menagih bulan-bulan sebelum pelanggan aktif.
     */
    #[Test]
    public function tanggal_aktivasi_masa_depan_ditolak(): void
    {
        $cs = $this->makeCs();

        $this->actingAs($cs)
            ->post(route('customers.bypass.store'), $this->payload([
                'registration_date' => now()->addDay()->format('Y-m-d'),
            ]))
            ->assertSessionHasErrors('registration_date');

        $this->assertSame(0, Customer::count());
    }

    /**
     * Keputusan user 2026-11-15: Bypass sengaja TIDAK ditampilkan di UI
     * (tanpa menu sidebar) — diakses langsung lewat URL `/bypass` oleh
     * yang tahu, supaya tidak jadi jalan pintas pendaftaran harian.
     */
    #[Test]
    public function menu_bypass_tidak_tampil_di_sidebar(): void
    {
        $cs = $this->makeCs();

        $this->actingAs($cs)
            ->get(route('customers.create'))
            ->assertOk()
            ->assertDontSee('Bypass Pelanggan')
            ->assertDontSee(route('customers.bypass.create').'"', false);
    }

    #[Test]
    public function halaman_bypass_tampil_untuk_cs(): void
    {
        $cs = $this->makeCs();

        $this->actingAs($cs)
            ->get(route('customers.bypass.create'))
            ->assertOk()
            ->assertSee('Bypass Pelanggan')
            ->assertSee(route('customers.bypass.store'), false);
    }
}
