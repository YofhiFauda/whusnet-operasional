<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dua card di atas halaman Tagihan dulu berlabel "Belum Dibayar —
 * Pendaftaran/Bulanan", padahal isinya cuma piutang periode lalu, tak ikut
 * filter Cabang, dan melewatkan tagihan manual. Diganti jadi:
 *   1. Penagihan periode (bulan berjalan / filter Periode): sudah masuk vs ditagih.
 *   2. Tunggakan bulan lalu: sisa piutang + jumlah pelanggan, link ke daftarnya.
 */
class TagihanCardProgresDanTunggakanTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Pop $popA;

    private Pop $popB;

    private InternetPackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(now()->parse('2026-09-25 09:00:00'));

        $ownerRole = Role::where('name', 'Owner')->firstOrFail();
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id, 'status' => 'active']);
        $this->package = InternetPackage::query()->firstOrFail();
        $this->popA = Pop::factory()->create(['type' => 'cabang']);
        $this->popB = Pop::factory()->create(['type' => 'cabang']);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function tagihan(Pop $pop, string $periode, int $total, int $dibayar, InvoiceStatus $status, string $jenis = 'bulanan', ?Customer $customer = null): Invoice
    {
        $customer ??= Customer::factory()->create([
            'pop_id' => $pop->id,
            'internet_package_id' => $this->package->id,
            'status' => 'active',
        ]);

        $service = CustomerService::create([
            'customer_id' => $customer->id,
            'internet_package_id' => $this->package->id,
            'package_name_snapshot' => 'Paket Test',
            'monthly_price' => $total,
            'discount' => 0,
            'ppn' => 0,
            'total_monthly_bill' => $total,
            'activation_date' => '2026-06-01',
            'due_date' => '2026-06-10',
            'service_status' => 'aktif',
            'billing_status' => 'active',
        ]);

        return Invoice::create([
            'invoice_number' => 'INV-'.fake()->unique()->numerify('######'),
            'invoice_type' => $jenis,
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'customer_service_id' => $service->id,
            'internet_package_id' => $this->package->id,
            'billing_period' => $periode,
            'issue_date' => $periode.'-01',
            'due_date' => $periode.'-10',
            'subtotal' => $total,
            'discount' => 0,
            'ppn' => 0,
            'total_amount' => $total,
            'paid_amount' => $dibayar,
            'remaining_amount' => $total - $dibayar,
            'invoice_status' => $status->value,
        ]);
    }

    private function isiData(): void
    {
        // Periode berjalan 2026-09, Cabang A
        $this->tagihan($this->popA, '2026-09', 100000, 0, InvoiceStatus::BELUM_DIBAYAR);
        $this->tagihan($this->popA, '2026-09', 50000, 50000, InvoiceStatus::LUNAS);
        $this->tagihan($this->popA, '2026-09', 80000, 30000, InvoiceStatus::SEBAGIAN, 'manual');
        $this->tagihan($this->popA, '2026-09', 40000, 0, InvoiceStatus::BATAL);
        // Periode berjalan, Cabang B
        $this->tagihan($this->popB, '2026-09', 200000, 200000, InvoiceStatus::LUNAS);

        // Tunggakan (periode lalu)
        $budi = $this->tagihan($this->popA, '2026-08', 70000, 0, InvoiceStatus::BELUM_DIBAYAR)->customer;
        $this->tagihan($this->popA, '2026-07', 70000, 0, InvoiceStatus::BELUM_DIBAYAR, 'bulanan', $budi);
        $this->tagihan($this->popA, '2026-08', 50000, 30000, InvoiceStatus::SEBAGIAN, 'awal');
        $this->tagihan($this->popB, '2026-08', 60000, 0, InvoiceStatus::BELUM_DIBAYAR);
        $this->tagihan($this->popA, '2026-08', 90000, 90000, InvoiceStatus::LUNAS);
    }

    #[Test]
    public function card_penagihan_default_bulan_berjalan_semua_jenis_tanpa_tagihan_batal(): void
    {
        $this->isiData();

        $progress = $this->actingAs($this->owner)->get(route('invoices.index'))->viewData('billingProgress');

        $this->assertSame('2026-09', $progress['period']);
        $this->assertEquals(430000, $progress['billed']);
        $this->assertEquals(280000, $progress['collected']);
        $this->assertSame(65, $progress['percent']);
    }

    #[Test]
    public function card_penagihan_ikut_filter_periode_dan_cabang(): void
    {
        $this->isiData();

        $progress = $this->actingAs($this->owner)
            ->get(route('invoices.index', ['billing_period' => '2026-09', 'pop_id' => $this->popA->id]))
            ->viewData('billingProgress');

        $this->assertEquals(230000, $progress['billed']);
        $this->assertEquals(80000, $progress['collected']);
        $this->assertSame(35, $progress['percent']);

        $agustus = $this->actingAs($this->owner)
            ->get(route('invoices.index', ['billing_period' => '2026-08']))
            ->viewData('billingProgress');

        $this->assertSame('2026-08', $agustus['period']);
        $this->assertEquals(270000, $agustus['billed']);
    }

    #[Test]
    public function card_penagihan_tanpa_tagihan_persentase_null(): void
    {
        $progress = $this->actingAs($this->owner)->get(route('invoices.index'))->viewData('billingProgress');

        $this->assertEquals(0, $progress['billed']);
        $this->assertNull($progress['percent']);
    }

    #[Test]
    public function card_tunggakan_hanya_piutang_periode_lalu_dan_menghitung_pelanggan_unik(): void
    {
        $this->isiData();

        $arrears = $this->actingAs($this->owner)->get(route('invoices.index'))->viewData('arrearsSummary');

        // 70k + 70k (pelanggan sama) + 20k sisa cicilan + 60k Cabang B
        $this->assertEquals(220000, $arrears['amount']);
        $this->assertSame(3, $arrears['customer_count']);

        $arrearsA = $this->actingAs($this->owner)
            ->get(route('invoices.index', ['pop_id' => $this->popA->id]))
            ->viewData('arrearsSummary');

        $this->assertEquals(160000, $arrearsA['amount']);
        $this->assertSame(2, $arrearsA['customer_count']);
    }

    #[Test]
    public function link_lihat_daftar_tunggakan_hanya_menampilkan_tagihan_periode_lalu(): void
    {
        $this->isiData();

        $response = $this->actingAs($this->owner)
            ->get(route('invoices.belum-lunas', ['tunggakan' => 1]));

        $periodes = collect($response->viewData('invoices')->items())->pluck('billing_period')->unique()->sort()->values()->all();
        $this->assertSame(['2026-07', '2026-08'], $periodes);
        $this->assertSame(4, $response->viewData('invoices')->total());
    }

    #[Test]
    public function card_baru_tampil_dengan_label_dan_link_tunggakan(): void
    {
        $this->isiData();

        $response = $this->actingAs($this->owner)->get(route('invoices.index', ['pop_id' => $this->popA->id]));

        $response->assertSee('Penagihan September 2026');
        $response->assertSee('Tunggakan Bulan Lalu');
        $response->assertSee('2 pelanggan belum bayar');
        $response->assertSee(route('invoices.belum-lunas', ['tunggakan' => 1, 'pop_id' => $this->popA->id]));
        $response->assertDontSee('Belum Dibayar — Biaya Pendaftaran/Pasang Baru');
    }
}
