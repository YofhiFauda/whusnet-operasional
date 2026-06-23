<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:150',
            'identity_number' => 'required|string|size:16|regex:/^[0-9]+$/',
            'gender' => 'required|string|in:Laki-laki,Perempuan',
            'primary_phone' => ['required', 'string', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,11}$/'],
            'alternative_phone' => ['nullable', 'string', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,11}$/'],
            'email' => 'nullable|email|max:100',
            'registration_date' => 'required|date',
            'pop_id' => 'required|exists:pops,id',
            'distribution_id' => 'nullable|exists:distributions,id',
            'address' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'city_id' => 'required|exists:cities,id',
            'district_id' => 'required|exists:districts,id',
            'village_id' => 'required|exists:villages,id',
            'internet_package_id' => 'required|exists:internet_packages,id',
            'contract_period_months' => 'required|integer|min:1',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_percent' => 'nullable|numeric|between:0,100',
            'other_fee' => 'nullable|numeric|min:0',
            
            // Referrals
            'sales_code' => 'nullable|string|max:30',
            'agent_code' => 'nullable|string|max:30',
            'referral_customer_code' => 'nullable|string|max:30',
            
            // Technical specs
            'ont_sn' => 'nullable|string|max:100',
            'ip_address' => 'nullable|string|max:45',
            'odp_code' => 'nullable|string|max:50',
            'olt_code' => 'nullable|string|max:50',
            'vlan_id' => 'nullable|string|max:20',
            
            // Status is auto-assigned to registered in controller for creation
            'status' => 'nullable|string|max:50',
            
            // Documents
            'foto_ktp' => 'required|file|image|mimes:jpeg,png,jpg|max:2048',
            'foto_rumah' => 'nullable|file|image|mimes:jpeg,png,jpg|max:2048',
            'foto_kontrak' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'identity_number.required' => 'NIK wajib diisi.',
            'identity_number.size' => 'NIK harus berjumlah 16 digit angka.',
            'identity_number.regex' => 'NIK hanya boleh berisi angka.',
            'primary_phone.regex' => 'Format nomor HP tidak valid (harus format Indonesia, misal: 0812...).',
            'foto_ktp.required' => 'Foto KTP wajib dilampirkan.',
            'foto_ktp.image' => 'File KTP harus berupa gambar.',
            'foto_ktp.mimes' => 'Format gambar KTP harus jpeg, png, atau jpg.',
            'address.required' => 'Alamat lengkap instalasi wajib diisi.',
            'city_id.required' => 'Kota wajib dipilih.',
            'district_id.required' => 'Kecamatan wajib dipilih.',
            'village_id.required' => 'Desa/Kelurahan wajib dipilih.',
            'internet_package_id.required' => 'Paket internet wajib dipilih.',
        ];
    }
}
