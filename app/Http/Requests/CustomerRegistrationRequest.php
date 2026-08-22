<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Support\RupiahInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Skip Survey (Registrasi) butuh permission sempit sendiri —
        // customers.create biasa gak cukup. Dicek di sini (bukan dibiarkan
        // required_if jalan diam-diam) supaya klien yang maksa kirim
        // skip_survey=1 tanpa hak dapat 403 jelas, bukan validasi field
        // survey yang membingungkan.
        if ($this->boolean('skip_survey') && ! auth()->user()?->hasPermission('customers.registration.skip_survey')) {
            return false;
        }

        return true;
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('identity_number')) {
            $this->merge([
                'identity_number' => preg_replace('/[^0-9]/', '', (string) $this->identity_number),
            ]);
        }

        // Kolom rupiah diketik berformat ribuan (`10.000`). Tanpa normalisasi,
        // titiknya lolos `numeric` sebagai desimal Inggris — diskon 10 ribu
        // tersimpan 10 rupiah dan langganan pelanggan ini salah tagih terus.
        // `tax_percent` TIDAK ikut: itu persen, bukan rupiah.
        $this->merge(RupiahInput::parseKeys(
            $this->only(['discount_amount', 'other_fee']),
            'discount_amount',
            'other_fee',
        ));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:150',
            'identity_number' => 'required|string|size:16|regex:/^[0-9]+$/',
            'gender' => ['required', 'string', Rule::enum(Gender::class)],
            'primary_phone' => ['required', 'string', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,11}$/'],
            'alternative_phone' => ['nullable', 'string', 'regex:/^(\+62|62|0)8[1-9][0-9]{6,11}$/'],
            'email' => 'nullable|email|max:100',
            'registration_date' => 'required|date',
            'pop_id' => 'required|exists:pops,id',
            'distribution_id' => 'nullable|exists:distributions,id',
            'address' => 'required|string',
            // Wajib begitu Skip Survey aktif — titik koordinat gak boleh
            // kosong kalau gak ada teknisi yang bakal survey lapangan.
            'latitude' => ['nullable', 'required_if:skip_survey,1', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_if:skip_survey,1', 'numeric', 'between:-180,180'],
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
            'foto_rumah' => ['nullable', 'required_if:skip_survey,1', 'file', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'foto_kontrak' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',

            // Skip Survey — Sales input data survey langsung saat registrasi
            // (lihat ActionCode::SKIP_SURVEY). Field-field di bawah cuma
            // wajib kalau skip_survey aktif; kalau tidak, sama sekali diabaikan.
            'skip_survey' => 'nullable|boolean',
            'nearest_odp' => ['nullable', 'required_if:skip_survey,1', 'string', 'max:255'],
            'cable_estimation_meter' => ['nullable', 'required_if:skip_survey,1', 'integer', 'min:0'],
            'difficulty_level' => ['nullable', 'required_if:skip_survey,1', 'in:MUDAH,SEDANG,SULIT'],
            'survey_photo' => ['nullable', 'required_if:skip_survey,1', 'file', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            // Opsional (sama seperti Lapor Survey teknisi) — diisi hanya kalau
            // pelanggan minta dipasang di tanggal tertentu. after_or_equal:today:
            // tanggal lampau gak ada artinya dan bikin task lahir langsung
            // TERLAMBAT di papan FOP.
            'requested_installation_date' => 'nullable|date|after_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'identity_number.required' => 'NIK wajib diisi.',
            'identity_number.size' => 'NIK harus berjumlah 16 digit angka.',
            'identity_number.digits' => 'NIK harus berjumlah 16 digit angka.',
            'identity_number.regex' => 'NIK hanya boleh berisi angka.',
            'primary_phone.regex' => 'Format nomor HP tidak valid (harus format Indonesia, misal: 0812...).',
            'address.required' => 'Alamat lengkap instalasi wajib diisi.',
            'city_id.required' => 'Kota wajib dipilih.',
            'district_id.required' => 'Kecamatan wajib dipilih.',
            'village_id.required' => 'Desa/Kelurahan wajib dipilih.',
            'internet_package_id.required' => 'Paket internet wajib dipilih.',
            'latitude.required_if' => 'Titik koordinat (Latitude) wajib diisi saat Skip Survey aktif.',
            'longitude.required_if' => 'Titik koordinat (Longitude) wajib diisi saat Skip Survey aktif.',
            'foto_rumah.required_if' => 'Foto Rumah wajib diunggah saat Skip Survey aktif.',
            'nearest_odp.required_if' => 'ODP Terdekat wajib diisi saat Skip Survey aktif.',
            'cable_estimation_meter.required_if' => 'Estimasi Kabel wajib diisi saat Skip Survey aktif.',
            'difficulty_level.required_if' => 'Tingkat Kesulitan wajib dipilih saat Skip Survey aktif.',
            'survey_photo.required_if' => 'Foto ODP wajib diunggah saat Skip Survey aktif.',
            'requested_installation_date.after_or_equal' => 'Tanggal request pemasangan tidak boleh sebelum hari ini.',
        ];
    }
}
