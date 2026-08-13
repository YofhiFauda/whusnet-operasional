<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersCustomerList;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Halaman List Pelanggan Gagal — route, permission (customers.failed.view), dan
 * view (customers/failed.blade.php) sendiri. Lihat catatan
 * CustomerTerminatedController — pola sama persis.
 */
class CustomerFailedController extends Controller
{
    use RendersCustomerList;

    public function index(Request $request): View
    {
        return $this->renderCustomerList($request, 'failed', 'customers.failed');
    }
}
