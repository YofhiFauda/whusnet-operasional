<?php

namespace App\Http\Controllers;

use App\Enums\TaskType;
use App\Enums\TicketBucket;
use App\Models\Pop;
use App\Models\Ticket;
use Illuminate\Http\Request;

/**
 * Induk bersama halaman arsip Ticketing (Ticket Selesai & Ticket Dibatalkan).
 *
 * Tiap halaman punya controller + route + permission + view SENDIRI
 * (TicketSelesaiController / TicketDibatalkanController) — dulu semuanya
 * numpang satu route bucket generik `/tickets/{bucket}` sehingga gak bisa
 * di-toggle per-halaman di Role Matrix. Kelas ini cuma nampung query +
 * render yang identik, BUKAN berarti dua halaman itu "satu fitur".
 *
 * Pola sama persis CustomerTerminatedController/CustomerFailedController.
 */
abstract class TicketArchiveController extends Controller
{
    /**
     * Bucket yang jadi isi halaman ini. Diisi controller anak.
     */
    abstract protected function bucket(): TicketBucket;

    /**
     * Permission yang menggerbangi halaman ini. Diisi controller anak —
     * sengaja gak diturunkan otomatis dari bucket() biar kelihatan eksplisit
     * pas baca kodenya, dan gampang dicek silang sama routes/web.php.
     */
    abstract protected function permission(): string;

    /**
     * View halaman ini. Tiap halaman file sendiri (tickets/selesai.blade.php,
     * tickets/dibatalkan.blade.php) — dua-duanya nge-include partial isi yang
     * sama, tapi identitas halamannya kepisah.
     */
    abstract protected function view(): string;

    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasPermission($this->permission()), 403);

        $activeBucket = $this->bucket();

        $query = Ticket::query()
            ->applyUserScope()
            ->inBucket($activeBucket)
            ->with([
                // pop_id/status/distribution_id WAJIB ikut ke-select — dipakai
                // Customer::getDisplayIdAttribute() buat nebak format CID lengkap
                // (Pop::resolveDisplayId()). Tanpa kolom-kolom ini, $customer->pop
                // selalu null (FK-nya gak ke-load) dan display_id diam-diam jatuh
                // ke customer_code mentah (mis. "RQ000007" tanpa prefix POP/
                // distribusi), padahal customers.cid udah nyimpen CID lengkap
                // yang benar (mis. "C1X4CRQ000007").
                'customer:id,full_name,cid,customer_code,pop_id,status,distribution_id,primary_phone',
                'customer.pop:id,name,cid_prefix',
                'creator:id,name',
                'pop:id,name',
                'fopTask:id,task_number,status',
                'issueCategory:id,name',
                // Atribusi (siapa buat/selesaikan/kirim ke NOC/kirim ke FOP) —
                // lihat Ticket::closedBy()/escalatedToNocBy()/escalatedToFopBy().
                'histories.actor:id,name',
            ])
            ->withCount('attachments');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('detail_keluhan', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($c) use ($search) {
                        $c->where('full_name', 'like', "%{$search}%")
                            ->orWhere('cid', 'like', "%{$search}%")
                            ->orWhere('customer_code', 'like', "%{$search}%");
                    });
            });
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        // "Ticket Saya" — riwayat tiket yang dikirim user login sendiri.
        if ($request->boolean('mine')) {
            $query->where('created_by', auth()->id());
        }

        $tickets = $query->latest('created_at')->paginate(20)->withQueryString();

        return view($this->view(), [
            'tickets' => $tickets,
            'activeBucket' => $activeBucket,
            'archiveTabs' => $this->archiveTabs($activeBucket),
            'currentUrl' => url()->current(),
            'typeOptions' => TaskType::ticketOptions(),
            // Buat subscribe Echo.private('tickets.{popId}') per POP yang
            // kelihatan user — auto-refresh (Gap #3, App\Events\TicketQueueUpdated).
            'allowedPopIds' => Pop::forUser(auth()->user())->pluck('id'),
        ]);
    }

    /**
     * Navigasi antar halaman arsip. Cuma nampilin yang user emang punya
     * permission-nya — dua halaman ini permission-nya kepisah, jadi bisa aja
     * user cuma boleh lihat salah satu.
     *
     * @return array<int, array{label: string, url: string, count: int, active: bool}>
     */
    private function archiveTabs(TicketBucket $activeBucket): array
    {
        $tabs = [];

        foreach ([
            ['bucket' => TicketBucket::SELESAI, 'permission' => 'tickets.selesai.view', 'route' => 'tickets.selesai'],
            ['bucket' => TicketBucket::DIBATALKAN, 'permission' => 'tickets.dibatalkan.view', 'route' => 'tickets.dibatalkan'],
        ] as $tab) {
            if (! auth()->user()->hasPermission($tab['permission'])) {
                continue;
            }

            $tabs[] = [
                'label' => $tab['bucket']->label(),
                'url' => route($tab['route']),
                'count' => Ticket::query()->applyUserScope()->inBucket($tab['bucket'])->count(),
                'active' => $tab['bucket'] === $activeBucket,
            ];
        }

        return $tabs;
    }
}
