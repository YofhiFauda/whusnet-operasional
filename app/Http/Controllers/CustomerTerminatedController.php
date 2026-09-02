<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersCustomerList;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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
}
