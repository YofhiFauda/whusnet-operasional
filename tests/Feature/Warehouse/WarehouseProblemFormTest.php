<?php

namespace Tests\Feature\Warehouse;

use App\Enums\SerialStatus;
use App\Models\InventoryBalance;
use App\Services\InventoryReceiveService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;

/**
 * Form "Ada barang bermasalah" — kejadian (hilang/rusak/dibuang/karantina/
 * salah catat) dipetakan ke field endpoint lama: SN/roll → `new_status`,
 * barang curah → `qty_delta` bertanda. Test POST di bawah memakai persis
 * nilai yang dikirim form, supaya pemetaan di view tidak bisa melenceng
 * dari yang diterima controller.
 */
class WarehouseProblemFormTest extends StockBoardTestCase
{
    #[Test]
    public function form_masalah_merender_url_balance_dari_server_dan_tanpa_url_sn_rakitan(): void
    {
        $html = $this->actingAs($this->owner)->get(route('warehouse.adjustments.problem.create'))
            ->assertOk()
            ->getContent();

        $balanceUrl = e(route('warehouse.adjustments.balance.store'));
        $this->assertStringContainsString('action="'.$balanceUrl.'"', $html);
        $this->assertStringContainsString('data-url-qty="'.$balanceUrl.'"', $html);
        // URL per-SN datang dari respons resolve (adjust_url), bukan template string.
        $this->assertStringNotContainsString('/adjustments/serial/${', $html);
        $this->assertStringContainsString('result.adjust_url', $html);
    }

    #[Test]
    public function keterangan_tertulis_wajib_dan_ikut_tersimpan_di_reason(): void
    {
        $html = $this->actingAs($this->owner)->get(route('warehouse.adjustments.problem.create'))->getContent();

        // Audit (spec §6 risiko 4): kode kejadian saja tidak cukup — dulu form
        // lama mewajibkan alasan diketik. Keterangan digabung ke `reason`.
        $this->assertMatchesRegularExpression('/<textarea[^>]*id="prb-detail"[^>]*required/s', $html);
        $this->assertStringContainsString("eventKey + ': ' + detail.trim()", $html);
    }

    #[Test]
    public function tombol_simpan_terkunci_selama_pencarian_berjalan(): void
    {
        // Scanner barcode mengirim Enter langsung setelah SN — tanpa kunci ini
        // form bisa POST ke SN hasil pencarian SEBELUMNYA.
        foreach (['warehouse.adjustments.problem.create', 'warehouse.adjustments.check.create'] as $route) {
            $html = $this->actingAs($this->owner)->get(route($route))->getContent();
            $this->assertMatchesRegularExpression('/type="submit"[^>]*:disabled="[^"]*loading/s', $html, $route);
            $this->assertStringContainsString('@input="pending()"', $html, $route);
        }
    }

    #[Test]
    public function kejadian_hilang_pada_sn_mengubah_status_lost_lewat_endpoint_serial(): void
    {
        Storage::fake('public');
        $modem = $this->buatItem('PRB-MODEM', 'Modem Masalah', 'serialized', 'unit', null, 'modem_ont');
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['PRB-SN-1'], 100000, $this->owner);

        $this->actingAs($this->owner)->post(route('warehouse.adjustments.serial.store', $serial), [
            'pop_id' => $this->pusat->id,
            'q' => 'PRB-SN-1',
            'new_status' => 'lost',
            'reason' => 'hilang',
            'notes' => 'tidak ada di rak',
            'evidence' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(SerialStatus::LOST, $serial->fresh()->status);
    }

    #[Test]
    public function hilang_sn_tanpa_foto_ditolak(): void
    {
        $modem = $this->buatItem('PRB-MODEM', 'Modem Masalah', 'serialized', 'unit', null, 'modem_ont');
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['PRB-SN-1'], 100000, $this->owner);

        $this->actingAs($this->owner)->post(route('warehouse.adjustments.serial.store', $serial), [
            'new_status' => 'lost',
            'reason' => 'hilang',
        ])->assertSessionHasErrors('evidence');

        $this->assertSame(SerialStatus::AVAILABLE, $serial->fresh()->status);
    }

    #[Test]
    public function karantina_sn_tanpa_foto_diterima(): void
    {
        $modem = $this->buatItem('PRB-MODEM', 'Modem Masalah', 'serialized', 'unit', null, 'modem_ont');
        [$serial] = app(InventoryReceiveService::class)->receiveSerialized($this->pusat, $modem, ['PRB-SN-1'], 100000, $this->owner);

        $this->actingAs($this->owner)->post(route('warehouse.adjustments.serial.store', $serial), [
            'new_status' => 'quarantine',
            'reason' => 'karantina',
        ])->assertSessionHasNoErrors();

        $this->assertSame(SerialStatus::QUARANTINE, $serial->fresh()->status);
    }

    #[Test]
    public function kejadian_rusak_qty_mengurangi_saldo_lewat_endpoint_balance(): void
    {
        $konektor = $this->buatItem('PRB-KON', 'Konektor Masalah', 'quantity');
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $konektor, 20, 1000, $this->owner);

        $this->actingAs($this->owner)->post(route('warehouse.adjustments.balance.store'), [
            'pop_id' => $this->pusat->id,
            'item_id' => $konektor->id,
            'lot_no' => '',
            'qty_delta' => -5,
            'reason' => 'rusak',
        ])->assertRedirect(route('warehouse.stock.index'));

        $this->assertEquals(15, (float) InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $konektor->id)->value('qty'));
    }

    #[Test]
    public function salah_catat_qty_boleh_menambah_saldo(): void
    {
        $konektor = $this->buatItem('PRB-KON', 'Konektor Masalah', 'quantity');
        app(InventoryReceiveService::class)->receiveQuantity($this->pusat, $konektor, 20, 1000, $this->owner);

        $this->actingAs($this->owner)->post(route('warehouse.adjustments.balance.store'), [
            'pop_id' => $this->pusat->id,
            'item_id' => $konektor->id,
            'qty_delta' => 3,
            'reason' => 'salah_catat',
            'notes' => 'terima kemarin kurang input 3',
        ])->assertRedirect(route('warehouse.stock.index'));

        $this->assertEquals(23, (float) InventoryBalance::where('pop_id', $this->pusat->id)->where('item_id', $konektor->id)->value('qty'));
    }

    #[Test]
    public function jumlah_nol_ditolak_dan_error_tampil_di_form(): void
    {
        $konektor = $this->buatItem('PRB-KON', 'Konektor Masalah', 'quantity');
        $form = route('warehouse.adjustments.problem.create');

        $this->actingAs($this->owner)->from($form)->post(route('warehouse.adjustments.balance.store'), [
            'pop_id' => $this->pusat->id,
            'item_id' => $konektor->id,
            'qty_delta' => 0,
            'reason' => 'rusak',
        ])->assertRedirect($form)->assertSessionHas('error');

        $this->actingAs($this->owner)->withSession(['error' => 'Qty penyesuaian tidak boleh nol.'])
            ->get($form)
            ->assertSee('Qty penyesuaian tidak boleh nol.');
    }
}
