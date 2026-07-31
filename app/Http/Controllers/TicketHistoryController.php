<?php

namespace App\Http\Controllers;

use App\Enums\TaskType;
use App\Enums\TicketHandler;
use App\Enums\TicketHandlingStatus;
use App\Models\Pop;
use App\Models\Ticket;
use App\Models\TicketIssueCategory;
use App\Models\User;
use App\Support\IndonesianDate;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * History Ticketing — arsip SELURUH tiket, pengganti sheet Google Sheets
 * "Helpdesk Task Manager" yang dipakai Helpdesk sebelum sistem ini ada
 * (docs/plan/analisa-halaman-history-ticketing.md).
 *
 * SENGAJA bukan turunan TicketArchiveController: kelas itu terikat SATU bucket
 * (`inBucket()`), sedangkan halaman ini justru harus menampung semua bucket
 * sekaligus — termasuk tiket yang masih jalan dan tiket "Terputus" (orphan).
 *
 * Yang TIDAK masuk sini: FopTask yang gak lahir dari tiket (disubmit langsung
 * dari /fop-tasks). Ticketing khusus melayani keluhan pelanggan; task FOP
 * mandiri tetap dipantau di modulnya sendiri.
 */
class TicketHistoryController extends Controller
{
    private const STATUS_SELESAI = 'selesai';

    private const STATUS_DIBATALKAN = 'dibatalkan';

