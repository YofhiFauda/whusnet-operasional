<?php

namespace App\Http\Requests\CustomerPortal;

use Illuminate\Foundation\Http\FormRequest;

class PortalLoginRequest extends FormRequest
{
    /**
     * Selalu true — otorisasi jalur portal SUDAH selesai di middleware
     * `portal_client` (client secret), bukan lewat `auth()->user()` staf.
     * Beda dari CustomerRegistrationRequest yang buat staf dan memang perlu
     * cek permission di sini.
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
        return [
            'login_id' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }
}
