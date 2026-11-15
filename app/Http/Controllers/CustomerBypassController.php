<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsToCustomer;
use App\Http\Requests\CustomerBypassRequest;
use App\Services\CustomerBypassService;
use App\Services\CustomerRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bypass Pelanggan — `/bypass`. Form registrasi yang sama, tapi pelanggan
 * langsung aktif (lihat CustomerBypassService). Gerbang route:
 * `customers.registration.bypass`.
 */
class CustomerBypassController extends Controller
{
    use RedirectsToCustomer;

    public function create(Request $request, CustomerRegistrationService $registration): View
    {
        return view('customers.create', array_merge(
            $registration->formData($request->user()),
            ['bypassMode' => true],
        ));
    }

    public function store(CustomerBypassRequest $request, CustomerBypassService $bypass): RedirectResponse
    {
        $customer = $bypass->register($request->validated(), $request->user(), [
            'foto_kontrak' => $request->file('foto_kontrak'),
            'fab_document' => $request->file('fab_document'),
        ]);

        $cidLabel = $customer->cid ?? $customer->customer_code;

        return $this->redirectToCustomer($customer, 'customers.bypass.create')
            ->with('success', "Pelanggan {$customer->full_name} langsung AKTIF lewat Bypass dengan CID {$cidLabel}. Lengkapi data lainnya lewat Edit.");
    }
}
