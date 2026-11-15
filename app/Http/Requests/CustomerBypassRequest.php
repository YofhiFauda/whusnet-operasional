<?php

namespace App\Http\Requests;

use App\Models\Pop;
use Illuminate\Validation\Rule;

/**
 * Form Bypass Pelanggan — aturan field SAMA dengan Registrasi biasa (yang
 * wajib di sana, wajib di sini; sisanya dilengkapi belakangan sebagai "data
 * belum lengkap"), minus semua yang menyangkut survey/jaringan.
 */
class CustomerBypassRequest extends CustomerRegistrationRequest
{
    /**
     * Ganti total gerbang induk (yang cuma memeriksa skip_survey): bypass
     * butuh permission sempitnya sendiri. Middleware route juga menjaga,
     * ini lapis kedua kalau request dipakai dari jalur lain.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('customers.registration.bypass');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        // Survey & Skip Survey gak ada artinya buat pelanggan yang sudah
        // terpasang — dicabut dari rules supaya kalaupun dikirim, nilainya
        // tidak ikut ke validated() (diabaikan, bukan disimpan). Foto Rumah
        // cuma ada di blok Skip Survey. `status` dicabut supaya klien tidak
        // bisa menyetir status — status bypass selalu ACTIVE dari server.
        // `distribution_id` dicabut: keputusan user, Mini POP & Distribusi
        // default kosong, diisi belakangan lewat Edit.
        unset(
            $rules['skip_survey'],
            $rules['nearest_odp'],
            $rules['cable_estimation_meter'],
            $rules['difficulty_level'],
            $rules['survey_photo'],
            $rules['requested_installation_date'],
            $rules['foto_rumah'],
            $rules['status'],
            $rules['distribution_id'],
        );

        // Koordinat di induk `required_if:skip_survey,1` — tanpa skip_survey
        // tetap nullable, cukup buang aturan required_if-nya biar jelas.
        $rules['latitude'] = ['nullable', 'numeric', 'between:-90,90'];
        $rules['longitude'] = ['nullable', 'numeric', 'between:-180,180'];

        // Di Registrasi biasa tanggal ini cuma tanggal daftar (ditimpa
        // finalVerify saat aktivasi). Di Bypass dia LANGSUNG jadi
        // activation_date — tanggal masa depan (salah ketik) bikin
        // GenerateMonthlyInvoicesCommand menagih bulan-bulan sebelum
        // pelanggan aktif, karena yang dilewati cuma bulan aktivasi.
        $rules['registration_date'] = ['required', 'date', 'before_or_equal:today'];

        // Wajib dalam POP scope actor. Dropdown sudah difilter Pop::forUser(),
        // tapi request bisa dipalsukan — tanpa ini CS cabang A bisa
        // mengaktifkan pelanggan di cabang B (kebocoran lintas cabang).
        $rules['pop_id'] = [
            'required',
            Rule::in(Pop::forUser($this->user())->where('type', 'cabang')->pluck('id')->all()),
        ];

        return $rules;
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'pop_id.in' => 'POP yang dipilih di luar cakupan akses Anda.',
            'registration_date.required' => 'Tanggal aktivasi wajib diisi.',
            'registration_date.before_or_equal' => 'Tanggal aktivasi tidak boleh melewati hari ini.',
        ]);
    }
}
