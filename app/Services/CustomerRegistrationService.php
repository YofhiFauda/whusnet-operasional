<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\Agent;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\Person;
use App\Models\Pop;
use App\Models\Role;
use App\Models\User;
use App\Models\Village;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

/**
 * Pembuatan record pelanggan hasil form Registrasi: customer + person +
 * foto/FAB + alamat + layanan (customer_services).
 *
 * Diekstrak dari CustomerController::store() (2026-11-15) karena sekarang ada
 * DUA pintu yang memakai form registrasi yang sama: Registrasi biasa dan
 * Bypass Pelanggan (CustomerBypassService). Kalau masing-masing punya salinan
 * sendiri, rumus `total_monthly_bill` / mapping `service_status` gampang
 * menyimpang — dan itu langsung jadi nominal tagihan bulanan yang salah.
 *
 * Yang SENGAJA tidak di sini: data survey + Task/FopTask Skip Survey (urusan
 * controller registrasi), aktivasi Bypass (urusan CustomerBypassService), dan
 * transaksi DB — pemanggil yang membungkus, supaya langkah lanjutannya ikut
 * atomik bersama record yang dibuat di sini.
 */
class CustomerRegistrationService
{
    /**
     * Turunan status workflow → customer_services.service_status. Dulu
     * disimpan di kolom customers.customer_status (zombie — duplikat `status`
     * yang gampang menyimpang); sumber kebenaran service_status =
     * customer_services.
     */
    private const SERVICE_STATUS_MAP = [
        'active' => 'aktif',
        'suspended' => 'isolir',
        'terminated' => 'berhenti',
        'rejected' => 'nonaktif',
        'waiting_survey' => 'survey',
        'surveyed' => 'survey',
        'waiting_acc' => 'survey',
        'waiting_installation' => 'menunggu_pemasangan',
        'installed' => 'menunggu_pemasangan',
        'registered' => 'calon_pelanggan',
    ];

