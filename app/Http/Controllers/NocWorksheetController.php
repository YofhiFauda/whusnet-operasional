<?php

namespace App\Http\Controllers;

use App\Enums\FopTaskPriority;
use App\Enums\TaskType;
use App\Enums\TicketHandler;
use App\Enums\TicketHandlingStatus;
use App\Enums\TicketHistoryAction;
use App\Models\Pop;
use App\Models\Ticket;
use App\Models\TicketIssueCategory;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Worksheet NOC — halaman kerja NOC sendiri, TERPISAH dari panel "List Task
 * Ticketing" di /tickets/new (worksheet bersama Helpdesk & NOC).
 *
 * Tampilannya tabel padat + pencarian + filter (ADHOC-09), bukan daftar kartu
 * lagi: antrean NOC bisa puluhan tiket dan versi kartu gak punya cara nemu
 * tiket tertentu selain scroll.
 *
 * DUA TAB:
 *   masuk       — tiket di tangan NOC & masih open (dikirim Helpdesk). Bisa diaksi.
 *   assign_fop  — tiket yang UDAH NOC teruskan ke FOP. Read-only; progres
 *                 lapangannya dibaca di /fop-tasks, bukan di sini.
 *
 * Tab kedua MURNI turunan data yang sudah ada (`handler=fop` + jejak
 * `ticket_histories`) — ini BUKAN pengembalian window "Pending NOC" + aksi
 * Oncheck yang dihapus ADHOC-06. Gak ada kolom baru, gak ada langkah
 * "terima dulu": tiket yang diassign ke NOC tetap langsung berstatus diproses.
 *
 * Gerbangnya `noc_worksheet.view` (feature root) — TETAP satu permission untuk
 * seluruh halaman. Dua permission tab lama (`noc_worksheet.masuk.view`/
 * `noc_worksheet.diproses.view`) tetap pensiun di TicketFeatureSeeder; tab baru
 * di sini SENGAJA gak menghidupkannya (tab ini bukan tab yang itu).
 *
 * Cuma nampilin daftar; aksi (Selesai/Ke FOP/Kembalikan/Batalkan) tetap lewat
 * endpoint TicketController yang sudah ada — controller ini gak duplikasi logic
 * mutasi tiket.
 */
class NocWorksheetController extends Controller
{
    private const TAB_MASUK = 'masuk';

