<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerPortal\Concerns\ScopedToAuthenticatedCustomer;
use App\Http\Resources\CustomerPortal\PaymentReceiptResource;
use App\Http\Resources\CustomerPortal\PaymentResource;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
}