    /**
     * Data dropdown form registrasi — dipakai halaman Registrasi biasa DAN
     * Bypass Pelanggan (view `customers.create` yang sama).
     *
     * @return array<string, mixed>
     */
    public function formData(User $user): array
    {
        // Fase 5.4 — $districts TIDAK dimuat: form create memakai cascade async
        // (city → /api/cities/{city}/districts → /api/districts/{d}/villages),
        // jadi memuat SELURUH district di sini sia-sia (dead weight yang meledak
        // saat wilayah bertambah). $cities dipertahankan — top-level, kecil,
        // memang dirender.
        // Restriksi Paket per Role (Skema 1) — lihat InternetPackage::scopeAvailableFor().
        $packages = InternetPackage::availableFor($user)->orderBy('name')->get();

        return [
            'packages' => $packages,
            'cities' => City::orderBy('name')->get(),
            'pops' => Pop::forUser($user)->where('type', 'cabang')->get(),
            // Skema 3 (2026-09-12) — dropdown ID Sales/Agent. Berbasis flag
            // `roles.is_package_restricted` (bukan hardcode role code 'sales'):
            // role yang paketnya dibatasi (Skema 1) otomatis JUGA jadi "role
            // penjual" di dropdown ID Sales & Dashboard Omset (Skema 2).
            'salesUsers' => User::whereHas('role', fn ($q) => $q->where('is_package_restricted', true))->orderBy('name')->get(),
            'agents' => $user->hasPermission('agents.view') ? Agent::active()->orderBy('name')->get() : collect(),
            // Nama role yang diatur (2026-09-14) — blade menjelaskan ke actor
            // di luar role itu kenapa ID Sales gak auto-terisi.
            'restrictedRoleNames' => Role::where('is_package_restricted', true)->pluck('name'),
            // Paket berkategori Bisnis butuh FAB — dikirim ke JS supaya blok
            // upload FAB cuma muncul untuk paket relevan; validasi tetap di
            // server (CustomerRegistrationRequest::rules()).
            'fabRequiredPackageIds' => $packages->filter(fn ($package) => stripos((string) $package->category, 'bisnis') !== false)->pluck('id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated  Hasil validasi form registrasi TANPA field survey/skip_survey.
     * @param  array{foto_rumah?: ?UploadedFile, foto_kontrak?: ?UploadedFile, fab_document?: ?UploadedFile}  $files
     */
    public function create(array $validated, User $actor, string $status, array $files = []): Customer
    {
        $validated['created_by'] = $actor->id;
        $validated['updated_by'] = $actor->id;
        $validated['status'] = $status;

        // Skema 3 (2026-09-12) — ID Sales/Agent/Referral. Actor ber-role
        // "restricted paket" TIDAK BOLEH mendaftarkan pelanggan atas nama
        // orang lain (paksa `sales_user_id` = dirinya sendiri, abaikan apa
        // pun yang disubmit klien — cegah spoofing komisi). `agent_id` cuma
        // berlaku dari actor yang punya akses Master Agent — Agent gak pernah
        // login, jadi kalau field ini nyelip dari role lain, abaikan.
        $validated['sales_user_id'] = $actor->role?->is_package_restricted
            ? $actor->id
            : ($validated['sales_user_id'] ?? null);
        if (! $actor->hasPermission('agents.view')) {
            $validated['agent_id'] = null;
        }

        $serviceStatus = self::SERVICE_STATUS_MAP[$status] ?? 'calon_pelanggan';

        // File bukan kolom customers — dicabut supaya gak nebeng ke create().
        unset($validated['foto_rumah'], $validated['foto_kontrak'], $validated['fab_document']);
        $fotoRumah = $files['foto_rumah'] ?? null;
        $fotoKontrak = $files['foto_kontrak'] ?? null;
        $fabDocument = $files['fab_document'] ?? null;

        // jenis_kontrak bukan kolom customers — punya customer_services
        // (contract_type). Cabut sebelum Customer::create() supaya gak
        // diam-diam ke-drop lewat filter Fillable (aman, tapi membingungkan).
        $jenisKontrak = $validated['jenis_kontrak'] ?? null;
        unset($validated['jenis_kontrak']);

        $pop = Pop::findOrFail($validated['pop_id']);
        $validated['customer_code'] = $pop->generateRegistrationNumber();

        // Pendaftaran lewat UI = orang baru → person baru berdiri sendiri
        // (tanpa legacy_key). Cukup jaga invarian "tiap customer punya person".
        $validated['person_id'] = Person::create()->id;

        $customer = Customer::create($validated);

        $updates = [];
        if ($fotoRumah instanceof UploadedFile) {
            $updates['foto_rumah'] = FileUploadService::uploadSurveyPhoto($fotoRumah, $customer, 'house');
        }
        if ($fotoKontrak instanceof UploadedFile) {
            $updates['foto_kontrak'] = FileUploadService::uploadInstallationPhoto($fotoKontrak, $customer, 'kontrak');
        }
        if (! empty($updates)) {
            $customer->update($updates);
        }

        // FAB (Formulir Akan Berlangganan Bisnis) — wajib untuk paket
        // berkategori Bisnis (CustomerRegistrationRequest::rules()). Disimpan
        // lewat CustomerDocument (DocumentType::FAB) supaya konsisten dengan
        // jalur upload dokumen lain di Detail Pelanggan.
        if ($fabDocument instanceof UploadedFile) {
            $fabPath = FileUploadService::uploadCustomerRegistrationDoc($fabDocument, $customer, 'fab');

            CustomerDocument::create([
                'customer_id' => $customer->id,
                'document_type' => DocumentType::FAB->value,
                'file_path' => $fabPath,
                'uploaded_by' => $actor->id,
            ]);
        }

        $this->createAddress($customer, $validated);
        $this->createService($customer, $validated, $serviceStatus, $jenisKontrak);

        return $customer->load('customerService');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createAddress(Customer $customer, array $validated): void
    {
        $cityName = ! empty($validated['city_id']) ? City::where('id', $validated['city_id'])->value('name') : null;
        $districtName = ! empty($validated['district_id']) ? District::where('id', $validated['district_id'])->value('name') : null;
        $villageName = ! empty($validated['village_id']) ? Village::where('id', $validated['village_id'])->value('name') : null;

        $customer->customerAddress()->create([
            'full_address' => $validated['address'] ?? null,
            'province' => 'Jawa Timur',
            'city' => $cityName,
            'district' => $districtName,
            'village' => $villageName,
            'city_id' => $validated['city_id'] ?? null,
            'district_id' => $validated['district_id'] ?? null,
            'village_id' => $validated['village_id'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            // Foto sudah dicabut dari $validated di create() — kolom ini
            // memang selalu null di jalur registrasi (perilaku lama
            // dipertahankan apa adanya saat ekstraksi).
            'house_photo' => $validated['foto_rumah'] ?? null,
            'contract_photo' => $validated['foto_kontrak'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createService(Customer $customer, array $validated, string $serviceStatus, ?string $jenisKontrak): void
    {
        if (empty($validated['internet_package_id'])) {
            return;
        }

        $package = InternetPackage::findOrFail($validated['internet_package_id']);

        $monthlyPrice = (float) $package->monthly_price;
        $discount = (float) ($validated['discount_amount'] ?? 0.00);
        $ppn = (float) ($validated['tax_percent'] ?? 0.00);
        $otherFee = (float) ($validated['other_fee'] ?? 0.00);

        // total_monthly_bill SENGAJA TIDAK ikutkan $otherFee (2026-09-14).
        // Wajib murni tagihan bulanan berulang: GenerateMonthlyInvoicesCommand
        // cuma baca monthly_price+discount+ppn, other_fee (materai dkk) cuma
        // sekali di Tagihan Awal (InitialInvoiceService::calculate()). Dulu
        // di-fold ke sini dan bikin field ini berbohong soal nominal bulanan
        // (temuan nyata: CID C1X4ARQ000004). Kolom other_fee TETAP disimpan.
        $discountedPrice = max(0, $monthlyPrice - $discount);
        $totalBill = $discountedPrice * (1 + $ppn / 100);

        $downLabel = isset($package->download_speed_mbps) ? $package->download_speed_mbps.' Mbps' : null;
        $upLabel = isset($package->upload_speed_mbps) ? $package->upload_speed_mbps.' Mbps' : null;

        $activationDate = $validated['registration_date'] ?? null;
        $dueDate = $activationDate ? Carbon::parse($activationDate)->addMonth()->format('Y-m-d') : null;

        $customer->customerService()->create([
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'download_speed_snapshot' => $downLabel,
            'upload_speed_snapshot' => $upLabel,
            'monthly_price' => $monthlyPrice,
            'discount' => $discount,
            'ppn' => $ppn,
            'other_fee' => $otherFee,
            'total_monthly_bill' => $totalBill,
            'activation_date' => $activationDate,
            'due_date' => $dueDate,
            'billing_cycle' => 'monthly',
            'service_status' => $serviceStatus,
            'billing_status' => ($validated['status'] === 'active' || $serviceStatus === 'aktif') ? 'active' : 'pending',
            'contract_type' => $jenisKontrak,
        ]);
    }
}