    private const TAB_ASSIGN_FOP = 'assign_fop';

    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('noc_worksheet.view'), 403);

        $tab = $this->resolveTab($request);

        $tickets = $this->baseQuery($request, $tab)
            ->with([
                // pop_id/status/distribution_id WAJIB ikut — Customer::getDisplayIdAttribute()
                // butuh relasi POP buat resolve CID lengkap.
                'customer:id,full_name,cid,customer_code,pop_id,status,distribution_id,primary_phone',
                'customer.pop:id,name,cid_prefix',
                'creator:id,name',
                'pop:id,name',
                'issueCategory:id,name',
                // Dipakai escalatedToFopBy() di kolom "Dikirim oleh" tab Assign FOP.
                'histories.actor:id,name',
            ])
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('noc.worksheet', [
            'tickets' => $tickets,
            'tab' => $tab,
            'tabCounts' => $this->tabCounts($request),
            'filters' => $this->activeFilters($request),
            'popOptions' => Pop::forUser(auth()->user())->orderBy('name')->get(['id', 'name']),
            'categoryOptions' => TicketIssueCategory::orderBy('name')->get(['id', 'name']),
            'creatorOptions' => $this->creatorOptions(),
            'typeOptions' => TaskType::ticketOptions(),
            'priorityOptions' => FopTaskPriority::cases(),
            'allowedPopIds' => Pop::forUser(auth()->user())->pluck('id'),
        ]);
    }

    /**
     * Tab aktif. Nilai asing jatuh ke `masuk` — halaman ini sering dibuka dari
     * link/bookmark, query ngawur gak boleh jadi 500 atau tabel kosong misterius.
     */
    private function resolveTab(Request $request): string
    {
        $tab = (string) $request->query('tab', self::TAB_MASUK);

        return in_array($tab, [self::TAB_MASUK, self::TAB_ASSIGN_FOP], true) ? $tab : self::TAB_MASUK;
    }

    /**
     * Akar query per tab + seluruh filter. `applyUserScope()` wajib — tanpa itu
     * halaman ini bocorin tiket lintas cabang.
     *
     * @return Builder<Ticket>
     */
    private function baseQuery(Request $request, string $tab): Builder
    {
        $query = Ticket::query()->applyUserScope();

        if ($tab === self::TAB_ASSIGN_FOP) {
            // "Pernah lewat meja NOC" dibaca dari jejak riwayat, BUKAN kolom
            // baru: tiket yang Helpdesk kirim LANGSUNG ke FOP bukan pekerjaan
            // NOC, jadi gak boleh nongol di worksheet NOC.
            $query->where('handler', TicketHandler::FOP->value)
                ->whereHas('histories', function ($history) {
                    $history->where('action', TicketHistoryAction::DIESKALASI->value)
                        ->where('to_status', TicketHandler::NOC->value);
                });
        } else {
            $query->where('handler', TicketHandler::NOC->value)
                ->where('status', TicketHandlingStatus::OPEN->value);
        }

        $this->applyFilters($query, $request);

        return $query;
    }

    /**
     * Filter dipisah dari pemilihan tab supaya hitungan badge tiap tab memakai
     * filter yang SAMA persis dengan tabel — kalau beda, angka badge kelihatan
     * bohong waktu user sedang menyaring.
     *
     * @param  Builder<Ticket>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('detail_keluhan', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_village', 'like', "%{$search}%")
                    ->orWhereHas('customer', function (Builder $c) use ($search) {
                        $c->where('full_name', 'like', "%{$search}%")
                            ->orWhere('cid', 'like', "%{$search}%")
                            ->orWhere('customer_code', 'like', "%{$search}%");
                    });
            });
        }

        if ($popId = $request->query('pop_id')) {
            $query->where('pop_id', $popId);
        }

        if ($categoryId = $request->query('issue_category_id')) {
            $query->where('issue_category_id', $categoryId);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($priority = $request->query('priority')) {
            if ($case = FopTaskPriority::tryFrom($priority)) {
                $query->where('priority', $case->value);
            }
        }

        if ($creatorId = $request->query('created_by')) {
            $query->where('created_by', $creatorId);
        }

        if ($from = $request->query('date_from')) {
            $query->where('created_at', '>=', $from.' 00:00:00');
        }

        if ($to = $request->query('date_to')) {
            $query->where('created_at', '<=', $to.' 23:59:59');
        }
    }

    /**
     * @return array<string, int>
     */
    private function tabCounts(Request $request): array
    {
        return [
            self::TAB_MASUK => $this->baseQuery($request, self::TAB_MASUK)->count(),
            self::TAB_ASSIGN_FOP => $this->baseQuery($request, self::TAB_ASSIGN_FOP)->count(),
        ];
    }

    /**
     * Pilihan "Dikirim oleh" — hanya user yang BENERAN pernah bikin tiket dalam
     * scope POP user login, bukan seluruh tabel users.
     *
     * @return Collection<int, User>
     */
    private function creatorOptions()
    {
        $creatorIds = Ticket::query()->applyUserScope()->distinct()->pluck('created_by')->filter();

        return User::whereIn('id', $creatorIds)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return array<string, mixed>
     */
    private function activeFilters(Request $request): array
    {
        return [
            'q' => $request->query('q', ''),
            'pop_id' => $request->query('pop_id', ''),
            'issue_category_id' => $request->query('issue_category_id', ''),
            'type' => $request->query('type', ''),
            'priority' => $request->query('priority', ''),
            'created_by' => $request->query('created_by', ''),
            'date_from' => $request->query('date_from', ''),
            'date_to' => $request->query('date_to', ''),
        ];
    }
}
