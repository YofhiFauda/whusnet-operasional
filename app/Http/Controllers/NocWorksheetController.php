<?php

namespace App\Http\Controllers;

use App\Enums\TicketHandler;
use App\Models\Pop;
use App\Models\Ticket;
use Illuminate\Http\Request;

/**
 * Worksheet NOC — halaman kerja NOC sendiri, TERPISAH dari panel "List Task
 * Ticketing" di /tickets/new (worksheet bersama Helpdesk & NOC). Dua tab-nya
 * masing-masing HALAMAN SENDIRI (route + permission sendiri, bukan param
 * bucket bebas) biar aksesnya bisa diatur granular lewat Role Matrix:
 *
 *   Ticket Masuk    (/noc/worksheet/masuk)    = noc_worksheet.masuk.view
 *   Ticket Diproses (/noc/worksheet/diproses) = noc_worksheet.diproses.view
 *
 * Cuma nampilin daftar; aksi (Oncheck NOC/Selesai/Ke FOP/Kembalikan/Batalkan)
 * tetap lewat endpoint TicketController yang sudah ada — controller ini gak
 * duplikasi logic mutasi tiket.
 */
class NocWorksheetController extends Controller
{
    /**
     * Pintu masuk Worksheet NOC — SATU halaman, dua tab di dalamnya. Route ini
     * cuma nentuin tab mana yang kebuka duluan, nyesuain permission user
     * (bisa aja dia cuma boleh salah satu tab). Sidebar nunjuk ke sini, bukan
     * ke tab-nya langsung.
     */
    public function index()
    {
        if (auth()->user()->hasPermission('noc_worksheet.masuk.view')) {
            return redirect()->route('noc.worksheet.masuk');
        }

        if (auth()->user()->hasPermission('noc_worksheet.diproses.view')) {
            return redirect()->route('noc.worksheet.diproses');
        }

        abort(403);
    }

    /**
     * Tiket baru dikirim Helpdesk, NOC belum Oncheck (Ticket::isPendingNoc()).
     */
    public function masuk(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('noc_worksheet.masuk.view'), 403);

        return $this->worksheetPage($request, 'masuk');
    }

    /**
     * Tiket yang udah di-Oncheck NOC (Ticket::isOnCheckNoc()).
     */
    public function diproses(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('noc_worksheet.diproses.view'), 403);

        return $this->worksheetPage($request, 'diproses');
    }

    private function worksheetPage(Request $request, string $activeTab)
    {
        $query = Ticket::query()->applyUserScope()
            ->where('handler', TicketHandler::NOC->value)
            ->where('status', 'open')
            ->with([
                'customer:id,full_name,cid,customer_code,pop_id,status,distribution_id,primary_phone',
                'customer.pop:id,name,cid_prefix',
                'creator:id,name',
                'pop:id,name',
                'issueCategory:id,name',
                'histories.actor:id,name',
            ]);

        if ($activeTab === 'masuk') {
            $query->whereNull('noc_checked_at');
        } else {
            $query->whereNotNull('noc_checked_at');
        }

        $tickets = $query->latest('created_at')->paginate(20)->withQueryString();

        return view('noc.worksheet', [
            'tickets' => $tickets,
            'activeTab' => $activeTab,
            'tabs' => $this->worksheetTabs($activeTab),
            'allowedPopIds' => Pop::forUser(auth()->user())->pluck('id'),
        ]);
    }

    /**
     * Tab navigasi antar dua halaman worksheet. Cuma nampilin yang user emang
     * punya permission-nya — dua halaman ini permission-nya kepisah.
     *
     * @return array<int, array{key: string, label: string, url: string, count: int, active: bool}>
     */
    private function worksheetTabs(string $activeTab): array
    {
        $base = fn () => Ticket::query()->applyUserScope()
            ->where('handler', TicketHandler::NOC->value)
            ->where('status', 'open');

        $tabs = [];

        if (auth()->user()->hasPermission('noc_worksheet.masuk.view')) {
            $tabs[] = [
                'key' => 'masuk',
                'label' => 'Ticket Masuk',
                'url' => route('noc.worksheet.masuk'),
                'count' => $base()->whereNull('noc_checked_at')->count(),
                'active' => $activeTab === 'masuk',
            ];
        }

        if (auth()->user()->hasPermission('noc_worksheet.diproses.view')) {
            $tabs[] = [
                'key' => 'diproses',
                'label' => 'Ticket Diproses',
                'url' => route('noc.worksheet.diproses'),
                'count' => $base()->whereNotNull('noc_checked_at')->count(),
                'active' => $activeTab === 'diproses',
            ];
        }

        return $tabs;
    }
}
