<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Halaman List Pelanggan Gagal — route & permission (customers.failed.view)
 * sendiri, terpisah dari List Data Pelanggan biasa (CustomerController::index()).
 * Lihat catatan CustomerTerminatedController — pola sama persis.
 */
class CustomerFailedController extends CustomerController
{
    public function index(Request $request)
    {
        return $this->renderCustomerList($request, 'failed');
    }
}
