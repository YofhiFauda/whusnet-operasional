<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Permintaan user 2026-11-13 (ADHOC-297 bug #3 lanjutan): satu combobox
 * gabungan "Barang (custody tim)" menyulitkan teknisi yang memegang banyak
 * barang. Baris "Barang Dipasang" jadi tiga langkah — Kategori → Nama Barang
 * → Jumlah — dan opsinya HANYA barang yang dipegang tim task.
 *
 * Nama field yang dikirim ke server TIDAK berubah (selected_inventory_serial_id,
 * selected_inventory_roll_id + roll_meters_used, materials[i][...]) — backend
 * storePemasangan()/TaskMaintenanceController::store() gak tahu UI-nya berubah.
 */
class BarangDipasangPilihKategoriNamaJumlahTest extends TestCase
{
    private function render(): string
    {
        $modemCategory = (object) ['code' => 'modem_ont', 'name' => 'Modem/ONT Pelanggan'];
        $rollCategory = (object) ['code' => 'kabel_dropcore', 'name' => 'Kabel Dropcore'];

        $serials = collect([
            (object) ['id' => 21, 'serial_number' => 'ZTEHGG021', 'item' => (object) ['name' => 'Modem ONT ZTE F609', 'category' => $modemCategory]],
        ]);
        $rolls = collect([
            (object) ['id' => 48, 'roll_code' => 'DC-1C-000048', 'length_remaining' => 1000, 'item' => (object) ['name' => 'Kabel Dropcore 1 Core', 'category' => $rollCategory]],
        ]);
        $custody = collect([
            ['item_id' => 7, 'code' => 'PC-1M', 'name' => 'Patch Cord SC/APC 1 Meter', 'unit' => 'pcs', 'type' => 'patch_cord', 'category_name' => 'Patch Cord', 'available' => 150],
        ]);

        return (string) $this->blade(
            '<x-installation-item-rows :serial-options="$serials" :roll-options="$rolls" :custody-options="$custody" />',
            compact('serials', 'rolls', 'custody')
        );
    }

    #[Test]
    public function baris_punya_kolom_kategori_nama_barang_dan_jumlah(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('>Kategori<', $html);
        $this->assertStringContainsString('>Nama Barang<', $html);
        $this->assertStringContainsString('>Jumlah<', $html);
        $this->assertStringNotContainsString('Barang (custody tim)', $html);
    }

    #[Test]
    public function opsi_membawa_kategori_dari_barang_yang_dipegang_tim(): void
    {
        $html = html_entity_decode($this->render(), ENT_QUOTES);

        $this->assertStringContainsString('category_name', $html);

        foreach (['ONT Pelanggan', 'Kabel Dropcore', 'Patch Cord'] as $categoryName) {
            $this->assertStringContainsString($categoryName, $html);
        }

        $this->assertStringContainsString('ZTEHGG021', $html);
        $this->assertStringContainsString('DC-1C-000048', $html);
        $this->assertStringContainsString('APC 1 Meter', $html);
    }

    #[Test]
    public function nama_field_ke_server_tidak_berubah(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('<input type="hidden" id="selected_inventory_serial_id" name="selected_inventory_serial_id"', $html);
        $this->assertStringContainsString('name="selected_inventory_roll_id"', $html);
        $this->assertStringContainsString('name="roll_meters_used"', $html);
        $this->assertStringContainsString('`materials[${index}][item_id]`', $html);
        $this->assertStringContainsString('`materials[${index}][qty]`', $html);
        $this->assertStringContainsString('`materials[${index}][note]`', $html);
    }

    #[Test]
    public function initial_rows_menyertakan_category_saat_serial_id_diisi(): void
    {
        // Saat halaman di-refresh dengan initialSerialId ada (PSB Fase 1 Tahap 5
        // setelah storePemasangan), $initialRows harus menyertakan 'category'
        // supaya combobox Kategori tidak kosong setelah refresh.
        $modemCategory = (object) ['code' => 'modem_ont', 'name' => 'Modem/ONT Pelanggan'];
        $serials = collect([
            (object) ['id' => 21, 'serial_number' => 'ZTEHGG021', 'item' => (object) ['name' => 'Modem ONT ZTE F609', 'category' => $modemCategory]],
        ]);

        $html = (string) $this->blade(
            '<x-installation-item-rows :serial-options="$serials" :initial-serial-id="21" />',
            ['serials' => $serials]
        );

        // JSON initialRows yang dikirim ke Alpine harus mengandung "category":"modem_ont".
        // HTML meng-encode kutip dengan \u0022 karena @js() di dalam atribut HTML.
        // Cukup periksa key 'category' dan nilai 'modem_ont' hadir di HTML.
        $this->assertStringContainsString('modem_ont', $html);
        // 'category' harus hadir sebagai key JSON (di-encode \u0022category\u0022)
        $this->assertMatchesRegularExpression('/\\\\u0022category\\\\u0022:\\\\u0022modem_ont\\\\u0022/', $html);
    }

    #[Test]
    public function initial_rows_menyertakan_category_saat_roll_id_diisi(): void
    {
        $rollCategory = (object) ['code' => 'kabel_dropcore', 'name' => 'Kabel Dropcore'];
        $rolls = collect([
            (object) ['id' => 48, 'roll_code' => 'DC-1C-000048', 'length_remaining' => 1000, 'item' => (object) ['name' => 'Kabel Dropcore 1 Core', 'category' => $rollCategory]],
        ]);

        $html = (string) $this->blade(
            '<x-installation-item-rows :roll-options="$rolls" :initial-roll-id="48" :initial-roll-meters="25" />',
            ['rolls' => $rolls]
        );

        $this->assertStringContainsString('kabel_dropcore', $html);
        $this->assertMatchesRegularExpression('/\\\\u0022category\\\\u0022:\\\\u0022kabel_dropcore\\\\u0022/', $html);
    }

    #[Test]
    public function initial_rows_menyertakan_category_saat_material_rows_diisi(): void
    {
        $custody = collect([
            ['item_id' => 7, 'code' => 'PC-1M', 'name' => 'Patch Cord SC/APC 1 Meter', 'unit' => 'pcs', 'type' => 'patch_cord', 'category_name' => 'Patch Cord', 'available' => 150],
        ]);
        $materialRows = [['item_id' => 7, 'qty' => 2, 'note' => '', 'item_type' => 'patch_cord']];

        $html = (string) $this->blade(
            '<x-installation-item-rows :custody-options="$custody" :initial-material-rows="$materialRows" />',
            ['custody' => $custody, 'materialRows' => $materialRows]
        );

        $this->assertStringContainsString('patch_cord', $html);
        $this->assertMatchesRegularExpression('/\\\\u0022category\\\\u0022:\\\\u0022patch_cord\\\\u0022/', $html);
    }
}
