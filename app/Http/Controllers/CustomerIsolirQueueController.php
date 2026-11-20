<?php

namespace App\Http\Controllers;

use App\Enums\IsolationAdminDecision;
use App\Enums\IsolationCaseStatus;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\IsolationCase;
use App\Models\Pop;
use App\Services\ActionQueueBadgeService;
use App\Services\EffectiveAccessService;
use App\Services\IsolationCaseService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Halaman Monitoring Isolir (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md
 * §8/§21) — menggantikan "List Pelanggan Isolir" ADHOC-306 (belum pernah
 * commit) total. Empat tab lifecycle (§8.3): Menunggu Isolir, Sudah
 * Diisolir, Pemulihan, Riwayat — SEMUANYA dari `IsolationCaseService`, bukan
 * filter `status` pelanggan.
 *
 * Eksekusi isolir/pemulihan TETAP lewat `CustomerController::toggleSuspend()`
 * (satu-satunya jalur yang mengubah status layanan) — controller ini HANYA
 * mengelola kasus (tandai manual/batalkan sebelum eksekusi), tidak pernah
 * mengubah status layanan.
 */
class CustomerIsolirQueueController extends Controller
{
    public function __construct(private readonly IsolationCaseService $service) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'menunggu');
        $tab = in_array($tab, ['menunggu', 'siap', 'diisolir', 'pemulihan', 'riwayat'], true) ? $tab : 'menunggu';

        $tabStatuses = match ($tab) {
            'siap' => IsolationCaseStatus::readyForIsolationGroup(),
            'diisolir' => [IsolationCaseStatus::SUSPENDED],
            'pemulihan' => IsolationCaseStatus::restorationGroup(),
            'riwayat' => IsolationCaseStatus::historyGroup(),
            default => IsolationCaseStatus::waitingIsolationGroup(),
        };

        $builder = match ($tab) {
            'siap' => $this->service->readyForIsolationQuery($request->user()),
            'diisolir' => $this->service->suspendedQuery($request->user()),
            'pemulihan' => $this->service->restorationQuery($request->user()),
            'riwayat' => $this->service->historyQuery($request->user()),
            default => $this->service->waitingIsolationQuery($request->user()),
        };

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'pop_id' => $request->query('pop_id'),
            'status' => $request->query('status'),
            'invoice_status' => $request->query('invoice_status'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ];

        $this->applyFilters($builder, $filters, $tabStatuses);

        $cases = $builder
            ->with(['customer.pop', 'service', 'caseInvoices.invoice', 'events.actor'])
            ->orderByDesc('detected_at')
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'menunggu' => $this->service->waitingIsolationQuery($request->user())->count(),
            'siap' => $this->service->readyForIsolationQuery($request->user())->count(),
            'diisolir' => $this->service->suspendedQuery($request->user())->count(),
            'pemulihan' => $this->service->restorationQuery($request->user())->count(),
            'riwayat' => $this->service->historyQuery($request->user())->count(),
        ];

        $kpi = $this->service->kpiSummary($request->user());

        // Dropdown filter POP cuma buat pilih Cabang — Pusat & Mini POP bukan
        // satuan kerja NOC/isolir (pola sama InvoiceController).
        $pops = Pop::forUser()->where('type', 'cabang')->orderBy('name')->get(['id', 'name']);

        return view('customers.isolir-queue.index', [
            ...compact('cases', 'tab', 'counts', 'kpi', 'pops'),
            'filters' => $filters,
            'tabStatuses' => $tabStatuses,
        ]);
    }

    /**
     * Pencarian & filter (§8.5) — diterapkan DI ATAS query dasar tab yang
     * sedang aktif, jadi tidak pernah bisa "menembus" keluar grup status
     * tab (mis. filter status di tab Menunggu Isolir cuma bisa pilih di
     * antara anggota `waitingIsolationGroup()`).
     *
     * @param  Builder<IsolationCase>  $builder
     * @param  array<string, mixed>  $filters
     * @param  list<IsolationCaseStatus>  $tabStatuses
     */
    private function applyFilters(Builder $builder, array $filters, array $tabStatuses): void
    {
        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $builder->where(function (Builder $query) use ($q) {
                $query->where('case_number', 'like', "%{$q}%")
                    ->orWhereHas('customer', fn (Builder $c) => $c->where('full_name', 'like', "%{$q}%")
                        ->orWhere('cid', 'like', "%{$q}%")
                        ->orWhere('customer_code', 'like', "%{$q}%"))
                    ->orWhereHas('caseInvoices.invoice', fn (Builder $i) => $i->where('invoice_number', 'like', "%{$q}%"));
            });
        }

        if ($filters['pop_id']) {
            $builder->where('pop_id', (int) $filters['pop_id']);
        }

        $statusValues = array_map(fn ($s) => $s->value, $tabStatuses);
        if ($filters['status'] && in_array($filters['status'], $statusValues, true)) {
            $builder->where('status', $filters['status']);
        }

        if ($filters['invoice_status'] === 'lunas') {
            $builder->where('current_outstanding_amount', '<=', 0);
        } elseif ($filters['invoice_status'] === 'belum_lunas') {
            $builder->where('current_outstanding_amount', '>', 0);
        }

        if ($filters['date_from']) {
            $builder->whereDate('detected_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $builder->whereDate('detected_at', '<=', $filters['date_to']);
        }
    }

    /**
     * Jumlah antrean untuk badge sidebar. "Antrean" = kasus yang masih perlu
     * dipantau/ditindaklanjuti (menunggu isolir + sudah diisolir + pemulihan),
     * TIDAK termasuk riwayat.
     */
    public function badgeCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => app(ActionQueueBadgeService::class)->isolirQueue($request->user())->count(),
        ]);
    }

    /**
     * Tandai manual dari List Pelanggan (Quick Hub/baris, fetch JSON) maupun
     * dari halaman Monitoring Isolir sendiri.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'note' => ['required', 'string', 'max:500'],
        ]);

        $customer = Customer::findOrFail($data['customer_id']);
        $this->authorizeCustomerPopScope($customer);

        $service = $customer->customerService;

        if ($customer->status !== 'active' || ! $service) {
            $message = 'Hanya pelanggan dengan layanan aktif yang bisa ditandai untuk isolir.';

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return redirect()->back()->with('error', $message);
        }

        $this->service->markManual($service, $request->user(), $data['note']);

        $message = "Pelanggan {$customer->full_name} ditandai masuk Monitoring Isolir.";

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Batalkan kasus SEBELUM isolir dieksekusi (status masih di kelompok
     * "Menunggu Isolir" — lihat `IsolationCaseService::cancelCase()`).
     */
    public function destroy(Request $request, CustomerService $customerService): RedirectResponse
    {
        $customer = $customerService->customer;
        $this->authorizeCustomerPopScope($customer);

        $case = $this->service->findActiveCaseForService($customerService);

        if ($case) {
            $this->service->cancelCase($case, $request->user(), 'manual_cancel');
        }

        return redirect()->back()->with('success', "Kasus isolir pelanggan {$customer->full_name} dibatalkan.");
    }

    /**
     * Keputusan Admin "Eksekusi Isolir"/"Toleransi" (follow-up 2026-11-19) —
     * gerbang yang dicek `CustomerController::toggleSuspend()` sebelum NOC
     * boleh isolir. Permission SAMA dengan "Tandai Manual"
     * (`customers.isolir_queue.create`) — satu kelompok aktor (Admin/CS)
     * yang menentukan antrean, bukan yang mengeksekusi.
     */
    public function decide(Request $request, CustomerService $customerService): RedirectResponse
    {
        $customer = $customerService->customer;
        $this->authorizeCustomerPopScope($customer);

        $data = $request->validate([
            'decision' => ['required', Rule::enum(IsolationAdminDecision::class)],
            'note' => ['nullable', 'string', 'max:500'],
            'until' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $case = $this->service->findActiveCaseForService($customerService);

        if (! $case) {
            return redirect()->back()->with('error', "Pelanggan {$customer->full_name} tidak punya kasus isolir aktif.");
        }

        $decision = IsolationAdminDecision::from($data['decision']);
        $this->service->setAdminDecision($case, $decision, $request->user(), $data['note'] ?? null, $data['until'] ?? null);

        $message = $decision === IsolationAdminDecision::EXECUTE
            ? "Kasus {$case->case_number} diputuskan: Eksekusi Isolir."
            : "Kasus {$case->case_number} diputuskan: Toleransi.";

        return redirect()->back()->with('success', $message);
    }

    private function authorizeCustomerPopScope(Customer $customer): void
    {
        $access = app(EffectiveAccessService::class);
        $user = auth()->user();

        if ($access->hasAllPopAccess($user)) {
            return;
        }

        abort_unless(
            in_array((int) $customer->pop_id, $access->getAllowedPopIds($user), true),
            403,
            'Anda tidak memiliki akses ke pelanggan di POP ini.'
        );
    }
}
