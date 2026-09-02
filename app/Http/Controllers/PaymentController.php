<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\CollectorActivityUpdated;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Pop;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\CustomerBalanceService;
use App\Services\FileUploadService;
use App\Services\PaymentService;
use App\Services\Receipts\ReceiptPresenter;
use App\Support\ReasonValidationRule;
use App\Support\RupiahInput;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /**
     * Display a listing of payments with operational filters.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $popId = $request->query('pop_id', '');
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $method = trim((string) $request->query('method', ''));
        $status = trim((string) $request->query('status', ''));
        $invoiceType = trim((string) $request->query('invoice_type', ''));
        $allowedMethods = array_column(PaymentMethod::cases(), 'value');
        $allowedStatuses = array_column(PaymentStatus::cases(), 'value');

        $query = Payment::query()
            ->applyUserScope()
            ->with(['invoice', 'customer', 'pop', 'receiver', 'collector'])
            ->latest('payment_date')
            ->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('old_payment_id', 'like', "%{$search}%")
                    ->orWhere('old_transaction_id', 'like', "%{$search}%")
                    ->orWhereHas('invoice', function ($invoiceQuery) use ($search) {
                        $invoiceQuery->where('invoice_number', 'like', "%{$search}%")
                            ->orWhere('old_invoice_id', 'like', "%{$search}%")
                            ->orWhere('old_cost_id', 'like', "%{$search}%");
                    })
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('full_name', 'like', "%{$search}%")
                            ->orWhere('customer_code', 'like', "%{$search}%")
                            ->orWhere('old_customer_id', 'like', "%{$search}%")
                            ->orWhere('cid', 'like', "%{$search}%")
                            ->orWhere('primary_phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($popId !== '') {
            $query->where('pop_id', $popId);
        }

        // whereDate() membungkus kolom jadi DATE(payment_date) dan mematikan
        // index. Batas ditulis eksplisit startOfDay/endOfDay — lihat alasan
        // lengkapnya di CustomerReportController::index().
        if ($dateFrom !== '') {
            $query->where('payment_date', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        if ($dateTo !== '') {
            $query->where('payment_date', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        if ($method !== '' && in_array($method, $allowedMethods, true)) {
            $query->where('payment_method', $method);
        }

        if ($status !== '' && in_array($status, $allowedStatuses, true)) {
            $query->where('payment_status', $status);
        }

        if ($invoiceType !== '') {
            $query->whereHas('invoice', function ($invoiceQuery) use ($invoiceType) {
                $invoiceQuery->where('invoice_type', $invoiceType);
            });
        }

        $payments = $query->paginate(10)->withQueryString();
        $pops = Pop::forUser()->orderBy('name')->get();

        return view('payments.index', compact(
            'payments',
            'pops',
            'search',
            'popId',
            'dateFrom',
            'dateTo',
            'method',
            'status',
            'invoiceType',
            'allowedMethods',
            'allowedStatuses'
        ));
    }

    /**
     * Tab Khusus lebih bayar — daftar READ-ONLY pembayaran yang punya
     * `overpay_amount > 0`. SENGAJA bukan saldo/ledger (§D-5 tetap di luar
     * scope, lihat catatan di docs/plan/analisa-billing-tagihan-pembayaran-
     * kolektor.md): halaman ini murni menampilkan pembayaran mana yang
     * punya kelebihan uang, biar admin tahu ke mana harus menyelesaikannya
     * secara manual. Tidak ada aksi "pakai saldo" di sini.
     *
     * Reuse permission `payments.view` — ini sudut pandang lain dari data
     * yang sama, bukan modul/objek bisnis baru seperti Ticketing.
     */
    public function overpay(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $popId = $request->query('pop_id', '');

        $query = Payment::query()
            ->applyUserScope()
            ->where('payment_status', PaymentStatus::VALID->value)
            ->where('overpay_amount', '>', 0)
            ->with(['invoice', 'customer', 'pop', 'receiver', 'collector'])
            ->latest('payment_date')
            ->latest('id');

        if ($search !== '') {
            $query->whereHas('customer', function ($customerQuery) use ($search) {
                $customerQuery->where('full_name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('cid', 'like', "%{$search}%");
            });
        }

        if ($popId !== '') {
            $query->where('pop_id', $popId);
        }

        $totalOverpay = (float) (clone $query)->sum('overpay_amount');
        $payments = $query->paginate(10)->withQueryString();
        $pops = Pop::forUser()->orderBy('name')->get();

        return view('payments.overpay', compact('payments', 'pops', 'search', 'popId', 'totalOverpay'));
    }

    /**
     * Display a single payment detail.
     */
    public function show(Payment $payment): View
    {
        abort_unless(
            Payment::query()->applyUserScope()->whereKey($payment->id)->exists(),
            403,
            'Anda tidak memiliki akses ke pembayaran POP ini.'
        );

        $relations = ['invoice.customerService', 'invoice.internetPackage', 'customer', 'pop', 'receiver', 'collector'];

        if (auth()->user()->hasPermission('audit_logs.view')) {
            $relations[] = 'auditLogs.user';
        }

        $payment->load($relations);
        $installmentContext = $payment->installmentContext();
        // Lembar cetak A4 di halaman ini memakai isi yang sama persis dengan
        // struk thermal & kartu kolektor — lihat ReceiptPresenter.
        $kwitansi = app(ReceiptPresenter::class)->for($payment);

        return view('payments.show', compact('payment', 'installmentContext', 'kwitansi'));
    }

    /**
     * Struk/kwitansi cetak untuk satu pembayaran.
     *
     * Dipakai tombol "Cetak Struk" di Modal Hub List Pelanggan dan dari detail
     * pembayaran. Scope POP dicek ulang di sini — struk memuat identitas dan
     * nominal pelanggan, jadi tidak boleh bisa dibuka lintas cabang cuma karena
     * ID pembayarannya ketebak.
     */
    public function receipt(Payment $payment): View
    {
        abort_unless(
            Payment::query()->applyUserScope()->whereKey($payment->id)->exists(),
            403,
            'Anda tidak memiliki akses ke pembayaran POP ini.'
        );

        $payment->load(['invoice.internetPackage', 'customer', 'pop', 'receiver', 'collector']);
        $installmentContext = $payment->installmentContext();
        $kwitansi = app(ReceiptPresenter::class)->for($payment);

        return view('payments.receipt', compact('payment', 'installmentContext', 'kwitansi'));
    }

    /**
     * Show payment input form for an invoice.
     */
    public function create(Invoice $invoice): View
    {
        $this->authorizeInvoiceAccess($invoice);

        $invoice->load(['customer', 'pop', 'customerService', 'internetPackage']);

        // Urutan cicilan yang AKAN dibuat kalau form ini disimpan. Cuma
        // pembayaran VALID yang dihitung — pembayaran ditolak tidak boleh
        // menggeser nomor cicilan, kalau tidak riwayat jadi bolong.
        $nextInstallmentNumber = $invoice->payments()
            ->where('payment_status', PaymentStatus::VALID->value)
            ->count() + 1;

        // Saldo pelanggan (dari sisa bayar/overpay sebelumnya) — dipakai
        // checklist "Pakai saldo pelanggan" di atas Catatan. 0 kalau invoice
        // tak terhubung pelanggan.
        $customerBalance = $invoice->customer
            ? app(CustomerBalanceService::class)->balance($invoice->customer)
            : 0.0;

        return view('payments.create', compact('invoice', 'nextInstallmentNumber', 'customerBalance'));
    }

    /**
     * Store payment and update invoice paid/remaining amounts.
     */
    public function store(Request $request, Invoice $invoice): RedirectResponse|JsonResponse
    {
        $this->authorizeInvoiceAccess($invoice);

        if ($invoice->invoice_status === InvoiceStatus::LUNAS) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Tagihan ini sudah lunas.'], 422);
            }

            return redirect()
                ->route('invoices.show', $invoice->id)
                ->withErrors(['amount' => 'Tagihan ini sudah lunas.']);
        }

        if ($invoice->invoice_status === InvoiceStatus::BATAL) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Tagihan yang batal tidak dapat menerima pembayaran.'], 422);
            }

            return redirect()
                ->route('invoices.show', $invoice->id)
                ->withErrors(['amount' => 'Tagihan yang batal tidak dapat menerima pembayaran.']);
        }

        // Submit ulang dengan kunci yang sama TIDAK menyimpan payment kedua.
        // Dicek sebelum validasi, sama seperti jalur kolektor: pada submit
        // ulang tagihannya sudah lunas dari submit pertama, jadi validasi pasti
        // gagal dan pengguna menerima error untuk pembayaran yang sebenarnya
        // berhasil.
        $duplikat = $this->existingPaymentFor($request);

        if ($duplikat) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Pembayaran {$duplikat->payment_number} sudah tercatat sebelumnya — tidak diproses ulang.",
                    'payment' => [
                        'payment_number' => $duplikat->payment_number,
                        'amount' => (float) $duplikat->amount,
                    ],
                    'already_processed' => true,
                ]);
            }

            return redirect()
                ->route('invoices.show', $invoice->id)
                ->with('success', "Pembayaran {$duplikat->payment_number} sudah tercatat sebelumnya — tidak diproses ulang.");
        }

        // `150.000` yang diketik kasir = seratus lima puluh ribu, bukan 150,0.
        // Tanpa normalisasi ini titiknya dibaca sebagai desimal Inggris dan
        // pembayaran tercatat 1.000 kali lebih kecil TANPA satu pun error.
        $request->merge(['amount' => RupiahInput::parse($request->input('amount'))]);

        $validated = $request->validate([
            // Batas atas WAJIB, sejajar dengan jalur kolektor
            // (RecordsCollectorBatch::batchValidationRules()). Tanggal masa
            // depan merusak pemotongan pendapatan per periode dan membuat
            // laporan bulan berjalan memuat uang yang belum ada.
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            // Transfer & Kolektor punya field pendukung wajib — lihat
            // PaymentMethod::requiresBankDetails()/requiresCollector().
            'bank_name' => 'required_if:payment_method,transfer|nullable|string|max:100',
            'account_number' => 'required_if:payment_method,transfer|nullable|string|max:50',
            'collected_by' => [
                'required_if:payment_method,kolektor',
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    if (! $value) {
                        return;
                    }

                    $collector = User::find($value);

                    if (! $collector || ! $collector->hasRole('kolektor')) {
                        $fail('User yang dipilih bukan kolektor.');
                    }
                },
            ],
            // Nominal saldo pelanggan yang dipakai untuk pembayaran ini.
            // Dicek ulang (dengan lock) di PaymentService::record() — di
            // sini cuma validasi bentuk, bukan kecukupan saldo.
            'use_balance_amount' => 'nullable|numeric|min:0',
            // Nullable, bukan required: endpoint ini juga dipakai dari JSON
            // oleh pemanggil yang tak punya form untuk menaruh kuncinya.
            // Tanpa kunci, perilakunya persis seperti dulu.
            'idempotency_key' => 'nullable|string|max:191',
            // `amount` = TOTAL uang tunai/transfer/kolektor yang diserahkan
            // pelanggan (di luar saldo), bukan lagi dibatasi sisa tagihan.
            // Kalau amount+saldo melebihi sisa, kelebihannya otomatis
            // dipisah jadi overpay_amount di transaction di bawah — admin
            // tak perlu hitung sendiri "sisa tagihan dikurangi total"
            // (2026-08-04: versi lama minta itu, gampang salah ketik/hitung
            // di lapangan, lihat docs/plan/analisa-billing-tagihan-
            // pembayaran-kolektor.md §D-5).
            'amount' => 'required|numeric|min:1|max:99999999.99',
            'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'note' => 'nullable|string|max:1000',
        ]);

        $proofPath = null;
        if ($request->hasFile('proof_file')) {
            $invoice->loadMissing('customer');
            $proofPath = FileUploadService::uploadPaymentProof(
                $request->file('proof_file'),
                $invoice->customer,
                $invoice->invoice_type?->value,
                $validated['payment_date']
            );
        }

        try {
            $payment = app(PaymentService::class)->record($invoice, $validated, $proofPath);
        } catch (\InvalidArgumentException $e) {
            // PaymentObserver::rejectBurstDuplicate() menolak pembayaran kembar
            // dalam jendela 5 menit. Itu kondisi yang memang diantisipasi —
            // klik dobel — jadi tak pantas keluar sebagai 500. Ditolak lagi di
            // sini, tapi sebagai pesan validasi yang bisa dibaca admin.
            // Penolakan LAIN dari observer (mis. nominal ≤ 0) tetap dilempar.
            if (! str_contains($e->getMessage(), 'Duplicate payment detected')) {
                throw $e;
            }

            throw ValidationException::withMessages([
                'amount' => 'Pembayaran dengan tanggal dan nominal yang sama baru saja tercatat untuk tagihan ini.',
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Dua request kembar tiba nyaris bersamaan: yang kalah balapan
            // ditolak indeks unique, bukan menyimpan payment kedua. Pengecekan
            // di awal method tidak cukup — di antara pengecekan dan penyimpanan
            // masih ada celah, dan indeks database inilah penjaga terakhirnya.
            $duplikat = $this->existingPaymentFor($request);

            // Tanpa kunci idempotensi tak ada yang bisa ditelusuri — lempar apa
            // adanya. Duplikat pada jalur itu ditahan
            // PaymentObserver::rejectBurstDuplicate() di atas, bukan di sini;
            // `payments_invoice_date_amount_unique` sudah DI-DROP 2026-08-03
            // karena ikut menolak cicilan yang sah.
            if (! $duplikat) {
                throw $e;
            }

            return $request->expectsJson()
                ? response()->json([
                    'success' => true,
                    'message' => "Pembayaran {$duplikat->payment_number} sudah tercatat sebelumnya — tidak diproses ulang.",
                    'already_processed' => true,
                ])
                : redirect()
                    ->route('invoices.show', $invoice->id)
                    ->with('success', "Pembayaran {$duplikat->payment_number} sudah tercatat sebelumnya — tidak diproses ulang.");
        }

        if ($request->expectsJson()) {
            $payment->invoice->refresh();

            return response()->json([
                'success' => true,
                'message' => "Pembayaran {$payment->payment_number} berhasil dicatat.",
                'payment' => [
                    'id' => $payment->id,
                    'payment_number' => $payment->payment_number,
                    'amount' => (float) $payment->amount,
                ],
                'invoice' => [
                    'id' => $payment->invoice->id,
                    'invoice_status' => $payment->invoice->invoice_status,
                    'paid_amount' => (float) $payment->invoice->paid_amount,
                    'remaining_amount' => (float) $payment->invoice->remaining_amount,
                ],
            ]);
        }

        return redirect()
            ->route('invoices.show', $invoice->id)
            ->with('success', "Pembayaran {$payment->payment_number} berhasil dicatat.");
    }

    /**
     * Pembayaran yang sudah pernah tersimpan dengan kunci idempotensi ini.
     *
     * Tanpa kunci (pemanggil lama / JSON tanpa form) selalu null — perilakunya
     * persis seperti sebelum penahan ini ada.
     */
    private function existingPaymentFor(Request $request): ?Payment
    {
        $key = trim((string) $request->input('idempotency_key'));

        if ($key === '') {
            return null;
        }

        return Payment::where('idempotency_key', $key)->first();
    }

    /**
     * Void/reject payment yang salah input. Invoice ikut terkoreksi otomatis
     * di transaksi yang sama lewat Invoice::recalculateFromPayments() —
     * `Payment.php` sudah lama mengantisipasi `payment_status → DITOLAK`
     * (audit action 'cancel'), tapi tanpa ini invoice akan tetap menghitung
     * payment yang sudah ditolak sebagai lunas. Jebakan laten yang sekarang
     * ditutup (docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md
     * §A-6, §A-7 #7).
     */
    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless(
            Payment::query()->applyUserScope()->whereKey($payment->id)->exists(),
            403,
            'Anda tidak memiliki akses ke pembayaran POP ini.'
        );

        if ($payment->payment_status === PaymentStatus::DITOLAK) {
            return redirect()
                ->route('payments.show', $payment->id)
                ->withErrors(['reject_reason' => 'Pembayaran ini sudah ditolak sebelumnya.']);
        }

        // Batas koreksi = titik verifikasi setoran. Sebelum itu payment masih
        // sekadar catatan; sesudahnya uang fisiknya sudah dihitung dan
        // diserahterimakan dua pihak, dan setoran terverifikasi adalah dokumen
        // yang mereka sepakati. Menolak payment di dalamnya = mengubah dokumen
        // itu belakangan, yaitu persis cara jejak uang bisa dihapus diam-diam.
        //
        // Koreksi yang benar: pembayaran pembalik (bertanda + beralasan) yang
        // menerbitkan Kurang/Lebih Setor baru — setoran lama tak disentuh.
        $deposit = $payment->collectorDeposit;
        if ($deposit && $deposit->status->isVerified()) {
            return redirect()
                ->route('payments.show', $payment->id)
                ->withErrors([
                    'reject_reason' => "Pembayaran ini sudah masuk setoran {$deposit->deposit_number} yang berstatus {$deposit->status->label()}. Setoran terverifikasi tidak boleh diubah — buat pembayaran koreksi, jangan tolak yang ini.",
                ]);
        }

        $validated = $request->validate([
            'reject_reason' => ReasonValidationRule::required(1000),
        ]);

        DB::transaction(function () use ($payment, $validated) {
            $payment->update([
                'payment_status' => PaymentStatus::DITOLAK->value,
                'reject_reason' => $validated['reject_reason'],
                'rejected_at' => now(),
                'rejected_by' => auth()->id(),
            ]);

            $lockedInvoice = Invoice::query()
                ->whereKey($payment->invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedInvoice->recalculateFromPayments();

            // Kredit saldo pelanggan yang lahir dari lebih bayar payment ini
            // ikut dibalik — kalau tidak, pelanggan tetap punya saldo aktif
            // dari uang yang ternyata tak pernah sah tercatat.
            app(CustomerBalanceService::class)->reverseCreditForPayment($payment);
        });

        // Notif ke yang mencatat setoran (kolektor lapangan kalau ada, kalau
        // gak staf yang input langsung) — sebelumnya penolakan gak ngasih tau
        // siapa pun, kolektor baru sadar pas ngecek worklist-nya sendiri
        // (docs/plan/analisa-status-implementasi-notifikasi.md §5).
        $this->notifyPaymentRecorderIfDifferentActor($payment, auth()->user(), $validated['reject_reason']);

        // Kalau yang ditolak adalah uang tagihan kolektor, SALDONYA TURUN.
        // Notifikasi mendarat di lonceng, tapi Worklist yang sedang terbuka
        // tetap memajang saldo lama — bentuknya persis "angka berubah tanpa
        // penjelasan" yang modul ini justru ada untuk mencegah.
        if ($payment->collected_by && $payment->pop_id) {
            $kolektor = User::find($payment->collected_by);

            if ($kolektor) {
                CollectorActivityUpdated::dispatch(
                    $kolektor,
                    (int) $payment->pop_id,
                    'pembayaran_ditolak',
                    1,
                    (float) $payment->amount,
                    $payment->payment_number,
                );
            }
        }

        return redirect()
            ->route('payments.show', $payment->id)
            ->with('success', "Pembayaran {$payment->payment_number} berhasil ditolak/dibatalkan.");
    }

    private function notifyPaymentRecorderIfDifferentActor(Payment $payment, User $actor, string $reason): void
    {
        $recorderId = $payment->collected_by ?? $payment->received_by;
        $recorder = $recorderId ? User::find($recorderId) : null;

        if (! $recorder || $recorder->id === $actor->id) {
            return;
        }

        $recorder->notify(new AppNotification(
            title: 'Pembayaran Ditolak: '.$payment->payment_number,
            message: "Pembayaran {$payment->payment_number} yang Anda catat ditolak {$actor->name}. Alasan: {$reason}",
            actionUrl: route('payments.show', $payment->id),
            type: NotificationType::ERROR
        ));
    }

    private function authorizeInvoiceAccess(Invoice $invoice): void
    {
        abort_unless(
            Invoice::query()->applyUserScope()->whereKey($invoice->id)->exists(),
            403,
            'Anda tidak memiliki akses ke tagihan POP ini.'
        );
    }
}
