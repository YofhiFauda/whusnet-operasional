<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Worklist read-only kolektor — "siapa yang harus saya datangi hari ini".
 * Sengaja MINIMAL: nol tombol input, nol aksi. Kolektor tak berwenang
 * input pembayaran (§B-1) — dia cuma perlu tahu daftar & sisa tagihan.
 *
 * docs/plan/analisa-billing-tagihan-pembayaran-kolektor.md §B-8 no. 5.
 */
class CollectorWorklistController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::query()
            ->where('collector_id', auth()->id())
            ->with(['invoices' => function ($q) {
                $q->whereIn('invoice_status', [InvoiceStatus::BELUM_DIBAYAR->value, InvoiceStatus::SEBAGIAN->value])
                    ->orderBy('due_date');
            }])
            ->whereHas('invoices', function ($q) {
                $q->whereIn('invoice_status', [InvoiceStatus::BELUM_DIBAYAR->value, InvoiceStatus::SEBAGIAN->value]);
            })
            ->orderBy('full_name')
            ->paginate(50)
            ->withQueryString();

        return view('collector-worklist.index', compact('customers'));
    }
}
