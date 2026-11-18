<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Halaman Tagihan & Pembayaran dulu terkunci 10 baris per halaman. Sekarang
 * bisa dipilih 10/25/50/100 lewat `?per_page=`, dengan whitelist supaya nilai
 * ekstrem (mis. 100000) tidak bisa memaksa server memuat semua baris.
 */
class TagihanDanPembayaranPilihanJumlahBarisTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $ownerRole = Role::where('name', 'Owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);
    }

    private function buatTagihan(int $jumlah): void
    {
        $package = InternetPackage::query()->firstOrFail();

        for ($i = 0; $i < $jumlah; $i++) {
            $customer = Customer::factory()->create(['internet_package_id' => $package->id, 'status' => 'active']);
            $service = CustomerService::create([
                'customer_id' => $customer->id,
                'internet_package_id' => $package->id,
                'package_name_snapshot' => 'Paket Test',
                'monthly_price' => 100000,
                'discount' => 0,
                'ppn' => 0,
                'total_monthly_bill' => 100000,
                'activation_date' => '2026-06-01',
                'due_date' => '2026-06-10',
                'service_status' => 'aktif',
                'billing_status' => 'active',
            ]);
            Invoice::create([
                'invoice_number' => 'INV-BRS-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'invoice_type' => 'bulanan',
                'customer_id' => $customer->id,
                'pop_id' => $customer->pop_id,
                'customer_service_id' => $service->id,
                'internet_package_id' => $package->id,
                'billing_period' => '2026-09',
                'issue_date' => '2026-09-01',
                'due_date' => '2026-09-10',
                'subtotal' => 100000,
                'discount' => 0,
                'ppn' => 0,
                'total_amount' => 100000,
                'paid_amount' => 0,
                'remaining_amount' => 100000,
                'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
            ]);
        }
    }

    /**
     * @return array<string, array{0: string|null, 1: int}>
     */
    public static function pilihanBaris(): array
    {
        return [
            'default' => [null, 10],
            '25' => ['25', 25],
            '50' => ['50', 50],
            '100' => ['100', 100],
            'di luar whitelist' => ['100000', 10],
            'bukan angka' => ['semua', 10],
        ];
    }

    #[Test]
    #[DataProvider('pilihanBaris')]
    public function halaman_tagihan_memakai_jumlah_baris_terpilih(?string $perPage, int $expected): void
    {
        $query = $perPage === null ? [] : ['per_page' => $perPage];

        $invoices = $this->actingAs($this->owner)->get(route('invoices.index', $query))->viewData('invoices');

        $this->assertSame($expected, $invoices->perPage());
    }

    #[Test]
    #[DataProvider('pilihanBaris')]
    public function halaman_pembayaran_memakai_jumlah_baris_terpilih(?string $perPage, int $expected): void
    {
        $query = $perPage === null ? [] : ['per_page' => $perPage];

        $payments = $this->actingAs($this->owner)->get(route('payments.index', $query))->viewData('payments');

        $this->assertSame($expected, $payments->perPage());
    }

    #[Test]
    public function pilihan_baris_tampil_dan_ikut_terbawa_di_link_halaman_berikutnya(): void
    {
        $this->buatTagihan(30);

        $response = $this->actingAs($this->owner)->get(route('invoices.index', ['per_page' => 25]));

        $response->assertSee('name="per_page"', false);
        $response->assertSee('<option value="25" selected>', false);
        $this->assertStringContainsString('per_page=25', $response->viewData('invoices')->nextPageUrl());
        $this->assertCount(25, $response->viewData('invoices')->items());
    }

    #[Test]
    public function footer_tabel_berbahasa_indonesia_dengan_urutan_teks_lalu_pilihan_baris(): void
    {
        $this->buatTagihan(12);

        $response = $this->actingAs($this->owner)->get(route('invoices.index'));

        $response->assertSeeInOrder(['Menampilkan', '1&ndash;10', 'dari', '12', 'tagihan', 'name="per_page"', 'Berikutnya'], false);
        $response->assertDontSee('Showing');
        $response->assertDontSee('results');
    }

    #[Test]
    public function halaman_belum_lunas_juga_mendukung_pilihan_baris(): void
    {
        $invoices = $this->actingAs($this->owner)
            ->get(route('invoices.belum-lunas', ['per_page' => 50]))
            ->viewData('invoices');

        $this->assertSame(50, $invoices->perPage());
    }

    #[Test]
    public function pilihan_baris_tampil_di_halaman_pembayaran(): void
    {
        $this->actingAs($this->owner)->get(route('payments.index'))
            ->assertSee('name="per_page"', false)
            ->assertSee('<option value="10" selected>', false);
    }
}
