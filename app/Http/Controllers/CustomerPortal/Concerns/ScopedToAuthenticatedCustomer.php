<?php

namespace App\Http\Controllers\CustomerPortal\Concerns;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

/**
 * Satu-satunya titik yang boleh resolve `customer_id` dari token
 * (docs/api/api-portal-pelanggan/, Fase 3) — `customer_id` HANYA pernah
 * datang dari `$request->attributes` yang ditaruh `EnsurePortalCustomerToken`,
 * TIDAK PERNAH dari query/body/header. Ini aturan tunggal yang menutup IDOR
 * lintas pelanggan (business-logic.md §Kepemilikan data).
 *
 * `invoicesQuery()`/`paymentsQuery()` bukan cuma kenyamanan — mereka
 * memaksa pola "query dibuka SUDAH terfilter, baru cari nomor dokumen di
 * dalamnya" (flowchart.md §2: "gagal aman", bukan "gagal terbuka"). Anti-pola
 * yang HARUS dihindari (ditemukan di PaymentController::receipt staf):
 * ambil model dulu by id lalu `abort_unless()` belakangan — 403 yang muncul
 * dari pola itu justru mengonfirmasi nomor dokumen itu ADA, padahal
 * requirement portal jawabannya harus 404 baik nomor tidak ada maupun milik
 * orang lain (tidak bisa dibedakan dari luar).
 */
trait ScopedToAuthenticatedCustomer
{
    protected function customer(Request $request): Customer
    {
        return Customer::findOrFail($request->attributes->get('portal_customer_id'));
    }

    /**
     * @return HasMany<Invoice, Customer>
     */
    protected function invoicesQuery(Request $request): HasMany
    {
        return $this->customer($request)->invoices();
    }

    /**
     * @return HasMany<Payment, Customer>
     */
    protected function paymentsQuery(Request $request): HasMany
    {
        return $this->customer($request)->payments();
    }

    /**
     * @return HasMany<Ticket, Customer>
     */
    protected function ticketsQuery(Request $request): HasMany
    {
        return $this->customer($request)->tickets();
    }
}
