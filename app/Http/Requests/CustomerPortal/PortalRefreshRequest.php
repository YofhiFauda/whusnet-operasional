<?php

namespace App\Http\Requests\CustomerPortal;

use Illuminate\Foundation\Http\FormRequest;

class PortalRefreshRequest extends FormRequest
{
    /**
     * Selalu true — endpoint ini justru TIDAK melewati middleware
     * `portal_token` (refresh token bukan access token, diverifikasi manual
     * di controller). Otorisasi kepemilikan token terjadi di situ, bukan di
     * FormRequest.
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
            'refresh_token' => ['required', 'string'],
        ];
    }
}
