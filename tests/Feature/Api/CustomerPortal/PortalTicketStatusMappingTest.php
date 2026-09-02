<?php

namespace Tests\Feature\Api\CustomerPortal;

use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Pop;
use App\Models\Ticket;
use App\Models\User;
use App\Support\CustomerPortal\TicketPortalStatusPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `TicketPortalStatusPresenter::resolve()` — SEMUA baris tabel mapping
 * status (docs/api/api-portal-pelanggan/flowchart.md §3). Test PALING
 * KRITIS di Fase 4: urutan cek `handler` vs `status` yang salah akan lolos
 * di kasus sederhana tapi salah persis untuk tiket pasca-FOP yang sudah
 * lama selesai — kasus itu WAJIB ada di sini.
 */
class PortalTicketStatusMappingTest extends TestCase
{
    use RefreshDatabase;

    private function seedTicket(array $overrides = []): Ticket
    {
        $pop = Pop::create([
            'code' => 'TSM', 'pop_code' => 'TSM',
            'registration_prefix' => 'TSM', 'cid_prefix' => 'D',
            'name' => 'POP TSM', 'type' => 'cabang', 'status' => 'active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'RQ'.random_int(100000, 999999),
            'full_name' => 'Pelanggan Status Mapping',
            'primary_phone' => '081200000099',
            'registration_date' => '2026-06-01',
            'status' => 'active',
            'pop_id' => $pop->id,
            'address' => 'Jl. Status Mapping',
        ]);
        $staff = User::factory()->create();

        return Ticket::create(array_merge([
            'ticket_number' => 'TKT-TSM-'.random_int(100000, 999999),
            'type' => 'MTN',
            'customer_id' => $customer->id,
            'pop_id' => $pop->id,
            'detail_keluhan' => 'Internet lambat.',
            'priority' => 'Medium',
            'created_by' => $staff->id,
        ], $overrides));
    }

    private function seedFopTask(string $status): FopTask
    {
        return FopTask::create([
            'task_number' => 'TFOP-TSM-'.random_int(100000, 999999),
            'category' => 'MTN',
            'tugas' => 'Uji status mapping',
            'issue' => 'Lemot',
            'status' => $status,
        ]);
    }

    public function test_handler_helpdesk_status_open_diterima(): void
    {
        $ticket = $this->seedTicket(['handler' => 'helpdesk', 'status' => 'open']);

        $this->assertSame(['value' => 'diterima', 'label' => 'Diterima'], TicketPortalStatusPresenter::resolve($ticket));
    }

    public function test_handler_noc_status_open_sedang_ditangani(): void
    {
        $ticket = $this->seedTicket(['handler' => 'noc', 'status' => 'open']);

        $this->assertSame(['value' => 'sedang_ditangani', 'label' => 'Sedang Ditangani'], TicketPortalStatusPresenter::resolve($ticket));
    }

    public function test_status_closed_handler_helpdesk_selesai(): void
    {
        $ticket = $this->seedTicket(['handler' => 'helpdesk', 'status' => 'closed']);

        $this->assertSame(['value' => 'selesai', 'label' => 'Selesai'], TicketPortalStatusPresenter::resolve($ticket));
    }

    public function test_status_cancelled_handler_noc_dibatalkan(): void
    {
        $ticket = $this->seedTicket(['handler' => 'noc', 'status' => 'cancelled']);

        $this->assertSame(['value' => 'dibatalkan', 'label' => 'Dibatalkan'], TicketPortalStatusPresenter::resolve($ticket));
    }

    /**
     * REGRESI EKSPLISIT: begitu handler=FOP, kolom tickets.status BEKU
     * (tetap 'open' sejak sebelum eskalasi) — kalau presenter salah urutan
     * cek (baca status dulu), tiket ini akan SALAH kebaca "Diterima"
     * selamanya walau FopTask-nya sudah lama selesai di lapangan.
     */
    public function test_handler_fop_status_kolom_masih_open_tapi_foptask_selesai_tetap_selesai(): void
    {
        $fopTask = $this->seedFopTask('selesai');
        $ticket = $this->seedTicket(['handler' => 'fop', 'status' => 'open', 'fop_task_id' => $fopTask->id]);

        $this->assertSame(['value' => 'selesai', 'label' => 'Selesai'], TicketPortalStatusPresenter::resolve($ticket->fresh()->load('fopTask')));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function fopTaskStatusProvider(): array
    {
        return [
            'selesai' => ['selesai', 'selesai'],
            'dibatalkan' => ['dibatalkan', 'dibatalkan'],
            'draft' => ['draft', 'sedang_ditangani'],
            'terjadwal' => ['terjadwal', 'sedang_ditangani'],
            'in_progress' => ['in_progress', 'sedang_ditangani'],
            'pending' => ['pending', 'sedang_ditangani'],
        ];
    }

    #[DataProvider('fopTaskStatusProvider')]
    public function test_handler_fop_seluruh_taskstatus_termapping_benar(string $fopTaskStatus, string $expectedValue): void
    {
        $fopTask = $this->seedFopTask($fopTaskStatus);
        $ticket = $this->seedTicket(['handler' => 'fop', 'fop_task_id' => $fopTask->id]);

        $result = TicketPortalStatusPresenter::resolve($ticket->fresh()->load('fopTask'));

        $this->assertSame($expectedValue, $result['value']);
    }

    /**
     * Orphan (Ticket::isOrphan()): handler=FOP tapi FopTask-nya sudah
     * dihapus — tampil "Sedang Ditangani", BUKAN "Terputus" (istilah
     * internal, gak berarti apa-apa buat pelanggan).
     */
    public function test_handler_fop_orphan_foptask_hilang_sedang_ditangani_bukan_terputus(): void
    {
        $ticket = $this->seedTicket(['handler' => 'fop', 'fop_task_id' => null]);

        $this->assertTrue($ticket->isOrphan());
        $result = TicketPortalStatusPresenter::resolve($ticket->fresh()->load('fopTask'));
        $this->assertSame('sedang_ditangani', $result['value']);
        $this->assertNotSame('Terputus', $result['label']);
    }
}
