<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerQrToken;
use App\Models\Task;
use App\Models\User;
use App\Services\CustomerQrTokenService;
use App\Services\EffectiveAccessService;
use App\Services\QrAttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Absen teknisi lewat QR (rancangan-qr-pelanggan-final.md §6.3).
 *
 * Route di belakang `auth` + `permission:tasks.qr_attendance.create`, tapi
 * SEMUA guard diulang di sini (token, POP scope, penugasan, status task lewat
 * policy `statusStart`) — jangan percaya bahwa halaman `show()` sudah
 * memfilter apa pun, karena `store()` bisa dipanggil langsung.
 */
class QrAttendanceController extends Controller
{
    public function __construct(
        private readonly CustomerQrTokenService $qrTokens,
        private readonly QrAttendanceService $attendance,
    ) {}

    public function show(Request $request, string $code): View|RedirectResponse
    {
        ['customer' => $customer, 'tasks' => $tasks] = $this->resolveContext($request, $code);

        if ($tasks->isEmpty()) {
            return redirect()->route('qr.scan.show')
                ->with('error', 'Tidak ada task terjadwal hari ini untuk pelanggan ini.');
        }

        if ($tasks->count() > 1) {
            return redirect()->route('qr.scan.show')
                ->with('error', 'Ada lebih dari satu task hari ini untuk pelanggan ini. Mulai task secara manual dari halaman Task.');
        }

        return view('qr.attendance', [
            'code' => $code,
            'customer' => $customer,
            'task' => $tasks->first(),
        ]);
    }

    public function store(Request $request, string $code): RedirectResponse
    {
        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        ['customer' => $customer, 'qrToken' => $qrToken, 'tasks' => $tasks] = $this->resolveContext($request, $code);

        if ($tasks->count() !== 1) {
            $this->logScan($request, $qrToken, $customer, 'no_eligible_task');

            return redirect()->route('qr.scan.show')
                ->with('error', 'Tidak ada task terjadwal yang bisa diabsen untuk pelanggan ini.');
        }

        /** @var Task $task */
        $task = $tasks->first();
        $user = $request->user();

        $latitude = isset($validated['latitude']) ? (float) $validated['latitude'] : null;
        $longitude = isset($validated['longitude']) ? (float) $validated['longitude'] : null;

        $evaluation = $this->attendance->evaluateRadius($customer, $latitude, $longitude);

        // Absen TANPA koordinat ditolak, bukan dibiarkan lolos. Kalau dibiarkan,
        // teknisi bisa "absen" dari rumah: radius tidak pernah dihitung dan
        // started_latitude kosong. Pesannya dibedakan: koordinat pelanggan belum
        // ada (masalah data, admin yang beresin) vs GPS perangkat mati (teknisi).
        if ($evaluation['flag'] === 'tanpa_koordinat') {
            $this->logScan($request, $qrToken, $customer, 'tanpa_koordinat');

            $pesan = $customer->latitude === null || $customer->longitude === null
                ? 'Koordinat pelanggan belum diisi. Minta admin melengkapinya sebelum absen.'
                : 'Absen wajib dengan lokasi GPS. Aktifkan lokasi di perangkat lalu coba lagi.';

            return redirect()->route('qr.scan.show')->with('error', $pesan);
        }

        if ($evaluation['flag'] === 'out_of_radius') {
            $this->logScan($request, $qrToken, $customer, 'out_of_radius', $evaluation['distance']);

            return redirect()->route('qr.scan.show')
                ->with('error', "Anda berada {$evaluation['distance']} m dari lokasi pelanggan. Absen hanya bisa dilakukan di lokasi.");
        }

        $this->authorize('statusStart', $task);

        $task = $this->attendance->start(
            $task,
            $user,
            $latitude,
            $longitude,
            isset($validated['accuracy']) ? (int) round($validated['accuracy']) : null,
        );

        $this->logScan($request, $qrToken, $customer, 'success', $evaluation['distance'], $evaluation['flag'] === 'perlu_review' ? 'perlu_review' : null);

        return redirect()->route('tasks.show', $task)
            ->with('success', "Absen berhasil. Task [{$task->task_number}] dimulai.");
    }

    /**
     * Resolve token + cek POP scope + ambil task hari ini. Gagal → 404/403
     * (sama seperti dispatch(), supaya respons tidak membocorkan keberadaan
     * token atau pelanggan di luar scope).
     *
     * @return array{customer: Customer, qrToken: CustomerQrToken, tasks: Collection<int, Task>}
     */
    private function resolveContext(Request $request, string $code): array
    {
        [$token, $signature] = array_pad(explode('.', $code, 2), 2, '');
        $resolution = $this->qrTokens->resolve($token, $signature);

        if ($resolution['status'] !== 'success') {
            abort(404);
        }

        /** @var CustomerQrToken $qrToken */
        $qrToken = $resolution['qrToken'];
        $customer = $qrToken->customer;
        /** @var User $user */
        $user = $request->user();

        $access = app(EffectiveAccessService::class);
        if (! $access->hasAllPopAccess($user) && ! in_array((int) $customer->pop_id, $access->getAllowedPopIds($user), true)) {
            abort(403, 'Anda tidak memiliki akses ke pelanggan di POP ini.');
        }

        return [
            'customer' => $customer,
            'qrToken' => $qrToken,
            'tasks' => $this->attendance->todaysTasks($customer, $user),
        ];
    }

    private function logScan(Request $request, CustomerQrToken $qrToken, Customer $customer, string $result, ?int $distance = null, ?string $reason = null): void
    {
        $this->qrTokens->recordScan([
            'customer_qr_token_id' => $qrToken->id,
            'customer_id' => $customer->id,
            'user_id' => $request->user()->id,
            'purpose' => 'attendance',
            'result' => $result,
            'reason' => $reason,
            'distance_meters' => $distance,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
