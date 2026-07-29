<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Halaman List Pelanggan Putus — route & permission (customers.terminated.view)
 * sendiri, terpisah dari List Data Pelanggan biasa (CustomerController::index()).
 * Extend CustomerController buat pakai ulang query builder+enrichment
 * (renderCustomerList()) tanpa duplikasi logic filter/pagination/wilayah yang
 * kompleks — bukan berarti controller ini "bagian dari" CustomerController,
 * cuma reuse method protected.
 */
class CustomerTerminatedController extends CustomerController
{
    public function index(Request $request)
    {
        return $this->renderCustomerList($request, 'terminated');
    }
}
