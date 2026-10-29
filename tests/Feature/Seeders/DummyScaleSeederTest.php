<?php

namespace Tests\Feature\Seeders;

use App\Models\Customer;
use Database\Seeders\DummyScaleSeeder;
use Database\Seeders\InternetPackageSeeder;
use Database\Seeders\ItemCategorySeeder;
use Database\Seeders\ItemSeeder;
use Database\Seeders\MasterPopSeeder;
use Database\Seeders\NetworkNodeTypeSeeder;
use Database\Seeders\PonorogoRegionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TicketIssueCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DummyScaleSeeder menulis lewat DB::table() (melewati observer), jadi
 * invarian keuangan/inventori yang biasanya dijaga observer dipastikan di sini.
 */
class DummyScaleSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PonorogoRegionSeeder::class,
            InternetPackageSeeder::class,
            RoleSeeder::class,
            NetworkNodeTypeSeeder::class,
            ItemCategorySeeder::class,
            ItemSeeder::class,
            MasterPopSeeder::class,
            TicketIssueCategorySeeder::class,
        ]);

        // Kategori paket Bisnis = kategori yang punya role validator Biaya Instalasi (BD).
        DB::table('package_categories')->where('name', 'like', 'Paket Bisnis%')
            ->update(['installation_fee_approval_role_id' => DB::table('roles')->where('code', 'business_development')->value('id')]);
        DB::table('pops')->insertOrIgnore([
            'code' => 'CN', 'pop_code' => 'CONNEXA', 'name' => 'CONNEXA', 'type' => 'pusat', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'CN', 'created_at' => now(), 'updated_at' => now(),
        ]);

        putenv('DUMMY_CUSTOMERS=100');
    }

    protected function tearDown(): void
    {
        putenv('DUMMY_CUSTOMERS');
        putenv('DUMMY_PURGE');

        parent::tearDown();
    }

    #[Test]
    public function tagihan_dummy_konsisten_dengan_item_dan_pembayaran(): void
    {
        $this->seed(DummyScaleSeeder::class);

        $this->assertSame(100, Customer::where('registered_by_name', 'DUMMY-SEED')->count());
        $this->assertGreaterThan(0, DB::table('invoices')->count());

        $itemMismatch = DB::table('invoices as i')
            ->join(DB::raw('(select invoice_id, sum(amount) s from invoice_items group by invoice_id) x'), 'x.invoice_id', '=', 'i.id')
            ->whereRaw('x.s <> i.subtotal')->count();
        $this->assertSame(0, $itemMismatch, 'SUM(invoice_items) harus = subtotal');

        $paidMismatch = DB::table('invoices as i')
            ->leftJoin(DB::raw('(select invoice_id, sum(amount) s from payments group by invoice_id) x'), 'x.invoice_id', '=', 'i.id')
            ->whereRaw('coalesce(x.s, 0) <> i.paid_amount')->count();
        $this->assertSame(0, $paidMismatch, 'paid_amount harus = SUM(payments)');

        $this->assertSame(0, DB::table('payments')->where('amount', '<=', 0)->count());
        $this->assertSame(0, DB::table('invoices')->where('invoice_status', 'lunas')->where('remaining_amount', '>', 0)->count());
    }

    #[Test]
    public function satu_tagihan_langganan_per_pelanggan_per_periode(): void
    {
        $this->seed(DummyScaleSeeder::class);

        $dobel = DB::table('invoices')->select('customer_id', 'billing_period')
            ->groupBy('customer_id', 'billing_period')->havingRaw('count(*) > 1')->count();

        $this->assertSame(0, $dobel);
        $this->assertSame(3, DB::table('invoices')->distinct()->count('billing_period'));
    }

    #[Test]
    public function pelanggan_terpasang_punya_cid_port_odp_dan_ont_satu_satu(): void
    {
        $this->seed(DummyScaleSeeder::class);

        $billed = Customer::where('registered_by_name', 'DUMMY-SEED')->whereIn('status', ['active', 'suspended']);

        $this->assertSame(0, (clone $billed)->whereNull('cid')->count());
        $this->assertSame((clone $billed)->count(), DB::table('network_port_assignments')->where('active_key', 1)->count());
        $this->assertSame((clone $billed)->count(), DB::table('inventory_serials')->where('status', 'installed')->count());
    }

    #[Test]
    public function pelanggan_bisnis_sales_dan_agent_terisi_konsisten(): void
    {
        $this->seed(DummyScaleSeeder::class);

        $businessCategories = DB::table('package_categories')->whereNotNull('installation_fee_approval_role_id')->pluck('name');
        $businessIds = DB::table('customers as c')->join('internet_packages as p', 'p.id', '=', 'c.internet_package_id')
            ->where('c.registered_by_name', 'DUMMY-SEED')->whereIn('p.category', $businessCategories)->pluck('c.id');

        $this->assertNotEmpty($businessIds, 'harus ada pelanggan Bisnis dummy');
        $this->assertSame($businessIds->count(), DB::table('customer_acquisitions')->whereIn('customer_id', $businessIds)->whereNotNull('installation_fee')->count());
        $this->assertSame(0, DB::table('customers as c')->join('internet_packages as p', 'p.id', '=', 'c.internet_package_id')
            ->where('c.registered_by_name', 'DUMMY-SEED')->where('c.customer_type', 'RUMAHAN')->whereIn('p.category', $businessCategories)->count(), 'pelanggan rumahan tak boleh pakai paket Bisnis');
        $this->assertGreaterThan(0, DB::table('customers')->where('registered_by_name', 'DUMMY-SEED')->whereNotNull('sales_user_id')->count());
        $this->assertGreaterThan(0, DB::table('customers')->where('registered_by_name', 'DUMMY-SEED')->whereNotNull('agent_id')->count());
        $this->assertSame(0, DB::table('customers')->whereNotNull('sales_user_id')->whereNotNull('agent_id')->count(), 'sales & agent saling eksklusif');
    }

    #[Test]
    public function tiket_fop_task_dan_task_teknisi_sinkron(): void
    {
        $this->seed(DummyScaleSeeder::class);

        $fopTickets = DB::table('tickets')->where('handler', 'fop');
        $this->assertGreaterThan(0, (clone $fopTickets)->count());
        // FopTask hanya lahir dari eskalasi ke FOP: tiket non-FOP tak boleh punya fop_task_id, tiket FOP wajib.
        $this->assertSame(0, DB::table('tickets')->where('handler', '!=', 'fop')->whereNotNull('fop_task_id')->count());
        $this->assertSame(0, (clone $fopTickets)->whereNull('fop_task_id')->count());

        $this->assertSame(0, DB::table('fop_tasks as f')->join('tasks as t', 't.id', '=', 'f.task_id')->whereColumn('f.status', '!=', 't.status')->count(), 'status FopTask harus sama dengan Task');
        $this->assertSame(0, DB::table('tasks')->where('status', 'lapor_nanti')->whereNotNull('completed_at')->count(), 'Lapor Nanti belum punya completed_at');
        $this->assertSame(0, DB::table('tasks')->where('status', 'selesai')->whereNull('completed_at')->count());

        // pending melepas tim; dibatalkan juga tidak menyisakan tim.
        $this->assertSame(0, DB::table('task_teams as tt')->join('tasks as t', 't.id', '=', 'tt.task_id')->whereIn('t.status', ['pending', 'dibatalkan'])->count());

        $this->assertSame(0, DB::table('tickets')->selectRaw('count(*) as n')->groupBy('ticket_number')->havingRaw('count(*) > 1')->get()->count(), 'nomor tiket unik');
        $this->assertSame(0, DB::table('fop_tasks')->groupBy('task_number')->havingRaw('count(*) > 1')->select('task_number')->get()->count());
        $this->assertSame(0, DB::table('tasks')->groupBy('task_number')->havingRaw('count(*) > 1')->select('task_number')->get()->count());
        $this->assertGreaterThan(0, DB::table('ticket_histories')->where('action', 'dieskalasi')->count());
        $this->assertGreaterThan(0, DB::table('tasks')->whereIn('task_type', ['SURVEY', 'PSB', 'DEAC'])->count());
    }

    #[Test]
    public function permintaan_stok_gudang_konsisten(): void
    {
        $this->seed(DummyScaleSeeder::class);

        $this->assertGreaterThan(0, DB::table('stock_requests')->count());
        $this->assertSame(0, DB::table('stock_request_items')->whereColumn('qty_fulfilled', '>', 'qty_requested')->count());
        $fulfilledMismatch = DB::table('stock_request_items as i')->join('stock_requests as r', 'r.id', '=', 'i.stock_request_id')
            ->where('r.status', 'fulfilled')->whereColumn('i.qty_fulfilled', '!=', 'i.qty_requested')->count();
        $this->assertSame(0, $fulfilledMismatch);
        $this->assertSame(0, DB::table('stock_requests')->where('status', 'pending')->whereNotNull('decided_at')->count());
    }

    #[Test]
    public function seeder_tidak_jalan_dua_kali_dan_purge_membersihkan_semuanya(): void
    {
        $this->seed(DummyScaleSeeder::class);
        $customers = Customer::count();
        $this->seed(DummyScaleSeeder::class);
        $this->assertSame($customers, Customer::count(), 'run kedua harus skip');

        putenv('DUMMY_PURGE=1');
        $this->seed(DummyScaleSeeder::class);

        foreach (['customers', 'invoices', 'payments', 'invoice_items', 'inventory_serials', 'inventory_transactions', 'inventory_rolls', 'network_nodes', 'network_node_ports', 'technician_custody', 'customer_acquisitions', 'tickets', 'ticket_histories', 'fop_tasks', 'fop_task_teams', 'tasks', 'task_teams', 'task_reports', 'stock_requests', 'stock_request_items', 'agents'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "{$table} harus kosong setelah purge");
        }
        $this->assertSame(0, DB::table('users')->where('email', 'like', 'dummy.%@whusnet.test')->count());
    }
}
