<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Helpers\FormatHelper;
use Carbon\Carbon;

class FormatHelperTest extends TestCase
{
    public function test_it_formats_rupiah_correctly()
    {
        $this->assertEquals('Rp 1.500.000', FormatHelper::rupiah(1500000));
        $this->assertEquals('Rp 1.500.000,00', FormatHelper::rupiah(1500000, true));
        $this->assertEquals('Rp 0', FormatHelper::rupiah(null));
        $this->assertEquals('Rp 150', FormatHelper::rupiah(150));
        $this->assertEquals('Rp 150.750', FormatHelper::rupiah(150750));
        
        // Test global helper
        $this->assertEquals('Rp 1.500.000', format_rupiah(1500000));
    }

    public function test_it_formats_tanggal_correctly()
    {
        // Set carbon locale just in case
        Carbon::setLocale('id');
        
        $date = '2026-06-16';
        $this->assertEquals('16 Juni 2026', FormatHelper::tanggal($date));
        $this->assertEquals('Selasa, 16 Juni 2026', FormatHelper::tanggal($date, true));
        $this->assertEquals('-', FormatHelper::tanggal(null));
        
        // Test global helper
        $this->assertEquals('16 Juni 2026', format_tanggal($date));
    }

    public function test_it_formats_jam_correctly()
    {
        $time = '2026-06-16 09:55:23';
        $this->assertEquals('09:55 WIB', FormatHelper::jam($time));
        $this->assertEquals('09:55', FormatHelper::jam($time, false));
        $this->assertEquals('-', FormatHelper::jam(null));
        
        // Test global helper
        $this->assertEquals('09:55 WIB', format_jam($time));
    }

    public function test_it_formats_datetime_correctly()
    {
        // Set carbon locale just in case
        Carbon::setLocale('id');

        $datetime = '2026-06-16 09:55:23';
        $this->assertEquals('16 Juni 2026 09:55 WIB', FormatHelper::datetime($datetime));
        $this->assertEquals('Selasa, 16 Juni 2026 09:55 WIB', FormatHelper::datetime($datetime, true));
        $this->assertEquals('-', FormatHelper::datetime(null));
        
        // Test global helper
        $this->assertEquals('16 Juni 2026 09:55 WIB', format_datetime($datetime));
    }

    public function test_it_compiles_blade_directives()
    {
        $this->assertEquals(
            "<?php echo \App\Helpers\FormatHelper::rupiah(1250000); ?>",
            \Illuminate\Support\Facades\Blade::compileString('@rupiah(1250000)')
        );
        
        $this->assertEquals(
            "<?php echo \App\Helpers\FormatHelper::tanggal('2026-06-16'); ?>",
            \Illuminate\Support\Facades\Blade::compileString("@tanggal('2026-06-16')")
        );

        $this->assertEquals(
            "<?php echo \App\Helpers\FormatHelper::jam('09:55:23'); ?>",
            \Illuminate\Support\Facades\Blade::compileString("@jam('09:55:23')")
        );

        $this->assertEquals(
            "<?php echo \App\Helpers\FormatHelper::datetime('2026-06-16 09:55:23'); ?>",
            \Illuminate\Support\Facades\Blade::compileString("@datetime('2026-06-16 09:55:23')")
        );
    }
}
