<?php

namespace Tests\Feature;

use App\Enums\WorkflowTransition;
use App\Models\Customer;
use App\Models\Pop;
use App\Services\CustomerWorkflowService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresi 2026-09-26: pesan konfirmasi `window.confirmAction('... {{ $nama }} ...')`
 * di atribut onsubmit/onclick. `{{ }}` mengubah `'` jadi `&#039;`, tapi browser
 * men-decode entity atribut SEBELUM JavaScript jalan — nama "Ma'ruf" memutus
 * string JS (tombol mati diam-diam, preventDefault sudah terlanjur jalan) dan
 * nama berisi `x');alert(1);//` dieksekusi di browser admin (stored XSS).
 *
 * Fix: pesan dirakit di PHP lalu dikirim lewat `@js()` — tanda kutip jadi unicode escape JS.
 * Test ini meniru browser: ambil nilai atribut, decode entity HTML, lalu pastikan
 * payload nama tidak pernah muncul mentah di kode JS-nya.
 */
class KonfirmasiNamaBerapostrofTidakMerusakJsTest extends TestCase
{
    use RefreshDatabase;

    private const NAMA_APOSTROF = "Ma'ruf";

    private const NAMA_XSS = "x');alert(1);//";

    private Pop $pop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->loginAsAdmin();

        $this->pop = Pop::firstOrCreate(['pop_code' => 'C'], [
            'code' => 'C', 'name' => "Jetis O'Brien", 'type' => 'cabang', 'status' => 'active',
            'registration_prefix' => 'RQ', 'cid_prefix' => 'C',
        ]);
    }

    private function makeCustomer(string $name, string $status): Customer
    {
        return Customer::create([
            'customer_code' => 'APO-'.fake()->unique()->numerify('#####'),
            'full_name' => $name,
            'primary_phone' => '081234500000',
            'status' => $status,
            'pop_id' => $this->pop->id,
            'data_completeness_status' => 'draft',
            'registration_date' => now(),
        ]);
    }

    /**
     * Nilai atribut onsubmit/onclick yang memanggil confirmAction/confirmDelete,
     * sudah di-decode seperti yang dilihat mesin JS browser.
     *
     * @return string[]
     */
    private function confirmHandlers(string $html): array
    {
        preg_match_all('/\bon(?:submit|click)="([^"]*confirm(?:Action|Delete)[^"]*)"/', $html, $matches);

        return array_map(fn (string $raw) => html_entity_decode($raw, ENT_QUOTES | ENT_HTML5), $matches[1]);
    }

    private function assertNamesNeverRawInJs(string $html, array $names): void
    {
        $handlers = $this->confirmHandlers($html);
        $this->assertNotEmpty($handlers, 'Tombol konfirmasi tidak ditemukan di halaman');

        foreach ($handlers as $js) {
            foreach ($names as $name) {
                $this->assertStringNotContainsString($name, $js, "Nama mentah masuk ke kode JS: {$js}");
            }
        }
    }

    #[Test]
    public function halaman_pelanggan_putus_aman_untuk_nama_berapostrof(): void
    {
        $this->makeCustomer(self::NAMA_APOSTROF, 'terminated');
        $this->makeCustomer(self::NAMA_XSS, 'terminated');

        $html = $this->get(route('customers.terminated'))->assertOk()->getContent();

        $this->assertNamesNeverRawInJs($html, [self::NAMA_APOSTROF, self::NAMA_XSS]);
        // Nama tetap sampai ke dialog, cuma kutipnya di-escape jadi unicode escape JS.
        $escapedApostrophe = trim(json_encode(self::NAMA_APOSTROF, JSON_HEX_APOS), '"');
        $this->assertStringContainsString($escapedApostrophe, implode(PHP_EOL, $this->confirmHandlers($html)));
    }

    #[Test]
    public function halaman_pelanggan_gagal_aman_untuk_nama_berapostrof(): void
    {
        // Tombol "Kembalikan" cuma muncul kalau status sebelum ditolak tercatat
        // di audit log transisi — jadi lewat transisi workflow asli.
        foreach ([self::NAMA_APOSTROF, self::NAMA_XSS] as $name) {
            app(CustomerWorkflowService::class)->transition($this->makeCustomer($name, 'surveyed'), WorkflowTransition::REJECTED, 'Ditolak: uji');
        }

        $html = $this->get(route('customers.failed'))->assertOk()->getContent();

        $this->assertNamesNeverRawInJs($html, [self::NAMA_APOSTROF, self::NAMA_XSS]);
    }

    #[Test]
    public function halaman_master_pop_aman_untuk_nama_berapostrof(): void
    {
        $html = $this->get(route('master.pop.index'))->assertOk()->getContent();

        $this->assertNamesNeverRawInJs($html, ["Jetis O'Brien"]);
    }

    #[Test]
    public function tidak_ada_view_yang_menyisipkan_echo_blade_ke_string_konfirmasi(): void
    {
        // Penjaga statis — pola `confirmAction('... {{ ... }}')` dilarang di view
        // mana pun; pakai `confirmAction(@js(...), ...)`.
        $offenders = [];
        $views = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($views as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            foreach (file($file->getPathname()) as $no => $line) {
                if (preg_match("/confirm(?:Action|Delete)\('[^']*\{\{/", $line)) {
                    $offenders[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname()).':'.($no + 1);
                }
            }
        }

        $this->assertSame([], $offenders, 'Pesan konfirmasi masih menyisipkan {{ }} ke string JS: '.implode(', ', $offenders));
    }
}
