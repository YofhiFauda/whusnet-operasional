<?php

namespace App\Http\Controllers;

use App\Enums\TicketHandler;
use App\Enums\TicketHandlingStatus;
use App\Models\Pop;
use App\Models\Ticket;
use App\Models\TicketHistory;

/**
 * Dashboard NOC — tracking tiket buat NOC (bukan buat teknisi lapangan,
 * beda dari FopDashboardController). Pola sama (stat cards, list aktif,
 * Echo auto-refresh channel POP), tapi data & scope-nya khusus tiket
 * `handler=NOC`.
 */
class NocDashboardController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->hasPermission('noc_dashboard.view'), 403);

        $stats = [
            'pending_noc' => Ticket::query()->applyUserScope()
                ->where('handler', TicketHandler::NOC->value)->where('status', 'open')
                ->whereNull('noc_checked_at')->count(),
            'on_check_noc' => Ticket::query()->applyUserScope()
                ->where('handler', TicketHandler::NOC->value)->where('status', 'open')
                ->whereNotNull('noc_checked_at')->count(),
            'selesai_hari_ini' => Ticket::query()->applyUserScope()
                ->where('status', TicketHandlingStatus::CLOSED->value)
                ->whereDate('updated_at', today())->count(),
            'dibatalkan_hari_ini' => Ticket::query()->applyUserScope()
                ->where('status', TicketHandlingStatus::CANCELLED->value)
                ->whereDate('updated_at', today())->count(),
        ];

        // List tiket aktif + aging — paling lama nunggu di atas, biar keliatan
        // mana yang keteteran.
        $activeTickets = Ticket::query()->applyUserScope()
            ->where('handler', TicketHandler::NOC->value)
            ->where('status', 'open')
            ->with(['customer:id,full_name,cid,customer_code', 'creator:id,name', 'issueCategory:id,name'])
            ->orderBy('created_at')
            ->limit(30)
            ->get();

        $activityFeed = TicketHistory::query()
            ->whereHas('ticket', fn ($q) => $q->applyUserScope())
            ->with(['actor:id,name', 'ticket:id,ticket_number'])
            ->latest('happened_at')
            ->limit(20)
            ->get();

        $issueStats = Ticket::query()->applyUserScope()
            ->whereNotNull('issue_category_id')
            ->with('issueCategory:id,name')
            ->get()
            ->groupBy(fn (Ticket $t) => $t->issueCategory?->name ?? 'Lainnya')
            ->map->count()
            ->sortDesc()
            ->take(10);

        $regionStats = Ticket::query()->applyUserScope()
            ->with('customer.district:id,name')
            ->get()
            ->groupBy(fn (Ticket $t) => $t->customer?->district?->name ?? 'Tidak diketahui')
            ->map->count()
            ->sortDesc()
            ->take(10);

        return view('noc.dashboard', [
            'stats' => $stats,
            'activeTickets' => $activeTickets,
            'activityFeed' => $activityFeed,
            'issueStats' => $issueStats,
            'regionStats' => $regionStats,
            'allowedPopIds' => Pop::forUser(auth()->user())->pluck('id'),
        ]);
    }
}
