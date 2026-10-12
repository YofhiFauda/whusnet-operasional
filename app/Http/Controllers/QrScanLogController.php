<?php

namespace App\Http\Controllers;

use App\Models\QrScanLog;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Riwayat Scan QR (docs/plan/qr-code/rancangan-qr-pelanggan-final.md
 * §5) — satu-satunya tempat membaca `qr_scan_logs`, tabel yang SUDAH menjejak
 * SETIAP percobaan scan (termasuk GAGAL: `bad_signature`, `token_revoked`,
 * `out_of_radius`, dll) sejak Fase 1 tapi belum pernah dibaca controller/view
 * apa pun (temuan ADHOC audit dashboard, 2026-10-12) — scan gagal berulang
 * dari teknisi yang sama adalah sinyal nyata buat "orang main di lapangan",
 * bukan sampah data.
 *
 * Permission `qr_scan_logs.view` SUDAH DIGENERATE sejak `QrFeatureSeeder`
 * (feature `qr_scan_logs`, ROOT) — di sini cuma dipakai, bukan dibuat baru.
 * Audience dikoreksi (2026-10-12, keputusan eksplisit user, lihat
 * `database/seeders/RolePermissionSeeder.php`): owner, atasan, FOP — bukan
 * admin seperti draf rancangan lama. FOP butuh ini buat mengawasi
 * teknisinya, bukan mengawasi dirinya sendiri.
 */
class QrScanLogController extends Controller
{
    public function index(Request $request, EffectiveAccessService $access): View
    {
        $user = auth()->user();
        $hasAllPopAccess = $access->hasAllPopAccess($user);
        $allowedPopIds = $access->getAllowedPopIds($user);

        $filters = $this->resolveFilters($request);

        // `qr_scan_logs` TIDAK punya kolom `pop_id` sendiri — POP diturunkan
        // dari relasi (customer/task/ticket), paling spesifik duluan. Baris
        // yang gak bisa diturunkan POP-nya sama sekali (mis. `token_not_found`
        // tanpa customer) WAJIB dibuang buat user yang discope POP (deny-by-
        // default, CLAUDE.md aturan #3) — jangan ditebak, jangan diloloskan.
        $query = QrScanLog::query()
            ->with(['customer.pop', 'user', 'task.pop', 'ticket.pop'])
            ->whereBetween('scanned_at', [$filters['start'], $filters['end']])
            ->when($filters['user_id'], fn ($q) => $q->where('user_id', $filters['user_id']))
            ->when($filters['result'], fn ($q) => $q->where('result', $filters['result']));

        if (! $hasAllPopAccess) {
            $query->where(function ($q) use ($allowedPopIds) {
                $q->whereHas('customer', fn ($cq) => $cq->whereIn('pop_id', $allowedPopIds))
                    ->orWhere(function ($qq) use ($allowedPopIds) {
                        $qq->whereNull('customer_id')
                            ->whereHas('task', fn ($tq) => $tq->whereIn('pop_id', $allowedPopIds));
                    })
                    ->orWhere(function ($qq) use ($allowedPopIds) {
                        $qq->whereNull('customer_id')
                            ->whereNull('task_id')
                            ->whereHas('ticket', fn ($tq) => $tq->whereIn('pop_id', $allowedPopIds));
                    });
            });
        }

        $logs = $query->latest('scanned_at')->paginate(25)->withQueryString();

        $logs->getCollection()->transform(function (QrScanLog $log) {
            $popName = $log->customer?->pop?->name
                ?? $log->task?->pop?->name
                ?? $log->ticket?->pop?->name;

            // Baris tanpa POP yang bisa diturunkan HANYA terlihat oleh user
            // all-pop-access (sudah lolos filter di atas untuk user scoped) —
            // ditandai eksplisit, bukan dikosongkan diam-diam, biar pembaca
            // gak salah kira "POP-nya ada tapi kosong di tabel".
            $log->setAttribute('resolved_pop_name', $popName ?? 'POP tidak diketahui');

            return $log;
        });

        $technicians = User::query()
            ->whereIn('id', (clone $query)->reorder()->distinct()->pluck('user_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $resultOptions = (clone $query)->reorder('result')->distinct()->pluck('result');

        return view('qr-scan-logs.index', [
            'logs' => $logs,
            'technicians' => $technicians,
            'resultOptions' => $resultOptions,
            'preset' => $filters['preset'],
            'dateFrom' => $filters['start']->format('Y-m-d'),
            'dateTo' => $filters['end']->format('Y-m-d'),
            'userFilter' => $filters['user_id'],
            'resultFilter' => $filters['result'],
            'periodLabel' => $filters['period_label'],
        ]);
    }

    /**
     * Preset rentang tanggal — pola PERSIS `WarehouseUsageController::resolveFilters()`,
     * SENGAJA disalin bukan diekstrak ke helper bersama (dua controller beda
     * domain, kebetulan sama bentuknya — lihat CLAUDE.md "jangan overengineer").
     *
     * @return array{preset: string, start: Carbon, end: Carbon, user_id: ?int, result: ?string, period_label: string}
     */
    private function resolveFilters(Request $request): array
    {
        $preset = $request->query('preset', 'today');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $now = Carbon::now();

        switch ($preset) {
            case 'all':
                $start = Carbon::createFromDate(2000, 1, 1)->startOfDay();
                $end = $now->copy()->endOfDay();
                $periodLabel = 'Semua Waktu';
                break;
            case 'yesterday':
                $start = $now->copy()->subDay()->startOfDay();
                $end = $now->copy()->subDay()->endOfDay();
                $periodLabel = 'Kemarin ('.$start->translatedFormat('d F Y').')';
                break;
            case 'last_7_days':
                $start = $now->copy()->subDays(6)->startOfDay();
                $end = $now->copy()->endOfDay();
                $periodLabel = '7 Hari Terakhir ('.$start->translatedFormat('d M').' - '.$end->translatedFormat('d M Y').')';
                break;
            case 'this_month':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $periodLabel = 'Bulan Ini ('.$start->translatedFormat('F Y').')';
                break;
            case 'custom':
                $start = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : $now->copy()->startOfDay();
                $end = $dateTo ? Carbon::parse($dateTo)->endOfDay() : $now->copy()->endOfDay();
                $periodLabel = $start->translatedFormat('d M Y').' s/d '.$end->translatedFormat('d M Y');
                break;
            case 'today':
            default:
                $preset = 'today';
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                $periodLabel = 'Hari Ini ('.$start->translatedFormat('d F Y').')';
                break;
        }

        return [
            'preset' => $preset,
            'start' => $start,
            'end' => $end,
            'user_id' => $request->integer('user_id') ?: null,
            'result' => $request->query('result') ?: null,
            'period_label' => $periodLabel,
        ];
    }
}
