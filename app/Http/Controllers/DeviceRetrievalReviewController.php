<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\TaskDeviceRetrieval;
use App\Services\DeviceRetrievalReviewService;
use App\Support\LikeSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Halaman "Review Penarikan Alat" — laporan DEAC gagal per pelanggan,
 * diputuskan FOP (approve/tolak) dan dipantau CS/BD (komentar).
 * Semua logic di DeviceRetrievalReviewService; controller ini cuma
 * validasi, cek POP scope, dan redirect PRG.
 *
 * Aksi tersedia di DUA tempat (keputusan user Q7): baris List (inline) dan
 * Detail per pelanggan — keduanya POST ke endpoint yang sama. Tujuan
 * redirect dipilih dari token `return_to` (list|detail) yang dirakit server
 * di sini, BUKAN URL dari form, supaya tidak bisa dijadikan open redirect.
 *
 * Gagal validasi dari List: Laravel kembali ke halaman List (referer) dengan
 * old input `retrieval_id` + `review_form` — view memakainya untuk membuka
 * lagi baris & form yang gagal sehingga pesan error tidak hilang tertutup
 * (penyimpangan sadar dari pola #2 CLAUDE.md, lihat §8 dokumen rancangan).
 *
 * docs/plan/warehouse/analisa-review-penarikan-deac.md
 */
class DeviceRetrievalReviewController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private readonly DeviceRetrievalReviewService $reviews) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $this->resolveTab($request->query('tab'));

        $tabCounts = collect(DeviceRetrievalReviewService::TABS)
            ->mapWithKeys(fn (string $t) => [$t => $this->reviews->customersForTab($user, $t)->count()]);

        // Percobaan terbanyak di atas — kasus yang sudah ≥ 5 percobaan
        // ("boleh ditoleransi") otomatis naik ke atas (§3.3 rancangan).
        // Pencarian cuma menyaring daftar; hitungan di summary strip tetap
        // seluruh antrian (ukuran beban kerja, bukan hasil filter). Kolom
        // sama dengan List Pelanggan (RendersCustomerList).
        $search = LikeSearch::sanitize((string) $request->query('q', ''));

        $customers = $this->reviews->customersForTab($user, $tab)
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $match) use ($search) {
                if (preg_match('/\d/', $search)) {
                    $match->where('customers.customer_code', 'like', "{$search}%")
                        ->orWhere('customers.cid', 'like', "%{$search}%")
                        ->orWhere('customers.primary_phone', 'like', "%{$search}%");
                } else {
                    $match->where('customers.full_name', 'like', "%{$search}%");
                }
            }))
            ->withCount(['deviceRetrievals as retrieval_attempts_count' => fn (Builder $q) => $this->reviews->constrainToCase($q)])
            ->with([
                'pop:id,name',
                'customerDevice',
                'deviceRetrievals' => fn ($q) => $q->whereNotNull('task_device_retrievals.review_status')
                    ->with(['task.teamMembers.user', 'task.fopTask', 'reviewer', 'comments.user']),
            ])
            ->orderByDesc('retrieval_attempts_count')
            ->orderBy('customers.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        foreach ($customers as $customer) {
            $customer->setRelation('caseAttempts', $this->reviews->filterToCase($customer, $customer->deviceRetrievals));
        }

        return view('device_retrieval_reviews.index', [
            'customers' => $customers,
            'tab' => $tab,
            'tabCounts' => $tabCounts,
            'search' => $search,
            'canReview' => $user->hasPermission('device_retrieval_review.review'),
            'canComment' => $user->hasPermission('device_retrieval_review.comment'),
        ]);
    }

    public function show(Request $request, Customer $customer): View
    {
        $this->authorizeCustomerScope($request, $customer);

        $attempts = $this->reviews->caseAttempts($customer);
        abort_if($attempts->isEmpty(), 404);

        $customer->loadMissing(['pop:id,name', 'customerDevice']);

        return view('device_retrieval_reviews.show', [
            'customer' => $customer,
            'attempts' => $attempts,
            'canReview' => $request->user()->hasPermission('device_retrieval_review.review'),
            'canComment' => $request->user()->hasPermission('device_retrieval_review.comment'),
        ]);
    }

    public function approve(Request $request, TaskDeviceRetrieval $retrieval): RedirectResponse
    {
        $customer = $this->authorizeRetrievalScope($request, $retrieval);
        $validated = $request->validate($this->commonRules() + [
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->reviews->approve($retrieval, $request->user(), $validated['review_note'] ?? null);

        return $this->redirectBack($validated, $customer)
            ->with('success', "Laporan penarikan alat {$customer->full_name} disetujui — pelanggan dibebaskan dari penarikan alat.");
    }

    public function reject(Request $request, TaskDeviceRetrieval $retrieval): RedirectResponse
    {
        $customer = $this->authorizeRetrievalScope($request, $retrieval);
        $validated = $request->validate($this->commonRules() + [
            'review_note' => ['required', 'string', 'max:1000'],
            'schedule_now' => ['nullable', 'boolean'],
        ], [
            'review_note.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $fopTask = $this->reviews->reject($retrieval, $request->user(), $validated['review_note']);
        $message = "Laporan ditolak. Task pengambilan alat baru {$fopTask->task_number} dibuat (Draft).";

        // "Langsung jadwalkan" (Q4/Q8): arahkan ke form assign /fop-tasks yang
        // sudah ada, difilter ke task baru. Tanpa hak fop_tasks.view jatuh ke
        // halaman review biasa — task tetap Draft menunggu dijadwalkan.
        if ($request->boolean('schedule_now') && $request->user()->hasPermission('fop_tasks.view')) {
            return redirect()->route('fop-tasks.index', ['search' => $fopTask->task_number])
                ->with('success', $message.' Silakan tugaskan teknisi.');
        }

        return $this->redirectBack($validated, $customer)->with('success', $message);
    }

    public function comment(Request $request, TaskDeviceRetrieval $retrieval): RedirectResponse
    {
        $customer = $this->authorizeRetrievalScope($request, $retrieval);
        $validated = $request->validate($this->commonRules() + [
            'body' => ['required', 'string', 'max:1000'],
        ], [
            'body.required' => 'Komentar tidak boleh kosong.',
        ]);

        $this->reviews->comment($retrieval, $request->user(), $validated['body']);

        return $this->redirectBack($validated, $customer)->with('success', 'Komentar tersimpan.');
    }

    /**
     * Field pendamping semua form aksi. `retrieval_id` & `review_form` cuma
     * dibaca view saat gagal validasi (buka ulang baris + form yang gagal).
     *
     * @return array<string, array<int, mixed>>
     */
    private function commonRules(): array
    {
        return [
            'return_to' => ['nullable', Rule::in(['list', 'detail'])],
            'tab' => ['nullable', Rule::in(DeviceRetrievalReviewService::TABS)],
            'retrieval_id' => ['nullable', 'integer'],
            'review_form' => ['nullable', Rule::in(['approve', 'reject', 'comment'])],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function redirectBack(array $validated, Customer $customer): RedirectResponse
    {
        if (($validated['return_to'] ?? null) === 'detail') {
            return redirect()->route('device-retrieval-reviews.show', $customer);
        }

        return redirect()->to(route('device-retrieval-reviews.index', ['tab' => $this->resolveTab($validated['tab'] ?? null)]).'#pelanggan-'.$customer->id);
    }

    private function resolveTab(mixed $tab): string
    {
        return in_array($tab, DeviceRetrievalReviewService::TABS, true) ? $tab : DeviceRetrievalReviewService::TAB_MENUNGGU;
    }

    private function authorizeCustomerScope(Request $request, Customer $customer): void
    {
        abort_unless(Customer::applyUserScope($request->user())->whereKey($customer->id)->exists(), 403);
    }

    private function authorizeRetrievalScope(Request $request, TaskDeviceRetrieval $retrieval): Customer
    {
        $customer = $retrieval->task?->customer;
        abort_unless($customer, 404);
        $this->authorizeCustomerScope($request, $customer);

        return $customer;
    }
}