    /**
     * Tiket yang diserahkan ke FOP. SATU status, apa pun yang terjadi di
     * lapangan setelahnya (Terjadwal/In Progress/Selesai/Dibatalkan/FopTask
     * dihapus) — itu semua dibaca di modul FOP, bukan di sini.
     */
    private const STATUS_ASSIGN_FOP = 'assign_fop';

    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('tickets.history.view'), 403);

        $query = $this->baseQuery($request);

        $summary = $this->summarize($request);

        $tickets = (clone $query)
            ->with([
                // pop_id/status/distribution_id WAJIB ikut — Customer::getDisplayIdAttribute()
                // butuh relasi POP buat resolve CID lengkap (lihat catatan sama
                // di TicketArchiveController).
                'customer:id,full_name,cid,customer_code,pop_id,status,distribution_id',
                'customer.pop:id,name,cid_prefix',
                'creator:id,name',
                'pop:id,name',
                'issueCategory:id,name',
                'histories.actor:id,name',
            ])
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('tickets.history', [
            'tickets' => $tickets,
            'summary' => $summary,
            'filters' => $this->activeFilters($request),
            'popOptions' => Pop::forUser(auth()->user())->orderBy('name')->get(['id', 'name']),
            'categoryOptions' => TicketIssueCategory::orderBy('name')->get(['id', 'name']),
            'creatorOptions' => $this->creatorOptions(),
            'statusOptions' => $this->statusOptions(),
            'typeOptions' => TaskType::ticketOptions(),
            'canExport' => auth()->user()->hasPermission('tickets.history.export'),
        ]);
    }

    /**
     * Ekspor hasil filter yang SAMA persis dengan yang tampil di layar —
     * termasuk POP scope. Ditulis per-chunk supaya rekap setahun gak narik
     * semua baris ke memori sekaligus.
     */
    public function export(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('tickets.history.export'), 403);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'history-ticketing-'.uniqid().'.xlsx';

        $writer = SimpleExcelWriter::create($path);

        $this->baseQuery($request)
            ->with([
                'customer:id,full_name,cid,customer_code,pop_id,status,distribution_id',
                'customer.pop:id,name,cid_prefix',
                'creator:id,name',
                'pop:id,name',
                'issueCategory:id,name',
                'histories.actor:id,name',
            ])
            ->orderBy('created_at')
            ->chunk(500, function ($tickets) use ($writer) {
                $writer->addRows($tickets->map(fn (Ticket $ticket) => $this->exportRow($ticket))->all());
            });

        $writer->close();

        return response()->download($path, 'history-ticketing-'.now()->format('Ymd-His').'.xlsx')
            ->deleteFileAfterSend();
    }

    /**
     * Akar query halaman ini — cuma tiket yang UDAH LEPAS dari meja Ticketing:
     * Selesai, Dibatalkan, atau diserahkan ke FOP.
     *
     * Tiket yang masih `open` di tangan Helpdesk/NOC SENGAJA dikecualikan: itu
     * pekerjaan berjalan, rumahnya Worksheet Helpdesk & Worksheet NOC. Kalau
     * ikut ditampilkan di sini, History jadi duplikat antrean kerja dan
     * "arsip" kehilangan artinya.
     *
     * `applyUserScope()` wajib — tanpa itu halaman ini bocorin data lintas cabang.
     *
     * @return Builder<Ticket>
     */
    private function baseQuery(Request $request): Builder
    {
        $query = Ticket::query()->applyUserScope()->where(function (Builder $scope) {
            $scope->whereIn('status', [
                TicketHandlingStatus::CLOSED->value,
                TicketHandlingStatus::CANCELLED->value,
            ])->orWhere('handler', TicketHandler::FOP->value);
        });

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

        if ($handler = $request->query('handler')) {
            if ($case = TicketHandler::tryFrom($handler)) {
                $query->where('handler', $case->value);
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

        $this->applyStatusFilter($query, (string) $request->query('status', ''));

        return $query;
    }

    /**
     * Filter status — TIGA nilai saja, sesuai isi halaman ini. SENGAJA bukan
     * `TicketBucket`/`scopeInBucket()`: bucket `masuk`/`diproses` gak pernah
     * muncul di sini, dan bucket `selesai`/`dibatalkan` versi bucket ikut
     * menarik status FopTask — padahal History berhenti di "Assign FOP".
     *
     * @param  Builder<Ticket>  $query
     */
    private function applyStatusFilter(Builder $query, string $status): void
    {
        match ($status) {
            self::STATUS_SELESAI => $query
                ->where('status', TicketHandlingStatus::CLOSED->value)
                ->whereNot('handler', TicketHandler::FOP->value),
            self::STATUS_DIBATALKAN => $query
                ->where('status', TicketHandlingStatus::CANCELLED->value)
                ->whereNot('handler', TicketHandler::FOP->value),
            self::STATUS_ASSIGN_FOP => $query->where('handler', TicketHandler::FOP->value),
            default => null,
        };
    }

    /**
     * Ringkasan header — meniru "RATA² SOLVING TME" di sheet lama.
     *
     * Rata-rata dihitung di DATABASE, bukan dengan menarik semua baris ke PHP:
     * rekap setahun bisa puluhan ribu baris. Ekspresi selisih waktu beda per
     * driver, jadi dicabangkan eksplisit — sqlite dipakai test, mysql dipakai
     * produksi.
     *
     * Rata-rata dihitung dari `resolved_at` yang terisi saja: tiket dibatalkan
     * sengaja gak punya nilai itu (dibatalkan bukan diselesaikan), jadi gak
     * ikut menyeret angka rata-rata.
     *
     * @return array{total: int, selesai: int, assign_fop: int, dibatalkan: int, avg_minutes: ?int, avg_label: string}
     */
    private function summarize(Request $request): array
    {
        $total = (clone $this->baseQuery($request))->count();

        $selesai = (clone $this->baseQuery($request))
            ->where('status', TicketHandlingStatus::CLOSED->value)
            ->whereNot('handler', TicketHandler::FOP->value)
            ->count();

        $assignFop = (clone $this->baseQuery($request))
            ->where('handler', TicketHandler::FOP->value)
            ->count();

        $withTime = (clone $this->baseQuery($request))->whereNotNull('resolved_at')->count();

        $avgMinutes = null;

        if ($withTime > 0) {
            $expression = match (DB::connection()->getDriverName()) {
                'sqlite' => '(julianday(resolved_at) - julianday(created_at)) * 1440',
                'pgsql' => 'EXTRACT(EPOCH FROM (resolved_at - created_at)) / 60',
                default => 'TIMESTAMPDIFF(MINUTE, created_at, resolved_at)',
            };

            $avgMinutes = (clone $this->baseQuery($request))
                ->whereNotNull('resolved_at')
                ->avg(DB::raw($expression));

            $avgMinutes = $avgMinutes === null ? null : (int) round((float) $avgMinutes);
        }

        return [
            'total' => $total,
            'selesai' => $selesai,
            'assign_fop' => $assignFop,
            'dibatalkan' => $total - $selesai - $assignFop,
            'avg_minutes' => $avgMinutes,
            'avg_label' => $avgMinutes === null
                ? '—'
                : sprintf('%d:%02d:00', intdiv($avgMinutes, 60), $avgMinutes % 60),
        ];
    }

    /**
     * Pilihan "Input By" — hanya user yang BENERAN pernah bikin tiket dalam
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
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            self::STATUS_SELESAI => 'Selesai (Helpdesk/NOC)',
            self::STATUS_DIBATALKAN => 'Dibatalkan (Helpdesk/NOC)',
            self::STATUS_ASSIGN_FOP => 'Assign FOP',
        ];
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
            'handler' => $request->query('handler', ''),
            'status' => $request->query('status', ''),
            'created_by' => $request->query('created_by', ''),
            'date_from' => $request->query('date_from', ''),
            'date_to' => $request->query('date_to', ''),
        ];
    }

    /**
     * Satu baris ekspor — urutan kolomnya mengikuti sheet lama supaya rekap
     * lama & baru bisa ditumpuk tanpa mapping ulang.
     *
     * @return array<string, string|null>
     */
    private function exportRow(Ticket $ticket): array
    {
        return [
            'DATE' => IndonesianDate::dateTime($ticket->created_at),
            'TIKET' => $ticket->ticket_number,
            'INPUT BY' => $ticket->creator?->name,
            'NAMA' => $ticket->customer_name,
            'CID' => $ticket->customer?->display_id,
            'HP/CONTACT' => $ticket->customer_phone,
            'DESA' => $ticket->customer_village,
            'POP' => $ticket->pop?->name,
            'ISSUE/ADUAN' => $ticket->detail_keluhan,
            'KATEGORI' => $ticket->issueCategory?->name,
            'PAKET' => $ticket->customer_package,
            'STATUS' => self::statusLabelFor($ticket),
            'OLEH' => self::actorLabelFor($ticket),
            // Buat tiket jalur FOP dua kolom ini berarti "kapan diserahkan ke
            // FOP" & "berapa lama di meja Ticketing", BUKAN waktu pekerjaan
            // lapangan beres — itu diukur di modul FOP.
            'SELESAI/DISERAHKAN' => $ticket->resolved_at ? IndonesianDate::dateTime($ticket->resolved_at) : null,
            'DURASI TICKETING' => $ticket->solvingTimeLabel(),
        ];
    }

    /**
     * Label status baris History. Dipakai view DAN exportRow() — satu sumber,
     * biar tabel di layar dan file ekspor gak pernah beda isi.
     *
     * SENGAJA TIDAK pakai Ticket::statusLabel(): method itu menurunkan label
     * dari status FopTask begitu `handler=FOP` (Terjadwal/In Progress/Selesai/
     * Terputus). History Ticketing berhenti di titik penyerahan — semua tiket
     * jalur FOP berlabel "Assign FOP", progres lapangannya dibaca di /fop-tasks.
     */
    public static function statusLabelFor(Ticket $ticket): string
    {
        if ($ticket->handler === TicketHandler::FOP) {
            return 'Assign FOP';
        }

        return match ($ticket->status) {
            TicketHandlingStatus::CLOSED => 'Selesai ('.$ticket->handler->label().')',
            TicketHandlingStatus::CANCELLED => 'Dibatalkan ('.$ticket->handler->label().')',
            // Tiket `open` di Helpdesk/NOC gak pernah masuk halaman ini
            // (baseQuery() membuangnya). Cabang ini ada supaya helper-nya jujur
            // kalau dipanggil dari konteks lain — jangan dibalikin ke ternary
            // yang menganggap "bukan closed berarti cancelled", itu bikin tiket
            // yang masih dikerjakan kelihatan "Dibatalkan".
            default => 'Diproses '.$ticket->handler->label(),
        };
    }

    /**
     * Kelas badge per baris — sewarna dengan label di atas, bukan turunan
     * status FopTask (Ticket::statusBadgeClasses()).
     */
    public static function statusBadgeFor(Ticket $ticket): string
    {
        if ($ticket->handler === TicketHandler::FOP) {
            return 'bg-sky-50 dark:bg-sky-900/20 border-sky-200 dark:border-sky-800/50 text-sky-700 dark:text-sky-400';
        }

        return match ($ticket->status) {
            TicketHandlingStatus::CLOSED => 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800/50 text-emerald-700 dark:text-emerald-400',
            TicketHandlingStatus::CANCELLED => 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400',
            default => 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800/50 text-amber-700 dark:text-amber-400',
        };
    }

    /**
     * Siapa yang "menutup buku" tiket ini dari sisi Ticketing — artinya beda
     * per hasil akhir, dan itu justru yang berguna dibaca:
     *
     *   Selesai      → yang menyelesaikan
     *   Dibatalkan   → yang membatalkan
     *   Assign FOP   → yang MENGIRIM ke FOP (bukan teknisi yang mengerjakan —
     *                  pengerjaan lapangan dibaca di /fop-tasks)
     *
     * Butuh `histories.actor` eager-loaded (lihat catatan di Ticket).
     */
    public static function actorLabelFor(Ticket $ticket): ?string
    {
        if ($ticket->handler === TicketHandler::FOP) {
            return $ticket->escalatedToFopBy()?->name;
        }

        return match ($ticket->status) {
            TicketHandlingStatus::CLOSED => $ticket->closedBy()?->name,
            TicketHandlingStatus::CANCELLED => $ticket->cancelledBy()?->name,
            default => null,
        };
    }
}
