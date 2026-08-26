<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerPortal\Concerns\ScopedToAuthenticatedCustomer;
use App\Http\Resources\CustomerPortal\InvoiceDetailResource;
use App\Http\Resources\CustomerPortal\InvoiceResource;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * `GET /me/invoices`, `/me/invoices/{invoice_number}`
 * (docs/api/api-portal-pelanggan/, Fase 3). Semua route SUDAH di belakang
 * `portal_client` + `portal_token` — lihat routes/api.php.
 */
#[Group('Portal Pelanggan', 'Endpoint bagi aplikasi Portal Pelanggan (domain terpisah, tanpa kredensial DB operasional) untuk kredensial, tagihan, pembayaran, kwitansi, saldo, dan riwayat ticketing pelanggan. Semua permintaan WAJIB header `X-Portal-Client` (client secret statis); endpoint `/me/*` tambah `Authorization: Bearer <access_token>`.')]
class PortalInvoiceController extends Controller
{
    use ScopedToAuthenticatedCustomer;

    /**
     * Daftar tagihan
     *
     * `paid_amount`/`remaining_amount`/`invoice_status` dibaca apa adanya
     * dari kolom, tidak dihitung ulang — `Invoice::recalculateFromPayments()`
     * satu-satunya sumber kebenaran.
     */
    #[QueryParameter('status', description: 'Filter invoice_status.', example: 'lunas')]
    #[QueryParameter('period', description: 'Filter billing_period, format Y-m.', example: '2026-08')]
    #[Response(200, description: 'Daftar tagihan berhasil diambil.', examples: [[
        'data' => [[
            'invoice_number' => 'INV-2026-08-000123',
            'invoice_type' => ['value' => 'bulanan', 'label' => 'Tagihan Bulanan Rutin'],
            'billing_period' => '2026-08',
            'issue_date' => '2026-08-01T00:00:00+07:00',
            'due_date' => '2026-08-15T00:00:00+07:00',
            'total_amount' => '150000.00',
            'paid_amount' => '150000.00',
            'remaining_amount' => '0.00',
            'invoice_status' => ['value' => 'lunas', 'label' => 'Lunas'],
        ]],
    ]])]
    #[Response(401, description: 'Access token tidak disertakan atau tidak valid.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->invoicesQuery($request);

        $status = $request->string('status')->toString();
        if ($status !== '' && in_array($status, array_column(InvoiceStatus::cases(), 'value'), true)) {
            $query->where('invoice_status', $status);
        }

        $period = $request->string('period')->toString();
        if ($period !== '') {
            $query->where('billing_period', $period);
        }

        $invoices = $query->latest('issue_date')->latest('id')->paginate(10)->withQueryString();

        return InvoiceResource::collection($invoices);
    }

    /**
     * Detail tagihan
     *
     * Bentuk sama seperti item daftar, plus daftar pembayaran yang
     * menempel.
     */
    #[Response(200, description: 'Detail tagihan berhasil diambil.')]
    #[Response(401, description: 'Access token tidak disertakan atau tidak valid.')]
    #[Response(404, description: 'invoice_number tidak ditemukan atau milik pelanggan lain — sengaja sama, tidak bisa dibedakan dari luar.')]
    public function show(Request $request, string $invoiceNumber): InvoiceDetailResource
    {
        // Query dibuka SUDAH terfilter customer, baru dicari nomornya di
        // dalamnya — firstOrFail() → ModelNotFoundException → 404 otomatis
        // baik nomor tidak ada maupun milik pelanggan lain, tidak bisa
        // dibedakan dari luar (flowchart.md §2).
        $invoice = $this->invoicesQuery($request)
            ->with('payments')
            ->where('invoice_number', $invoiceNumber)
            ->firstOrFail();

        return new InvoiceDetailResource($invoice);
    }
}
