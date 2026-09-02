<?php

namespace App\Http\Requests\CustomerPortal;

use App\Models\CustomerPortalAccount;
use App\Rules\StrongPortalPassword;
use Illuminate\Foundation\Http\FormRequest;

class PortalClaimRequest extends FormRequest
{
    /**
     * Selalu true — otorisasi jalur portal SUDAH selesai di middleware
     * `portal_client` (client secret). Belum ada identitas pelanggan
     * ter-autentikasi di titik ini — itu justru tujuan endpoint ini.
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
        // Belum ada `portal_customer_id` (beda dari PortalUpdatePasswordRequest)
        // — pelanggan belum diautentikasi, identitasnya justru mau
        // ditetapkan lewat login_id yang dikirim di body ini sendiri.
        $customer = CustomerPortalAccount::where('login_id', $this->input('login_id'))->first()?->customer;

        return [
            'login_id' => ['required', 'string'],
            // Format sama seperti gerbang PIN publik (§6.5.4) — digits:6,
            // BUKAN divalidasi cocok/tidak di sini (itu business logic Service).
            'pin' => ['required', 'digits:6'],
            'new_password' => [
                'required', 'string', 'min:10',
                new StrongPortalPassword(
                    $this->input('login_id'),
                    $customer?->primary_phone,
                    $customer?->alternative_phone,
                ),
            ],
        ];
    }
}
