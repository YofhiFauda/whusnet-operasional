<?php

namespace Tests\Feature;

use App\Services\NumberSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NumberSequenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_nomor_tiket_task_dan_tfop_berurutan_dengan_format_tanggal_dan_enam_digit(): void
    {
        $numbers = app(NumberSequenceService::class);
        $ymd = date('Ymd');

        $this->assertSame("TKT-{$ymd}-000001", $numbers->ticketNumber());
        $this->assertSame("TKT-{$ymd}-000002", $numbers->ticketNumber());
        $this->assertSame("TFOP-{$ymd}-000001", $numbers->fopTaskNumber());
        $this->assertSame("TASK-{$ymd}-000001", $numbers->taskNumber());
        $this->assertSame("TASK-{$ymd}-000002", $numbers->taskNumber());
    }

    public function test_nomor_invoice_independen_per_prefix_dan_tanggal(): void
    {
        $numbers = app(NumberSequenceService::class);

        $this->assertSame('TAG-20261005-000001', $numbers->invoiceNumber('TAG', '20261005'));
        $this->assertSame('TAG-20261005-000002', $numbers->invoiceNumber('TAG', '20261005'));
        $this->assertSame('OTH-20261005-000001', $numbers->invoiceNumber('OTH', '20261005'));
        $this->assertSame('TAG-20261006-000001', $numbers->invoiceNumber('TAG', '20261006'));
    }

    public function test_counter_tersimpan_di_tabel_number_sequences(): void
    {
        $numbers = app(NumberSequenceService::class);

        $numbers->invoiceNumber('ACT', '20261005');
        $numbers->invoiceNumber('ACT', '20261005');

        $this->assertSame(
            2,
            (int) DB::table('number_sequences')->where('key', 'INV:ACT-20261005')->value('value'),
        );
    }
}
