<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerPortal\Concerns\ScopedToAuthenticatedCustomer;
use App\Http\Resources\CustomerPortal\PaymentReceiptResource;
use App\Http\Resources\CustomerPortal\PaymentResource;
use App\Models\Payment;
use App\Services\Receipts\ReceiptPresenter;
use Barryvdh\DomPDF\Facade\Pdf;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * `GET /me/payments`, `/me/payments/{payment_number}/receipt`
 * (docs/api/api-portal-pelanggan/, Fase 3).
 */
#[Group('Portal Pelanggan', 'Endpoint bagi aplikasi Portal Pelanggan (domain terpisah, tanpa kredensial DB operasional) untuk kredensial, tagihan, pembayaran, kwitansi, saldo, dan riwayat ticketing pelanggan. Semua permintaan WAJIB header `X-Portal-Client` (client secret statis); endpoint `/me/*` tambah `Authorization: Bearer <access_token>`.')]
class PortalPaymentController extends Controller
{
    use ScopedToAuthenticatedCustomer;

    /**
     * Riwayat pembayaran
     *
     * `overpay_amount` dan `billing_period` selalu ikut keluar. Pembayaran
     * ditolak tetap tampil (tanpa reject_reason) — uang yang sudah
     * diserahkan tidak boleh lenyap dari layar tanpa penjelasan.
     */
    #[QueryParameter('status', description: 'Filter payment_status.', example: 'valid')]
    #[QueryParameter('period', description: 'Filter billing_period, format Y-m.', example: '2026-08')]
    #[Response(200, description: 'Riwayat pembayaran berhasil diambil.', examples: [[
        'data' => [[
            'payment_number' => 'PAY-202608-0042',
            'payment_date' => '2026-08-10T00:00:00+07:00',
            'billing_period' => '2026-08',
            'invoice_number' => 'INV-2026-08-000123',
            'amount' => '150000.00',
            'overpay_amount' => '0.00',
            'payment_method' => 'cash',
            'payment_status' => ['value' => 'valid', 'label' => 'Valid'],
            'has_receipt' => true,
        ]],
    ]])]
    #[Response(401, description: 'Access token tidak disertakan atau tidak valid.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->paymentsQuery($request)->with('invoice:id,invoice_number');

        $status = $request->string('status')->toString();
        if ($status !== '' && in_array($status, array_column(PaymentStatus::cases(), 'value'), true)) {
            $query->where('payment_status', $status);
        }

        $period = $request->string('period')->toString();
        if ($period !== '') {
            $query->where('billing_period', $period);
        }

        $payments = $query->latest('payment_date')->latest('id')->paginate(10)->withQueryString();

        return PaymentResource::collection($payments);
    }

    /**
     * Isi kwitansi
     *
     * Turunan `ReceiptPresenter::for()` (sama dipakai cetak internal),
     * dipangkas: `penerima`/`penagih`/`catatan`/`dicetak` (data pegawai)
     * dibuang, ditambah `dibayar_raw` + `tanggal_bayar_iso`.
     */
    #[Response(200, description: 'Kwitansi berhasil diambil.')]
    #[Response(401, description: 'Access token tidak disertakan atau tidak valid.')]
    #[Response(404, description: 'payment_number tidak ditemukan atau milik pelanggan lain — sengaja sama, tidak bisa dibedakan dari luar.')]
    public function receipt(Request $request, string $paymentNumber): PaymentReceiptResource
    {
        // Binding MANUAL di atas query terfilter — BUKAN implicit route-model
        // -binding by id seperti PaymentController::receipt staf (anti-pola
        // "bind dulu cek belakangan", lihat docblock ScopedToAuthenticatedCustomer).
        $payment = $this->paymentsQuery($request)
            ->with(['invoice.internetPackage', 'customer', 'pop'])
            ->where('payment_number', $paymentNumber)
            ->firstOrFail();

        return new PaymentReceiptResource($payment);
    }

    /**
     * Kwitansi (PDF) — Lihat / Unduh
     *
     * Render dompdf dari template `payments.receipt` YANG SAMA dipakai
     * kasir/kolektor internal (`PaymentController::receipt()`) — Portal
     * TIDAK punya template kwitansi sendiri. `$isCustomerCopy=true`
     * menyembunyikan baris "Diterima oleh"/"Catatan" (data internal
     * pegawai, sama alasan `PaymentReceiptResource` membuangnya dari JSON).
     *
     * `?download=1` → `Content-Disposition: attachment` (tombol Unduh, file
     * .pdf beneran). Tanpa itu → `inline` (tombol Lihat, dibuka di tab/
     * viewer PDF browser). Isi PDF-nya identik di dua-duanya — cuma header
     * disposisi yang beda.
     */
    #[Response(200, description: 'Kwitansi PDF berhasil dibuat.')]
    #[Response(401, description: 'Access token tidak disertakan atau tidak valid.')]
    #[Response(404, description: 'payment_number tidak ditemukan atau milik pelanggan lain — sengaja sama, tidak bisa dibedakan dari luar.')]
    public function receiptPdf(Request $request, string $paymentNumber): HttpResponse
    {
        $viewData = $this->receiptViewData($request, $paymentNumber);

        // `isPdf` → blade render layout invoice A4 TERPISAH (`.a4`, lihat
        // docblock $isPdf di payments.receipt.blade.php), BUKAN struk
        // thermal 80mm yang dibesarin paksa (tiga percobaan sebelumnya
        // ke arah situ semua gagal/rapuh). Kertas A4 standar biar gak
        // perlu itung px manual kayak sebelumnya. Cuma buat render dompdf
        // ini — TIDAK ikut ke `receiptView()` (iframe HTML modal, tetap
        // struk thermal ukuran natural).
        $pdf = Pdf::loadView('payments.receipt', [...$viewData, 'isPdf' => true])
            ->setPaper('a4');

        $filename = "kwitansi-{$viewData['payment']->payment_number}.pdf";

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    /**
     * Kwitansi (HTML) — buat modal "Lihat Kwitansi" di Portal
     *
     * Blade `payments.receipt` yang SAMA (lewat browser, bukan dompdf) —
     * dipilih user eksplisit di atas render PDF-dalam-tab: dompdf cuma
     * APROKSIMASI CSS (flexbox/font web-nya gak 100% identik), sedangkan
     * HTML asli dirender browser pelanggan sendiri, jadi PIXEL-IDENTIK
     * sama yang staf lihat buka `/payments/{id}/kwitansi` di Operasional.
     * Portal nge-embed ini lewat `<iframe>` di dalam modal (lihat proxy
     * `api/payments/[paymentNumber]/receipt-view/route.ts`), TIDAK pernah
     * dipakai standalone/dinavigasi langsung dari luar.
     */
    #[Response(200, description: 'Kwitansi HTML berhasil dirender.')]
    #[Response(401, description: 'Access token tidak disertakan atau tidak valid.')]
    #[Response(404, description: 'payment_number tidak ditemukan atau milik pelanggan lain — sengaja sama, tidak bisa dibedakan dari luar.')]
    public function receiptView(Request $request, string $paymentNumber): View
    {
        return view('payments.receipt', $this->receiptViewData($request, $paymentNumber));
    }

    /**
     * @return array{payment: Payment, installmentContext: array<string, mixed>|null, kwitansi: array<string, mixed>, isCustomerCopy: bool}
     */
    private function receiptViewData(Request $request, string $paymentNumber): array
    {
        // Binding MANUAL di atas query terfilter — BUKAN implicit route-model
        // -binding by id seperti PaymentController::receipt staf (anti-pola
        // "bind dulu cek belakangan", lihat docblock ScopedToAuthenticatedCustomer).
        $payment = $this->paymentsQuery($request)
            ->with(['invoice.internetPackage', 'customer', 'pop'])
            ->where('payment_number', $paymentNumber)
            ->firstOrFail();

        return [
            'payment' => $payment,
            'installmentContext' => $payment->installmentContext(),
            'kwitansi' => app(ReceiptPresenter::class)->for($payment),
            // Sembunyikan toolbar + baris "Diterima oleh"/"Catatan" (data
            // internal pegawai) — dipakai HTML modal maupun PDF, lihat
            // docblock $isCustomerCopy di payments/receipt.blade.php.
            'isCustomerCopy' => true,
        ];
    }
}
