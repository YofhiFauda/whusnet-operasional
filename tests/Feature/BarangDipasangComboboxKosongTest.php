<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi ADHOC-297 bug #3 (laporan user 2026-11-13): di Step 5 laporan PSB
 * (dan laporan Maintenance/C-REQ), combobox "Barang Dipasang (Modem / Roll
 * Kabel / Pasif)" kosong — barang custody teknisi gak bisa dipilih padahal
 * datanya ada di HTML.
 *
 * Akar masalah (direproduksi di Chrome headless): `modem-blocked-expr`
 * (mis. `modemAcquisition === 'beli_sendiri'`) di-echo `{{ }}` di dalam atribut
 * `options-expr` milik <x-combobox>, lalu combobox meng-echo `{{ $optionsExpr }}`
 * LAGI ⇒ kutip ter-escape dua kali (`&amp;#039;`). Browser cuma membuka satu
 * lapis, Alpine menerima `&#039;beli_sendiri&#039;` ⇒ SyntaxError
 * "Unexpected token '&'" ⇒ comboBox() gak pernah terbentuk ⇒ daftar kosong.
 */
class BarangDipasangComboboxKosongTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function modemBlockedExpressions(): array
    {
        return [
            'laporan PSB' => ["modemAcquisition === 'beli_sendiri'"],
            'laporan maintenance' => ["modemOwnership === 'tidak_ganti' || modemOwnership === 'beli_sendiri'"],
        ];
    }

    #[Test]
    #[DataProvider('modemBlockedExpressions')]
    public function ekspresi_modem_blocked_cuma_di_escape_sekali(string $expression): void
    {
        $html = (string) $this->blade(
            '<x-installation-item-rows :modem-blocked-expr="$expr" />',
            ['expr' => $expression]
        );

        $this->assertStringNotContainsString(
            '&amp;#039;',
            $html,
            'Kutip di modem-blocked-expr ter-escape dua kali — Alpine akan SyntaxError dan combobox barang kosong.'
        );

        // Satu lapis escape = setelah browser decode atribut, JS-nya utuh.
        $this->assertStringContainsString(
            'comboOptionsFor(index, ('.e($expression).'))',
            $html
        );
    }
}
