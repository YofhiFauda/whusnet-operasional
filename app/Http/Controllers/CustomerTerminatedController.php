<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersCustomerList;
use App\Models\Customer;
use App\Services\InvoiceWriteOffService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Halaman List Pelanggan Putus — route, permission (customers.terminated.view),
 * DAN view (customers/terminated.blade.php) sendiri, terpisah dari List Data
 * Pelanggan biasa (CustomerController::index()).
 *
 * Pakai trait RendersCustomerList buat query/filter/pagination — bukan extend
 * CustomerController seperti dulu: extend bikin halaman daftar ini mewarisi
 * seluruh method tulis pelanggan (store/update/destroy/import) yang bukan
 * urusannya.
 */
class CustomerTerminatedController extends Controller
{
    use RendersCustomerList;

    public function index(Request $request): View
    {
        return $this->renderCustomerList($request, 'terminated', 'customers.terminated');
    }

    /**
     * "Kembalikan Semua" (ADHOC-105): seluruh invoice tak tertagih pelanggan
     * dikembalikan ke tab Tagihan dalam satu transaksi (atomik — satu gagal
     * berarti semuanya batal). Permission `invoices.approve` dipasang di route,
     * sama dengan tombol Kembalikan per invoice.
     */
    public function reverseAllWriteOffs(Customer $customer, InvoiceWriteOffService $service): RedirectResponse
    {
        abort_unless(
            Customer::query()->applyUserScope()->whereKey($customer->id)->exists(),
            403,
            'Anda tidak memiliki akses ke pelanggan di POP ini.'
        );

        try {
            $reversed = $service->reverseAllForCustomer($customer, auth()->user());
        } catch (ValidationException $e) {
            return redirect()->route('customers.terminated')->with('error', (string) collect($e->errors())->flatten()->first());
        }

        return redirect()
            ->route('customers.terminated')
            ->with('success', "{$reversed->count()} tagihan {$customer->full_name} dikembalikan ke tab Tagihan.");
    }
}
