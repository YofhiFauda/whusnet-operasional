<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi 2026-11-11: barang yang sudah diserahkan ke teknisi "tidak bisa
 * dipasang" di laporan PSB. Data custody benar — penyebabnya browser:
 * `surveys/report` & `installations/report` sama-sama deklarasi
 * `let currentActiveStep` top-level. hx-boost swap <body> me-re-execute
 * script di dokumen yang sama ⇒ SyntaxError "already declared" ⇒ loop
 * insert-script htmx putus ⇒ comboBox & baris barang custody gak pernah
 * terdaftar, dropdown SN/roll mati.
 *
 * Plus dropdown <x-combobox> terpotong `overflow-hidden` panel wizard dan
 * tertutup footer fixed z-30 di mobile.
 */
class TopLevelLetConstBoostRedeclareTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function boostedReportViews(): array
    {
        return [
            'laporan PSB' => ['resources/views/installations/report.blade.php'],
            'laporan survey' => ['resources/views/surveys/report.blade.php'],
            'laporan maintenance' => ['resources/views/tasks/maintenance-report.blade.php'],
        ];
    }

    #[Test]
    #[DataProvider('boostedReportViews')]
    public function script_inline_tidak_punya_let_const_top_level(string $path): void
    {
        $source = file_get_contents(base_path($path));

        preg_match_all('/<script>(.*?)<\/script>/s', $source, $scripts);
        $this->assertNotEmpty($scripts[1], "{$path} harus punya script inline.");

        foreach ($scripts[1] as $script) {
            $this->assertSame(
                [],
                $this->topLevelLexicalDeclarations($script),
                "{$path}: deklarasi top-level wajib `var`/function supaya aman di-re-execute hx-boost."
            );
        }
    }

    #[Test]
    #[DataProvider('boostedReportViews')]
    public function inisialisasi_halaman_laporan_tidak_bergantung_dom_content_loaded(string $path): void
    {
        // DOMContentLoaded cuma nyala sekali per dokumen — halaman yang dibuka
        // lewat link boost (tombol "Lapor Sekarang" di Tasks Saya) gak pernah
        // menerimanya, jadi wizard/tombol Aktivasi gak terinisialisasi.
        $this->assertStringNotContainsString(
            'DOMContentLoaded',
            file_get_contents(base_path($path)),
            "{$path}: pakai window.onPageReady(), bukan DOMContentLoaded."
        );
    }

    #[Test]
    public function timer_laporan_psb_membersihkan_interval_kunjungan_sebelumnya(): void
    {
        // `var timerInterval = null` dieksekusi ulang tiap navigasi boost ⇒
        // handle interval lama hilang, timer jalan dobel. Handle wajib di
        // window supaya bisa di-clearInterval() dulu.
        $source = file_get_contents(base_path('resources/views/installations/report.blade.php'));

        $this->assertStringContainsString('clearInterval(window.__psbReportTimer)', $source);
    }

    /**
     * Deklarasi `let`/`const`/`class` yang berada di kedalaman kurung kurawal
     * 0 — level teratas script. Baris direktif Blade (`@if`, `@endif`, …)
     * bukan blok JS jadi gak dihitung; dulu penjaga ini cuma melihat indentasi
     * dan kelewatan `const timerStartedAt` yang ter-indent di dalam `@if`.
     *
     * @return list<string>
     */
    private function topLevelLexicalDeclarations(string $script): array
    {
        $found = [];
        $depth = 0;

        foreach (preg_split('/\R/', $script) as $line) {
            $code = trim(preg_replace('#//.*$#', '', $line));

            if ($code === '' || str_starts_with($code, '@')) {
                continue;
            }

            if ($depth === 0 && preg_match('/^(let|const|class)\s/', $code)) {
                $found[] = $code;
            }

            $depth += substr_count($code, '{') - substr_count($code, '}');
            $depth = max(0, $depth);
        }

        return $found;
    }

    #[Test]
    public function panel_wizard_psb_tidak_memotong_dropdown(): void
    {
        $source = file_get_contents(base_path('resources/views/installations/report.blade.php'));

        preg_match('/<div class="lg:col-span-8 [^"]*min-h-\[520px\]"/', $source, $panel);
        $this->assertNotEmpty($panel);
        $this->assertStringNotContainsString('overflow-hidden', $panel[0]);
    }

    #[Test]
    public function dropdown_combobox_di_atas_footer_fixed_z30(): void
    {
        $html = (string) $this->blade('<x-combobox id="sn" name="sn" />');

        $this->assertStringContainsString('absolute z-40 mt-1', $html);
    }
}
