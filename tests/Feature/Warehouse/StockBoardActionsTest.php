<?php

namespace Tests\Feature\Warehouse;

use App\Enums\SerialStatus;
use App\Models\InventoryBalance;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleScope;
use App\Services\InventoryReceiveService;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * Menu aksi per baris + payload drawer rincian papan Kelola Stok (spec §3
 * Drawer, §6 Aksi dan lot_no, §8).
 *
 * URL aksi dibandingkan dengan `route(..., [...])` persis — klien tidak boleh
 * merakit path (aturan repo, PostTargetRenderedServerSideTest). Payload drawer
 * dibaca dari atribut `data-stock-detail` (satu sumber: partial `_detail`).
 */
class StockBoardActionsTest extends StockBoardTestCase
{
    private function papan(?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->owner)
            ->get(route('warehouse.stock.index', ['view' => 'papan']))
            ->assertOk();
    }

    private function fragmen(TestResponse $response): string
    {
        $html = $response->getContent();
        $awal = strpos($html, '<!-- papan:awal -->');
        $akhir = strpos($html, '<!-- papan:akhir -->');
        $this->assertNotFalse($awal);
        $this->assertNotFalse($akhir);

        return substr($html, $awal, $akhir - $awal);
    }

    /**
     * Payload drawer per baris, dikunci "popId-itemId".
     *
     * @return array<string, array<string, mixed>>
     */
    private function payloads(string $fragmen): array
    {
        preg_match_all('/data-stock-detail="([^"]*)"/', $fragmen, $matches);
        $result = [];
        foreach ($matches[1] as $encoded) {
            $detail = json_decode(html_entity_decode($encoded, ENT_QUOTES), true);
            $this->assertIsArray($detail, 'payload drawer bukan JSON valid');
            $result[$detail['popId'].'-'.$detail['itemId']] = $detail;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function payload(string $fragmen, Pop $pop, Item $item): array
    {
        $payloads = $this->payloads($fragmen);
        $key = $pop->id.'-'.$item->id;
        $this->assertArrayHasKey($key, $payloads, "payload drawer {$key} tidak dirender");

        return $payloads[$key];
    }

    private function assertHref(string $url, string $fragmen): void
    {
        $this->assertStringContainsString('href="'.e($url).'"', $fragmen, "tautan aksi {$url} tidak dirender server");
    }

    /** Prefix URL (tanpa lot_no) tidak boleh muncul sebagai href sama sekali. */
    private function assertNoHref(string $url, string $fragmen): void
    {
        $this->assertStringNotContainsString('href="'.e($url), $fragmen, "tautan aksi {$url} seharusnya tidak dirender");
    }

    private function sn(Pop $pop, Item $item, string $serial): void
    {
        InventorySerial::create([
            'item_id' => $item->id,
            'serial_number' => $serial,
            'status' => SerialStatus::AVAILABLE->value,
            'current_pop_id' => $pop->id,
        ]);
    }

    private function stokQuantity(Pop $pop, Item $item, float $qty, string $lotNo = ''): void
    {
        InventoryBalance::create(['pop_id' => $pop->id, 'item_id' => $item->id, 'lot_no' => $lotNo, 'qty' => $qty]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function url(string $route, Pop $pop, Item $item, array $extra = []): string
    {
        return route($route, array_merge(['pop_id' => $pop->id, 'item_id' => $item->id], $extra));
    }

    #[Test]
    public function url_aksi_dirender_server_dan_sesuai_tipe_gudang(): void
    {
        $modem = $this->buatItem('SA-MDM', 'Modem Aksi', 'serialized', 'unit', null, 'modem_ont');
        $this->sn($this->pusat, $modem, 'SAM-1');
        $konektor = $this->buatItem('SA-KON', 'Konektor Aksi', 'quantity', 'pcs');
        $this->stokQuantity($this->cabangA, $konektor, 9);

        $fragmen = $this->fragmen($this->papan());

        // Pusat: Kirim Transfer ada; Minta Stok & Serah Teknisi tidak.
        $pusat = $this->payload($fragmen, $this->pusat, $modem)['actions'];
        $this->assertSame($this->url('warehouse.transfers.create', $this->pusat, $modem), $pusat['transferUrl']);
        $this->assertNull($pusat['requestStockUrl']);
        $this->assertNull($pusat['issueUrl']);
        $this->assertSame($this->url('warehouse.adjustments.balance.create', $this->pusat, $modem), $pusat['adjustUrl']);
        $this->assertSame($this->url('warehouse.stock.threshold.create', $this->pusat, $modem), $pusat['thresholdUrl']);
        $this->assertHref($pusat['transferUrl'], $fragmen);
        $this->assertHref($pusat['adjustUrl'], $fragmen);
        $this->assertHref($pusat['thresholdUrl'], $fragmen);
        $this->assertNoHref($this->url('warehouse.stock-requests.create', $this->pusat, $modem), $fragmen);
        $this->assertNoHref($this->url('warehouse.issues.create', $this->pusat, $modem), $fragmen);

        // Cabang: kebalikannya — Minta Stok & Serah Teknisi, tanpa Transfer.
        $cabang = $this->payload($fragmen, $this->cabangA, $konektor)['actions'];
        $this->assertNull($cabang['transferUrl']);
        $this->assertSame($this->url('warehouse.stock-requests.create', $this->cabangA, $konektor, ['lot_no' => '']), $cabang['requestStockUrl']);
        $this->assertSame($this->url('warehouse.issues.create', $this->cabangA, $konektor, ['lot_no' => '']), $cabang['issueUrl']);
        $this->assertHref($cabang['requestStockUrl'], $fragmen);
        $this->assertHref($cabang['issueUrl'], $fragmen);
        $this->assertNoHref($this->url('warehouse.transfers.create', $this->cabangA, $konektor), $fragmen);

        // Teks menu memakai label aksi yang sama dengan layar lama.
        $this->assertStringContainsString('Kirim Transfer', $fragmen);
        $this->assertStringContainsString('Minta Stok', $fragmen);
        $this->assertStringContainsString('Serah Teknisi', $fragmen);
        $this->assertStringNotContainsString('aksi: Task 5', $fragmen);
    }

    #[Test]
    public function quantity_satu_harga_membawa_lot_no_di_semua_url_aksi(): void
    {
        $kabel = $this->buatItem('SA-Q1', 'Kabel Satu Harga', 'quantity', 'meter');
        $lot = 'SA-Q1-20261001-000007';
        $this->stokQuantity($this->cabangA, $kabel, 30, $lot);
        $konektor = $this->buatItem('SA-Q1P', 'Konektor Satu Harga', 'quantity', 'pcs');
        $this->stokQuantity($this->pusat, $konektor, 12, $lot);

        $fragmen = $this->fragmen($this->papan());

        $cabang = $this->payload($fragmen, $this->cabangA, $kabel);
        $withLot = ['lot_no' => $lot];
        $this->assertSame($this->url('warehouse.stock-requests.create', $this->cabangA, $kabel, $withLot), $cabang['actions']['requestStockUrl']);
        $this->assertSame($this->url('warehouse.issues.create', $this->cabangA, $kabel, $withLot), $cabang['actions']['issueUrl']);
        $this->assertSame($this->url('warehouse.adjustments.balance.create', $this->cabangA, $kabel, $withLot), $cabang['actions']['adjustUrl']);
        $this->assertSame($this->url('warehouse.stock.threshold.create', $this->cabangA, $kabel, $withLot), $cabang['actions']['thresholdUrl']);
        foreach (['requestStockUrl', 'issueUrl', 'adjustUrl', 'thresholdUrl'] as $key) {
            $this->assertHref($cabang['actions'][$key], $fragmen);
        }

        $pusat = $this->payload($fragmen, $this->pusat, $konektor);
        $this->assertSame($this->url('warehouse.transfers.create', $this->pusat, $konektor, $withLot), $pusat['actions']['transferUrl']);
        $this->assertHref($pusat['actions']['transferUrl'], $fragmen);

        // Satu tingkat harga tetap tampil di drawer, Opname-nya sama dengan baris.
        $this->assertCount(1, $cabang['priceTiers']);
        $this->assertSame($lot, $cabang['priceTiers'][0]['lotNo']);
        $this->assertSame($cabang['actions']['adjustUrl'], $cabang['priceTiers'][0]['opnameUrl']);
    }

    #[Test]
    public function quantity_dua_harga_menyembunyikan_opname_di_baris_utama_dan_menyediakannya_per_harga(): void
    {
        $dua = $this->buatItem('SA-Q2', 'Dropcore Dua Harga Aksi', 'quantity', 'meter');
        $receive = app(InventoryReceiveService::class);
        $receive->receiveQuantity($this->pusat, $dua, 12, 250000, $this->owner);
        $receive->receiveQuantity($this->pusat, $dua, 10, 260000, $this->owner);
        $lots = InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $dua->id)->orderBy('id')->pluck('lot_no')->all();
        $this->assertCount(2, $lots);

        $fragmen = $this->fragmen($this->papan());
        $detail = $this->payload($fragmen, $this->pusat, $dua);

        // Baris utama: Opname disembunyikan, aksi lain TANPA lot_no.
        $this->assertNull($detail['actions']['adjustUrl']);
        $this->assertSame($this->url('warehouse.transfers.create', $this->pusat, $dua), $detail['actions']['transferUrl']);
        $this->assertSame($this->url('warehouse.stock.threshold.create', $this->pusat, $dua), $detail['actions']['thresholdUrl']);
        $this->assertHref($detail['actions']['transferUrl'], $fragmen);
        $this->assertHref($detail['actions']['thresholdUrl'], $fragmen);
        $this->assertNoHref($this->url('warehouse.adjustments.balance.create', $this->pusat, $dua), $fragmen);
        $this->assertStringContainsString('Opname per harga', $fragmen);

        // Drawer: tiap tingkat harga punya Opname sendiri dengan lot_no-nya.
        $this->assertCount(2, $detail['priceTiers']);
        $byLot = collect($detail['priceTiers'])->keyBy('lotNo');
        foreach ($lots as $lot) {
            $this->assertTrue($byLot->has($lot), "tingkat harga {$lot} tidak ada di payload");
            $this->assertSame($this->url('warehouse.adjustments.balance.create', $this->pusat, $dua, ['lot_no' => $lot]), $byLot[$lot]['opnameUrl']);
        }
        $this->assertEqualsCanonicalizing([250000.0, 260000.0], collect($detail['priceTiers'])->pluck('price')->map(fn ($p) => (float) $p)->all());
        $this->assertEqualsCanonicalizing([12.0, 10.0], collect($detail['priceTiers'])->pluck('qty')->map(fn ($q) => (float) $q)->all());
    }

    #[Test]
    public function serialized_dan_roll_tanpa_lot_no(): void
    {
        $modem = $this->buatItem('SA-SN', 'Modem Tanpa Lot', 'serialized', 'unit', null, 'modem_ont');
        $this->sn($this->pusat, $modem, 'SAS-1');
        $fo = $this->buatItem('SA-FO', 'Kabel FO Aksi', 'roll', 'meter', 1000);
        app(InventoryReceiveService::class)->receiveRoll($this->pusat, $fo, 1, null, 1500000, $this->owner);

        $fragmen = $this->fragmen($this->papan());

        foreach ([$modem, $fo] as $item) {
            $detail = $this->payload($fragmen, $this->pusat, $item);
            $this->assertSame([], $detail['priceTiers']);
            foreach ($detail['actions'] as $key => $url) {
                if ($url !== null) {
                    $this->assertStringNotContainsString('lot_no', $url, "{$key} {$item->code} tidak boleh membawa lot_no");
                }
            }
            $this->assertSame($this->url('warehouse.adjustments.balance.create', $this->pusat, $item), $detail['actions']['adjustUrl']);
        }
    }

    #[Test]
    public function aksi_hilang_bila_permission_tidak_ada(): void
    {
        $modem = $this->buatItem('SA-VW', 'Modem Lihat Saja', 'serialized', 'unit', null, 'modem_ont');
        $this->sn($this->pusat, $modem, 'SAV-1');
        $dua = $this->buatItem('SA-VQ', 'Kabel Lihat Saja', 'quantity', 'meter');
        $receive = app(InventoryReceiveService::class);
        $receive->receiveQuantity($this->pusat, $dua, 5, 1000, $this->owner);
        $receive->receiveQuantity($this->pusat, $dua, 6, 1100, $this->owner);
        $konektor = $this->buatItem('SA-VK', 'Konektor Lihat Saja', 'quantity', 'pcs');
        $this->stokQuantity($this->cabangA, $konektor, 3);

        $role = Role::create(['name' => 'Gudang Lihat Saja', 'code' => 'gudang-lihat-'.uniqid(), 'is_system' => false]);
        $role->permissions()->attach(Permission::where('code', 'warehouse.view')->firstOrFail());
        $viewer = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
        $scope = UserRoleScope::create(['user_id' => $viewer->id, 'role_id' => $role->id, 'scope_type' => 'selected_pop']);
        $scope->targets()->create(['pop_id' => $this->pusat->id]);
        $scope->targets()->create(['pop_id' => $this->cabangA->id]);

        $fragmen = $this->fragmen($this->papan($viewer));

        // Drawer tetap bisa dibuka (lihat rincian), tetapi tanpa satu aksi pun.
        foreach ([[$this->pusat, $modem], [$this->pusat, $dua], [$this->cabangA, $konektor]] as [$pop, $item]) {
            $detail = $this->payload($fragmen, $pop, $item);
            $this->assertSame([null], array_values(array_unique(array_values($detail['actions']), SORT_REGULAR)));
            foreach ($detail['priceTiers'] as $tier) {
                $this->assertNull($tier['opnameUrl']);
            }
        }
        foreach (['warehouse.transfers.create', 'warehouse.stock-requests.create', 'warehouse.issues.create', 'warehouse.adjustments.balance.create', 'warehouse.stock.threshold.create'] as $route) {
            $this->assertStringNotContainsString('href="'.e(route($route)), $fragmen, "{$route} dirender tanpa permission");
        }
        $this->assertStringNotContainsString('⋮ Aksi', $fragmen);
        $this->assertStringNotContainsString('Opname per harga', $fragmen);
    }

    #[Test]
    public function drawer_memuat_url_serials_dan_rolls_dari_route_bukan_dirakit_klien(): void
    {
        $modem = $this->buatItem('SA-DS', 'Modem Drawer', 'serialized', 'unit', null, 'modem_ont');
        $this->sn($this->pusat, $modem, 'SAD-1');
        $fo = $this->buatItem('SA-DR', 'Kabel Drawer', 'roll', 'meter', 1000);
        app(InventoryReceiveService::class)->receiveRoll($this->pusat, $fo, 1, null, 1500000, $this->owner);
        $konektor = $this->buatItem('SA-DQ', 'Konektor Drawer', 'quantity', 'pcs');
        $this->stokQuantity($this->pusat, $konektor, 4);

        $fragmen = $this->fragmen($this->papan());

        $sn = $this->payload($fragmen, $this->pusat, $modem);
        $this->assertSame(route('warehouse.stock.serials', ['pop_id' => $this->pusat->id, 'item_id' => $modem->id]), $sn['serialsUrl']);
        $this->assertNull($sn['rollsUrl']);
        $this->assertSame('serialized', $sn['tracking']);
        $this->assertSame('Modem Drawer', $sn['itemName']);
        $this->assertSame('SA-DS', $sn['itemCode']);
        $this->assertSame('Pusat Papan', $sn['popName']);
        $this->assertNotEmpty($sn['categoryName']);

        $roll = $this->payload($fragmen, $this->pusat, $fo);
        $this->assertSame(route('warehouse.stock.rolls', ['pop_id' => $this->pusat->id, 'item_id' => $fo->id]), $roll['rollsUrl']);
        $this->assertNull($roll['serialsUrl']);
        $this->assertSame('meter', $roll['unit']);

        $qty = $this->payload($fragmen, $this->pusat, $konektor);
        $this->assertNull($qty['serialsUrl']);
        $this->assertNull($qty['rollsUrl']);

        // Komponen drawer hanya ada sekali, mendengar event, dan berlabel dialog.
        $this->assertSame(1, substr_count($fragmen, 'open-stock-drawer.window'));
        $this->assertStringContainsString('role="dialog"', $fragmen);
        $this->assertStringContainsString('aria-modal="true"', $fragmen);

        // Sumber drawer tidak merakit URL endpoint/aksi sendiri.
        foreach (['_drawer', '_actions'] as $partial) {
            $source = file_get_contents(resource_path("views/warehouse/stock/board/{$partial}.blade.php"));
            $this->assertStringNotContainsString('pop_id=', $source, "{$partial} merakit query string di klien");
            $this->assertStringNotContainsString('/warehouse/', $source, "{$partial} menulis path route di klien");
            $this->assertStringNotContainsString('${', $source, "{$partial} merakit URL dengan template literal");
        }
    }

    #[Test]
    public function endpoint_serials_dan_rolls_luar_scope_tetap_ditolak(): void
    {
        $modem = $this->buatItem('SA-SC', 'Modem Scope', 'serialized', 'unit', null, 'modem_ont');
        $this->sn($this->cabangB, $modem, 'SAC-1');
        $popAdminA = $this->userDenganScope([$this->cabangA->id]);

        // Dalam scope: endpoint dapat diakses (memastikan 403 di bawah karena
        // scope, bukan karena permission halaman).
        $this->actingAs($popAdminA)
            ->getJson(route('warehouse.stock.serials', ['pop_id' => $this->cabangA->id, 'item_id' => $modem->id]))
            ->assertOk();

        foreach (['warehouse.stock.serials', 'warehouse.stock.rolls'] as $route) {
            $this->actingAs($popAdminA)
                ->getJson(route($route, ['pop_id' => $this->cabangB->id, 'item_id' => $modem->id]))
                ->assertForbidden();
        }
    }
}
