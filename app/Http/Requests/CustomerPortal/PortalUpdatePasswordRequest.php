<?php

namespace App\Http\Requests\CustomerPortal;

use App\Models\Customer;
use App\Rules\StrongPortalPassword;
use Illuminate\Foundation\Http\FormRequest;

class PortalUpdatePasswordRequest extends FormRequest
{
    /**
     * Selalu true — otorisasi jalur portal sudah selesai di middleware
     * `portal_client` + `portal_token`, bukan lewat `auth()->user()` staf.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // FormRequest MEWARISI Illuminate\Http\Request — attributes yang
        // ditaruh EnsurePortalCustomerToken (portal_customer_id) sudah
        // tersedia di sini tanpa perlu di-passing ulang.
        $customer = Customer::with('portalAccount')->find($this->attributes->get('portal_customer_id'));

        return [
            'current_password' => ['required', 'string'],
            'new_password' => [
                'required', 'string', 'min:10',
                new StrongPortalPassword(
                    $customer?->portalAccount?->login_id,
                    $customer?->primary_phone,
                    $customer?->alternative_phone,
                ),
            ],
        ];
    }
}
